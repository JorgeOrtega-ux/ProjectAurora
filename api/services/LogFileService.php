<?php
// api/services/LogFileService.php

class LogFileService {
    private $logsBaseDir;

    public function __construct() {
        // Ruta absoluta a la carpeta logs. Ajusta si tu estructura es diferente.
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
                    
                    $filesList[] = [
                        'id' => md5($filePath),
                        'filename' => $filename,
                        'path' => $dir . '/' . $filename, // Ruta relativa segura
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

    public function getFilesContent($paths = []) {
        $results = [];

        foreach ($paths as $relativePath) {
            // 1. Limpieza básica
            $cleanPath = str_replace('..', '', $relativePath);
            $fullPath = $this->logsBaseDir . '/' . $cleanPath;
            
            // 2. Validación estricta de ruta (Sandbox)
            $realFullPath = realpath($fullPath);
            // Verificar que la ruta resuelta esté dentro de la carpeta logs
            if ($realFullPath === false || strpos($realFullPath, $this->logsBaseDir) !== 0) {
                $results[] = [
                    'path' => $relativePath,
                    'filename' => basename($relativePath),
                    'error' => 'Acceso denegado o archivo no encontrado.',
                    'content' => ''
                ];
                continue;
            }

            // 3. Lectura segura
            if (file_exists($realFullPath) && is_file($realFullPath)) {
                $size = filesize($realFullPath);
                $isTruncated = false;
                $content = '';

                // Límite de 1MB para evitar colgar el navegador
                if ($size > 1048576) { 
                    $content = $this->tailCustom($realFullPath, 50000); // ~50KB del final
                    $isTruncated = true;
                } else {
                    $content = file_get_contents($realFullPath);
                }

                $results[] = [
                    'path' => $relativePath,
                    'filename' => basename($relativePath),
                    'content' => $content,
                    'is_truncated' => $isTruncated,
                    'size' => $this->formatSize($size)
                ];
            }
        }

        return ['success' => true, 'files' => $results];
    }

    private function tailCustom($filepath, $bytes = 50000) {
        $f = fopen($filepath, "rb");
        fseek($f, -min(filesize($filepath), $bytes), SEEK_END);
        $data = fread($f, $bytes);
        fclose($f);
        return "... (Archivo grande, mostrando los últimos bytes) ...\n" . $data;
    }

    private function formatSize($bytes) {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' bytes';
    }
}
?>