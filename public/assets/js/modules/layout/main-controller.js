/**
 * MainController.js
 * Encargado de la lógica de UI (Menús, Buscador, Interacción visual).
 * ACTUALIZADO: Manejo de temas, GESTOS MOBILE y AUTO-REPARACIÓN DE AVATAR (Con Retraso).
 */

import { SettingsService } from '../../core/api-services.js';

// ==========================================
// CONFIGURACIÓN
// ==========================================
let allowMultipleModules = false; 
let closeOnEsc = true;            

// Función centralizada para abrir/cerrar módulos
const toggleModuleState = (moduleElement) => {
    if (!moduleElement) return;
    
    // Si está desactivado (cerrado), lo abrimos
    if (moduleElement.classList.contains('disabled')) {
        moduleElement.classList.remove('disabled');
        
        // requestAnimationFrame asegura que el navegador renderice el display:flex
        // antes de añadir la clase active, permitiendo la transición de entrada.
        requestAnimationFrame(() => {
            moduleElement.classList.add('active');
        });
    } else {
        // Si está abierto, lo cerramos
        closeModuleWithAnimation(moduleElement);
    }
};

const closeModuleWithAnimation = (moduleElement) => {
    // 1. IMPORTANTE: Limpiar cualquier transformación inline (del drag) INMEDIATAMENTE.
    const content = moduleElement.querySelector('.menu-content');
    if(content) {
        content.style.transform = ''; 
    }

    // 2. Quitar clase active para iniciar la transición CSS de salida
    moduleElement.classList.remove('active');
    
    // 3. Esperar a que termine la transición (300ms según CSS) para ocultarlo del DOM
    setTimeout(() => {
        if (!moduleElement.classList.contains('active')) {
            moduleElement.classList.add('disabled');
        }
    }, 300);
};

const closeAllActiveModules = (exceptModule = null) => {
    const activeModules = document.querySelectorAll('.module-content.active');
    activeModules.forEach(mod => {
        if (mod !== exceptModule) {
            closeModuleWithAnimation(mod);
        }
    });
};

/* --- LÓGICA DE SOMBRA AL SCROLL --- */
const setupScrollEffects = () => {
    const scrollContainer = document.querySelector('.general-content-scrolleable');
    const topHeader = document.querySelector('.general-content-top');

    if (scrollContainer && topHeader) {
        scrollContainer.addEventListener('scroll', () => {
            if (scrollContainer.scrollTop > 0) {
                if (!topHeader.classList.contains('shadow')) {
                    topHeader.classList.add('shadow');
                }
            } else {
                topHeader.classList.remove('shadow');
            }
        });
    }
};

/* --- LÓGICA DE GESTOS (DRAG) PARA MOBILE --- */
const setupMobileGestures = () => {
    const profileModule = document.querySelector('[data-module="moduleProfile"]');
    if (!profileModule) return;

    const pillContainer = profileModule.querySelector('.pill-container');
    const menuContent = profileModule.querySelector('.menu-content');

    if (!pillContainer || !menuContent) return;

    let startY = 0;
    let currentY = 0;
    let isDragging = false;
    let menuHeight = 0;

    // 1. Iniciar arrastre
    pillContainer.addEventListener('touchstart', (e) => {
        startY = e.touches[0].clientY;
        menuHeight = menuContent.offsetHeight;
        isDragging = true;
        menuContent.style.transition = 'none';
    }, { passive: true });

    // 2. Mover
    pillContainer.addEventListener('touchmove', (e) => {
        if (!isDragging) return;
        currentY = e.touches[0].clientY;
        const deltaY = currentY - startY;
        if (deltaY > 0) {
            requestAnimationFrame(() => {
                menuContent.style.transform = `translateY(${deltaY}px)`;
            });
        }
    }, { passive: true });

    // 3. Soltar
    pillContainer.addEventListener('touchend', (e) => {
        if (!isDragging) return;
        isDragging = false;
        const deltaY = currentY - startY;
        menuContent.style.transition = 'transform 0.3s cubic-bezier(0.1, 0.7, 0.1, 1)';
        if (deltaY > (menuHeight * 0.4)) {
            closeModuleWithAnimation(profileModule);
        } else {
            requestAnimationFrame(() => {
                menuContent.style.transform = ''; 
            });
        }
        startY = 0; currentY = 0;
    });
};

