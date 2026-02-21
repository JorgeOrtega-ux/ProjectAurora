<?php
// includes/core/Logger.php
namespace App\Core;

class Logger {
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_CRITICAL = 'CRITICAL';

    /**
     * Obtiene la IP del cliente para adjuntarla al log.
     */
    private static function getClientIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        }
    }

    /**
     * Motor principal de escritura de logs.
     */
    public static function log(string $category, string $level, string $message, array|string|\Throwable $context = []) {
        // La ruta base es /logs (dos niveles arriba de /includes/core)
        $baseDir = __DIR__ . '/../../logs';
        $categoryDir = $baseDir . '/' . $category;

        // Crear directorio si no existe con permisos 0755
        if (!is_dir($categoryDir)) {
            mkdir($categoryDir, 0755, true);
        }

        $date = date('Y-m-d');
        $file = $categoryDir . '/' . $date . '.log';
        $time = date('Y-m-d H:i:s');
        $ip = self::getClientIp();

        // Procesar el contexto o Stacktrace
        $contextStr = '';
        if ($context instanceof \Throwable) {
            $contextStr = " | Exception: " . $context->getMessage() . " in " . $context->getFile() . ":" . $context->getLine() . "\nStack trace:\n" . $context->getTraceAsString();
        } elseif (!empty($context)) {
            $contextStr = " | Context: " . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        // Formato final del log
        $logEntry = "[$time] [$level] [IP: $ip] $message$contextStr" . PHP_EOL;

        // Escribir en el archivo de forma segura (con bloqueo)
        file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Helpers rápidos para los diferentes directorios
     */
    public static function system(string $message, string $level = self::LEVEL_INFO, array|string|\Throwable $context = []) {
        self::log('system', $level, $message, $context);
    }

    public static function database(string $message, string $level = self::LEVEL_ERROR, array|string|\Throwable $context = []) {
        self::log('database', $level, $message, $context);
    }

    public static function app(string $message, string $level = self::LEVEL_INFO, array|string|\Throwable $context = []) {
        self::log('app', $level, $message, $context);
    }
}
?>