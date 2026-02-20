import { ApiService } from './api-services.js';
import { API_ROUTES } from './api-routes.js';

export class AuthController {
    constructor(router) {
        this.router = router;
        this.init();
        
        this.checkRegisterStage(window.location.pathname);
        this.checkResetPasswordStage(window.location.pathname);
    }

    init() {
        document.body.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                if (e.target.closest('#form-login')) { e.preventDefault(); this.handleLogin(); }
                else if (e.target.closest('#form-register-1')) { e.preventDefault(); this.handleRegisterStage1(); }
                else if (e.target.closest('#form-register-2')) { e.preventDefault(); this.handleRegisterStage2(); }
                else if (e.target.closest('#form-register-3')) { e.preventDefault(); this.handleRegisterFinal(); }
                else if (e.target.closest('#form-forgot-password')) { e.preventDefault(); this.handleForgotPassword(); }
                else if (e.target.closest('#form-reset-password')) { e.preventDefault(); this.handleResetPassword(); }
            }
        });

        document.body.addEventListener('click', (e) => {
            if (e.target.closest('#btn-login')) { e.preventDefault(); this.handleLogin(); return; }
            if (e.target.closest('#btn-next-1')) { e.preventDefault(); this.handleRegisterStage1(); return; }
            if (e.target.closest('#btn-next-2')) { e.preventDefault(); this.handleRegisterStage2(); return; }
            if (e.target.closest('#btn-register-final')) { e.preventDefault(); this.handleRegisterFinal(); return; }
            if (e.target.closest('#btn-forgot-password')) { e.preventDefault(); this.handleForgotPassword(); return; }
            if (e.target.closest('#btn-reset-password')) { e.preventDefault(); this.handleResetPassword(); return; }

            const logoutBtn = e.target.closest('[data-action="logout"]');
            if (logoutBtn) { e.preventDefault(); this.handleLogout(logoutBtn); return; }

            const toggleBtn = e.target.closest('.component-input-action');
            if (toggleBtn) {
                const wrapper = toggleBtn.parentElement;
                const input = wrapper.querySelector('input');
                const icon = toggleBtn.querySelector('span');
                if (input && input.type === 'password') { input.type = 'text'; icon.textContent = 'visibility_off'; }
                else if (input && input.type === 'text') { input.type = 'password'; icon.textContent = 'visibility'; }
            }

            if (e.target.closest('#btn-back-1')) {
                this.router.navigate('/ProjectAurora/register');
            } else if (e.target.closest('#btn-back-2')) {
                this.router.navigate('/ProjectAurora/register/aditional-data');
            }
        });

        window.addEventListener('viewLoaded', (e) => {
            this.checkRegisterStage(e.detail.url);
            this.checkResetPasswordStage(e.detail.url);
        });
    }

    showFatalJsonError(containerId, codeId, httpCode, message, type, codeStr, formsToHide = []) {
        const container = document.getElementById(containerId);
        const codeBox = document.getElementById(codeId);
        
        if (!container || !codeBox) return;

        formsToHide.forEach(form => {
            if (form) form.style.display = 'none';
        });
        const header = document.querySelector('.component-header-centered');
        if (header) header.style.display = 'none';

        const jsonContent = `Route Error (${httpCode} ): {\n  "error": {\n    "message": "${message}",\n    "type": "${type}",\n    "param": null,\n    "code": "${codeStr}"\n  }\n}`;
        codeBox.textContent = jsonContent;
        container.style.display = 'flex';
    }

    checkRegisterStage(url) {
        if (!url.includes('/ProjectAurora/register')) return;

        const stage1 = document.getElementById('form-register-1');
        const stage2 = document.getElementById('form-register-2');
        const stage3 = document.getElementById('form-register-3');
        const header = document.querySelector('.component-header-centered');
        const fatalContainer = document.getElementById('register-fatal-error');

        if (!stage1 || !stage2 || !stage3) return;

        if (header) header.style.display = 'block';
        if (fatalContainer) fatalContainer.style.display = 'none';

        const email = sessionStorage.getItem('reg_email') || '';
        const pass = sessionStorage.getItem('reg_password') || '';
        const user = sessionStorage.getItem('reg_username') || '';
        const devCode = sessionStorage.getItem('reg_dev_code') || '';

        if (document.getElementById('reg-email')) document.getElementById('reg-email').value = email;
        if (document.getElementById('reg-password')) document.getElementById('reg-password').value = pass;
        if (document.getElementById('reg-username')) document.getElementById('reg-username').value = user;

        stage1.style.display = 'none';
        stage2.style.display = 'none';
        stage3.style.display = 'none';

        const title = document.getElementById('auth-title');
        const subtitle = document.getElementById('auth-subtitle');

        if (url.endsWith('/register/aditional-data')) {
            if (!email) { 
                this.showFatalJsonError('register-fatal-error', 'register-fatal-error-code', 409, 'Invalid client. Please start over.', 'invalid_request_error', 'invalid_state', [stage1, stage2, stage3]);
                return; 
            }
            stage2.style.display = 'flex';
            if (title) { title.textContent = window.t('js.auth.stage2.title'); subtitle.textContent = window.t('js.auth.stage2.sub'); }
            setTimeout(() => document.getElementById('reg-username').focus(), 50);

        } else if (url.endsWith('/register/verification-account')) {
            if (!email || !user) { 
                this.showFatalJsonError('register-fatal-error', 'register-fatal-error-code', 409, 'Invalid client. Please start over.', 'invalid_request_error', 'invalid_state', [stage1, stage2, stage3]);
                return; 
            }
            stage3.style.display = 'flex';
            if (title) { title.textContent = window.t('js.auth.stage3.title'); subtitle.textContent = window.t('js.auth.stage3.sub'); }
            const display = document.getElementById('simulated-code-display');
            if (display && devCode) display.textContent = devCode;
            setTimeout(() => document.getElementById('reg-code').focus(), 50);

        } else {
            stage1.style.display = 'flex';
            if (title) { title.textContent = window.t('js.auth.stage1.title'); subtitle.textContent = window.t('js.auth.stage1.sub'); }
        }
    }

    checkResetPasswordStage(url) {
        if (!url.includes('/ProjectAurora/reset-password')) return;
        
        const token = new URLSearchParams(window.location.search).get('token');
        const form = document.getElementById('form-reset-password');
        const header = document.querySelector('.component-header-centered');
        const fatalContainer = document.getElementById('reset-fatal-error');
        
        if (fatalContainer) fatalContainer.style.display = 'none';
        if (header) header.style.display = 'block';
        if (form) form.style.display = 'flex';

        if (!token) {
            this.showFatalJsonError('reset-fatal-error', 'reset-fatal-error-code', 400, 'Invalid or missing token. Please start over.', 'invalid_request_error', 'invalid_state', [form]);
        }
    }

    async handleRegisterStage1() {
        const emailInput = document.getElementById('reg-email');
        const passwordInput = document.getElementById('reg-password');
        const errorDiv = document.getElementById('register-error-1');
        const btn = document.getElementById('btn-next-1');
        const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';

        if (!emailInput.value || !passwordInput.value) { this.showError(errorDiv, window.t('js.auth.err_fields')); return; }
        if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value)){ this.showError(errorDiv, window.t('js.auth.err_email')); return; }

        this.setLoading(btn, true);
        this.hideError(errorDiv);

        try {
            const res = await ApiService.post(API_ROUTES.AUTH.CHECK_EMAIL, { email: emailInput.value, csrf_token: csrfToken });
            if (res.success) {
                sessionStorage.setItem('reg_email', emailInput.value);
                sessionStorage.setItem('reg_password', passwordInput.value);
                this.router.navigate('/ProjectAurora/register/aditional-data'); 
            } else {
                this.showError(errorDiv, window.t(res.message));
            }
        } catch (error) { this.showError(errorDiv, window.t('js.auth.err_conn')); } 
        finally { this.setLoading(btn, false); }
    }

    async handleRegisterStage2() {
        const usernameInput = document.getElementById('reg-username');
        const errorDiv = document.getElementById('register-error-2');
        const btn = document.getElementById('btn-next-2');
        const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';

        const email = sessionStorage.getItem('reg_email');
        const password = sessionStorage.getItem('reg_password');

        if (!usernameInput.value) { this.showError(errorDiv, window.t('js.auth.err_user')); return; }

        this.setLoading(btn, true);
        this.hideError(errorDiv);

        try {
            const res = await ApiService.post(API_ROUTES.AUTH.SEND_CODE, { email, password, username: usernameInput.value, csrf_token: csrfToken });
            if (res.success) {
                sessionStorage.setItem('reg_username', usernameInput.value);
                sessionStorage.setItem('reg_dev_code', res.dev_code);
                this.router.navigate('/ProjectAurora/register/verification-account'); 
            } else { this.showError(errorDiv, window.t(res.message)); }
        } catch (error) { this.showError(errorDiv, window.t('js.auth.err_gen_code')); } 
        finally { this.setLoading(btn, false); }
    }

    async handleRegisterFinal() {
        const code = document.getElementById('reg-code').value;
        const errorDiv = document.getElementById('register-error-3');
        const btn = document.getElementById('btn-register-final');
        const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';
        const email = sessionStorage.getItem('reg_email');

        if (!code || code.length !== 6) { this.showError(errorDiv, window.t('js.auth.err_code')); return; }

        this.setLoading(btn, true);
        this.hideError(errorDiv);

        let prefsLocal = { language: 'en-us', openLinksNewTab: true };
        const localData = localStorage.getItem('aurora_prefs');
        if (localData) {
            try { prefsLocal = JSON.parse(localData); } catch(e){}
        }

        try {
            const res = await ApiService.post(API_ROUTES.AUTH.REGISTER, { 
                email, 
                code, 
                csrf_token: csrfToken,
                language: prefsLocal.language,
                open_links_new_tab: prefsLocal.openLinksNewTab
            });

            if (res.success) {
                sessionStorage.clear(); 
                window.location.href = '/ProjectAurora/';
            } else { this.showError(errorDiv, window.t(res.message)); this.setLoading(btn, false); }
        } catch (error) { this.showError(errorDiv, window.t('js.auth.err_conn')); this.setLoading(btn, false); }
    }

    async handleLogin() {
        const btn = document.getElementById('btn-login'); 
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;
        const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';
        const errorDiv = document.getElementById('login-error');

        if (!email || !password) {
            this.showError(errorDiv, window.t('js.auth.err_fields'));
            return;
        }

        this.setLoading(btn, true);
        this.hideError(errorDiv);

        try {
            const res = await ApiService.post(API_ROUTES.AUTH.LOGIN, { email, password, csrf_token: csrfToken });
            if (res.success) window.location.href = '/ProjectAurora/'; 
            else { this.showError(errorDiv, window.t(res.message)); this.setLoading(btn, false); }
        } catch (error) { this.showError(errorDiv, window.t('js.auth.err_conn')); this.setLoading(btn, false); }
    }

    async handleLogout(btn) {
        if (btn.classList.contains('is-loading')) return;
        btn.classList.add('is-loading');

        const spinnerContainer = document.createElement('div');
        spinnerContainer.className = 'component-menu-link-icon';
        spinnerContainer.innerHTML = '<div class="component-spinner-button dark-spinner"></div>';
        
        btn.appendChild(spinnerContainer);

        try { 
            await ApiService.post(API_ROUTES.AUTH.LOGOUT); 
            setTimeout(() => {
                window.location.href = '/ProjectAurora/login'; 
            }, 400);
        } catch (error) { 
            console.error(error); 
            btn.classList.remove('is-loading');
            spinnerContainer.remove();
        }
    }

    async handleForgotPassword() {
        const email = document.getElementById('forgot-email').value;
        const csrfToken = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';
        const btn = document.getElementById('btn-forgot-password');
        const errorDiv = document.getElementById('forgot-error');
        const linkContainer = document.getElementById('simulated-link-container');
        const linkDisplay = document.getElementById('simulated-link-display');

        if (!email) {
            this.showError(errorDiv, window.t('js.auth.err_fields'));
            return;
        }

        this.setLoading(btn, true);
        this.hideError(errorDiv);
        linkContainer.style.display = 'none';

        try {
            const res = await ApiService.post(API_ROUTES.AUTH.FORGOT_PASSWORD, { email, csrf_token: csrfToken });
            if (res.success) {
                linkDisplay.href = res.dev_link; linkDisplay.textContent = window.location.origin + res.dev_link;
                linkDisplay.dataset.nav = res.dev_link; linkContainer.style.display = 'block';
            } else { this.showError(errorDiv, window.t(res.message)); }
        } catch (error) { this.showError(errorDiv, window.t('js.auth.err_process')); } finally { this.setLoading(btn, false); }
    }

    async handleResetPassword() {
        const token = new URLSearchParams(window.location.search).get('token');
        const pass1 = document.getElementById('reset-password-1').value;
        const pass2 = document.getElementById('reset-password-2').value;
        const btn = document.getElementById('btn-reset-password');
        const errorDiv = document.getElementById('reset-error');
        const successDiv = document.getElementById('reset-success');
        const form = document.getElementById('form-reset-password');

        this.hideError(errorDiv);
        
        if (!token) { 
            this.showFatalJsonError('reset-fatal-error', 'reset-fatal-error-code', 400, 'Invalid client. Please start over.', 'invalid_request_error', 'invalid_state', [form]);
            return; 
        }

        if (!pass1 || !pass2) { this.showError(errorDiv, window.t('js.auth.err_fields')); return; }
        if (pass1 !== pass2) { this.showError(errorDiv, window.t('js.auth.err_pass_match')); return; }

        this.setLoading(btn, true);
        try {
            const res = await ApiService.post(API_ROUTES.AUTH.RESET_PASSWORD, { token, password: pass1, csrf_token: document.getElementById('csrf_token').value });
            if (res.success) {
                successDiv.style.display = 'block'; btn.style.display = 'none';
                setTimeout(() => window.location.href = '/ProjectAurora/login', 2000);
            } else { 
                this.showFatalJsonError('reset-fatal-error', 'reset-fatal-error-code', 409, window.t(res.message), 'invalid_request_error', 'token_expired_or_used', [form]);
                this.setLoading(btn, false); 
            }
        } catch (error) { this.showError(errorDiv, window.t('js.auth.err_update')); this.setLoading(btn, false); }
    }

    setLoading(btn, isLoading) {
        if(!btn) return;
        if (isLoading) {
            if (!btn.dataset.originalText) btn.dataset.originalText = btn.textContent.trim();
            btn.disabled = true; btn.innerHTML = '<div class="component-spinner-button"></div>';
        } else {
            btn.disabled = false; btn.textContent = btn.dataset.originalText || 'Continuar';
        }
    }
    showError(element, message) { if (element) { element.textContent = message; element.style.display = 'block'; } }
    hideError(element) { if (element) { element.style.display = 'none'; element.textContent = ''; } }
}