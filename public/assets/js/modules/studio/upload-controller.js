/**
 * public/assets/js/modules/studio/upload-controller.js
 * Versión Refactorizada: Arquitectura Signal & Interceptors
 * Actualizado para soporte de "Components" PHP y corrección de rutas/visibilidad.
 * [SEGURIDAD] Implementación de Upload Tokens.
 * [MEJORA] Soporte para progreso de procesamiento en tiempo real.
 * [NUEVO] Validación Tiered Limits (Size & Duration) en Frontend.
 * [MODIFICADO] Generación de miniaturas síncrona (PHP/FFmpeg).
 * [OPTIMIZACIÓN] Selección de miniaturas en cliente (Defer save).
 * [NUEVO] Gestión de Metadatos: Categorías y Actores (Tags System).
 */

import { ApiService } from '../../core/services/api-service.js';
import { ToastManager } from '../../core/components/toast-manager.js';
import { I18nManager } from '../../core/utils/i18n-manager.js';
import { DialogManager } from '../../core/components/dialog-manager.js';

const CHUNK_SIZE = 2 * 1024 * 1024; // 2MB
const MAX_FILES = 3;

// [TIERED LIMITS] Configuración de límites
const LIMITS = {
    STD_SIZE: 25 * 1024 * 1024 * 1024, // 25 GB
    STD_DURATION: 7200,                // 2 Horas
    FOUNDER_SIZE: 100 * 1024 * 1024 * 1024, // 100 GB
    FOUNDER_DURATION: 43200            // 12 Horas
};

// Estado local de videos
let _videosState = []; 
let _activeVideoUuid = null;
let _activeBatchId = null;
let _isEditMode = false; // Bandera para saber si estamos editando uno existente

// [NUEVO] Estado temporal para selección de miniatura (Frontend Only)
let _tempSelectedThumbnail = null;
let _tempDominantColor = null;
// Variable para bloqueo de operaciones de generación
let _isThumbOperationActive = false;

// [NUEVO] Timeout para debounce de búsqueda
let _searchTimeout = null;

// Helper para detectar rol (debe ser inyectado en el layout o body)
const getUserLimits = () => {
    // Intentar leer rol del DOM o variable global
    const role = document.body.dataset.userRole || window.AURORA_USER_ROLE || 'user';
    const isFounder = (role === 'founder');

    return {
        maxSize: isFounder ? LIMITS.FOUNDER_SIZE : LIMITS.STD_SIZE,
        maxDuration: isFounder ? LIMITS.FOUNDER_DURATION : LIMITS.STD_DURATION,
        label: isFounder ? 'Founder' : 'Estándar'
    };
};

export const UploadController = {
    init: async () => {
        const container = document.querySelector('[data-section="channel-upload"]');
        if (!container) return;

        console.log("UploadController: Inicializado (V2 + Security Tokens + Tags System)");
        
        _videosState = [];
        _activeVideoUuid = null;
        _activeBatchId = null;
        _isEditMode = false;
        _tempSelectedThumbnail = null;
        _tempDominantColor = null;
        
        initDropzone();
        initEditorEvents();
        
        // Listeners de Sockets (Solo procesamiento, ya no miniaturas)
        document.removeEventListener('socket:processing_complete', onProcessingComplete);
        document.addEventListener('socket:processing_complete', onProcessingComplete);

        // Listener para progreso de procesamiento (Transcodificación)
        document.removeEventListener('socket:processing_progress', onProcessingProgress);
        document.addEventListener('socket:processing_progress', onProcessingProgress);

        // Revisar si venimos de una URL con ID específico (Modo Edición)
        const initialVideoId = document.getElementById('initial-video-id')?.value;
        
        if (initialVideoId && initialVideoId.length > 5) {
            // Cargar modo edición para este video
            await loadExistingDraft(initialVideoId);
        } else {
            // Comportamiento normal: buscar subidas pendientes
            await checkPendingUploads();
        }
    }
};

// ==========================================
// 1. INICIALIZACIÓN Y CARGA
// ==========================================

async function loadExistingDraft(uuid) {
    try {
        console.log("Cargando borrador existente:", uuid);
        
        // 1. Ocultar dropzone y mostrar editor (con loading)
        document.getElementById('upload-dropzone')?.classList.add('d-none');
        document.getElementById('video-editor-area')?.classList.remove('d-none');
        
        // Mostrar la barra de botones de acción
        document.getElementById('action-buttons-group')?.classList.remove('d-none');
        
        const alertBox = document.getElementById('editor-status-alert');
        if (alertBox) {
            alertBox.className = 'component-message component-message--info mb-4'; 
            alertBox.innerHTML = '<span class="spinner-sm"></span> Cargando datos del video...';
            alertBox.classList.remove('d-none');
        }

        // 2. Pedir datos al servidor
        const formData = new FormData();
        formData.append('video_uuid', uuid);
        
        const route = ApiService.Routes.Studio.GetDetails || { route: 'studio.get_video_details' }; 
        const res = await ApiService.post(route, formData);

        if (res.success && res.video) {
            _isEditMode = true;
            _activeVideoUuid = res.video.uuid;

            // Construir estado local
            const videoEntry = {
                uuid: res.video.uuid,
                title: res.video.title || '',
                description: res.video.description || '',
                status: res.video.status || 'ready',
                progress: 100, // Progreso de subida (ya subido)
                // Ruta absoluta para la miniatura
                thumbnail: res.video.thumbnail_src ? (window.BASE_PATH + res.video.thumbnail_src) : null,
                // [NUEVO] Guardar miniaturas generadas si existen
                generated_thumbnails: res.video.generated_thumbnails || [],
                // [NUEVO] Cargar Tags
                categories: res.video.categories || [], // Array de objetos {id, name}
                cast: res.video.cast || [] // Array de objetos {id, name, avatar}
            };

            _videosState = [videoEntry];

            renderTabs();
            switchEditor(videoEntry.uuid);
            
            // Quitar mensaje de carga
            if (alertBox) alertBox.classList.add('d-none');
            
        } else {
            console.error("Error cargando video:", res.message);
            ToastManager.show('No se pudo cargar la información del video.', 'error');
            // Volver al inicio si falla
            document.getElementById('upload-dropzone')?.classList.remove('d-none');
            document.getElementById('video-editor-area')?.classList.add('d-none');
            document.getElementById('action-buttons-group')?.classList.add('d-none');
        }

    } catch (e) {
        console.error("Error loading draft:", e);
        ToastManager.show('Error de conexión al obtener video.', 'error');
        document.getElementById('upload-dropzone')?.classList.remove('d-none');
        document.getElementById('video-editor-area')?.classList.add('d-none');
        document.getElementById('action-buttons-group')?.classList.add('d-none');
    }
}

