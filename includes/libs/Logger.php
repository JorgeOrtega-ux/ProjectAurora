<?php
// includes/libs/Logger.php
namespace Aurora\Libs;
class Logger {
    
    // Ruta base desde includes/libs/ hacia la raíz del proyecto
    private const LOG_DIR = __DIR__ . '/../../logs/';

    /**
     * Escribe un log en el sistema.
     * * @param string $channel 'app', 'database', 'security'
     * @param string $message Mensaje principal del error
     * @param array $context Datos adicionales (array, json, excepción)
     * @param string $level Nivel de severidad (INFO, ERROR, CRITICAL, WARNING)
     */
    public static function log($channel, $message, $context = [], $level = 'ERROR') {
        // 1. Definir ruta y archivo (Rotación diaria)
        $date = date('Y-m-d');
        $dir = self::LOG_DIR . $channel . '/';
        $file = $dir . $channel . '-' . $date . '.log';

        // 2. Crear directorio si no existe (con permisos seguros)
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                // Si falla crear la carpeta de logs, fallback al error log de PHP del sistema
                error_log("CRITICAL: No se pudo crear el directorio de logs en $dir");
                return;
            }
        }

        // 3. Obtener Contexto (IP, Usuario, URL)
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $userId = $_SESSION['user_id'] ?? 'GUEST';
        $uri = $_SERVER['REQUEST_URI'] ?? 'Unknown URI';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        $timestamp = date('Y-m-d H:i:s');

        // 4. Formatear Contexto Adicional
        $contextString = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

        // 5. Construir la línea de log
        // Formato: [FECHA] [NIVEL] [IP] [USER:ID] [METHOD URI] MENSAJE {CONTEXTO}
        $logLine = sprintf(
            "[%s] [%s] [%s] [USER:%s] [%s %s] %s %s" . PHP_EOL,
            $timestamp,
            str_pad($level, 7), // Padding para alineación visual
            $ip,
            $userId,
            $method,
            $uri,
            $message,
            $contextString
        );

        // 6. Escribir en archivo (Lock exclusivo para evitar condiciones de carrera)
        file_put_contents($file, $logLine, FILE_APPEND | LOCK_EX);
    }

    // Helpers rápidos
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