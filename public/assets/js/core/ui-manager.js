/**
 * public/assets/js/core/ui-manager.js
 * Gestor global de componentes de interfaz (Dropdowns, Acordeones, Tabs, etc.)
 * Centraliza la interacción visual y emite eventos para la lógica de negocio.
 */

const SELECTORS = {
    // Dropdowns
    dropdown: {
        wrapper: '.trigger-select-wrapper',
        popover: '.popover-module',
        triggerSelector: '.trigger-selector',
        triggerText: '.trigger-select-text',
        triggerIcon: '.trigger-select-icon',
        optionClass: '.menu-link',
        activeClass: 'active',
        attrTrigger: '[data-trigger="dropdown"]',
        attrOption: '[data-action="select-option"]',
        attrSearch: '[data-action="filter-options"]'
    },
    // Accordions
    accordion: {
        item: '.component-accordion-item',
        header: '.component-accordion-header',
        chevron: '.component-accordion-chevron'
    }
};

export const UiManager = {
    init: () => {
        console.log("UiManager: Inicializado (Core System)");
        _initDropdowns();
        _initAccordions();
    },

    // Utilidad pública
    closeAllDropdowns: () => _closeDropdowns()
};

/* ==========================================================================
   LÓGICA DE DROPDOWNS (Menús Desplegables)
   ========================================================================== */
function _initDropdowns() {
    const S = SELECTORS.dropdown;

    document.addEventListener('click', (e) => {
        if (e.target.closest(S.attrSearch)) return;

        // 1. Selección de Opción
        const optionBtn = e.target.closest(S.attrOption);
        if (optionBtn) { 
            _handleOptionSelect(optionBtn, e, S); 
            return; 
        }

        // 2. Abrir/Cerrar Menú
        const triggerWrapper = e.target.closest(S.attrTrigger);
        if (triggerWrapper) {
            if (e.target.closest(S.triggerSelector)) { 
                _handleDropdownToggle(triggerWrapper, e, S); 
            }
            return;
        }
        
        // 3. Cerrar al hacer clic fuera
        if (!e.target.closest(S.wrapper)) { 
            _closeDropdowns(S); 
        }
    });

    document.addEventListener('input', (e) => {
        if (e.target.matches(S.attrSearch)) {
            _handleDropdownSearch(e.target, S);
        }
    });
}

function _handleDropdownToggle(wrapper, event, S) {
    event.stopPropagation();
    const menu = wrapper.querySelector(S.popover);
    const trigger = wrapper.querySelector(S.triggerSelector);
    if (!menu || !trigger) return;

    const isActive = menu.classList.contains(S.activeClass);
    
    // Primero cerramos cualquier otro menú abierto
    _closeDropdowns(S); 
    
    // Si NO estaba activo, lo abrimos ahora
    if (!isActive) {
        menu.classList.add(S.activeClass);    // 1. Hacer visible
        trigger.classList.add(S.activeClass);
        wrapper.classList.add('dropdown-active');
        
        // Auto-foco
        const input = menu.querySelector('input');
        if(input) setTimeout(() => input.focus(), 100);

        // === CORRECCIÓN SCROLL Y SOMBRA ===
        const list = menu.querySelector('.menu-list');
        const header = menu.querySelector('.menu-search-header');
        
        if (list) {
            // A. FORZAR SCROLL ARRIBA (Ahora que es visible, sí funciona)
            list.scrollTop = 0; 
            
            // B. Configurar evento de sombra
            if (header) {
                header.classList.remove('shadow'); // Reset inicial
                
                list.onscroll = () => {
                    if (list.scrollTop > 5) {
                        header.classList.add('shadow');
                    } else {
                        header.classList.remove('shadow');
                    }
                };
            }
        }
        // ==================================
    }
}