async function checkPendingUploads() {
    try {
        const res = await ApiService.post(ApiService.Routes.Studio.GetPending, new FormData(), { signal: window.PAGE_SIGNAL });
        if (res.success && res.videos.length > 0) {
            
            document.getElementById('upload-dropzone')?.classList.add('d-none');
            document.getElementById('video-editor-area')?.classList.remove('d-none');
            
            // Mostrar botones si hay videos pendientes
            document.getElementById('action-buttons-group')?.classList.remove('d-none');

            res.videos.forEach(v => {
                _videosState.push({
                    uuid: v.uuid,
                    title: v.title || 'Sin título',
                    status: (v.status === 'waiting_for_metadata') ? 'ready' : 'processing',
                    // Si viene del servidor como processing, usamos el porcentaje guardado si existe, sino 0
                    processingPercent: v.processing_percentage || 0,
                    progress: 100, // Progreso de subida (ya subido)
                    // Ruta absoluta
                    thumbnail: v.thumbnail_src ? (window.BASE_PATH + v.thumbnail_src) : null,
                    description: v.description || '',
                    generated_thumbnails: v.generated_thumbnails || [],
                    // [NUEVO] Inicializar vacíos para nuevos videos o pendientes
                    categories: [],
                    cast: []
                });
            });

            renderTabs();
            if (_videosState.length > 0) {
                switchEditor(_videosState[0].uuid);
            }
        }
        updateAddButtonVisibility();
    } catch (e) {
        if (!e.isAborted) console.error("Error checking pending:", e);
    }
}

