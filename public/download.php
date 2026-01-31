<?php
// public/download.php

// 1. Cargar dependencias básicas
// Usamos bootstrap para tener acceso a Redis y Configuración de forma segura
$services = require_once __DIR__ . '/../includes/bootstrap.php';
extract($services); // $pdo, $i18n, $redis

$token = $_GET['token'] ?? '';

if (empty($token) || !$redis) {
    http_response_code(400);
    die("Solicitud inválida o servicio no disponible.");
}

// 2. Verificar Token en Redis
$redisKey = "download:token:$token";
$dataJson = $redis->get($redisKey);

if (!$dataJson) {
    http_response_code(403);
    die("El enlace de descarga ha expirado o no es válido.");
}

$data = json_decode($dataJson, true);
$filepath = $data['filepath'];
$filename = $data['filename'];

// 3. Validación de Seguridad Adicional (Doble Check)
if (!file_exists($filepath)) {
    http_response_code(404);
    die("El archivo ya no existe en el servidor.");
}

// 4. Servir el archivo
// Limpiar buffer de salida para evitar corrupción de binarios
if (ob_get_level()) {
    ob_end_clean();
}

// Cabeceras para forzar descarga
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));

// Leer y enviar archivo
readfile($filepath);

// 5. Quemar el token (One-Time Use) para que no se pueda reutilizar el enlace
$redis->del($redisKey);

exit;
?>