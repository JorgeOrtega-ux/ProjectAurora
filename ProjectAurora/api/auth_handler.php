<?php
// --- CONFIGURACIÓN DE LOGS ---
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/php_error.log';
if (!file_exists($logDir)) { mkdir($logDir, 0777, true); }
error_reporting(E_ALL);
ini_set('ignore_repeated_errors', TRUE);
ini_set('display_errors', FALSE); 
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

function logger($message) {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] [CUSTOM] $message" . PHP_EOL, FILE_APPEND);
}
// --- FIN CONFIGURACIÓN ---

session_start();
header('Content-Type: application/json'); 
require_once '../config/database.php';

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// --- FUNCIONES AUXILIARES ---
function generate_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}
function get_random_color() {
    return str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
}
function generate_verification_code() {
    // Código alfanumérico de 10 caracteres (ajustable)
    return strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
}
function is_allowed_domain($email) {
    return preg_match('/@(gmail|outlook|icloud|yahoo)\.[a-z]{2,}(\.[a-z]{2,})?$/i', $email);
}

$response = ['success' => false, 'message' => 'Acción no válida'];

try {
    // ==================================================================
    // REGISTRO - ETAPA 1: Validación Inicial (Solo en Memoria/Sesión)
    // ==================================================================
    if ($action === 'register_step_1') {
        $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) throw new Exception('Completa todos los campos.');
        if (strlen($email) < 4) throw new Exception('Correo muy corto.');
        if (!is_allowed_domain($email)) throw new Exception('Dominio no permitido (Use Gmail, Outlook, etc).');
        if (strlen($password) < 8) throw new Exception('Contraseña muy corta (mínimo 8).');

        // Verificar duplicados
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) throw new Exception('El correo ya está registrado.');

        // Guardamos en sesión solo para transportar al paso 2
        if (!isset($_SESSION['temp_register'])) $_SESSION['temp_register'] = [];
        $_SESSION['temp_register']['email'] = $email;
        $_SESSION['temp_register']['password'] = $password; // Aún en texto plano en sesión (temporal)

        $response = ['success' => true, 'message' => 'Paso 1 OK'];


    // ==================================================================
    // REGISTRO - ETAPA 2: Generar Payload y Guardar en BD
    // ==================================================================
    } elseif ($action === 'register_step_2') {
        $username = trim($data['username'] ?? '');
        $email = $_SESSION['temp_register']['email'] ?? '';
        $rawPassword = $_SESSION['temp_register']['password'] ?? '';

        if (empty($email) || empty($rawPassword)) throw new Exception('Sesión expirada. Recarga la página.');
        
        // Validar Username
        if (!preg_match('/^[a-zA-Z0-9_]{8,32}$/', $username)) {
            throw new Exception('Usuario inválido (8-32 carácteres alfanuméricos).');
        }
        
        // Verificar duplicado Username
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) throw new Exception('El usuario ya existe.');

        // --- AQUÍ LA MAGIA: PREPARAR PAYLOAD ---
        $passwordHash = password_hash($rawPassword, PASSWORD_BCRYPT);
        
        $payload = json_encode([
            'username' => $username,
            'password_hash' => $passwordHash
        ]);

        $code = generate_verification_code();

        // Limpiar códigos anteriores para este email y tipo
        $del = $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'registration'");
        $del->execute([$email]);

        // Insertar en la nueva estructura de tabla
        $sql = "INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, 'registration', ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))";
        $stmt = $pdo->prepare($sql);
        
        if (!$stmt->execute([$email, $code, $payload])) {
            throw new Exception("Error al guardar código de verificación.");
        }

        // (Opcional) Limpiamos el password plano de la sesión por seguridad
        unset($_SESSION['temp_register']['password']);
        // Guardamos username en sesión solo para mostrarlo en la UI si es necesario, pero la verdad está en la BD
        $_SESSION['temp_register']['username'] = $username; 

        logger("Code generado para $email: $code | Payload: $payload");

        $response = ['success' => true, 'message' => 'Código enviado'];


    // ==================================================================
    // REGISTRO - ETAPA 3: Verificar Payload y Crear Usuario
    // ==================================================================
    } elseif ($action === 'register_final') {
        $inputCode = strtoupper(trim($data['code'] ?? ''));
        $email = $_SESSION['temp_register']['email'] ?? '';

        if (empty($email)) throw new Exception('Sesión perdida.');

        // Buscar el código en la BD y EXTRAER EL PAYLOAD
        $sql = "SELECT id, payload FROM verification_codes WHERE identifier = ? AND code = ? AND code_type = 'registration' AND expires_at > NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $inputCode]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new Exception('Código inválido o expirado.');
        }

        // Decodificar el payload JSON de la BD
        $payloadData = json_decode($row['payload'], true);
        if (!$payloadData || !isset($payloadData['username']) || !isset($payloadData['password_hash'])) {
            throw new Exception('Error de integridad en los datos de registro.');
        }

        $finalUsername = $payloadData['username'];
        $finalPassHash = $payloadData['password_hash'];
        $uuid = generate_uuid();

        // Generar Avatar
        $selectedColor = get_random_color();
        $apiUrl = "https://ui-avatars.com/api/?name={$finalUsername}&size=256&background={$selectedColor}&color=ffffff&bold=true&length=1";
        
        $uploadDirRel = '/assets/uploads/avatars/'; 
        $uploadDirAbs = __DIR__ . '/..' . $uploadDirRel;
        if (!file_exists($uploadDirAbs)) { mkdir($uploadDirAbs, 0777, true); }
        
        $fileName = $uuid . '.png';
        $destPath = $uploadDirAbs . $fileName;
        $dbPath = 'assets/uploads/avatars/' . $fileName; 

        $imageContent = @file_get_contents($apiUrl);
        if ($imageContent !== false) file_put_contents($destPath, $imageContent);
        else $dbPath = null;

        // Insertar Usuario Final
        $insert = $pdo->prepare("INSERT INTO users (uuid, email, username, password, avatar, role) VALUES (?, ?, ?, ?, ?, 'user')");
        if ($insert->execute([$uuid, $email, $finalUsername, $finalPassHash, $dbPath])) {
            
            // Auto Login
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_uuid'] = $uuid;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = 'user';
            $_SESSION['user_avatar'] = $dbPath;

            // Borrar el código usado
            $pdo->prepare("DELETE FROM verification_codes WHERE id = ?")->execute([$row['id']]);
            
            // Limpiar sesión de registro completamente
            unset($_SESSION['temp_register']);

            $response = ['success' => true, 'message' => 'Bienvenido a Project Aurora'];
            logger("Usuario creado: $finalUsername ($email)");
        } else {
            throw new Exception('Error crítico al crear usuario.');
        }

    // ==================================================================
    // LOGIN
    // ==================================================================
    } elseif ($action === 'login') {
        $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $data['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_uuid'] = $user['uuid'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_avatar'] = $user['avatar'];
            $_SESSION['user_role'] = $user['role'];
            $response = ['success' => true, 'message' => 'Login correcto'];
        } else {
            throw new Exception('Credenciales incorrectas.');
        }

    // ==================================================================
    // NUEVO: RECUPERACIÓN DE CONTRASEÑA (3 PASOS)
    // ==================================================================

    // --- PASO 1: Verificar email y Enviar código ---
    } elseif ($action === 'recovery_step_1') {
        $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        
        // Verificar si existe el usuario
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        // Por seguridad, a veces no se dice si existe o no, pero para UX diremos si no existe.
        if ($stmt->rowCount() === 0) {
            throw new Exception('Este correo no está registrado.');
        }

        // Generar código
        $code = generate_verification_code();

        // Limpiar códigos previos de recuperación
        $del = $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'recovery'");
        $del->execute([$email]);

        // Insertar nuevo código (No payload necesario aquí, solo validar propiedad)
        $stmt = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, expires_at) VALUES (?, 'recovery', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
        $stmt->execute([$email, $code]);

        // Guardar en sesión para flujo
        if (!isset($_SESSION['temp_recovery'])) $_SESSION['temp_recovery'] = [];
        $_SESSION['temp_recovery']['email'] = $email;
        $_SESSION['temp_recovery']['step'] = 2; // Mover al paso 2 visualmente

        logger("Code RECUPERACION para $email: $code");
        $response = ['success' => true, 'message' => 'Código enviado'];

    // --- PASO 2: Verificar Código ---
    } elseif ($action === 'recovery_step_2') {
        $email = $_SESSION['temp_recovery']['email'] ?? '';
        $inputCode = strtoupper(trim($data['code'] ?? ''));

        if (empty($email)) throw new Exception('Sesión expirada.');

        $stmt = $pdo->prepare("SELECT id FROM verification_codes WHERE identifier = ? AND code = ? AND code_type = 'recovery' AND expires_at > NOW()");
        $stmt->execute([$email, $inputCode]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['temp_recovery']['verified'] = true; // Marca segura
            $_SESSION['temp_recovery']['step'] = 3; // Mover al paso 3 visualmente
            $response = ['success' => true, 'message' => 'Código correcto'];
        } else {
            throw new Exception('Código incorrecto o expirado.');
        }

    // --- PASO 3: Cambiar Contraseña ---
    } elseif ($action === 'recovery_final') {
        $email = $_SESSION['temp_recovery']['email'] ?? '';
        $verified = $_SESSION['temp_recovery']['verified'] ?? false;
        $newPass = $data['password'] ?? '';

        if (empty($email) || !$verified) throw new Exception('Acceso no autorizado al paso final.');
        if (strlen($newPass) < 8) throw new Exception('La contraseña debe tener mín. 8 caracteres.');

        $newHash = password_hash($newPass, PASSWORD_BCRYPT);

        // Actualizar Usuario
        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        if ($upd->execute([$newHash, $email])) {
            
            // Limpiar códigos usados
            $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = 'recovery'")->execute([$email]);
            
            // Limpiar sesión temporal
            unset($_SESSION['temp_recovery']);

            $response = ['success' => true, 'message' => 'Contraseña actualizada correctamente.'];
        } else {
            throw new Exception('Error al actualizar base de datos.');
        }

    } elseif ($action === 'logout') {
        session_destroy();
        $response = ['success' => true, 'message' => 'Bye'];
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    logger("Error: " . $e->getMessage());
}

echo json_encode($response);
exit;
?>