<?php
// api/auth_handler.php

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php'; 
require_once __DIR__ . '/../includes/i18n.php'; // <--- Cargar I18N

// 1. CARGAR TRADUCCIONES PARA LA API
// Necesitamos saber el idioma. Como no pasamos por router.php, hacemos la lógica aquí.
if (session_status() === PHP_SESSION_NONE) session_start();
$userId = $_SESSION['user_id'] ?? 0;
$lang = null;

if ($userId) {
    // Si hay usuario logueado, intentar sacar preferencia de DB (rápido)
    try {
        $stmt = $pdo->prepare("SELECT language FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $lang = $stmt->fetchColumn();
    } catch(Exception $e){}
}

if (!$lang) {
    $lang = detect_browser_language(); // Función ahora en i18n.php
}

load_translations($lang);

// ==========================================
// CONFIGURACIÓN DE RUTAS DE AVATARES
// ==========================================
define('UPLOAD_BASE_DIR', __DIR__ . '/../public/assets/uploads/avatars/');
define('DIR_CUSTOM', UPLOAD_BASE_DIR . 'custom/');
define('DIR_DEFAULT', UPLOAD_BASE_DIR . 'default/');
define('URL_BASE_AVATARS', 'assets/uploads/avatars/');

if (!is_dir(DIR_CUSTOM)) @mkdir(DIR_CUSTOM, 0755, true);
if (!is_dir(DIR_DEFAULT)) @mkdir(DIR_DEFAULT, 0755, true);

// ==========================================
// FUNCIONES AUXILIARES
// ==========================================

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
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return __('api.error.email_format');
    $atPos = strrpos($email, '@');
    if ($atPos === false) return __('api.error.email_format');
    
    $prefix = substr($email, 0, $atPos);
    $domain = strtolower(substr($email, $atPos + 1));
    
    if (strlen($prefix) < 4) return __('api.error.email_format'); // Mensaje genérico para simplificar o crear clave especifica
    $allowedDomains = ['gmail.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'yahoo.com'];
    if (!in_array($domain, $allowedDomains)) return __('api.error.email_domain');
    
    return true;
}

function ensureDefaultAvatarExists($uuid, $username) {
    $targetFile = DIR_DEFAULT . $uuid . '.png';
    if (!file_exists($targetFile)) {
        try {
            $randomColor = str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
            $url = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=" . $randomColor . "&color=fff&size=128&format=png";
            $imgContent = @file_get_contents($url);
            if ($imgContent) {
                @file_put_contents($targetFile, $imgContent);
                return true;
            }
        } catch (Exception $e) { }
        return false;
    }
    return true; 
}

// --- SEGURIDAD (RATE LIMITING) ---

function logSecurityEvent($pdo, $identifier, $actionType) {
    try {
        $ip = getClientIP();
        $identifier = substr($identifier, 0, 250);
        $stmt = $pdo->prepare("INSERT INTO security_logs (user_identifier, action_type, ip_address, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$identifier, $actionType, $ip]);
    } catch (Exception $e) {}
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
            sendJsonResponse('error', __('api.error.rate_limit'));
        }
    } catch (Exception $e) {}
}

