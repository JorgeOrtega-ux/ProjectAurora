<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href = '/ProjectAurora/settings/guest';</script>";
    exit;
}
?>
<div class="view-content">
    <h1 style="font-size: 24px; font-weight: 600; margin-bottom: 8px;">Accesibilidad</h1>
    <p style="color: #666; font-size: 14px;">Personaliza tu experiencia de navegación visual y lectura.</p>
    
    <div style="margin-top: 32px; padding: 32px; border: 2px dashed #e0e0e0; border-radius: 12px; text-align: center; color: #888;">
        <span class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 12px; opacity: 0.5;">visibility</span>
        <p style="font-weight: 500;">Sección vacía por el momento</p>
    </div>
</div>