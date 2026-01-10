<div id="menu-content-settings" class="menu-content" style="display: flex;">
    <div class="menu-content-top">
        <div class="menu-list">
            <div class="menu-link" data-nav="main" style="margin-bottom: 8px; border-bottom: 1px solid #eee;">
                <div class="menu-link-icon"><span class="material-symbols-rounded">arrow_back</span></div>
                <div class="menu-link-text"><span>Volver al inicio</span></div>
            </div>
            <div class="menu-link <?php echo ($currentSection ?? '') === 'settings/preferences' ? 'active' : ''; ?>" data-nav="settings/preferences">
                <div class="menu-link-icon"><span class="material-symbols-rounded">tune</span></div>
                <div class="menu-link-text"><span>Preferencias</span></div>
            </div>
        </div>
    </div>
    <div class="menu-content-bottom"></div>
</div>