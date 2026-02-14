/* public/assets/js/modules/app/channel-profile-controller.js */

import { navigateTo } from '../../core/utils/url-manager.js';
import { ToastManager } from '../../core/components/toast-manager.js';
import { ApiService } from '../../core/services/api-service.js';
import { ApiRoutes } from '../../core/services/api-routes.js'; 

let hoverTimeout = null;
let activeVideo = null;
let activeHls = null;

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

    triggerBtn.addEventListener('click', () => fileInput.click());

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

    if (zoomSlider && previewImg) {
        zoomSlider.addEventListener('input', (e) => {
            currentScale = parseFloat(e.target.value);
            updateTransform(previewImg);
        });
    }

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

    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => closeModal(modal, fileInput));
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            if (previewImg && desktopZone) {
                const imgRect = previewImg.getBoundingClientRect();
                const zoneRect = desktopZone.getBoundingClientRect();
                
                const scaleFactorX = previewImg.naturalWidth / imgRect.width;
                const scaleFactorY = previewImg.naturalHeight / imgRect.height;
                
                const cropX = (zoneRect.left - imgRect.left) * scaleFactorX;
                const cropY = (zoneRect.top - imgRect.top) * scaleFactorY;
                
                const cropWidth = zoneRect.width * scaleFactorX;
                const cropHeight = zoneRect.height * scaleFactorY;

                const canvas = document.createElement('canvas');
                canvas.width = zoneRect.width;
                canvas.height = zoneRect.height;
                const ctx = canvas.getContext('2d');

                try {
                    ctx.drawImage(
                        previewImg, 
                        cropX, cropY, cropWidth, cropHeight,
                        0, 0, canvas.width, canvas.height
                    );

                    canvas.toBlob((blob) => {
                        if (!blob) {
                            ToastManager.show('Error al procesar la imagen.', 'error');
                            return;
                        }

                        const formData = new FormData();
                        formData.append('banner', blob, 'banner.jpg');

                        ToastManager.show('Subiendo banner...', 'info');
                        
                        // CORRECCIÓN FINAL: Pasar el objeto de ruta directamente
                        ApiService.post(ApiRoutes.Settings.UploadBanner, formData)
                            .then(response => {
                                if (response.success) {
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

// ... Resto de funciones (initFeedInteractions, startPreview, stopPreview) se mantienen igual