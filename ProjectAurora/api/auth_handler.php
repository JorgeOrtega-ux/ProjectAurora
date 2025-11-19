<?php
// api/auth_handler.php

// --- CONFIGURACIÓN DE LOGS ---
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/php_error.log';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}
error_reporting(E_ALL);
ini_set('ignore_repeated_errors', TRUE);
ini_set('display_errors', FALSE);
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

function logger($message)
{
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] [CUSTOM] $message" . PHP_EOL, FILE_APPEND);
}
// --- FIN CONFIGURACIÓN ---

// [SEGURIDAD] Configuración de Cookies de Sesión (Igual que en router.php)
if (session_status() === PHP_SESSION_NONE) {
    // --- NUEVO: Persistencia de Sesión (30 días) ---
    $lifetime = 60 * 60 * 24 * 30;
    ini_set('session.cookie_lifetime', $lifetime);
    ini_set('session.gc_maxlifetime', $lifetime);
    // -----------------------------------------------

    ini_set('session.cookie_httponly', 1);
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    ini_set('session.cookie_secure', $isHttps ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');

    session_start();
}

header('Content-Type: application/json');

date_default_timezone_set('America/Matamoros');

require_once '../config/database.php';
require_once '../config/utilities.php';

// VALIDACIÓN CSRF
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF).']);
    exit;
}

