<?php
// includes/logic/communities/membership_service.php

class MembershipService {

    public static function joinByCode($pdo, $userId, $code) {
        $code = trim($code ?? '');
        
        if (empty($code) || strlen($code) !== 14) {
            throw new Exception("El código debe tener el formato XXXX-XXXX-XXXX.");
        }

        $stmt = $pdo->prepare("SELECT id, community_name, privacy, member_count, max_members FROM communities WHERE access_code = ?");
        $stmt->execute([$code]);
        $community = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$community) {
            throw new Exception("Código de acceso inválido o comunidad no encontrada.");
        }

        // Verificar Baneo
        self::checkBan($pdo, $userId, $community['id']);

        // Validación de Límite de Miembros
        if ($community['max_members'] > 0 && $community['member_count'] >= $community['max_members']) {
            throw new Exception("La comunidad <strong>" . htmlspecialchars($community['community_name']) . "</strong> está llena.");
        }

        $stmtCheck = $pdo->prepare("SELECT id FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmtCheck->execute([$community['id'], $userId]);
        if ($stmtCheck->rowCount() > 0) {
            throw new Exception("Ya eres miembro de <strong>" . htmlspecialchars($community['community_name']) . "</strong>.");
        }

        $pdo->beginTransaction();
        try {
            $stmtInsert = $pdo->prepare("INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, 'member')");
            $stmtInsert->execute([$community['id'], $userId]);

            $stmtCount = $pdo->prepare("UPDATE communities SET member_count = member_count + 1 WHERE id = ?");
            $stmtCount->execute([$community['id']]);

            $pdo->commit();
            return ['success' => true, 'message' => "Te has unido a <strong>" . htmlspecialchars($community['community_name']) . "</strong> correctamente."];
        } catch (Exception $e) {
            $pdo->rollBack();
            throw new Exception(translation('global.error_connection'));
        }
    }

