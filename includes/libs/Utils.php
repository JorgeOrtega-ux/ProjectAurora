<?php
// includes/libs/Utils.php

require_once __DIR__ . '/I18n.php';
require_once __DIR__ . '/Logger.php'; // Aseguramos que el Logger esté disponible

class Utils {

    /**
     * Inicializa los manejadores globales de errores y excepciones.
     * Debe llamarse al inicio de la aplicación (ej: en loader.php o index.php).
     */
    public static function initErrorHandlers() {
        // 1. Manejador de Excepciones No Capturadas (Uncaught Exceptions)
        set_exception_handler(function ($e) {
            Logger::app('Uncaught Exception', [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            self::showGenericErrorPage();
        });

        // 2. Manejador de Errores PHP (Notices, Warnings, etc.)
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // Ignorar errores silenciados con @
            if (!(error_reporting() & $errno)) {
                return false;
            }

            // Convertir errores en logs
            $level = 'ERROR';
            switch ($errno) {
                case E_WARNING: $level = 'WARNING'; break;
                case E_NOTICE:  $level = 'NOTICE'; break;
                default:        $level = 'CRITICAL'; break;
            }

            Logger::log('app', $errstr, [
                'file' => $errfile,
                'line' => $errline,
                'errno' => $errno
            ], $level);

            // Si es un error crítico, detener ejecución; si es Warning, dejamos continuar
            // pero NO mostramos nada en pantalla gracias a display_errors = 0 (configurado en php.ini o runtime)
            return true; // true evita que PHP use su manejador de errores estándar
        });

        // 3. Manejador de Cierre (Shutdown) para errores Fatales
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                Logger::log('app', 'Fatal Error (Shutdown)', [
                    'message' => $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line']
                ], 'CRITICAL');
                
                self::showGenericErrorPage();
            }
        });
        
        // FORZAR SILENCIO EN FRONTEND (Capa extra de seguridad)
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
    }

    /**
     * Muestra una página de error genérica y termina el script.
     */
    public static function showGenericErrorPage() {
        if (!headers_sent()) {
            http_response_code(500);
        }
        // Verificar si es una petición AJAX/API para responder JSON
        $isApi = (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) || 
                 (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor (500). Contacte a soporte.']);
        } else {
            echo "<div style='font-family:sans-serif; text-align:center; padding:50px;'>";
            echo "<h1 style='color:#333;'>Error del Sistema</h1>";
            echo "<p style='color:#666;'>Ha ocurrido un error inesperado. El incidente ha sido registrado.</p>";
            echo "<a href='/ProjectAurora/' style='color:#007bff;'>Volver al inicio</a>";
            echo "</div>";
        }
        exit;
    }

    /**
     * Inicializa el sistema de internacionalización basado en la sesión.
     * @return I18n
     */
    public static function initI18n() {
        $userLang = $_SESSION['preferences']['language'] ?? 'es-latam';
        return new I18n($userLang);
    }

    /**
     * Envía una respuesta JSON y termina la ejecución.
     * @param array $data
     */
    public static function jsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Valida el Token CSRF en peticiones POST.
     * Si falla, loguea el intento de seguridad y termina.
     * @param I18n $i18n
     */
    public static function validateCsrf($i18n) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                
                // LOG DE SEGURIDAD AUTOMÁTICO
                Logger::security('CSRF Validation Failed', [
                    'post_token' => $_POST['csrf_token'] ?? 'null',
                    'session_token' => $_SESSION['csrf_token'] ?? 'null'
                ]);

                self::jsonResponse(['success' => false, 'message' => $i18n->t('api.security_error')]);
            }
        }
    }

    /**
     * Obtiene la fuente (src) del avatar global.
     * @return string
     */
    public static function getGlobalAvatarSrc() {
        $src = '';
        if (isset($_SESSION['user_id'])) {
            if (!empty($_SESSION['avatar'])) {
                $avatarFile = __DIR__ . '/../../' . $_SESSION['avatar'];
                if (file_exists($avatarFile)) {
                    $mimeType = mime_content_type($avatarFile);
                    $data = file_get_contents($avatarFile);
                    $src = 'data:' . $mimeType . ';base64,' . base64_encode($data);
                }
            }
            if (empty($src)) {
                $name = $_SESSION['username'] ?? 'User';
                $src = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=random&color=fff";
            }
        }
        return $src;
    }
}
?>