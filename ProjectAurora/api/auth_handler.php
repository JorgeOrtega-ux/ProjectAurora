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
    // 12 caracteres alfanuméricos en mayúsculas
    return strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
}

$response = ['success' => false, 'message' => 'Acción no válida'];

try {
    // ==================================================================
    // REGISTRO - ETAPA 1: Validar Email y Contraseña
    // ==================================================================
    if ($action === 'register_step_1') {
        $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            throw new Exception('Por favor completa todos los campos.');
        }

        // Verificar si el correo existe en BD usuarios
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            throw new Exception('El correo ya está registrado.');
        }

        // Guardar en sesión temporalmente
        if (!isset($_SESSION['temp_register'])) $_SESSION['temp_register'] = [];
        $_SESSION['temp_register']['email'] = $email;
        $_SESSION['temp_register']['password'] = $password;

        $response = ['success' => true, 'message' => 'Paso 1 OK'];
        logger("Registro Paso 1 (Email): $email");

    // ==================================================================
    // REGISTRO - ETAPA 2: Validar Username y Enviar Código
    // ==================================================================
    } elseif ($action === 'register_step_2') {
        $username = trim($data['username'] ?? '');
        $email = $_SESSION['temp_register']['email'] ?? '';

        if (empty($email)) throw new Exception('La sesión ha expirado. Vuelve al inicio.');
        if (empty($username)) throw new Exception('El nombre de usuario es requerido.');
        if (strlen($username) < 3) throw new Exception('El usuario debe tener al menos 3 caracteres.');

        // Verificar unicidad de username
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            throw new Exception('El nombre de usuario ya está ocupado.');
        }

        $_SESSION['temp_register']['username'] = $username;

        // Generar Código
        $code = generate_verification_code();
        
        // Eliminar códigos previos para este email
        $del = $pdo->prepare("DELETE FROM verification_codes WHERE email = ?");
        $del->execute([$email]);

        // Guardar nuevo código con expiración (ej: 15 mins)
        $ins = $pdo->prepare("INSERT INTO verification_codes (email, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
        if (!$ins->execute([$email, $code])) {
            throw new Exception("Error al generar código de verificación.");
        }

        // --- MOCKUP ENVÍO DE CORREO ---
        // En producción, aquí usarías mail() o PHPMailer
        logger("CODIGO DE VERIFICACION para $email: $code"); 

        $response = ['success' => true, 'message' => 'Código enviado'];

    // ==================================================================
    // REGISTRO - ETAPA 3: Verificar Código y Crear Cuenta
    // ==================================================================
    } elseif ($action === 'register_final') {
        $inputCode = strtoupper(trim($data['code'] ?? ''));
        $email = $_SESSION['temp_register']['email'] ?? '';
        $password = $_SESSION['temp_register']['password'] ?? '';
        $username = $_SESSION['temp_register']['username'] ?? '';

        if (empty($email) || empty($password) || empty($username)) {
            throw new Exception('Datos de sesión incompletos. Reinicia el registro.');
        }

        // Validar código
        $stmt = $pdo->prepare("SELECT id FROM verification_codes WHERE email = ? AND code = ? AND expires_at > NOW()");
        $stmt->execute([$email, $inputCode]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Código inválido o ha expirado.');
        }

        // --- CREACIÓN REAL DEL USUARIO ---
        $uuid = generate_uuid();
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        // Generar Avatar
        $nameParam = $username;
        $selectedColor = get_random_color();
        $apiUrl = "https://ui-avatars.com/api/?name={$nameParam}&size=256&background={$selectedColor}&color=ffffff&bold=true&length=1";
        
        $uploadDirRel = '/assets/uploads/avatars/'; 
        $uploadDirAbs = __DIR__ . '/..' . $uploadDirRel;
        if (!file_exists($uploadDirAbs)) { mkdir($uploadDirAbs, 0777, true); }
        
        $fileName = $uuid . '.png';
        $destPath = $uploadDirAbs . $fileName;
        $dbPath = 'assets/uploads/avatars/' . $fileName; 

        $imageContent = @file_get_contents($apiUrl);
        if ($imageContent !== false) {
            file_put_contents($destPath, $imageContent);
        } else {
            $dbPath = null; 
        }

        // Insertar en BD
        $insert = $pdo->prepare("INSERT INTO users (uuid, email, username, password, avatar, role) VALUES (?, ?, ?, ?, ?, 'user')");
        if ($insert->execute([$uuid, $email, $username, $hashedPassword, $dbPath])) {
            
            // Auto Login
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_uuid'] = $uuid;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = 'user';
            $_SESSION['user_avatar'] = $dbPath;

            // Limpieza
            $pdo->prepare("DELETE FROM verification_codes WHERE email = ?")->execute([$email]);
            unset($_SESSION['temp_register']);

            $response = ['success' => true, 'message' => 'Bienvenido a Project Aurora'];
            logger("Usuario registrado exitosamente: $username ($email)");
        } else {
            throw new Exception('Error crítico al guardar usuario en BD.');
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
            
            $response = ['success' => true, 'message' => 'Bienvenido'];
            logger("Login exitoso: $email");
        } else {
            throw new Exception('Credenciales incorrectas.');
        }

    } elseif ($action === 'logout') {
        session_destroy();
        $response = ['success' => true, 'message' => 'Sesión cerrada'];
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    logger("Error Auth: " . $e->getMessage());
}

echo json_encode($response);
exit;
?>