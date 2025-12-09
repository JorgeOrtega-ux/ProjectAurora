<?php
// public/api/auth_handler.php

// Asegurar que la respuesta sea siempre JSON
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

// --- FUNCIONES AUXILIARES ---

function sendJsonResponse($status, $message, $redirectUrl = null, $data = []) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'redirect' => $redirectUrl,
        'data' => $data
    ]);
    exit;
}

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

// --- LÓGICA DE AUTENTICACIÓN (POST) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Leer datos (Soporta tanto Form Data como JSON Raw)
    $input = $_POST;
    if (empty($input)) {
        $json = json_decode(file_get_contents('php://input'), true);
        if ($json) $input = $json;
    }

    $action = $input['action'] ?? '';

    // --- ETAPA 1: REGISTRO - VALIDAR CORREO Y CONTRASEÑA ---
    if ($action === 'register_step_1') {
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if ($email && $password) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                sendJsonResponse('error', "El correo electrónico ya está registrado.");
            } else {
                $_SESSION['temp_register'] = [
                    'email' => $email,
                    'password' => $password
                ];
                // Éxito: JS redirigirá al siguiente paso
                sendJsonResponse('success', "Datos válidos", $basePath . "register/aditional-data");
            }
        } else {
            sendJsonResponse('error', "Correo y contraseña requeridos.");
        }
    }

    // --- ETAPA 2: REGISTRO - VALIDAR USUARIO ---
    if ($action === 'register_step_2') {
        $username = trim($input['username'] ?? '');
        
        if (!isset($_SESSION['temp_register'])) {
            sendJsonResponse('error', "Sesión expirada. Vuelve a empezar.", $basePath . "register");
        }

        $email = $_SESSION['temp_register']['email'];
        $password = $_SESSION['temp_register']['password'];

        if ($username) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);

            if ($stmt->rowCount() > 0) {
                sendJsonResponse('error', "El nombre de usuario ya está en uso.");
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
                    // Éxito: JS redirigirá a verificar
                    sendJsonResponse('success', "Código enviado", $basePath . "register/verify");
                } else {
                    sendJsonResponse('error', "Error al generar código de verificación.");
                }
            }
        } else {
            sendJsonResponse('error', "Nombre de usuario requerido.");
        }
    }

    // --- ETAPA 3: VERIFICAR CÓDIGO ---
    if ($action === 'verify_code') {
        $code = trim($input['code'] ?? '');
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

                    // Generar Avatar (Opcional, fallo silencioso)
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

                    // Éxito Total: JS redirigirá al home
                    sendJsonResponse('success', "Cuenta creada exitosamente", $basePath);
                } else {
                    sendJsonResponse('error', "Error crítico al crear la cuenta.");
                }
            } else {
                sendJsonResponse('error', "Código inválido o expirado.");
            }
        } else {
            sendJsonResponse('error', "Código requerido.");
        }
    }

    // --- LOGIN ---
    if ($action === 'login') {
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

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
            // Éxito: JS redirigirá al home
            sendJsonResponse('success', "Bienvenido", $basePath);
        } else {
            sendJsonResponse('error', "Credenciales incorrectas.");
        }
    }
    
    // Si no coincide ninguna acción
    sendJsonResponse('error', "Acción no válida.");
}

// 3. LOGOUT (GET) - Esto sigue siendo una petición normal de navegador
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $basePath . "login");
    exit;
}
?>