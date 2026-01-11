<?php
// api/controllers/AuthHandler.php

require_once __DIR__ . '/../services/AuthServices.php';

class AuthHandler {
    private $authService;
    private $basePath;
    private $redirectUrl;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        $this->authService = new AuthServices();
        
        // Cargamos configuración desde variables de entorno
        $this->basePath = $_ENV['APP_BASE_PATH'] ?? '/ProjectAurora/';
        $this->redirectUrl = $_ENV['APP_URL'] ?? 'http://localhost/ProjectAurora/';
    }

    public function handleRequest() {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            // --- Validación de Seguridad CSRF ---
            $this->validateCSRF();
            // ------------------------------------

            switch ($action) {
                case 'login':
                    $this->processLogin();
                    break;
                case 'register_step_1':
                    $this->processRegisterStep1();
                    break;
                case 'register_step_2':
                    $this->processRegisterStep2();
                    break;
                case 'verify_account':
                    $this->processVerification();
                    break;
                default:
                    $this->sendJson(false, 'Acción no válida');
            }
        } elseif ($action === 'logout') {
            $this->processLogout();
        }
    }

    // --- Método Helper de Validación CSRF ---
    private function validateCSRF() {
        if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token'])) {
            $this->sendJson(false, 'Error de seguridad: Token no encontrado. Recarga la página.');
        }

        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $this->sendJson(false, 'Error de seguridad: Token inválido (CSRF). Por favor recarga la página.');
        }
    }

    // --- HELPER: RESPUESTA JSON ---
    private function sendJson($status, $message, $redirect = null) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'redirect' => $redirect
        ]);
        exit;
    }

    private function processRegisterStep1() {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $this->sendJson(false, __('auth.service.empty_fields'));
        }

        $validation = $this->authService->validateStep1($email, $password);
        if (!$validation['status']) {
            $this->sendJson(false, $validation['message']);
        }

        if ($this->authService->checkEmailExists($email)) {
            $this->sendJson(false, __('auth.service.email_exists'));
        }

        $_SESSION['reg_temp_email'] = $email;
        $_SESSION['reg_temp_password'] = $password;

        $this->sendJson(true, 'OK', $this->basePath . "register/additional-data");
    }

    private function processRegisterStep2() {
        $username = trim($_POST['username'] ?? '');
        $email = $_SESSION['reg_temp_email'] ?? null;
        $password = $_SESSION['reg_temp_password'] ?? null;

        if (!$email || !$password) {
            $this->sendJson(false, 'La sesión expiró, recarga la página.');
        }

        if (empty($username)) {
            $this->sendJson(false, __('auth.service.empty_fields'));
        }

        $validation = $this->authService->validateUsername($username);
        if (!$validation['status']) {
            $this->sendJson(false, $validation['message']);
        }

        $result = $this->authService->requestVerificationCode($email, $username, $password);

        if ($result['status']) {
            unset($_SESSION['reg_temp_password']); 
            $this->sendJson(true, 'OK', $this->basePath . "register/verification-account");
        } else {
            $this->sendJson(false, $result['message']);
        }
    }

    private function processVerification() {
        $code = $_POST['code'] ?? '';
        $email = $_SESSION['reg_temp_email'] ?? null;

        if (!$email) {
            $this->sendJson(false, 'La sesión expiró.');
        }

        $result = $this->authService->verifyAndCreateUser($email, $code);

        if ($result['status']) {
            unset($_SESSION['reg_temp_email']);
            // Usamos la URL desde el .env
            $this->sendJson(true, 'Bienvenido', $this->redirectUrl);
        } else {
            $this->sendJson(false, $result['message']);
        }
    }

    private function processLogin() {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $result = $this->authService->login($email, $password);

        if ($result['status']) {
            $this->sendJson(true, 'Bienvenido', $this->redirectUrl);
        } else {
            $this->sendJson(false, $result['message']);
        }
    }

    private function processLogout() {
        $this->authService->logout();
        header("Location: " . $this->basePath . "login");
        exit;
    }
}
?>