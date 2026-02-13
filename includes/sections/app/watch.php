<?php
// includes/sections/app/watch.php

$videoUuid = trim($_GET['v'] ?? '');
$videoData = null;

if (!empty($videoUuid) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT v.title, v.description, v.hls_path, v.sprite_path, v.vtt_path, v.created_at, v.dominant_color,
                   u.username, u.avatar_path, u.uuid as user_uuid
            FROM videos v 
            JOIN users u ON v.user_id = u.id 
            WHERE v.uuid = ? AND v.status = 'published' 
            LIMIT 1
        ");
        $stmt->execute([$videoUuid]);
        $videoData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        die("ERROR SQL: " . $e->getMessage());
    }
}

$avatarUrl = '';
$domColorRgb = '255, 255, 255'; 

if ($videoData) {
    $avatarPath = $videoData['avatar_path'] ?? '';
    $projectRoot = realpath(__DIR__ . '/../../../'); 
    $cleanPath = ltrim($avatarPath, '/');
    $physicalPathPublic = $projectRoot . '/public/' . $cleanPath;
    $physicalPathRoot = $projectRoot . '/' . $cleanPath;

    if (!empty($avatarPath)) {
        if (file_exists($physicalPathPublic)) {
            $avatarUrl = $basePath . 'public/' . $cleanPath;
        } elseif (file_exists($physicalPathRoot)) {
            $avatarUrl = $basePath . $cleanPath;
        } else {
            $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($videoData['username']) . "&background=random&color=fff";
        }
    } else {
        $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($videoData['username']) . "&background=random&color=fff";
    }

    $hex = $videoData['dominant_color'] ?? '#ffffff';
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 6) {
        list($r, $g, $b) = sscanf($hex, "%02x%02x%02x");
        $domColorRgb = "$r, $g, $b";
    }
}
?>

<div class="component-wrapper component-wrapper--full" data-section="watch" style="max-width: 100%; padding: 0;">
    
    <?php if ($videoData): ?>
        <?php
        // [CORRECCIÓN] Leemos la cookie para aplicar el modo cine desde el servidor y evitar parpadeo
        $isCinemaMode = isset($_COOKIE['aurora_cinema_mode']) && $_COOKIE['aurora_cinema_mode'] === 'on';
        $cinemaClass = $isCinemaMode ? 'component-watch-mode-cinema' : '';
        ?>
        
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
                                <span>Desactivado</span>
                                <span class="material-symbols-rounded check-icon">check</span>
                            </div>
                            <div class="component-watch-settings-option" data-type="lighting" data-value="on">
                                <span>Activo</span>
                                <span class="material-symbols-rounded check-icon">check</span>
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
                                    <button id="play-pause-btn" class="component-watch-control-btn" title="Reproducir/Pausar">
                                        <span class="material-symbols-rounded">play_arrow</span>
                                    </button>
                                </div>
                                <div class="component-watch-control-pill component-watch-volume-container">
                                    <button id="mute-btn" class="component-watch-control-btn" title="Silenciar">
                                        <span class="material-symbols-rounded">volume_up</span>
                                    </button>
                                    <div class="component-watch-volume-wrapper">
                                        <input type="range" id="volume-bar" class="component-watch-volume-bar" min="0" max="1" step="0.05" value="1">
                                    </div>
                                </div>
                                <div class="component-watch-control-pill component-watch-timer-pill">
                                    <span id="current-time">0:00</span>
                                    <span class="component-watch-time-sep">/</span>
                                    <span id="duration">0:00</span>
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
                            <img src="<?php echo $avatarUrl; ?>" alt="Avatar" class="component-watch-avatar">
                            <div class="component-watch-author-text">
                                <span class="component-watch-username"><?php echo htmlspecialchars($videoData['username']); ?></span>
                                <span class="component-watch-subs">0 suscriptores</span>
                            </div>
                            <button class="component-button primary" style="border-radius: 20px; margin-left: 24px;">Suscribirse</button>
                        </div>
                        
                        <div class="component-watch-actions">
                            
                            <div class="component-watch-joined-pill">
                                <button class="component-watch-joined-btn like" title="Me gusta">
                                    <span class="material-symbols-rounded">thumb_up</span>
                                    <span>12</span>
                                </button>
                                <div class="component-watch-joined-separator"></div>
                                <button class="component-watch-joined-btn dislike" title="No me gusta">
                                    <span class="material-symbols-rounded">thumb_down</span>
                                </button>
                            </div>

                            <button class="component-button component-watch-action-pill">
                                <span class="material-symbols-rounded">share</span> Compartir
                            </button>

                            <button class="component-button component-watch-action-pill">
                                <span class="material-symbols-rounded">download</span> Descargar
                            </button>

                            <button class="component-button component-watch-action-pill">
                                <span class="material-symbols-rounded">bookmark</span> Guardar
                            </button>

                        </div>
                    </div>

                    <div class="component-watch-desc-box" style="--dominant-rgb: <?php echo $domColorRgb; ?>;">
                        <p class="component-watch-desc-meta">
                            0 visualizaciones • <?php echo date('d M Y', strtotime($videoData['created_at'])); ?>
                        </p>
                        <p class="component-watch-desc-text">
                            <?php echo nl2br(htmlspecialchars($videoData['description'] ?? '')); ?>
                        </p>
                    </div>
                </div>

                <div class="component-watch-comments">
                    <h3 style="font-size: 1.2rem; margin-bottom: 16px;">Comentarios</h3>
                    <div class="component-card" style="text-align: center; color: var(--text-secondary); padding: 40px;">
                        <span class="material-symbols-rounded" style="font-size: 32px; margin-bottom: 8px;">forum</span>
                        <p>Próximamente: Sección de comentarios</p>
                    </div>
                </div>

            </div>

            <div class="component-watch-col-right">
                <div class="component-card" style="text-align: center; color: var(--text-secondary); padding: 40px; min-height: 400px; display: flex; flex-direction: column; justify-content: center;">
                    <span class="material-symbols-rounded" style="font-size: 32px; margin-bottom: 8px;">playlist_play</span>
                    <p>Próximamente:<br>Videos relacionados</p>
                </div>
            </div>

        </div>

        <input type="hidden" id="watch-hls-source" value="<?php echo $basePath . $videoData['hls_path']; ?>">
        <input type="hidden" id="watch-sprite-source" value="<?php echo !empty($videoData['sprite_path']) ? $basePath . $videoData['sprite_path'] : ''; ?>">
        <input type="hidden" id="watch-vtt-source" value="<?php echo !empty($videoData['vtt_path']) ? $basePath . $videoData['vtt_path'] : ''; ?>">
        <input type="hidden" id="watch-video-uuid" value="<?php echo htmlspecialchars($videoUuid); ?>">

    <?php else: ?>
        <div class="component-layout-centered">
            <div class="component-card" style="text-align: center; padding: 40px;">
                <span class="material-symbols-rounded" style="font-size: 48px; color: var(--text-tertiary);">videocam_off</span>
                <h2 style="margin: 16px 0; color: var(--text-primary);">Video no disponible</h2>
                <p style="color: var(--text-secondary);">El video no existe o es privado.</p>
                <button class="component-button primary mt-16" onclick="window.history.back()">Volver</button>
            </div>
        </div>
    <?php endif; ?>

</div>