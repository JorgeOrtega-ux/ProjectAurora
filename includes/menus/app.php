<div id="menu-content-app" class="menu-content" style="display: flex;">
    <div class="menu-content-top">
        <div class="menu-list">
            <div class="menu-link <?php echo ($currentSection ?? '') === 'main' ? 'active' : ''; ?>" data-nav="main">
                <div class="menu-link-icon"><span class="material-symbols-rounded">home</span></div>
                <div class="menu-link-text"><span>Página principal</span></div>
            </div>
            <div class="menu-link <?php echo ($currentSection ?? '') === 'trends' ? 'active' : ''; ?>" data-nav="trends">
                <div class="menu-link-icon"><span class="material-symbols-rounded">trending_up</span></div>
                <div class="menu-link-text"><span>Explorar tendencias</span></div>
            </div>
        </div>
    </div>
    <div class="menu-content-bottom"></div>
</div>