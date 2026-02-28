<?php
require_once 'bot.php';

// Просто запускаем проверку событий
$bot->checkForNewEvents();
echo "Проверка завершена в " . date('Y-m-d H:i:s');
?>
