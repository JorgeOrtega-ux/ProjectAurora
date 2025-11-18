<div class="module-content module-surface body-title disabled" data-module="moduleSurface">
    <div class="menu-content">
        <div class="menu-list">
            <!-- 
                                  NOTA: Se ha quitado el onclick="...".
                                  Ahora url-manager.js lo manejará usando 'data-nav'.
                                -->
            <div class="menu-link <?php echo ($CURRENT_SECTION === 'main') ? 'active' : ''; ?>"
                data-nav="main">
                <div class="menu-link-icon">
                    <span class="material-symbols-rounded">home</span>
                </div>
                <div class="menu-link-text">
                    <span>Página principal</span>
                </div>
            </div>

            <div class="menu-link <?php echo ($CURRENT_SECTION === 'explorer') ? 'active' : ''; ?>"
                data-nav="explorer">
                <div class="menu-link-icon">
                    <span class="material-symbols-rounded">explore</span>
                </div>
                <div class="menu-link-text">
                    <span>Explorar comunidades</span>
                </div>
            </div>
        </div>
    </div>
</div>