// ==========================================
// LÓGICA PRINCIPAL
// ==========================================

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
        sendJsonResponse('error', __('api.error.csrf'));
    }

    $action = $input['action'] ?? '';

    // 1. REGISTRO PASO 1
    if ($action === 'register_step_1') {
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if ($email && $password) {
            $val = validateEmailRequirements($email);
            if ($val !== true) sendJsonResponse('error', $val);
            if (strlen($password) < 8) sendJsonResponse('error', __('api.error.password_short'));

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                sendJsonResponse('error', __('api.error.email_exists'));
            } else {
                $_SESSION['temp_register'] = ['email' => $email, 'password' => $password];
                sendJsonResponse('success', __('api.success.valid_data'), $basePath . "register/aditional-data");
            }
        } else {
            sendJsonResponse('error', __('api.error.missing_data'));
        }
    }

    // 2. REGISTRO PASO 2
    if ($action === 'register_step_2') {
        $username = trim($input['username'] ?? '');
        
        if (!isset($_SESSION['temp_register'])) {
            sendJsonResponse('error', __('api.error.session_expired'), $basePath . "register");
        }
        $email = $_SESSION['temp_register']['email'];
        $password = $_SESSION['temp_register']['password'];

        if ($username) {
            if (strlen($username) < 6) sendJsonResponse('error', __('api.error.username_short'));

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);

            if ($stmt->rowCount() > 0) {
                sendJsonResponse('error', __('api.error.username_exists'));
            } else {
                $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation'")->execute([$email]);
                
                $code = generate_verification_code();
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $payload = json_encode(['username' => $username, 'email' => $email, 'password' => $passwordHash]);

                $stmt = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
                
                if ($stmt->execute([$email, 'account_activation', $code, $payload])) {
                    $_SESSION['pending_verification_email'] = $email;
                    unset($_SESSION['temp_register']);
                    sendJsonResponse('success', __('api.success.code_sent'), $basePath . "register/verify");
                } else {
                    sendJsonResponse('error', __('api.error.db_error'));
                }
            }
        } else {
            sendJsonResponse('error', __('api.error.missing_data'));
        }
    }

    // 3. REENVIAR CÓDIGO
    if ($action === 'resend_verification_code') {
        if (!isset($_SESSION['pending_verification_email'])) {
            sendJsonResponse('error', __('api.error.missing_data'));
        }

        $email = $_SESSION['pending_verification_email'];
        $checkStmt = $pdo->prepare("SELECT created_at FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation' AND created_at > (NOW() - INTERVAL 60 SECOND) ORDER BY id DESC LIMIT 1");
        $checkStmt->execute([$email]);
        
        if ($checkStmt->rowCount() > 0) {
            sendJsonResponse('error', __('api.error.wait_resend'));
        }

        $newCode = generate_verification_code();
        $stmtLast = $pdo->prepare("SELECT payload FROM verification_codes WHERE identifier = ? AND code_type = 'account_activation' ORDER BY id DESC LIMIT 1");
        $stmtLast->execute([$email]);
        $lastRow = $stmtLast->fetch();

        if ($lastRow) {
            $payload = $lastRow['payload'];
            $stmtInsert = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
            if ($stmtInsert->execute([$email, 'account_activation', $newCode, $payload])) {
                sendJsonResponse('success', __('api.success.code_resent') . $newCode);
            } else {
                sendJsonResponse('error', __('api.error.db_error'));
            }
        } else {
            sendJsonResponse('error', __('api.error.session_expired'), $basePath . "register");
        }
    }

    // 4. VERIFICAR CÓDIGO (REGISTRO FINAL)
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

                    ensureDefaultAvatarExists($uuid, $payload['username']);

                    // USAR IDIOMA DETECTADO EN EL HANDLER O EL DE I18N
                    $detectedLang = detect_browser_language(); // Usamos la de i18n
                    $stmtPref = $pdo->prepare("INSERT INTO user_preferences (user_id, language, open_links_new_tab) VALUES (?, ?, 1)");
                    $stmtPref->execute([$newId, $detectedLang]);

                    $_SESSION['user_id'] = $newId;
                    $_SESSION['username'] = $payload['username'];
                    $_SESSION['uuid'] = $uuid;
                    $_SESSION['role'] = 'user';
                    unset($_SESSION['pending_verification_email']);
                    logUserAccess($pdo, $newId);
                    
                    sendJsonResponse('success', __('api.success.account_created'), $basePath);
                } else {
                    sendJsonResponse('error', __('api.error.db_error'));
                }
            } else {
                logSecurityEvent($pdo, $emailIdentifier, 'verify_fail');
                sendJsonResponse('error', __('api.error.invalid_code'));
            }
        } else {
            sendJsonResponse('error', __('api.error.missing_data'));
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
            sendJsonResponse('success', __('api.success.welcome'), $basePath);
        } else {
            logSecurityEvent($pdo, $email, 'login_fail');
            sendJsonResponse('error', __('api.error.credentials'));
        }
    }

    // 6. ACTUALIZAR PERFIL
    if ($action === 'update_profile') {
        if (!isset($_SESSION['user_id'])) sendJsonResponse('error', __('api.error.no_auth'));

        $userId = $_SESSION['user_id'];
        $newUsername = trim($input['username'] ?? '');
        $newEmail = trim($input['email'] ?? '');

        if (empty($newUsername) || empty($newEmail)) sendJsonResponse('error', __('api.error.missing_data'));
        if (strlen($newUsername) < 6) sendJsonResponse('error', __('api.error.username_short'));

        $emailVal = validateEmailRequirements($newEmail);
        if ($emailVal !== true) sendJsonResponse('error', $emailVal);

        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmtCheck->execute([$newUsername, $newEmail, $userId]);
        
        if ($stmtCheck->rowCount() > 0) {
            sendJsonResponse('error', __('api.error.username_exists'));
        }

        $updateStmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        if ($updateStmt->execute([$newUsername, $newEmail, $userId])) {
            $_SESSION['username'] = $newUsername;
            sendJsonResponse('success', __('api.success.profile_updated'), null, ['username' => $newUsername, 'email' => $newEmail]);
        } else {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    // 6.1 ACTUALIZAR PREFERENCIAS
    if ($action === 'update_preferences') {
        if (!isset($_SESSION['user_id'])) sendJsonResponse('error', __('api.error.no_auth'));
        $userId = $_SESSION['user_id'];
        
        $language = $input['language'] ?? null;
        $openLinks = isset($input['open_links_new_tab']) ? (int)$input['open_links_new_tab'] : null;

        $fields = [];
        $params = [];

        if ($language) {
            $allowedLangs = ['es-419', 'en-US', 'en-GB', 'fr-FR', 'pt-BR'];
            if (!in_array($language, $allowedLangs)) {
                // Silencioso o error
            } else {
                $fields[] = "language = ?";
                $params[] = $language;
            }
        }

        if ($openLinks !== null) {
            $fields[] = "open_links_new_tab = ?";
            $params[] = $openLinks; 
        }

        if (empty($fields)) {
            sendJsonResponse('success', "OK"); 
        }

        $params[] = $userId;
        $sql = "UPDATE user_preferences SET " . implode(', ', $fields) . " WHERE user_id = ?";
        
        try {
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                sendJsonResponse('success', __('api.success.preferences_saved'));
            } else {
                sendJsonResponse('error', __('api.error.db_error'));
            }
        } catch (Exception $e) {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    // 7. SUBIR FOTO
    if ($action === 'upload_profile_picture') {
        if (!isset($_SESSION['user_id'])) sendJsonResponse('error', __('api.error.no_auth'));
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
             sendJsonResponse('error', __('error.load_content'));
        }

        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        
        if (!in_array($mime, $allowedTypes)) {
            sendJsonResponse('error', __('api.error.upload_format'));
        }
        
        if ($file['size'] > 2 * 1024 * 1024) {
            sendJsonResponse('error', __('api.error.upload_size'));
        }

        $uuid = $_SESSION['uuid'];
        $targetFile = DIR_CUSTOM . $uuid . '.png';
        $src = null;

        switch ($mime) {
            case 'image/jpeg': $src = imagecreatefromjpeg($file['tmp_name']); break;
            case 'image/png':  $src = imagecreatefrompng($file['tmp_name']); break;
            case 'image/webp': $src = imagecreatefromwebp($file['tmp_name']); break;
        }

        $uploadSuccess = false;
        if ($src) {
            imagepng($src, $targetFile, 9);
            imagedestroy($src);
            $uploadSuccess = true;
        } else {
             if(move_uploaded_file($file['tmp_name'], $targetFile)) $uploadSuccess = true;
        }

        if ($uploadSuccess) {
            $defaultFile = DIR_DEFAULT . $uuid . '.png';
            if (file_exists($defaultFile)) unlink($defaultFile);
            $url = $basePath . URL_BASE_AVATARS . 'custom/' . $uuid . '.png?v=' . time();
            sendJsonResponse('success', __('api.success.photo_updated'), null, ['url' => $url]);
        } else {
            sendJsonResponse('error', __('api.error.db_error'));
        }
    }

    // 8. ELIMINAR FOTO
    if ($action === 'delete_profile_picture') {
         if (!isset($_SESSION['user_id'])) sendJsonResponse('error', __('api.error.no_auth'));
         $uuid = $_SESSION['uuid'];
         $username = $_SESSION['username'];
         
         $customFile = DIR_CUSTOM . $uuid . '.png';
         if (file_exists($customFile)) unlink($customFile);
         
         $defaultFile = DIR_DEFAULT . $uuid . '.png';
         if (file_exists($defaultFile)) unlink($defaultFile);

         ensureDefaultAvatarExists($uuid, $username);

         $defaultUrl = $basePath . URL_BASE_AVATARS . 'default/' . $uuid . '.png?v=' . time();
         sendJsonResponse('success', __('api.success.photo_deleted'), null, ['url' => $defaultUrl]);
    }

    // 9. PASSWORD RESET
    if ($action === 'request_password_reset') {
        $email = trim($input['email'] ?? '');
        checkRateLimit($pdo, $email, 'recovery_request', 3, 60, true);
        logSecurityEvent($pdo, $email, 'recovery_request');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) sendJsonResponse('error', __('api.error.email_format'));

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $checkLimit = $pdo->prepare("SELECT id FROM password_resets WHERE email = ? AND created_at > (NOW() - INTERVAL 60 SECOND)");
            $checkLimit->execute([$email]);
            if ($checkLimit->rowCount() > 0) {
                sendJsonResponse('error', __('api.error.wait_resend'));
            }

            $token = bin2hex(random_bytes(32));
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            $ins = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
            if ($ins->execute([$email, $token])) {
                sendJsonResponse('success', __('api.success.link_generated'));
            }
        } else {
            sendJsonResponse('error', __('api.success.link_generated')); // Seguridad: no revelar si existe
        }
    }

    if ($action === 'reset_password') {
        $token = $input['token'] ?? '';
        $newPass = $input['password'] ?? '';
        if (strlen($newPass) < 8) sendJsonResponse('error', __('api.error.password_short'));

        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $req = $stmt->fetch();

        if ($req) {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$hash, $req['email']]);
            $pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
            sendJsonResponse('success', __('api.success.password_updated'), $basePath . "login");
        } else {
            sendJsonResponse('error', __('api.error.invalid_code'));
        }
    }

    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        sendJsonResponse('success', __('api.success.logout'), $basePath . "login");
    }

    sendJsonResponse('error', "Action invalid");
}
?>