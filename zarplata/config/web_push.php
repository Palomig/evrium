<?php
/**
 * Web Push / VAPID implementation — pure PHP, no Composer.
 *
 * Implements:
 *  - RFC 8292 VAPID (ES256 JWT)
 *  - RFC 8291 message encryption (ECDH + AES-128-GCM)
 *
 * Requirements: PHP 8.0+, ext-openssl, ext-curl
 *
 * Usage:
 *   $push = new VapidPush(VAPID_PUBLIC, VAPID_PRIVATE, 'mailto:admin@evrium.ru');
 *   $push->send($subscription, ['title' => 'Урок через 10 минут', 'body' => '...', 'url' => '/zarplata/mobile/lessons.php']);
 */

class VapidPush
{
    private string $publicKey;   // base64url, 88 chars (~65 bytes uncompressed P-256 point)
    private string $privateKey;  // base64url, 44 chars (32 bytes raw scalar)
    private string $subject;     // mailto: or https://

    public function __construct(string $publicKey, string $privateKey, string $subject)
    {
        $this->publicKey  = $publicKey;
        $this->privateKey = $privateKey;
        $this->subject    = $subject;
    }

    /**
     * Send a push notification to a single subscription.
     *
     * @param array $subscription ['endpoint'=>'...', 'p256dh'=>'...', 'auth'=>'...']
     * @param array $payload      ['title'=>'...', 'body'=>'...', 'url'=>'...', 'icon'=>'...']
     * @return bool
     */
    public function send(array $subscription, array $payload): bool
    {
        $endpoint = $subscription['endpoint'];
        $p256dh   = $subscription['p256dh'];
        $auth     = $subscription['auth'];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        // Encrypt payload (RFC 8291)
        try {
            $encrypted = $this->encrypt($json, $p256dh, $auth);
        } catch (Exception $e) {
            error_log('[WebPush] Encryption failed: ' . $e->getMessage());
            return false;
        }

        // Build VAPID JWT
        $parsedUrl = parse_url($endpoint);
        $audience  = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        try {
            $jwt = $this->createJwt($audience);
        } catch (Exception $e) {
            error_log('[WebPush] JWT creation failed: ' . $e->getMessage());
            return false;
        }

        $vapidHeader = 'vapid t=' . $jwt . ', k=' . $this->publicKey;

        // Send via cURL
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POSTFIELDS     => $encrypted['ciphertext'],
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: 2419200',
                'Urgency: normal',
                'Authorization: ' . $vapidHeader,
            ],
        ]);

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('[WebPush] cURL error: ' . $error);
            return false;
        }

        // 201 = Created (success), 410 = Gone (unsubscribed), 404 = Not found
        if ($status === 201 || $status === 200) {
            return true;
        }

        error_log('[WebPush] Push failed. Status: ' . $status . ' Response: ' . $response);
        return false;
    }

    /**
     * Like send(), but returns detailed result instead of a boolean.
     * Useful for diagnostics (tells you the exact HTTP status + body).
     *
     * @return array ['status' => int, 'ok' => bool, 'error' => ?string, 'response' => ?string]
     */
    public function sendDetailed(array $subscription, array $payload): array
    {
        $endpoint = $subscription['endpoint'];
        $p256dh   = $subscription['p256dh'];
        $auth     = $subscription['auth'];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        try {
            $encrypted = $this->encrypt($json, $p256dh, $auth);
        } catch (Exception $e) {
            return ['status' => 0, 'ok' => false, 'error' => 'encrypt: ' . $e->getMessage(), 'response' => null];
        }

        $parsedUrl = parse_url($endpoint);
        $audience  = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        try {
            $jwt = $this->createJwt($audience);
        } catch (Exception $e) {
            return ['status' => 0, 'ok' => false, 'error' => 'jwt: ' . $e->getMessage(), 'response' => null];
        }

        $vapidHeader = 'vapid t=' . $jwt . ', k=' . $this->publicKey;

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POSTFIELDS     => $encrypted['ciphertext'],
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: 2419200',
                'Urgency: normal',
                'Authorization: ' . $vapidHeader,
            ],
        ]);

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['status' => 0, 'ok' => false, 'error' => 'curl: ' . $error, 'response' => null];
        }

        $ok = ($status === 201 || $status === 200);
        return [
            'status'   => (int) $status,
            'ok'       => $ok,
            'error'    => $ok ? null : 'HTTP ' . $status,
            'response' => is_string($response) ? substr($response, 0, 500) : null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // VAPID JWT (ES256)
    // ─────────────────────────────────────────────────────────────────────

    private function createJwt(string $audience): string
    {
        $header  = $this->b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims  = $this->b64u(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,  // 12 hours
            'sub' => $this->subject,
        ]));

        $signingInput = $header . '.' . $claims;

        // Build P-256 private key PEM from raw 32-byte scalar
        $privKeyBytes = $this->b64uDecode($this->privateKey);
        $pubKeyBytes  = $this->b64uDecode($this->publicKey);  // 65-byte uncompressed point

        $pem = $this->buildP256PrivKeyPem($privKeyBytes, $pubKeyBytes);
        $pkey = openssl_pkey_get_private($pem);
        if (!$pkey) {
            throw new RuntimeException('Failed to load VAPID private key: ' . openssl_error_string());
        }

        // Sign → DER-encoded ECDSA signature
        openssl_sign($signingInput, $derSig, $pkey, OPENSSL_ALGO_SHA256);

        // Convert DER SEQUENCE {r, s} → raw 64-byte R||S
        $rs = $this->derSigToRS($derSig);

        return $signingInput . '.' . $this->b64u($rs);
    }

    /**
     * Build an SEC1 ECPrivateKey PEM from raw bytes.
     * SEC1 DER: SEQUENCE { version INTEGER, privateKey OCTET STRING, [0] OID, [1] pubKey }
     */
    private function buildP256PrivKeyPem(string $privBytes, string $pubBytes): string
    {
        // P-256 OID: 1.2.840.10045.3.1.7
        $oid = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

        // [0] EXPLICIT namedCurve
        $namedCurve = "\xa0\x0a" . $oid;  // tag + len + OID

        // [1] EXPLICIT publicKey (BIT STRING, 0 unused bits)
        $pubKeyBitStr = "\x03\x42\x00" . $pubBytes;  // BIT STRING tag, len=66, unused=0
        $publicKeyCtx = "\xa1\x44" . $pubKeyBitStr;   // [1] tag, len=68

        // INTEGER version = 1
        $version = "\x02\x01\x01";

        // OCTET STRING (private key, 32 bytes)
        $privOctet = "\x04\x20" . $privBytes;

        $inner = $version . $privOctet . $namedCurve . $publicKeyCtx;
        $der   = "\x30" . $this->derLen(strlen($inner)) . $inner;

        return "-----BEGIN EC PRIVATE KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END EC PRIVATE KEY-----\n";
    }

    /**
     * DER-encode a length value (short or long form).
     */
    private function derLen(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }
        if ($len < 0x100) {
            return "\x81" . chr($len);
        }
        return "\x82" . chr(($len >> 8) & 0xff) . chr($len & 0xff);
    }

    /**
     * Convert DER-encoded ECDSA signature to raw 64-byte R||S.
     */
    private function derSigToRS(string $derSig): string
    {
        $offset = 0;
        if (ord($derSig[$offset++]) !== 0x30) {
            throw new RuntimeException('Invalid DER signature: missing SEQUENCE');
        }
        // Skip length
        $len = ord($derSig[$offset++]);
        if ($len & 0x80) {
            $offset += ($len & 0x7f);
        }

        // Integer R
        if (ord($derSig[$offset++]) !== 0x02) {
            throw new RuntimeException('Invalid DER signature: missing INTEGER r');
        }
        $rLen = ord($derSig[$offset++]);
        $r    = substr($derSig, $offset, $rLen);
        $offset += $rLen;

        // Integer S
        if (ord($derSig[$offset++]) !== 0x02) {
            throw new RuntimeException('Invalid DER signature: missing INTEGER s');
        }
        $sLen = ord($derSig[$offset++]);
        $s    = substr($derSig, $offset, $sLen);

        // Strip leading zero (DER sign byte) and pad to 32 bytes
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    // ─────────────────────────────────────────────────────────────────────
    // RFC 8291 Message Encryption
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Encrypt a message for delivery to a push subscription.
     * Returns ['ciphertext' => string].
     */
    private function encrypt(string $plaintext, string $p256dh, string $auth): array
    {
        // Decode subscription keys
        $receiverPublicKeyBytes = $this->b64uDecode($p256dh);  // 65 bytes uncompressed
        $authSecret             = $this->b64uDecode($auth);     // 16 bytes

        // Generate ephemeral ECDH key pair on P-256
        $ephemeralKey = openssl_pkey_new([
            'curve_name'        => 'prime256v1',
            'private_key_type'  => OPENSSL_KEYTYPE_EC,
        ]);
        if (!$ephemeralKey) {
            throw new RuntimeException('Failed to generate ephemeral EC key: ' . openssl_error_string());
        }

        // Get ephemeral public key bytes (uncompressed, 65 bytes)
        $ephemeralPubKeyBytes = $this->ecKeyToUncompressedPoint($ephemeralKey);

        // Load receiver public key
        $receiverPubKeyPem = $this->buildP256PubKeyPem($receiverPublicKeyBytes);
        $receiverPubKey    = openssl_pkey_get_public($receiverPubKeyPem);
        if (!$receiverPubKey) {
            throw new RuntimeException('Failed to load receiver public key: ' . openssl_error_string());
        }

        // ECDH shared secret
        $sharedSecret = openssl_pkey_derive($receiverPubKey, $ephemeralKey);
        if ($sharedSecret === false) {
            throw new RuntimeException('ECDH failed: ' . openssl_error_string());
        }

        // RFC 8291 key derivation
        $salt = random_bytes(16);

        // Step 1: PRK_key = HKDF-Extract(auth_secret, ecdh_secret)
        $prkKey = $this->hkdfExtract($authSecret, $sharedSecret);

        // Step 2: IKM = HKDF-Expand(PRK_key, "WebPush: info\0" || ua_public || as_public, 32)
        $keyInfo = "WebPush: info\x00" . $receiverPublicKeyBytes . $ephemeralPubKeyBytes;
        $ikm     = $this->hkdfExpand($prkKey, $keyInfo, 32);

        // Step 3: PRK = HKDF-Extract(salt, IKM) — for content key and nonce (RFC 8188)
        $prk = $this->hkdfExtract($salt, $ikm);

        // Content encryption key (16 bytes) and nonce (12 bytes)
        // hkdfExpand() already appends the counter byte, so info ends with \x00 only.
        $cek   = $this->hkdfExpand($prk, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = $this->hkdfExpand($prk, "Content-Encoding: nonce\x00", 12);

        // Pad plaintext: append \x02 delimiter (end-of-data marker)
        $paddedPlaintext = $plaintext . "\x02";

        // Encrypt with AES-128-GCM
        $tag        = '';
        $ciphertext = openssl_encrypt($paddedPlaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($ciphertext === false) {
            throw new RuntimeException('AES-128-GCM encryption failed: ' . openssl_error_string());
        }

        // Build RFC 8291 header:
        // salt (16) + rs (4 = record size = 4096) + idlen (1 = 65) + keyid (65 = ephemeral pub)
        $rs     = pack('N', 4096);  // record size = 4096
        $idlen  = chr(65);
        $header = $salt . $rs . $idlen . $ephemeralPubKeyBytes;

        return ['ciphertext' => $header . $ciphertext . $tag];
    }

    /**
     * Extract the uncompressed P-256 public key point (65 bytes) from an OpenSSL key.
     */
    private function ecKeyToUncompressedPoint($pkey): string
    {
        $details = openssl_pkey_get_details($pkey);
        if (!$details || !isset($details['ec'])) {
            throw new RuntimeException('Not an EC key');
        }
        // x and y are raw bytes; pad to 32 bytes each
        $x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        return "\x04" . $x . $y;
    }

    /**
     * Build a SubjectPublicKeyInfo PEM from a raw P-256 uncompressed point.
     */
    private function buildP256PubKeyPem(string $pubBytes): string
    {
        // SubjectPublicKeyInfo DER:
        // SEQUENCE {
        //   SEQUENCE { OID id-ecPublicKey, OID prime256v1 }
        //   BIT STRING { 0x00, <pubBytes> }
        // }
        $algOid  = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"; // id-ecPublicKey
        $curveOid = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // prime256v1
        $algSeq  = "\x30" . $this->derLen(strlen($algOid) + strlen($curveOid)) . $algOid . $curveOid;
        $bitStr  = "\x03" . $this->derLen(1 + strlen($pubBytes)) . "\x00" . $pubBytes;
        $inner   = $algSeq . $bitStr;
        $der     = "\x30" . $this->derLen(strlen($inner)) . $inner;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    // ─────────────────────────────────────────────────────────────────────
    // HKDF (RFC 5869, SHA-256)
    // ─────────────────────────────────────────────────────────────────────

    private function hkdfExtract(string $salt, string $ikm): string
    {
        return hash_hmac('sha256', $ikm, $salt, true);
    }

    private function hkdfExpand(string $prk, string $info, int $length): string
    {
        $t   = '';
        $okm = '';
        for ($i = 1; strlen($okm) < $length; $i++) {
            $t    = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
            $okm .= $t;
        }
        return substr($okm, 0, $length);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Base64URL helpers
    // ─────────────────────────────────────────────────────────────────────

    private function b64u(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function b64uDecode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) + (4 - strlen($data) % 4) % 4, '=');
        return base64_decode($padded);
    }
}

/**
 * Generate a new VAPID key pair and return ['public' => '...', 'private' => '...'] in base64url.
 */
function vapidGenerateKeys(): array
{
    $key = openssl_pkey_new([
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);

    $details = openssl_pkey_get_details($key);

    // Private key scalar (32 bytes)
    $privBytes = str_pad($details['ec']['d'], 32, "\x00", STR_PAD_LEFT);
    $x         = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
    $y         = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    $pubBytes  = "\x04" . $x . $y;  // 65 bytes uncompressed point

    $b64u = fn($d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

    return [
        'public'  => $b64u($pubBytes),
        'private' => $b64u($privBytes),
    ];
}
