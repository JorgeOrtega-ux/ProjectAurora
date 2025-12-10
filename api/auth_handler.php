<?php
// api/auth_handler.php
// UBICACIÓN: Raíz del proyecto /api/ (fuera de public)

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php'; 

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

function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) $ip = $_SERVER['HTTP_CLIENT_IP'];
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $ip;
}

function logUserAccess($pdo, $userId) {
    try {
        $ip = getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $ip, $userAgent]);
    } catch (Exception $e) { }
}

function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function generate_verification_code() {
    return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function validateEmailRequirements($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return "Formato inválido.";
    $atPos = strrpos($email, '@');
    if ($atPos === false) return "Correo inválido.";
    
    $prefix = substr($email, 0, $atPos);
    $domain = strtolower(substr($email, $atPos + 1));
    
    if (strlen($prefix) < 4) return "El correo debe tener al menos 4 caracteres antes del @.";
    $allowedDomains = ['gmail.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'yahoo.com'];
    if (!in_array($domain, $allowedDomains)) return "Dominio no permitido. Use: " . implode(', ', $allowedDomains) . ".";
    
    return true;
}

// --- NUEVAS FUNCIONES DE SEGURIDAD (RATE LIMITING) ---

function logSecurityEvent($pdo, $identifier, $actionType) {
    try {
        $ip = getClientIP();
        $identifier = substr($identifier, 0, 250);
        $stmt = $pdo->prepare("INSERT INTO security_logs (user_identifier, action_type, ip_address, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$identifier, $actionType, $ip]);
    } catch (Exception $e) {
        error_log("Error logging security event: " . $e->getMessage());
    }
}

function checkRateLimit($pdo, $identifier, $actionType, $limit, $minutes, $checkByIp = false) {
    $ip = getClientIP();
    
    if ($checkByIp) {
        $sql = "SELECT COUNT(*) FROM security_logs WHERE ip_address = ? AND action_type = ? AND created_at > (NOW() - INTERVAL ? MINUTE)";
        $params = [$ip, $actionType, $minutes];
    } else {
        $sql = "SELECT COUNT(*) FROM security_logs WHERE (user_identifier = ? OR ip_address = ?) AND action_type = ? AND created_at > (NOW() - INTERVAL ? MINUTE)";
        $params = [$identifier, $ip, $actionType, $minutes];
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->fetchColumn();

        if ($count >= $limit) {
            sendJsonResponse('error', "Demasiados intentos. Por seguridad, espera $minutes minutos antes de intentar nuevamente.");
        }
    } catch (Exception $e) {
        error_log("Rate Limit Check Error: " . $e->getMessage());
    }
}

// --- LÓGICA PRINCIPAL ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if (empty($input)) {
        $json = json_decode(file_get_contents('php://input'), true);
        if ($json) $input = $json;
    }

    // VERIFICACIÓN CSRF
    $incomingToken = $input['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($incomingToken) || empty($sessionToken) || !hash_equals($sessionToken, $incomingToken)) {
        sendJsonResponse('error', 'Sesión inválida (CSRF). Recarga la página.');
    }

    $action = $input['action'] ?? '';

    // 1. REGISTRO PASO 1
    if ($action === 'register_step_1') {
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if ($email && $password) {
            $val = validateEmailRequirements($email);
            if ($val !== true) sendJsonResponse('error', $val);
            if (strlen($password) < 8) sendJsonResponse('error', "La contraseña debe tener 8+ caracteres.");

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                sendJsonResponse('error', "El correo ya está registrado.");
            } else {
                $_SESSION['temp_register'] = ['email' => $email, 'password' => $password];
                sendJsonResponse('success', "Datos válidos", $basePath . "register/aditional-data");
            }
        } else {
            sendJsonResponse('error', "Faltan datos.");
        }
    }

    // 2. REGISTRO PASO 2 (Envío inicial)
    if ($action === 'register_step_2') {
        $username = trim($input['username'] ?? '');
        
        if (!isset($_SESSION['temp_register'])) {
            sendJsonResponse('error', "Sesión expirada.", $basePath . "register");
        }
        $email = $_SESSION['temp_register']['email'];
        $password = $_SESSION['temp_register']['password'];

        if ($username) {
            if (strlen($username) < 6) sendJsonResponse('error', "Usuario muy corto (min 6).");

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);

            if ($stmt->rowCount() > 0) {
                sendJsonResponse('error', "Usuario en uso.");
            } else {
                $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation'")->execute([$email]);
                
                $code = generate_verification_code();
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $payload = json_encode(['username' => $username, 'email' => $email, 'password' => $passwordHash]);

                $stmt = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
                
                if ($stmt->execute([$email, 'account_activation', $code, $payload])) {
                    $_SESSION['pending_verification_email'] = $email;
                    unset($_SESSION['temp_register']);
                    sendJsonResponse('success', "Código enviado", $basePath . "register/verify");
                } else {
                    sendJsonResponse('error', "Error BD.");
                }
            }
        } else {
            sendJsonResponse('error', "Nombre de usuario requerido.");
        }
    }

    // 3. REENVIAR CÓDIGO
    if ($action === 'resend_verification_code') {
        if (!isset($_SESSION['pending_verification_email'])) {
            sendJsonResponse('error', "Sin sesión de verificación.");
        }

        $email = $_SESSION['pending_verification_email'];

        $checkStmt = $pdo->prepare("SELECT created_at FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation' AND created_at > (NOW() - INTERVAL 60 SECOND) ORDER BY id DESC LIMIT 1");
        $checkStmt->execute([$email]);
        
        if ($checkStmt->rowCount() > 0) {
            sendJsonResponse('error', "Debes esperar 60 segundos antes de reenviar.");
        }

        $newCode = generate_verification_code();
        $stmtLast = $pdo->prepare("SELECT payload FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation' ORDER BY id DESC LIMIT 1");
        $stmtLast->execute([$email]);
        $lastRow = $stmtLast->fetch();

        if ($lastRow) {
            $payload = $lastRow['payload'];
            $stmtInsert = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
            
            if ($stmtInsert->execute([$email, 'account_activation', $newCode, $payload])) {
                sendJsonResponse('success', "Código reenviado: " . $newCode);
            } else {
                sendJsonResponse('error', "Error al guardar.");
            }
        } else {
            sendJsonResponse('error', "Error de sesión. Regístrate de nuevo.", $basePath . "register");
        }
    }

    // 4. VERIFICAR CÓDIGO
    if ($action === 'verify_code') {
        $code = trim($input['code'] ?? '');
        $emailIdentifier = $_SESSION['pending_verification_email'] ?? null;

        if ($code && $emailIdentifier) {
            checkRateLimit($pdo, $emailIdentifier, 'verify_fail', 5, 15);

            $stmt = $pdo->prepare("SELECT * FROM verification_codes WHERE identifier = ? AND code = ? AND code_type = 'account_activation' AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
            $stmt->execute([$emailIdentifier, $code]);
            $row = $stmt->fetch();

            if ($row) {
                $payload = json_decode($row['payload'], true);
                $uuid = generate_uuid();

                $insertUser = $pdo->prepare("INSERT INTO users (username, email, password, uuid) VALUES (?, ?, ?, ?)");
                if ($insertUser->execute([$payload['username'], $payload['email'], $payload['password'], $uuid])) {
                    $newId = $pdo->lastInsertId();
                    $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ?")->execute([$emailIdentifier]);

                    try {
                        $url = "https://ui-avatars.com/api/?name=" . urlencode($payload['username']) . "&background=random&color=fff&size=128";
                        $img = @file_get_contents($url);
                        if ($img) {
                            $dir = __DIR__ . '/../public/assets/uploads/profile_pictures/';
                            if (!is_dir($dir)) @mkdir($dir, 0755, true);
                            @file_put_contents($dir . $uuid . '.png', $img);
                        }
                    } catch (Exception $e) {}

                    $_SESSION['user_id'] = $newId;
                    $_SESSION['username'] = $payload['username'];
                    $_SESSION['uuid'] = $uuid;
                    $_SESSION['role'] = 'user';
                    unset($_SESSION['pending_verification_email']);
                    logUserAccess($pdo, $newId);
                    
                    sendJsonResponse('success', "Cuenta creada.", $basePath);
                } else {
                    sendJsonResponse('error', "Error al crear usuario.");
                }
            } else {
                logSecurityEvent($pdo, $emailIdentifier, 'verify_fail');
                sendJsonResponse('error', "Código inválido o expirado.");
            }
        } else {
            sendJsonResponse('error', "Ingresa el código.");
        }
    }

    // 5. LOGIN
    if ($action === 'login') {
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        checkRateLimit($pdo, $email, 'login_fail', 5, 15);

        $stmt = $pdo->prepare("SELECT id, username, password, uuid, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['uuid'] = $user['uuid'];
            $_SESSION['role'] = $user['role'];
            logUserAccess($pdo, $user['id']);
            sendJsonResponse('success', "Bienvenido", $basePath);
        } else {
            logSecurityEvent($pdo, $email, 'login_fail');
            sendJsonResponse('error', "Credenciales incorrectas.");
        }
    }

    // 6. ACTUALIZAR PERFIL (USERNAME/EMAIL)
    if ($action === 'update_profile') {
        if (!isset($_SESSION['user_id'])) sendJsonResponse('error', "No autenticado.");

        $userId = $_SESSION['user_id'];
        $newUsername = trim($input['username'] ?? '');
        $newEmail = trim($input['email'] ?? '');

        if (empty($newUsername) || empty($newEmail)) sendJsonResponse('error', "Campos requeridos.");
        if (strlen($newUsername) < 6) sendJsonResponse('error', "Usuario: mín. 6 caracteres.");

        $emailVal = validateEmailRequirements($newEmail);
        if ($emailVal !== true) sendJsonResponse('error', $emailVal);

        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmtCheck->execute([$newUsername, $newEmail, $userId]);
        
        if ($stmtCheck->rowCount() > 0) {
            sendJsonResponse('error', "El nombre de usuario o correo ya está en uso.");
        }

        $updateStmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        if ($updateStmt->execute([$newUsername, $newEmail, $userId])) {
            $_SESSION['username'] = $newUsername;
            sendJsonResponse('success', "Perfil actualizado.", null, ['username' => $newUsername, 'email' => $newEmail]);
        } else {
            sendJsonResponse('error', "Error al guardar cambios.");
        }
    }

    // 7. SUBIR FOTO DE PERFIL (NUEVO)
    if ($action === 'upload_profile_picture') {
        if (!isset($_SESSION['user_id'])) sendJsonResponse('error', "No autenticado.");
        
        // Verificar si se subió el archivo
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
             sendJsonResponse('error', "Error al subir la imagen.");
        }

        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        
        // Validar MIME
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        
        if (!in_array($mime, $allowedTypes)) {
            sendJsonResponse('error', "Formato inválido (solo JPG, PNG, WEBP).");
        }
        
        // Validar tamaño (2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            sendJsonResponse('error', "La imagen pesa más de 2MB.");
        }

        // Procesar imagen (Estandarizar a PNG)
        $uuid = $_SESSION['uuid'];
        $targetDir = __DIR__ . '/../public/assets/uploads/profile_pictures/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        
        $targetFile = $targetDir . $uuid . '.png';
        $src = null;

        switch ($mime) {
            case 'image/jpeg': $src = imagecreatefromjpeg($file['tmp_name']); break;
            case 'image/png':  $src = imagecreatefrompng($file['tmp_name']); break;
            case 'image/webp': $src = imagecreatefromwebp($file['tmp_name']); break;
        }

        if ($src) {
            // Guardar con compresión básica (calidad 9/0-9 para PNG)
            imagepng($src, $targetFile, 9);
            imagedestroy($src);
            
            // Devolver URL con timestamp para bustear caché del navegador
            sendJsonResponse('success', "Foto actualizada.", null, [
                'url' => $basePath . 'assets/uploads/profile_pictures/' . $uuid . '.png?v=' . time()
            ]);
        } else {
             // Fallback si GD falla
             if(move_uploaded_file($file['tmp_name'], $targetFile)) {
                sendJsonResponse('success', "Foto actualizada.", null, [
                    'url' => $basePath . 'assets/uploads/profile_pictures/' . $uuid . '.png?v=' . time()
                ]);
             } else {
                sendJsonResponse('error', "Error al procesar imagen.");
             }
        }
    }

    // 8. ELIMINAR FOTO DE PERFIL (NUEVO)
    if ($action === 'delete_profile_picture') {
         if (!isset($_SESSION['user_id'])) sendJsonResponse('error', "No autenticado.");
         $uuid = $_SESSION['uuid'];
         $targetFile = __DIR__ . '/../public/assets/uploads/profile_pictures/' . $uuid . '.png';
         
         if (file_exists($targetFile)) {
             unlink($targetFile);
             // Devolver la URL del avatar por defecto
             $defaultUrl = 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['username']) . '&background=random&color=fff&size=128';
             sendJsonResponse('success', "Foto eliminada.", null, ['url' => $defaultUrl]);
         } else {
             sendJsonResponse('error', "No tienes foto personalizada.");
         }
    }

    // 9. RECUPERAR PASSWORD
    if ($action === 'request_password_reset') {
        $email = trim($input['email'] ?? '');
        checkRateLimit($pdo, $email, 'recovery_request', 3, 60, true);
        logSecurityEvent($pdo, $email, 'recovery_request');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) sendJsonResponse('error', "Correo inválido.");

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $checkLimit = $pdo->prepare("SELECT id FROM password_resets WHERE email = ? AND created_at > (NOW() - INTERVAL 60 SECOND)");
            $checkLimit->execute([$email]);
            if ($checkLimit->rowCount() > 0) {
                sendJsonResponse('error', "Espera 60s para otro enlace.");
            }

            $token = bin2hex(random_bytes(32));
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            $ins = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
            if ($ins->execute([$email, $token])) {
                sendJsonResponse('success', "Enlace generado (backend).");
            }
        } else {
            sendJsonResponse('error', "Si existe, se enviará.");
        }
    }

    // 10. RESTABLECER PASSWORD
    if ($action === 'reset_password') {
        $token = $input['token'] ?? '';
        $newPass = $input['password'] ?? '';
        if (strlen($newPass) < 8) sendJsonResponse('error', "Mínimo 8 caracteres.");

        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $req = $stmt->fetch();

        if ($req) {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$hash, $req['email']]);
            $pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
            sendJsonResponse('success', "Contraseña actualizada.", $basePath . "login");
        } else {
            sendJsonResponse('error', "Token inválido.");
        }
    }

    // 11. LOGOUT
    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        sendJsonResponse('success', "Sesión cerrada.", $basePath . "login");
    }

    sendJsonResponse('error', "Acción no válida.");
}
?>