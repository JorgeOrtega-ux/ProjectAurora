<?php
// includes/sections/app/channel-profile.php

// 1. Validar UUID
if (!isset($viewingChannelUUID)) {
    include __DIR__ . '/../system/404.php';
    exit;
}

// 2. Lógica de Pestañas
$allowedTabs = ['home', 'videos', 'shorts'];
$currentTab = isset($_GET['tab']) && in_array($_GET['tab'], $allowedTabs) ? $_GET['tab'] : 'home';

// 3. Datos del Dueño
$stmtChannel = $pdo->prepare("SELECT id, username, uuid, avatar_path, account_status, subscribers_count FROM users WHERE uuid = ? LIMIT 1");
$stmtChannel->execute([$viewingChannelUUID]);
$channelOwner = $stmtChannel->fetch(PDO::FETCH_ASSOC);

// 4. Estado
$channelStatus = 'NOT_FOUND'; 
if ($channelOwner) {
    if ($channelOwner['account_status'] === 'deleted') {
        $channelStatus = 'NOT_FOUND';
    } elseif (isset($_SESSION['uuid']) && $_SESSION['uuid'] === $viewingChannelUUID) {
        $channelStatus = 'OWNER';
    } else {
        $channelStatus = 'VISITOR';
    }
}

// 5. Conteo Videos
$totalVideos = 0;
if ($channelOwner) {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE user_id = ? AND status = 'published'");
    $stmtCount->execute([$channelOwner['id']]);
    $totalVideos = $stmtCount->fetchColumn();
}

$avatarSrc = (isset($channelOwner['avatar_path']) && $channelOwner['avatar_path'])
    ? $basePath . $channelOwner['avatar_path'] 
    : $basePath . 'public/storage/profilePicture/default/default.png';

$bannerStyle = "background: linear-gradient(135deg, var(--sl-color-primary-800) 0%, var(--sl-color-primary-500) 100%);";

// Helpers PHP
function getNearestSafeColorPHP($rawHex) {
    if (!$rawHex || $rawHex === '#000000') return '#202020';
    $palette = [
        '#cbd5e1', '#94a3b8', '#64748b', '#475569', '#334155', 
        '#fca5a5', '#f87171', '#ef4444', '#dc2626', '#b91c1c', 
        '#fdba74', '#fb923c', '#f97316', '#ea580c', '#c2410c', 
        '#fcd34d', '#fbbf24', '#f59e0b', '#d97706', '#b45309', 
        '#86efac', '#4ade80', '#22c55e', '#16a34a', '#15803d', 
        '#6ee7b7', '#34d399', '#10b981', '#059669', '#047857', 
        '#5eead4', '#2dd4bf', '#14b8a6', '#0d9488', '#0f766e', 
        '#67e8f9', '#22d3ee', '#06b6d4', '#0891b2', '#0e7490', 
        '#93c5fd', '#60a5fa', '#3b82f6', '#2563eb', '#1d4ed8', 
        '#a5b4fc', '#818cf8', '#6366f1', '#4f46e5', '#4338ca', 
        '#c4b5fd', '#a78bfa', '#8b5cf6', '#7c3aed', '#6d28d9', 
        '#d8b4fe', '#c084fc', '#a855f7', '#9333ea', '#7e22ce', 
        '#f0abfc', '#e879f9', '#d946ef', '#c026d3', '#a21caf', 
        '#f9a8d4', '#f472b6', '#ec4899', '#db2777', '#be185d', 
        '#fda4af', '#fb7185', '#f43f5e', '#e11d48', '#be123c'  
    ];
    list($r, $g, $b) = sscanf($rawHex, "#%02x%02x%02x");
    $minDistance = PHP_INT_MAX;
    $closestHex = '#64748b';
    foreach ($palette as $pHex) {
        list($pr, $pg, $pb) = sscanf($pHex, "#%02x%02x%02x");
        $dist = pow($pr - $r, 2) + pow($pg - $g, 2) + pow($pb - $b, 2);
        if ($dist < $minDistance) {
            $minDistance = $dist;
            $closestHex = $pHex;
        }
    }
    return $closestHex;
}
if (!function_exists('fmtDuration')) {
    function fmtDuration($seconds) { return gmdate($seconds > 3600 ? "H:i:s" : "i:s", $seconds); }
}
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60) return 'hace unos segundos';
        $intervals = [31536000=>'año', 2592000=>'mes', 604800=>'semana', 86400=>'día', 3600=>'hora', 60=>'minuto'];
        foreach ($intervals as $secs => $str) {
            $d = $diff / $secs;
            if ($d >= 1) { $r = round($d); return 'hace '.$r.' '.$str.($r>1?'s':''); }
        }
        return 'hace poco';
    }
}
?>

