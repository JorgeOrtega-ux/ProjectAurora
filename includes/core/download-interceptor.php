<?php
// includes/core/download-interceptor.php

// Requiere que $currentSection y $redis estén definidos
if (isset($currentSection) && $currentSection === 'download') {
    // Verificar si el servicio de Redis está activo
    if (!isset($redis) || !$redis) {
        http_response_code(500);
        die("Servicio de descargas no disponible temporalmente.");
    }

    $token = $_GET['token'] ?? '';

    if (empty($token)) {
        http_response_code(400);
        die("Solicitud inválida.");
    }

    // Verificar Token en Redis
    $redisKey = "download:token:$token";
    $dataJson = $redis->get($redisKey);

    if (!$dataJson) {
        http_response_code(403);
        die("El enlace de descarga ha expirado o no es válido.");
    }

    $data = json_decode($dataJson, true);
    $filepath = $data['filepath'];
    $filename = $data['filename'];

    // Validación de seguridad física
    if (!file_exists($filepath)) {
        http_response_code(404);
        die("El archivo ya no existe en el servidor.");
    }

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

    // Quemar el token (One-Time Use)
    $redis->del($redisKey);

    // Detener la ejecución para no cargar el resto del HTML
    exit;
}