<?php
/**
 * SQLite Database Configuration for OGE Geometry Table
 */

// Database file path
define('DB_PATH', __DIR__ . '/../data/oge_geometry.sqlite');

/**
 * Get PDO connection to SQLite database
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        // Create data directory if not exists
        $dataDir = dirname(DB_PATH);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Enable foreign keys
        $pdo->exec('PRAGMA foreign_keys = ON');

        // Initialize tables if needed
        initDatabase($pdo);
    }

    return $pdo;
}

/**
 * Initialize database schema
 */
function initDatabase(PDO $pdo): void {
    // Create prototypes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS prototypes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            num VARCHAR(50) NOT NULL,
            type VARCHAR(255) NOT NULL,
            method TEXT NOT NULL,
            image TEXT DEFAULT '',
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create index for sorting
    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_prototypes_sort ON prototypes(sort_order)
    ");

    // Check if table is empty and seed with default data
    $count = $pdo->query("SELECT COUNT(*) FROM prototypes")->fetchColumn();
    if ($count == 0) {
        seedDefaultData($pdo);
    }

    // Backfill built-in diagrams for old databases where rows already exist
    // but image paths were empty. Uploaded/custom images are not overwritten.
    updateMissingDefaultImages($pdo);
}

/**
 * Built-in SVG diagrams for public prototype rows.
 */
function defaultPrototypeImages(): array {
    return [
        '1-6' => 'assets/prototypes/prototype-01-06.svg',
        '7-12' => 'assets/prototypes/prototype-07-12.svg',
        '13-18' => 'assets/prototypes/prototype-13-18.svg',
        '19-22' => 'assets/prototypes/prototype-19-22.svg',
        '23-27 и 28-32' => 'assets/p23/oge15_p23_img1.png',
        '33-38' => 'assets/prototypes/prototype-33-38.svg',
        '39-44' => 'assets/prototypes/prototype-39-44.svg',
        '45-50' => 'assets/prototypes/prototype-45-50.svg',
        '51-56' => 'assets/prototypes/prototype-51-56.svg',
        '57-68' => 'assets/prototypes/prototype-57-68.svg',
        '69-74' => 'assets/prototypes/prototype-69-74.svg',
        '75-79 и 80-84' => 'assets/prototypes/prototype-75-84.svg',
        '85-92' => 'assets/prototypes/prototype-85-92.svg',
        '93-97 и 98-102' => 'assets/prototypes/prototype-93-102.svg',
        '103-108 и 109-114' => 'assets/prototypes/prototype-103-114.svg',
        '115-120' => 'assets/prototypes/prototype-115-120.svg',
    ];
}

/**
 * Fill missing images for default rows without touching custom uploads.
 */
function updateMissingDefaultImages(PDO $pdo): void {
    $stmt = $pdo->prepare("UPDATE prototypes SET image = ?, updated_at = CURRENT_TIMESTAMP WHERE num = ? AND (image IS NULL OR image = '' OR image LIKE 'assets/prototypes/prototype-%')");
    foreach (defaultPrototypeImages() as $num => $image) {
        $stmt->execute([$image, $num]);
    }
}

/**
 * Seed default data
 */
