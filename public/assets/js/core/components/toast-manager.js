const ToastManager = {
    init: () => {
    },

    show: (message, type = 'info', duration = 3000) => {
        let container = document.getElementById('toast-container');
        
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }

        if (window.USER_PREFS && window.USER_PREFS.extended_toast) {
            duration = 10000; 
        }

        const toast = document.createElement('div');
        toast.classList.add('toast-item');

        let iconName = 'info';
        if (type === 'success') iconName = 'check_circle';
        if (type === 'error') iconName = 'error';
        if (type === 'warning') iconName = 'warning';

        toast.innerHTML = `
            <span class="material-symbols-rounded toast-icon">${iconName}</span>
            <span class="toast-message"></span>
            <span class="material-symbols-rounded toast-close">close</span>
        `;

        const messageEl = toast.querySelector('.toast-message');
        if (messageEl) {
            messageEl.textContent = message;
        }

        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        const removeToast = () => {
            if (!toast.parentElement) return;

            toast.classList.remove('show');
            
            toast.addEventListener('transitionend', () => {
                if (toast.parentElement) {
                    toast.remove();
                }

                const currentContainer = document.getElementById('toast-container');
                if (currentContainer && currentContainer.children.length === 0) {
                    currentContainer.remove();
                }
            }, { once: true });
        };

        toast.querySelector('.toast-close').addEventListener('click', removeToast);
        setTimeout(removeToast, duration);
    }
};

export { ToastManager };