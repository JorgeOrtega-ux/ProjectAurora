<?php
// api/controllers/SettingsHandler.php

require_once __DIR__ . '/../services/SettingsServices.php';

class SettingsHandler {
    private $service;
    
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->service = new SettingsServices();
    }

    public function handleRequest() {
        // Verificar Autenticación (Middleware simple)
        if (!isset($_SESSION['user_id'])) {
            $this->sendJson(false, 'Sesión expirada. Recarga la página.');
        }

        // Verificar CSRF
        $this->validateCSRF();

        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'settings_update_field':
                $this->processUpdateField();
                break;
            case 'settings_update_avatar':
                $this->processUpdateAvatar();
                break;
            case 'settings_delete_avatar':
                $this->processDeleteAvatar();
                break;
            default:
                $this->sendJson(false, 'Acción de configuración no válida.');
        }
    }

    private function processUpdateField() {
        $field = $_POST['field'] ?? '';
        $value = trim($_POST['value'] ?? '');
        
        if (empty($value)) $this->sendJson(false, 'El valor no puede estar vacío.');

        $res = $this->service->updateField($_SESSION['user_id'], $field, $value);
        $this->sendJson($res['status'], $res['message']);
    }

    private function processUpdateAvatar() {
        if (!isset($_FILES['avatar'])) $this->sendJson(false, 'No se recibió imagen.');
        
        $res = $this->service->updateAvatar($_SESSION['user_id'], $_FILES['avatar']);
        
        if ($res['status']) {
            $this->sendJson(true, $res['message'], ['newUrl' => $res['url']]);
        } else {
            $this->sendJson(false, $res['message']);
        }
    }

    private function processDeleteAvatar() {
        $res = $this->service->deleteAvatar($_SESSION['user_id']);
        if ($res['status']) {
            $this->sendJson(true, $res['message'], ['newUrl' => $res['url']]);
        } else {
            $this->sendJson(false, $res['message']);
        }
    }

    private function validateCSRF() {
        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $this->sendJson(false, 'Error de seguridad (Token inválido).');
        }
    }

    private function sendJson($status, $message, $extra = []) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
        exit;
    }
}
?>