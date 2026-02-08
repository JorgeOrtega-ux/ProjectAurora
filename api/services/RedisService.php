<?php
// api/services/RedisService.php

namespace Aurora\Services;

use Aurora\Libs\Utils;
use Aurora\Libs\Logger;
use Predis\Collection\Iterator\Keyspace;
use Exception;
use DateTime;

class RedisService {
    private $redis;

    public function __construct($redis) {
        $this->redis = $redis;
    }

    public function getStats() {
        if (!$this->redis) return ['success' => false, 'message' => 'Redis no disponible'];

        try {
            $info = $this->redis->info();
            $dbSize = $this->redis->dbsize();

            return [
                'success' => true,
                'stats' => [
                    'version' => $info['Server']['redis_version'],
                    'uptime' => $this->formatUptime($info['Server']['uptime_in_seconds']),
                    'memory_used' => $info['Memory']['used_memory_human'],
                    'memory_peak' => $info['Memory']['used_memory_peak_human'],
                    'connected_clients' => $info['Clients']['connected_clients'],
                    'total_keys' => $dbSize
                ]
            ];
        } catch (Exception $e) {
            Logger::app('Redis Error (getStats)', ['msg' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error de conexión con el servicio de caché (Redis).'];
        }
    }

    public function getKeys($pattern = '*', $limit = 100) {
        if (!$this->redis) return ['success' => false, 'message' => 'Redis no disponible'];

        try {
            $foundKeys = [];
            $count = 0;
            
            if (empty($pattern)) $pattern = '*';

            $iterator = new \Predis\Collection\Iterator\Keyspace($this->redis, $pattern);

            foreach ($iterator as $key) {
                $type = $this->redis->type($key);
                $ttl = $this->redis->ttl($key);
                
                $foundKeys[] = [
                    'key' => $key,
                    'type' => (string)$type,
                    'ttl' => $ttl
                ];
                
                $count++;
                if ($count >= $limit) break;
            }

            usort($foundKeys, function($a, $b) {
                return strcmp($a['key'], $b['key']);
            });

            return ['success' => true, 'keys' => $foundKeys];

        } catch (Exception $e) {
            Logger::app('Redis Error (getKeys)', ['msg' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error de conexión con el servicio de caché (Redis).'];
        }
    }

    public function getValue($key) {
        if (!$this->redis) return ['success' => false, 'message' => 'Redis no disponible'];

        try {
            if (!$this->redis->exists($key)) {
                return ['success' => false, 'message' => 'La clave no existe'];
            }

            // [SEGURIDAD] Bloqueo de lectura para claves sensibles
            // Esto evita el secuestro de sesiones o robo de tokens si un admin es comprometido
            if ($this->isSensitive($key)) {
                return [
                    'success' => true, 
                    'data' => [
                        'key' => $key,
                        'type' => (string)$this->redis->type($key),
                        'ttl' => $this->redis->ttl($key),
                        'size' => '---', // Ocultamos el tamaño para no dar pistas
                        'value' => '[CONTENIDO PROTEGIDO: INFORMACIÓN SENSIBLE]'
                    ]
                ];
            }

            $type = $this->redis->type($key);
            $value = null;
            $size = 0;

            switch ($type) {
                case 'string':
                    $value = $this->redis->get($key);
                    $size = strlen($value);
                    break;
                case 'list':
                    $value = $this->redis->lrange($key, 0, -1);
                    $size = count($value);
                    break;
                case 'hash':
                    $value = $this->redis->hgetall($key);
                    $size = count($value);
                    break;
                case 'set':
                    $value = $this->redis->smembers($key);
                    $size = count($value);
                    break;
                case 'zset':
                    $value = $this->redis->zrange($key, 0, -1, ['withscores' => true]);
                    $size = count($value);
                    break;
                default:
                    $value = 'Tipo no soportado para visualización';
            }

            return [
                'success' => true, 
                'data' => [
                    'key' => $key,
                    'type' => (string)$type,
                    'ttl' => $this->redis->ttl($key),
                    'size' => $size,
                    'value' => $value
                ]
            ];

        } catch (Exception $e) {
            Logger::app('Redis Error (getValue)', ['msg' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error de conexión con el servicio de caché (Redis).'];
        }
    }

    public function deleteKey($key) {
        if (!$this->redis) return ['success' => false, 'message' => 'Redis no disponible'];
        
        try {
            $deleted = $this->redis->del($key);
            return ['success' => $deleted > 0, 'message' => $deleted ? 'Clave eliminada' : 'Clave no encontrada'];
        } catch (Exception $e) {
            Logger::app('Redis Error (deleteKey)', ['msg' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error de conexión con el servicio de caché (Redis).'];
        }
    }

    public function flushDB() {
        if (!$this->redis) return ['success' => false, 'message' => 'Redis no disponible'];
        try {
            $this->redis->flushdb();
            return ['success' => true, 'message' => 'Base de datos Redis vaciada correctamente'];
        } catch (Exception $e) {
            Logger::app('Redis Error (flushDB)', ['msg' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error de conexión con el servicio de caché (Redis).'];
        }
    }

    private function formatUptime($seconds) {
        $dtF = new \DateTime('@0');
        $dtT = new \DateTime("@$seconds");
        return $dtF->diff($dtT)->format('%a días, %h horas, %i min');
    }

    /**
     * [SEGURIDAD] Detecta patrones de claves que no deben ser legibles.
     */
    private function isSensitive($key) {
        $patterns = [
            '/^PHPREDIS_SESSION/', // Sesiones de PHP (Crítico)
            '/session:/i',         // Convención de sesiones manuales
            '/token:/i',           // Tokens de descarga, WS, etc.
            '/verify:/i',          // Códigos de verificación de email/cuenta
            '/auth:/i',            // Datos de autenticación
            '/secret/i',           // Claves secretas
            '/password/i',         // Contraseñas (aunque deberían estar hasheadas)
            '/csrf/i'              // Tokens Anti-CSRF
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $key)) {
                return true;
            }
        }
        return false;
    }
}
?>