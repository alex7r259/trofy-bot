<?php
require_once 'config.php';
require_once 'bot.php';

// Защита паролем
$password = '123456789'; // Установите свой пароль
$logout = isset($_GET['logout']);

session_start();

// Выход из системы
if ($logout) {
    session_destroy();
    header('Location: view_logs.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
        header('Location: view_logs.php');
        exit;
    } else {
        $error = "Неверный пароль";
    }
}

// Проверка авторизации (сессия истекает через 1 час)
$isAuthenticated = isset($_SESSION['authenticated']) && 
                   $_SESSION['authenticated'] && 
                   (time() - $_SESSION['login_time']) <= 3600 &&
                   ($_SESSION['user_ip'] ?? '') === $_SERVER['REMOTE_ADDR'];

if (!$isAuthenticated) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Просмотр логов - Авторизация</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            * { box-sizing: border-box; }
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                margin: 0; 
                padding: 0; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-container {
                background: white;
                border-radius: 10px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.2);
                padding: 40px;
                width: 100%;
                max-width: 400px;
                margin: 20px;
            }
            .login-header {
                text-align: center;
                margin-bottom: 30px;
            }
            .login-header h2 {
                color: #333;
                margin-bottom: 10px;
                font-weight: 600;
            }
            .login-header p {
                color: #666;
                font-size: 14px;
            }
            .form-group {
                margin-bottom: 20px;
            }
            .form-group label {
                display: block;
                margin-bottom: 8px;
                color: #333;
                font-weight: 500;
                font-size: 14px;
            }
            .form-control {
                width: 100%;
                padding: 12px 15px;
                border: 1px solid #ddd;
                border-radius: 5px;
                font-size: 16px;
                transition: border-color 0.3s;
            }
            .form-control:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            .btn {
                display: block;
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            }
            .alert {
                padding: 12px;
                border-radius: 5px;
                margin-bottom: 20px;
                font-size: 14px;
            }
            .alert-error {
                background-color: #fee;
                border: 1px solid #fcc;
                color: #c00;
            }
            .logo {
                text-align: center;
                margin-bottom: 20px;
            }
            .logo img {
                max-width: 80px;
                margin-bottom: 10px;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="logo">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z" fill="#667eea"/>
                </svg>
                <h2>Логи бота</h2>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="password">Пароль для доступа к логам</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Введите пароль" required autofocus>
                </div>
                <button type="submit" class="btn">Войти</button>
            </form>
            
            <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #999;">
                <?php echo date('Y'); ?> • Логи Telegram бота
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Создаем объект бота
$bot = new TelegramEventBot();

// Получаем параметры
$type = $_GET['type'] ?? 'all';
$limit = min((int)($_GET['limit'] ?? 100), 1000); // Максимум 1000 записей
$days = (int)($_GET['days'] ?? 3);

// Действия
$action = $_GET['action'] ?? '';
$logFile = $_GET['file'] ?? '';

// Выполнение действий
switch ($action) {
    case 'cleanup':
        $bot->cleanupOldLogs($days);
        $_SESSION['message'] = 'Логи успешно очищены';
        header('Location: view_logs.php?type=' . urlencode($type) . '&limit=' . $limit);
        exit;
        
    case 'download':
        if (in_array($logFile, ['bot.log', 'incoming.log', 'error.log'])) {
            $filePath = __DIR__ . '/logs/' . $logFile;
            if (file_exists($filePath)) {
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="' . $logFile . '_' . date('Y-m-d') . '.txt"');
                readfile($filePath);
                exit;
            }
        }
        break;
        
    case 'clear':
        if (in_array($logFile, ['bot.log', 'incoming.log', 'error.log'])) {
            $filePath = __DIR__ . '/logs/' . $logFile;
            if (file_exists($filePath)) {
                file_put_contents($filePath, '');
                $_SESSION['message'] = 'Лог-файл очищен: ' . $logFile;
                header('Location: view_logs.php?type=' . urlencode($type) . '&limit=' . $limit);
                exit;
            }
        }
        break;
}

// Получаем логи
$logs = $bot->getLogs($type, $limit);

// Получаем статистику по файлам
$logFiles = [
    'bot.log' => [
        'path' => __DIR__ . '/logs/bot.log',
        'name' => 'Основной лог',
        'icon' => '📝'
    ],
    'incoming.log' => [
        'path' => __DIR__ . '/logs/incoming.log',
        'name' => 'Входящие сообщения',
        'icon' => '📨'
    ],
    'error.log' => [
        'path' => __DIR__ . '/logs/error.log',
        'name' => 'Лог ошибок',
        'icon' => '❌'
    ]
];

$fileStats = [];
foreach ($logFiles as $fileName => $fileInfo) {
    if (file_exists($fileInfo['path'])) {
        $size = filesize($fileInfo['path']);
        $lines = count(file($fileInfo['path'], FILE_SKIP_EMPTY_LINES));
        $fileStats[$fileName] = [
            'size' => $size,
            'size_formatted' => formatBytes($size),
            'lines' => $lines,
            'modified' => filemtime($fileInfo['path']),
            'name' => $fileInfo['name'],
            'icon' => $fileInfo['icon']
        ];
    }
}

// Функция для форматирования размера
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Просмотр логов бота</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header h1 svg {
            fill: white;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .nav {
            background: #f8f9fa;
            padding: 20px 30px;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .nav-tabs {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .nav-tab {
            padding: 10px 20px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #666;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav-tab:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .nav-tab.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .nav-controls {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }
        
        .select-control {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
            color: #333;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .stats {
            padding: 20px 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #eaeaea;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-desc {
            font-size: 13px;
            color: #888;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .file-action-btn {
            padding: 8px 15px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #666;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .file-action-btn:hover {
            background: #e9ecef;
            color: #333;
        }
        
        .logs-container {
            padding: 30px;
        }
        
        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .logs-header h2 {
            font-size: 18px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .log-entries {
            background: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #eaeaea;
        }
        
        .log-entry {
            padding: 15px 20px;
            border-bottom: 1px solid #eaeaea;
            background: white;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 13px;
            line-height: 1.5;
            transition: background 0.3s;
        }
        
        .log-entry:hover {
            background: #f8f9fa;
        }
        
        .log-entry:last-child {
            border-bottom: none;
        }
        
        .log-entry.info {
            border-left: 4px solid #007bff;
        }
        
        .log-entry.warning {
            border-left: 4px solid #ffc107;
            background: #fff9e6;
        }
        
        .log-entry.error {
            border-left: 4px solid #dc3545;
            background: #fff5f5;
        }
        
        .log-entry.incoming {
            border-left: 4px solid #28a745;
            background: #f0fff4;
        }
        
        .log-entry.debug {
            border-left: 4px solid #6c757d;
            background: #f8f9fa;
        }
        
        .log-time {
            color: #666;
            font-weight: 600;
            margin-right: 10px;
        }
        
        .log-level {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-right: 10px;
            text-transform: uppercase;
        }
        
        .log-level.info { background: #e3f2fd; color: #1976d2; }
        .log-level.warning { background: #fff3cd; color: #856404; }
        .log-level.error { background: #f8d7da; color: #721c24; }
        .log-level.incoming { background: #d4edda; color: #155724; }
        .log-level.debug { background: #e2e3e5; color: #383d41; }
        
        .log-content {
            margin-top: 8px;
            color: #333;
            white-space: pre-wrap;
            word-break: break-word;
        }
        
        .empty-logs {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 16px;
        }
        
        .empty-logs svg {
            fill: #ddd;
            margin-bottom: 15px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .refresh-info {
            font-size: 13px;
            color: #666;
            text-align: center;
            margin-top: 20px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .header, .nav, .stats, .logs-container {
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .nav {
                flex-direction: column;
            }
            
            .nav-controls {
                margin-left: 0;
                width: 100%;
            }
            
            .select-control, .btn {
                flex: 1;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Заголовок -->
        <div class="header">
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12zM7 9h10v2H7zm0 4h7v2H7z"/>
                </svg>
                Просмотр логов Telegram бота
            </h1>
            
            <div class="user-info">
                <span>Вход выполнен</span>
                <span>•</span>
                <span><?php echo date('d.m.Y H:i:s'); ?></span>
                <a href="?logout=1" class="logout-btn">Выйти</a>
            </div>
        </div>
        
        <!-- Сообщения -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="currentColor"/>
                </svg>
                <?php 
                echo htmlspecialchars($_SESSION['message']);
                unset($_SESSION['message']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Навигация -->
        <div class="nav">
            <div class="nav-tabs">
                <a href="?type=all&limit=<?php echo $limit; ?>" 
                   class="nav-tab <?php echo $type == 'all' ? 'active' : ''; ?>">
                    📝 Все логи
                </a>
                <a href="?type=incoming&limit=<?php echo $limit; ?>" 
                   class="nav-tab <?php echo $type == 'incoming' ? 'active' : ''; ?>">
                    📨 Входящие
                </a>
                <a href="?type=error&limit=<?php echo $limit; ?>" 
                   class="nav-tab <?php echo $type == 'error' ? 'active' : ''; ?>">
                    ❌ Ошибки
                </a>
            </div>
            
            <div class="nav-controls">
                <select class="select-control" onchange="location.href='?type=<?php echo $type; ?>&limit='+this.value">
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 записей</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 записей</option>
                    <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200 записей</option>
                    <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500 записей</option>
                </select>
                
                <button class="btn" onclick="location.href='?action=cleanup&type=<?php echo $type; ?>&limit=<?php echo $limit; ?>&days=3'">
                    🧹 Очистить старые
                </button>
            </div>
        </div>
        
        <!-- Статистика файлов -->
        <div class="stats">
            <?php foreach ($fileStats as $fileName => $stats): ?>
                <div class="stat-card">
                    <h3><?php echo $stats['icon']; ?> <?php echo htmlspecialchars($stats['name']); ?></h3>
                    <div class="stat-value"><?php echo number_format($stats['lines']); ?> строк</div>
                    <div class="stat-desc">
                        Размер: <?php echo $stats['size_formatted']; ?><br>
                        Изменен: <?php echo date('d.m.Y H:i', $stats['modified']); ?>
                    </div>
                    <div class="file-actions">
                        <a href="?action=download&file=<?php echo urlencode($fileName); ?>&type=<?php echo $type; ?>&limit=<?php echo $limit; ?>" 
                           class="file-action-btn" title="Скачать">
                            📥 Скачать
                        </a>
                        <a href="?action=clear&file=<?php echo urlencode($fileName); ?>&type=<?php echo $type; ?>&limit=<?php echo $limit; ?>" 
                           class="file-action-btn" 
                           onclick="return confirm('Вы уверены, что хотите очистить этот лог-файл?')"
                           title="Очистить">
                            🗑️ Очистить
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Основной контент с логами -->
        <div class="logs-container">
            <div class="logs-header">
                <h2>
                    <?php 
                    $titles = [
                        'all' => '📝 Все логи',
                        'incoming' => '📨 Входящие сообщения',
                        'error' => '❌ Ошибки'
                    ];
                    echo $titles[$type] ?? 'Логи';
                    ?>
                    <span style="font-size: 14px; color: #666; font-weight: normal;">
                        (показано: <?php echo count($logs); ?> из <?php echo $limit; ?>)
                    </span>
                </h2>
            </div>
            
            <div class="log-entries">
                <?php if (empty($logs)): ?>
                    <div class="empty-logs">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 5v14H5V5h14m0-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" fill="#ddd"/>
                            <path d="M12 7h-2v6h2V7zm0 8h-2v2h2v-2z" fill="#ccc"/>
                        </svg>
                        <p>Логи отсутствуют</p>
                        <?php if ($type == 'incoming'): ?>
                            <p style="font-size: 14px; margin-top: 10px;">
                                Проверьте, что вебхук настроен правильно и бот получает сообщения
                            </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        // Определяем класс и уровень лога
                        $logClass = 'info';
                        $logLevel = 'INFO';
                        
                        if (strpos($log, '[ERROR]') !== false) {
                            $logClass = 'error';
                            $logLevel = 'ERROR';
                        } elseif (strpos($log, '[WARNING]') !== false) {
                            $logClass = 'warning';
                            $logLevel = 'WARNING';
                        } elseif (strpos($log, '[INCOMING]') !== false) {
                            $logClass = 'incoming';
                            $logLevel = 'INCOMING';
                        } elseif (strpos($log, '[DEBUG]') !== false) {
                            $logClass = 'debug';
                            $logLevel = 'DEBUG';
                        }
                        
                        // Извлекаем время из лога
                        $time = '';
                        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $log, $matches)) {
                            $time = $matches[1];
                            $logContent = substr($log, strlen($time) + 2); // Убираем время и скобки
                        } else {
                            $logContent = $log;
                        }
                        ?>
                        
                        <div class="log-entry <?php echo $logClass; ?>">
                            <?php if ($time): ?>
                                <span class="log-time"><?php echo htmlspecialchars($time); ?></span>
                            <?php endif; ?>
                            <span class="log-level <?php echo $logClass; ?>"><?php echo $logLevel; ?></span>
                            <div class="log-content"><?php echo htmlspecialchars($logContent); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="refresh-info">
                Страница обновится автоматически через 30 секунд
                <span id="countdown">30</span> сек
            </div>
        </div>
    </div>
    
    <script>
        // Авто-обновление страницы
        let countdown = 30;
        const countdownElement = document.getElementById('countdown');
        
        const countdownInterval = setInterval(() => {
            countdown--;
            if (countdownElement) {
                countdownElement.textContent = countdown;
            }
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                window.location.reload();
            }
        }, 1000);
        
        // Отмена авто-обновления при взаимодействии с пользователем
        document.addEventListener('click', () => {
            clearInterval(countdownInterval);
            if (countdownElement) {
                countdownElement.textContent = ' (отменено)';
            }
        });
        
        document.addEventListener('keydown', () => {
            clearInterval(countdownInterval);
            if (countdownElement) {
                countdownElement.textContent = ' (отменено)';
            }
        });
        
        // Быстрые клавиши
        document.addEventListener('keydown', (e) => {
            // Ctrl + F - поиск (в будущем можно реализовать)
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                // Здесь можно добавить поиск
            }
            // R - обновить вручную
            if (e.key === 'r' || e.key === 'R') {
                window.location.reload();
            }
        });
        
        // Подсветка синтаксиса для JSON (если есть в логах)
        document.addEventListener('DOMContentLoaded', () => {
            const logContents = document.querySelectorAll('.log-content');
            logContents.forEach(content => {
                const text = content.textContent;
                // Попробуем найти JSON в тексте
                try {
                    // Ищем что-то похожее на JSON (начинается с { или [)
                    if (text.trim().startsWith('{') || text.trim().startsWith('[')) {
                        const obj = JSON.parse(text);
                        content.innerHTML = syntaxHighlight(JSON.stringify(obj, null, 2));
                    }
                } catch (e) {
                    // Не JSON, оставляем как есть
                }
            });
        });
        
        function syntaxHighlight(json) {
            json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, 
                function (match) {
                    let cls = 'number';
                    if (/^"/.test(match)) {
                        if (/:$/.test(match)) {
                            cls = 'key';
                        } else {
                            cls = 'string';
                        }
                    } else if (/true|false/.test(match)) {
                        cls = 'boolean';
                    } else if (/null/.test(match)) {
                        cls = 'null';
                    }
                    return '<span class="' + cls + '">' + match + '</span>';
                }
            );
        }
    </script>
    
    <style>
        /* Стили для подсветки JSON */
        .string { color: #22863a; }
        .number { color: #005cc5; }
        .boolean { color: #6f42c1; }
        .null { color: #d73a49; }
        .key { color: #24292e; font-weight: bold; }
    </style>
</body>
</html>
