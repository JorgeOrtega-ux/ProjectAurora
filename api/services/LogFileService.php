<?php
// api/services/LogFileService.php

class LogFileService {
    private $logsBaseDir;

    public function __construct() {
        // Ruta absoluta a la carpeta logs
        $this->logsBaseDir = realpath(__DIR__ . '/../../logs/');
    }

    public function getAllLogFiles() {
        $directories = ['app', 'database', 'security'];
        $filesList = [];

        foreach ($directories as $dir) {
            $path = $this->logsBaseDir . '/' . $dir;
            if (is_dir($path)) {
                $files = glob($path . '/*.log');
                foreach ($files as $filePath) {
                    $filename = basename($filePath);
                    $size = filesize($filePath);
                    $mtime = filemtime($filePath);
                    
                    // Identificamos el "tipo" por la carpeta
                    $filesList[] = [
                        'id' => md5($filePath), // ID único para el frontend
                        'filename' => $filename,
                        'path' => $dir . '/' . $filename, // Ruta relativa para API
                        'category' => $dir,
                        'size' => $this->formatSize($size),
                        'size_bytes' => $size,
                        'modified_at' => date('d/m/Y H:i', $mtime),
                        'timestamp' => $mtime
                    ];
                }
            }
        }

        // Ordenar por fecha (más reciente primero)
        usort($filesList, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return ['success' => true, 'files' => $filesList];
    }

    public function deleteLogFiles($paths = []) {
        $deleted = 0;
        foreach ($paths as $relativePath) {
            // Seguridad: Evitar Path Traversal
            $cleanPath = str_replace('..', '', $relativePath);
            $fullPath = $this->logsBaseDir . '/' . $cleanPath;

            if (file_exists($fullPath) && is_file($fullPath)) {
                if (unlink($fullPath)) {
                    $deleted++;
                }
            }
        }
        
        return [
            'success' => true, 
            'message' => "Se eliminaron $deleted archivos correctamente."
        ];
    }

    private function formatSize($bytes) {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' bytes';
    }
}
?>