/**
 * public/assets/js/modules/app/whiteboard-controller.js
 */

export const WhiteboardController = {
    state: {
        scale: 1,
        panning: false,
        selecting: false,
        pointX: 0, 
        pointY: 0,
        startX: 0,
        startY: 0,
        selStartX: 0,
        selStartY: 0,
        mouseX: 0, 
        mouseY: 0,
        isShiftPressed: false
    },
    
    config: {
        minScale: 0.1,
        maxScale: 5.0,
        boardWidth: 3000,
        boardHeight: 3000,
        scrollMargin: 300,
        sliderFillColor: '#000000', 
        sliderBgColor: '#e0e0e0'
    },

    elements: {
        viewport: null,
        surface: null,
        status: null,
        zoomSlider: null,
        zoomDisplay: null,
        btnCenter: null,
        selectionBox: null,
        drawer: null,
        drawerTitle: null,
        btnCloseDrawer: null
    },

    init: () => {
        console.log("WhiteboardController: Iniciando...");
        
        // Mapeo de elementos con los nuevos IDs (wb-*)
        WhiteboardController.elements.viewport = document.getElementById('wb-viewport');
        WhiteboardController.elements.surface = document.getElementById('wb-surface');
        WhiteboardController.elements.status = document.getElementById('wb-status-text');
        WhiteboardController.elements.zoomSlider = document.getElementById('wb-zoom-slider');
        WhiteboardController.elements.zoomDisplay = document.getElementById('wb-zoom-display');
        WhiteboardController.elements.btnCenter = document.getElementById('wb-btn-center');
        WhiteboardController.elements.selectionBox = document.getElementById('wb-selection-box');
        
        WhiteboardController.elements.drawer = document.getElementById('wb-drawer');
        WhiteboardController.elements.drawerTitle = document.getElementById('wb-drawer-title');
        WhiteboardController.elements.btnCloseDrawer = document.getElementById('wb-close-drawer');

        if (!WhiteboardController.elements.viewport || !WhiteboardController.elements.surface) {
            console.error("WhiteboardController: No se encontró viewport o surface.");
            return;
        }

        // Reset inicial
        WhiteboardController.elements.surface.style.left = ''; 
        WhiteboardController.elements.surface.style.top = ''; 
        WhiteboardController.centerBoard();
        
        WhiteboardController.bindEvents();
        WhiteboardController.bindSidebarEvents();
        WhiteboardController.updateSliderFill();
    },

    bindEvents: () => {
        const { viewport, surface, zoomSlider, btnCenter } = WhiteboardController.elements;

        // 1. RASTREO MOUSE
        viewport.addEventListener('mousemove', (e) => {
            const rect = viewport.getBoundingClientRect();
            WhiteboardController.state.mouseX = e.clientX - rect.left;
            WhiteboardController.state.mouseY = e.clientY - rect.top;
            WhiteboardController.updateStatusText();
        });

        // 2. PANNING VS SELECCIÓN
        viewport.addEventListener('mousedown', (e) => {
            // Solo actuar si clicamos directamente en el viewport o superficie (no en notas)
            if (e.target !== viewport && e.target !== surface) return;

            if (e.shiftKey || e.button === 1) { // Shift+Click o Botón Central
                // PANNING
                WhiteboardController.state.panning = true;
                WhiteboardController.state.startX = e.clientX - WhiteboardController.state.pointX;
                WhiteboardController.state.startY = e.clientY - WhiteboardController.state.pointY;
                viewport.style.cursor = 'grabbing';
            } else {
                // SELECCIÓN (Botón izquierdo normal)
                if (e.button === 0) {
                    WhiteboardController.state.selecting = true;
                    const rect = viewport.getBoundingClientRect();
                    WhiteboardController.state.selStartX = e.clientX - rect.left;
                    WhiteboardController.state.selStartY = e.clientY - rect.top;
                    WhiteboardController.updateSelectionBox(WhiteboardController.state.selStartX, WhiteboardController.state.selStartY, 0, 0);
                }
            }
        });

        window.addEventListener('mousemove', (e) => {
            if (WhiteboardController.state.panning) {
                e.preventDefault();
                let newX = e.clientX - WhiteboardController.state.startX;
                let newY = e.clientY - WhiteboardController.state.startY;
                WhiteboardController.updateTransform(newX, newY, WhiteboardController.state.scale);
            }
            if (WhiteboardController.state.selecting) {
                e.preventDefault();
                const rect = viewport.getBoundingClientRect();
                const currentX = e.clientX - rect.left;
                const currentY = e.clientY - rect.top;
                const width = Math.abs(currentX - WhiteboardController.state.selStartX);
                const height = Math.abs(currentY - WhiteboardController.state.selStartY);
                const left = Math.min(currentX, WhiteboardController.state.selStartX);
                const top = Math.min(currentY, WhiteboardController.state.selStartY);
                WhiteboardController.updateSelectionBox(left, top, width, height);
            }
        });

        window.addEventListener('mouseup', () => {
            if (WhiteboardController.state.panning) {
                WhiteboardController.state.panning = false;
                viewport.style.cursor = WhiteboardController.state.isShiftPressed ? 'grab' : 'default';
            }
            if (WhiteboardController.state.selecting) {
                WhiteboardController.state.selecting = false;
                WhiteboardController.hideSelectionBox();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Shift') {
                WhiteboardController.state.isShiftPressed = true;
                if (!WhiteboardController.state.panning && !WhiteboardController.state.selecting) viewport.style.cursor = 'grab';
            }
        });

        document.addEventListener('keyup', (e) => {
            if (e.key === 'Shift') {
                WhiteboardController.state.isShiftPressed = false;
                if (!WhiteboardController.state.panning && !WhiteboardController.state.selecting) viewport.style.cursor = 'default';
            }
        });

        // 3. ZOOM (WHEEL)
        viewport.addEventListener('wheel', (e) => {
            e.preventDefault();
            if (e.ctrlKey) {
                // Zoom
                const xs = (e.clientX - WhiteboardController.state.pointX) / WhiteboardController.state.scale;
                const ys = (e.clientY - WhiteboardController.state.pointY) / WhiteboardController.state.scale;
                const delta = -Math.sign(e.deltaY);
                const step = 0.1;
                let newScale = WhiteboardController.state.scale + (delta * step * WhiteboardController.state.scale);
                newScale = Math.min(Math.max(newScale, WhiteboardController.config.minScale), WhiteboardController.config.maxScale);
                const newX = e.clientX - xs * newScale;
                const newY = e.clientY - ys * newScale;
                WhiteboardController.updateTransform(newX, newY, newScale);
            } else {
                // Scroll normal (pan)
                const scrollSpeed = 1; 
                let newX = WhiteboardController.state.pointX - (e.deltaX * scrollSpeed);
                let newY = WhiteboardController.state.pointY - (e.deltaY * scrollSpeed);
                WhiteboardController.updateTransform(newX, newY, WhiteboardController.state.scale);
            }
        }, { passive: false });

        // 4. SLIDER
        if (zoomSlider) {
            zoomSlider.addEventListener('input', (e) => {
                const percent = parseInt(e.target.value, 10);
                const newScale = percent / 100;
                const viewportRect = viewport.getBoundingClientRect();
                const centerX = viewportRect.width / 2;
                const centerY = viewportRect.height / 2;
                
                // Zoom hacia el centro de la pantalla
                const xs = (centerX - WhiteboardController.state.pointX) / WhiteboardController.state.scale;
                const ys = (centerY - WhiteboardController.state.pointY) / WhiteboardController.state.scale;
                
                const newX = centerX - xs * newScale;
                const newY = centerY - ys * newScale;
                
                WhiteboardController.updateTransform(newX, newY, newScale);
                WhiteboardController.updateSliderFill();
            });
        }
        
        // 5. CENTRAR
        if (btnCenter) {
            btnCenter.addEventListener('click', () => {
                WhiteboardController.centerBoard();
            });
        }
    },

    bindSidebarEvents: () => {
        const { drawer, drawerTitle, btnCloseDrawer } = WhiteboardController.elements;
        if (!drawer) return;

        const toggleBtns = document.querySelectorAll('[data-drawer]');

        toggleBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                
                const contentId = btn.getAttribute('data-drawer');
                const contentEl = document.getElementById(contentId);
                const buttonTitle = btn.getAttribute('title');

                if (drawer.classList.contains('active') && contentEl.classList.contains('active')) {
                    WhiteboardController.closeDrawer();
                    return;
                }

                WhiteboardController.openDrawer(contentId, buttonTitle);
                
                toggleBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });

        if (btnCloseDrawer) {
            btnCloseDrawer.addEventListener('click', () => {
                WhiteboardController.closeDrawer();
            });
        }
    },

    openDrawer: (contentId, title) => {
        const { drawer, drawerTitle } = WhiteboardController.elements;
        
        document.querySelectorAll('.wb-drawer-content').forEach(el => el.classList.remove('active'));
        
        const target = document.getElementById(contentId);
        if (target) target.classList.add('active');

        if (drawerTitle) drawerTitle.innerText = title || 'Menú';

        drawer.classList.add('active');
    },

    closeDrawer: () => {
        const { drawer } = WhiteboardController.elements;
        if (drawer) drawer.classList.remove('active');
        document.querySelectorAll('[data-drawer]').forEach(btn => btn.classList.remove('active'));
    },

    updateSelectionBox: (left, top, width, height) => {
        const { selectionBox } = WhiteboardController.elements;
        if (!selectionBox) return;
        selectionBox.style.display = 'block';
        selectionBox.style.left = `${left}px`;
        selectionBox.style.top = `${top}px`;
        selectionBox.style.width = `${width}px`;
        selectionBox.style.height = `${height}px`;
    },

    hideSelectionBox: () => {
        const { selectionBox } = WhiteboardController.elements;
        if (selectionBox) selectionBox.style.display = 'none';
    },

    updateTransform: (x, y, scale) => {
        const { viewport, surface, zoomSlider, zoomDisplay } = WhiteboardController.elements;
        const { boardWidth, boardHeight, scrollMargin } = WhiteboardController.config;
        
        if(!viewport) return;

        const viewportRect = viewport.getBoundingClientRect();
        const currentWidth = boardWidth * scale;
        const currentHeight = boardHeight * scale;
        
        // Límites para que no se pierda la pizarra
        const minX = viewportRect.width - currentWidth - scrollMargin;
        const maxX = scrollMargin;
        const minY = viewportRect.height - currentHeight - scrollMargin;
        const maxY = scrollMargin;

        let constrainedX, constrainedY;

        // Si es más pequeña que el viewport, centrar
        if (currentWidth < viewportRect.width) constrainedX = (viewportRect.width - currentWidth) / 2;
        else constrainedX = Math.min(maxX, Math.max(x, minX));

        if (currentHeight < viewportRect.height) constrainedY = (viewportRect.height - currentHeight) / 2;
        else constrainedY = Math.min(maxY, Math.max(y, minY));

        WhiteboardController.state.pointX = constrainedX;
        WhiteboardController.state.pointY = constrainedY;
        WhiteboardController.state.scale = scale;
        
        surface.style.transform = `translate(${constrainedX}px, ${constrainedY}px) scale(${scale})`;

        // Actualizar UI
        const currentSliderValue = Math.round(scale * 100);
        if (zoomSlider && Math.abs(zoomSlider.value - currentSliderValue) > 1) {
            zoomSlider.value = currentSliderValue;
            WhiteboardController.updateSliderFill();
        }
        if (zoomDisplay) zoomDisplay.innerText = `${currentSliderValue}%`;
        WhiteboardController.updateStatusText();
    },

    updateSliderFill: () => {
        const slider = WhiteboardController.elements.zoomSlider;
        if (!slider) return;
        const { sliderFillColor, sliderBgColor } = WhiteboardController.config;
        const min = parseFloat(slider.min);
        const max = parseFloat(slider.max);
        const val = parseFloat(slider.value);
        const percentage = ((val - min) / (max - min)) * 100;
        
        // Usamos una variable CSS si queremos que coincida con el tema, o negro por defecto
        slider.style.background = `linear-gradient(to right, ${sliderFillColor} 0%, ${sliderFillColor} ${percentage}%, ${sliderBgColor} ${percentage}%, ${sliderBgColor} 100%)`;
    },

    updateStatusText: () => {
        const { status } = WhiteboardController.elements;
        const { scale, pointX, pointY, mouseX, mouseY } = WhiteboardController.state;
        const { boardWidth, boardHeight } = WhiteboardController.config;
        if (!status) return;
        
        // Calcular coordenadas "reales" en la superficie
        const rawX = (mouseX - pointX) / scale;
        const rawY = (mouseY - pointY) / scale;
        
        // Coordenadas relativas al centro del tablero (0,0 es el centro)
        const centeredX = rawX - (boardWidth / 2);
        const centeredY = rawY - (boardHeight / 2);
        
        status.innerText = `X: ${Math.round(centeredX)} Y: ${Math.round(centeredY)}`;
    },

    centerBoard: () => {
        const { viewport } = WhiteboardController.elements;
        if(!viewport) return;
        const vpW = viewport.clientWidth;
        const vpH = viewport.clientHeight;
        // Calcular centro
        const centerX = (vpW - WhiteboardController.config.boardWidth) / 2;
        const centerY = (vpH - WhiteboardController.config.boardHeight) / 2;
        WhiteboardController.updateTransform(centerX, centerY, 1);
    }
};