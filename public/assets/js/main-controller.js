export class MainController {
    constructor() {
        this.init();
    }

    init() {
        // Guardamos referencia al header para usarlo en varios métodos
        this.header = document.querySelector('.header');
        
        this.handleMobileSearch();
        this.handleResize();
        this.handleScrollShadow(); // Iniciamos la detección de scroll
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

    handleScrollShadow() {
        const scrollableArea = document.querySelector('.general-content-scrolleable');
        const topSection = document.querySelector('.general-content-top');

        if (scrollableArea && topSection) {
            scrollableArea.addEventListener('scroll', () => {
                // Si el scroll vertical es mayor a 0, agregamos la clase shadow
                if (scrollableArea.scrollTop > 0) {
                    topSection.classList.add('shadow');
                } else {
                    // Si volvemos arriba del todo, la quitamos
                    topSection.classList.remove('shadow');
                }
            });
        }
    }
}