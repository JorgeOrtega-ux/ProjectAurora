<?php
// includes/bootstrap.php

// 1. CARGA DEL AUTOLOADER DE COMPOSER
require_once __DIR__ . '/../vendor/autoload.php';

use Aurora\Libs\Utils;

// 2. CONFIGURACIÓN DE SEGURIDAD PARA LA SESIÓN
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path'     => '/',
    'domain'   => $cookieParams['domain'],
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Carga de Utilidades y Manejo de Errores
// Utils ya está cargada por Composer, solo llamamos al método
Utils::initErrorHandlers();

// 4. Conexión a Base de Datos
// Ya está cargada automáticamente por Composer ("files": ["config/database/db.php"])
// $pdo está disponible en el scope global.

// 5. Inicializar Sistema de Internacionalización (I18n)
$i18n = Utils::initI18n();

// 6. Retornar las variables globales críticas
return [
    'pdo'  => $pdo,
    'i18n' => $i18n
];
?>