<?php if ($channelStatus === 'NOT_FOUND'): ?>
    <div style="padding: 4rem; text-align: center;">
        <h1>🚫 Canal no encontrado</h1>
        <p style="color: var(--text-secondary);">Este canal no existe o fue eliminado.</p>
        <a href="<?php echo $basePath; ?>" class="btn btn-primary" style="margin-top: 1rem;">Volver al inicio</a>
    </div>
<?php else: ?>

<style>
    .banner-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.85); z-index: 9999;
        display: none; align-items: center; justify-content: center;
        opacity: 0; transition: opacity 0.3s ease;
    }
    .banner-modal-overlay.active { display: flex; opacity: 1; }
    
    .banner-modal-content {
        background: var(--bg-surface); width: 90%; max-width: 900px;
        border-radius: var(--radius-md); overflow: hidden;
        display: flex; flex-direction: column;
        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
    }
    
    .banner-crop-area {
        position: relative; width: 100%; height: 450px; background: #121212;
        overflow: hidden; display: flex; align-items: center; justify-content: center;
        cursor: grab; /* Cursor de mano abierta */
        user-select: none; /* Evitar selección azul al arrastrar */
    }
    
    .banner-crop-area:active {
        cursor: grabbing; /* Cursor de mano cerrada al arrastrar */
    }
    
    .banner-preview-img {
        /* [IMPORTANTE] Ajuste inicial para ver toda la foto */
        max-height: 100%; 
        width: auto;
        
        transition: transform 0.05s linear; /* Transición rápida para suavidad */
        transform-origin: center;
        pointer-events: none; /* Dejar pasar clicks al contenedor para el drag */
        will-change: transform;
    }
    
    /* ZONAS SEGURAS (GUIDES) */
    .safe-zone-overlay {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        pointer-events: none; /* Click through */
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        z-index: 10; /* Encima de la imagen */
    }
    
    .zone-tv-label {
        position: absolute; top: 10px; left: 10px; 
        color: rgba(255,255,255,0.5); font-size: 0.8rem; font-weight: bold;
        text-shadow: 0 1px 2px black;
    }

    .zone-desktop {
        width: 100%; height: 30%; /* Aprox ratio */
        border-top: 1px dashed rgba(255,255,255,0.3);
        border-bottom: 1px dashed rgba(255,255,255,0.3);
        background: rgba(255, 255, 255, 0.05);
        display: flex; align-items: center; justify-content: center;
        position: relative;
    }
    
    .zone-all-devices {
        width: 1546px; max-width: 80%;
        height: 100%;
        background: rgba(0, 255, 0, 0.1);
        border-left: 1px solid rgba(255,255,255,0.5);
        border-right: 1px solid rgba(255,255,255,0.5);
        display: flex; align-items: center; justify-content: center;
        color: rgba(255,255,255,0.9); font-weight: bold; font-size: 0.9rem;
        text-shadow: 0 1px 3px black;
    }
    
    .banner-controls {
        padding: 1.5rem; border-top: 1px solid var(--border-color);
        display: flex; justify-content: space-between; align-items: center;
        background: var(--bg-surface);
        z-index: 20;
    }
    
    .zoom-control { display: flex; align-items: center; gap: 10px; color: var(--text-secondary); }
    .zoom-slider { width: 200px; accent-color: var(--text-primary); cursor: pointer; }
    .zone-darkener { flex: 1; width: 100%; background: rgba(0,0,0,0.6); }
</style>

