<?php
// public/index.php

// 1. Inicialización del Entorno (DB, Utils, Auth, CSRF)
require_once __DIR__ . '/../includes/core/app-setup.php';

// 2. Configuración de Rutas URL
require_once __DIR__ . '/../config/routers/router.php';

// 4. Lógica de Negocio (Permisos, Sesión, Mantenimiento, Selección de Archivo)
require_once __DIR__ . '/../includes/core/routing-logic.php';

// 5. Renderizado de la Vista (HTML)
require_once __DIR__ . '/../includes/layouts/main-shell.php';
?>