    public static function joinPublic($pdo, $userId, $communityId) {
        $communityId = (int)$communityId;

        // Verificar Baneo
        self::checkBan($pdo, $userId, $communityId);

        $stmtCheck = $pdo->prepare("SELECT id FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmtCheck->execute([$communityId, $userId]);
        if ($stmtCheck->rowCount() > 0) throw new Exception("Ya eres miembro.");
        
        // Validación de Límite
        $stmtLimit = $pdo->prepare("SELECT member_count, max_members FROM communities WHERE id = ?");
        $stmtLimit->execute([$communityId]);
        $commData = $stmtLimit->fetch(PDO::FETCH_ASSOC);

        if ($commData['max_members'] > 0 && $commData['member_count'] >= $commData['max_members']) {
            throw new Exception("La comunidad está llena.");
        }

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO community_members (community_id, user_id) VALUES (?, ?)")->execute([$communityId, $userId]);
        $pdo->prepare("UPDATE communities SET member_count = member_count + 1 WHERE id = ?")->execute([$communityId]);
        $pdo->commit();
        
        return ['success' => true, 'message' => 'Te has unido al grupo.'];
    }

    public static function leaveCommunity($pdo, $userId, $uuid, $communityId) {
        $communityId = (int)$communityId;

        if ($communityId === 0 && !empty($uuid)) {
            $stmtId = $pdo->prepare("SELECT id FROM communities WHERE uuid = ?");
            $stmtId->execute([$uuid]);
            $communityId = $stmtId->fetchColumn();
        }

        if (empty($communityId)) throw new Exception(translation('global.action_invalid'));

        $pdo->beginTransaction();
        $stmtDel = $pdo->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmtDel->execute([$communityId, $userId]);

        if ($stmtDel->rowCount() > 0) {
            $pdo->prepare("UPDATE communities SET member_count = GREATEST(0, member_count - 1) WHERE id = ?")->execute([$communityId]);
            $pdo->commit();
            return ['success' => true, 'message' => 'Has salido del grupo.'];
        } else {
            $pdo->rollBack();
            throw new Exception("No eres miembro de este grupo.");
        }
    }

    public static function getPublicCommunities($pdo, $userId) {
        // [MODIFICADO] Se añade 'c.privacy' y se ajusta el WHERE para incluir privadas
        $sql = "SELECT c.id, c.uuid, c.community_name, c.community_type, c.is_verified, c.member_count, c.profile_picture, c.banner_picture, c.privacy
                FROM communities c
                WHERE (c.privacy = 'public' OR c.privacy = 'private')
                AND c.id NOT IN (SELECT community_id FROM community_members WHERE user_id = ?)
                ORDER BY c.member_count DESC LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $communities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['success' => true, 'communities' => $communities];
    }

    // [NUEVO] Método para solicitar acceso
    public static function requestAccess($pdo, $userId, $uuid = '', $name = '') {
        $communityId = 0;
        $communityName = '';

        if (!empty($uuid)) {
            $stmt = $pdo->prepare("SELECT id, community_name FROM communities WHERE uuid = ?");
            $stmt->execute([$uuid]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) {
                $communityId = $res['id'];
                $communityName = $res['community_name'];
            }
        } elseif (!empty($name)) {
            $stmt = $pdo->prepare("SELECT id, community_name FROM communities WHERE community_name = ?");
            $stmt->execute([$name]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) {
                $communityId = $res['id'];
                $communityName = $res['community_name'];
            }
        }

        if (!$communityId) {
            throw new Exception("Comunidad no encontrada.");
        }

        // Verificar si ya es miembro
        $stmtCheckMember = $pdo->prepare("SELECT id FROM community_members WHERE community_id = ? AND user_id = ?");
        $stmtCheckMember->execute([$communityId, $userId]);
        if ($stmtCheckMember->rowCount() > 0) {
            throw new Exception("Ya eres miembro de esta comunidad.");
        }

        // Verificar si ya tiene solicitud pendiente
        $stmtCheckReq = $pdo->prepare("SELECT id, status FROM community_join_requests WHERE community_id = ? AND user_id = ?");
        $stmtCheckReq->execute([$communityId, $userId]);
        $existingReq = $stmtCheckReq->fetch(PDO::FETCH_ASSOC);

        if ($existingReq) {
            if ($existingReq['status'] === 'pending') {
                throw new Exception("Ya tienes una solicitud pendiente para esta comunidad.");
            } elseif ($existingReq['status'] === 'rejected') {
                // Opcional: Permitir reintentar después de cierto tiempo, o bloquear.
                // Aquí permitiremos reenviar actualizando el estado.
                $pdo->prepare("UPDATE community_join_requests SET status = 'pending', created_at = NOW() WHERE id = ?")->execute([$existingReq['id']]);
                return ['success' => true, 'message' => "Solicitud reenviada correctamente."];
            }
            // Si estaba accepted, ya debería haber saltado el check de miembro, pero por si acaso.
        }

        // Crear solicitud
        $stmtInsert = $pdo->prepare("INSERT INTO community_join_requests (user_id, community_id, status) VALUES (?, ?, 'pending')");
        if ($stmtInsert->execute([$userId, $communityId])) {
            return ['success' => true, 'message' => "Solicitud enviada a <strong>" . htmlspecialchars($communityName) . "</strong>."];
        } else {
            throw new Exception("Error al enviar la solicitud.");
        }
    }

    // Helper privado para verificar baneos
    private static function checkBan($pdo, $userId, $communityId) {
        $stmtBan = $pdo->prepare("SELECT reason, expires_at FROM community_bans WHERE community_id = ? AND user_id = ?");
        $stmtBan->execute([$communityId, $userId]);
        $banData = $stmtBan->fetch(PDO::FETCH_ASSOC);

        if ($banData) {
            $isBanned = true;
            $msg = "No puedes unirte. ";
            
            if ($banData['expires_at']) {
                $expires = new DateTime($banData['expires_at']);
                $now = new DateTime();
                
                if ($now > $expires) {
                    $pdo->prepare("DELETE FROM community_bans WHERE community_id = ? AND user_id = ?")->execute([$communityId, $userId]);
                    $isBanned = false;
                } else {
                    $msg .= "Estás suspendido hasta el " . $expires->format('d/m/Y H:i') . ". Razón: " . htmlspecialchars($banData['reason']);
                }
            } else {
                $msg .= "Estás vetado permanentemente. Razón: " . htmlspecialchars($banData['reason']);
            }

            if ($isBanned) {
                throw new Exception($msg);
            }
        }
    }
}
?>