/* --- SELF-HEALING AVATAR SYSTEM --- */
const setupAvatarRepairSystem = () => {
    // 1. MECÁNICO SILENCIOSO: Busca imágenes marcadas para reparación
    const imgToRepair = document.querySelector('img[data-needs-repair="true"]');
    
    if (imgToRepair) {
        console.log('MainController: Avatar roto detectado. Esperando 500ms para efecto visual...');
        
        // MODIFICADO: Agregamos setTimeout de 500ms antes de reparar
        setTimeout(() => {
            console.log('MainController: Iniciando reparación...');
            
            // USAMOS EL SERVICIO CENTRALIZADO
            SettingsService.repairAvatar()
                .then(data => {
                    if (data.status === 'success' && data.data && data.data.url) {
                        console.log('MainController: Avatar reparado exitosamente.');
                        
                        // A) Actualizar Header y Perfil
                        document.querySelectorAll('.component-card__avatar-image, .profile-img').forEach(img => {
                            // Pequeño fade-out/in visual opcional si quisieras, 
                            // pero el cambio de src directo es suficiente tras el delay.
                            img.src = data.data.url;
                            img.removeAttribute('data-needs-repair');
                        });

                        // B) Actualizar Lista de Admin (Si existe la tarjeta del usuario actual)
                        if (window.CURRENT_USER_ID) {
                            const adminUserCardImg = document.querySelector(`.component-entity-card[data-id="${window.CURRENT_USER_ID}"] .component-entity-avatar img`);
                            if (adminUserCardImg) {
                                adminUserCardImg.src = data.data.url;
                            }
                        }

                    } else {
                        console.warn('MainController: No se pudo reparar el avatar.', data);
                    }
                })
                .catch(err => {
                    console.error('MainController: Error conectando con servicio de reparación.', err);
                });
                
        }, 500); // <--- Retraso de 500ms
    }

    // 2. RED DE SEGURIDAD (On Error Fallback)
    document.addEventListener('error', function(e){
        const target = e.target;
        if (target.tagName === 'IMG' && 
           (target.classList.contains('profile-img') || 
            target.classList.contains('component-card__avatar-image') ||
            target.closest('.component-entity-avatar'))) {
            
            if (target.dataset.hasFallback === 'true') return;
            
            console.warn('MainController: Error de carga de imagen detectado. Aplicando fallback local.');
            
            // Fallback inmediato
            let fallbackId = 1;
            const card = target.closest('.component-entity-card');
            if(card && card.dataset.id) {
                fallbackId = (parseInt(card.dataset.id) % 5) + 1;
            } else if (window.CURRENT_USER_ID) {
                fallbackId = (window.CURRENT_USER_ID % 5) + 1;
            }

            target.src = `assets/uploads/avatars/fallback/${fallbackId}.png`; 
            target.dataset.hasFallback = 'true';
        }
    }, true); 
};

const setupEventListeners = () => {
    // 1. Configuración de Módulos (Surface y Profile)
    const moduleTriggers = [
        { action: 'toggleModuleSurface', target: 'moduleSurface' },
        { action: 'toggleModuleProfile', target: 'moduleProfile' }
    ];

    moduleTriggers.forEach(({ action, target }) => {
        const btn = document.querySelector(`[data-action="${action}"]`);
        const moduleEl = document.querySelector(`[data-module="${target}"]`);

        if (btn && moduleEl) {
            btn.addEventListener('click', (e) => {
                e.stopPropagation(); 
                if (!allowMultipleModules && moduleEl.classList.contains('disabled')) {
                    closeAllActiveModules(moduleEl);
                }
                toggleModuleState(moduleEl);
            });
        }
    });

    // 2. Cerrar módulos al hacer clic fuera (Backdrop)
    document.addEventListener('click', (e) => {
        const activeModules = document.querySelectorAll('.module-content.active');
        activeModules.forEach(mod => {
            const menuContent = mod.querySelector('.menu-content');
            
            // Si el clic es dentro del menú blanco, no cerrar
            if (menuContent && menuContent.contains(e.target)) {
                return;
            }
            // Si el clic es en el botón toggle, ignorar (ya tiene su evento)
            if (e.target.closest('[data-action^="toggleModule"]')) {
                return;
            }

            // Si es clic en el fondo oscuro -> Cerrar con animación
            closeModuleWithAnimation(mod);
        });
    });

    // 3. Cerrar con tecla Escape
    if (closeOnEsc) {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAllActiveModules();
            }
        });
    }
};

/* --- LÓGICA DE TEMAS --- */
export const applyAppTheme = (themePreference) => {
    const html = document.documentElement;
    html.classList.remove('light-theme', 'dark-theme', 'system-theme-pending');

    if (themePreference === 'system') {
        const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        html.classList.add(systemDark ? 'dark-theme' : 'light-theme');
    } else if (themePreference === 'dark') {
        html.classList.add('dark-theme');
    } else {
        html.classList.add('light-theme');
    }
};

const setupSystemThemeListener = () => {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {
        if (window.USER_PREFS && window.USER_PREFS.theme === 'system') {
            applyAppTheme('system');
        }
    });
};

const initTheme = () => {
    if (window.USER_PREFS && window.USER_PREFS.theme) {
        applyAppTheme(window.USER_PREFS.theme);
    }
    setupSystemThemeListener();
};

export const initMainController = () => {
    console.log('MainController: Inicializando UI con Gestos...');
    setupEventListeners();
    setupMobileGestures();
    setupScrollEffects();
    initTheme(); 
    setupAvatarRepairSystem(); 
};