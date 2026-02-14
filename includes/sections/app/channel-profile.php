<?php
// includes/sections/app/channel-profile.php

global $basePath, $pdo, $viewingChannelUUID, $viewingChannelTab;

// 1. Validar UUID
if (!isset($viewingChannelUUID)) {
    include __DIR__ . '/../system/404.php';
    exit;
}

// 2. Definir Pestaña Actual
$allowedTabs = ['home', 'videos', 'shorts'];
$requestedTab = isset($viewingChannelTab) ? $viewingChannelTab : 'home';
$currentTab = in_array($requestedTab, $allowedTabs) ? $requestedTab : 'home';

// 3. Datos del Dueño (Necesarios tanto para SPA como Full Page para hacer la query)
$stmtChannel = $pdo->prepare("SELECT id, username, uuid, avatar_path, banner_path, account_status, subscribers_count FROM users WHERE uuid = ? LIMIT 1");
$stmtChannel->execute([$viewingChannelUUID]);
$channelOwner = $stmtChannel->fetch(PDO::FETCH_ASSOC);

if (!$channelOwner || $channelOwner['account_status'] === 'deleted') {
    // Si es SPA y no existe, devolvemos error simple
    if (isset($_GET['spf'])) { echo "<p>Canal no encontrado</p>"; exit; }
    // Si es full page, mostramos la UI de error
    $channelStatus = 'NOT_FOUND';
} else {
    $channelStatus = (isset($_SESSION['uuid']) && $_SESSION['uuid'] === $viewingChannelUUID) ? 'OWNER' : 'VISITOR';
}

// =================================================================================
// [SPA LOGIC] SI ES PETICIÓN PARCIAL, CARGAMOS EL FEED Y SALIMOS
// =================================================================================
$isPartialRequest = isset($_SERVER['HTTP_X_SPA_REQUEST']) || isset($_GET['spf']);

if ($isPartialRequest) {
    // Limpiamos todo el HTML previo (Header, Sidebar)
    while (ob_get_level() > 0) ob_end_clean();
    
    if ($channelStatus === 'NOT_FOUND') {
        echo "<p class='component-channel-empty'>Canal no encontrado.</p>";
    } else {
        // [AQUÍ] Incluimos el archivo unificado que filtra según $currentTab
        include __DIR__ . '/channel-feed.php';
    }
    exit; // Matamos el proceso
}
// =================================================================================

// --- CARGA COMPLETA (FULL PAGE) ---

// Helpers & Assets
$totalVideos = 0;
if ($channelOwner) {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE user_id = ? AND status = 'published'");
    $stmtCount->execute([$channelOwner['id']]);
    $totalVideos = $stmtCount->fetchColumn();
}

$avatarSrc = (isset($channelOwner['avatar_path']) && $channelOwner['avatar_path'])
    ? $basePath . $channelOwner['avatar_path'] 
    : $basePath . 'public/storage/profilePicture/default/default.png';

$bannerUrl = (isset($channelOwner['banner_path']) && $channelOwner['banner_path'])
    ? $basePath . $channelOwner['banner_path'] : null;

$bannerInlineStyle = $bannerUrl 
    ? "background-image: url('" . htmlspecialchars($bannerUrl) . "');" 
    : "background: linear-gradient(135deg, var(--sl-color-primary-800) 0%, var(--sl-color-primary-500) 100%);";

// Links
$linkHome = $basePath . 'c/' . $viewingChannelUUID;
$linkVideos = $basePath . 'c/videos/' . $viewingChannelUUID;
$linkShorts = $basePath . 'c/shorts/' . $viewingChannelUUID;

// Funciones Auxiliares
function getNearestSafeColorPHP($rawHex) {
    if (!$rawHex || $rawHex === '#000000') return '#202020';
    $palette = ['#cbd5e1', '#94a3b8', '#64748b', '#475569', '#334155', '#fca5a5', '#f87171', '#ef4444', '#dc2626', '#b91c1c', '#fdba74', '#fb923c', '#f97316', '#ea580c', '#c2410c', '#fcd34d', '#fbbf24', '#f59e0b', '#d97706', '#b45309', '#86efac', '#4ade80', '#22c55e', '#16a34a', '#15803d', '#6ee7b7', '#34d399', '#10b981', '#059669', '#047857', '#5eead4', '#2dd4bf', '#14b8a6', '#0d9488', '#0f766e', '#67e8f9', '#22d3ee', '#06b6d4', '#0891b2', '#0e7490', '#93c5fd', '#60a5fa', '#3b82f6', '#2563eb', '#1d4ed8', '#a5b4fc', '#818cf8', '#6366f1', '#4f46e5', '#4338ca', '#c4b5fd', '#a78bfa', '#8b5cf6', '#7c3aed', '#6d28d9', '#d8b4fe', '#c084fc', '#a855f7', '#9333ea', '#7e22ce', '#f0abfc', '#e879f9', '#d946ef', '#c026d3', '#a21caf', '#f9a8d4', '#f472b6', '#ec4899', '#db2777', '#be185d', '#fda4af', '#fb7185', '#f43f5e', '#e11d48', '#be123c'];
    list($r, $g, $b) = sscanf($rawHex, "#%02x%02x%02x");
    $minDistance = PHP_INT_MAX;
    $closestHex = '#64748b';
    foreach ($palette as $pHex) {
        list($pr, $pg, $pb) = sscanf($pHex, "#%02x%02x%02x");
        $dist = pow($pr - $r, 2) + pow($pg - $g, 2) + pow($pb - $b, 2);
        if ($dist < $minDistance) { $minDistance = $dist; $closestHex = $pHex; }
    }
    return $closestHex;
}
if (!function_exists('fmtDuration')) { function fmtDuration($seconds) { return gmdate($seconds > 3600 ? "H:i:s" : "i:s", $seconds); }}
if (!function_exists('timeAgo')) { function timeAgo($datetime) {
    $time = strtotime($datetime); $diff = time() - $time; if ($diff < 60) return 'hace unos segundos';
    $intervals = [31536000=>'año', 2592000=>'mes', 604800=>'semana', 86400=>'día', 3600=>'hora', 60=>'minuto'];
    foreach ($intervals as $secs => $str) { $d = $diff / $secs; if ($d >= 1) { $r = round($d); return 'hace '.$r.' '.$str.($r>1?'s':''); }}
    return 'hace poco';
}}
?>

