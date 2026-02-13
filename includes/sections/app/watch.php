<?php
// includes/sections/app/watch.php

$videoUuid = trim($_GET['v'] ?? '');
$videoData = null;

if (!empty($videoUuid) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT v.title, v.description, v.hls_path, v.created_at, 
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
if ($videoData) {
    if (!empty($videoData['avatar_path']) && file_exists(__DIR__ . '/../../../../' . $videoData['avatar_path'])) {
        $avatarUrl = $basePath . $videoData['avatar_path'];
    } else {
        $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($videoData['username']) . "&background=random&color=fff";
    }
}
?>

<div class="component-wrapper component-wrapper--full" data-section="watch" style="max-width: 100%; padding: 0;">
    
    <?php if ($videoData): ?>
        <div class="watch-layout">
            
            <div class="watch-left">
                
                <div class="watch-player-card" id="video-container">
                    <div class="video-player-wrapper">
                        <video id="main-player" playsinline poster="" class="video-element"></video>
                    </div>

                    <div id="settings-popover" class="settings-popover">
                        
                        <div id="settings-main" class="settings-panel active">
                            <div class="settings-item" data-target="lighting">
                                <span class="material-symbols-rounded icon-left">light_mode</span>
                                <span class="setting-label">Iluminación cinematográfica</span>
                                <span class="setting-value" id="lighting-status-text">Desactivado</span>
                                <span class="material-symbols-rounded icon-right">chevron_right</span>
                            </div>
                            <div class="settings-item" data-target="quality">
                                <span class="material-symbols-rounded icon-left">tune</span>
                                <span class="setting-label">Calidad</span>
                                <span class="setting-value" id="quality-status-text">Auto</span>
                                <span class="material-symbols-rounded icon-right">chevron_right</span>
                            </div>
                        </div>

                        <div id="settings-lighting" class="settings-panel">
                            <div class="settings-header" data-back="main">
                                <span class="material-symbols-rounded">arrow_back</span>
                                <span>Iluminación cinematográfica</span>
                            </div>
                            <div class="settings-option selected" data-type="lighting" data-value="off">
                                <span>Desactivado</span>
                                <span class="material-symbols-rounded check-icon">check</span>
                            </div>
                            <div class="settings-option" data-type="lighting" data-value="on">
                                <span>Activo</span>
                                <span class="material-symbols-rounded check-icon">check</span>
                            </div>
                        </div>

                        <div id="settings-quality" class="settings-panel">
                            <div class="settings-header" data-back="main">
                                <span class="material-symbols-rounded">arrow_back</span>
                                <span>Calidad</span>
                            </div>
                            <div id="quality-options-container">
                                </div>
                        </div>

                    </div>
                    <div class="custom-controls" id="custom-controls">
                        
                        <div class="progress-container">
                            <input type="range" id="seek-bar" class="seek-bar" value="0" min="0" step="0.1">
                        </div>

                        <div class="controls-row">
                            
                            <div class="controls-left">
                                <div class="control-pill">
                                    <button id="play-pause-btn" class="control-btn" title="Reproducir/Pausar">
                                        <span class="material-symbols-rounded">play_arrow</span>
                                    </button>
                                </div>

                                <div class="control-pill volume-pill-container">
                                    <button id="mute-btn" class="control-btn" title="Silenciar">
                                        <span class="material-symbols-rounded">volume_up</span>
                                    </button>
                                    <div class="volume-slider-wrapper">
                                        <input type="range" id="volume-bar" class="volume-bar" min="0" max="1" step="0.05" value="1">
                                    </div>
                                </div>

                                <div class="control-pill timer-pill">
                                    <span id="current-time">0:00</span>
                                    <span class="time-separator">/</span>
                                    <span id="duration">0:00</span>
                                </div>
                            </div>

                            <div class="controls-right">
                                <div class="control-pill group-pill">
                                    <button id="settings-btn" class="control-btn" title="Configuración">
                                        <span class="material-symbols-rounded">settings</span>
                                    </button>
                                    <button class="control-btn" title="Modo Cine">
                                        <span class="material-symbols-rounded">crop_landscape</span>
                                    </button>
                                    <button id="fullscreen-btn" class="control-btn" title="Pantalla Completa">
                                        <span class="material-symbols-rounded">fullscreen</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="watch-meta-card">
                    <h1 class="watch-title"><?php echo htmlspecialchars($videoData['title']); ?></h1>
                    
                    <div class="watch-author-row">
                        <div class="watch-author-info">
                            <img src="<?php echo $avatarUrl; ?>" alt="Avatar" class="watch-avatar">
                            <div class="watch-author-text">
                                <span class="watch-username"><?php echo htmlspecialchars($videoData['username']); ?></span>
                                <span class="watch-subs">0 suscriptores</span>
                            </div>
                            <button class="component-button primary" style="border-radius: 20px; margin-left: 24px;">Suscribirse</button>
                        </div>
                        <div class="watch-actions">
                            <button class="component-button" style="border-radius: 20px;">
                                <span class="material-symbols-rounded">thumb_up</span> 0
                            </button>
                            <button class="component-button" style="border-radius: 20px;">
                                <span class="material-symbols-rounded">share</span> Compartir
                            </button>
                        </div>
                    </div>

                    <div class="watch-description-box">
                        <p class="watch-views-date">
                            0 visualizaciones • <?php echo date('d M Y', strtotime($videoData['created_at'])); ?>
                        </p>
                        <p class="watch-desc-text">
                            <?php echo nl2br(htmlspecialchars($videoData['description'] ?? '')); ?>
                        </p>
                    </div>
                </div>

                <div class="watch-comments-section">
                    <h3 style="font-size: 1.2rem; margin-bottom: 16px;">Comentarios</h3>
                    <div class="component-card" style="text-align: center; color: var(--text-secondary); padding: 40px;">
                        <span class="material-symbols-rounded" style="font-size: 32px; margin-bottom: 8px;">forum</span>
                        <p>Próximamente: Sección de comentarios</p>
                    </div>
                </div>

            </div>

            <div class="watch-right">
                <div class="component-card" style="text-align: center; color: var(--text-secondary); padding: 40px; min-height: 400px; display: flex; flex-direction: column; justify-content: center;">
                    <span class="material-symbols-rounded" style="font-size: 32px; margin-bottom: 8px;">playlist_play</span>
                    <p>Próximamente:<br>Videos relacionados</p>
                </div>
            </div>

        </div>

        <input type="hidden" id="watch-hls-source" value="<?php echo $basePath . $videoData['hls_path']; ?>">
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

<style>
/* =========================================
   ESTILOS REPRODUCTOR + SETTINGS
   ========================================= */

.watch-player-card {
    position: relative;
    background-color: #000;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    aspect-ratio: 16 / 9;
}

.video-player-wrapper, .video-element {
    width: 100%;
    height: 100%;
    object-fit: contain;
    cursor: pointer;
}

/* --- OVERLAY DE CONTROLES --- */
.custom-controls {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
    padding: 0 12px 12px 12px;
    opacity: 0;
    transition: opacity 0.3s ease;
    display: flex;
    flex-direction: column;
    gap: 8px;
    z-index: 20; /* Por encima del video, debajo del popover si fuera necesario */
}

.watch-player-card:hover .custom-controls,
.custom-controls.show {
    opacity: 1;
}

/* --- BARRA DE PROGRESO --- */
.progress-container {
    width: 100%;
    height: 14px;
    display: flex;
    align-items: center;
    cursor: pointer;
}

.seek-bar {
    -webkit-appearance: none;
    width: 100%;
    height: 3px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
    cursor: pointer;
    transition: height 0.1s ease;
    background-image: linear-gradient(#ff0000, #ff0000);
    background-size: 0% 100%;
    background-repeat: no-repeat;
}

.progress-container:hover .seek-bar {
    height: 5px;
}

.seek-bar::-webkit-slider-thumb {
    -webkit-appearance: none;
    height: 12px;
    width: 12px;
    border-radius: 50%;
    background: #ff0000;
    cursor: pointer;
    transform: scale(0);
    transition: transform 0.1s ease;
}

.progress-container:hover .seek-bar::-webkit-slider-thumb {
    transform: scale(1);
}

/* --- CONTROLES --- */
.controls-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.controls-left, .controls-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.control-pill {
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    border-radius: 999px;
    padding: 2px;
    display: flex;
    align-items: center;
    height: 40px;
    position: relative; /* Para el settings popover positioning relativo si se quisiera */
}

.group-pill {
    gap: 2px;
}

.control-btn {
    background: transparent;
    border: none;
    color: #fff;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.control-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

.control-btn span {
    font-size: 24px;
}

.timer-pill {
    padding: 0 16px;
    color: #fff;
    font-size: 0.85rem;
    font-family: monospace;
    font-weight: 500;
}

.time-separator {
    margin: 0 4px;
    opacity: 0.7;
}

.volume-pill-container {
    padding-right: 0;
    transition: all 0.3s ease;
    overflow: hidden;
}

.volume-slider-wrapper {
    width: 0;
    overflow: hidden;
    transition: width 0.3s ease, padding 0.3s ease;
    display: flex;
    align-items: center;
}

.volume-pill-container:hover .volume-slider-wrapper,
.volume-slider-wrapper:active {
    width: 80px;
    padding-right: 12px;
}

.volume-bar {
    -webkit-appearance: none;
    width: 100%;
    height: 3px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
    cursor: pointer;
}

.volume-bar::-webkit-slider-thumb {
    -webkit-appearance: none;
    height: 10px;
    width: 10px;
    border-radius: 50%;
    background: #fff;
    cursor: pointer;
}

/* =========================================
   SETTINGS POPOVER (NUEVO)
   ========================================= */

.settings-popover {
    position: absolute;
    bottom: 60px; /* Encima de los controles */
    right: 24px; /* Alineado a la derecha */
    width: 280px;
    background-color: rgba(28, 28, 28, 0.95);
    backdrop-filter: blur(8px);
    border-radius: 12px;
    padding: 8px 0;
    display: none; /* Oculto por defecto */
    flex-direction: column;
    z-index: 30;
    color: #fff;
    font-size: 0.9rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.5);
    transition: height 0.2s ease;
}

.settings-popover.active {
    display: flex;
}

/* Paneles (Main, Quality, Lighting) */
.settings-panel {
    display: none;
    flex-direction: column;
    width: 100%;
}

.settings-panel.active {
    display: flex;
    animation: fadeInPanel 0.2s ease;
}

@keyframes fadeInPanel {
    from { opacity: 0; transform: translateX(10px); }
    to { opacity: 1; transform: translateX(0); }
}

/* Items del menú principal */
.settings-item {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    cursor: pointer;
    transition: background 0.2s;
    height: 48px;
}

.settings-item:hover {
    background-color: rgba(255,255,255,0.1);
}

.settings-item .icon-left {
    margin-right: 12px;
    font-size: 20px;
}

.settings-item .setting-label {
    flex: 1;
}

.settings-item .setting-value {
    color: #aaa;
    margin-right: 8px;
    font-size: 0.85rem;
}

.settings-item .icon-right {
    color: #aaa;
    font-size: 18px;
}

/* Cabecera de submenús */
.settings-header {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    margin-bottom: 4px;
    cursor: pointer;
    font-weight: 500;
}

.settings-header:hover {
    background-color: rgba(255,255,255,0.1);
}

.settings-header span:first-child {
    margin-right: 12px;
    font-size: 20px;
}

/* Opciones de submenús (Checkable) */
.settings-option {
    display: flex;
    align-items: center;
    padding: 10px 36px 10px 16px; /* Padding derecho para el check */
    cursor: pointer;
    position: relative;
    font-size: 0.9rem;
}

.settings-option:hover {
    background-color: rgba(255,255,255,0.1);
}

.settings-option.selected {
    font-weight: 600;
}

.settings-option .check-icon {
    position: absolute;
    right: 16px;
    font-size: 18px;
    display: none;
    color: #fff;
}

.settings-option.selected .check-icon {
    display: block;
}

/* --- LAYOUT GENERAL (Mismo de antes) --- */
.watch-layout { display: flex; gap: 24px; width: 100%; max-width: 1700px; margin: 0 auto; padding: 24px; align-items: flex-start; }
.watch-left { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 16px; }
.watch-meta-card { display: flex; flex-direction: column; gap: 12px; }
.watch-title { font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.watch-author-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
.watch-author-info { display: flex; align-items: center; gap: 12px; }
.watch-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
.watch-author-text { display: flex; flex-direction: column; }
.watch-username { font-weight: 600; font-size: 1rem; color: var(--text-primary); }
.watch-subs { font-size: 0.8rem; color: var(--text-secondary); }
.watch-actions { display: flex; gap: 8px; }
.watch-description-box { background-color: var(--bg-hover-light); border-radius: 12px; padding: 12px; font-size: 0.9rem; color: var(--text-primary); margin-top: 4px; }
.watch-views-date { font-weight: 600; font-size: 0.85rem; color: var(--text-primary); margin-bottom: 8px; }
.watch-desc-text { white-space: pre-wrap; line-height: 1.5; color: var(--text-secondary); }
.watch-right { width: 400px; flex-shrink: 0; display: flex; flex-direction: column; gap: 16px; }
@media (max-width: 1000px) {
    .watch-layout { flex-direction: column; padding: 0; }
    .watch-left { width: 100%; }
    .watch-right { width: 100%; padding: 0 16px; }
    .watch-player-card { border-radius: 0; }
    .watch-meta-card { padding: 0 16px; }
    .watch-comments-section { padding: 0 16px 24px 16px; }
}
</style>