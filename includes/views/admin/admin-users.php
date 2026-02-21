<?php
// includes/views/admin/admin-users.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Obtener los usuarios de la base de datos
$users = [];
global $dbConnection;
if (isset($dbConnection)) {
    try {
        $stmt = $dbConnection->query("SELECT uuid, username, email, role, status, avatar_path, created_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        // En caso de error, el arreglo quedará vacío
    }
}
?>

<style>
    /* =========================================
       TOOLBAR STICKY Y SECUNDARIAS
       ========================================= */
    .component-toolbar-wrapper {
        position: sticky;
        top: 16px;
        width: 100%;
        max-width: 425px; /* Ancho máximo ajustado */
        margin: 0 auto 24px auto; /* Centrada y separada del header */
        z-index: 50;
    }

    .component-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        height: 56px;
        padding: 8px;
        background-color: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease;
        position: relative;
    }

    .toolbar-group {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .component-toolbar--primary {
        position: relative;
        z-index: 2;
    }

    /* Toolbar secundaria (Debajo de la principal) */
    .component-toolbar--secondary {
        position: absolute;
        top: calc(100% + 5px);
        left: 0;
        width: 100%;
        height: 56px; /* Misma altura que la principal */
        padding: 8px;
        background-color: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-card);
        z-index: 10;
        opacity: 0;
        pointer-events: none;
        visibility: hidden;
        transform: translateY(-5px);
        transition: all 0.2s ease;
    }

    .component-toolbar--secondary.active {
        opacity: 1;
        pointer-events: auto;
        visibility: visible;
        transform: translateY(0);
    }

    .component-toolbar__side {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .selection-info-text {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-primary);
        margin-right: 4px;
    }

    /* =========================================
       COMPONENTE PAGINACIÓN
       ========================================= */
    .component-pagination {
        display: flex;
        align-items: center;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        height: 40px;
        padding: 2px; /* Espacio para separar los botones del borde padre */
        gap: 4px; /* Separación entre botones y texto */
    }

    .component-pagination .component-button {
        border: 1px solid var(--border-transparent-20); /* Borde completo */
        border-radius: 6px;
        background-color: transparent;
    }

    .component-pagination .component-button:hover:not(:disabled) {
        background-color: var(--bg-hover-light);
    }

    .component-pagination-info {
        padding: 0 8px;
        font-size: 14px;
        font-weight: 500;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        height: 100%;
    }

    /* =========================================
       LISTA Y TARJETAS DE USUARIO
       ========================================= */
    .component-users-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
        /* Margin top eliminado según requerimiento */
    }

    .component-user-card {
        background-color: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 16px;
        display: flex;
        flex-wrap: wrap; 
        align-items: center;
        gap: 8px;
        transition: border-color 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }

    .component-user-card:hover {
        border-color: var(--text-primary);
    }

    .component-user-card.selected {
        border-color: var(--action-primary);
        box-shadow: 0 0 0 1px var(--action-primary);
    }

    /* Sistema de foto de perfil (40px) */
    .admin-user-avatar-box {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: var(--bg-surface-alt);
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-right: 4px;
    }

    .admin-user-avatar-box::before {
        content: '';
        position: absolute;
        top: -3px;
        left: -3px;
        right: -3px;
        bottom: -3px;
        border-radius: 50%;
        border: 2px solid transparent;
        pointer-events: none;
        z-index: 2;
    }

    .admin-user-avatar-box[data-role="user"]::before { border-color: var(--role-user-border); }
    .admin-user-avatar-box[data-role="moderator"]::before { border-color: var(--role-moderator); }
    .admin-user-avatar-box[data-role="administrator"]::before { border-color: var(--role-administrator); }
    .admin-user-avatar-box[data-role="founder"]::before {
        border: none;
        background-image: conic-gradient(from 300deg, #D32029 0deg 90deg, #206BD3 90deg 210deg, #28A745 210deg 300deg, #FFC107 300deg 360deg);
        mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #fff 0);
        -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #fff 0);
    }

    .admin-user-avatar-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
        border: 2px solid var(--bg-surface);
    }

    /* Badges transparentes (min-height 30px) */
    .component-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0 12px;
        min-height: 30px;
        background-color: transparent;
        border: 1px solid var(--border-transparent-20);
        border-radius: 50px;
        font-size: 13px;
        font-weight: 500;
        color: var(--text-secondary);
        white-space: nowrap;
    }

    .component-badge .material-symbols-rounded {
        font-size: 16px;
    }