function _handleOptionSelect(option, event, S) {
    event.stopPropagation();
    event.preventDefault();
    const wrapper = option.closest(S.wrapper);
    if (!wrapper) return;

    const value = option.dataset.value;
    const label = option.dataset.label;
    const type = option.dataset.type; 

    // Actualizar UI
    const textEl = wrapper.querySelector(S.triggerText);
    if (textEl && label) textEl.innerText = label;
    
    const newIcon = option.querySelector('.material-symbols-rounded')?.innerText;
    const iconEl = wrapper.querySelector(S.triggerIcon);
    if(newIcon && iconEl) iconEl.innerText = newIcon;

    wrapper.querySelectorAll(S.optionClass).forEach(opt => opt.classList.remove(S.activeClass));
    option.classList.add(S.activeClass);

    _closeDropdowns(S);

    if (value && type) {
        const customEvent = new CustomEvent('ui:dropdown-selected', {
            detail: { type, value, label, element: option }
        });
        document.dispatchEvent(customEvent);
        console.log(`UiManager: Evento [ui:dropdown-selected] -> ${type}:${value}`);
    }
}

function _closeDropdowns(S = SELECTORS.dropdown) {
    document.querySelectorAll(S.popover).forEach(el => {
        // === LIMPIEZA PREVIA (Antes de ocultar) ===
        const input = el.querySelector('input');
        if(input) { input.value = ''; _handleDropdownSearch(input, S); }

        const header = el.querySelector('.menu-search-header');
        if (header) header.classList.remove('shadow');
        
        const list = el.querySelector('.menu-list');
        if (list) {
            list.scrollTop = 0; // Intento de reset
            list.onscroll = null; // Liberar memoria del evento
        }

        // === AHORA SÍ OCULTAMOS ===
        el.classList.remove(S.activeClass);
    });
    document.querySelectorAll(S.triggerSelector).forEach(el => el.classList.remove(S.activeClass));
    document.querySelectorAll(S.wrapper).forEach(el => el.classList.remove('dropdown-active'));
}

function _handleDropdownSearch(input, S) {
    const term = input.value.toLowerCase().trim();
    const wrapper = input.closest(S.popover);
    if (!wrapper) return;

    const links = wrapper.querySelectorAll(`${S.optionClass}[data-label]`);
    let hasVisible = false;

    links.forEach(link => {
        const label = (link.dataset.label || '').toLowerCase();
        if (label.includes(term)) {
            link.style.display = 'flex';
            hasVisible = true;
        } else {
            link.style.display = 'none';
        }
    });

    let emptyState = wrapper.querySelector('.menu-empty-state-dynamic');
    if (!hasVisible) {
        if (!emptyState) {
            emptyState = document.createElement('div');
            emptyState.className = 'menu-empty-state menu-empty-state-dynamic';
            emptyState.innerText = 'Sin resultados';
            emptyState.style.display = 'block';
            emptyState.style.padding = '12px';
            emptyState.style.textAlign = 'center';
            emptyState.style.color = 'var(--text-secondary)';
            emptyState.style.fontSize = '13px';
            wrapper.querySelector('.menu-list').appendChild(emptyState);
        }
    } else {
        if (emptyState) emptyState.remove();
    }
}

/* ==========================================================================
   LÓGICA DE ACCORDIONS
   ========================================================================== */
function _initAccordions() {
    const S = SELECTORS.accordion;
    document.addEventListener('click', (e) => {
        const header = e.target.closest(S.header);
        if (!header) return;
        const item = header.closest(S.item);
        if (!item) return;

        const parentGroup = item.parentElement;
        if (parentGroup) {
            const siblings = parentGroup.querySelectorAll(S.item);
            siblings.forEach(sibling => {
                if (sibling !== item) sibling.classList.remove('active');
            });
        }

        const isActive = item.classList.contains('active');
        if (isActive) {
            item.classList.remove('active');
        } else {
            item.classList.add('active');
            const event = new CustomEvent('ui:accordion-opened', {
                detail: { element: item, id: item.dataset.accordionId }
            });
            document.dispatchEvent(event);
        }
    });
}