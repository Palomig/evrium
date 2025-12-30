package ru.evrium.notifier

import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.provider.Settings
import android.text.TextUtils
import android.widget.Button
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.util.concurrent.TimeUnit

class MainActivity : AppCompatActivity() {

    companion object {
        private const val PREFS_NAME = "evrium_notifier_prefs"
        private const val KEY_WEBHOOK_URL = "webhook_url"
        private const val KEY_TOKEN = "api_token"
    }

    private lateinit var statusText: TextView
    private lateinit var webhookUrlEdit: EditText
    private lateinit var tokenEdit: EditText
    private lateinit var permissionButton: Button
    private lateinit var saveButton: Button
    private lateinit var testButton: Button

    private val client = OkHttpClient.Builder()
        .connectTimeout(30, TimeUnit.SECONDS)
        .build()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        statusText = findViewById(R.id.statusText)
        webhookUrlEdit = findViewById(R.id.webhookUrlEdit)
        tokenEdit = findViewById(R.id.tokenEdit)
        permissionButton = findViewById(R.id.permissionButton)
        saveButton = findViewById(R.id.saveButton)
        testButton = findViewById(R.id.testButton)

        // Загружаем сохраненные настройки
        loadSettings()

        permissionButton.setOnClickListener {
            openNotificationSettings()
        }

        saveButton.setOnClickListener {
            saveSettings()
        }

        testButton.setOnClickListener {
            testWebhook()
        }
    }

    override fun onResume() {
        super.onResume()
        updateStatus()
    }

    private fun updateStatus() {
        val enabled = isNotificationServiceEnabled()
        if (enabled) {
            statusText.text = "✅ Доступ к уведомлениям разрешён"
            statusText.setTextColor(getColor(android.R.color.holo_green_light))
            permissionButton.text = "Проверить настройки"
        } else {
            statusText.text = "❌ Нужен доступ к уведомлениям"
            statusText.setTextColor(getColor(android.R.color.holo_red_light))
            permissionButton.text = "Дать разрешение"
        }
    }

    private fun isNotificationServiceEnabled(): Boolean {
        val flat = Settings.Secure.getString(contentResolver, "enabled_notification_listeners")
        if (!TextUtils.isEmpty(flat)) {
            val names = flat.split(":").toTypedArray()
            for (name in names) {
                val cn = ComponentName.unflattenFromString(name)
                if (cn != null && cn.packageName == packageName) {
                    return true
                }
            }
        }
        return false
    }

    private fun openNotificationSettings() {
        AlertDialog.Builder(this)
            .setTitle("Доступ к уведомлениям")
            .setMessage("Найдите 'Evrium Notifier' в списке и включите доступ к уведомлениям")
            .setPositiveButton("Открыть настройки") { _, _ ->
                startActivity(Intent(Settings.ACTION_NOTIFICATION_LISTENER_SETTINGS))
            }
            .setNegativeButton("Отмена", null)
            .show()
    }

    private fun loadSettings() {
        val prefs = getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        webhookUrlEdit.setText(prefs.getString(KEY_WEBHOOK_URL, "https://эвриум.рф/zarplata/api/incoming_payments.php"))
        tokenEdit.setText(prefs.getString(KEY_TOKEN, ""))
    }

    private fun saveSettings() {
        val webhookUrl = webhookUrlEdit.text.toString().trim()
        val token = tokenEdit.text.toString().trim()

        if (webhookUrl.isEmpty()) {
            Toast.makeText(this, "Введите URL webhook", Toast.LENGTH_SHORT).show()
            return
        }

        val prefs = getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        prefs.edit()
            .putString(KEY_WEBHOOK_URL, webhookUrl)
            .putString(KEY_TOKEN, token)
            .apply()

        Toast.makeText(this, "✅ Настройки сохранены", Toast.LENGTH_SHORT).show()
    }

    private fun testWebhook() {
        val webhookUrl = webhookUrlEdit.text.toString().trim()
        val token = tokenEdit.text.toString().trim()

        if (webhookUrl.isEmpty()) {
            Toast.makeText(this, "Введите URL webhook", Toast.LENGTH_SHORT).show()
            return
        }

        testButton.isEnabled = false
        testButton.text = "Отправка..."

        CoroutineScope(Dispatchers.IO).launch {
            try {
                val json = JSONObject().apply {
                    put("notification", "TEST Перевод по СБП от ТЕСТ ТЕСТОВИЧ Тест-Банк +1000 ₽")
                    put("title", "Перевод от ТЕСТ ТЕСТОВИЧ")
                    put("text", "+1000 ₽ Счёт карты VISA •• 1234")
                    put("bigText", "+1000 ₽ Счёт карты VISA •• 1234")
                    put("source", "evrium_android_app_test")
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

                withContext(Dispatchers.Main) {
                    testButton.isEnabled = true
                    testButton.text = "Тестовый запрос"

                    if (response.isSuccessful) {
                        Toast.makeText(
                            this@MainActivity,
                            "✅ Успешно! Проверьте логи на сайте",
                            Toast.LENGTH_LONG
                        ).show()
                    } else {
                        Toast.makeText(
                            this@MainActivity,
                            "❌ Ошибка: ${response.code}\n$responseBody",
                            Toast.LENGTH_LONG
                        ).show()
                    }
                }
            } catch (e: Exception) {
                withContext(Dispatchers.Main) {
                    testButton.isEnabled = true
                    testButton.text = "Тестовый запрос"
                    Toast.makeText(
                        this@MainActivity,
                        "❌ Ошибка: ${e.message}",
                        Toast.LENGTH_LONG
                    ).show()
                }
            }
        }
    }
}