<?php if ($channelStatus === 'NOT_FOUND'): ?>
    <div class="component-channel-not-found">
        <h1 class="component-channel-not-found-title">🚫 Canal no encontrado</h1>
        <p class="component-channel-not-found-text">Este canal no existe o fue eliminado.</p>
        <a href="<?php echo $basePath; ?>" class="btn btn-primary component-channel-btn-back">Volver al inicio</a>
    </div>
<?php else: ?>

<div class="component-channel-wrapper">
    <div class="component-channel-container">
        
        <input type="file" id="banner-upload-input" accept="image/*" style="display: none;">
        <div id="channel-banner-display" class="component-channel-banner" style="<?php echo $bannerInlineStyle; ?>">
            <?php if ($channelStatus === 'OWNER'): ?>
                <button id="btn-trigger-banner" class="component-channel-banner-edit-btn">
                    <span class="material-symbols-rounded">camera_alt</span> Editar banner
                </button>
            <?php endif; ?>
        </div>

        <div class="component-channel-header">
            <div class="component-channel-avatar-container">
                <img src="<?php echo htmlspecialchars($avatarSrc); ?>" class="component-channel-avatar-img">
            </div>
            <div class="component-channel-info-row">
                <div>
                    <div class="component-channel-identity">
                        <h1><?php echo htmlspecialchars($channelOwner['username']); ?><span class="material-symbols-rounded component-channel-verified-icon">verified</span></h1>
                    </div>
                    <div class="component-channel-meta">
                        <span>@<?php echo htmlspecialchars($channelOwner['username']); ?></span>•
                        <span><?php echo number_format($channelOwner['subscribers_count']); ?> suscriptores</span>•
                        <span><?php echo number_format($totalVideos); ?> videos</span>
                    </div>
                    <p class="component-channel-description">Bienvenido a mi canal oficial en Project Aurora.</p>
                </div>
                <div class="component-channel-actions">
                    <?php if ($channelStatus === 'OWNER'): ?>
                        <button class="component-channel-btn component-channel-btn-secondary">Personalizar</button>
                        <a href="<?php echo $basePath; ?>s/channel/upload" class="component-channel-btn component-channel-btn-primary">Gestionar videos</a>
                    <?php else: ?>
                        <button class="component-channel-btn component-channel-btn-subscribe">Suscribirse</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="component-channel-tabs">
            <a href="<?php echo $linkHome; ?>" class="component-channel-tab-link spa-tab-link <?php echo $currentTab === 'home' ? 'active' : ''; ?>">Principal</a>
            <a href="<?php echo $linkVideos; ?>" class="component-channel-tab-link spa-tab-link <?php echo $currentTab === 'videos' ? 'active' : ''; ?>">Videos</a>
            <a href="<?php echo $linkShorts; ?>" class="component-channel-tab-link spa-tab-link <?php echo $currentTab === 'shorts' ? 'active' : ''; ?>">Shorts</a>
        </div>
        
        <div class="component-channel-content-area" id="channel-feed-grid">
            <?php include __DIR__ . '/channel-feed.php'; ?>
        </div>
    </div> </div>

<div id="banner-modal" class="component-channel-modal-overlay">
    <div class="component-channel-modal-content">
        <div class="component-channel-modal-header">
            <h3 class="component-channel-modal-title">Personalización del Banner</h3>
            <p class="component-channel-modal-subtitle">Arrastra y haz zoom para ajustar tu banner.</p>
        </div>
        <div class="component-channel-crop-area" id="crop-container">
            <img id="banner-preview-image" class="component-channel-preview-img" src="" alt="Vista previa" draggable="false">
            <div class="component-channel-safe-zone">
                <div class="component-channel-zone-darkener"></div>
                <div class="component-channel-zone-desktop">
                    <span class="component-channel-zone-label">TV</span>
                    <div class="component-channel-zone-all-devices">VISIBLE EN TODOS</div>
                </div>
                <div class="component-channel-zone-darkener"></div>
            </div>
        </div>
        <div class="component-channel-controls">
            <div class="component-channel-zoom-control">
                <span class="material-symbols-rounded">zoom_out</span>
                <input type="range" id="zoom-slider" class="component-channel-zoom-slider" min="1" max="3" step="0.05" value="1">
                <span class="material-symbols-rounded">zoom_in</span>
            </div>
            <div class="component-channel-control-btns">
                <button id="btn-cancel-crop" class="component-channel-btn-cancel">Cancelar</button>
                <button id="btn-save-crop" class="component-channel-btn-save">Hecho</button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>