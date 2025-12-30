package ru.evrium.notifier

import android.app.Notification
import android.content.Context
import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import android.util.Log
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.util.concurrent.TimeUnit

class SberbankNotificationService : NotificationListenerService() {

    companion object {
        private const val TAG = "SberbankNotifier"
        private const val SBERBANK_PACKAGE = "ru.sberbankmobile"
        private const val PREFS_NAME = "evrium_notifier_prefs"
        private const val KEY_WEBHOOK_URL = "webhook_url"
        private const val KEY_TOKEN = "api_token"
    }

    private val client = OkHttpClient.Builder()
        .connectTimeout(30, TimeUnit.SECONDS)
        .writeTimeout(30, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .build()

    private val scope = CoroutineScope(Dispatchers.IO)

    override fun onNotificationPosted(sbn: StatusBarNotification) {
        // Фильтруем только уведомления от Сбербанка
        if (sbn.packageName != SBERBANK_PACKAGE) {
            return
        }

        val notification = sbn.notification ?: return
        val extras = notification.extras ?: return

        // Извлекаем все возможные текстовые поля
        val title = extras.getCharSequence(Notification.EXTRA_TITLE)?.toString() ?: ""
        val text = extras.getCharSequence(Notification.EXTRA_TEXT)?.toString() ?: ""
        val bigText = extras.getCharSequence(Notification.EXTRA_BIG_TEXT)?.toString() ?: ""
        val subText = extras.getCharSequence(Notification.EXTRA_SUB_TEXT)?.toString() ?: ""
        val infoText = extras.getCharSequence(Notification.EXTRA_INFO_TEXT)?.toString() ?: ""
        val summaryText = extras.getCharSequence(Notification.EXTRA_SUMMARY_TEXT)?.toString() ?: ""
        val tickerText = notification.tickerText?.toString() ?: ""

        Log.d(TAG, "Sberbank notification received:")
        Log.d(TAG, "  title: $title")
        Log.d(TAG, "  text: $text")
        Log.d(TAG, "  bigText: $bigText")
        Log.d(TAG, "  subText: $subText")
        Log.d(TAG, "  infoText: $infoText")
        Log.d(TAG, "  summaryText: $summaryText")
        Log.d(TAG, "  tickerText: $tickerText")

        // Формируем полное сообщение
        val fullMessage = buildString {
            if (title.isNotEmpty()) append(title)
            if (text.isNotEmpty()) {
                if (isNotEmpty()) append(" ")
                append(text)
            }
            if (bigText.isNotEmpty() && bigText != text) {
                if (isNotEmpty()) append(" ")
                append(bigText)
            }
        }

        if (fullMessage.isEmpty()) {
            Log.w(TAG, "Empty notification, skipping")
            return
        }

        // Отправляем на webhook
        sendToWebhook(
            title = title,
            text = text,
            bigText = bigText,
            subText = subText,
            tickerText = tickerText,
            fullMessage = fullMessage
        )
    }

    private fun sendToWebhook(
        title: String,
        text: String,
        bigText: String,
        subText: String,
        tickerText: String,
        fullMessage: String
    ) {
        val prefs = getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val webhookUrl = prefs.getString(KEY_WEBHOOK_URL, "") ?: ""
        val token = prefs.getString(KEY_TOKEN, "") ?: ""

        if (webhookUrl.isEmpty()) {
            Log.w(TAG, "Webhook URL not configured")
            return
        }

        scope.launch {
            try {
                val json = JSONObject().apply {
                    put("notification", fullMessage)
                    put("title", title)
                    put("text", text)
                    put("bigText", bigText)
                    put("subText", subText)
                    put("tickerText", tickerText)
                    put("source", "evrium_android_app")
                }

                val url = if (token.isNotEmpty()) {
                    "$webhookUrl?action=webhook&token=$token"
                } else {
                    webhookUrl
                }

                val body = json.toString().toRequestBody("application/json".toMediaType())
                val request = Request.Builder()
                    .url(url)
                    .post(body)
                    .build()

                val response = client.newCall(request).execute()
                val responseBody = response.body?.string()

                Log.d(TAG, "Webhook response: ${response.code} - $responseBody")

                if (response.isSuccessful) {
                    Log.i(TAG, "Notification sent successfully")
                } else {
                    Log.e(TAG, "Webhook error: ${response.code}")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Failed to send notification", e)
            }
        }
    }

    override fun onNotificationRemoved(sbn: StatusBarNotification) {
        // Не обрабатываем удаление уведомлений
    }
}
