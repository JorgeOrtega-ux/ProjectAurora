<?php
// includes/sections/app/channel-feed.php

// Asegurar que tenemos las variables necesarias
if (!isset($channelOwner['id']) || !isset($pdo) || !isset($currentTab)) return;

// 1. Preparar Query según el Tab
$sql = "SELECT uuid, title, thumbnail_path, views_count, created_at, duration, orientation, hls_path, dominant_color 
        FROM videos WHERE user_id = ? AND status = 'published'";

$params = [$channelOwner['id']];

switch ($currentTab) {
    case 'videos':
        $sql .= " AND orientation = 'landscape'";
        $emptyMessage = "Aún no hay videos largos.";
        $emptyIcon = "movie";
        break;
    case 'shorts':
        $sql .= " AND orientation = 'portrait'";
        $emptyMessage = "Aún no hay Shorts.";
        $emptyIcon = "smartphone";
        break;
    case 'home':
    default:
        // Home muestra todo
        $emptyMessage = "Este canal aún no tiene videos.";
        $emptyIcon = "video_library";
        break;
}

$sql .= " ORDER BY created_at DESC";

// 2. Ejecutar
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Renderizar
?>

<div class="channel-tab-content fade-in">
    <?php if ($currentTab === 'home'): ?>
        <h3 class="component-channel-section-title">Subidas recientes</h3>
    <?php endif; ?>

    <?php if (empty($videos)): ?>
        <div class="component-channel-empty">
            <span class="material-symbols-rounded component-channel-empty-icon"><?php echo $emptyIcon; ?></span>
            <p><?php echo $emptyMessage; ?></p>
        </div>
    <?php else: ?>
        
        <div class="component-channel-grid <?php echo ($currentTab === 'shorts') ? 'grid-shorts' : ''; ?>" 
             style="<?php echo ($currentTab === 'shorts') ? 'grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));' : ''; ?>">
             
            <?php foreach ($videos as $video): ?>
                <?php 
                    $isShort = $video['orientation'] === 'portrait';
                    $thumb = $video['thumbnail_path'] ? $basePath . $video['thumbnail_path'] : $basePath . 'public/assets/img/no-thumb.jpg';
                    // Si no está definida la función de color (SPA partial load), usamos default
                    $safeColor = function_exists('getNearestSafeColorPHP') ? getNearestSafeColorPHP($video['dominant_color']) : '#202020';
                ?>
                
                <div class="video-card <?php echo $isShort ? 'video-card--portrait' : ''; ?>" 
                     style="--dynamic-base: <?php echo $safeColor; ?>; cursor: pointer;"
                     data-uuid="<?php echo $video['uuid']; ?>"
                     data-hls="<?php echo $video['hls_path']; ?>"
                     data-orientation="<?php echo $video['orientation']; ?>"
                >
                    <div class="video-top">
                        <img src="<?php echo $thumb; ?>" loading="lazy" class="video-thumb-img" 
                             style="width: 100%; height: 100%; object-fit: <?php echo $isShort ? 'contain' : 'cover'; ?>;">
                        
                        <?php if (!$isShort): ?>
                            <div class="video-duration">
                                <?php echo function_exists('fmtDuration') ? fmtDuration($video['duration']) : gmdate("i:s", $video['duration']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="video-bottom">
                        <div class="video-meta" style="margin-left: 0;"> 
                            <h3 class="video-title" title="<?php echo htmlspecialchars($video['title']); ?>">
                                <?php echo htmlspecialchars($video['title']); ?>
                            </h3>
                            <div class="video-info">
                                <div class="video-stats">
                                    <span><?php echo number_format($video['views_count']); ?> vistas</span>
                                    <?php if (!$isShort): ?>
                                        <span><?php echo function_exists('timeAgo') ? timeAgo($video['created_at']) : 'reciente'; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>