function initDropzone() {
    const dropzone = document.getElementById('upload-dropzone');
    const input = document.getElementById('input-video-files');
    const btnSelect = document.getElementById('btn-select-files');
    const btnTrigger = document.getElementById('btn-trigger-files');
    const btnAddMore = document.getElementById('btn-add-more');

    if (!dropzone || !input) return;

    const openSelector = () => input.click();
    if(btnSelect) btnSelect.addEventListener('click', openSelector);
    if(btnTrigger) btnTrigger.addEventListener('click', openSelector);
    if(btnAddMore) btnAddMore.addEventListener('click', openSelector);

    // [MODIFICADO] handleFiles ahora es async para validar duración
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

// [NUEVO] Función helper para obtener duración antes de subir
const getVideoDuration = (file) => {
    return new Promise((resolve, reject) => {
        const video = document.createElement('video');
        video.preload = 'metadata';
        
        video.onloadedmetadata = () => {
            window.URL.revokeObjectURL(video.src);
            resolve(video.duration);
        };
        
        video.onerror = () => {
            window.URL.revokeObjectURL(video.src);
            reject("Error cargando metadatos");
        };
        
        video.src = window.URL.createObjectURL(file);
    });
};

// [MODIFICADO] Función handleFiles con validaciones estrictas
async function handleFiles(files) {
    const validFiles = Array.from(files).filter(f => f.type.startsWith('video/'));
    
    if (validFiles.length === 0) {
        ToastManager.show('Selecciona archivos de video válidos.', 'warning');
        return;
    }

    if ((_videosState.length + validFiles.length) > MAX_FILES) {
        ToastManager.show(`Máximo ${MAX_FILES} videos por sesión.`, 'warning');
        return;
    }

    // [TIERED LIMITS] Obtener límites del usuario actual
    const limits = getUserLimits();
    const filesToUpload = [];

    // Validar cada archivo (Asíncrono para la duración)
    for (const file of validFiles) {
        // 1. Validar Tamaño
        if (file.size > limits.maxSize) {
            const sizeGB = (limits.maxSize / (1024 * 1024 * 1024)).toFixed(0);
            ToastManager.show(`El archivo "${file.name}" excede el límite de ${sizeGB}GB.`, 'error');
            continue; // Saltar este archivo
        }

        // 2. Validar Duración (Intento en cliente)
        try {
            const duration = await getVideoDuration(file);
            if (duration > limits.maxDuration) {
                const limitHours = limits.maxDuration / 3600;
                ToastManager.show(`El archivo "${file.name}" dura más de ${limitHours} horas.`, 'error');
                continue; // Saltar este archivo
            }
        } catch (e) {
            console.warn("No se pudo validar duración en cliente, validando en servidor...", e);
            // Dejamos pasar si falla la comprobación local para que el servidor decida
        }

        filesToUpload.push(file);
    }

    if (filesToUpload.length === 0) return;

    // UI Updates
    document.getElementById('upload-dropzone')?.classList.add('d-none');
    document.getElementById('video-editor-area')?.classList.remove('d-none');
    document.getElementById('action-buttons-group')?.classList.remove('d-none');

    if (!_activeBatchId) {
        _activeBatchId = 'batch_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    // Iniciar subidas
    filesToUpload.forEach(file => {
        const defaultTitle = file.name.replace(/\.[^/.]+$/, "");
        const tempId = 'temp_' + Date.now() + Math.random();
        
        const videoEntry = {
            uuid: tempId,
            fileObject: file,
            title: defaultTitle,
            description: '',
            status: 'uploading',
            progress: 0,
            processingPercent: 0,
            thumbnail: null,
            uploadToken: null,
            generated_thumbnails: [],
            // [NUEVO] Inicializar Tags
            categories: [],
            cast: []
        };
        
        _videosState.push(videoEntry);
        startUpload(videoEntry);
    });

    renderTabs();
    updateAddButtonVisibility();
    
    if (!_activeVideoUuid) {
        const firstNew = _videosState.find(v => v.status === 'uploading');
        if (firstNew) switchEditor(firstNew.uuid);
    }
}

function updateAddButtonVisibility() {
    const btnAdd = document.getElementById('btn-add-more');
    if (!btnAdd) return;

    if (_videosState.length > 0 && _videosState.length < MAX_FILES) {
        btnAdd.classList.remove('d-none');
    } else {
        btnAdd.classList.add('d-none');
    }
}

// ==========================================
// 2. LÓGICA DE SUBIDA (MODIFICADA)
// ==========================================

async function startUpload(videoEntry) {
    const file = videoEntry.fileObject;
    
    try {
        const initData = new FormData();
        initData.append('action', 'init_upload');
        initData.append('batch_id', _activeBatchId);
        initData.append('file_name', file.name);
        
        // [TIERED LIMITS] Enviamos el tamaño para validación temprana en servidor (Gatekeeper)
        initData.append('file_size', file.size);

        const initRes = await ApiService.post(ApiService.Routes.Studio.InitUpload, initData);
        if (!initRes.success) throw new Error(initRes.message);
        
        const oldId = videoEntry.uuid;
        videoEntry.uuid = initRes.video_uuid;
        
        // [TOKEN] Guardamos el token de seguridad devuelto por el servidor
        videoEntry.uploadToken = initRes.upload_token;
        
        if (_activeVideoUuid === oldId) {
            _activeVideoUuid = videoEntry.uuid;
        }
        renderTabs();

        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        
        for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
            if (!_videosState.find(v => v.uuid === videoEntry.uuid)) {
                console.log("Subida abortada por usuario");
                return;
            }

            const start = chunkIndex * CHUNK_SIZE;
            const end = Math.min(start + CHUNK_SIZE, file.size);
            const chunk = file.slice(start, end);

            const formData = new FormData();
            formData.append('action', 'upload_chunk');
            formData.append('video_uuid', videoEntry.uuid);
            formData.append('chunk_index', chunkIndex);
            formData.append('chunk', chunk);
            formData.append('is_last', (chunkIndex === totalChunks - 1) ? 'true' : 'false');
            
            // [TOKEN] Adjuntamos el token en cada chunk para validar permisos y lock
            formData.append('upload_token', videoEntry.uploadToken);

            const chunkRes = await ApiService.post(ApiService.Routes.Studio.UploadChunk, formData);
            if (!chunkRes.success) throw new Error(chunkRes.message);

            const percent = Math.round(((chunkIndex + 1) / totalChunks) * 100);
            videoEntry.progress = percent;
            
            if (_activeVideoUuid === videoEntry.uuid) {
                updateEditorStatusUI(videoEntry);
            }
        }

        videoEntry.status = 'processing';
        videoEntry.progress = 100;
        videoEntry.processingPercent = 0; // Inicio de procesamiento
        
        renderTabs();
        if (_activeVideoUuid === videoEntry.uuid) {
            updateEditorStatusUI(videoEntry);
        }
        
        ToastManager.show(`"${videoEntry.title}" subido. Procesando...`, 'info');

    } catch (error) {
        console.error("Upload error:", error);
        if (!_videosState.find(v => v.uuid === videoEntry.uuid)) return;

        videoEntry.status = 'error';
        videoEntry.errorMsg = error.message || 'Fallo en subida';
        renderTabs();
        if (_activeVideoUuid === videoEntry.uuid) updateEditorStatusUI(videoEntry);
        
        // Mensaje específico si es por límites
        if (error.message.includes('límite')) {
             ToastManager.show(error.message, 'error');
        } else {
             ToastManager.show(`Error subiendo ${file.name}: ${videoEntry.errorMsg}`, 'error');
        }
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
        
        if (v.status === 'uploading') icon = 'upload'; 
        else if (v.status === 'processing') icon = 'settings_suggest'; 
        else if (v.status === 'ready') icon = 'check_circle';
        else if (v.status === 'error') icon = 'error';

        const badge = document.createElement('div');
        badge.className = `studio-tab-badge ${isActive}`;
        badge.dataset.uuid = v.uuid;
        
        let iconHtml = `<span class="material-symbols-rounded studio-tab-icon">${icon}</span>`;
        if (v.status === 'uploading' || v.status === 'processing') {
             iconHtml = `<div class="badge-spinner"></div>`;
        }

        badge.innerHTML = `${iconHtml}<span class="studio-tab-text">${v.title}</span>`;
        badge.addEventListener('click', () => switchEditor(v.uuid));
        container.appendChild(badge);
    });
}

