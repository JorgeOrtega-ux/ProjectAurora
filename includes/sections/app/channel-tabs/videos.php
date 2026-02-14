<?php
// includes/sections/app/channel-tabs/videos.php
if (!isset($channelOwner['id'])) return;

$stmt = $pdo->prepare("
    SELECT uuid, title, thumbnail_path, views_count, created_at, duration, orientation, hls_path, dominant_color 
    FROM videos 
    WHERE user_id = ? AND status = 'published' AND orientation = 'landscape'
    ORDER BY created_at DESC
");
$stmt->execute([$channelOwner['id']]);
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="padding: 1.5rem 0;">
    <?php if (empty($videos)): ?>
        <div style="text-align: center; padding: 3rem 0; color: var(--text-tertiary);">
            <span class="material-symbols-rounded" style="font-size: 48px;">movie</span>
            <p>Aún no hay videos largos.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px;">
            <?php foreach ($videos as $video): ?>
                <?php 
                    $thumb = $video['thumbnail_path'] ? $basePath . $video['thumbnail_path'] : '';
                    $safeColor = getNearestSafeColorPHP($video['dominant_color']);
                ?>
                <div class="video-card" 
                     style="--dynamic-base: <?php echo $safeColor; ?>; cursor: pointer;"
                     data-uuid="<?php echo $video['uuid']; ?>"
                     data-hls="<?php echo $video['hls_path']; ?>"
                     data-orientation="landscape"
                >
                    <div class="video-top">
                        <img src="<?php echo $thumb; ?>" loading="lazy" class="video-thumb-img" style="width: 100%; height: 100%; object-fit: cover;">
                        <div class="video-duration"><?php echo fmtDuration($video['duration']); ?></div>
                    </div>
                    <div class="video-bottom">
                        <div class="video-meta" style="margin-left: 0;">
                            <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                            <div class="video-info">
                                <div class="video-stats">
                                    <span><?php echo number_format($video['views_count']); ?> vistas</span>
                                    <span><?php echo timeAgo($video['created_at']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>