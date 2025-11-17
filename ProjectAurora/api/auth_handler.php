<?php
// --- INICIO CONFIGURACIÓN DE LOGS ---
// Definir ruta de logs (sube un nivel desde 'api' y entra a 'logs')
$logFile = __DIR__ . '/../logs/php_error.log';

// Asegurar que se reporten todos los errores
error_reporting(E_ALL);
ini_set('ignore_repeated_errors', TRUE);
ini_set('display_errors', FALSE); // IMPORTANTE: No mostrar errores en el HTML (response)
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

// Función para loguear mensajes personalizados (útil para depurar)
function logger($message) {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] [CUSTOM] $message" . PHP_EOL, FILE_APPEND);
}

// Probar que el log funciona
logger("Se recibió una petición en auth_handler.php");
// --- FIN CONFIGURACIÓN DE LOGS ---

session_start();
header('Content-Type: application/json');

// Incluir conexión
require_once '../config/database.php';

// Leer entrada JSON
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// Función para generar UUID v4
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
        $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $password = $data['password'];

        if (!$email || !$password) {
            throw new Exception('Por favor completa todos los campos.');
        }

        // Verificar si existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            throw new Exception('El correo ya está registrado.');
        }

        // Insertar usuario
        $uuid = generate_uuid();
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $insert = $pdo->prepare("INSERT INTO users (uuid, email, password) VALUES (?, ?, ?)");
        if ($insert->execute([$uuid, $email, $hashedPassword])) {
            // Iniciar sesión automáticamente
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_uuid'] = $uuid;
            $_SESSION['user_email'] = $email;
            
            $response = ['success' => true, 'message' => 'Registro exitoso'];
        } else {
            throw new Exception('Error al registrar el usuario.');
        }

    } elseif ($action === 'login') {
        $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $password = $data['password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_uuid'] = $user['uuid'];
            $_SESSION['user_email'] = $user['email'];
            
            $response = ['success' => true, 'message' => 'Bienvenido'];
        } else {
            throw new Exception('Credenciales incorrectas.');
        }

    } elseif ($action === 'logout') {
        session_destroy();
        $response = ['success' => true, 'message' => 'Sesión cerrada'];
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>