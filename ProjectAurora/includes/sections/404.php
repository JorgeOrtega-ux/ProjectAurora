<?php
// Esto asegura que el $basePath (definido en router.php) esté disponible
// para el enlace de "Go back home".
if (!isset($basePath)) {
    $basePath = '/ProjectAurora/';
}
?>
<div class="section-content overflow-y active" data-section="404">

    <div class="section-center-wrapper">

        <div class="error-404-container"> <span class="error-404-badge">404</span> <h1 class="error-404-title">Oops! Page not found.</h1> <p class="error-404-text"> We couldn't find the page you're looking for. It might have been moved or doesn't exist anymore.
            </p>

            <a href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>" class="form-button" style="max-width: 220px; margin: 24px auto 0; text-decoration: none; display: block; text-align: center; line-height: 55px;">
                Go back home
            </a>

        </div>

    </div>

</div>