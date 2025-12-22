export function initAuthController() {
    console.log("Auth Controller: Iniciado");

    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const logoutBtn = document.querySelector('[data-action="logout"]');

    if (loginForm) {
        loginForm.addEventListener('submit', (e) => handleAuth(e, 'login'));
    }

    if (registerForm) {
        registerForm.addEventListener('submit', (e) => handleAuth(e, 'register'));
    }

    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            handleLogout();
        });
    }
}

async function handleAuth(event, action) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', action);

    // Feedback visual (opcional)
    const btn = form.querySelector('button');
    const originalText = btn.innerText;
    btn.innerText = 'Procesando...';
    btn.disabled = true;

    try {
        const response = await fetch(window.BASE_PATH + 'public/auth-handler.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = data.redirect;
        } else {
            alert(data.message);
            btn.innerText = originalText;
            btn.disabled = false;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Ocurrió un error en la conexión.');
        btn.innerText = originalText;
        btn.disabled = false;
    }
}

async function handleLogout() {
    const formData = new FormData();
    formData.append('action', 'logout');

    try {
        const response = await fetch(window.BASE_PATH + 'public/auth-handler.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            window.location.href = data.redirect;
        }
    } catch (error) {
        console.error('Logout error:', error);
    }
}