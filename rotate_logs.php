<?php
require_once 'config.php';

class LogRotator {
    private $logDir;
    private $maxSize; // в байтах
    private $keepDays;
    
    public function __construct($logDir = __DIR__ . '/logs', $maxSize = 10485760, $keepDays = 30) {
        $this->logDir = $logDir;
        $this->maxSize = $maxSize; // 10 MB по умолчанию
        $this->keepDays = $keepDays;
    }
    
    public function rotate() {
        $files = [
            'bot.log',
            'incoming.log', 
            'error.log'
        ];
        
        foreach ($files as $file) {
            $filePath = $this->logDir . '/' . $file;
            
            if (file_exists($filePath) && filesize($filePath) > $this->maxSize) {
                $this->compressAndRotate($filePath);
            }
        }
        
        $this->cleanupOldArchives();
    }
    
    private function compressAndRotate($filePath) {
        $backupPath = $filePath . '.' . date('Y-m-d_H-i-s') . '.gz';
        
        // Читаем текущий лог
        $content = file_get_contents($filePath);
        
        // Сжимаем и сохраняем
        $compressed = gzencode($content, 9);
        file_put_contents($backupPath, $compressed);
        
        // Очищаем основной файл
        file_put_contents($filePath, '');
        
        error_log("Лог файл $filePath сжат и сохранен как $backupPath");
    }
    
    private function cleanupOldArchives() {
        $cutoffTime = time() - ($this->keepDays * 24 * 3600);
        
        $files = glob($this->logDir . '/*.gz');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                error_log("Удален старый архив лога: $file");
            }
        }
    }
}

// Запуск ротации
$rotator = new LogRotator();
$rotator->rotate();

echo "Ротация логов выполнена успешно\n";
?>
