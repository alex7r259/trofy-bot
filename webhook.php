<?php
require_once 'config.php';
require_once 'bot.php';

$bot = new TelegramEventBot();

// Получаем входящие данные от Telegram
$input = file_get_contents('php://input');
$update = json_decode($input, true);

// ЛОГИРОВАНИЕ: Записываем сырые данные для отладки (опционально)
if (DEBUG_MODE && !empty($input)) {
    $bot->writeLog("Raw webhook input received (length: " . strlen($input) . " chars)", 'DEBUG');
}

// ЛОГИРОВАНИЕ: Записываем входящее сообщение
if (!empty($update)) {
    $bot->logIncomingMessage($update);
}

// Обработка загруженных файлов (для всех пользователей)
if (!empty($update) && isset($update['message'])) {
    $message = $update['message'];
    
    // Проверяем, содержит ли сообщение файл
    $hasFile = isset($message['photo']) || isset($message['document']) || 
               isset($message['video']) || isset($message['audio']) || 
               isset($message['voice']) || isset($message['sticker']);
    
    if ($hasFile) {
        // Сохраняем файл
        $fileInfo = $bot->handleUploadedFile($update);
        if ($fileInfo) {
            // Файл успешно сохранен, дальше обрабатываем как обычно
        }
    }
}

