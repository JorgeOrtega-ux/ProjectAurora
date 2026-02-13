<?php
// includes/sections/app/watch.php

$videoUuid = trim($_GET['v'] ?? ''); // Limpiar espacios de la URL
$videoData = null;

if (!empty($videoUuid) && isset($pdo)) {
    try {
    // Elimina "v.views," de la lista de campos
$stmt = $pdo->prepare("
    SELECT v.title, v.description, v.hls_path, v.created_at, 
           u.username, u.avatar_path, u.uuid as user_uuid
    FROM videos v 
    JOIN users u ON v.user_id = u.id 
    WHERE v.uuid = ? AND v.status = 'published' 
    LIMIT 1
");
        $stmt->execute([$videoUuid]);
        $videoData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si sigue fallando aquí, es un error de JOIN.
    } catch (Exception $e) {
        // ESTO TE MOSTRARÁ EL ERROR REAL SI HAY FALLO DE SINTAXIS
        die("ERROR SQL OCULTO: " . $e->getMessage());
    }
}

// Helper para avatar
$avatarUrl = '';
if ($videoData) {
    if (!empty($videoData['avatar_path']) && file_exists(__DIR__ . '/../../../../' . $videoData['avatar_path'])) {
        $avatarUrl = $basePath . $videoData['avatar_path'];
    } else {
        $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($videoData['username']) . "&background=random&color=fff";
    }
}
?>

<div class="component-wrapper component-wrapper--full" data-section="watch" style="max-width: 100%; padding: 0;">
    
    <?php if ($videoData): ?>
        <div class="watch-layout">
            
            <div class="watch-left">
                
                <div class="watch-player-card">
                    <div class="video-player-wrapper">
                        <video id="main-player" controls playsinline poster=""></video>
                    </div>
                </div>

                <div class="watch-meta-card">
                    <h1 class="watch-title"><?php echo htmlspecialchars($videoData['title']); ?></h1>
                    
                    <div class="watch-author-row">
                        <div class="watch-author-info">
                            <img src="<?php echo $avatarUrl; ?>" alt="Avatar" class="watch-avatar">
                            <div class="watch-author-text">
                                <span class="watch-username"><?php echo htmlspecialchars($videoData['username']); ?></span>
                                <span class="watch-subs">0 suscriptores</span>
                            </div>
                            <button class="component-button primary" style="border-radius: 20px; margin-left: 24px;">Suscribirse</button>
                        </div>
                        <div class="watch-actions">
                            <button class="component-button" style="border-radius: 20px;">
                                <span class="material-symbols-rounded">thumb_up</span> 0
                            </button>
                            <button class="component-button" style="border-radius: 20px;">
                                <span class="material-symbols-rounded">share</span> Compartir
                            </button>
                        </div>
                    </div>

                    <div class="watch-description-box">
                        <p class="watch-views-date">
                            0 visualizaciones • <?php echo date('d M Y', strtotime($videoData['created_at'])); ?>
                        </p>
                        <p class="watch-desc-text">
                            <?php echo nl2br(htmlspecialchars($videoData['description'] ?? '')); ?>
                        </p>
                    </div>
                </div>

                <div class="watch-comments-section">
                    <h3 style="font-size: 1.2rem; margin-bottom: 16px;">Comentarios</h3>
                    
                    <div class="component-card" style="text-align: center; color: var(--text-secondary); padding: 40px;">
                        <span class="material-symbols-rounded" style="font-size: 32px; margin-bottom: 8px;">forum</span>
                        <p>Próximamente: Sección de comentarios</p>
                    </div>
                </div>

            </div>

            <div class="watch-right">
                <div class="component-card" style="text-align: center; color: var(--text-secondary); padding: 40px; min-height: 400px; display: flex; flex-direction: column; justify-content: center;">
                    <span class="material-symbols-rounded" style="font-size: 32px; margin-bottom: 8px;">playlist_play</span>
                    <p>Próximamente:<br>Videos relacionados</p>
                </div>
            </div>

        </div>

        <input type="hidden" id="watch-hls-source" value="<?php echo $basePath . $videoData['hls_path']; ?>">
        <input type="hidden" id="watch-video-uuid" value="<?php echo htmlspecialchars($videoUuid); ?>">

    <?php else: ?>
        <div class="component-layout-centered">
            <div class="component-card" style="text-align: center; padding: 40px;">
                <span class="material-symbols-rounded" style="font-size: 48px; color: var(--text-tertiary);">videocam_off</span>
                <h2 style="margin: 16px 0; color: var(--text-primary);">Video no disponible</h2>
                <p style="color: var(--text-secondary);">El video no existe o es privado.</p>
                <button class="component-button primary mt-16" onclick="window.history.back()">Volver</button>
            </div>
        </div>
    <?php endif; ?>

</div>

<style>
    /* ... contenido previo ... */

/* =========================================
   21. WATCH PAGE LAYOUT (Estilo YouTube)
   ========================================= */

.watch-layout {
    display: flex;
    flex-direction: row;
    gap: 24px;
    width: 100%;
    max-width: 1700px; /* Limitar ancho en pantallas ultra-wide */
    margin: 0 auto;
    padding: 24px;
    align-items: flex-start; /* Evita que la columna derecha se estire */
}

/* --- COLUMNA IZQUIERDA (Player + Info) --- */
.watch-left {
    flex: 1; /* Ocupa todo el espacio disponible */
    min-width: 0; /* Previene desbordamiento en Flexbox */
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* Reproductor */
.watch-player-card {
    width: 100%;
    background-color: #000;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    position: relative;
    /* Aspect Ratio 16:9 */
    aspect-ratio: 16 / 9;
}

.video-player-wrapper {
    width: 100%;
    height: 100%;
}

.video-player-wrapper video {
    width: 100%;
    height: 100%;
    object-fit: contain; /* Ajustar sin recortar (barras negras si es necesario) */
}

/* Info del Video */
.watch-meta-card {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.watch-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.4;
    margin: 0;
}

.watch-author-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.watch-author-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.watch-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.watch-author-text {
    display: flex;
    flex-direction: column;
}

.watch-username {
    font-weight: 600;
    font-size: 1rem;
    color: var(--text-primary);
}

.watch-subs {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.watch-actions {
    display: flex;
    gap: 8px;
}

/* Caja de Descripción */
.watch-description-box {
    background-color: var(--bg-hover-light);
    border-radius: 12px;
    padding: 12px;
    font-size: 0.9rem;
    color: var(--text-primary);
    margin-top: 4px;
}

.watch-views-date {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.watch-desc-text {
    white-space: pre-wrap; /* Mantiene saltos de línea */
    line-height: 1.5;
    color: var(--text-secondary);
}

/* --- COLUMNA DERECHA (Recomendaciones) --- */
.watch-right {
    width: 400px; /* Ancho fijo estilo sidebar */
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* --- RESPONSIVE --- */
@media (max-width: 1000px) {
    .watch-layout {
        flex-direction: column; /* Apilar columnas */
        padding: 0; /* Quitar padding lateral para aprovechar móvil */
    }

    .watch-left {
        width: 100%;
    }

    .watch-right {
        width: 100%; /* Sidebar ahora ocupa todo el ancho abajo */
        padding: 0 16px;
    }

    .watch-player-card {
        border-radius: 0; /* Player cuadrado en móvil */
    }

    .watch-meta-card {
        padding: 0 16px;
    }
    
    .watch-comments-section {
        padding: 0 16px 24px 16px;
    }
}
</style>