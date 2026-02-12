/**
 * public/assets/js/modules/studio/upload-controller.js
 */

import { ApiService } from '../../core/services/api-service.js';
import { ToastManager } from '../../core/components/toast-manager.js';
import { SocketClient } from '../../core/services/socket-client.js';
import { I18nManager } from '../../core/utils/i18n-manager.js';

const CHUNK_SIZE = 2 * 1024 * 1024; // 2MB
const MAX_FILES = 3;

let _container = null;
let _filesQueue = [];
let _activeBatchId = null;
let _isUploading = false;
let _activeVideoUuid = null; // UUID del video que se está editando

export const UploadController = {
    init: async () => {
        _container = document.querySelector('[data-section="channel-upload"]');
        if (!_container) return;

        console.log("UploadController: Inicializado");
        
        _filesQueue = [];
        _activeBatchId = null;
        _isUploading = false;
        
        initDropzone();
        initEditorEvents();
        
        // Escuchar eventos de WebSockets (Worker -> Server -> Frontend)
        document.removeEventListener('socket:processing_complete', onProcessingComplete);
        document.addEventListener('socket:processing_complete', onProcessingComplete);
        
        // Verificar si hay una subida pendiente al recargar
        checkPendingUploads();
    }
};

async function checkPendingUploads() {
    try {
        const res = await ApiService.post(ApiService.Routes.Studio.GetPending, new FormData(), { signal: window.PAGE_SIGNAL });
        if (res.success && res.videos.length > 0) {
            // Restaurar estado: Mostrar editor para el primer video pendiente
            const video = res.videos[0];
            showEditor(video);
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

    // Drag & Drop visual feedback
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
    if (_isUploading) return;
    
    const validFiles = Array.from(files).filter(f => f.type.startsWith('video/'));
    
    if (validFiles.length === 0) {
        ToastManager.show('Selecciona archivos de video válidos.', 'warning');
        return;
    }

    if (validFiles.length > MAX_FILES) {
        ToastManager.show(`Máximo ${MAX_FILES} videos a la vez.`, 'warning');
        return;
    }

    _filesQueue = validFiles;
    _activeBatchId = 'batch_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    
    startBatchUpload();
}

function startBatchUpload() {
    _isUploading = true;
    
    // UI Switch: Ocultar Dropzone, Mostrar Lista
    document.getElementById('upload-dropzone').classList.add('d-none');
    const listContainer = document.getElementById('upload-progress-list');
    listContainer.classList.remove('d-none');
    listContainer.innerHTML = '';

    // Renderizar items iniciales
    _filesQueue.forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'upload-item';
        item.id = `upload-item-${index}`;
        item.innerHTML = `
            <div class="upload-item-header">
                <span class="filename">${file.name}</span>
                <span class="percentage">0%</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" style="width: 0%"></div>
            </div>
            <div class="upload-item-status">
                <span class="status-text">Esperando...</span>
            </div>
        `;
        listContainer.appendChild(item);
    });

    // Iniciar subida secuencial
    processNextInQueue(0);
}

async function processNextInQueue(index) {
    if (index >= _filesQueue.length) {
        // Todos subidos. Esperar procesamiento del Worker.
        document.getElementById('global-upload-status').style.display = 'block';
        ToastManager.show('Archivos subidos. Procesando...', 'info');
        return;
    }

    const file = _filesQueue[index];
    const uiItem = document.getElementById(`upload-item-${index}`);
    
    try {
        await uploadSingleFile(file, uiItem);
        // Éxito en este archivo, siguiente
        processNextInQueue(index + 1);
    } catch (error) {
        console.error("Error subiendo archivo:", error);
        handleBatchFailure();
    }
}

async function uploadSingleFile(file, uiItem) {
    const statusText = uiItem.querySelector('.status-text');
    const fill = uiItem.querySelector('.progress-fill');
    const percentLabel = uiItem.querySelector('.percentage');

    statusText.textContent = 'Iniciando subida...';

    // 1. Inicializar en Backend
    const initData = new FormData();
    initData.append('action', 'init_upload');
    initData.append('batch_id', _activeBatchId);
    initData.append('file_name', file.name);

    const initRes = await ApiService.post(ApiService.Routes.Studio.InitUpload, initData);
    
    if (!initRes.success) throw new Error(initRes.message);
    const videoUuid = initRes.video_uuid;

    // 2. Loop de Chunks
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    
    for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
        const start = chunkIndex * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, file.size);
        const chunk = file.slice(start, end);

        const formData = new FormData();
        formData.append('action', 'upload_chunk');
        formData.append('video_uuid', videoUuid);
        formData.append('chunk_index', chunkIndex);
        formData.append('chunk', chunk);
        formData.append('is_last', (chunkIndex === totalChunks - 1) ? 'true' : 'false');

        // Subida del chunk
        // Nota: Aquí se podría usar XMLHttpRequest para progreso de subida del chunk individual,
        // pero por simplicidad actualizamos la barra por cada chunk completado.
        const chunkRes = await ApiService.post(ApiService.Routes.Studio.UploadChunk, formData);
        
        if (!chunkRes.success) throw new Error(chunkRes.message);

        // Actualizar UI
        const percent = Math.round(((chunkIndex + 1) / totalChunks) * 100);
        fill.style.width = `${percent}%`;
        percentLabel.textContent = `${percent}%`;
        statusText.textContent = `Subiendo... ${percent}%`;
    }

    // Al finalizar subida, cambiar estado visual a "Procesando"
    statusText.textContent = 'En cola de procesamiento...';
    fill.classList.add('processing');
    
    // Guardamos el UUID en el elemento para referenciarlo luego
    uiItem.dataset.uuid = videoUuid;
}

