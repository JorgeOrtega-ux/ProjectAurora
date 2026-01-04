<?php
// includes/sections/app/main.php

// Asegurar acceso a BD (dependiendo de tu bootstrap, $pdo suele estar disponible)
// Si no está disponible globalmente, lo invocamos
if (!isset($pdo)) {
    global $pdo; // O el mecanismo que uses para obtener la instancia DB
}

$userId = $_SESSION['user_id'] ?? null;
$whiteboards = [];

if ($userId && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM whiteboards WHERE user_id = :uid ORDER BY updated_at DESC");
        $stmt->execute([':uid' => $userId]);
        $whiteboards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Manejo silencioso o log de error
        error_log("Error fetching whiteboards: " . $e->getMessage());
    }
}
?>

<div class="component-wrapper component-wrapper--full">
    <div class="component-header-card">
        <h1 class="component-page-title"><?php echo $i18n->t('app.name'); ?></h1>
        <p class="component-page-description">Tus espacios creativos recientes</p>
    </div>

    <div class="component-card">
        <?php if (empty($whiteboards)): ?>
            <div style="text-align: center; padding: 40px 20px; color: #666;">
                <span class="material-symbols-rounded" style="font-size: 48px; color: #cbd5e1; margin-bottom: 15px;">draw</span>
                <p>No tienes pizarrones creados aún.</p>
                <p style="font-size: 13px; color: #94a3b8;">Haz clic en el botón (+) arriba para comenzar.</p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; padding: 10px;">
                <?php foreach ($whiteboards as $wb): ?>
                    <?php 
                        $uuid = htmlspecialchars($wb['uuid']);
                        $name = htmlspecialchars($wb['name']);
                        $date = date('d M Y', strtotime($wb['created_at']));
                        // URL apunta a la ruta dinámica
                        $url = "whiteboard/" . $uuid; 
                    ?>
                    <a href="<?php echo $url; ?>" class="wb-card-link" style="text-decoration: none; color: inherit;">
                        <div class="wb-card" style="
                            background: #fff; 
                            border: 1px solid #e2e8f0; 
                            border-radius: 12px; 
                            overflow: hidden; 
                            transition: transform 0.2s, box-shadow 0.2s; 
                            cursor: pointer;
                            height: 100%;
                            display: flex;
                            flex-direction: column;
                        ">
                            <div style="height: 120px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid #e2e8f0;">
                                <span class="material-symbols-rounded" style="font-size: 32px; color: #94a3b8;">dashboard</span>
                            </div>
                            
                            <div style="padding: 15px;">
                                <h3 style="margin: 0 0 5px; font-size: 15px; font-weight: 600; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo $name; ?>
                                </h3>
                                <div style="font-size: 12px; color: #64748b; display: flex; align-items: center; gap: 4px;">
                                    <span class="material-symbols-rounded" style="font-size: 14px;">calendar_today</span>
                                    <span><?php echo $date; ?></span>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <style>
                .wb-card:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                }
            </style>
        <?php endif; ?>
    </div>
</div>