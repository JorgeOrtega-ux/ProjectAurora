<?php
// includes/sections/app/watch.php

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
                    if (!empty($currAvatarPath) && file_exists(__DIR__ . '/../../../' . $currAvatarPath)) {
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
    if (!empty($avatarPath) && file_exists(__DIR__ . '/../../../' . $avatarPath)) {
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

<div class="component-wrapper component-wrapper--full component-wrapper--watch-page" data-section="watch">
    
    <?php if ($videoData): ?>
        <?php
        $isCinemaMode = isset($_COOKIE['aurora_cinema_mode']) && $_COOKIE['aurora_cinema_mode'] === 'on';
        $cinemaClass = $isCinemaMode ? 'component-watch-mode-cinema' : '';
        ?>

        <div class="js-video-context d-none" 
             data-video-uuid="<?php echo htmlspecialchars($videoUuid); ?>"
             data-channel-uuid="<?php echo htmlspecialchars($videoData['user_uuid']); ?>"
             data-user-avatar="<?php echo htmlspecialchars($currentUserAvatar); ?>"></div>
        
        <div class="component-watch-layout <?php echo $cinemaClass; ?>">
            
            <div class="component-watch-col-left">
                
                <div class="component-watch-player-card" id="video-container">
                    <canvas id="ambient-canvas" class="component-watch-ambient-canvas"></canvas>

                    <div class="component-watch-player-wrapper">
                        <video id="main-player" playsinline poster="" class="component-watch-video-element"></video>
                        
                        <div id="scrub-tooltip" class="component-watch-scrub-tooltip">
                            <div class="component-watch-scrub-img-wrapper">
                                <div class="component-watch-scrub-preview"></div>
                            </div>
                            <div class="component-watch-scrub-time-pill">
                                <span class="component-watch-scrub-time">0:00</span>
                            </div>
                        </div>
                    </div>

                    <div id="settings-popover" class="component-watch-settings-popover">
                        <div id="settings-main" class="component-watch-settings-panel active">
                            <div class="component-watch-settings-item" data-target="lighting">
                                <span class="material-symbols-rounded icon-left">light_mode</span>
                                <span class="setting-label">Iluminación cinematográfica</span>
                                <span class="setting-value" id="lighting-status-text">Desactivado</span>
                                <span class="material-symbols-rounded icon-right">chevron_right</span>
                            </div>
                            <div class="component-watch-settings-item" data-target="quality">
                                <span class="material-symbols-rounded icon-left">tune</span>
                                <span class="setting-label">Calidad</span>
                                <span class="setting-value" id="quality-status-text">Auto</span>
                                <span class="material-symbols-rounded icon-right">chevron_right</span>
                            </div>
                        </div>
                        <div id="settings-lighting" class="component-watch-settings-panel">
                            <div class="component-watch-settings-header" data-back="main">
                                <span class="material-symbols-rounded">arrow_back</span>
                                <span>Iluminación cinematográfica</span>
                            </div>
                            <div class="component-watch-settings-option" data-type="lighting" data-value="off">
                                <span>Desactivado</span><span class="material-symbols-rounded check-icon">check</span>
                            </div>
                            <div class="component-watch-settings-option" data-type="lighting" data-value="on">
                                <span>Activo</span><span class="material-symbols-rounded check-icon">check</span>
                            </div>
                        </div>
                        <div id="settings-quality" class="component-watch-settings-panel">
                            <div class="component-watch-settings-header" data-back="main">
                                <span class="material-symbols-rounded">arrow_back</span>
                                <span>Calidad</span>
                            </div>
                            <div id="quality-options-container"></div>
                        </div>
                    </div>

                    <div class="component-watch-controls" id="custom-controls">
                        <div class="component-watch-progress-container">
                            <div class="component-watch-progress-hover"></div>
                            <input type="range" id="seek-bar" class="component-watch-seek-bar" value="0" min="0" step="0.1">
                        </div>

                        <div class="component-watch-controls-row">
                            <div class="component-watch-controls-left">
                                <div class="component-watch-control-pill">
                                    <button id="play-pause-btn" class="component-watch-control-btn" title="Reproducir">
                                        <span class="material-symbols-rounded">play_arrow</span>
                                    </button>
                                </div>
                                <div class="component-watch-control-pill component-watch-volume-container">
                                    <div class="component-watch-volume-box">
                                        <button id="mute-btn" class="component-watch-control-btn" title="Volumen">
                                            <span class="material-symbols-rounded">volume_up</span>
                                        </button>
                                        <div class="component-watch-volume-expander">
                                            <div class="component-watch-volume-slider-wrap">
                                                <input type="range" id="volume-bar" class="component-watch-volume-bar" min="0" max="1" step="0.05" value="1">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="component-watch-control-pill component-watch-timer-pill">
                                    <div class="component-watch-timer-block">
                                        <span id="current-time">0:00</span><span class="component-watch-time-sep">/</span><span id="duration">0:00</span>
                                    </div>
                                </div>
                            </div>

                            <div class="component-watch-controls-right">
                                <div class="component-watch-control-pill component-watch-group-pill">
                                    <button id="settings-btn" class="component-watch-control-btn" title="Configuración">
                                        <span class="material-symbols-rounded">settings</span>
                                    </button>
                                    <button id="cinema-mode-btn" class="component-watch-control-btn" title="Modo Cine">
                                        <span class="material-symbols-rounded">crop_landscape</span>
                                    </button>
                                    <button id="fullscreen-btn" class="component-watch-control-btn" title="Pantalla Completa">
                                        <span class="material-symbols-rounded">fullscreen</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="component-watch-meta-card">
                    <h1 class="component-watch-title"><?php echo htmlspecialchars($videoData['title']); ?></h1>
                    
                    <div class="component-watch-author-row">
                        <div class="component-watch-author-info">
                            <a href="/ProjectAurora/channel/<?php echo $videoData['user_uuid']; ?>" class="component-watch-author-link">
                                <img src="<?php echo $avatarUrl; ?>" alt="Avatar" class="component-watch-avatar">
                                <div class="component-watch-author-text">
                                    <span class="component-watch-username"><?php echo htmlspecialchars($videoData['username']); ?></span>
                                    <span class="component-watch-subs js-count-subs"><?php echo formatCount($interaction['subs_count']); ?> suscriptores</span>
                                </div>
                            </a>
                            
                            <button class="component-button primary js-btn-subscribe component-watch-subscribe-btn <?php echo $interaction['subscribed'] ? 'subscribed' : ''; ?>">
                                <?php echo $interaction['subscribed'] ? 'Suscrito' : 'Suscribirse'; ?>
                            </button>
                        </div>
                        
                        <div class="component-watch-actions">
                            <div class="component-watch-joined-pill">
                                <button class="component-watch-joined-btn like js-btn-like <?php echo $interaction['liked'] ? 'active' : ''; ?>" title="Me gusta">
                                    <span class="material-symbols-rounded">thumb_up</span>
                                    <span class="js-count-like"><?php echo formatCount($interaction['likes_count']); ?></span>
                                </button>
                                <div class="component-watch-joined-separator"></div>
                                <button class="component-watch-joined-btn dislike js-btn-dislike <?php echo $interaction['disliked'] ? 'active' : ''; ?>" title="No me gusta">
                                    <span class="material-symbols-rounded">thumb_down</span>
                                    </button>
                            </div>
                            <button class="component-button component-watch-action-pill js-btn-share">
                                <span class="material-symbols-rounded">share</span> Compartir
                            </button>
                        </div>
                    </div>

                    <div class="component-watch-desc-box" style="--dominant-rgb: <?php echo $domColorRgb; ?>;">
                        <p class="component-watch-desc-meta">
                            <span class="js-view-count"><?php echo number_format($interaction['views_count']); ?></span> visualizaciones • <?php echo date('d M Y', strtotime($videoData['created_at'])); ?>
                        </p>
                        
                        <?php 
                        $fullDesc = $videoData['description'] ?? '';
                        $descLimit = 955;
                        $isLongDesc = mb_strlen($fullDesc) > $descLimit;
                        $displayDesc = $isLongDesc ? mb_substr($fullDesc, 0, $descLimit) . '...' : $fullDesc;
                        ?>

                        <p class="component-watch-desc-text" id="video-description-text" data-full-text="<?php echo htmlspecialchars($fullDesc); ?>" data-truncated-text="<?php echo htmlspecialchars($displayDesc); ?>"><?php echo nl2br(htmlspecialchars($displayDesc)); ?></p>
                        
                        <?php if ($isLongDesc): ?>
                            <button class="component-button text small js-read-more-desc" id="btn-toggle-description" data-action="expand">Leer más</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="component-watch-comments">
                    
                    <div class="component-watch-comments-header-box">
                        <h3 class="component-watch-comments-title">Comentarios</h3>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="component-watch-comment-input-row" id="main-comment-form-container">
                                <img src="<?php echo $currentUserAvatar; ?>" class="component-comment-avatar">
                                
                                <div class="component-watch-comment-input-wrapper chat-style" id="comment-wrapper-box">
                                    <textarea id="comment-input-main" class="component-input auto-expand chat-input" placeholder="Añade un comentario..." rows="1" maxlength="10000"></textarea>
                                    
                                    <button class="component-input-embedded-btn" id="btn-submit-main" disabled title="Enviar">
                                        <span class="material-symbols-rounded">send</span>
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="component-watch-login-placeholder">
                                <p class="component-watch-login-text">Inicia sesión para comentar</p>
                                <a href="/ProjectAurora/login" class="component-button primary small">Iniciar Sesión</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="comments-list" class="component-watch-comments-list">
                        <div class="component-loader-spinner component-loader-center"></div>
                    </div>
                </div>

            </div>

            <div class="component-watch-col-right">
                <div class="component-card component-watch-related-placeholder">
                    <span class="material-symbols-rounded component-watch-related-icon">playlist_play</span>
                    <p>Próximamente: Videos relacionados</p>
                </div>
            </div>

        </div>

        <input type="hidden" id="watch-hls-source" value="<?php echo '/ProjectAurora/' . ltrim($videoData['hls_path'], '/'); ?>">
        <input type="hidden" id="watch-sprite-source" value="<?php echo !empty($videoData['sprite_path']) ? '/ProjectAurora/' . ltrim($videoData['sprite_path'], '/') : ''; ?>">
        <input type="hidden" id="watch-vtt-source" value="<?php echo !empty($videoData['vtt_path']) ? '/ProjectAurora/' . ltrim($videoData['vtt_path'], '/') : ''; ?>">

    <?php else: ?>
        <div class="component-layout-centered">
            <div class="component-card component-watch-error-state">
                <span class="material-symbols-rounded component-watch-error-icon">videocam_off</span>
                <h2 class="component-watch-error-title">Video no disponible</h2>
                <p class="component-watch-error-desc">El video no existe o es privado.</p>
                <button class="component-button primary mt-16" onclick="window.history.back()">Volver</button>
            </div>
        </div>
    <?php endif; ?>

</div>