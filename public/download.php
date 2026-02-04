<?php
// public/download.php

// 1. Cargar SOLO lo necesario (Bootstrap inicia DB y Redis)
// Subimos un nivel porque estamos en public/
$services = require_once __DIR__ . '/../includes/bootstrap.php';
$redis = $services['redis'];

// 2. Verificaciones básicas
if (!$redis) {
    http_response_code(500);
    die("Error: Sistema de descargas temporalmente no disponible (Redis).");
}

$token = $_GET['token'] ?? '';

if (empty($token) || !ctype_alnum($token)) {
    http_response_code(400);
    die("Error: Solicitud inválida.");
}

// 3. Buscar el token en Redis
$redisKey = "download:token:$token";
$dataJson = $redis->get($redisKey);

if (!$dataJson) {
    http_response_code(403);
    die("Error: El enlace de descarga ha expirado o no es válido.");
}

// 4. Decodificar datos
$data = json_decode($dataJson, true);
$filepath = $data['filepath'];
$filename = $data['filename'];

// Seguridad: Verificar que el archivo existe físicamente
if (!file_exists($filepath)) {
    http_response_code(404);
    die("Error: El archivo ya no existe en el servidor.");
}

// 5. Preparar la descarga
// Limpiamos cualquier output previo (espacios en blanco, errores, etc)
if (ob_get_level()) {
    ob_end_clean();
}

// Desactivar límite de tiempo para archivos grandes
set_time_limit(0);

// Cabeceras para forzar la descarga
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
// Las comillas en filename son vitales por si el nombre tiene espacios
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));

// 6. Enviar el archivo
readfile($filepath);

// 7. Quemar el token (Seguridad: un solo uso)
// Si quieres permitir reintentos (ej. si falla la red), comenta esta línea.
// Pero por seguridad estricta, se debe borrar.
$redis->del($redisKey);

exit;
?>