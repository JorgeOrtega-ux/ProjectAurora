export class MainController {
    constructor() {
        this.init();
    }

    init() {
        // Guardamos referencia al header para usarlo en varios métodos
        this.header = document.querySelector('.header');
        
        this.handleMobileSearch();
        this.handleResize();
    }

    handleMobileSearch() {
        const searchTrigger = document.querySelector('.mobile-search-trigger');

        if (searchTrigger && this.header) {
            searchTrigger.addEventListener('click', (e) => {
                e.preventDefault();
                this.header.classList.toggle('is-search-active');
            });
        }
    }

    handleResize() {
        // Escuchamos el evento de redimensionar la ventana
        window.addEventListener('resize', () => {
            // Si la pantalla es mayor a 768px (Desktop) y la búsqueda está activa...
            if (window.innerWidth > 768 && this.header.classList.contains('is-search-active')) {
                // ...la desactivamos automáticamente.
                this.header.classList.remove('is-search-active');
            }
        });
    }
}