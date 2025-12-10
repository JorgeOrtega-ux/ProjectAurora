<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1><?= __('auth.recover.title') ?></h1>
            <p><?= __('auth.recover.subtitle') ?></p>
        </div>
        
        <div class="form-groups-wrapper">
            <div class="form-group">
                <input type="email" id="recover-email" required placeholder=" ">
                <label for="recover-email"><?= __('auth.email') ?></label>
            </div>
        </div>

        <button type="button" id="btn-recover-request" class="btn-primary"><?= __('auth.recover.send_link') ?></button>
        
        <div id="resend-container" style="margin-top: 15px; font-size: 14px; color: #666; display: none;">
            <a href="#" id="btn-resend-link" style="color: #999; pointer-events: none; text-decoration: none;">
                <?= __('auth.recover.resend_link') ?> <span id="recover-timer">(60)</span>
            </a>
        </div>

        <div id="simulation-result" style="margin-top: 15px; word-break: break-all; display:none;" class="alert success"></div>

        <div class="auth-footer">
            <p><a href="<?php echo $basePath; ?>login"><?= __('auth.recover.back_login') ?></a></p>
        </div>
    </div>
</div>