<?php
// api/controllers/AuthHandler.php

require_once __DIR__ . '/../services/AuthServices.php';

class AuthHandler {
    private $authService;
    private $basePath = '/ProjectAurora/'; 
    // Ajustar si cambia tu IP o dominio
    private $redirectUrl = 'http://192.168.1.158/ProjectAurora/'; 

    public function __construct() {
        $this->authService = new AuthServices();
    }

    /**
     * Procesa la petición entrante
     */
    public function handleRequest() {
        // Capturar acción de POST o GET
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            switch ($action) {
                case 'register':
                    $this->processRegister();
                    break;
                case 'login':
                    $this->processLogin();
                    break;
                default:
                    // Acción desconocida
                    header("Location: " . $this->basePath);
                    exit;
            }
        } elseif ($action === 'logout') {
            $this->processLogout();
        } else {
            // Sin acción válida, volver al inicio
            header("Location: " . $this->basePath);
            exit;
        }
    }

    private function processRegister() {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $result = $this->authService->register($username, $email, $password);

        if ($result['status']) {
            header("Location: " . $this->redirectUrl);
            exit;
        } else {
            header("Location: " . $this->basePath . "register?error=" . urlencode($result['message']));
            exit;
        }
    }

    private function processLogin() {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $result = $this->authService->login($email, $password);

        if ($result['status']) {
            header("Location: " . $this->redirectUrl);
            exit;
        } else {
            header("Location: " . $this->basePath . "login?error=" . urlencode($result['message']));
            exit;
        }
    }

    private function processLogout() {
        $this->authService->logout();
        header("Location: " . $this->basePath . "login");
        exit;
    }
}
?>