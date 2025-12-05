<?php
// includes/logic/admin/system_service.php

function update_server_config($pdo, $key, $value) {
    $allowedKeys = [
        'maintenance_mode', 'allow_registrations', 'min_password_length', 'max_password_length', 
        'min_username_length', 'max_username_length', 'max_email_length', 'max_login_attempts', 
        'lockout_time_minutes', 'code_resend_cooldown', 'username_cooldown', 'email_cooldown', 
        'profile_picture_max_size', 'allowed_email_domains',
        'chat_msg_limit', 'chat_time_window' 
    ];
    
    if (!in_array($key, $allowedKeys)) throw new Exception(translation('global.action_invalid'));
    
    if ($key === 'maintenance_mode') {
        $intVal = (int)$value; 
        $sql = "UPDATE server_config SET maintenance_mode = ? WHERE id = 1";
        $pdo->prepare($sql)->execute([$intVal === 1 ? 1 : 0]);
        
        if ($intVal === 1) $pdo->exec("UPDATE server_config SET allow_registrations = 0 WHERE id = 1");
        send_live_notification('global', 'system_status_update', ['maintenance' => ($intVal === 1)]);
    
    } elseif ($key === 'allow_registrations') {
        $intVal = (int)$value; 
        $curr = getServerConfig($pdo);
        if ($intVal === 1 && (int)$curr['maintenance_mode'] === 1) throw new Exception("No puedes activar registros durante el mantenimiento.");
        $pdo->prepare("UPDATE server_config SET allow_registrations = ? WHERE id = 1")->execute([$intVal === 1 ? 1 : 0]);
    
    } else {
        $sql = "UPDATE server_config SET $key = ? WHERE id = 1";
        if ($key === 'allowed_email_domains') $finalVal = (!empty($value) && is_array($value)) ? json_encode($value) : NULL; 
        else $finalVal = (int)$value;
        $pdo->prepare($sql)->execute([$finalVal]);
    }
    
    return ['success' => true, 'message' => translation('global.save_status')];
}

function get_redis_status() {
    global $redis;
    $status = ['connected' => false, 'msg' => '', 'keys' => []];

    if (!class_exists('Redis')) {
        $status['msg'] = "Extensión 'Redis' no instalada en PHP.";
    } elseif (!isset($redis) || $redis === null) {
        $status['msg'] = "Variable \$redis no inicializada (Revisar database.php).";
    } else {
        try {
            $pong = $redis->ping();
            if ($pong) {
                $status['connected'] = true;
                $status['msg'] = "Conectado";
                
                $keys = $redis->keys('chat:buffer:*');
                $resultKeys = [];
                foreach ($keys as $key) {
                    $len = $redis->lLen($key);
                    $content = $redis->lRange($key, 0, 4); 
                    $resultKeys[] = ['key' => $key, 'count' => $len, 'preview' => $content];
                }
                $status['keys'] = $resultKeys;
            }
        } catch (Exception $e) {
            $status['msg'] = "Excepción de Conexión: " . $e->getMessage();
        }
    }
    return array_merge(['success' => true], $status);
}

function clear_redis() {
    global $redis;
    if (!isset($redis) || !$redis) throw new Exception("Redis no disponible.");
    
    $keys = $redis->keys('chat:buffer:*');
    $count = 0;
    foreach ($keys as $key) {
        $redis->del($key);
        $count++;
    }
    return ['success' => true, 'count' => $count];
}

function test_bridge() {
    $host = '127.0.0.1';
    $port = 8081;
    $timeout = 5;
    
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    
    if (!$fp) {
        throw new Exception("No se pudo conectar a $host:$port. Código: $errno - $errstr");
    } else {
        $bridgeSecret = $_ENV['BRIDGE_SECRET'] ?? getenv('BRIDGE_SECRET');

        if (empty($bridgeSecret) || $bridgeSecret === 'default_secret') {
            fclose($fp);
            throw new Exception("Error de Seguridad: BRIDGE_SECRET no está configurado correctamente en el servidor.");
        }

        $testPayload = json_encode([
            'auth_token' => $bridgeSecret,
            'target_id' => 'global',
            'type' => 'admin_notification', 
            'payload' => ['message' => '🔔 TEST DE PUENTE EXITOSO (Desde Panel Admin) 🔔']
        ]);
        
        fwrite($fp, $testPayload);
        fclose($fp);
        
        return [
            'success' => true, 
            'message' => 'El socket aceptó la conexión y el payload fue enviado con autenticación.'
        ];
    }
}
?>