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
            

        </div>
        
        <div class="component-watch-actions">
                        <button class="component-button primary js-btn-subscribe component-watch-subscribe-btn <?php echo $interaction['subscribed'] ? 'subscribed' : ''; ?>">
                <?php echo $interaction['subscribed'] ? 'Suscrito' : 'Suscribirse'; ?>
            </button>
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