<div class="channel-profile-wrapper" style="width: 100%; height: 100%; overflow-x: hidden; padding: 1.5rem;">

    <input type="file" id="banner-upload-input" accept="image/*" style="display: none;">

    <div id="channel-banner-display" class="channel-banner" style="width: 100%; height: 220px; <?php echo $bannerStyle; ?> background-size: cover; background-position: center; position: relative; border-radius: var(--radius-md);">
        <?php if ($channelStatus === 'OWNER'): ?>
            <button id="btn-trigger-banner" style="position: absolute; bottom: 1rem; right: 1rem; background: rgba(0,0,0,0.6); color: white; border: none; padding: 8px 12px; border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; gap: 5px; transition: background 0.2s;">
                <span class="material-symbols-rounded">camera_alt</span> Editar banner
            </button>
        <?php endif; ?>
    </div>

    <div style="max-width: 1200px; margin: 0 auto; padding: 0 1rem;">
        <div style="display: flex; flex-direction: column; align-items: flex-start;">
            <div class="channel-avatar-container" style="width: 160px; height: 160px; margin-top: -80px; position: relative; z-index: 2;">
                <img src="<?php echo htmlspecialchars($avatarSrc); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: var(--radius-md); border: 5px solid var(--bg-app); background-color: var(--bg-app); box-shadow: var(--shadow-card);">
            </div>
            <div style="margin-top: 1rem; width: 100%; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="font-size: 2rem; font-weight: 700; color: var(--text-primary); margin: 0;">
                        <?php echo htmlspecialchars($channelOwner['username']); ?>
                        <span class="material-symbols-rounded" style="font-size: 1.2rem; vertical-align: middle; color: var(--sl-color-primary-500); margin-left: 4px;">verified</span>
                    </h1>
                    <div style="color: var(--text-secondary); font-size: 0.95rem; margin-top: 0.5rem; display: flex; gap: 10px;">
                        <span>@<?php echo htmlspecialchars($channelOwner['username']); ?></span>•
                        <span><?php echo number_format($channelOwner['subscribers_count']); ?> suscriptores</span>•
                        <span><?php echo number_format($totalVideos); ?> videos</span>
                    </div>
                    <p style="color: var(--text-tertiary); margin-top: 1rem; max-width: 600px; font-size: 0.95rem;">Bienvenido a mi canal oficial en Project Aurora.</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <?php if ($channelStatus === 'OWNER'): ?>
                        <button class="btn" style="background: var(--sl-color-neutral-200); color: var(--text-primary); padding: 10px 20px; border-radius: 20px; font-weight: 600;">Personalizar</button>
                        <a href="<?php echo $basePath; ?>s/channel/upload" class="btn" style="background: var(--text-primary); color: var(--bg-app); padding: 10px 20px; border-radius: 20px; font-weight: 600; text-decoration: none;">Gestionar videos</a>
                    <?php else: ?>
                        <button class="btn" style="background: var(--text-primary); color: var(--bg-app); padding: 10px 24px; border-radius: 24px; font-weight: 600;">Suscribirse</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php 
            function getTabStyle($isActive) {
                return $isActive 
                    ? "padding-bottom: 0.8rem; color: var(--text-primary); font-weight: 600; text-decoration: none; border-bottom: 3px solid var(--text-primary); text-transform: uppercase;"
                    : "padding-bottom: 0.8rem; color: var(--text-secondary); font-weight: 500; text-decoration: none; border-bottom: 3px solid transparent; text-transform: uppercase;";
            }
        ?>
        <div style="margin-top: 2rem; border-bottom: 1px solid var(--border-color); display: flex; gap: 2rem;">
            <a href="?tab=home" style="<?php echo getTabStyle($currentTab === 'home'); ?>">Principal</a>
            <a href="?tab=videos" style="<?php echo getTabStyle($currentTab === 'videos'); ?>">Videos</a>
            <a href="?tab=shorts" style="<?php echo getTabStyle($currentTab === 'shorts'); ?>">Shorts</a>
        </div>
        
        <div class="channel-content-area" id="channel-feed-grid" style="min-height: 300px;">
            <?php 
            $fileToInclude = __DIR__ . '/channel-tabs/' . $currentTab . '.php';
            if (file_exists($fileToInclude)) include $fileToInclude;
            else echo "<p style='padding: 2rem;'>Error al cargar sección.</p>";
            ?>
        </div>
    </div>
</div>

<div id="banner-modal" class="banner-modal-overlay">
    <div class="banner-modal-content">
        <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color);">
            <h3 style="margin: 0; color: var(--text-primary);">Personalización del Banner</h3>
            <p style="margin: 0; color: var(--text-secondary); font-size: 0.9rem;">Arrastra y haz zoom para ajustar tu banner.</p>
        </div>
        
        <div class="banner-crop-area" id="crop-container">
            <img id="banner-preview-image" class="banner-preview-img" src="" alt="Vista previa" draggable="false">
            
            <div class="safe-zone-overlay">
                <div class="zone-darkener"></div>
                <div class="zone-desktop">
                    <span class="zone-tv-label">TV</span>
                    <div class="zone-all-devices">VISIBLE EN TODOS</div>
                </div>
                <div class="zone-darkener"></div>
            </div>
        </div>
        
        <div class="banner-controls">
            <div class="zoom-control">
                <span class="material-symbols-rounded">zoom_out</span>
                <input type="range" id="zoom-slider" class="zoom-slider" min="1" max="3" step="0.05" value="1">
                <span class="material-symbols-rounded">zoom_in</span>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button id="btn-cancel-crop" class="btn" style="background: transparent; border: 1px solid var(--border-color); color: var(--text-primary); padding: 8px 16px; border-radius: 6px; cursor: pointer;">Cancelar</button>
                <button id="btn-save-crop" class="btn" style="background: var(--text-primary); color: var(--bg-app); border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 600;">Hecho</button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>