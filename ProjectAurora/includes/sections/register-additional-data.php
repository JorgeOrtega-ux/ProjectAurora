<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Si no hay email en sesión (se saltaron el paso 1), redirigir al inicio
if (empty($_SESSION['temp_register']['email'])) {
    echo "<script>window.location.href = window.BASE_PATH + 'register';</script>";
    exit;
}
?>
<div class="section-content overflow-y active" data-section="register/additional-data">
    
    <div class="section-center-wrapper">

        <div class="form-container">
            
            <div style="margin-bottom: 10px;">
                <a href="#" onclick="event.preventDefault(); navigateTo('register')" style="color:#666; text-decoration:none; display:flex; align-items:center; gap:5px; font-size:14px;">
                    <span class="material-symbols-rounded" style="font-size:18px;">arrow_back</span> Volver
                </a>
            </div>

            <h1>Crea tu identidad (2/3)</h1>
            <p>Elige un nombre de usuario único.</p>

            <div class="floating-label-group">
                <input 
                    type="text" 
                    id="reg-username" 
                    class="floating-input" 
                    required 
                    placeholder=" "
                    maxlength="50"
                    value="<?php echo isset($_SESSION['temp_register']['username']) ? htmlspecialchars($_SESSION['temp_register']['username']) : ''; ?>"
                >
                <label for="reg-username" class="floating-label">Nombre de Usuario</label>
            </div>

            <button class="form-button" id="btn-register-step2">Continuar</button>

            <div id="register-error-2" class="form-error-message"></div>

        </div>

    </div>

</div>