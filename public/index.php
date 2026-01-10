<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
    <link rel="stylesheet" type="text/css" href="assets/css/styles.css">
    <title>Project Aurora</title>
</head>

<body>
    <div class="page-wrapper">
        <div class="main-content">
            <div class="general-content">
                <div class="general-content-top">
                    <div class="header">
                        <div class="header-left">
                            <div class="header-item">
                                <div class="header-button" data-action="toggleModuleSurface">
                                    <span class="material-symbols-rounded">menu</span>
                                </div>
                            </div>
                        </div>
                        <div class="header-center">
                            <div class="search-wrapper">
                                <div class="search-content">
                                    <div class="search-icon">
                                        <span class="material-symbols-rounded">search</span>
                                    </div>
                                    <div class="search-input">
                                        <input type="text" placeholder="Buscar...">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="header-right">
                            <div class="header-item">
                                <div class="header-button" data-action="toggleModuleOptions">
                                    <span class="material-symbols-rounded">more_vert</span>
                                </div>
                            </div>
                            <div class="module-content module-options disabled">
                                <div class="menu-content">
                                    <div class="menu-list">
                                        <div class="menu-link">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">settings</span></div>
                                            <div class="menu-link-text">
                                                <span>Configuración</span>
                                            </div>
                                        </div>
                                        <div class="menu-link">
                                            <div class="menu-link-icon"><span class="material-symbols-rounded">help</span></div>
                                            <div class="menu-link-text">
                                                <span>Ayuda y comentarios</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="general-content-bottom">
                    <div class="module-content module-surface disabled">
                        <div class="menu-content">
                            <div class="menu-content-top">
                                <div class="menu-list">
                                    <div class="menu-link">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">home</span></div>
                                        <div class="menu-link-text">
                                            <span>Pagina principal</span>
                                        </div>
                                    </div>
                                    <div class="menu-link">
                                        <div class="menu-link-icon"><span class="material-symbols-rounded">folder</span></div>
                                        <div class="menu-link-text">
                                            <span>Explorar tendencias</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="menu-content-bottom"></div>
                        </div>
                    </div>
                    <div class="general-content-scrolleable">
                        </div>
                </div>
            </div>
        </div>
    </div>

    <script type="module" src="assets/js/app-init.js"></script>
</body>

</html>