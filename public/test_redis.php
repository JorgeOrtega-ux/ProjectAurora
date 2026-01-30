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

// Clave específica que usa la app (para monitoreo rápido)
$configKey = 'server:config:all';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'force_load') {
        // Forzar recarga de configuración (App Logic)
        Utils::getServerConfig($pdo, 'maintenance_mode');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'clear_config_key') {
        // Borrar solo la clave de configuración
        $redis->del($configKey);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'flush_db') {
        // [NUEVO] Borrar TODAS las claves de la base de datos actual
        $redis->flushdb();
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
    <title>Inspector Avanzado Redis - Project Aurora</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; padding: 2rem; background: #f0f2f5; color: #1c1e21; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; }
        h1 { margin-top: 0; font-size: 24px; display: flex; align-items: center; gap: 10px; }
        h2 { margin-top: 0; font-size: 18px; color: #444; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
        
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-weight: bold; font-size: 12px; text-transform: uppercase; }
        .type-string { background: #e3f2fd; color: #1565c0; }
        .type-hash { background: #f3e5f5; color: #7b1fa2; }
        .type-set { background: #e8f5e9; color: #2e7d32; }
        .type-none { background: #eee; color: #666; }

        pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; overflow-x: auto; font-family: monospace; font-size: 13px; margin-top: 5px; max-height: 300px; }
        
        .btn-group { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        button { border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: opacity 0.2s; }
        button:hover { opacity: 0.9; }
        .btn-primary { background: #007bff; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-danger { background: #dc3545; color: white; }
        
        .key-item { border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 10px; background: #fafafa; }
        .key-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
        .key-name { font-family: monospace; font-weight: bold; color: #333; font-size: 14px; }
        .key-ttl { font-size: 12px; color: #666; background: #fff; padding: 2px 6px; border-radius: 4px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Monitor de Redis en Tiempo Real</h1>
        
        <div class="card">
            <h2>Configuración del Sistema (App Logic)</h2>
            <p>Estado de la clave principal: <span class="key-name" style="background:#eee; padding:2px 6px; border-radius:4px;"><?php echo $configKey; ?></span></p>
            
            <?php if ($redis->exists($configKey)): ?>
                <div style="margin: 10px 0; color: #2e7d32; font-weight: bold;">✅ EN CACHÉ (Ready)</div>
            <?php else: ?>
                <div style="margin: 10px 0; color: #c62828; font-weight: bold;">⚠️ NO EN CACHÉ (Miss)</div>
            <?php endif; ?>

            <div class="btn-group">
                <form method="POST">
                    <input type="hidden" name="action" value="force_load">
                    <button type="submit" class="btn-primary">🔄 Simular Carga (App)</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="action" value="clear_config_key">
                    <button type="submit" class="btn-warning">🔸 Invalidar Config</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:15px;">
                <h2 style="border:none; margin:0; padding:0;">💾 Explorador de Memoria (Todas las Claves)</h2>
                <form method="POST" onsubmit="return confirm('¿Estás seguro? Esto borrará TODA la caché de Redis.');">
                    <input type="hidden" name="action" value="flush_db">
                    <button type="submit" class="btn-danger" style="padding: 6px 12px; font-size: 13px;">🗑️ FLUSH DB</button>
                </form>
            </div>

            <?php
            // Obtener todas las claves
            $allKeys = $redis->keys('*');
            
            if (empty($allKeys)) {
                echo "<div style='text-align:center; padding:30px; color:#999;'>📭 La base de datos Redis está vacía.</div>";
            } else {
                echo "<p style='margin-bottom:15px;'>Total de claves: <strong>" . count($allKeys) . "</strong></p>";
                
                foreach ($allKeys as $key) {
                    // Obtener tipo y TTL
                    $typeObj = $redis->type($key);
                    $type = (string)$typeObj; // Predis devuelve objeto a veces, cast a string
                    $ttl = $redis->ttl($key);
                    
                    // Clase visual según tipo
                    $badgeClass = 'type-' . $type;
                    
                    echo "<div class='key-item'>";
                    echo "<div class='key-header'>";
                    echo "<div>";
                    echo "<span class='status-badge $badgeClass'>$type</span> ";
                    echo "<span class='key-name'>$key</span>";
                    echo "</div>";
                    echo "<span class='key-ttl'>" . ($ttl > 0 ? "Expira en {$ttl}s" : ($ttl == -1 ? 'Persistente' : 'Expirado')) . "</span>";
                    echo "</div>";
                    
                    // Mostrar Contenido
                    echo "<pre>";
                    try {
                        if ($type === 'string') {
                            $val = $redis->get($key);
                            // Intentar decodificar JSON para mostrarlo bonito
                            $json = json_decode($val);
                            if ($json && $val != $json) {
                                echo htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            } else {
                                echo htmlspecialchars($val);
                            }
                        } elseif ($type === 'hash') {
                            print_r($redis->hgetall($key));
                        } elseif ($type === 'list') {
                            print_r($redis->lrange($key, 0, -1));
                        } elseif ($type === 'set') {
                            print_r($redis->smembers($key));
                        } elseif ($type === 'zset') {
                            print_r($redis->zrange($key, 0, -1));
                        } else {
                            echo "(Tipo de dato complejo no visualizable)";
                        }
                    } catch (Exception $e) {
                        echo "Error leyendo clave: " . $e->getMessage();
                    }
                    echo "</pre>";
                    echo "</div>";
                }
            }
            ?>
        </div>
    </div>
</body>
</html>