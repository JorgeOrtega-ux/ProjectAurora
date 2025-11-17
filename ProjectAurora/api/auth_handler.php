<?php
// --- CONFIGURACIÓN DE LOGS ROBUSTA ---
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/php_error.log';

// Crear directorio si no existe
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Asegurar que se reporten todos los errores pero NO se impriman en el HTML
error_reporting(E_ALL);
ini_set('ignore_repeated_errors', TRUE);
ini_set('display_errors', FALSE); 
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

function logger($message) {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    // Usamos FILE_APPEND para no borrar logs anteriores
    file_put_contents($logFile, "[$date] [CUSTOM] $message" . PHP_EOL, FILE_APPEND);
}

// Test de log
logger("Auth handler invocado correctamente.");
// --- FIN CONFIGURACIÓN ---

session_start();
header('Content-Type: application/json'); // Forzar cabecera JSON

// Incluir conexión
require_once '../config/database.php';

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

function generate_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

$response = ['success' => false, 'message' => 'Acción no válida'];

try {
    if ($action === 'register') {
        $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            throw new Exception('Por favor completa todos los campos.');
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            throw new Exception('El correo ya está registrado.');
        }

        $uuid = generate_uuid();
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $insert = $pdo->prepare("INSERT INTO users (uuid, email, password) VALUES (?, ?, ?)");
        if ($insert->execute([$uuid, $email, $hashedPassword])) {
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_uuid'] = $uuid;
            $_SESSION['user_email'] = $email;
            
            $response = ['success' => true, 'message' => 'Registro exitoso'];
            logger("Usuario registrado: $email");
        } else {
            throw new Exception('Error al registrar el usuario en BD.');
        }

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
    logger("Error: " . $e->getMessage());
}

echo json_encode($response);
exit; // Importante salir para evitar output extra