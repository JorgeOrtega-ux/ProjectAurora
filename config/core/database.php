<?php
// config/core/database.php

// 1. Función para cargar variables del archivo .env
$envFile = __DIR__ . '/../../.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) continue;
        
        // Separar nombre y valor
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Eliminar comillas si existen
            $value = trim($value, '"\'');
            
            // Guardar en variables de entorno y superglobales
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// 2. Obtener credenciales MySQL
$host = $_ENV['DB_HOST'] ?? 'localhost';
$db   = $_ENV['DB_NAME'] ?? 'project_aurora_db';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
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
    // --- PLAN A: MANEJO HÍBRIDO DE ERRORES (API vs NAVEGADOR) ---

    // Intentar cargar traducciones para mensaje amigable
    if (!class_exists('I18n')) {
        $i18nPath = __DIR__ . '/../../includes/logic/i18n_server.php';
        if (file_exists($i18nPath)) {
            require_once $i18nPath;
            // Intentar cargar helper para idioma, fallback a es-latam
            $utilPath = __DIR__ . '/../helpers/utilities.php';
            if (file_exists($utilPath)) require_once $utilPath;
            
            $lang = (function_exists('detect_browser_language')) ? detect_browser_language() : 'es-latam';
            I18n::load($lang);
        }
    }
    
    $errorMsg = function_exists('translation') ? translation('global.error_connection') : 'Error de conexión a la base de datos';
    
    // Lógica de detección de contexto
    $isApi = false;
    
    // a) Por URL (si la ruta contiene /api/)
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) $isApi = true;
    
    // b) Por cabecera Accept (si espera JSON)
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) $isApi = true;
    
    // c) Por cabecera AJAX estándar
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') $isApi = true;
    
    // d) Por parámetros específicos de tus loaders
    if (isset($_GET['ajax_partial'])) $isApi = true;

    if ($isApi) {
        // --- CASO 1: RESPUESTA PARA API (JSON) ---
        // Mantenemos el comportamiento original para que el frontend muestre la alerta
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    } else {
        // --- CASO 2: RESPUESTA PARA NAVEGADOR (HTML) ---
        // Mostramos una página bonita en lugar de JSON crudo
        if (!headers_sent()) {
            http_response_code(500);
        }
        $basePath = '/ProjectAurora/'; // Path base para cargar CSS
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicio No Disponible - Project Aurora</title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/styles.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/componnents.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <style>
        body {
            background-color: #f5f5fa;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            font-family: "Roboto Condensed", sans-serif;
        }
        .error-card {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            text-align: center;
            max-width: 420px;
            width: 90%;
            border: 1px solid #e0e0e0;
            animation: fadeIn 0.3s ease-out;
        }
        .error-icon {
            font-size: 64px;
            color: #d32f2f;
            margin-bottom: 16px;
            background: #ffebee;
            padding: 16px;
            border-radius: 50%;
        }
        .error-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #1a1a1a;
        }
        .error-desc {
            color: #666;
            font-size: 15px;
            line-height: 1.5;
            margin-bottom: 32px;
        }
        .retry-btn {
            background-color: #000;
            color: #fff;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .retry-btn:hover {
            background-color: #333;
            transform: translateY(-1px);
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="error-card">
        <span class="material-symbols-rounded error-icon">dns</span>
        <h1 class="error-title"><?php echo htmlspecialchars($errorMsg); ?></h1>
        <p class="error-desc">
            No hemos podido establecer conexión con los servicios centrales. Es posible que el servidor de base de datos esté apagado o en mantenimiento.
        </p>
        <a href="javascript:location.reload()" class="retry-btn">
            <span class="material-symbols-rounded">refresh</span>
            Reintentar conexión
        </a>
    </div>
</body>
</html>
        <?php
        exit;
    }
}

// 3. Configuración Redis
$redisHost = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
$redisPort = $_ENV['REDIS_PORT'] ?? 6379;
$redisPass = $_ENV['REDIS_PASS'] ?? null;

try {
    $redis = new Redis();
    $redis->connect($redisHost, $redisPort);
    if ($redisPass) {
        $redis->auth($redisPass);
    }
} catch (Exception $e) {
    // Fallback o log error
    error_log("Redis Connection Error: " . $e->getMessage());
}
?>