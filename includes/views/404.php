<?php http_response_code(404); ?>
<div class="view-content">
    <div class="component-layout-centered" style="flex-direction: column; text-align: center;">
        
        <span style="display: inline-block; padding: 8px 16px; border: 1px solid var(--border-color); border-radius: 12px; font-weight: 600; font-size: 14px; color: var(--text-secondary); margin-bottom: 24px; letter-spacing: 2px;">
            <?= t('404.badge') ?>
        </span>
        
        <h1 style="font-size: 28px; font-weight: 700; margin-bottom: 12px; color: var(--text-primary);">
            <?= t('404.title') ?>
        </h1>
        
        <p style="font-size: 15px; color: var(--text-secondary); max-width: 350px; line-height: 1.5;">
            <?= t('404.desc') ?>
        </p>

    </div>
</div>