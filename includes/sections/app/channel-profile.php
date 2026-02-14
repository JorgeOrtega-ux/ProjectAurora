<?php
// includes/sections/app/channel-profile.php

// 1. Validar que tengamos un UUID capturado desde routing-logic.php
if (!isset($viewingChannelUUID)) {
    // Si por alguna razón llegaron aquí sin UUID, redirigir a 404
    include __DIR__ . '/../system/404.php';
    exit;
}

// 2. Buscar al dueño del canal en la Base de Datos
$stmtChannel = $pdo->prepare("SELECT id, username, uuid, avatar_path, account_status FROM users WHERE uuid = ? LIMIT 1");
$stmtChannel->execute([$viewingChannelUUID]);
$channelOwner = $stmtChannel->fetch(PDO::FETCH_ASSOC);

// 3. Definir el estado de la visita
$channelStatus = 'NOT_FOUND'; // Default

if ($channelOwner) {
    // Verificar si el canal está eliminado o suspendido (opcional, según tus reglas)
    if ($channelOwner['account_status'] === 'deleted') {
        $channelStatus = 'NOT_FOUND'; // O podrías crear un estado 'DELETED' específico
    } 
    elseif (isset($_SESSION['uuid']) && $_SESSION['uuid'] === $viewingChannelUUID) {
        // El UUID de la sesión coincide con el del perfil
        $channelStatus = 'OWNER';
    } 
    else {
        // El usuario existe, pero no soy yo
        $channelStatus = 'VISITOR';
    }
}
?>

<div class="app-container" style="padding: 2rem; color: var(--text-primary);">
    
    <?php if ($channelStatus === 'NOT_FOUND'): ?>
        
        <div class="state-message error">
            <h1>🚫 Canal no encontrado</h1>
            <p>Este canal no existe, el enlace es incorrecto o la cuenta fue eliminada.</p>
            <a href="<?php echo $basePath; ?>" class="btn btn-primary">Volver al inicio</a>
        </div>

    <?php elseif ($channelStatus === 'OWNER'): ?>

        <div class="state-message owner">
            <h1>👋 Hola, <?php echo htmlspecialchars($channelOwner['username']); ?></h1>
            <p style="color: #4ade80;"><strong>Tu eres el dueño de este canal.</strong></p>
            
            <div class="owner-controls" style="margin-top: 20px; padding: 15px; border: 1px dashed #4ade80; border-radius: 8px;">
                <p>Aquí verías botones para: [Editar Perfil] [Subir Video] [Ver Estadísticas]</p>
            </div>
        </div>

    <?php elseif ($channelStatus === 'VISITOR'): ?>

        <div class="state-message visitor">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <img src="<?php echo $channelOwner['avatar_path'] ? $basePath . $channelOwner['avatar_path'] : $basePath . 'public/storage/profilePicture/default/default.png'; ?>" 
                     style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover;">
                
                <div>
                    <h1>Canal de <?php echo htmlspecialchars($channelOwner['username']); ?></h1>
                    <p style="color: #60a5fa;"><strong>Estás viendo este perfil como visitante.</strong></p>
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <button class="btn btn-primary">Suscribirse</button>
            </div>
        </div>

    <?php endif; ?>

</div>