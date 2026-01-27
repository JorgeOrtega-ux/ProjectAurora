<?php
// api/services/LogFileService.php

class LogFileService {
    /**
     * Lista de directorios base permitidos para lectura.
     * Esto actúa como una "Caja de Arena" (Sandbox) de seguridad.
     */
    private $allowedDirectories = [];

    public function __construct() {
        // Configuramos las rutas absolutas permitidas.
        // Usamos realpath para resolver ../ y asegurar rutas canónicas.
        $this->allowedDirectories = [
            'logs'    => realpath(__DIR__ . '/../../logs'),
            'backups' => realpath(__DIR__ . '/../../storage/backups')
        ];
    }

    /**
     * Obtiene la lista de archivos de LOG (solo de la carpeta logs/)
     */
    public function getAllLogFiles() {
        // Usamos específicamente el directorio de logs para este listado
        $baseDir = $this->allowedDirectories['logs'] ?? null;
        
        if (!$baseDir) {
            return ['success' => false, 'message' => 'Directorio de logs no configurado o inaccesible.'];
        }

        $directories = ['app', 'database', 'security'];
        $filesList = [];

        foreach ($directories as $dir) {
            $path = $baseDir . '/' . $dir;
            if (is_dir($path)) {
                $files = glob($path . '/*.log');
                foreach ($files as $filePath) {
                    $filename = basename($filePath);
                    $size = filesize($filePath);
                    $mtime = filemtime($filePath);
                    
                    $filesList[] = [
                        'id' => md5($filePath),
                        'filename' => $filename,
                        'path' => $dir . '/' . $filename, // Ruta relativa para la API
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

    /**
     * Elimina archivos de LOG (Restringido a la carpeta logs/ por seguridad)
     */
    public function deleteLogFiles($paths = []) {
        $baseDir = $this->allowedDirectories['logs'] ?? null;
        if (!$baseDir) return ['success' => false, 'message' => 'Error de configuración de directorios.'];

        $deleted = 0;
        foreach ($paths as $relativePath) {
            // Seguridad: Evitar Path Traversal
            $cleanPath = str_replace('..', '', $relativePath);
            $fullPath = $baseDir . '/' . $cleanPath;

            // Validación estricta: Asegurar que sigue dentro de logs/
            $realFullPath = realpath($fullPath);
            if ($realFullPath && strpos($realFullPath, $baseDir) === 0 && is_file($realFullPath)) {
                if (unlink($realFullPath)) {
                    $deleted++;
                }
            }
        }
        
        return [
            'success' => true, 
            'message' => "Se eliminaron $deleted archivos correctamente."
        ];
    }

    /**
     * Lee el contenido de un archivo (Busca en Logs Y Backups)
     * Esta es la función clave corregida para el visor.
     */
    public function getFilesContent($paths = []) {
        $results = [];

        foreach ($paths as $relativePath) {
            $content = '';
            $error = null;
            $foundPath = null;
            $size = 0;
            $isTruncated = false;

            // 1. Limpieza básica
            $cleanPath = str_replace('..', '', $relativePath);

            // 2. BÚSQUEDA INTELIGENTE:
            // Buscamos el archivo en todos los directorios permitidos (logs y backups)
            foreach ($this->allowedDirectories as $key => $baseDir) {
                if (!$baseDir) continue; 

                $candidatePath = $baseDir . '/' . $cleanPath;
                
                // Si existe, verificamos la seguridad
                if (file_exists($candidatePath) && is_file($candidatePath)) {
                    $realFullPath = realpath($candidatePath);
                    
                    // SANDBOX CHECK: Confirmar que el archivo final realmente está dentro de una carpeta permitida
                    if ($realFullPath && strpos($realFullPath, $baseDir) === 0) {
                        $foundPath = $realFullPath;
                        break; // ¡Encontrado! Dejamos de buscar
                    }
                }
            }

            // 3. Lectura segura
            if ($foundPath) {
                $size = filesize($foundPath);
                
                // Límite de 1MB para evitar colgar el navegador
                if ($size > 1048576) { 
                    $content = $this->tailCustom($foundPath, 50000); // ~50KB del final
                    $isTruncated = true;
                } else {
                    $content = file_get_contents($foundPath);
                }
            } else {
                $error = 'Acceso denegado o archivo no encontrado en rutas permitidas.';
            }

            $results[] = [
                'path' => $relativePath,
                'filename' => basename($relativePath),
                'content' => $content,
                'error' => $error,
                'is_truncated' => $isTruncated,
                'size' => $this->formatSize($size)
            ];
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