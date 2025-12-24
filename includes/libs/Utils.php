<?php
// includes/libs/Utils.php

require_once __DIR__ . '/I18n.php';

class Utils {

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
     * Si falla, termina la ejecución con un error JSON.
     * @param I18n $i18n
     */
    public static function validateCsrf($i18n) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                self::jsonResponse(['success' => false, 'message' => $i18n->t('api.security_error')]);
            }
        }
    }

    /**
     * Obtiene la fuente (src) del avatar global.
     * Verifica si existe el archivo local y lo convierte a Base64,
     * o devuelve un avatar generado por UI Avatars.
     * @return string
     */
    public static function getGlobalAvatarSrc() {
        $src = '';
        
        if (isset($_SESSION['user_id'])) {
            if (!empty($_SESSION['avatar'])) {
                // __DIR__ es includes/libs, subimos 2 niveles para llegar a la raíz
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