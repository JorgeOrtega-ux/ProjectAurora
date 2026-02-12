

import { ApiService } from '../../core/services/api-service.js';
import { ToastManager } from '../../core/components/toast-manager.js';
import { I18nManager } from '../../core/utils/i18n-manager.js';

const CHUNK_SIZE = 2 * 1024 * 1024; // 2MB
const MAX_FILES = 5;

// Estado local de videos
// { uuid, title, status: 'uploading'|'processing'|'ready'|'published', progress: 0-100, data: {} }
let _videosState = []; 
let _activeVideoUuid = null; // Cuál se está editando actualmente
let _activeBatchId = null;

export const UploadController = {
    init: async () => {
        const container = document.querySelector('[data-section="channel-upload"]');
        if (!container) return;

        console.log("UploadController: Inicializado (Multi-Tab Mode)");
        
        _videosState = [];
        _activeVideoUuid = null;
        _activeBatchId = null;
        
        initDropzone();
        initEditorEvents();
        
        // Escuchar eventos de WebSockets para "Processing Complete"
        document.removeEventListener('socket:processing_complete', onProcessingComplete);
        document.addEventListener('socket:processing_complete', onProcessingComplete);
        
        // Recuperar borradores pendientes al recargar
        await checkPendingUploads();
    }
};

// ==========================================
// 1. INICIALIZACIÓN Y CARGA
// ==========================================

async function checkPendingUploads() {
    try {
        const res = await ApiService.post(ApiService.Routes.Studio.GetPending, new FormData(), { signal: window.PAGE_SIGNAL });
        if (res.success && res.videos.length > 0) {
            
            // Ocultar dropzone si hay videos
            document.getElementById('upload-dropzone').classList.add('d-none');
            document.getElementById('video-editor-area').classList.remove('d-none');

            // Reconstruir estado
            res.videos.forEach(v => {
                _videosState.push({
                    uuid: v.uuid,
                    title: v.title || 'Sin título',
                    status: (v.status === 'waiting_for_metadata') ? 'ready' : 'processing',
                    progress: 100, // Ya subido
                    thumbnail: v.thumbnail_src || null,
                    description: v.description || ''
                });
            });

            renderTabs();
            // Seleccionar el primero
            if (_videosState.length > 0) {
                switchEditor(_videosState[0].uuid);
            }
        }
    } catch (e) {
        if (!e.isAborted) console.error("Error checking pending:", e);
    }
}

function initDropzone() {
    const dropzone = document.getElementById('upload-dropzone');
    const input = document.getElementById('input-video-files');
    const btnSelect = document.getElementById('btn-select-files');
    const btnTrigger = document.getElementById('btn-trigger-files');

    if (!dropzone || !input) return;

    const openSelector = () => input.click();
    if(btnSelect) btnSelect.addEventListener('click', openSelector);
    if(btnTrigger) btnTrigger.addEventListener('click', openSelector);

    input.addEventListener('change', (e) => handleFiles(e.target.files));

    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('drag-active');
    });
    
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-active'));
    
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('drag-active');
        handleFiles(e.dataTransfer.files);
    });
}

