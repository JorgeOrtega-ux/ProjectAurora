<?php
// includes/libs/Utils.php

require_once __DIR__ . '/I18n.php';
require_once __DIR__ . '/Logger.php';

class Utils {

    /**
     * Inicializa los manejadores globales de errores y excepciones.
     */
    public static function initErrorHandlers() {
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

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if (!(error_reporting() & $errno)) {
                return false;
            }

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

            return true;
        });

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
        
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
    }

    /**
     * Aplica las cabeceras de seguridad y CSP.
     * @return string El nonce generado para scripts inline.
     */
    public static function applySecurityHeaders() {
        $cspNonce = base64_encode(random_bytes(16));

        header("Content-Security-Policy: " .
            "default-src 'self'; " .
            "script-src 'self' https://challenges.cloudflare.com https://unpkg.com https://cdnjs.cloudflare.com 'nonce-$cspNonce'; " .
            "style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; " .
            "img-src 'self' data: https://ui-avatars.com; " .
            "font-src 'self' https://fonts.gstatic.com; " .
            "frame-src https://challenges.cloudflare.com; " .
            "connect-src 'self' https://challenges.cloudflare.com https://unpkg.com; " .
            "object-src 'none'; " .
            "base-uri 'self';"
        );

        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: strict-origin-when-cross-origin");

        return $cspNonce;
    }

    public static function showGenericErrorPage() {
        if (!headers_sent()) {
            http_response_code(500);
        }
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
     * Obtiene una configuración del servidor desde la BD.
     */
    public static function getServerConfig($pdo, $key, $default = '0') {
        try {
            $stmt = $pdo->prepare("SELECT config_value FROM server_config WHERE config_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return $val !== false ? $val : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    public static function initI18n() {
        $userLang = $_SESSION['preferences']['language'] ?? 'es-latam';
        return new I18n($userLang);
    }

    public static function jsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function validateCsrf($i18n) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                Logger::security('CSRF Validation Failed', [
                    'post_token' => $_POST['csrf_token'] ?? 'null',
                    'session_token' => $_SESSION['csrf_token'] ?? 'null'
                ]);
                self::jsonResponse(['success' => false, 'message' => $i18n->t('api.security_error')]);
            }
        }
    }

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

    /**
     * Genera un UUID v4 válido.
     * Usado por WhiteboardService y AuthService.
     */
    public static function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
?>