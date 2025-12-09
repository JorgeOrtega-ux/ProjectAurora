<?php
session_start();

// CONFIGURACIÓN DE BASE DE DATOS
$host = 'localhost';
$db   = 'project_aurora_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

$basePath = '/ProjectAurora/'; // Aseguramos que basePath esté disponible

// --- FUNCIONES AUXILIARES ---

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

// Función para generar un código numérico simple (6 dígitos)
function generate_verification_code() {
    return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// --- LÓGICA DE AUTENTICACIÓN (POST) ---

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- ETAPA 1: VALIDAR CORREO Y CONTRASEÑA ---
    if ($_POST['action'] === 'register_step_1') {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if ($email && $password) {
            // Verificar si el correo ya existe en tabla users
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = "El correo electrónico ya está registrado.";
            } else {
                // Guardar en sesión temporalmente
                $_SESSION['temp_register'] = [
                    'email' => $email,
                    'password' => $password
                ];
                // Redirigir a Etapa 2
                header("Location: " . $basePath . "register/aditional-data");
                exit;
            }
        } else {
            $error = "Correo y contraseña requeridos.";
        }
    }

    // --- ETAPA 2: VALIDAR USUARIO, GENERAR CÓDIGO Y GUARDAR EN BD ---
    if ($_POST['action'] === 'register_step_2') {
        $username = trim($_POST['username']);
        
        // Recuperar datos de la etapa 1
        if (!isset($_SESSION['temp_register'])) {
            $error = "Sesión expirada. Vuelve a empezar.";
            header("Location: " . $basePath . "register"); // Fallback
            exit;
        }

        $email = $_SESSION['temp_register']['email'];
        $password = $_SESSION['temp_register']['password'];

        if ($username) {
            // Verificar existencia de usuario
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);

            if ($stmt->rowCount() > 0) {
                $error = "El nombre de usuario ya está en uso.";
            } else {
                // Generar Código y Payload
                $code = generate_verification_code();
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                $payload = json_encode([
                    'username' => $username,
                    'email'    => $email,
                    'password' => $passwordHash
                ]);

                // Insertar en verification_codes
                $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                $stmt = $pdo->prepare("INSERT INTO verification_codes (identifier, code_type, code, payload, expires_at) VALUES (?, ?, ?, ?, ?)");
                
                if ($stmt->execute([$email, 'account_activation', $code, $payload, $expiresAt])) {
                    
                    $_SESSION['pending_verification_email'] = $email;
                    unset($_SESSION['temp_register']);

                    header("Location: " . $basePath . "verification-account");
                    exit;

                } else {
                    $error = "Error al generar código de verificación.";
                }
            }
        } else {
            $error = "Nombre de usuario requerido.";
        }
    }

    // --- ETAPA 3: VERIFICAR CÓDIGO Y CREAR CUENTA ---
    if ($_POST['action'] === 'verify_code') {
        $code = trim($_POST['code']);
        $emailIdentifier = $_SESSION['pending_verification_email'] ?? null;

        if ($code && $emailIdentifier) {
            // Buscar el código en la BD
            $stmt = $pdo->prepare("SELECT * FROM verification_codes WHERE identifier = ? AND code = ? AND code_type = 'account_activation' AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
            $stmt->execute([$emailIdentifier, $code]);
            $verificationRow = $stmt->fetch();

            if ($verificationRow) {
                // ¡Código Válido! Procedemos a crear el usuario
                $payload = json_decode($verificationRow['payload'], true);
                
                $finalUsername = $payload['username'];
                $finalEmail = $payload['email'];
                $finalPassHash = $payload['password'];
                $uuid = generate_uuid();

                // Insertar Usuario Real
                $insertUser = $pdo->prepare("INSERT INTO users (username, email, password, uuid) VALUES (?, ?, ?, ?)");
                
                if ($insertUser->execute([$finalUsername, $finalEmail, $finalPassHash, $uuid])) {
                    
                    // IMPORTANTE: Capturar ID INMEDIATAMENTE
                    $newUserId = $pdo->lastInsertId();

                    // Borrar el código usado
                    $delStmt = $pdo->prepare("DELETE FROM verification_codes WHERE id = ?");
                    $delStmt->execute([$verificationRow['id']]);

                    // --- GENERAR AVATAR (Con supresión de errores para no romper headers) ---
                    try {
                        $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($finalUsername) . "&background=random&color=fff&size=128";
                        $imageData = @file_get_contents($avatarUrl);
                        if ($imageData) {
                            $targetDir = __DIR__ . '/../public/assets/uploads/profile_pictures/';
                            if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
                            @file_put_contents($targetDir . $uuid . '.png', $imageData);
                        }
                    } catch (Exception $e) {}

                    // --- AUTO-LOGIN ---
                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['username'] = $finalUsername;
                    $_SESSION['uuid'] = $uuid; 
                    $_SESSION['role'] = 'user';

                    unset($_SESSION['pending_verification_email']);

                    logUserAccess($pdo, $newUserId);

                    // IMPORTANTE: Forzar guardado de sesión antes de redirigir
                    session_write_close();

                    header("Location: " . $basePath);
                    exit;

                } else {
                    $error = "Error crítico al crear la cuenta.";
                }

            } else {
                $error = "Código inválido o expirado.";
            }
        } else {
            $error = "Código requerido.";
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
            
            // También recomendable aquí, aunque menos crítico si no hay inserciones previas
            session_write_close();
            
            header("Location: " . $basePath);
            exit;
        } else {
            $error = "Credenciales incorrectas.";
        }
    }
}

// 3. LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $basePath . "login");
    exit;
}
?>