function handleFiles(files) {
    const validFiles = Array.from(files).filter(f => f.type.startsWith('video/'));
    
    if (validFiles.length === 0) {
        ToastManager.show('Selecciona archivos de video válidos.', 'warning');
        return;
    }

    if ((_videosState.length + validFiles.length) > MAX_FILES) {
        ToastManager.show(`Máximo ${MAX_FILES} videos por sesión.`, 'warning');
        return;
    }

    // Ocultar Dropzone, Mostrar Editor
    document.getElementById('upload-dropzone').classList.add('d-none');
    document.getElementById('video-editor-area').classList.remove('d-none');

    if (!_activeBatchId) {
        _activeBatchId = 'batch_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    // Procesar cada archivo
    validFiles.forEach(file => {
        // Título por defecto: Nombre de archivo sin extensión
        const defaultTitle = file.name.replace(/\.[^/.]+$/, "");
        
        // Crear entrada temporal en estado (sin UUID aún)
        const tempId = 'temp_' + Date.now() + Math.random();
        
        const videoEntry = {
            uuid: tempId, // Temporal hasta que el server responda
            fileObject: file,
            title: defaultTitle,
            description: '',
            status: 'uploading',
            progress: 0,
            thumbnail: null
        };
        
        _videosState.push(videoEntry);
        
        // Iniciar subida individual
        startUpload(videoEntry);
    });

    renderTabs();
    
    // Si no hay video activo seleccionado, seleccionar el primero que acabamos de agregar
    if (!_activeVideoUuid) {
        // Buscamos el primer tempId que acabamos de añadir
        const firstNew = _videosState.find(v => v.status === 'uploading');
        if (firstNew) switchEditor(firstNew.uuid);
    }
}

// ==========================================
// 2. LÓGICA DE SUBIDA (Individual)
// ==========================================

async function startUpload(videoEntry) {
    const file = videoEntry.fileObject;
    
    try {
        // A) Inicializar en Backend
        const initData = new FormData();
        initData.append('action', 'init_upload');
        initData.append('batch_id', _activeBatchId);
        initData.append('file_name', file.name);

        const initRes = await ApiService.post(ApiService.Routes.Studio.InitUpload, initData);
        
        if (!initRes.success) throw new Error(initRes.message);
        
        // Actualizar UUID real en el estado
        const oldId = videoEntry.uuid;
        videoEntry.uuid = initRes.video_uuid;
        
        // Si el usuario estaba viendo el "temp", actualizar la referencia activa
        if (_activeVideoUuid === oldId) {
            _activeVideoUuid = videoEntry.uuid;
        }
        
        renderTabs(); // Refrescar para actualizar IDs en el DOM

        // B) Subir Chunks
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        
        for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
            const start = chunkIndex * CHUNK_SIZE;
            const end = Math.min(start + CHUNK_SIZE, file.size);
            const chunk = file.slice(start, end);

            const formData = new FormData();
            formData.append('action', 'upload_chunk');
            formData.append('video_uuid', videoEntry.uuid);
            formData.append('chunk_index', chunkIndex);
            formData.append('chunk', chunk);
            formData.append('is_last', (chunkIndex === totalChunks - 1) ? 'true' : 'false');

            const chunkRes = await ApiService.post(ApiService.Routes.Studio.UploadChunk, formData);
            if (!chunkRes.success) throw new Error(chunkRes.message);

            // Actualizar progreso
            const percent = Math.round(((chunkIndex + 1) / totalChunks) * 100);
            videoEntry.progress = percent;
            
            // Si es el video activo, actualizar barra global (opcional) o UI
            if (_activeVideoUuid === videoEntry.uuid) {
                updateEditorStatusUI(videoEntry);
            }
            
            // Actualizar badge spinner/progress? (Simplificado: Solo spinner en CSS)
        }

        // C) Finalizado Subida -> Backend responde con 'queued'
        // El servidor ya respondió en el último chunk.
        
        videoEntry.status = 'processing';
        videoEntry.progress = 100;
        
        renderTabs();
        if (_activeVideoUuid === videoEntry.uuid) {
            updateEditorStatusUI(videoEntry);
            // Llenar datos si el servidor devolvió título limpio (ya lo hicimos en local, pero confirmamos)
        }
        
        ToastManager.show(`"${videoEntry.title}" subido. Procesando...`, 'info');

    } catch (error) {
        console.error("Upload error:", error);
        videoEntry.status = 'error';
        videoEntry.errorMsg = 'Fallo en subida';
        renderTabs();
        if (_activeVideoUuid === videoEntry.uuid) updateEditorStatusUI(videoEntry);
        ToastManager.show(`Error subiendo ${file.name}`, 'error');
    }
}

// ==========================================
// 3. GESTIÓN DE TABS Y EDITOR
// ==========================================

function renderTabs() {
    const container = document.getElementById('video-tabs-container');
    if (!container) return;
    
    container.innerHTML = '';

    _videosState.forEach(v => {
        const isActive = (v.uuid === _activeVideoUuid) ? 'active' : '';
        let icon = 'movie'; 
        let spinClass = '';

        if (v.status === 'uploading') {
            icon = 'upload'; // O nada si usamos spinner
        } else if (v.status === 'processing') {
            icon = 'settings_suggest'; 
            spinClass = 'badge-spinner'; // CSS spinner
        } else if (v.status === 'ready') {
            icon = 'check_circle';
        } else if (v.status === 'error') {
            icon = 'error';
        }

        const badge = document.createElement('div');
        badge.className = `studio-tab-badge ${isActive}`;
        badge.dataset.uuid = v.uuid;
        
        // Icono o Spinner
        let iconHtml = `<span class="material-symbols-rounded studio-tab-icon">${icon}</span>`;
        if (v.status === 'uploading' || v.status === 'processing') {
             // Reemplazar icono con spinner si está activo
             iconHtml = `<div class="badge-spinner"></div>`;
        }

        badge.innerHTML = `
            ${iconHtml}
            <span class="studio-tab-text">${v.title}</span>
        `;
        
        badge.addEventListener('click', () => switchEditor(v.uuid));
        container.appendChild(badge);
    });
}

