<?php
// config/database/redis.php

class RedisClient {
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            try {
                $redis = new Redis();
                // Ajusta host/port según tus variables de entorno o defaults
                $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
                $port = $_ENV['REDIS_PORT'] ?? 6379;
                $redis->connect($host, $port);
                
                // Opcional: Autenticación
                $auth = $_ENV['REDIS_PASSWORD'] ?? null;
                if ($auth) { $redis->auth($auth); }

                self::$instance = $redis;
            } catch (Exception $e) {
                // Fallback silencioso o log de error
                error_log("Redis connection error: " . $e->getMessage());
                return null;
            }
        }
        return self::$instance;
    }
}
?>