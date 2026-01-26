<?php
// includes/libs/Logger.php

class Logger {
    
    // Ruta ajustada para salir de includes/libs/ hacia logs/
    private const LOG_DIR = __DIR__ . '/../../logs/';

    public static function log($channel, $message, $context = [], $level = 'ERROR') {
        $date = date('Y-m-d');
        $dir = self::LOG_DIR . $channel . '/';
        $file = $dir . $channel . '-' . $date . '.log';

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log("CRITICAL: No se pudo crear el directorio de logs en $dir");
                return;
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $userId = $_SESSION['user_id'] ?? 'GUEST';
        $uri = $_SERVER['REQUEST_URI'] ?? 'Unknown URI';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        $timestamp = date('Y-m-d H:i:s');

        $contextString = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

        $logLine = sprintf(
            "[%s] [%s] [%s] [USER:%s] [%s %s] %s %s" . PHP_EOL,
            $timestamp,
            str_pad($level, 7),
            $ip,
            $userId,
            $method,
            $uri,
            $message,
            $contextString
        );

        file_put_contents($file, $logLine, FILE_APPEND | LOCK_EX);
    }

    public static function app($msg, $context = []) {
        self::log('app', $msg, $context, 'ERROR');
    }

    public static function db($msg, $context = []) {
        self::log('database', $msg, $context, 'CRITICAL');
    }

    public static function security($msg, $context = []) {
        self::log('security', $msg, $context, 'WARNING');
    }
}
?>