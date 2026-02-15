<?php
require_once __DIR__ . './watch_components/watch-logic.php';
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

                <?php include __DIR__ . './watch_components/watch-player.php'; ?>

                <?php include __DIR__ . './watch_components/watch-info.php'; ?>

                <?php include __DIR__ . './watch_components/watch-comments.php'; ?>

            </div>

            <?php include __DIR__ . './watch_components/watch-sidebar.php'; ?>

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