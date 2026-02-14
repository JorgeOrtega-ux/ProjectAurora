<?php
// includes/sections/app/channel-tabs/shorts.php
if (!isset($channelOwner['id'])) return;

$stmt = $pdo->prepare("
    SELECT uuid, title, thumbnail_path, views_count, created_at, duration, orientation, hls_path, dominant_color 
    FROM videos 
    WHERE user_id = ? AND status = 'published' AND orientation = 'portrait'
    ORDER BY created_at DESC
");
$stmt->execute([$channelOwner['id']]);
$shorts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="padding: 1.5rem 0;">
    <?php if (empty($shorts)): ?>
        <div style="text-align: center; padding: 3rem 0; color: var(--text-tertiary);">
            <span class="material-symbols-rounded" style="font-size: 48px;">smartphone</span>
            <p>Aún no hay Shorts.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px;">
            <?php foreach ($shorts as $short): ?>
                <?php 
                    $thumb = $short['thumbnail_path'] ? $basePath . $short['thumbnail_path'] : '';
                    $safeColor = getNearestSafeColorPHP($short['dominant_color']);
                ?>
                <div class="video-card video-card--portrait" 
                     style="--dynamic-base: <?php echo $safeColor; ?>; cursor: pointer;"
                     data-uuid="<?php echo $short['uuid']; ?>"
                     data-hls="<?php echo $short['hls_path']; ?>"
                     data-orientation="portrait"
                >
                    <div class="video-top">
                        <img src="<?php echo $thumb; ?>" loading="lazy" class="video-thumb-img" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div class="video-bottom">
                         <div class="video-meta" style="margin-left: 0;">
                            <h3 class="video-title"><?php echo htmlspecialchars($short['title']); ?></h3>
                            <div class="video-info">
                                <div class="video-stats">
                                    <span><?php echo number_format($short['views_count']); ?> vistas</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>