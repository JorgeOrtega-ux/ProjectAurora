<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['user_role'] ?? 'user';
if (!in_array($role, ['founder', 'administrator', 'admin'])) {
    include __DIR__ . '/../system/404.php'; 
    exit;
}
?>
<div class="section-content active" data-section="admin/users">
    <div class="section-center-wrapper" style="flex-direction: column; justify-content: flex-start; padding-top: 20px; width: 98%; max-width: none; margin: 0 auto;">
        
        <div class="toolbar-stack">
            
            <div class="content-toolbar">
                <div style="display: flex; gap: 8px;">
                    <button class="toolbar-action-btn" 
                            data-action="toggle-admin-user-search" 
                            data-i18n-tooltip="global.search"
                            data-tooltip="Buscar">
                        <span class="material-symbols-rounded">search</span>
                    </button>
                    <button class="toolbar-action-btn" 
                            data-i18n-tooltip="search.filter_tooltip"
                            data-tooltip="Filtrar">
                        <span class="material-symbols-rounded">filter_list</span>
                    </button>
                    <button class="toolbar-action-btn" data-tooltip="Nuevo Usuario">
                        <span class="material-symbols-rounded">person_add</span>
                    </button>
                </div>
                
                <div style="flex: 1;"></div>

                <div class="toolbar-pagination">
                    <button class="pagination-btn">
                        <span class="material-symbols-rounded">chevron_left</span>
                    </button>
                    <span class="pagination-number">1</span>
                    <button class="pagination-btn">
                        <span class="material-symbols-rounded">chevron_right</span>
                    </button>
                </div>
            </div>

            <div class="content-toolbar search-toolbar-panel disabled" id="admin-users-search-bar">
                <div class="search-container" style="width: 100%; max-width: 100%;">
                    <span class="material-symbols-rounded search-icon">search</span>
                    <input type="text" 
                           class="search-input" 
                           placeholder="Buscar por nombre, correo o ID..." 
                           spellcheck="false">
                </div>
            </div>

        </div>

        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th style="width: 120px;">Account Status</th>
                        <th style="width: 120px;">Rol</th>
                        <th style="width: 180px;">Creado</th>
                        <th style="width: 60px;"></th>
                    </tr>
                </thead>
                <tbody id="admin-users-table-body">
                    <tr>
                        <td>1</td>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="user-avatar-small" style="background-color: #e0e0e0; width: 32px; height: 32px; border-radius: 50%;"></div>
                                <span style="font-weight: 600;">AdminUser</span>
                            </div>
                        </td>
                        <td>admin@projectaurora.com</td>
                        <td><span class="status-badge status-active">Active</span></td>
                        <td>Administrator</td>
                        <td>2025-10-01 12:00</td>
                        <td>
                            <button class="action-icon-btn"><span class="material-symbols-rounded">more_vert</span></button>
                        </td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>UsuarioTest</td>
                        <td>test@ejemplo.com</td>
                        <td><span class="status-badge status-suspended">Suspended</span></td>
                        <td>User</td>
                        <td>2025-11-15 09:30</td>
                        <td>
                            <button class="action-icon-btn"><span class="material-symbols-rounded">more_vert</span></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</div>