function switchEditor(uuid) {
    _activeVideoUuid = uuid;
    
    // [IMPORTANTE] Resetear variables temporales al cambiar de video
    _tempSelectedThumbnail = null;
    _tempDominantColor = null;

    const nextVideo = _videosState.find(v => v.uuid === uuid);
    if (!nextVideo) return;

    renderTabs();

    // 1. Resetear estados visuales a "View"
    toggleEditState('title', false);
    toggleEditState('desc', false);
    toggleEditState('meta', false); // Resetear estado de metadatos

    // 2. Llenar valores
    // Título
    document.getElementById('display-title').textContent = nextVideo.title || 'Sin título';
    const inputTitle = document.getElementById('meta-title');
    inputTitle.value = nextVideo.title || '';
    inputTitle.dataset.originalValue = nextVideo.title || '';

    // Descripción
    document.getElementById('display-desc').textContent = nextVideo.description || 'Sin descripción';
    const inputDesc = document.getElementById('meta-desc');
    inputDesc.value = nextVideo.description || '';
    inputDesc.dataset.originalValue = nextVideo.description || '';

    // Preview File Name
    document.getElementById('meta-filename').textContent = (nextVideo.title || 'video') + '.mp4';
    
    // [NUEVO] Renderizar Tags (Categorías y Actores)
    renderTagsUI(nextVideo);

    // 3. Miniatura Principal
    const imgPreview = document.getElementById('thumbnail-preview');
    if (nextVideo.thumbnail) {
        imgPreview.src = nextVideo.thumbnail;
        imgPreview.classList.remove('d-none');
    } else {
        imgPreview.src = '';
        imgPreview.classList.add('d-none');
    }

    // 4. Miniaturas Generadas (Renderizar si existen)
    const grid = document.getElementById('generated-thumbs-grid');
    if (nextVideo.generated_thumbnails && Array.isArray(nextVideo.generated_thumbnails) && nextVideo.generated_thumbnails.length > 0) {
        renderGeneratedGrid(nextVideo.generated_thumbnails);
    } else {
        if (grid) {
            grid.innerHTML = '';
            grid.classList.add('d-none');
        }
    }

    updateEditorStatusUI(nextVideo);
}

function updateEditorStatusUI(video) {
    const alertBox = document.getElementById('editor-status-alert');
    const globalStatus = document.getElementById('global-upload-status');
    const globalText = document.getElementById('global-status-text');
    const btnGenThumbs = document.getElementById('btn-gen-thumbs');
    const btnPublish = document.getElementById('btn-publish');

    if (!alertBox) return; // Seguridad si el componente no cargó

    // MODO EDICIÓN: Limpiar estados de "subiendo"
    if (_isEditMode) {
        if(globalStatus) globalStatus.style.display = 'none';
        
        // Si hay error, lo mostramos, si no, ocultamos alertas
        if (video.status === 'error') {
            alertBox.classList.remove('d-none');
            alertBox.classList.add('component-message--error');
            alertBox.textContent = 'Error recuperando datos.';
        } else {
            alertBox.classList.add('d-none');
        }
        
        if (btnGenThumbs) btnGenThumbs.disabled = false;
        validatePublishRequirements();
        return;
    }

    // MODO SUBIDA
    // Estado Global (Barra superior)
    if (video.status === 'uploading') {
        if(globalStatus) {
            globalStatus.style.display = 'block';
            globalText.textContent = `Subiendo ${video.progress}%...`;
        }
        if (btnGenThumbs) btnGenThumbs.disabled = true;
    } else if (video.status === 'processing') {
        if(globalStatus) {
            globalStatus.style.display = 'block';
            globalText.textContent = 'Procesando en servidor...';
        }
        if (btnGenThumbs) btnGenThumbs.disabled = true;
    } else {
        if(globalStatus) globalStatus.style.display = 'none';
        if (btnGenThumbs) btnGenThumbs.disabled = false;
    }

    // Estado Local (Alert en form)
    alertBox.className = 'component-message mb-4'; 
    
    if (video.status === 'uploading') {
        alertBox.classList.remove('d-none');
        alertBox.classList.add('component-message--info');
        alertBox.innerHTML = `<strong>Subiendo video (${video.progress}%)...</strong><br>Puedes ir completando los detalles.`;
    } 
    else if (video.status === 'processing') {
        alertBox.classList.remove('d-none');
        alertBox.classList.add('component-message--warning');
        
        // [MODIFICADO] Mostrar porcentaje de procesamiento real
        const procPercent = video.processingPercent || 0;
        alertBox.innerHTML = `<strong>Procesando video (${procPercent}%)...</strong><br>El video se está convirtiendo. Podrás publicar en breve.`;
    }
    else if (video.status === 'ready') {
        alertBox.classList.add('d-none');
    }
    else if (video.status === 'error') {
        alertBox.classList.remove('d-none');
        alertBox.classList.add('component-message--error');
        alertBox.textContent = video.errorMsg || 'Ocurrió un error con este video.';
    }

    validatePublishRequirements();
}

function onProcessingComplete(e) {
    const data = e.detail.message;
    if (data && data.uuid) {
        const video = _videosState.find(v => v.uuid === data.uuid);
        if (video) {
            video.status = 'ready';
            video.processingPercent = 100;
            renderTabs();
            if (_activeVideoUuid === video.uuid) {
                updateEditorStatusUI(video);
                ToastManager.show('¡Procesamiento completado! Ya puedes publicar.', 'success');
            }
        }
    }
}

// [NUEVO] Handler para progreso en tiempo real
function onProcessingProgress(e) {
    const data = e.detail.message;
    if (data && data.uuid) {
        const video = _videosState.find(v => v.uuid === data.uuid);
        if (video) {
            video.status = 'processing';
            video.processingPercent = data.percent; 
            
            // Si el video está activo en el editor, actualizamos la UI
            if (_activeVideoUuid === video.uuid) {
                updateEditorStatusUI(video);
            }
        }
    }
}

// ==========================================
// 4. LÓGICA DE INTERFAZ & EVENTOS
// ==========================================

