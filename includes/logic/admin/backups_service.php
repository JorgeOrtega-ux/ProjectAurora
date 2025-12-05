<?php
// includes/logic/admin/backups_service.php

function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function getDbBinary($binaryName) {
    $customPath = $_ENV['DB_BIN_PATH'] ?? getenv('DB_BIN_PATH');
    if (!empty($customPath)) {
        $customPath = rtrim(str_replace('\\', '/', $customPath), '/');
        $binary = $customPath . '/' . $binaryName;
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            if (!str_ends_with(strtolower($binary), '.exe')) {
                $binary .= '.exe';
            }
        }
        if (!file_exists($binary)) {
            throw new Exception("El archivo no existe: $binary");
        }
        return '"' . $binary . '"'; 
    }
    return $binaryName;
}

function list_backups($backupDir) {
    $files = array_diff(scandir($backupDir), ['.', '..']); 
    $backups = [];
    foreach ($files as $file) { 
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') { 
            $path = $backupDir . '/' . $file; 
            $backups[] = [
                'filename' => $file, 
                'size' => formatSize(filesize($path)), 
                'created_at' => date("Y-m-d H:i:s", filemtime($path)), 
                'timestamp' => filemtime($path)
            ]; 
        } 
    }
    usort($backups, function($a, $b) { return $b['timestamp'] - $a['timestamp']; });
    return ['success' => true, 'backups' => $backups];
}

function create_backup($backupDir) {
    if (!is_writable($backupDir)) throw new Exception("Permiso denegado en carpeta 'backups'.");
    
    $host = $_ENV['DB_HOST'] ?? 'localhost'; 
    $db = $_ENV['DB_NAME'] ?? 'project_aurora_db'; 
    $user = $_ENV['DB_USER'] ?? 'root'; 
    $pass = $_ENV['DB_PASS'] ?? '';
    
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql'; 
    $filepath = $backupDir . '/' . $filename;

    $tempCreds = tempnam(sys_get_temp_dir(), 'mycnf');
    if (!$tempCreds) throw new Exception("Error interno: No se pudo crear archivo temporal de seguridad.");
    
    chmod($tempCreds, 0600); 

    $confContent = "[client]\nuser=\"$user\"\npassword=\"$pass\"\nhost=\"$host\"\n";
    if (file_put_contents($tempCreds, $confContent) === false) {
        @unlink($tempCreds);
        throw new Exception("Error al escribir configuración segura.");
    }

    try { 
        $mysqldump = getDbBinary('mysqldump'); 
    } catch (Exception $e) { 
        @unlink($tempCreds);
        throw $e; 
    }

    $command = "$mysqldump --defaults-extra-file=\"$tempCreds\" --opt $db > \"$filepath\" 2>&1";
    
    exec($command, $output, $returnVar);

    @unlink($tempCreds);

    if ($returnVar === 0 && file_exists($filepath) && filesize($filepath) > 0) {
        return ['success' => true, 'message' => translation('admin.backups.created_success')];
    } else { 
        if (file_exists($filepath)) @unlink($filepath); 
        throw new Exception("Error al generar respaldo (Código $returnVar). Detalles en log."); 
    }
}

function delete_backup($backupDir, $filename) {
    if (empty($filename) || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) throw new Exception(translation('global.action_invalid'));
    
    $filepath = $backupDir . '/' . $filename;
    if (file_exists($filepath)) { 
        if (@unlink($filepath)) return ['success' => true, 'message' => translation('admin.backups.delete_success')]; 
        else throw new Exception("Error al eliminar archivo."); 
    } else {
        throw new Exception("Archivo no encontrado.");
    }
}

function restore_backup($pdo, $backupDir, $filename) {
    if (empty($filename) || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        throw new Exception(translation('global.action_invalid'));
    }
    
    $filepath = $backupDir . '/' . $filename;
    if (!file_exists($filepath)) throw new Exception("Archivo no encontrado.");
    
    $host = $_ENV['DB_HOST'] ?? 'localhost'; 
    $db = $_ENV['DB_NAME'] ?? 'project_aurora_db'; 
    $user = $_ENV['DB_USER'] ?? 'root'; 
    $pass = $_ENV['DB_PASS'] ?? '';

    $tempCreds = tempnam(sys_get_temp_dir(), 'mycnf_restore');
    if (!$tempCreds) throw new Exception("Error al iniciar restauración segura.");
    chmod($tempCreds, 0600);
    
    $confContent = "[client]\nuser=\"$user\"\npassword=\"$pass\"\nhost=\"$host\"\n";
    file_put_contents($tempCreds, $confContent);

    try { 
        $mysql = getDbBinary('mysql'); 
    } catch (Exception $e) { 
        @unlink($tempCreds);
        throw $e; 
    }

    $command = "$mysql --defaults-extra-file=\"$tempCreds\" $db < \"$filepath\" 2>&1";
    
    exec($command, $output, $returnVar);
    
    @unlink($tempCreds);

    if ($returnVar === 0) { 
        $pdo->exec("DELETE FROM user_sessions"); 
        send_live_notification('global', 'force_logout', ['reason' => 'system_restore']); 
        return ['success' => true, 'message' => translation('admin.backups.restore_success')]; 
    } else { 
        throw new Exception("Error restaurando base de datos. Verifique logs."); 
    }
}
?>