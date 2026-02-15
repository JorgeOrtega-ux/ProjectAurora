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