function initEditorEvents() {
    // A) Configuración de campos (Toggle & Guardado Granular)
    setupFieldLogic('title', 'meta-title');
    setupFieldLogic('desc', 'meta-desc');
    // [NUEVO] Lógica para el campo Meta (Tags)
    setupFieldLogic('meta', null); // null porque no es un input único

    // B) Acciones Globales (Header)
    const btnPublish = document.getElementById('btn-publish');
    const btnSaveDraft = document.getElementById('btn-save-draft');
    const btnDelete = document.getElementById('btn-delete-video');

    if (btnPublish) btnPublish.addEventListener('click', () => saveGlobalAction(true));
    if (btnSaveDraft) btnSaveDraft.addEventListener('click', () => saveGlobalAction(false));
    if (btnDelete) btnDelete.addEventListener('click', deleteVideo);

    // C) Miniatura (Upload & Generate)
    const btnThumbUpload = document.getElementById('btn-trigger-thumb-upload');
    const inputThumb = document.getElementById('input-thumbnail');
    const btnGenThumbs = document.getElementById('btn-gen-thumbs');

    if (btnThumbUpload && inputThumb) {
        btnThumbUpload.addEventListener('click', () => inputThumb.click());
        inputThumb.addEventListener('change', handleThumbnailUpload);
    }

    if (btnGenThumbs) btnGenThumbs.addEventListener('click', generateThumbnails);

    // D) Inicializar Inputs de Tags (Autocomplete)
    initTagInputs();
}

function setupFieldLogic(targetName, inputId) {
    const section = document.querySelector(`[data-component="${targetName}-section"]`);
    if (!section) return;

    // Botones
    const btnEdit = section.querySelector(`[data-action="start-edit"][data-target="${targetName}"]`);
    const btnCancel = section.querySelector(`[data-action="cancel-edit"][data-target="${targetName}"]`);
    const btnSave = section.querySelector(`[data-action="save-field"][data-target="${targetName}"]`);
    const input = inputId ? document.getElementById(inputId) : null;

    // Evento Editar
    if (btnEdit) {
        btnEdit.addEventListener('click', () => {
            toggleEditState(targetName, true);
            if (input) input.focus();
        });
    }

    // Evento Cancelar
    if (btnCancel) {
        btnCancel.addEventListener('click', () => {
            if (input) input.value = input.dataset.originalValue || '';
            // Si es Meta, necesitamos revertir los tags visuales
            if (targetName === 'meta' && _activeVideoUuid) {
                 const video = _videosState.find(v => v.uuid === _activeVideoUuid);
                 if (video) renderTagsUI(video);
            }
            toggleEditState(targetName, false);
        });
    }

    // Evento Guardar Individual
    if (btnSave) {
        btnSave.addEventListener('click', () => saveFieldData(targetName));
    }
}

function toggleEditState(targetName, isEditing) {
    const section = document.querySelector(`[data-component="${targetName}-section"]`);
    if (!section) return;

    const viewState = section.querySelector('[data-state="view"]');
    const editState = section.querySelector('[data-state="edit"]');
    const actionsView = section.querySelector('[data-state="actions-view"]');
    const actionsEdit = section.querySelector('[data-state="actions-edit"]');

    if (isEditing) {
        viewState?.classList.remove('active');
        actionsView?.classList.remove('active');
        editState?.classList.remove('disabled');
        actionsEdit?.classList.remove('disabled');
        
        viewState?.classList.add('disabled');
        actionsView?.classList.add('disabled');
        editState?.classList.add('active');
        actionsEdit?.classList.add('active');
    } else {
        editState?.classList.remove('active');
        actionsEdit?.classList.remove('active');
        viewState?.classList.remove('disabled');
        actionsView?.classList.remove('disabled');

        editState?.classList.add('disabled');
        actionsEdit?.classList.add('disabled');
        viewState?.classList.add('active');
        actionsView?.classList.add('active');
    }
}

// [NUEVO] Lógica de Inputs de Etiquetas
function initTagInputs() {
    const categoryInput = document.getElementById('meta-category-input');
    const actorInput = document.getElementById('meta-actor-input');

    if (categoryInput) {
        // Búsqueda al escribir
        categoryInput.addEventListener('input', (e) => handleTagSearch('category', e.target.value));
        // Enter para crear/seleccionar
        categoryInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addTag('category', { name: e.target.value.trim(), id: null }); // ID null = Nuevo/Custom
                e.target.value = '';
                document.getElementById('category-suggestions')?.classList.add('d-none');
            }
        });
    }

    if (actorInput) {
        actorInput.addEventListener('input', (e) => handleTagSearch('actor', e.target.value));
        // Actores usualmente no se crean con Enter, solo selección, pero por UX dejamos limpiar
        actorInput.addEventListener('keydown', (e) => {
             if (e.key === 'Escape') document.getElementById('actor-suggestions')?.classList.add('d-none');
        });
    }

    // Cerrar sugerencias al hacer clic fuera
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.tag-input-wrapper')) {
            document.querySelectorAll('.suggestions-dropdown').forEach(el => el.classList.add('d-none'));
        }
    });
}

function handleTagSearch(type, query) {
    const dropdown = document.getElementById(`${type}-suggestions`);
    if (!dropdown) return;

    if (!query || query.length < 2) {
        dropdown.classList.add('d-none');
        return;
    }

    if (_searchTimeout) clearTimeout(_searchTimeout);
    _searchTimeout = setTimeout(async () => {
        try {
            const formData = new FormData();
            formData.append('type', type);
            formData.append('query', query);
            
            // Usamos una ruta genérica de búsqueda
            const route = ApiService.Routes.Studio.SearchTags || { route: 'studio.search_tags' };
            const res = await ApiService.post(route, formData, { silent: true });

            if (res.success && res.results && res.results.length > 0) {
                renderSuggestions(type, res.results);
            } else {
                dropdown.classList.add('d-none');
            }
        } catch (e) {
            console.error("Search error", e);
        }
    }, 300);
}