try {
    $now = new DateTime();
    $mins = $now->getOffset() / 60;
    $sgn = ($mins < 0 ? -1 : 1);
    $mins = abs($mins);
    $hrs = floor($mins / 60);
    $mins -= $hrs * 60;
    $offset = sprintf('%+d:%02d', $hrs * $sgn, $mins);
    $pdo->exec("SET time_zone='$offset';");
} catch (Exception $e) {
    logger("Warning: No se pudo sincronizar time_zone SQL: " . $e->getMessage());
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// --- FUNCIONES AUXILIARES ---

/**
 * Genera un UUID v4 criptográficamente seguro usando random_bytes().
 */
function generate_uuid()
{
    $data = random_bytes(16);
    assert(strlen($data) == 16);

    // Set version to 0100 (UUID v4)
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function get_random_color()
{
    // Lista de colores permitidos (sin el # para la API de UI Avatars)
    $colors = [
        'C84F4F', // Rojo esmeralda-like
        '4F7AC8', // Azul esmeralda-like
        '8C4FC8', // Morado esmeralda-like
        'C87A4F', // Naranja esmeralda-like
        '4FC8C8'  // Cian esmeralda-like
    ];
    return $colors[array_rand($colors)];
}

function generate_verification_code()
{
    // random_bytes es seguro criptográficamente
    return strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
}

function is_allowed_domain($email)
{
    return preg_match('/@(gmail|outlook|icloud|yahoo)\.[a-z]{2,}(\.[a-z]{2,})?$/i', $email);
}

function set_user_session($user)
{
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_uuid'] = $user['uuid'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_avatar'] = $user['avatar'];
    $_SESSION['user_role'] = $user['role'];
}

function mask_email($email)
{
    $parts = explode('@', $email);
    if (count($parts) == 2) {
        return substr($parts[0], 0, 3) . '***@' . $parts[1];
    }
    return $email;
}

$response = ['success' => false, 'message' => 'Acción no válida'];

try {
    // ==================================================================
    // REGISTRO - ETAPA 1
    // ==================================================================
    if ($action === 'register_step_1') {
        // [CORRECCIÓN APLICADA]
        $email = strtolower(filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) throw new Exception('Completa todos los campos.');
        if (strlen($email) < 4) throw new Exception('Correo muy corto.');
        if (!is_allowed_domain($email)) throw new Exception('Dominio no permitido.');
        if (strlen($password) < 8) throw new Exception('Contraseña muy corta.');

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) throw new Exception('El correo ya está registrado.');

        if (!isset($_SESSION['temp_register'])) $_SESSION['temp_register'] = [];
        $_SESSION['temp_register']['email'] = $email;
        $_SESSION['temp_register']['password'] = $password;

        $response = ['success' => true, 'message' => 'Paso 1 OK'];

        // ==================================================================
        // REGISTRO - ETAPA 2
        // ==================================================================
    } elseif ($action === 'register_step_2') {
        $username = trim($data['username'] ?? '');
        $email = $_SESSION['temp_register']['email'] ?? '';
        $rawPassword = $_SESSION['temp_register']['password'] ?? '';

        if (empty($email) || empty($rawPassword)) throw new Exception('Sesión expirada.');

        if (!preg_match('/^[a-zA-Z0-9_]{8,32}$/', $username)) throw new Exception('Usuario inválido.');

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) throw new Exception('El usuario ya existe.');

        $passwordHash = password_hash($rawPassword, PASSWORD_BCRYPT);
        $payload = json_encode(['username' => $username, 'password_hash' => $passwordHash]);
        $code = generate_verification_code();

        $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'registration'")->execute([$email]);

        $sql = "INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, 'registration', ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $code, $payload]);

        unset($_SESSION['temp_register']['password']);
        $_SESSION['temp_register']['username'] = $username;

        // [CAMBIO] Código oculto en logs
        logger("Code registro generado para el usuario. (Oculto por seguridad)");
        
        $response = ['success' => true, 'message' => 'Código enviado'];

        // ==================================================================
        // REGISTRO - ETAPA 3
        // ==================================================================
    } elseif ($action === 'register_final') {
        $inputCode = strtoupper(trim($data['code'] ?? ''));
        $email = $_SESSION['temp_register']['email'] ?? '';

        if (empty($email)) throw new Exception('Sesión perdida.');

        $sql = "SELECT id, payload FROM verification_codes WHERE identifier = ? AND code = ? AND code_type = 'registration' AND expires_at > NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $inputCode]);
        $row = $stmt->fetch();

        if (!$row) throw new Exception('Código inválido o expirado.');

        $payloadData = json_decode($row['payload'], true);
        $finalUsername = $payloadData['username'];
        $finalPassHash = $payloadData['password_hash'];

        // UUID Seguro
        $uuid = generate_uuid();
        $selectedColor = get_random_color();

        $apiUrl = "https://ui-avatars.com/api/?name={$finalUsername}&size=256&background={$selectedColor}&color=ffffff&bold=true&length=1";
        $fileName = $uuid . '.png';
        $destPath = __DIR__ . '/../public/assets/uploads/avatars/' . $fileName;
        $dbPath = 'assets/uploads/avatars/' . $fileName;
        $imageContent = @file_get_contents($apiUrl);
        if ($imageContent !== false) file_put_contents($destPath, $imageContent);
        else $dbPath = null;

        $insert = $pdo->prepare("INSERT INTO users (uuid, email, username, password, avatar, role) VALUES (?, ?, ?, ?, ?, 'user')");
        if ($insert->execute([$uuid, $email, $finalUsername, $finalPassHash, $dbPath])) {
            $newUserId = $pdo->lastInsertId();

            $newUser = [
                'id' => $newUserId,
                'uuid' => $uuid,
                'email' => $email,
                'avatar' => $dbPath,
                'role' => 'user'
            ];

            // Regenerar ID tras registro y autologin
            session_regenerate_id(true);
            set_user_session($newUser);

            $pdo->prepare("DELETE FROM verification_codes WHERE id = ?")->execute([$row['id']]);
            unset($_SESSION['temp_register']);

            $response = ['success' => true, 'message' => 'Bienvenido'];
        } else {
            throw new Exception('Error crítico.');
        }

        // ==================================================================
        // LOGIN (CON SOPORTE 2FA INTEGRADO)
        // ==================================================================
    } elseif ($action === 'login') {
        // [CORRECCIÓN APLICADA]
        $email = strtolower(filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $password = $data['password'] ?? '';

        if (checkLockStatus($pdo, $email, 'login_fail')) {
            throw new Exception("Has excedido intentos. Espera " . LOCKOUT_TIME_MINUTES . " mins.");
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            if (isset($user['account_status']) && $user['account_status'] !== 'active') {
                throw new Exception('Tu cuenta no está activa o ha sido suspendida.');
            }

            // LÓGICA 2FA
            if (isset($user['is_2fa_enabled']) && $user['is_2fa_enabled'] == 1) {

                $code = generate_verification_code();

                $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'login_2fa'")->execute([$email]);

                $stmt = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, expires_at) VALUES (?, 'login_2fa', ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
                $stmt->execute([$email, $code]);

                if (!isset($_SESSION['temp_login_2fa'])) $_SESSION['temp_login_2fa'] = [];
                $_SESSION['temp_login_2fa']['user_id'] = $user['id'];
                $_SESSION['temp_login_2fa']['email'] = $user['email'];

                // [CAMBIO] Código oculto en logs
                logger("Code 2FA Login para $email generado. (Oculto por seguridad)");

                $response = [
                    'success' => true,
                    'require_2fa' => true,
                    'message' => 'Verificación requerida',
                    'masked_email' => mask_email($email)
                ];
            } else {
                clearFailedAttempts($pdo, $email);

                // [SEGURIDAD] Prevención fijación de sesión
                session_regenerate_id(true);

                set_user_session($user);
                $response = ['success' => true, 'message' => 'Login correcto'];
            }
        } else {
            logFailedAttempt($pdo, $email, 'login_fail');
            throw new Exception('Credenciales incorrectas.');
        }

        // ==================================================================
        // LOGIN 2FA VERIFICATION
        // ==================================================================
    } elseif ($action === 'login_2fa_verify') {
        $inputCode = strtoupper(trim($data['code'] ?? ''));

        if (empty($_SESSION['temp_login_2fa']['user_id'])) {
            throw new Exception("Sesión expirada. Vuelve a iniciar login.");
        }

        $userId = $_SESSION['temp_login_2fa']['user_id'];
        $email = $_SESSION['temp_login_2fa']['email'];

        if (checkLockStatus($pdo, $email, 'login_2fa_fail')) {
            throw new Exception("Muchos intentos fallidos. Espera unos minutos.");
        }

        $stmt = $pdo->prepare("SELECT id FROM verification_codes WHERE identifier = ? AND code = ? AND code_type = 'login_2fa' AND expires_at > NOW()");
        $stmt->execute([$email, $inputCode]);

        if ($stmt->rowCount() > 0) {
            $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'login_2fa'")->execute([$email]);

            $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $user = $stmtUser->fetch();

            if (!$user) throw new Exception("Usuario no encontrado.");

            clearFailedAttempts($pdo, $email);

            // [SEGURIDAD] Prevención fijación de sesión
            session_regenerate_id(true);

            set_user_session($user);
            unset($_SESSION['temp_login_2fa']);

            $response = ['success' => true, 'message' => 'Autenticación completada'];
        } else {
            logFailedAttempt($pdo, $email, 'login_2fa_fail');
            throw new Exception("Código incorrecto o expirado.");
        }

        // ==================================================================
        // RECUPERACIÓN Y LOGOUT
        // ==================================================================

        // [INICIO DE MODIFICACIÓN IMPORTANTE]
    } elseif ($action === 'recovery_step_1') {
        $email = strtolower(filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL));

        // Preparamos la sesión en CUALQUIER CASO para mostrar el email en el siguiente paso
        if (!isset($_SESSION['temp_recovery'])) $_SESSION['temp_recovery'] = [];
        $_SESSION['temp_recovery']['email'] = $email; // Guardamos el correo (incluso si no existe)
        $_SESSION['temp_recovery']['step'] = 2;

        // [NUEVA VALIDACIÓN]
        if (!empty($email) && is_allowed_domain($email)) {

            // El correo TIENE UN FORMATO VÁLIDO, ahora buscamos si existe
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            // SOLO si el usuario existe, generamos y guardamos el código
            if ($stmt->rowCount() > 0) {
                $code = generate_verification_code();

                // Borrar códigos de recuperación anteriores para este email
                $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'recovery'")->execute([$email]);

                // Insertar el nuevo código
                $stmt = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, expires_at) VALUES (?, 'recovery', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
                $stmt->execute([$email, $code]);

                // [CAMBIO] Código oculto en logs
                logger("Code Recup generado para $email. (Oculto por seguridad)");
            } else {
                // El correo no existe, pero el formato era válido.
                logger("Intento de recuperación para correo no existente (formato válido): $email");
            }
        } else {
            // El correo tenía formato inválido (o estaba vacío)
            if (!empty($email)) {
                logger("Intento de recuperación con formato de correo inválido: $email");
            }
        }

        // SIEMPRE devolvemos éxito para evitar enumeración
        $response = ['success' => true, 'message' => 'Solicitud procesada'];
        // [FIN DE MODIFICACIÓN IMPORTANTE]

    } elseif ($action === 'recovery_step_2') {
        $email = $_SESSION['temp_recovery']['email'] ?? '';
        $inputCode = strtoupper(trim($data['code'] ?? ''));

        $stmt = $pdo->prepare("SELECT id FROM verification_codes WHERE identifier = ? AND code = ? AND code_type = 'recovery' AND expires_at > NOW()");
        $stmt->execute([$email, $inputCode]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['temp_recovery']['verified'] = true;
            $_SESSION['temp_recovery']['step'] = 3;
            $response = ['success' => true, 'message' => 'Código correcto'];
        } else {
            throw new Exception('Código incorrecto.');
        }
    } elseif ($action === 'recovery_final') {
        $email = $_SESSION['temp_recovery']['email'] ?? '';
        $verified = $_SESSION['temp_recovery']['verified'] ?? false;
        $newPass = $data['password'] ?? '';

        if (empty($email) || !$verified) throw new Exception('No autorizado.');
        $newHash = password_hash($newPass, PASSWORD_BCRYPT);

        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        if ($upd->execute([$newHash, $email])) {
            $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'recovery'")->execute([$email]);
            unset($_SESSION['temp_recovery']);
            clearFailedAttempts($pdo, $email);
            $response = ['success' => true, 'message' => 'Contraseña actualizada.'];
        } else {
            throw new Exception('Error BD.');
        }

        // ==================================================================
        // [NUEVO] LOGOUT SEGURO (ANTI-CSRF)
        // ==================================================================
    } elseif ($action === 'logout') {

        // 1. Vaciar el array de sesión
        $_SESSION = [];

        // 2. Borrar la cookie de sesión del navegador
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // 3. Destruir la sesión en el servidor
        session_destroy();

        // 4. Responder con éxito (el cliente JS redirigirá)
        $response = ['success' => true, 'message' => 'Sesión cerrada correctamente'];
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    if (strpos($e->getMessage(), 'espera') === false) {
        logger("Error: " . $e->getMessage());
    }
}

echo json_encode($response);
exit;