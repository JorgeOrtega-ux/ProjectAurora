<?php
// includes/logic/admin/communities_service.php

function list_communities($pdo, $q) {
    $q = trim($q ?? '');
    $sql = "SELECT id, uuid, community_name, community_type, privacy, member_count, profile_picture, is_verified FROM communities";
    $params = [];
    
    if (!empty($q)) {
        $sql .= " WHERE community_name LIKE ? OR access_code LIKE ?";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $communities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return ['success' => true, 'communities' => $communities];
}

function get_admin_community_details($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM communities WHERE id = ?");
    $stmt->execute([$id]);
    $community = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($community) {
        $stmtCh = $pdo->prepare("SELECT * FROM community_channels WHERE community_id = ? ORDER BY created_at ASC");
        $stmtCh->execute([$id]);
        $community['channels'] = $stmtCh->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'community' => $community];
    } else {
        throw new Exception("Comunidad no encontrada");
    }
}

function save_community($pdo, $data) {
    $id = (int)($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $type = $data['community_type'] ?? 'other';
    $allowedTypes = ['municipality', 'university', 'other'];
    if (!in_array($type, $allowedTypes)) $type = 'other';

    $privacy = $data['privacy'] ?? 'public';
    $code = trim($data['access_code'] ?? '');
    $pfp = trim($data['profile_picture'] ?? '');
    $banner = trim($data['banner_picture'] ?? '');
    
    $isVerified = isset($data['is_verified']) && $data['is_verified'] ? 1 : 0;
    $maxMembers = (int)($data['max_members'] ?? 0);
    $commStatus = $data['status'] ?? 'active';
    if (!in_array($commStatus, ['active', 'maintenance'])) $commStatus = 'active';
    
    $channelsRaw = $data['channels'] ?? []; 

    if (empty($name) || empty($code)) throw new Exception("Nombre y Código de acceso son obligatorios.");
    if (!in_array($privacy, ['public', 'private'])) throw new Exception("Privacidad inválida.");

    $stmtCheck = $pdo->prepare("SELECT id FROM communities WHERE access_code = ? AND id != ?");
    $stmtCheck->execute([$code, $id]);
    if ($stmtCheck->rowCount() > 0) throw new Exception("El código de acceso ya está en uso.");

    $pdo->beginTransaction(); 

    try {
        if ($id === 0) {
            // CREAR
            $uuid = generate_uuid();
            $sql = "INSERT INTO communities (uuid, community_name, community_type, access_code, privacy, profile_picture, banner_picture, is_verified, max_members, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $pdo->prepare($sql)->execute([$uuid, $name, $type, $code, $privacy, $pfp, $banner, $isVerified, $maxMembers, $commStatus]);
            $id = $pdo->lastInsertId(); 
            $msg = "Comunidad creada correctamente.";
            
            if (empty($channelsRaw)) {
                $channelsRaw[] = ['id' => 0, 'name' => 'General', 'type' => 'text', 'is_default' => true, 'status' => 'active'];
            }
        } else {
            // EDITAR
            $sql = "UPDATE communities SET community_name=?, community_type=?, access_code=?, privacy=?, profile_picture=?, banner_picture=?, is_verified=?, max_members=?, status=? WHERE id=?";
            $pdo->prepare($sql)->execute([$name, $type, $code, $privacy, $pfp, $banner, $isVerified, $maxMembers, $commStatus, $id]);
            $msg = "Comunidad actualizada correctamente.";
        }

        // --- PROCESAR CANALES ---
        $stmtCurrentIds = $pdo->prepare("SELECT id FROM community_channels WHERE community_id = ?");
        $stmtCurrentIds->execute([$id]);
        $existingIds = $stmtCurrentIds->fetchAll(PDO::FETCH_COLUMN);

        $submittedIds = [];
        $newDefaultChannelId = null; 

        foreach ($channelsRaw as $ch) {
            $chId = (int)($ch['id'] ?? 0);
            $chName = trim($ch['name'] ?? 'Canal');
            $chType = $ch['type'] ?? 'text';
            
            if ($chType !== 'text' && $chType !== 'voice') {
                $chType = 'text';
            }

            $maxUsers = isset($ch['max_users']) ? (int)$ch['max_users'] : 0;
            $chStatus = $ch['status'] ?? 'active';
            if (!in_array($chStatus, ['active', 'maintenance'])) $chStatus = 'active';

            $isDefault = isset($ch['is_default']) && $ch['is_default'] === true;
            
            if (empty($chName)) continue;

            if ($chId > 0 && in_array($chId, $existingIds)) {
                // UPDATE
                $stmtUpdCh = $pdo->prepare("UPDATE community_channels SET name = ?, type = ?, max_users = ?, status = ? WHERE id = ? AND community_id = ?");
                $stmtUpdCh->execute([$chName, $chType, $maxUsers, $chStatus, $chId, $id]);
                $submittedIds[] = $chId;
                
                if ($isDefault) $newDefaultChannelId = $chId;

            } else {
                // INSERT
                $chUuid = generate_uuid();
                $stmtInsCh = $pdo->prepare("INSERT INTO community_channels (uuid, community_id, name, type, max_users, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmtInsCh->execute([$chUuid, $id, $chName, $chType, $maxUsers, $chStatus]);
                $newId = $pdo->lastInsertId();
                
                if ($isDefault) $newDefaultChannelId = $newId;
            }
        }

        // Eliminar canales
        $toDelete = array_diff($existingIds, $submittedIds);
        if (!empty($toDelete)) {
            $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
            $stmtDelCh = $pdo->prepare("DELETE FROM community_channels WHERE id IN ($placeholders) AND community_id = ?");
            $params = array_merge($toDelete, [$id]);
            $stmtDelCh->execute($params);
        }

        if ($newDefaultChannelId) {
            $pdo->prepare("UPDATE communities SET default_channel_id = ? WHERE id = ?")->execute([$newDefaultChannelId, $id]);
        } else {
            $stmtFirst = $pdo->prepare("SELECT id FROM community_channels WHERE community_id = ? ORDER BY created_at ASC LIMIT 1");
            $stmtFirst->execute([$id]);
            $firstId = $stmtFirst->fetchColumn();
            if ($firstId) {
                $pdo->prepare("UPDATE communities SET default_channel_id = ? WHERE id = ?")->execute([$firstId, $id]);
            }
        }

        $pdo->commit();
        return ['success' => true, 'message' => $msg];

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function delete_community($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM communities WHERE id = ?");
    if ($stmt->execute([$id])) {
        return ['success' => true, 'message' => "Comunidad eliminada."];
    } else {
        throw new Exception("Error al eliminar.");
    }
}

// [NUEVO] Obtener solicitudes de unión pendientes
function get_join_requests($pdo) {
    // Se hace JOIN con users y communities para mostrar info relevante
    $sql = "SELECT r.id, r.user_id, r.community_id, r.created_at, 
                   u.username, u.profile_picture as user_picture, u.uuid as user_uuid,
                   c.community_name, c.profile_picture as community_picture
            FROM community_join_requests r
            JOIN users u ON r.user_id = u.id
            JOIN communities c ON r.community_id = c.id
            WHERE r.status = 'pending'
            ORDER BY r.created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return ['success' => true, 'requests' => $requests];
}

// [NUEVO] Resolver solicitud (Aceptar/Rechazar)
function resolve_join_request($pdo, $requestId, $decision, $adminId) {
    $requestId = (int)$requestId;
    
    $stmtReq = $pdo->prepare("SELECT user_id, community_id, status FROM community_join_requests WHERE id = ?");
    $stmtReq->execute([$requestId]);
    $request = $stmtReq->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new Exception("Solicitud no encontrada.");
    }

    if ($request['status'] !== 'pending') {
        throw new Exception("Esta solicitud ya fue procesada.");
    }

    $pdo->beginTransaction();
    try {
        if ($decision === 'accept') {
            // 1. Cambiar estado a accepted
            $stmtUpd = $pdo->prepare("UPDATE community_join_requests SET status = 'accepted' WHERE id = ?");
            $stmtUpd->execute([$requestId]);

            // 2. Insertar en community_members (verificar si ya existe por si acaso)
            $stmtCheck = $pdo->prepare("SELECT id FROM community_members WHERE community_id = ? AND user_id = ?");
            $stmtCheck->execute([$request['community_id'], $request['user_id']]);
            
            if ($stmtCheck->rowCount() == 0) {
                $stmtIns = $pdo->prepare("INSERT INTO community_members (community_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
                $stmtIns->execute([$request['community_id'], $request['user_id']]);

                // 3. Incrementar contador
                $pdo->prepare("UPDATE communities SET member_count = member_count + 1 WHERE id = ?")->execute([$request['community_id']]);
            }
            
            $msg = "Solicitud aceptada. Usuario añadido.";

        } elseif ($decision === 'reject') {
            // Rechazar
            $stmtUpd = $pdo->prepare("UPDATE community_join_requests SET status = 'rejected' WHERE id = ?");
            $stmtUpd->execute([$requestId]);
            $msg = "Solicitud rechazada.";
        } else {
            throw new Exception("Decisión inválida.");
        }

        $pdo->commit();
        return ['success' => true, 'message' => $msg];

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
?>