<?php
// public/api/auth_handler.php

// 1. Incluir conexión a BD (ajusta la ruta según tu estructura)
require_once __DIR__ . '/../../includes/db.php';

// --- FUNCIONES AUXILIARES (Movidas desde db.php) ---

function logUserAccess($pdo, $userId) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ipList[0]);
        }
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        
        $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $ip, $userAgent]);
    } catch (Exception $e) { }
}

function generate_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

function generate_verification_code() {
    return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Función auxiliar para redirigir con error
function redirectWithError($url, $message) {
    $_SESSION['error'] = $message;
    header("Location: " . $url);
    exit;
}

// --- LÓGICA DE AUTENTICACIÓN (POST) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Guardamos la URL de origen para redirigir en caso de error
    $referer = $_SERVER['HTTP_REFERER'] ?? $basePath . 'login';

    // --- ETAPA 1: VALIDAR CORREO Y CONTRASEÑA ---
    if ($_POST['action'] === 'register_step_1') {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if ($email && $password) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                redirectWithError($referer, "El correo electrónico ya está registrado.");
            } else {
                $_SESSION['temp_register'] = [
                    'email' => $email,
                    'password' => $password
                ];
                header("Location: " . $basePath . "register/aditional-data");
                exit;
            }
        } else {
            redirectWithError($referer, "Correo y contraseña requeridos.");
        }
    }

    // --- ETAPA 2: VALIDAR USUARIO ---
    if ($_POST['action'] === 'register_step_2') {
        $username = trim($_POST['username']);
        
        if (!isset($_SESSION['temp_register'])) {
            redirectWithError($basePath . "register", "Sesión expirada. Vuelve a empezar.");
        }

        $email = $_SESSION['temp_register']['email'];
        $password = $_SESSION['temp_register']['password'];

        if ($username) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);

            if ($stmt->rowCount() > 0) {
                redirectWithError($referer, "El nombre de usuario ya está en uso.");
            } else {
                $code = generate_verification_code();
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                $payload = json_encode([
                    'username' => $username,
                    'email'    => $email,
                    'password' => $passwordHash
                ]);

                $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $stmt = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, ?, ?, ?, ?)");
                
                if ($stmt->execute([$email, 'account_activation', $code, $payload, $expiresAt])) {
                    $_SESSION['pending_verification_email'] = $email;
                    unset($_SESSION['temp_register']);
                    header("Location: " . $basePath . "register/verify");
                    exit;
                } else {
                    redirectWithError($referer, "Error al generar código de verificación.");
                }
            }
        } else {
            redirectWithError($referer, "Nombre de usuario requerido.");
        }
    }

    // --- ETAPA 3: VERIFICAR CÓDIGO ---
    if ($_POST['action'] === 'verify_code') {
        $code = trim($_POST['code']);
        $emailIdentifier = $_SESSION['pending_verification_email'] ?? null;

        if ($code && $emailIdentifier) {
            $stmt = $pdo->prepare("SELECT * FROM verification_codes WHERE identifier = ? AND code = ? AND code_type = 'account_activation' AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
            $stmt->execute([$emailIdentifier, $code]);
            $verificationRow = $stmt->fetch();

            if ($verificationRow) {
                $payload = json_decode($verificationRow['payload'], true);
                
                $finalUsername = $payload['username'];
                $finalEmail = $payload['email'];
                $finalPassHash = $payload['password'];
                $uuid = generate_uuid();

                $insertUser = $pdo->prepare("INSERT INTO users (username, email, password, uuid) VALUES (?, ?, ?, ?)");
                
                if ($insertUser->execute([$finalUsername, $finalEmail, $finalPassHash, $uuid])) {
                    $newUserId = $pdo->lastInsertId();

                    $delStmt = $pdo->prepare("DELETE FROM verification_codes WHERE id = ?");
                    $delStmt->execute([$verificationRow['id']]);

                    // Generar Avatar
                    try {
                        $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($finalUsername) . "&background=random&color=fff&size=128";
                        $imageData = @file_get_contents($avatarUrl);
                        if ($imageData) {
                            $targetDir = __DIR__ . '/../assets/uploads/profile_pictures/';
                            if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
                            @file_put_contents($targetDir . $uuid . '.png', $imageData);
                        }
                    } catch (Exception $e) {}

                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['username'] = $finalUsername;
                    $_SESSION['uuid'] = $uuid; 
                    $_SESSION['role'] = 'user';

                    unset($_SESSION['pending_verification_email']);
                    logUserAccess($pdo, $newUserId);
                    session_write_close();

                    header("Location: " . $basePath);
                    exit;
                } else {
                    redirectWithError($referer, "Error crítico al crear la cuenta.");
                }
            } else {
                redirectWithError($referer, "Código inválido o expirado.");
            }
        } else {
            redirectWithError($referer, "Código requerido.");
        }
    }

    // --- LOGIN ---
    if ($_POST['action'] === 'login') {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT id, username, password, uuid, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['uuid'] = $user['uuid']; 
            $_SESSION['role'] = $user['role'];
            logUserAccess($pdo, $user['id']);
            
            session_write_close();
            header("Location: " . $basePath);
            exit;
        } else {
            redirectWithError($referer, "Credenciales incorrectas.");
        }
    }
}

// 3. LOGOUT (GET)
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $basePath . "login");
    exit;
}
?>