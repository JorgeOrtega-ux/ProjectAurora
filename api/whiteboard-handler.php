<?php
// api/whiteboard-handler.php

// 1. Cargar entorno (Recuperamos las variables que bootstrap devuelve)
$app = require_once '../includes/bootstrap.php';
$pdo = $app['pdo'];
$i18n = $app['i18n'];

require_once 'services/AuthService.php';
require_once 'services/WhiteboardService.php';

header('Content-Type: application/json');

// 2. Verificar Autenticación
// CORRECCIÓN: Pasar $i18n al constructor
$auth = new AuthService($pdo, $i18n);

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$currentUser = $_SESSION['user_id'];
$wbService = new WhiteboardService($pdo);

// 3. Procesar Solicitud
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if ($action === 'create') {
            $name = $input['name'] ?? 'Nuevo Pizarrón';
            $visibility = $input['visibility'] ?? 'private';
            $uuid = $wbService->createWhiteboard($currentUser, $name, $visibility);
            echo json_encode(['success' => true, 'uuid' => $uuid]);
        } 
        elseif ($action === 'save') {
            $uuid = $input['uuid'] ?? '';
            $content = $input['content'] ?? '';
            
            if (empty($uuid) || empty($content)) {
                throw new Exception("Datos incompletos");
            }
            
            // Si content es un array/objeto, convertirlo a string JSON
            if (is_array($content) || is_object($content)) {
                $content = json_encode($content);
            }

            $wbService->saveContent($uuid, $currentUser, $content);
            echo json_encode(['success' => true]);
        }
        elseif ($action === 'update_access') {
            $uuid = $input['uuid'] ?? '';
            $level = $input['visibility'] ?? 'private';
            
            if (empty($uuid)) throw new Exception("UUID requerido");
            
            $wbService->setAccessLevel($uuid, $currentUser, $level);
            echo json_encode(['success' => true, 'message' => 'Visibilidad actualizada']);
        }
        else {
            throw new Exception("Acción no válida");
        }
    } 
    elseif ($method === 'GET') {
        if ($action === 'load') {
            $uuid = $_GET['uuid'] ?? '';
            if (empty($uuid)) throw new Exception("UUID requerido");

            $content = $wbService->getContent($uuid, $currentUser);
            // Devolvemos el contenido directamente dentro de 'data'
            echo json_encode(['success' => true, 'data' => json_decode($content)]);
        } 
        elseif ($action === 'get_metadata') {
            $uuid = $_GET['uuid'] ?? '';
            if (empty($uuid)) throw new Exception("UUID requerido");
            
            $metadata = $wbService->getMetadata($uuid);
            // Añadir flag isOwner para la UI
            $metadata['isOwner'] = ((int)$metadata['user_id'] === (int)$currentUser);
            unset($metadata['user_id']); // No exponer ID interno innecesariamente
            
            echo json_encode(['success' => true, 'data' => $metadata]);
        }
        else {
            throw new Exception("Acción GET no válida");
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>