function renderSuggestions(type, results) {
    const dropdown = document.getElementById(`${type}-suggestions`);
    dropdown.innerHTML = '';
    
    results.forEach(item => {
        const div = document.createElement('div');
        div.className = 'suggestion-item';
        
        let content = '';
        if (type === 'actor') {
             // Avatar si existe, sino placeholder
             const avatar = item.avatar_path ? (window.BASE_PATH + item.avatar_path) : 'https://ui-avatars.com/api/?name='+item.name;
             content = `<img src="${avatar}"><span>${item.name}</span>`;
        } else {
             content = `<span>${item.name}</span> <span class="type-badge">${item.count || 0} videos</span>`;
        }

        div.innerHTML = content;
        div.addEventListener('click', () => {
            addTag(type, item);
            document.getElementById(`meta-${type}-input`).value = '';
            dropdown.classList.add('d-none');
        });
        dropdown.appendChild(div);
    });

    dropdown.classList.remove('d-none');
}

function addTag(type, item) {
    if (!_activeVideoUuid) return;
    const video = _videosState.find(v => v.uuid === _activeVideoUuid);
    if (!video) return;

    // Normalizar
    const name = item.name.trim();
    if (!name) return;

    // Array destino
    const collection = (type === 'category') ? video.categories : video.cast;

    // Evitar duplicados
    if (collection.find(t => t.name.toLowerCase() === name.toLowerCase())) {
        return; 
    }

    // Agregar (Si item tiene ID es existente, sino es nuevo string)
    collection.push({
        id: item.id || null, // null indicará al backend que cree/busque por nombre
        name: name,
        avatar_path: item.avatar_path || null
    });

    renderTagsUI(video);
}

function removeTag(type, index) {
    if (!_activeVideoUuid) return;
    const video = _videosState.find(v => v.uuid === _activeVideoUuid);
    if (!video) return;

    if (type === 'category') video.categories.splice(index, 1);
    else video.cast.splice(index, 1);

    renderTagsUI(video);
}

function renderTagsUI(video) {
    // 1. Renderizar Chips de Edición
    const renderChips = (containerId, items, type) => {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '';
        
        items.forEach((item, idx) => {
            const chip = document.createElement('div');
            chip.className = 'tag-chip';
            chip.innerHTML = `<span>${item.name}</span><span class="remove-tag">×</span>`;
            chip.querySelector('.remove-tag').addEventListener('click', () => removeTag(type, idx));
            container.appendChild(chip);
        });
    };

    renderChips('category-tags-collection', video.categories, 'category');
    renderChips('actor-tags-collection', video.cast, 'actor');

    // 2. Renderizar Vista de Lectura (View State)
    const renderView = (containerId, items, type) => {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        if (items.length === 0) {
            container.innerHTML = '<span style="color: var(--text-secondary); font-size: 13px;">Ninguna</span>';
            return;
        }
        
        container.innerHTML = '';
        items.forEach(item => {
            const pill = document.createElement('span');
            pill.className = `view-tag-pill ${type}`;
            
            let icon = type === 'category' ? '#' : '★';
            pill.textContent = `${icon} ${item.name}`;
            container.appendChild(pill);
        });
    };

    renderView('display-categories', video.categories, 'category');
    renderView('display-cast', video.cast, 'actor');
}

