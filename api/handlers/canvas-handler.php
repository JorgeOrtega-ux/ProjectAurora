<?php
// api/handlers/canvas-handler.php

// 1. BOOTSTRAP: Cargar configuración, base de datos y utilidades
// Subimos dos niveles para llegar a 'includes'
$services = require_once __DIR__ . '/../../includes/bootstrap.php';
extract($services); // Esto extrae $pdo, $i18n, $redis para usarlos aquí

// 2. Cargar el Servicio
require_once __DIR__ . '/../services/CanvasService.php';

// 3. Validar seguridad (CSRF) - Importante agregarlo como en auth-handler
Utils::validateCsrf($i18n);

// 4. Obtener la acción desde el POST (inyectada por api/index.php)
$action = $_POST['action'] ?? '';

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Acción no especificada']);
    exit;
}

$canvasService = new CanvasService($pdo, $i18n);

switch ($action) {
    case 'create_canvas':
        // Verificar autenticación
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => $i18n->t('auth.unauthorized') ?? 'No autorizado']);
            exit;
        }

        // Leer datos (ApiService envía FormData, así que están en $_POST)
        $size = $_POST['size'] ?? null;
        $privacy = $_POST['privacy'] ?? null;
        $accessCode = $_POST['access_code'] ?? null;
        
        // Validación básica
        if (!$size || !$privacy) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos (size o privacy).']);
            exit;
        }

        $result = $canvasService->createCanvas(
            $_SESSION['user_id'],
            $size,
            $privacy,
            $accessCode
        );

        echo json_encode($result);
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Acción no encontrada en CanvasHandler']);
        break;
}
?>