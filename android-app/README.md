# Evrium Notifier

Android-приложение для автоматической пересылки уведомлений Сбербанка на webhook сервера Zarplata.

## Функции

- Перехватывает уведомления от приложения Сбербанк
- Извлекает все текстовые поля (title, text, bigText и др.)
- Отправляет данные на webhook сервера

## Установка

1. Скачайте APK из раздела Releases или из GitHub Actions
2. Установите приложение
3. Откройте приложение
4. Нажмите "Дать разрешение" и включите доступ к уведомлениям
5. Введите токен из настроек сайта (https://эвриум.рф/zarplata/webhook_logs.php)
6. Нажмите "Сохранить настройки"
7. Проверьте работу кнопкой "Тестовый запрос"

## Сборка

```bash
cd android-app
./gradlew assembleDebug
```

APK будет в `app/build/outputs/apk/debug/app-debug.apk`

## Технологии

- Kotlin
- NotificationListenerService
- OkHttp для HTTP запросов
- Material Design
