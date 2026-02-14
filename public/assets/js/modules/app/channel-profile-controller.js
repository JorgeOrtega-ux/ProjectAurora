/* public/assets/js/modules/app/channel-profile-controller.js */

import { navigateTo } from '../../core/utils/url-manager.js';
import { ToastManager } from '../../core/components/toast-manager.js';
import { ApiService } from '../../core/services/api-service.js';
import { ApiRoutes } from '../../core/services/api-routes.js'; 

let hoverTimeout = null;
let activeVideo = null;
let activeHls = null;

// Variables para el Cropper
let currentScale = 1;
let bannerImage = null; 
let isDragging = false;
let startX, startY;
let translateX = 0, translateY = 0;

export const ChannelProfileController = {
    init: () => {
        console.log("ChannelProfileController: Inicializado con Recorte Real");
        initFeedInteractions();
        initBannerUploader();
    }
};

function initBannerUploader() {
    const triggerBtn = document.getElementById('btn-trigger-banner');
    const fileInput = document.getElementById('banner-upload-input');
    const modal = document.getElementById('banner-modal');
    const previewImg = document.getElementById('banner-preview-image');
    const cropContainer = document.getElementById('crop-container');
    const zoomSlider = document.getElementById('zoom-slider');
    const cancelBtn = document.getElementById('btn-cancel-crop');
    const saveBtn = document.getElementById('btn-save-crop');
    const bannerDisplay = document.getElementById('channel-banner-display');
    const desktopZone = document.querySelector('.zone-desktop');

    if (!triggerBtn || !fileInput) return;

    // 1. Trigger
    triggerBtn.addEventListener('click', () => fileInput.click());

    // 2. File Change
    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;

        if (!file.type.startsWith('image/')) {
            ToastManager.show('Por favor selecciona una imagen válida.', 'error');
            return;
        }

        const reader = new FileReader();
        reader.onload = (evt) => {
            bannerImage = evt.target.result;
            previewImg.src = bannerImage;
            
            // RESET
            currentScale = 1;
            zoomSlider.value = 1;
            translateX = 0;
            translateY = 0;
            updateTransform(previewImg);
            
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('active'), 10);
        };
        reader.readAsDataURL(file);
    });

    // 3. Zoom
    if (zoomSlider && previewImg) {
        zoomSlider.addEventListener('input', (e) => {
            currentScale = parseFloat(e.target.value);
            updateTransform(previewImg);
        });
    }

    // 4. Drag Logic
    if (cropContainer && previewImg) {
        cropContainer.addEventListener('mousedown', (e) => {
            e.preventDefault(); 
            isDragging = true;
            startX = e.clientX - translateX;
            startY = e.clientY - translateY;
            cropContainer.style.cursor = 'grabbing';
        });

        window.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            e.preventDefault();
            translateX = e.clientX - startX;
            translateY = e.clientY - startY;
            updateTransform(previewImg);
        });

        window.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                cropContainer.style.cursor = 'grab';
            }
        });
    }

    // 5. Cancelar
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => closeModal(modal, fileInput));
    }

    // 6. GUARDAR CON RECORTE REAL (CANVAS + API UPLOAD)
    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            if (previewImg && desktopZone) {
                // A. Obtenemos las coordenadas visuales
                const imgRect = previewImg.getBoundingClientRect();
                const zoneRect = desktopZone.getBoundingClientRect();
                
                const scaleFactorX = previewImg.naturalWidth / imgRect.width;
                const scaleFactorY = previewImg.naturalHeight / imgRect.height;
                
                const cropX = (zoneRect.left - imgRect.left) * scaleFactorX;
                const cropY = (zoneRect.top - imgRect.top) * scaleFactorY;
                
                const cropWidth = zoneRect.width * scaleFactorX;
                const cropHeight = zoneRect.height * scaleFactorY;

                // D. Creamos un Canvas
                const canvas = document.createElement('canvas');
                canvas.width = zoneRect.width;
                canvas.height = zoneRect.height;
                const ctx = canvas.getContext('2d');

                // E. Dibujamos solo la parte visible
                try {
                    ctx.drawImage(
                        previewImg, 
                        cropX, cropY, cropWidth, cropHeight, // Source
                        0, 0, canvas.width, canvas.height    // Destination
                    );

                    // F. Convertir a Blob y subir a la API
                    canvas.toBlob((blob) => {
                        if (!blob) {
                            ToastManager.show('Error al procesar la imagen.', 'error');
                            return;
                        }

                        // Creamos el FormData
                        const formData = new FormData();
                        formData.append('banner', blob, 'banner.jpg');

                        // Enviamos a la API
                        ToastManager.show('Subiendo banner...', 'info');
                        
                        // FIX: Enviamos el objeto ApiRoutes.Settings.UploadBanner directamente para validar la arquitectura
                        ApiService.post(ApiRoutes.Settings.UploadBanner, formData)
                            .then(response => {
                                if (response.success) {
                                    // G. Éxito: Actualizar UI
                                    const croppedDataUrl = canvas.toDataURL('image/jpeg', 0.9);
                                    
                                    if (bannerDisplay) {
                                        bannerDisplay.style.backgroundImage = `url('${croppedDataUrl}')`;
                                        bannerDisplay.style.backgroundSize = 'cover'; 
                                        bannerDisplay.style.backgroundPosition = 'center';
                                    }
                                    
                                    ToastManager.show('Banner actualizado correctamente', 'success');
                                    closeModal(modal, fileInput);
                                } else {
                                    ToastManager.show(response.message || 'Error al subir banner', 'error');
                                }
                            })
                            .catch(err => {
                                console.error("Error API:", err);
                                ToastManager.show('Error de conexión al subir.', 'error');
                            });

                    }, 'image/jpeg', 0.9);
                    
                } catch (err) {
                    console.error("Error al recortar", err);
                    ToastManager.show('Error al procesar la imagen', 'error');
                }
            }
        });
    }
}

