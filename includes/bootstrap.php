<?php
// includes/bootstrap.php

// 1. Carga del Autoloader de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// 2. CONFIGURACIÓN DE SEGURIDAD PARA LA SESIÓN
// Centralizamos aquí los parámetros de cookies seguras
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path'     => '/',
    'domain'   => $cookieParams['domain'],
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);

// Iniciamos la sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Carga de Utilidades y Manejo de Errores
require_once __DIR__ . '/libs/Utils.php';
Utils::initErrorHandlers();

// 4. Conexión a Base de Datos
// (Nota: db.php ya hace el require de Logger.php y carga el .env)
require_once __DIR__ . '/../config/database/db.php';

// 5. Inicializar Sistema de Internacionalización (I18n)
$i18n = Utils::initI18n();

// 6. Retornar las variables globales críticas para quien use este bootstrap
// Esto permite usar extract() en los archivos públicos
return [
    'pdo'  => $pdo,
    'i18n' => $i18n
];
?>