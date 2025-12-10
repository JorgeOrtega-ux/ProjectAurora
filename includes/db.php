<?php
// includes/db.php
// UBICACIÓN: Raíz del proyecto /includes/

// 1. Iniciar sesión siempre para tener acceso a $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Configurar Zona Horaria de PHP
date_default_timezone_set('America/Mexico_City'); 

// --- CARGADOR DE .ENV ---
// Busca el archivo .env en la raíz del proyecto para cargar las variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios (#)
        if (strpos(trim($line), '#') === 0) continue;
        
        // Procesar líneas formato CLAVE=VALOR
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Cargar en variables de entorno y $_SERVER si no existen
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// 3. Generación del Token CSRF (Seguridad para formularios)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 4. CONFIGURACIÓN DE BASE DE DATOS (SEGURIDAD ESTRICTA)
// Obtenemos las credenciales exclusivamente de las variables de entorno.
// Ya no hay valores por defecto ("fallbacks"), por lo que este archivo es seguro de compartir.

$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$charset = 'utf8mb4';

// Validación: Si faltan las variables críticas, detenemos la ejecución.
if (!$host || !$db || !$user) {
    // Mensaje técnico para el desarrollador (en producción deberías loguearlo en lugar de mostrarlo)
    die("Error crítico de configuración: No se han encontrado las variables de entorno de la base de datos. Verifica tu archivo .env.");
}

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Sincronización opcional de zona horaria MySQL
    try {
        $offset = date('P'); 
        $pdo->exec("SET time_zone = '$offset';");
    } catch (Exception $e) {
        // Continuamos aunque falle la zona horaria
    }

} catch (\PDOException $e) {
    // SEGURIDAD: Nunca mostrar el error real ($e->getMessage()) al usuario final en producción.
    // Esto evita filtrar información sobre la estructura de tu base de datos o usuarios.
    die("Error de conexión: No se pudo establecer comunicación con la base de datos.");
}

// Ruta base del proyecto (útil para redirecciones y assets)
$basePath = '/ProjectAurora/'; 
?>