function switchEditor(uuid) {
    // 1. Guardar estado del video actual antes de cambiar (si existe)
    if (_activeVideoUuid) {
        const currentVideo = _videosState.find(v => v.uuid === _activeVideoUuid);
        if (currentVideo) {
            currentVideo.title = document.getElementById('meta-title').value;
            currentVideo.description = document.getElementById('meta-desc').value;
        }
    }

    // 2. Cambiar activo
    _activeVideoUuid = uuid;
    const nextVideo = _videosState.find(v => v.uuid === uuid);
    if (!nextVideo) return;

    // 3. Renderizar Tabs para actualizar clase 'active'
    renderTabs();

    // 4. Llenar UI del Editor
    document.getElementById('meta-title').value = nextVideo.title || '';
    document.getElementById('meta-desc').value = nextVideo.description || '';
    document.getElementById('meta-filename').textContent = nextVideo.title + '.mp4';
    
    const imgPreview = document.getElementById('thumbnail-preview');
    if (nextVideo.thumbnail) {
        imgPreview.src = nextVideo.thumbnail;
        imgPreview.classList.remove('d-none');
    } else {
        imgPreview.src = '';
        imgPreview.classList.add('d-none');
    }

    updateEditorStatusUI(nextVideo);
}

function updateEditorStatusUI(video) {
    const alertBox = document.getElementById('editor-status-alert');
    const btnPublish = document.getElementById('btn-publish');
    const globalStatus = document.getElementById('global-upload-status');
    const globalText = document.getElementById('global-status-text');

    // Estado Global (Barra superior)
    if (video.status === 'uploading') {
        globalStatus.style.display = 'block';
        globalText.textContent = `Subiendo ${video.progress}%...`;
    } else if (video.status === 'processing') {
        globalStatus.style.display = 'block';
        globalText.textContent = 'Procesando en servidor...';
    } else {
        globalStatus.style.display = 'none';
    }

    // Estado Local (Alert en form)
    alertBox.className = 'component-message mb-0'; // Reset clases
    
    if (video.status === 'uploading') {
        alertBox.classList.remove('d-none');
        alertBox.classList.add('component-message--info');
        alertBox.innerHTML = `<strong>Subiendo video (${video.progress}%)...</strong><br>Puedes ir completando los detalles.`;
        btnPublish.disabled = true;
    } 
    else if (video.status === 'processing') {
        alertBox.classList.remove('d-none');
        alertBox.classList.add('component-message--warning');
        alertBox.innerHTML = `<strong>Procesando video...</strong><br>El video se está convirtiendo. Podrás publicar en breve.`;
        btnPublish.disabled = true;
    }
    else if (video.status === 'ready') {
        alertBox.classList.add('d-none'); // Ocultar si ya está listo
        validatePublishRequirements(); // Habilitar botón si hay título/thumb
    }
    else if (video.status === 'error') {
        alertBox.classList.remove('d-none');
        alertBox.classList.add('component-message--error');
        alertBox.textContent = 'Ocurrió un error con este video.';
        btnPublish.disabled = true;
    }
}

// ==========================================
// 4. EVENTOS DE SOCKET (Worker terminado)
// ==========================================

function onProcessingComplete(e) {
    const data = e.detail.message; // { uuid: '...', status: 'waiting_for_metadata' }
    
    if (data && data.uuid) {
        const video = _videosState.find(v => v.uuid === data.uuid);
        if (video) {
            video.status = 'ready'; // Listo para publicar
            
            // Si el socket manda thumbnail u otros datos, actualizarlos aquí
            // video.thumbnail = ...
            
            renderTabs();
            
            if (_activeVideoUuid === video.uuid) {
                updateEditorStatusUI(video);
                ToastManager.show('¡Procesamiento completado! Ya puedes publicar.', 'success');
            }
        }
    }
}

