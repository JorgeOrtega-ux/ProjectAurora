<?php
// includes/views/admin/admin-server.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Seguridad de la vista: solo administradores
$userRoleSession = $_SESSION['user_role'] ?? 'user';
if (!in_array($userRoleSession, ['administrator', 'founder'])) {
    http_response_code(403);
    echo "<div class='view-content'><div class='component-wrapper'><div class='component-header-card'><h1 class='component-page-title u-text-error'>Acceso denegado</h1></div></div></div>";
    exit;
}

global $dbConnection;
$configs = [];
if (isset($dbConnection)) {
    try {
        $stmt = $dbConnection->query("SELECT setting_key, setting_value, description FROM server_config");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $configs[$row['setting_key']] = $row;
        }
    } catch (\Throwable $e) {
        // Fallback silencioso
    }
}

// Categorización lógica de las opciones de la BD
$categories = [
    'security' => [
        'icon' => 'password',
        'title' => t('admin.server.cat_security') ?? 'Seguridad y Contraseñas',
        'desc' => t('admin.server.cat_security_desc') ?? 'Define los límites de longitud para las contraseñas de los usuarios.',
        'items' => [
            'min_password_length' => ['type' => 'number', 'label' => 'Longitud mínima', 'min' => 8, 'max' => 32],
            'max_password_length' => ['type' => 'number', 'label' => 'Longitud máxima', 'min' => 32, 'max' => 128]
        ]
    ],
    'users' => [
        'icon' => 'manage_accounts',
        'title' => t('admin.server.cat_users') ?? 'Cuentas de Usuario',
        'desc' => t('admin.server.cat_users_desc') ?? 'Establece las reglas permitidas para los nombres de usuario.',
        'items' => [
            'min_username_length' => ['type' => 'number', 'label' => 'Caracteres mínimos', 'min' => 3, 'max' => 10],
            'max_username_length' => ['type' => 'number', 'label' => 'Caracteres máximos', 'min' => 12, 'max' => 64]
        ]
    ],
    'email' => [
        'icon' => 'alternate_email',
        'title' => t('admin.server.cat_email') ?? 'Validación de Correo Electrónico',
        'desc' => t('admin.server.cat_email_desc') ?? 'Restricciones de longitud y lista blanca de dominios.',
        'items' => [
            'min_email_local_length' => ['type' => 'number', 'label' => 'Mínimo de caracteres antes del @', 'min' => 1, 'max' => 10],
            'max_email_local_length' => ['type' => 'number', 'label' => 'Máximo de caracteres antes del @', 'min' => 10, 'max' => 64],
            'allowed_email_domains' => ['type' => 'text', 'label' => 'Dominios permitidos (separados por coma)']
        ]
    ]
];
?>

<div class="view-content" id="admin-server-view">
    <div class="component-wrapper">
        <input type="hidden" id="csrf_token_admin" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
        
        <div class="component-header-card">
            <h1 class="component-page-title"><?= t('admin.server.title') ?? 'Configuración del Servidor' ?></h1>
            <p class="component-page-description"><?= t('admin.server.desc') ?? 'Administra las reglas de seguridad, validación y parámetros globales del sistema.' ?></p>
        </div>

        <div id="server-config-accordions">
            <?php 
            $accIndex = 1;
            foreach ($categories as $catKey => $category): 
            ?>
                <div class="component-card component-card--grouped component-accordion-item" data-accordion-id="config-<?= $accIndex ?>">
                    <div class="component-group-item component-accordion-header">
                        <div class="component-card__content">
                            <div class="component-card__icon-container component-card__icon-container--bordered">
                                <span class="material-symbols-rounded"><?= $category['icon'] ?></span>
                            </div>
                            <div class="component-card__text">
                                <h2 class="component-card__title"><?= $category['title'] ?></h2>
                                <p class="component-card__description"><?= $category['desc'] ?></p>
                            </div>
                        </div>
                        <span class="material-symbols-rounded component-accordion-chevron">expand_more</span>
                    </div>

                    <div class="component-accordion-content">
                        <?php foreach ($category['items'] as $key => $item): 
                            $currentValue = $configs[$key]['setting_value'] ?? '';
                            $description = $configs[$key]['description'] ?? $key;
                        ?>
                            <hr class="component-divider">
                            
                            <?php if ($item['type'] === 'number'): ?>
                                <div class="component-group-item component-group-item--wrap">
                                    <div class="component-card__content">
                                        <div class="component-card__text">
                                            <h2 class="component-card__title"><?= $item['label'] ?></h2>
                                            <p class="component-card__description"><?= htmlspecialchars($description) ?></p>
                                        </div>
                                    </div>
                                    <div class="component-card__actions actions-right">
                                        <div class="component-pagination config-stepper" data-target="<?= $key ?>" data-min="<?= $item['min'] ?>" data-max="<?= $item['max'] ?>">
                                            <button type="button" class="component-button component-button--square-40 stepper-btn" data-action="decrease" title="Disminuir">
                                                <span class="material-symbols-rounded">remove</span>
                                            </button>
                                            <div class="component-pagination-info stepper-val" id="val-<?= $key ?>" style="min-width: 48px; justify-content: center;">
                                                <?= htmlspecialchars($currentValue) ?>
                                            </div>
                                            <button type="button" class="component-button component-button--square-40 stepper-btn" data-action="increase" title="Aumentar">
                                                <span class="material-symbols-rounded">add</span>
                                            </button>
                                            <input type="hidden" id="input-<?= $key ?>" value="<?= htmlspecialchars($currentValue) ?>">
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="component-group-item component-group-item--stacked">
                                    <div class="component-card__content">
                                        <div class="component-card__text">
                                            <h2 class="component-card__title"><?= $item['label'] ?></h2>
                                            <p class="component-card__description"><?= htmlspecialchars($description) ?></p>
                                        </div>
                                    </div>
                                    <div class="component-card__actions" style="width: 100%;">
                                        <div class="component-input-wrapper" style="width: 100%;">
                                            <input type="text" id="input-<?= $key ?>" class="component-text-input" value="<?= htmlspecialchars($currentValue) ?>" placeholder=" ">
                                            <label for="input-<?= $key ?>" class="component-label-floating"><?= $item['label'] ?></label>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php 
            $accIndex++;
            endforeach; 
            ?>
        </div>

        <div class="component-actions" style="justify-content: flex-end; margin-top: 8px;">
            <button type="button" class="component-button primary component-button--wide" id="btn-save-server-config">
                <?= t('admin.server.btn_save') ?? 'Guardar Configuración' ?>
            </button>
        </div>

    </div>
</div>