async function saveFieldData(targetName) {
    if (!_activeVideoUuid) return;

    const video = _videosState.find(v => v.uuid === _activeVideoUuid);
    if (!video) return;

    const section = document.querySelector(`[data-component="${targetName}-section"]`);
    const btnSave = section.querySelector('[data-action="save-field"]');
    
    // [MODIFICADO] Lógica dinámica según el target
    const formData = new FormData();
    formData.append('video_uuid', _activeVideoUuid);
    formData.append('publish', 'false');

    if (targetName === 'title') {
        const val = document.getElementById('meta-title').value.trim();
        if (!val) { ToastManager.show('El título es obligatorio.', 'warning'); return; }
        formData.append('title', val);
        // Mantener descripción actual
        formData.append('description', video.description); 

    } else if (targetName === 'desc') {
        const val = document.getElementById('meta-desc').value.trim();
        formData.append('description', val);
        // Mantener título actual
        formData.append('title', video.title);

    } else if (targetName === 'meta') {
        // [NUEVO] Guardar Tags
        // Enviamos todo el conjunto de metadatos básicos para asegurar integridad
        formData.append('title', video.title);
        formData.append('description', video.description);
        
        // Convertir arrays a JSON
        formData.append('categories', JSON.stringify(video.categories));
        formData.append('actors', JSON.stringify(video.cast));
    }

    const originalText = btnSave.innerText;
    btnSave.innerText = 'Guardando...';
    btnSave.disabled = true;

    try {
        const res = await ApiService.post(ApiService.Routes.Studio.SaveMetadata, formData);

        if (res.success) {
            // Actualizar estado local si fue title/desc
            if (targetName === 'title') {
                video.title = formData.get('title');
                document.getElementById('meta-title').dataset.originalValue = video.title;
                document.getElementById('display-title').textContent = video.title;
            }
            if (targetName === 'desc') {
                video.description = formData.get('description');
                document.getElementById('meta-desc').dataset.originalValue = video.description;
                document.getElementById('display-desc').textContent = video.description || 'Sin descripción';
            }
            
            renderTabs();
            ToastManager.show(res.message, 'success');
            toggleEditState(targetName, false);
            validatePublishRequirements();
        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch (e) {
        ToastManager.show('Error al guardar cambios.', 'error');
        console.error(e);
    } finally {
        btnSave.innerText = originalText;
        btnSave.disabled = false;
    }
}

// [MODIFICADO] saveGlobalAction: Envía también miniatura Y TAGS
async function saveGlobalAction(publish) {
    if (!_activeVideoUuid) return;
    
    const video = _videosState.find(v => v.uuid === _activeVideoUuid);
    if (!video) return;

    const title = document.getElementById('meta-title').value.trim();
    const desc = document.getElementById('meta-desc').value.trim();

    if (!title) {
        ToastManager.show('El título es obligatorio', 'warning');
        return;
    }

    const btn = publish ? document.getElementById('btn-publish') : document.getElementById('btn-save-draft');
    const originalText = btn.innerHTML; // Guardamos el HTML (icono) en vez de solo texto
    btn.disabled = true;
    
    // Si es botón de guardar (icono), reemplazamos por spinner, si es publicar (texto), ponemos texto
    if (!publish) {
        btn.innerHTML = '<span class="spinner-sm" style="width:16px;height:16px;"></span>';
    } else {
        btn.innerText = 'Procesando...';
    }

    const formData = new FormData();
    formData.append('video_uuid', _activeVideoUuid);
    formData.append('title', title);
    formData.append('description', desc);
    formData.append('publish', publish ? 'true' : 'false');

    // [NUEVO] Incluir Tags
    formData.append('categories', JSON.stringify(video.categories));
    formData.append('actors', JSON.stringify(video.cast));

    // [NUEVO] Si hay una selección de miniatura pendiente, la enviamos
    if (_tempSelectedThumbnail) {
        formData.append('selected_thumbnail', _tempSelectedThumbnail);
        formData.append('dominant_color', _tempDominantColor);
    }

    try {
        const res = await ApiService.post(ApiService.Routes.Studio.SaveMetadata, formData);
        
        if (res.success) {
            ToastManager.show(res.message, 'success');
            
            // Si guardamos, limpiamos el estado temporal de miniatura para evitar reenvíos
            _tempSelectedThumbnail = null;
            _tempDominantColor = null;

            if (publish) {
                // Si se publica, eliminar de la lista local
                _videosState = _videosState.filter(v => v.uuid !== _activeVideoUuid);
                if (_videosState.length === 0) {
                    window.location.href = window.BASE_PATH + 'channel/my-content';
                } else {
                    switchEditor(_videosState[0].uuid);
                }
            } else {
                video.title = title;
                video.description = desc;
                renderTabs();
            }
        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch (e) {
        ToastManager.show('Error de conexión', 'error');
    } finally {
        if(btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            validatePublishRequirements();
        }
    }
}

async function handleThumbnailUpload(e) {
    const file = e.target.files[0];
    if (!file || !_activeVideoUuid) return;

    const currentVideo = _videosState.find(v => v.uuid === _activeVideoUuid);
    
    if (!_isEditMode && currentVideo.uuid.startsWith('temp_')) {
         if (currentVideo.progress === 0) {
            ToastManager.show('Espera a que inicie la subida.', 'warning');
            return;
         }
    }

    const spinner = document.querySelector('.preview-player-placeholder .thumbnail-loading');
    if (spinner) spinner.classList.remove('d-none');

    const formData = new FormData();
    formData.append('video_uuid', _activeVideoUuid);
    formData.append('thumbnail', file);

    try {
        const res = await ApiService.post(ApiService.Routes.Studio.UploadThumbnail, formData);
        
        if (res.success) {
            // Asegurar ruta absoluta
            const fullSrc = window.BASE_PATH + res.new_src;
            currentVideo.thumbnail = fullSrc;
            
            const imgPreview = document.getElementById('thumbnail-preview');
            if (imgPreview) {
                imgPreview.src = fullSrc;
                imgPreview.classList.remove('d-none');
            }
            
            applyDominantColor(res.dominant_color);
            validatePublishRequirements();
            ToastManager.show('Miniatura actualizada', 'success');
        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch (err) {
        console.error(err);
        ToastManager.show('Error al subir miniatura', 'error');
    } finally {
        if (spinner) spinner.classList.add('d-none');
        e.target.value = ''; 
    }
}

async function deleteVideo() {
    if (!_activeVideoUuid) return;

    const confirmed = await DialogManager.confirm({
        title: '¿Eliminar borrador?',
        message: 'Esta acción eliminará el video y sus archivos permanentemente.',
        type: 'danger',
        confirmText: 'Sí, eliminar',
        cancelText: 'Cancelar'
    });

    if (!confirmed) return;

    const btn = document.getElementById('btn-delete-video');
    btn.disabled = true;
    
    const videoUuid = _activeVideoUuid;

    try {
        const formData = new FormData();
        formData.append('video_uuid', videoUuid);
        const res = await ApiService.post(ApiService.Routes.Studio.DeleteVideo, formData);
        
        if (res.success) {
            ToastManager.show('Borrador eliminado.', 'success');
            if (_isEditMode) {
                window.location.href = window.BASE_PATH + 'channel/my-content';
                return;
            }
        }
    } catch (e) {
        console.error("Delete error:", e);
    } finally {
        _videosState = _videosState.filter(v => v.uuid !== videoUuid);
        
        if (_videosState.length === 0) {
            document.getElementById('video-editor-area')?.classList.add('d-none');
            document.getElementById('upload-dropzone')?.classList.remove('d-none');
            
            // Ocultar botones si no hay videos
            document.getElementById('action-buttons-group')?.classList.add('d-none');

            _activeVideoUuid = null;
            document.getElementById('input-video-files').value = '';
        } else {
            switchEditor(_videosState[0].uuid);
        }
        
        renderTabs();
        updateAddButtonVisibility();
        btn.disabled = false;
    }
}

function validatePublishRequirements() {
    const title = document.getElementById('meta-title').value.trim();
    const thumbPreview = document.getElementById('thumbnail-preview');
    // Verificar si hay imagen visible, ya sea subida o seleccionada temporalmente
    const hasThumb = (thumbPreview && !thumbPreview.classList.contains('d-none')) || _tempSelectedThumbnail;
    
    const btnPublish = document.getElementById('btn-publish');
    if (!btnPublish) return;

    const video = _videosState.find(v => v.uuid === _activeVideoUuid);
    
    const isReady = _isEditMode ? true : (video && video.status === 'ready');

    if (title && hasThumb && isReady) {
        btnPublish.disabled = false;
    } else {
        btnPublish.disabled = true;
    }
}

// ==========================================
// 5. UTILIDADES DE IA & MINIATURAS (REFACTORIZADO)
// ==========================================

async function generateThumbnails() {
    if (!_activeVideoUuid) return;
    
    // [SEGURIDAD] Bloqueo de cliente para evitar múltiples clics
    if (_isThumbOperationActive) return;
    _isThumbOperationActive = true;

    const btn = document.getElementById('btn-gen-thumbs');
    const originalContent = '<span class="material-symbols-rounded">autorenew</span>';
    
    // UI Loading
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-sm" style="width:16px;height:16px;"></span>';
    
    // Toast informativo (FFmpeg puede tardar unos segundos)
    ToastManager.show('Generando miniaturas, por favor espera...', 'info');

    const formData = new FormData();
    formData.append('video_uuid', _activeVideoUuid);

    try {
        // La llamada ahora es síncrona en PHP (FFmpeg directo)
        const route = ApiService.Routes.Studio.GenerateThumbs || { route: 'studio.generate_thumbs' };
        const res = await ApiService.post(route, formData);

        if (res.success && res.thumbnails) {
            ToastManager.show(res.message, 'success');
            
            // Guardar en estado local
            const video = _videosState.find(v => v.uuid === _activeVideoUuid);
            if (video) {
                video.generated_thumbnails = res.thumbnails;
            }
            
            // Renderizar inmediatamente
            renderGeneratedGrid(res.thumbnails);
        } else {
            ToastManager.show(res.message || 'No se generaron miniaturas.', 'error');
        }
    } catch (e) {
        console.error(e);
        ToastManager.show('Error al conectar con el servidor de generación.', 'error');
    } finally {
        // Restaurar botón
        btn.disabled = false;
        btn.innerHTML = originalContent;
        _isThumbOperationActive = false; // Liberar bloqueo
    }
}

function renderGeneratedGrid(thumbnails) {
    const grid = document.getElementById('generated-thumbs-grid');
    if (!grid) return;

    grid.innerHTML = '';
    
    // Validación extra: asegurarse que es un array
    if (!thumbnails || !Array.isArray(thumbnails) || thumbnails.length === 0) {
        grid.classList.add('d-none');
        return;
    }

    thumbnails.forEach(thumb => {
        // [CORRECCIÓN] Validar que el objeto y la propiedad path existan antes de usarlos
        if (!thumb || !thumb.path) {
            return; // Si el dato está corrupto, lo saltamos y seguimos con el siguiente
        }

        // Ajuste de ruta: PHP devuelve rutas relativas (public/storage/...), JS necesita base
        const pathStr = String(thumb.path); 
        const fullPath = pathStr.startsWith('http') ? pathStr : (window.BASE_PATH + pathStr);
        
        const div = document.createElement('div');
        div.className = 'generated-thumb-item';
        div.dataset.path = pathStr; 
        div.dataset.src = fullPath;
        div.dataset.color = thumb.color || '#000000';
        
        div.innerHTML = `<img src="${fullPath}" loading="lazy" alt="Opción generada">`;
        
        // Listener directo: Ahora solo llama a la función visual, NO al servidor
        div.addEventListener('click', () => selectGeneratedThumbnail(pathStr, thumb.color));
        
        grid.appendChild(div);
    });

    // Si después de filtrar los corruptos no quedó nada, ocultamos el grid
    if (grid.children.length === 0) {
        grid.classList.add('d-none');
    } else {
        grid.classList.remove('d-none');
    }
}

// [MODIFICADO] selectGeneratedThumbnail: Solo actualiza la UI y guarda estado temporal
function selectGeneratedThumbnail(path, color) {
    if (!_activeVideoUuid) return;

    // Guardar selección temporal en memoria
    _tempSelectedThumbnail = path;
    _tempDominantColor = color;

    // Actualizar previsualización principal visualmente
    const preview = document.getElementById('thumbnail-preview');
    if (preview) {
        preview.src = window.BASE_PATH + path;
        preview.classList.remove('d-none');
    }
    
    // Aplicar sombra de color
    applyDominantColor(color);
    
    // Validar requisitos para habilitar el botón de publicar
    validatePublishRequirements();

    // Feedback visual en el grid (Borde activo)
    const allItems = document.querySelectorAll('.generated-thumb-item');
    allItems.forEach(item => {
        if (item.dataset.path === path) {
            item.classList.add('active-thumb-selection');
            item.style.borderColor = 'var(--action-primary)';
            item.style.opacity = '1';
            item.style.transform = 'scale(1.05)';
        } else {
            item.classList.remove('active-thumb-selection');
            item.style.borderColor = 'transparent';
            item.style.opacity = '0.8';
            item.style.transform = 'scale(1)';
        }
    });
}

function applyDominantColor(color) {
    if (!color) return;
    const previewCard = document.querySelector('.video-preview-card');
    if (previewCard) {
        // [MODIFICADO] Neutralizar sombra de color
        previewCard.style.transition = 'box-shadow 0.3s ease';
        previewCard.style.boxShadow = 'none'; // Forzar sin sombra o mantener la default
    }
}