// ==========================================
// 5. EDITOR: THUMBNAIL Y GUARDADO
// ==========================================

function initEditorEvents() {
    const inputThumb = document.getElementById('input-thumbnail');
    const dropzoneThumb = document.getElementById('thumbnail-dropzone');
    
    dropzoneThumb?.addEventListener('click', () => inputThumb.click());

    inputThumb?.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file || !_activeVideoUuid) return;

        const currentVideo = _videosState.find(v => v.uuid === _activeVideoUuid);
        // Validar que no sea temp
        if (currentVideo.uuid.startsWith('temp_')) {
            ToastManager.show('Espera a que inicie la subida.', 'warning');
            return;
        }

        // Preview local
        const reader = new FileReader();
        reader.onload = (evt) => {
            const img = document.getElementById('thumbnail-preview');
            img.src = evt.target.result;
            img.classList.remove('d-none');
        };
        reader.readAsDataURL(file);

        // Subir
        const loadingSpinner = dropzoneThumb.querySelector('.thumbnail-loading');
        loadingSpinner.classList.remove('d-none');

        const formData = new FormData();
        formData.append('video_uuid', _activeVideoUuid);
        formData.append('thumbnail', file);

        try {
            const res = await ApiService.post(ApiService.Routes.Studio.UploadThumbnail, formData);
            if (res.success) {
                currentVideo.thumbnail = res.new_src; // Guardar en estado
                validatePublishRequirements();
            } else {
                ToastManager.show(res.message, 'error');
            }
        } catch (e) {
            console.error(e);
        } finally {
            loadingSpinner.classList.add('d-none');
        }
    });

    // Inputs text change -> Update Tab Title Live
    document.getElementById('meta-title')?.addEventListener('input', (e) => {
        if (_activeVideoUuid) {
            const val = e.target.value;
            const video = _videosState.find(v => v.uuid === _activeVideoUuid);
            if (video) {
                video.title = val || 'Sin título';
                // Actualizar solo texto del tab actual para performance
                const activeTab = document.querySelector(`.studio-tab-badge.active .studio-tab-text`);
                if(activeTab) activeTab.textContent = video.title;
                
                validatePublishRequirements();
            }
        }
    });

    document.getElementById('btn-publish')?.addEventListener('click', () => saveMetadata(true));
    document.getElementById('btn-save-draft')?.addEventListener('click', () => saveMetadata(false));
}

function validatePublishRequirements() {
    const title = document.getElementById('meta-title').value.trim();
    const hasThumb = !document.getElementById('thumbnail-preview').classList.contains('d-none');
    const btnPublish = document.getElementById('btn-publish');
    
    // Verificar estado del video actual
    const video = _videosState.find(v => v.uuid === _activeVideoUuid);
    const isReady = (video && video.status === 'ready');

    if (title && hasThumb && isReady) {
        btnPublish.disabled = false;
    } else {
        btnPublish.disabled = true;
    }
}

async function saveMetadata(publish) {
    if (!_activeVideoUuid) return;
    
    const video = _videosState.find(v => v.uuid === _activeVideoUuid);
    const title = document.getElementById('meta-title').value.trim();
    const desc = document.getElementById('meta-desc').value.trim();

    if (!title) {
        ToastManager.show('El título es obligatorio', 'warning');
        return;
    }

    const btn = publish ? document.getElementById('btn-publish') : document.getElementById('btn-save-draft');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Guardando...';

    const formData = new FormData();
    formData.append('video_uuid', _activeVideoUuid);
    formData.append('title', title);
    formData.append('description', desc);
    formData.append('publish', publish ? 'true' : 'false');

    try {
        const res = await ApiService.post(ApiService.Routes.Studio.SaveMetadata, formData);
        
        if (res.success) {
            ToastManager.show(res.message, 'success');
            if (publish) {
                // Eliminar de tabs y redirigir si no quedan
                _videosState = _videosState.filter(v => v.uuid !== _activeVideoUuid);
                if (_videosState.length === 0) {
                    window.location.href = window.BASE_PATH + 'channel/my-content';
                } else {
                    switchEditor(_videosState[0].uuid);
                }
            }
        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch (e) {
        ToastManager.show('Error al guardar', 'error');
    } finally {
        if(btn) {
            btn.innerText = originalText;
            btn.disabled = false;
            if (publish) validatePublishRequirements();
        }
    }
}