<div class="section-content active" data-section="error-missing-data">
    <div class="section-center-wrapper">
        <div class="form-container" style="text-align: center;">
            <h1 style="font-size: 32px; margin-bottom: 25px;" data-i18n="system.missing_data_title"><?php echo trans('system.missing_data_title'); ?></h1>
            <div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; text-align: left; background-color: #fff;">
                <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #000;" data-i18n="system.missing_data_error"><?php echo trans('system.missing_data_error'); ?></h3>
                <p style="margin: 0; font-size: 14px; color: #666; line-height: 1.5;">
                    <?php 
                    echo isset($missingDataMessage) 
                        ? $missingDataMessage 
                        : '<span data-i18n="system.missing_data_default">' . trans('system.missing_data_default') . '</span>'; 
                    ?>
                </p>
            </div>
        </div>
    </div>
</div>