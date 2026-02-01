<?php
// api/handlers/canvas-handler.php

require_once __DIR__ . '/../services/CanvasService.php';

// Asegurarse de que $pdo y $i18n estén disponibles (generalmente vienen del index o bootstrap)
global $pdo, $i18n;

if (!isset($action)) {
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
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit;
        }

        // [CORRECCIÓN CRÍTICA] Leer desde $_POST ya que ApiService envía FormData
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