<?php
// api/services/LogFileService.php

namespace Aurora\Services;

use Aurora\Libs\Utils;
use Exception;

class LogFileService {
    private $logDir;

    public function __construct() {
        $this->logDir = realpath(__DIR__ . '/../../logs');
    }

    public function getLogFiles() {
        if (!$this->logDir) return ['success' => false, 'files' => []];

        $files = glob($this->logDir . '/*.log');
        $result = [];

        foreach ($files as $file) {
            $result[] = [
                'name' => basename($file),
                // [REFACTORIZADO] Uso de Utils::formatSize
                'size' => Utils::formatSize(filesize($file)),
                'updated_at' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }

        // Ordenar por fecha de modificación (más reciente primero)
        usort($result, function($a, $b) {
            return strtotime($b['updated_at']) - strtotime($a['updated_at']);
        });

        return ['success' => true, 'files' => $result];
    }

    public function getLogContent($filename, $lines = 100) {
        // [REFACTORIZADO] Validación de rutas unificada (Sandboxing)
        $cleanName = basename($filename);
        $targetPath = realpath($this->logDir . '/' . $cleanName);

        // Verificar que el archivo existe y que está dentro del directorio de logs (evitar ../)
        if (!$targetPath || !file_exists($targetPath) || strpos($targetPath, $this->logDir) !== 0) {
            return ['success' => false, 'message' => 'Archivo de log inválido o no encontrado.'];
        }

        try {
            // Leer las últimas N líneas de forma eficiente
            $content = $this->tailCustom($targetPath, $lines);
            return ['success' => true, 'content' => $content];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error leyendo archivo.'];
        }
    }

    public function clearLogFile($filename) {
        $cleanName = basename($filename);
        $targetPath = realpath($this->logDir . '/' . $cleanName);

        if (!$targetPath || !file_exists($targetPath) || strpos($targetPath, $this->logDir) !== 0) {
            return ['success' => false, 'message' => 'Archivo inválido.'];
        }

        if (file_put_contents($targetPath, '') !== false) {
            return ['success' => true, 'message' => 'Log limpiado correctamente.'];
        }

        return ['success' => false, 'message' => 'No se pudo escribir en el archivo. Verifique permisos.'];
    }

    /**
     * Lee las últimas N líneas de un archivo sin cargar todo en memoria.
     */
    private function tailCustom($filepath, $lines = 100) {
        $f = @fopen($filepath, "rb");
        if ($f === false) return "Error opening file.";

        fseek($f, 0, SEEK_END);
        $pos = ftell($f);
        $linesRead = 0;
        $output = [];
        $chunkSize = 4096;
        $buffer = '';

        while ($pos > 0 && $linesRead < $lines) {
            $seek = max(0, $pos - $chunkSize);
            fseek($f, $seek);
            $readLen = $pos - $seek;
            $data = fread($f, $readLen);
            $buffer = $data . $buffer;
            
            // Contar saltos de línea en el buffer
            $localLines = substr_count($buffer, "\n");
            
            if ($localLines >= $lines) {
                // Ya tenemos suficientes líneas, recortamos y salimos
                $linesArr = explode("\n", $buffer);
                $output = array_slice($linesArr, -$lines);
                break;
            }
            
            $pos = $seek;
        }

        if (empty($output) && !empty($buffer)) {
             $output = explode("\n", $buffer);
        }

        fclose($f);
        return implode("\n", $output);
    }
}
?>