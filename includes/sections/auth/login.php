<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1><?= __('auth.login.title') ?></h1>
            <p><?= __('auth.login.subtitle') ?></p>
        </div>
        
        <input type="hidden" id="login-action" name="action" value="login">
        
        <div class="form-groups-wrapper">
            <div class="form-group">
                <input type="email" name="email" id="email" required placeholder=" ">
                <label for="email"><?= __('auth.email') ?></label>
            </div>

            <div class="form-group">
                <input type="password" name="password" id="password" required placeholder=" ">
                <label for="password"><?= __('auth.password') ?></label>
            </div>
        </div>

       <div class="forgot-password">
            <a href="<?php echo $basePath; ?>recover-password"><?= __('auth.forgot_password') ?></a>
        </div>
        <button type="button" id="btn-login" class="btn-primary"><?= __('auth.login_button') ?></button>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error" style="margin-top: 16px; margin-bottom: 0;">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success" style="margin-top: 16px; margin-bottom: 0;">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <div class="auth-footer">
            <p><?= __('auth.no_account') ?> <a href="<?php echo $basePath; ?>register"><?= __('auth.register_here') ?></a></p>
        </div>
    </div>
</div>