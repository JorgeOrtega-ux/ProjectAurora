<?php
// api/services/CanvasService.php

class CanvasService {
    private $pdo;
    private $i18n;

    public function __construct($pdo, $i18n) {
        $this->pdo = $pdo;
        $this->i18n = $i18n; // Por si necesitas traducciones de errores
    }

    public function createCanvas($userId, $size, $privacy, $accessCodeRaw) {
        try {
            // 1. Validar tamaño
            if (!in_array($size, [64, 128])) {
                return ['success' => false, 'message' => 'Tamaño de lienzo inválido.'];
            }

            // 2. Validar privacidad
            if (!in_array($privacy, ['public', 'private'])) {
                return ['success' => false, 'message' => 'Tipo de privacidad inválido.'];
            }

            // 3. Limpiar código de acceso (quitar guiones si vienen del frontend)
            $cleanCode = null;
            if ($privacy === 'private') {
                if (empty($accessCodeRaw)) {
                    return ['success' => false, 'message' => 'El código de acceso es obligatorio para lienzos privados.'];
                }
                $cleanCode = str_replace('-', '', $accessCodeRaw);
                if (strlen($cleanCode) !== 12 || !ctype_digit($cleanCode)) {
                    return ['success' => false, 'message' => 'El código debe ser de 12 dígitos numéricos.'];
                }
            }

            // 4. Verificar si el usuario ya tiene un lienzo (Opcional, según tu lógica de negocio)
            // Si solo permites uno por usuario:
            $stmtCheck = $this->pdo->prepare("SELECT id FROM canvases WHERE user_id = ? AND status = 'active'");
            $stmtCheck->execute([$userId]);
            if ($stmtCheck->fetch()) {
                // Opción A: Error
                // return ['success' => false, 'message' => 'Ya tienes un lienzo activo.'];
                
                // Opción B: Archivar el anterior y crear nuevo (usaré esta lógica para no bloquear)
                $stmtArchive = $this->pdo->prepare("UPDATE canvases SET status = 'archived' WHERE user_id = ?");
                $stmtArchive->execute([$userId]);
            }

            // 5. Insertar
            $stmt = $this->pdo->prepare("
                INSERT INTO canvases (user_id, width, height, privacy, access_code, status) 
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            
            $stmt->execute([
                $userId,
                $size,
                $size, // Width y Height iguales por ahora
                $privacy,
                $cleanCode
            ]);

            // Obtener username para redirección
            $stmtUser = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $user = $stmtUser->fetch();

            return [
                'success' => true, 
                'message' => 'Lienzo creado exitosamente',
                'data' => [
                    'canvas_id' => $this->pdo->lastInsertId(),
                    'redirect_url' => '/ProjectAurora/' . $user['username'] // URL solicitada
                ]
            ];

        } catch (Exception $e) {
            // Loguear error real internamente si tienes Logger
            // Logger::error($e->getMessage());
            return ['success' => false, 'message' => 'Error de base de datos al crear lienzo.'];
        }
    }
}