function seedDefaultData(PDO $pdo): void {
    $defaultData = [
        [
            'num' => '1-6',
            'type' => 'Параллелограмм и биссектриса угла',
            'method' => 'Свойство биссектрисы: биссектриса угла параллелограмма (AK) отсекает равнобедренный треугольник △ABK. Доказательство через равенство углов: ∠BAK = ∠KAD (биссектриса) и ∠KAD = ∠BKA (накрест лежащие). Следовательно, AB = BK. Метод: найти AB (=BK), затем BC = BK + KC, периметр P = 2(AB + BC).'
        ],
        [
            'num' => '7-12',
            'type' => 'Ромб: углы по расстоянию до центра',
            'method' => 'Свойства ромба и прямоугольного треугольника: расстояние от точки пересечения диагоналей O до стороны OH — высота. Диагональ делится пополам: OB = BD/2. Метод: в △BOH найти sin∠OBH = OH/OB = ½, значит угол 30°. Углы ромба: 60° и 120°.'
        ],
        [
            'num' => '13-18',
            'type' => 'Ромб: высота через отрезки стороны',
            'method' => 'Свойства ромба и Пифагор: сторона CD = DH + HC, все стороны равны. Метод: определить сторону ромба AD (=CD). Применить теорему Пифагора в прямоугольном △ADH для нахождения высоты AH.'
        ],
        [
            'num' => '19-22',
            'type' => 'Прямоугольный треугольник: медиана к гипотенузе',
            'method' => 'Свойство: медиана к гипотенузе равна её половине. Метод: найти гипотенузу AB по теореме Пифагора, затем разделить её пополам.'
        ],
        [
            'num' => '23-27 и 28-32',
            'type' => 'Прямоугольный треугольник: высота к гипотенузе',
            'method' => 'Метод площадей: S = ½ab (через катеты) и S = ½ch (через гипотенузу и высоту). Метод: найти третью сторону по Пифагору, затем выразить высоту h = ab/c.'
        ],
        [
            'num' => '33-38',
            'type' => 'Трапеция: биссектрисы при боковой стороне',
            'method' => 'Свойства односторонних углов: биссектрисы перпендикулярны, так как 2x + 2y = 180° ⇒ x + y = 90°. Метод: доказать, что △ABF — прямоугольный, найти сторону AB по Пифагору.'
        ],
        [
            'num' => '39-44',
            'type' => 'Треугольник: параллельный отрезок внутри',
            'method' => 'Подобие треугольников (прямое): при MN ∥ AC, △MBN ~ △ABC. Метод: доказать подобие по углам и составить пропорцию MN/AC = BN/BC.'
        ],
        [
            'num' => '45-50',
            'type' => 'Пересечение диагоналей («Бабочка»)',
            'method' => 'Подобие треугольников (перевёрнутое): △ABM ~ △CDM. Метод: подобие по вертикальным и накрест лежащим углам, пропорция AM/CM = AB/CD.'
        ],
        [
            'num' => '51-56',
            'type' => 'Прямоугольный треугольник: катет через проекцию',
            'method' => 'Свойство высоты: квадрат катета равен произведению гипотенузы на проекцию катета. Метод: использовать формулу AB² = AH · AC.'
        ],
        [
            'num' => '57-68',
            'type' => 'Трапеция: боковые стороны и углы (сдвиг)',
            'method' => 'Инвариант — высота: высота трапеции одинакова. Метод: провести две высоты, в одном прямоугольном треугольнике найти h через тригонометрию, затем перенести её и найти сторону AB.'
        ],
        [
            'num' => '69-74',
            'type' => 'Трапеция: отрезок, параллельный основаниям',
            'method' => 'Подобие + теорема Фалеса: провести диагональ. Метод: найти части диагонали через подобие, перенести соотношение по Фалесу, сложить отрезки: EF равен их сумме.'
        ],
        [
            'num' => '75-79 и 80-84',
            'type' => 'Окружность: две хорды и расстояние до центра',
            'method' => 'Инвариант — радиус. Метод: найти радиус по Пифагору для первой хорды, затем использовать его для второй хорды или расстояния.'
        ],
        [
            'num' => '85-92',
            'type' => 'Окружность на высоте (прямоугольник)',
            'method' => 'Свойство вписанного угла: угол, опирающийся на диаметр, равен 90°. Метод: доказать, что BPHK — прямоугольник, следовательно, диагонали равны: PK = BH.'
        ],
        [
            'num' => '93-97 и 98-102',
            'type' => 'Окружность: «перевернутое подобие»',
            'method' => 'Вписанный четырёхугольник и подобие: △AKP ~ △ABC. Метод: подобие по общему углу и равенству углов, составить пропорцию и найти KP.'
        ],
        [
            'num' => '103-108 и 109-114',
            'type' => 'Окружность: касательная, центр на стороне',
            'method' => 'Касательная и секущая / Пифагор. Метод 1: AB² = AP · AC, найти диаметр. Метод 2: найти радиус, затем применить Пифагор в △AOB, получить AC = AO + R.'
        ],
        [
            'num' => '115-120',
            'type' => 'Треугольник: сторона через радиус описанной окружности',
            'method' => 'Расширенная теорема синусов: a/sin A = 2R. Метод: найти угол A, затем по теореме синусов определить сторону BC.'
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO prototypes (num, type, method, image, sort_order)
        VALUES (?, ?, ?, '', ?)
    ");

    foreach ($defaultData as $index => $row) {
        $stmt->execute([
            $row['num'],
            $row['type'],
            $row['method'],
            $index
        ]);
    }
}

/**
 * Helper: Execute query and return all rows
 */
function dbQuery(string $sql, array $params = []): array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Helper: Execute query and return single row
 */
function dbQueryOne(string $sql, array $params = []): ?array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Helper: Execute INSERT/UPDATE/DELETE
 */
function dbExecute(string $sql, array $params = []): int|string {
    $pdo = getDB();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Return last insert ID for INSERT, or affected rows for UPDATE/DELETE
    $lastId = $pdo->lastInsertId();
    if ($lastId && $lastId !== '0') {
        return (int)$lastId;
    }
    return $stmt->rowCount();
}