// Простая обработка команд от администраторов
if (!empty($update) && isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $userId = $message['from']['id'];
    $chatType = $message['chat']['type'] ?? 'private'; // private, group, supergroup, channel
    
    // Логируем информацию о сообщении
    $bot->writeLog("Message from user $userId in $chatType chat $chatId: " . substr($text, 0, 100), 'DEBUG');
    
    // Проверяем, что команда от администратора
    if (in_array($userId, ADMIN_IDS)) {
        // Обрезаем лишние пробелы
        $text = trim($text);
        
        // ОПРЕДЕЛЯЕМ КОМАНДЫ (включая команды для локальных файлов)
        $knownCommands = [
            '/start',
            '/check',
            '/stats',
            '/test',
            '/help',
            '/logs',
            '/logs_incoming',
            '/cleanup_logs',
            '/chats',
            '/files',
            '/send_local_photo',
            '/send_local_video',
            '/send_local_document',
            '/send_local_audio',
            '/send_local_voice',
            '/send_local_sticker',
            '/delete_file',
            '/cleanup_files',
            '/send_text'
        ];
        
        // Проверяем, является ли сообщение командой
        $isCommand = false;
        $command = '';
        
        foreach ($knownCommands as $cmd) {
            if (strpos($text, $cmd) === 0) {
                $isCommand = true;
                $command = $cmd;
                break;
            }
        }
        
        // Если это не известная команда - ИГНОРИРУЕМ
        if (!$isCommand) {
            // Если это похоже на команду (начинается с /), логируем но не отвечаем
            if (strpos($text, '/') === 0) {
                $bot->writeLog("Unknown command from admin $userId: $text", 'INFO');
                // НЕ ОТВЕЧАЕМ - просто игнорируем
            } else {
                // Обычное сообщение (не команда) - просто логируем
                $bot->writeLog("Regular message from admin $userId (not a command)", 'DEBUG');
            }
            http_response_code(200);
            echo 'OK';
            exit;
        }
        
        // ОБРАБОТКА ИЗВЕСТНЫХ КОМАНД
        $bot->writeLog("Processing admin command from $userId: $command", 'INFO');
        
        switch ($command) {
            case '/start':
                $response = "🤖 *Бот для создания тем из событий WordPress*\n\n";
                $response .= "📱 *Доступные команды:*\n\n";
                $response .= "*Основные:*\n";
                $response .= "/check - Проверить новые события\n";
                $response .= "/stats - Статистика бота\n";
                $response .= "/test - Тест подключений\n";
                $response .= "/chats - Список чатов\n\n";
                $response .= "*Отправка сообщений:*\n";
                $response .= "/send_text*-*<chat_id>*-*<текст>*-*[topic_id] - Отправить текст\n";
                $response .= "/send_local_photo <chat_id> <имя_файла> [caption] [topic_id] - Отправить фото\n";
                $response .= "/send_local_video <chat_id> <имя_файла> [caption] [topic_id] - Отправить видео\n";
                $response .= "/send_local_document <chat_id> <имя_файла> [caption] [topic_id] - Отправить документ\n";
                $response .= "/delete_file <имя_файла> - Удалить файл\n";
                $response .= "/cleanup_files - Очистить старые файлы\n\n";
                $response .= "*Логи:*\n";
                $response .= "/logs - Показать последние логи\n";
                $response .= "/logs_incoming - Показать входящие сообщения\n";
                $response .= "/cleanup_logs - Очистить старые логи\n\n";
                $response .= "/help - Подробная справка";
                $bot->sendMessage($chatId, $response, 'Markdown');
                $bot->writeLog("Sent /start response to admin $userId", 'INFO');
                break;
                
            case '/send_text':
                $bot->writeLog("Admin $userId sending text message", 'INFO');
                $parts = explode('*-*', $text, 4);
                if (count($parts) < 3) {
                    $response = "❌ *Неверный формат команды.*\n\n";
                    $response .= "*Использование:*\n";
                    $response .= "`/send_text*-*<chat_id>*-*<текст>*-*[topic_id]`\n\n";
                    $response .= "*Примеры:*\n";
                    $response .= "`/send_text*-*-100123456789*-*Привет, мир!`\n";
                    $response .= "`/send_text*-*-100123456789*-*Сообщение в топик*-*123`\n";
                    $bot->sendMessage($chatId, $response, 'Markdown');
                    break;
                }
                
                $targetChatId = $parts[1];
                $messageText = $parts[2];
                $topicId = count($parts) >= 4 ? $parts[3] : null;
                
                $result = $bot->sendMessage($targetChatId, $messageText, 'Markdown', null, $topicId);
                if ($result && isset($result['ok']) && $result['ok']) {
                    $response = "✅ Текст успешно отправлен в чат `$targetChatId`";
                    if ($topicId) {
                        $response .= " в топик `$topicId`";
                    }
                } else {
                    $error = isset($result['description']) ? $result['description'] : 'Неизвестная ошибка';
                    $response = "❌ Ошибка отправки текста: `$error`";
                }
                $bot->sendMessage($chatId, $response, 'Markdown');
                break;
                
            case '/files':
                $bot->writeLog("Admin $userId requested file list", 'INFO');
                $files = $bot->getLocalFiles();
                
                if (empty($files)) {
                    $response = "📁 <b>Сохраненные файлы:</b>\n\nНет сохраненных файлов.\n\nОтправьте файл боту, чтобы он сохранился автоматически.";
                } else {
                    $response = "📁 <b>Сохраненные файлы:</b>\n\n";
                    
                    // Группируем файлы по типам
                    $filesByType = [];
                    foreach ($files as $file) {
                        $type = $file['type'];
                        if (!isset($filesByType[$type])) {
                            $filesByType[$type] = [];
                        }
                        $filesByType[$type][] = $file;
                    }
                        
                    foreach ($filesByType as $type => $typeFiles) {
                        $typeName = getTypeName($type);
                        $response .= "<b>{$typeName}:</b>\n";
                        foreach ($typeFiles as $file) {
                            // Экранируем HTML-сущности
                            $fileName = htmlspecialchars($file['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $size = htmlspecialchars($file['size_formatted'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $response .= "  • <code>{$fileName}</code> ({$size})\n";
                        }
                        $response .= "\n";
                    }
                    
                    $response .= "Используйте команды /send_local_* для отправки файлов.";
                }
                
                $bot->sendMessage($chatId, $response, 'HTML');
                break;
            
            case '/send_local_photo':
                $bot->writeLog("Admin $userId sending local photo", 'INFO');
                $parts = explode(' ', $text, 5);
                if (count($parts) < 3) {
                    $response = "❌ *Неверный формат команды.*\n\n";
                    $response .= "*Использование:*\n";
                    $response .= "`/send_local_photo <chat_id> <имя_файла> [caption] [topic_id]`\n\n";
                    $response .= "*Примеры:*\n";
                    $response .= "`/send_local_photo -100123456789 photo.jpg \"Мое фото\"`\n";
                    $response .= "`/send_local_photo -100123456789 photo.jpg \"Фото в топик\" 123`\n\n";
                    $response .= "Используйте `/files` для просмотра доступных файлов.";
                    $bot->sendMessage($chatId, $response, 'Markdown');
                    break;
                }
                
                $targetChatId = $parts[1];
                $fileName = $parts[2];
                $caption = '';
                $topicId = null;
                
                if (count($parts) >= 4) {
                    // Проверяем, является ли 4-й параметр числом (topic_id) или строкой (caption)
                    if (is_numeric($parts[3]) && count($parts) == 4) {
                        $topicId = $parts[3];
                    } else {
                        $caption = $parts[3];
                        if (count($parts) >= 5) {
                            $topicId = $parts[4];
                        }
                    }
                }
                
                $filePath = $bot->uploadsDir . '/' . $fileName;
                
                if (!file_exists($filePath)) {
                    $response = "❌ Файл `$fileName` не найден.\n";
                    $response .= "Используйте `/files` для просмотра доступных файлов.";
                    $bot->sendMessage($chatId, $response, 'Markdown');
                    break;
                }
                
                $result = $bot->sendPhotoFromFile($targetChatId, $filePath, $caption, 'Markdown', null, $topicId);
                if ($result && isset($result['ok']) && $result['ok']) {
                    $response = "✅ Фото `$fileName` успешно отправлено в чат `$targetChatId`";
                    if ($topicId) {
                        $response .= " в топик `$topicId`";
                    }
                } else {
                    $error = isset($result['description']) ? $result['description'] : 'Неизвестная ошибка';
                    $response = "❌ Ошибка отправки фото: `$error`";
                }
                $bot->sendMessage($chatId, $response, 'Markdown');
                break;
                
            case '/send_local_video':
                $bot->writeLog("Admin $userId sending local video", 'INFO');
                $parts = explode(' ', $text, 5);
                if (count($parts) < 3) {
                    $response = "❌ *Неверный формат команды.*\n\n";
                    $response .= "*Использование:*\n";
                    $response .= "`/send_local_video <chat_id> <имя_файла> [caption] [topic_id]`\n\n";
                    $response .= "*Примеры:*\n";
                    $response .= "`/send_local_video -100123456789 video.mp4 \"Мое видео\"`\n";
                    $response .= "`/send_local_video -100123456789 video.mp4 \"Видео в топик\" 123`\n\n";
                    $response .= "Используйте `/files` для просмотра доступных файлов.";
                    $bot->sendMessage($chatId, $response, 'Markdown');
                    break;
                }
                
                $targetChatId = $parts[1];
                $fileName = $parts[2];
                $caption = '';
                $topicId = null;
                
                if (count($parts) >= 4) {
                    // Проверяем, является ли 4-й параметр числом (topic_id) или строкой (caption)
                    if (is_numeric($parts[3]) && count($parts) == 4) {
                        $topicId = $parts[3];
                    } else {
                        $caption = $parts[3];
                        if (count($parts) >= 5) {
                            $topicId = $parts[4];
                        }
                    }
                }
                
                $filePath = $bot->uploadsDir . '/' . $fileName;
                
                if (!file_exists($filePath)) {
                    $response = "❌ Файл `$fileName` не найден.\n";
                    $response .= "Используйте `/files` для просмотра доступных файлов.";
                    $bot->sendMessage($chatId, $response, 'Markdown');
                    break;
                }
                
                $result = $bot->sendVideoFromFile($targetChatId, $filePath, $caption, 'Markdown', null, $topicId);
                if ($result && isset($result['ok']) && $result['ok']) {
                    $response = "✅ Видео `$fileName` успешно отправлено в чат `$targetChatId`";
                    if ($topicId) {
                        $response .= " в топик `$topicId`";
                    }
                } else {
                    $error = isset($result['description']) ? $result['description'] : 'Неизвестная ошибка';
                    $response = "❌ Ошибка отправки видео: `$error`";
                }
                $bot->sendMessage($chatId, $response, 'Markdown');
                break;
                
            case '/send_local_document':
                $bot->writeLog("Admin $userId sending local document", 'INFO');
                $parts = explode(' ', $text, 5);
                if (count($parts) < 3) {
                    $response = "❌ *Неверный формат команды.*\n\n";
                    $response .= "*Использование:*\n";
                    $response .= "`/send_local_document <chat_id> <имя_файла> [caption] [topic_id]`\n\n";
                    $response .= "*Примеры:*\n";
                    $response .= "`/send_local_document -100123456789 document.pdf \"Документ\"`\n";
                    $response .= "`/send_local_document -100123456789 document.pdf \"Документ в топик\" 123`\n\n";
                    $response .= "Используйте `/files` для просмотра доступных файлов.";
                    $bot->sendMessage($chatId, $response, 'Markdown');
                    break;
                }
                
                $targetChatId = $parts[1];
                $fileName = $parts[2];
                $caption = '';
                $topicId = null;
                
                if (count($parts) >= 4) {
                    // Проверяем, является ли 4-й параметр числом (topic_id) или строкой (caption)
                    if (is_numeric($parts[3]) && count($parts) == 4) {
                        $topicId = $parts[3];
                    } else {
                        $caption = $parts[3];
                        if (count($parts) >= 5) {
                            $topicId = $parts[4];
                        }
                    }
                }
                
                $filePath = $bot->uploadsDir . '/' . $fileName;
                
                if (!file_exists($filePath)) {
                    $response = "❌ Файл `$fileName` не найден.\n";
                    $response .= "Используйте `/files` для просмотра доступных файлов.";
                    $bot->sendMessage($chatId, $response, 'Markdown');
                    break;
                }
                
                $result = $bot->sendDocumentFromFile($targetChatId, $filePath, $caption, 'Markdown', null, $topicId);
                if ($result && isset($result['ok']) && $result['ok']) {
                    $response = "✅ Документ `$fileName` успешно отправлен в чат `$targetChatId`";
                    if ($topicId) {
                        $response .= " в топик `$topicId`";
                    }
                } else {
                    $error = isset($result['description']) ? $result['description'] : 'Неизвестная ошибка';
                    $response = "❌ Ошибка отправки документа: `$error`";
                }
                $bot->sendMessage($chatId, $response, 'Markdown');
                break;
                
            case '/send_local_audio':
                $bot->writeLog("Admin $userId sending local audio", 'INFO');
                $parts = explode(' ', $text, 5);
                if (count($parts) < 3) {
                    $response = "❌ *Неверный формат команды.*\n\n";
                    $response .= "*Использование:*\n";
                    $response .= "`/send_local_audio <chat_id> <имя_файла> [caption] [topic_id]`\n\n";
                    $response .= "*Примеры:*\n";
                    $response .= "`/send_local_audio -100123456789 audio.mp3 \"Музыка\"`\n";
                    $response .= "`/send_local_audio -100123456789 audio.mp3 \"Аудио в топик\" 123`\n\n";
                    $response .= "Используйте `/files` для просмотра доступных файлов.";
                    $bot->sendMessage($chatId, $response, 'Markdown');
                    break;
                }
                
                $targetChatId = $parts[1];
                $fileName = $parts[2];
                $caption = '';
                $topicId = null;
                
                if (count($parts) >= 4) {
                    // Проверяем, является ли 4-й параметр числом (topic_id) или строкой (caption)
                    if (is_numeric($parts[3]) && count($parts) == 4) {
                        $topicId = $parts[3];
                    } else {
                        $caption = $parts[3];
                        if (count($parts) >= 5) {
                            $topicId = $parts[4];
                        }
                    }
                }
                
                $filePath = $bot->uploadsDir . '/' . $fileName;
                
                if (!file_exists($filePath)) {
                    $response = "❌ Файл `$fileName` не найден.\n";
                    $response .= "Используйте `/files` для просмотра доступных файлов.";
                    $bot->sendMessage($chatId, $response, 'Markdown');
                    break;
                }
                
                $result = $bot->sendAudioFromFile($targetChatId, $filePath, $caption, 'Markdown', null, $topicId);
                if ($result && isset($result['ok']) && $result['ok']) {
                    $response = "✅ Аудио `$fileName` успешно отправлено в чат `$targetChatId`";
                    if ($topicId) {
                        $response .= " в топик `$topicId`";
                    }
                } else {
                    $error = isset($result['description']) ? $result['description'] : 'Неизвестная ошибка';
                    $response = "❌ Ошибка отправки аудио: `$error`";
                }
                $bot->sendMessage($chatId, $response, 'Markdown');
                break;
                
            case '/delete_file':
                $bot->writeLog("Admin $userId deleting file", 'INFO');
                $parts = explode(' ', $text, 2);
                if (count($parts) < 2) {
                    $response = "❌ *Неверный формат команды.*\n\n";
                    $response .= "*Использование:*\n";
                    $response .= "`/delete_file <имя_файла>`\n\n";
                    $response .= "*Пример:*\n";
                    $response .= "`/delete_file photo.jpg`\n\n";
                    $response .= "Используйте `/files` для просмотра доступных файлов.";
                    $bot->sendMessage($chatId, $response, 'Markdown');
                    break;
                }
                
                $fileName = $parts[1];
                
                $success = $bot->deleteLocalFile($fileName);
                if ($success) {
                    $response = "✅ Файл `$fileName` успешно удален.";
                } else {
                    $response = "❌ Не удалось удалить файл `$fileName`.";
                }
                $bot->sendMessage($chatId, $response, 'Markdown');
                break;
                
            case '/cleanup_files':
                $bot->writeLog("Admin $userId cleaning up files", 'INFO');
                $parts = explode(' ', $text, 2);
                $days = isset($parts[1]) ? intval($parts[1]) : 7;
                
                $deleted = $bot->cleanupOldFiles($days);
                $response = "🧹 Удалено старых файлов: $deleted (старше $days дней)";
                $bot->sendMessage($chatId, $response);
                break;
                
            // Остальные существующие команды...
            case '/check':
                $bot->writeLog("Admin $userId triggered manual check", 'INFO');
                $result = $bot->checkForNewEvents();
                $response = "✅ Проверка завершена.\n";
                $response .= "Найдено событий: {$result['total']}\n";
                $response .= "Обработано новых: {$result['processed']}\n";
                $response .= "Уже было обработано: {$result['already_processed']}";
                $bot->sendMessage($chatId, $response);
                $bot->writeLog("Manual check completed: {$result['processed']} new events processed", 'INFO');
                break;
                
            case '/stats':
                $bot->writeLog("Admin $userId requested stats", 'INFO');
                $stats = $bot->getStats();
                $response = "📊 *Статистика бота:*\n\n";
                $response .= "✅ Обработано событий: {$stats['processed_events']}\n";
                $response .= "📝 *Размеры логов:*\n";
                foreach ($stats['log_sizes'] as $file => $size) {
                    $response .= "  • $file: $size\n";
                }
                $response .= "⏰ Последняя проверка: {$stats['last_check']}\n";
                $response .= "🔧 Статус: {$stats['bot_status']}";
                $bot->sendMessage($chatId, $response, 'Markdown');
                break;
                
            case '/test':
                $bot->writeLog("Admin $userId triggered test", 'INFO');
                $success = $bot->testBot();
                $response = $success ? "✅ Все тесты пройдены успешно" : "❌ Тесты не пройдены";
                $bot->sendMessage($chatId, $response);
                break;
                
            case '/help':
                $bot->writeLog("Admin $userId requested help", 'INFO');
                $help = getHelpText();
                $bot->sendMessage($chatId, $help, 'Markdown');
                break;
                
            case '/logs':
                $bot->writeLog("Admin $userId requested logs", 'INFO');
                $logs = $bot->getLogs('all', 10);
                if (empty($logs)) {
                    $response = "📋 Логи отсутствуют или файл логов пуст.";
                } else {
                    $response = "📋 *Последние 10 записей лога:*\n\n";
                    foreach ($logs as $log) {
                        $response .= $log . "\n";
                    }
                }
                $bot->sendMessage($chatId, $response, 'Markdown');
                break;
                
            case '/logs_incoming':
                $bot->writeLog("Admin $userId requested incoming logs", 'INFO');
                $logs = $bot->getLogs('incoming', 10);
                if (empty($logs)) {
                    $response = "📨 Входящие логи отсутствуют.";
                } else {
                    $response = "📨 *Последние 10 входящих сообщений:*\n\n";
                    foreach ($logs as $log) {
                        $response .= $log . "\n";
                    }
                }
                $bot->sendMessage($chatId, $response, 'Markdown');
                break;
                
            case '/cleanup_logs':
                $bot->writeLog("Admin $userId triggered log cleanup", 'INFO');
                $bot->cleanupOldLogs(3);
                $response = "🧹 Логи очищены (сохранены за последние 3 дня)";
                $bot->sendMessage($chatId, $response);
                break;
                
            case '/send_local_voice':
                $bot->sendMessage($chatId, "❌ Команда /send_local_voice пока не реализована");
                break;
                
            case '/send_local_sticker':
                $bot->sendMessage($chatId, "❌ Команда /send_local_sticker пока не реализована");
                break;

            case '/chats':
                $bot->writeLog("Admin $userId requested chat list", 'INFO');
                $chats = $bot->getChats();
                if (empty($chats)) {
                    $response = "📱 Чаты не найдены. Бот должен получить хотя бы одно сообщение в чате.";
                } else {
                    $response = "📱 *Список чатов, где состоит бот:*\n\n";
                    foreach ($chats as $chat) {
                        $response .= "• *{$chat['title']}*\n";
                        $response .= "  ID: `{$chat['id']}`\n";
                        $response .= "  Тип: {$chat['type']}\n";
                        if ($chat['username']) {
                            $response .= "  @{$chat['username']}\n";
                        }
                        $response .= "\n";
                    }
                    $response .= "Используйте ID чата для отправки сообщений.";
                }
                $bot->sendMessage($chatId, $response, 'Markdown');
                break;
        }
    } else {
        // НЕ-АДМИНИСТРАТОРЫ
        // Логируем сообщения от не-администраторов
        $userName = $message['from']['first_name'] ?? 'Unknown';
        $userUsername = $message['from']['username'] ?? 'No username';
        
        // Проверяем, является ли сообщение командой (начинается с /)
        if (strpos(trim($text), '/') === 0) {
            // Это команда, но от не-администратора
            $bot->writeLog("Command from non-admin $userName (@$userUsername, ID: $userId): $text", 'WARNING');
            
            // ОТВЕЧАЕМ ТОЛЬКО В ЛИЧНЫХ СООБЩЕНИЯХ, В ГРУППАХ МОЛЧИМ
            if ($chatType === 'private') {
                $response = "⛔ У вас нет доступа к командам бота. Обратитесь к администратору.";
                $bot->sendMessage($chatId, $response);
            } else {
                // В группе молчим, только логируем
                $bot->writeLog("Ignoring command from non-admin in group chat", 'DEBUG');
            }
        } else {
            // Обычное сообщение от не-администратора - просто логируем
            $bot->writeLog("Regular message from non-admin $userName (@$userUsername, ID: $userId)", 'DEBUG');
            // НЕ ОТВЕЧАЕМ
        }
    }
} elseif (!empty($update)) {
    // Логируем другие типы обновлений (не message)
    $updateType = array_keys($update)[1] ?? 'unknown';
    $bot->writeLog("Received non-message update type: $updateType", 'DEBUG');
}

// Всегда возвращаем успешный статус для Telegram
http_response_code(200);
echo 'OK';

/**
 * Получение названия типа файла
 */
function getTypeName($type) {
    $names = [
        'photo' => '📸 Фото',
        'video' => '🎥 Видео',
        'audio' => '🎵 Аудио',
        'document' => '📄 Документы',
        'voice' => '🎤 Голосовые',
        'sticker' => '😀 Стикеры'
    ];
    
    return isset($names[$type]) ? $names[$type] : ucfirst($type);
}

function formatHtmlMessage($text, $escapeHtml = true) {
    if ($escapeHtml) {
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $text;
}

/**
 * Получение текста справки
 */
function getHelpText() {
    $help = "📚 *Подробная справка по командам бота:*\n\n";
    
    $help .= "*📁 РАБОТА С ЛОКАЛЬНЫМИ ФАЙЛАМИ*\n";
    $help .= "1. Отправьте файл боту (фото, видео, документ и т.д.)\n";
    $help .= "2. Бот автоматически сохранит файл\n";
    $help .= "3. Используйте команды для отправки сохраненных файлов\n\n";
    
    $help .= "*📋 КОМАНДЫ ДЛЯ ФАЙЛОВ:*\n";
    $help .= "`/files` - Список сохраненных файлов\n";
    $help .= "`/send_local_photo <chat_id> <файл> [подпись] [topic_id]` - Отправить фото\n";
    $help .= "`/send_local_video <chat_id> <файл> [подпись] [topic_id]` - Отправить видео\n";
    $help .= "`/send_local_document <chat_id> <файл> [подпись] [topic_id]` - Отправить документ\n";
    $help .= "`/send_local_audio <chat_id> <файл> [подпись] [topic_id]` - Отправить аудио\n";
    $help .= "`/delete_file <файл>` - Удалить файл\n";
    $help .= "`/cleanup_files [дни]` - Очистить старые файлы\n\n";
    
    $help .= "*📝 ОТПРАВКА ТЕКСТА:*\n";
    $help .= "`/send_text <chat_id> <текст> [topic_id]` - Отправить текстовое сообщение\n\n";
    
    $help .= "*🎯 ПРИМЕРЫ:*\n";
    $help .= "`/files` - показать файлы\n";
    $help .= "`/send_text -100123456789 \"Привет, мир!\"`\n";
    $help .= "`/send_text -100123456789 \"Сообщение в топик\" 123`\n";
    $help .= "`/send_local_photo -100123456789 photo.jpg \"Мое фото\"`\n";
    $help .= "`/send_local_photo -100123456789 photo.jpg \"Фото в топик\" 123`\n";
    $help .= "`/delete_file old_photo.jpg`\n\n";
    
    $help .= "*📊 ОСНОВНЫЕ КОМАНДЫ:*\n";
    $help .= "`/check` - Проверить новые события\n";
    $help .= "`/stats` - Статистика бота\n";
    $help .= "`/test` - Тест подключений\n";
    $help .= "`/chats` - Список чатов\n\n";
    
    $help .= "*📝 ЛОГИ:*\n";
    $help .= "`/logs` - Показать логи\n";
    $help .= "`/logs_incoming` - Входящие сообщения\n";
    $help .= "`/cleanup_logs` - Очистить логи\n";
    
    return $help;
}
?>
