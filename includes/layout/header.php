<div class="header">
    <div class="header-left">
        <div class="component-actions">
            <button class="component-button component-button--square-40" data-action="toggleModuleSurface">
                <span class="material-symbols-rounded">menu</span>
            </button>
        </div>
    </div>

    <div class="header-center">
        <div class="component-search">
            <div class="component-search-icon">
                <span class="material-symbols-rounded">search</span>
            </div>
            <div class="component-search-input">
                <input type="text" class="component-search-input-field" placeholder="Buscar...">
            </div>
        </div>
    </div>

    <div class="header-right">
        <div class="component-actions">
            <button class="component-button component-button--square-40 mobile-search-trigger">
                <span class="material-symbols-rounded">search</span>
            </button>

            <button class="component-button component-button--square-40" data-action="toggleModuleMainOptions">
                <span class="material-symbols-rounded">more_vert</span>
            </button>
        </div>
    </div>

    <?php
    // __DIR__ aquí es '.../includes/layout'. 
    // Subimos uno para ir a 'includes' y entramos a 'modules'.
    include __DIR__ . '/../modules/moduleMainOptions.php';
    ?>

</div>