</style>

<div class="view-content">
    <div class="component-wrapper">
        
        <div class="component-toolbar-wrapper">
            <div class="component-toolbar">
                
                <div class="toolbar-group component-toolbar--primary" id="toolbar-primary">
                    <div class="component-toolbar__side">
                        <button type="button" class="component-button component-button--square-40" data-tooltip="Buscar" onclick="toggleSearchToolbar()">
                            <span class="material-symbols-rounded">search</span>
                        </button>
                        <button type="button" class="component-button component-button--square-40" data-tooltip="Filtros">
                            <span class="material-symbols-rounded">filter_list</span>
                        </button>
                    </div>
                    <div class="component-toolbar__side">
                        <div class="component-pagination">
                            <button type="button" class="component-button component-button--square-40" disabled>
                                <span class="material-symbols-rounded">chevron_left</span>
                            </button>
                            <div class="component-pagination-info">1 / 10</div>
                            <button type="button" class="component-button component-button--square-40">
                                <span class="material-symbols-rounded">chevron_right</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="toolbar-group component-toolbar--secondary" id="toolbar-search">
                    <div class="component-search" style="width: 100%;">
                        <div class="component-search-icon"><span class="material-symbols-rounded">search</span></div>
                        <div class="component-search-input">
                            <input type="text" class="component-search-input-field" placeholder="Buscar usuario..." id="admin-user-search-input">
                        </div>
                    </div>
                </div>

                <div class="toolbar-group component-toolbar--secondary" id="toolbar-selection">
                    <div class="component-toolbar__side">
                        <button type="button" class="component-button component-button--square-40" data-tooltip="Administrar cuenta">
                            <span class="material-symbols-rounded">manage_accounts</span>
                        </button>
                        <button type="button" class="component-button component-button--square-40" data-tooltip="Administrar rol">
                            <span class="material-symbols-rounded">admin_panel_settings</span>
                        </button>
                        <button type="button" class="component-button component-button--square-40" data-tooltip="Estado de la cuenta">
                            <span class="material-symbols-rounded">gpp_maybe</span>
                        </button>
                    </div>
                    <div class="component-toolbar__side">
                        <span class="selection-info-text">1 seleccionado</span>
                        <button type="button" class="component-button component-button--square-40" data-tooltip="Desmarcar" onclick="clearUserSelection(event)">
                            <span class="material-symbols-rounded">close</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <div class="component-header-card">
            <h1 class="component-page-title"><?= t('surface.admin_users') ?? 'Gestionar Usuarios' ?></h1>
            <p class="component-page-description">Administra los roles, cuentas y accesos de los usuarios registrados en el sistema.</p>
        </div>

        <div class="component-users-list">
            <?php if (empty($users)): ?>
                <div style="padding: 24px; text-align: center; color: var(--text-secondary); border: 1px dashed var(--border-color); border-radius: 12px;">
                    No hay usuarios registrados o no se pudo conectar a la base de datos.
                </div>
            <?php else: ?>
                <?php foreach ($users as $user): 
                    $avatarPath = '/ProjectAurora/' . ltrim($user['avatar_path'], '/');
                    $dateFormatted = date('d M, Y', strtotime($user['created_at']));
                    
                    // Asignar icono dinámico según el estatus
                    $statusIcon = 'check_circle';
                    if ($user['status'] === 'suspended') $statusIcon = 'block';
                    if ($user['status'] === 'deleted') $statusIcon = 'delete_forever';
                ?>
                    <div class="component-user-card" onclick="selectUserCard(this)">
                        
                        <div class="admin-user-avatar-box" data-role="<?= htmlspecialchars($user['role']) ?>">
                            <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Avatar de <?= htmlspecialchars($user['username']) ?>">
                        </div>
                        
                        <span class="component-badge" title="Nombre de usuario">
                            <span class="material-symbols-rounded">person</span>
                            <?= htmlspecialchars($user['username']) ?>
                        </span>
                        
                        <span class="component-badge" title="Correo electrónico">
                            <span class="material-symbols-rounded">mail</span>
                            <?= htmlspecialchars($user['email']) ?>
                        </span>
                        
                        <span class="component-badge" title="Rol del usuario">
                            <span class="material-symbols-rounded">shield_person</span>
                            <?= ucfirst(htmlspecialchars($user['role'])) ?>
                        </span>
                        
                        <span class="component-badge" title="Estado de la cuenta">
                            <span class="material-symbols-rounded"><?= $statusIcon ?></span>
                            <?= ucfirst(htmlspecialchars($user['status'])) ?>
                        </span>
                        
                        <span class="component-badge" title="Identificador único (UUID)">
                            <span class="material-symbols-rounded">fingerprint</span>
                            <?= htmlspecialchars($user['uuid']) ?>
                        </span>
                        
                        <span class="component-badge" title="Fecha de registro">
                            <span class="material-symbols-rounded">calendar_today</span>
                            <?= $dateFormatted ?>
                        </span>
                        
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
    // Mostrar u Ocultar la barra de búsqueda secundaria (funciona como Toggle)
    window.toggleSearchToolbar = function() {
        const searchToolbar = document.getElementById('toolbar-search');
        const selectionToolbar = document.getElementById('toolbar-selection');
        
        // Si la barra de selección está activa, la cerramos
        if (selectionToolbar && selectionToolbar.classList.contains('active')) {
            clearUserSelection(); 
        }

        // Alternar el estado de la barra de búsqueda
        if (searchToolbar.classList.contains('active')) {
            searchToolbar.classList.remove('active');
            document.getElementById('admin-user-search-input').value = '';
        } else {
            searchToolbar.classList.add('active');
            setTimeout(() => document.getElementById('admin-user-search-input').focus(), 100);
        }
    };

    // Lógica para seleccionar/deseleccionar una card (Solo 1 a la vez)
    window.selectUserCard = function(cardElement) {
        const allCards = document.querySelectorAll('.component-user-card');
        const selectionToolbar = document.getElementById('toolbar-selection');
        const searchToolbar = document.getElementById('toolbar-search');
        
        const isCurrentlySelected = cardElement.classList.contains('selected');
        
        // Limpiamos todas las selecciones y ocultamos búsqueda
        allCards.forEach(c => c.classList.remove('selected'));
        if(searchToolbar) searchToolbar.classList.remove('active');
        
        if (isCurrentlySelected) {
            // Clic en la misma que ya estaba activa: la desactiva
            selectionToolbar.classList.remove('active');
        } else {
            // Activa la clickeada y muestra las herramientas de acción
            cardElement.classList.add('selected');
            selectionToolbar.classList.add('active');
        }
    };

    // Botón de "Cerrar/X" en la toolbar de selección para limpiar estado
    window.clearUserSelection = function(event) {
        if(event) event.stopPropagation();
        
        const allCards = document.querySelectorAll('.component-user-card');
        const selectionToolbar = document.getElementById('toolbar-selection');
        
        allCards.forEach(c => c.classList.remove('selected'));
        if (selectionToolbar) {
            selectionToolbar.classList.remove('active');
        }
    };
</script>