async function handleBatchFailure() {
    _isUploading = false;
    ToastManager.show('Error en la subida. Cancelando lote...', 'error');
    
    const formData = new FormData();
    formData.append('batch_id', _activeBatchId);
    await ApiService.post(ApiService.Routes.Studio.CancelBatch, formData);
    
    setTimeout(() => window.location.reload(), 2000);
}

// === LÓGICA DE EDITOR Y MINIATURAS ===

function initEditorEvents() {
    const inputThumb = document.getElementById('input-thumbnail');
    const dropzoneThumb = document.getElementById('thumbnail-dropzone');
    
    // Trigger input file
    dropzoneThumb?.addEventListener('click', () => inputThumb.click());

    // Subida inmediata de miniatura al seleccionar
    inputThumb?.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        if (!_activeVideoUuid) {
            ToastManager.show('Espera a que el video termine de procesarse.', 'warning');
            return;
        }

        // Preview local inmediata
        const reader = new FileReader();
        reader.onload = (evt) => {
            const img = document.getElementById('thumbnail-preview');
            img.src = evt.target.result;
            img.classList.remove('d-none');
        };
        reader.readAsDataURL(file);

        // Subida al servidor
        const formData = new FormData();
        formData.append('video_uuid', _activeVideoUuid);
        formData.append('thumbnail', file);

        ToastManager.show('Subiendo miniatura...', 'info');
        
        try {
            const res = await ApiService.post(ApiService.Routes.Studio.UploadThumbnail, formData);
            if (res.success) {
                ToastManager.show('Miniatura guardada', 'success');
                validatePublishRequirements(); // Verificar si ya se puede publicar
            } else {
                ToastManager.show(res.message, 'error');
            }
        } catch (e) {
            ToastManager.show('Error al subir imagen', 'error');
        }
    });

    // Botón Publicar
    document.getElementById('btn-publish')?.addEventListener('click', () => saveMetadata(true));
    // Botón Borrador
    document.getElementById('btn-save-draft')?.addEventListener('click', () => saveMetadata(false));
}

function showEditor(videoData) {
    // Ocultar fases anteriores
    document.getElementById('upload-dropzone').classList.add('d-none');
    document.getElementById('upload-progress-list').classList.add('d-none');
    document.getElementById('global-upload-status').style.display = 'none';

    // Mostrar Editor
    const editor = document.getElementById('video-editor-area');
    editor.classList.remove('d-none');

    // Llenar datos
    _activeVideoUuid = videoData.uuid;
    document.getElementById('meta-title').value = videoData.title || '';
    document.getElementById('meta-desc').value = videoData.description || '';
    document.getElementById('meta-filename').textContent = videoData.title + '.mp4'; // O el nombre real si lo tuviéramos guardado

    // Si ya tiene miniatura (recarga de página), mostrarla
    if (videoData.thumbnail_src) {
        const img = document.getElementById('thumbnail-preview');
        img.src = videoData.thumbnail_src;
        img.classList.remove('d-none');
        validatePublishRequirements();
    }
}

// Evento de WebSocket: El worker terminó de procesar
function onProcessingComplete(e) {
    const data = e.detail.message;
    if (data && data.uuid) {
        // Buscar el item en la lista de progreso
        const items = document.querySelectorAll('.upload-item');
        let found = false;
        
        items.forEach(item => {
            if (item.dataset.uuid === data.uuid) {
                found = true;
                item.querySelector('.status-text').textContent = '¡Listo para editar!';
                item.querySelector('.progress-fill').classList.remove('processing');
                item.querySelector('.progress-fill').style.backgroundColor = 'var(--color-success)';
                
                // Transición automática al editor para el primer video completado
                // (Opcional: Podríamos hacer que el usuario haga clic para editar)
                setTimeout(() => {
                    showEditor({
                        uuid: data.uuid,
                        title: data.title || 'Video sin título'
                    });
                }, 500);
            }
        });
        
        // Si no estaba en la lista visual (recarga de página), ignorar o manejar lógica global
    }
}

function validatePublishRequirements() {
    const title = document.getElementById('meta-title').value.trim();
    const hasThumb = !document.getElementById('thumbnail-preview').classList.contains('d-none');
    const btnPublish = document.getElementById('btn-publish');

    if (title && hasThumb) {
        btnPublish.disabled = false;
    } else {
        btnPublish.disabled = true;
    }
}

// Listeners para validación en tiempo real
document.addEventListener('input', (e) => {
    if (e.target.id === 'meta-title') validatePublishRequirements();
});

async function saveMetadata(publish = false) {
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
                // Redirigir a la lista de contenido o limpiar editor
                setTimeout(() => {
                    window.location.href = window.BASE_PATH + 'channel/my-content'; // Ajustar ruta
                }, 1000);
            }
        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch (e) {
        ToastManager.show('Error al guardar', 'error');
    } finally {
        btn.innerText = originalText;
        btn.disabled = false;
        if (publish) validatePublishRequirements();
    }
}