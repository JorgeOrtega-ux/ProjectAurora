<?php

// Asegurar dependencias globales
if (!isset($pdo) || !isset($redis)) {
    global $pdo, $redis;
}

$videoUuid = trim($_GET['v'] ?? '');
$videoData = null;

// Inicializar estado de interacción
$interaction = [
    'liked' => false,
    'disliked' => false,
    'subscribed' => false,
    'likes_count' => 0,
    'dislikes_count' => 0,
    'views_count' => 0,
    'subs_count' => 0
];

// Inicializar usuario actual
$currentUser = null;
$currentUserAvatar = '';

if (!empty($videoUuid) && isset($pdo)) {
    try {
        // 1. Obtener datos del video y autor
        $stmt = $pdo->prepare("
            SELECT v.id, v.title, v.description, v.hls_path, v.sprite_path, v.vtt_path, 
                   v.created_at, v.dominant_color, v.views_count, v.likes_count, v.dislikes_count,
                   u.id as author_id, u.username, u.avatar_path, u.uuid as user_uuid, u.subscribers_count
            FROM videos v 
            JOIN users u ON v.user_id = u.id 
            WHERE v.uuid = ? AND v.status = 'published' 
            LIMIT 1
        ");
        $stmt->execute([$videoUuid]);
        $videoData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($videoData) {
            // 2. Lógica Híbrida Redis/DB para contadores en tiempo real
            if ($redis) {
                $bufferKey = "video:buffer:views:{$videoUuid}";
                $bufferCount = (int)$redis->get($bufferKey);
                $interaction['views_count'] = (int)$videoData['views_count'] + $bufferCount;

                $vStats = $redis->hgetall("video:stats:{$videoUuid}");
                if ($vStats) {
                    $interaction['likes_count'] = $vStats['likes'] ?? $videoData['likes_count'];
                    $interaction['dislikes_count'] = $vStats['dislikes'] ?? $videoData['dislikes_count'];
                } else {
                    $interaction['likes_count'] = $videoData['likes_count'];
                    $interaction['dislikes_count'] = $videoData['dislikes_count'];
                }

                $uStats = $redis->hget("user:stats:{$videoData['user_uuid']}", 'subscribers');
                $interaction['subs_count'] = ($uStats !== null) ? $uStats : $videoData['subscribers_count'];

            } else {
                $interaction['likes_count'] = $videoData['likes_count'];
                $interaction['dislikes_count'] = $videoData['dislikes_count'];
                $interaction['views_count'] = $videoData['views_count'];
                $interaction['subs_count'] = $videoData['subscribers_count'];
            }

            // 3. Estado del usuario (si está logueado)
            if (isset($_SESSION['user_id'])) {
                $myId = $_SESSION['user_id'];
                
                $chkInt = $pdo->prepare("SELECT type FROM video_interactions WHERE user_id = ? AND video_id = ?");
                $chkInt->execute([$myId, $videoData['id']]);
                $type = $chkInt->fetchColumn();
                
                if ($type === 'like') $interaction['liked'] = true;
                if ($type === 'dislike') $interaction['disliked'] = true;

                $chkSub = $pdo->prepare("SELECT id FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
                $chkSub->execute([$myId, $videoData['author_id']]);
                if ($chkSub->fetch()) {
                    $interaction['subscribed'] = true;
                }

                $stmtUser = $pdo->prepare("SELECT username, avatar_path FROM users WHERE id = ?");
                $stmtUser->execute([$myId]);
                $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

                if ($currentUser) {
                    $currAvatarPath = $currentUser['avatar_path'] ?? '';
                    if (!empty($currAvatarPath) && file_exists(__DIR__ . '/../../../../' . $currAvatarPath)) {
                        $currentUserAvatar = '/ProjectAurora/' . ltrim($currAvatarPath, '/'); 
                    } else {
                        $currentUserAvatar = "https://ui-avatars.com/api/?name=" . urlencode($currentUser['username']) . "&background=333&color=fff";
                    }
                }
            }
        }

    } catch (Exception $e) {
        error_log("Watch Page Error: " . $e->getMessage());
    }
}

// Helpers visuales para el frontend
$avatarUrl = '';
$domColorRgb = '20, 20, 20'; 

if ($videoData) {
    $avatarPath = $videoData['avatar_path'] ?? '';
    if (!empty($avatarPath) && file_exists(__DIR__ . '/../../../../' . $avatarPath)) {
        $avatarUrl = '/ProjectAurora/' . ltrim($avatarPath, '/'); 
    } else {
        $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($videoData['username']) . "&background=333&color=fff";
    }

    $hex = $videoData['dominant_color'] ?? '#202020';
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 6) {
        list($r, $g, $b) = sscanf($hex, "%02x%02x%02x");
        $domColorRgb = "$r, $g, $b";
    }
}

function formatCount($n) {
    if ($n >= 1000000) return number_format($n / 1000000, 1) . 'M';
    if ($n >= 1000) return number_format($n / 1000, 1) . 'K';
    return $n;
}
?>