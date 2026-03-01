<?php
// Конфигурация бота
define('BOT_TOKEN', 'test');
define('GROUP_ID', 'test'); // тест

define('ADMIN_IDS', ['admin1', 'admin2']); // ID администраторов

// WordPress конфигурация
define('WORDPRESS_URL', 'https://test-trophy.ru');
define('WORDPRESS_API_KEY', 'test'); // Опционально, если нужна авторизация
//define('WORDPRESS_USERNAME', '123'); // Для REST API
//define('WORDPRESS_PASSWORD', '123'); // Для REST API

// Настройки
define('EVENTS_POST_TYPE', 'event'); // Тип записи как в API
define('EVENTS_CATEGORY_ID', 0); // 0 = все категории
define('CHECK_INTERVAL_MINUTES', 5);
define('TIMEZONE', 'Asia/Yekaterinburg');

// Настройки приветственного сообщения
define('WELCOME_MESSAGE', "🎉 Добро пожаловать в тему события!\n\nЗдесь можно обсудить детали мероприятия, задать вопросы организаторам и скоординироваться с другими участниками.");
define('EVENT_MESSAGE_TEMPLATE', "📅 *{title}*\n\n📝 *Описание:*\n{excerpt}\n\n📌 *Дата проведения:* {date}\n📍 *Место:* {location}\n 🔗 *Ссылка на сайте:* {link}");

// Файлы
define('PROCESSED_EVENTS_FILE', __DIR__ . '/processed-events.txt');
define('LOG_FILE', __DIR__ . '/logs/bot.log');
define('INCOMING_LOG_FILE', __DIR__ . '/logs/incoming.log');
define('ERROR_LOG_FILE', __DIR__ . '/logs/error.log');

define('STORED_FILES_JSON', __DIR__ . '/stored_files.json');
// Создаем директорию для логов если не существует
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Устанавливаем временную зону
date_default_timezone_set(TIMEZONE);

// Включение отладки
define('DEBUG_MODE', true);

// Состояние встроенного интерфейса отправки (в Telegram)
define('COMPOSE_STATE_FILE', __DIR__ . '/compose_state.json');
?>