function updateTransform(element) {
    if(!element) return;
    element.style.transform = `translate(${translateX}px, ${translateY}px) scale(${currentScale})`;
}

function closeModal(modal, fileInput) {
    modal.classList.remove('active');
    setTimeout(() => {
        modal.style.display = 'none';
        fileInput.value = '';
    }, 300);
}

function initFeedInteractions() {
    const container = document.getElementById('channel-feed-grid');
    if (!container) return;

    container.addEventListener('click', (e) => {
        const card = e.target.closest('.video-card');
        if (card && card.dataset.uuid) {
            e.preventDefault();
            e.stopPropagation();
            const isShort = card.dataset.orientation === 'portrait';
            if (isShort) {
                window.location.href = window.BASE_PATH + 'shorts/' + card.dataset.uuid;
            } else {
                navigateTo('watch', { v: card.dataset.uuid });
            }
        }
    });

    container.addEventListener('mouseenter', (e) => {
        const card = e.target.closest('.video-card');
        if (card) {
            if (hoverTimeout) clearTimeout(hoverTimeout);
            hoverTimeout = setTimeout(() => startPreview(card), 600);
        }
    }, true);

    container.addEventListener('mouseleave', (e) => {
        const card = e.target.closest('.video-card');
        if (card) {
            if (hoverTimeout) clearTimeout(hoverTimeout);
            stopPreview(card);
        }
    }, true);
}

function startPreview(card) {
    if (card.querySelector('video')) return;
    const hlsUrl = card.dataset.hls;
    if (!hlsUrl) return;

    const topContainer = card.querySelector('.video-top');
    if (!topContainer) return;

    const video = document.createElement('video');
    video.className = 'video-preview';
    video.style.cssText = `
        position: absolute; top: 0; left: 0; width: 100%; height: 100%; 
        object-fit: ${card.dataset.orientation === 'portrait' ? 'contain' : 'cover'}; 
        z-index: 2; opacity: 0; transition: opacity 0.3s ease; background: #000;
    `;
    
    video.muted = true;
    video.autoplay = true;
    video.playsInline = true;
    
    topContainer.appendChild(video);
    activeVideo = video;

    if (Hls.isSupported()) {
        const hls = new Hls();
        hls.loadSource(window.BASE_PATH + hlsUrl);
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, () => {
            const p = video.play();
            if (p !== undefined) p.then(() => video.style.opacity = '1').catch(()=>{});
        });
        hls.on(Hls.Events.ERROR, (e, d) => { if (d.fatal) stopPreview(card); });
        activeHls = hls;
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = window.BASE_PATH + hlsUrl;
        video.addEventListener('loadedmetadata', () => {
            video.play();
            video.style.opacity = '1';
        });
    }
}

function stopPreview(card) {
    if (activeHls) { activeHls.destroy(); activeHls = null; }
    if (activeVideo) { activeVideo.pause(); activeVideo.remove(); activeVideo = null; }
}