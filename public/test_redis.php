<?php
// public/test_redis.php

// 1. Cargamos el bootstrap para obtener $redis, $pdo y la configuración
require_once __DIR__ . '/../includes/bootstrap.php';

// Verificamos si Redis está conectado
try {
    $redis->ping();
} catch (Exception $e) {
    die("Error crítico: No se pudo conectar a Redis. Verifica tu configuración.");
}

// Clave que estamos usando para la configuración
$cacheKey = 'server:config:all';

// Procesar acciones de los botones (antes de pintar el HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'force_load') {
        // Llamamos a Utils para forzar la lectura (y escritura en caché si no existe)
        // Pedimos cualquier clave, el sistema cargará TODO el hash automáticamente
        Utils::getServerConfig($pdo, 'maintenance_mode');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'clear_cache') {
        // Borramos la clave para simular una invalidación
        $redis->del($cacheKey);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspector de Redis - Project Aurora</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; padding: 2rem; background: #f0f2f5; color: #1c1e21; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; }
        h1 { margin-top: 0; font-size: 24px; }
        h2 { margin-top: 0; font-size: 18px; color: #65676b; }
        
        .status-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-weight: bold; font-size: 14px; }
        .status-hit { background: #e6fffa; color: #047857; border: 1px solid #047857; }
        .status-miss { background: #fff5f5; color: #c53030; border: 1px solid #c53030; }

        pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; overflow-x: auto; font-family: monospace; font-size: 13px; }
        
        .btn-group { display: flex; gap: 10px; margin-top: 20px; }
        button { border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: opacity 0.2s; }
        button:hover { opacity: 0.9; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .key-name { font-family: monospace; background: #eee; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛠️ Inspector de Caché (Redis)</h1>
        
        <div class="card">
            <h2>Estado de la clave: <span class="key-name"><?php echo $cacheKey; ?></span></h2>
            
            <?php if ($redis->exists($cacheKey)): ?>
                <div style="margin: 15px 0;">
                    <span class="status-badge status-hit">✅ CACHE HIT (Datos en Redis)</span>
                    <span style="margin-left: 10px; font-size: 14px; color: #666;">
                        TTL: <?php echo $redis->ttl($cacheKey); ?>s
                    </span>
                </div>

                <h3>Contenido RAW (Dump):</h3>
                <pre><?php print_r($redis->hgetall($cacheKey)); ?></pre>

            <?php else: ?>
                <div style="margin: 15px 0;">
                    <span class="status-badge status-miss">🐢 CACHE MISS (Vacío)</span>
                </div>
                <p>La clave no existe en Redis actualmente. El sistema consultará a MySQL en la próxima petición.</p>
            <?php endif; ?>

            <div class="btn-group">
                <form method="POST">
                    <input type="hidden" name="action" value="force_load">
                    <button type="submit" class="btn-primary">🔄 Simular Visita (Cargar Caché)</button>
                </form>

                <form method="POST">
                    <input type="hidden" name="action" value="clear_cache">
                    <button type="submit" class="btn-danger">🗑️ Borrar Clave (Invalidar)</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2>¿Cómo probar?</h2>
            <ol style="line-height: 1.6; color: #444;">
                <li>Si ves <strong>CACHE MISS</strong>, pulsa "Simular Visita". Debería cambiar a <strong>HIT</strong> y mostrar los datos.</li>
                <li>Si ves <strong>CACHE HIT</strong>, pulsa "Borrar Clave". Debería volver a <strong>MISS</strong>.</li>
                <li>Ve a tu Panel de Admin, cambia una configuración y guarda. Vuelve aquí y recarga: deberías ver los datos nuevos (porque el Admin borró la caché vieja y al entrar aquí se regeneró).</li>
            </ol>
        </div>
    </div>
</body>
</html>