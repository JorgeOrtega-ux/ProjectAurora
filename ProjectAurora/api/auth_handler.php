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
        
        // Avatar logic
        $nameParam = explode('@', $email)[0];
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

        // Insertar (La columna role tiene default 'user' en BD, así que no es obligatorio ponerla en el INSERT)
        $insert = $pdo->prepare("INSERT INTO users (uuid, email, password, avatar) VALUES (?, ?, ?, ?)");
        if ($insert->execute([$uuid, $email, $hashedPassword, $dbPath])) {
            
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_uuid'] = $uuid;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_avatar'] = $dbPath;
            $_SESSION['user_role'] = 'user'; // <-- NUEVO: Asignar rol por defecto a la sesión
            
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
            $_SESSION['user_avatar'] = $user['avatar'];
            $_SESSION['user_role'] = $user['role']; // <-- NUEVO: Guardar el rol de la BD
            
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
exit;
?>