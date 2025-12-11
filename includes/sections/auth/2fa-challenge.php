<?php
// includes/sections/auth/2fa-challenge.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Seguridad extra: si no hay sesión temporal de 2fa, volver al login
if (!isset($_SESSION['temp_2fa_user_id'])) {
    echo "<script>window.location.href = '" . $basePath . "login';</script>";
    exit;
}
?>

<div class="auth-wrapper">
    <div class="auth-card">
        
        <div id="view-totp">
            <div class="auth-header">
                <span class="material-symbols-rounded" style="font-size: 48px; color: #000; margin-bottom: 10px;">lock_person</span>
                <h1><?= __('auth.2fa.challenge_title') ?></h1>
                <p><?= __('auth.2fa.challenge_desc') ?></p>
            </div>
            
            <input type="hidden" name="action" value="verify_2fa_login">
            
            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="text" id="2fa-code-input" required placeholder=" " maxlength="6" inputmode="numeric" pattern="[0-9]*" style="text-align: center; letter-spacing: 5px; font-size: 20px;">
                    <label for="2fa-code-input" style="left: 50%; transform: translateX(-50%) translateY(-50%);"><?= __('auth.2fa.app_label') ?></label>
                </div>
            </div>

            <button type="button" id="btn-verify-totp" class="btn-primary"><?= __('global.verify') ?></button>

            <div class="auth-footer" style="margin-top: 15px;">
                <p style="margin-bottom: 8px;">
                    <a href="#" id="trigger-show-backup" class="text-link" style="color: #666; font-size: 13px;">
                        <?= __('auth.2fa.lost_device') ?>
                    </a>
                </p>
                <p><a href="<?php echo $basePath; ?>login" class="text-link"><?= __('auth.recover.back_login') ?></a></p>
            </div>
        </div>

        <div id="view-backup" style="display: none;">
            <div class="auth-header">
                <span class="material-symbols-rounded" style="font-size: 48px; color: #d32f2f; margin-bottom: 10px;">key_off</span>
                <h1><?= __('auth.2fa.backup_title') ?></h1>
                <p><?= __('auth.2fa.backup_desc') ?></p>
            </div>

            <div class="form-groups-wrapper">
                <div class="form-group">
                    <input type="text" id="2fa-backup-input" required placeholder=" " style="text-align: center; font-size: 16px;">
                    <label for="2fa-backup-input" style="left: 50%; transform: translateX(-50%) translateY(-50%); width: 100%; text-align: center;"><?= __('auth.2fa.backup_label') ?></label>
                </div>
            </div>

            <button type="button" id="btn-verify-backup" class="btn-primary"><?= __('auth.2fa.use_backup_btn') ?></button>

            <div class="auth-footer" style="margin-top: 15px;">
                <p style="margin-bottom: 8px;">
                    <a href="#" id="trigger-show-totp" class="text-link" style="color: #666; font-size: 13px;">
                        <?= __('auth.2fa.have_device') ?>
                    </a>
                </p>
                <p><a href="<?php echo $basePath; ?>login" class="text-link"><?= __('auth.recover.back_login') ?></a></p>
            </div>
        </div>

    </div>
</div>