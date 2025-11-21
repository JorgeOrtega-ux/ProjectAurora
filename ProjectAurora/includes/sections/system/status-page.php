<?php
$status = $_GET['status'] ?? 'suspended';
$icon = "block";
$color = "#d32f2f"; 
$titleKey = "status.suspended_title";
$msgKey = "status.suspended_msg";

if ($status === 'deleted') {
    $titleKey = "status.deleted_title";
    $msgKey = "status.deleted_msg";
    $icon = "delete_forever";
    $color = "#616161"; 
}
?>

<div class="section-content active" data-section="status-page">
    <div class="section-center-wrapper">
        
        <div class="form-container" style="text-align: center; padding-top: 0;">
            
            <div style="margin-bottom: 20px;">
                <span class="material-symbols-rounded" style="font-size: 80px; color: <?php echo $color; ?>;">
                    <?php echo $icon; ?>
                </span>
            </div>

            <h1 style="margin-bottom: 15px; color: <?php echo $color; ?>; font-size: 28px;" data-i18n="<?php echo $titleKey; ?>">
                <?php echo trans($titleKey); ?>
            </h1>
            
            <p style="color: #555; line-height: 1.6; font-size: 16px; margin-bottom: 40px;" data-i18n="<?php echo $msgKey; ?>">
                <?php echo trans($msgKey); ?>
            </p>
            
            <div>
                <a href="<?php echo isset($basePath) ? $basePath : '/ProjectAurora/'; ?>login" style="color: #888; text-decoration: none; font-size: 14px; font-weight: 500;">
                    <span class="material-symbols-rounded" style="font-size: 16px; vertical-align: text-bottom;">arrow_back</span> 
                    <span data-i18n="global.back_home"><?php echo trans('global.back_home'); ?></span>
                </a>
            </div>

        </div>

    </div>
</div>