<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
?>
<div class="section-content active" data-section="main">
    <div class="component-wrapper">
        
        <div class="component-header-card" style="text-align: left;">
            <h1 class="component-page-title">Mis Comunidades</h1>
            <p class="component-page-description">Grupos a los que te has unido.</p>
        </div>

        <div id="my-communities-list" class="mt-16" style="display: flex; flex-direction: column; gap: 16px;">
            <div class="small-spinner" style="margin: 40px auto;"></div>
        </div>

    </div>
</div>