/**
 * public/assets/js/modules/studio/content-controller.js
 * Controlador para la gestión de contenido del canal.
 * ACTUALIZADO: Lógica síncrona de miniaturas y edición en modal.
 */

import { ApiService } from '../../core/services/api-service.js';
import { ToastManager } from '../../core/components/toast-manager.js';
import { DialogManager } from '../../core/components/dialog-manager.js';
// import { navigateTo } from '../../core/utils/url-manager.js'; // Ya no se usa para editar

let _container = null;
let _state = {
    page: 1,
    limit: 20,
    search: '',
    status: 'all', // all, published, queued, processing
    totalPages: 1
};
let _searchTimeout = null;
let _currentEditUuid = null; // Para saber qué video estamos editando

// Ruta base para las acciones del estudio (Fallback si no está en ApiRoutes)
const STUDIO_ROUTE = { route: 'studio-handler' };

export const ContentController = {
    init: () => {
        _container = document.querySelector('[data-section="channel-content"]');
        if (!_container) return;

        console.log("ContentController: Inicializado");
        
        // Reset state
        _state.page = 1;
        _state.search = '';
        _state.status = 'all';

        initEvents();
        initModalEvents(); // [NUEVO] Eventos del modal
        
        // Escuchar eventos de Dropdown (UiManager)
        document.removeEventListener('ui:dropdown-selected', onFilterSelected);
        document.addEventListener('ui:dropdown-selected', onFilterSelected);

        loadContent();

        // Listener para actualizaciones en tiempo real (Procesamiento de video)
        document.removeEventListener('socket:processing_complete', onVideoProcessed);
        document.addEventListener('socket:processing_complete', onVideoProcessed);

        document.removeEventListener('socket:processing_progress', onProcessingProgress);
        document.addEventListener('socket:processing_progress', onProcessingProgress);
    }
};

function onVideoProcessed() {
    if (_container && document.body.contains(_container)) {
        loadContent(true);
    }
}

function onProcessingProgress(e) {
    const data = e.detail.message;
    if (data && data.uuid) {
        const row = document.querySelector(`tr[data-row-uuid="${data.uuid}"]`);
        if (row) {
            const statusCell = row.cells[1];
            if (statusCell) {
                statusCell.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span class="material-symbols-rounded" style="font-size: 18px; color: var(--color-warning);">hourglass_top</span>
                        <span style="font-size: 13px;">Procesando (${data.percent}%)</span>
                    </div>
                `;
            }
        }
    }
}

function onFilterSelected(e) {
    if (!_container || !document.body.contains(_container)) return;
    
    const { type, value } = e.detail;
    
    // Filtro de estado
    if (type === 'filter_status') {
        _state.status = value;
        _state.page = 1;
        loadContent();
    }
}

function initEvents() {
    // 1. Buscador Expandible (Código Original conservado)
    const btnToggleSearch = _container.querySelector('[data-action="toggle-content-search"]');
    const searchDropdown = document.getElementById('content-search-dropdown');
    const inputSearch = document.getElementById('content-search-input');

    if (btnToggleSearch && searchDropdown) {
        btnToggleSearch.addEventListener('click', (e) => {
            e.stopPropagation();
            const isVisible = searchDropdown.style.display === 'block';
            
            if (isVisible) {
                searchDropdown.style.display = 'none';
                btnToggleSearch.classList.remove('active');
                btnToggleSearch.style.backgroundColor = '';
            } else {
                searchDropdown.style.display = 'block';
                btnToggleSearch.classList.add('active');
                btnToggleSearch.style.backgroundColor = 'var(--bg-hover-light)';
                if (inputSearch) setTimeout(() => inputSearch.focus(), 50);
            }
        });

        document.addEventListener('click', (e) => {
            if (searchDropdown.style.display === 'block' && 
                !searchDropdown.contains(e.target) && 
                !btnToggleSearch.contains(e.target)) {
                searchDropdown.style.display = 'none';
                btnToggleSearch.classList.remove('active');
                btnToggleSearch.style.backgroundColor = '';
            }
        });

        if (inputSearch) {
            inputSearch.addEventListener('input', (e) => {
                const val = e.target.value.trim();
                if (_searchTimeout) clearTimeout(_searchTimeout);
                
                _searchTimeout = setTimeout(() => {
                    _state.search = val;
                    _state.page = 1;
                    loadContent();
                }, 400);
            });

            inputSearch.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    searchDropdown.style.display = 'none';
                    btnToggleSearch.classList.remove('active');
                    btnToggleSearch.style.backgroundColor = '';
                }
            });
        }
    }

    // 2. Paginación
    const btnPrev = _container.querySelector('[data-action="prev-page"]');
    const btnNext = _container.querySelector('[data-action="next-page"]');

    if (btnPrev) {
        btnPrev.addEventListener('click', () => {
            if (_state.page > 1) {
                _state.page--;
                loadContent();
            }
        });
    }

    if (btnNext) {
        btnNext.addEventListener('click', () => {
            if (_state.page < _state.totalPages) {
                _state.page++;
                loadContent();
            }
        });
    }
    
    // 3. Acciones de Tabla
    const tableBody = document.getElementById('content-table-body');
    if (tableBody) {
        tableBody.addEventListener('click', (e) => {
            const btnEdit = e.target.closest('[data-action="edit-video"]');
            const btnDelete = e.target.closest('[data-action="delete-video"]');
            
            if (btnEdit) {
                 const videoUuid = btnEdit.dataset.uuid;
                 // [CAMBIO] Abrir modal en lugar de navegar
                 openEditModal(videoUuid);
            }

            if (btnDelete) {
                const uuid = btnDelete.dataset.uuid;
                const title = btnDelete.dataset.title;
                deleteVideo(uuid, title);
            }
        });
    }
}

// [NUEVO] Inicializar eventos dentro del Modal de Edición
function initModalEvents() {
    // Guardar borrador
    const saveBtn = document.getElementById('save-video-changes');
    if (saveBtn) saveBtn.addEventListener('click', () => saveChanges(false));

    // Publicar
    const publishBtn = document.getElementById('publish-video-btn');
    if (publishBtn) publishBtn.addEventListener('click', () => saveChanges(true));

    // Generar Miniaturas (Síncrono)
    const generateBtn = document.getElementById('generate-thumbs-btn');
    if (generateBtn) generateBtn.addEventListener('click', handleGenerateThumbs);

    // Subir miniatura personalizada
    const thumbInput = document.getElementById('custom-thumb-input');
    if (thumbInput) thumbInput.addEventListener('change', handleCustomThumbUpload);

    // Selección de miniatura generada (Delegación)
    const generatedGrid = document.getElementById('generated-thumbs-grid');
    if (generatedGrid) {
        generatedGrid.addEventListener('click', (e) => {
            const item = e.target.closest('.generated-thumb-item');
            if (item) handleSelectGeneratedThumb(item);
        });
    }
    
    // Cerrar modales genéricos
    document.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', () => {
            DialogManager.close('edit-video-dialog'); // Asumiendo que DialogManager soporta ID o close genérico
            // Si DialogManager.close() no acepta ID, usa el método que cierre el modal actual.
            // O usa: document.getElementById('edit-video-dialog').classList.remove('active');
            const dialog = document.getElementById('edit-video-dialog');
            if(dialog) dialog.classList.remove('active'); 
        });
    });
}

// --- LÓGICA DE EDICIÓN Y MINIATURAS ---

async function openEditModal(uuid) {
    _currentEditUuid = uuid;
    
    // Abrir modal (Asumiendo estructura estándar de modal CSS)
    const modal = document.getElementById('edit-video-dialog');
    if (modal) modal.classList.add('active');
    
    const formContainer = document.getElementById('edit-form-container');
    if (formContainer) formContainer.classList.add('loading');

    const formData = new FormData();
    formData.append('action', 'get_video_details');
    formData.append('video_uuid', uuid);

    try {
        const res = await ApiService.post(STUDIO_ROUTE, formData);

        if (res.success) {
            populateEditForm(res.video);
        } else {
            ToastManager.show(res.message, 'error');
            if(modal) modal.classList.remove('active');
        }
    } catch (e) {
        console.error(e);
        ToastManager.show('Error al cargar datos', 'error');
    } finally {
        if (formContainer) formContainer.classList.remove('loading');
    }
}

function populateEditForm(video) {
    const titleInput = document.getElementById('edit-title');
    const descInput = document.getElementById('edit-desc');

    if (titleInput) titleInput.value = video.title || '';
    if (descInput) descInput.value = video.description || '';

    updateThumbnailPreview(video.thumbnail_src, video.dominant_color);

    // Renderizar miniaturas ya existentes (si las hay guardadas)
    renderGeneratedThumbnails(video.generated_thumbnails || []);
}

function updateThumbnailPreview(src, color) {
    const img = document.getElementById('current-thumb-img');
    const container = document.querySelector('.current-thumbnail-preview');
    
    if (src) {
        if (img) {
            img.src = src;
            img.style.display = 'block';
        }
        if (container) container.style.backgroundColor = 'transparent';
    } else {
        if (img) img.style.display = 'none';
        if (container) container.style.backgroundColor = color || '#333';
    }
}

// [NUEVO] Lógica Síncrona: Pide al PHP y recibe el array inmediatamente
async function handleGenerateThumbs() {
    if (!_currentEditUuid) return;

    const btn = document.getElementById('generate-thumbs-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-rounded spin">sync</span> Generando...';
    }

    const formData = new FormData();
    formData.append('action', 'generate_thumbnails');
    formData.append('video_uuid', _currentEditUuid);

    try {
        // Esperamos la respuesta DIRECTA del servidor (FFmpeg ya corrió en PHP)
        const res = await ApiService.post(STUDIO_ROUTE, formData);

        if (res.success) {
            ToastManager.show(res.message, 'success');
            // Renderizamos inmediatamente lo que devolvió PHP
            renderGeneratedThumbnails(res.thumbnails);
        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch (e) {
        console.error(e);
        ToastManager.show('Error al generar miniaturas', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded">auto_awesome</span> Generar Automáticas';
        }
    }
}

// [NUEVO] Renderiza objetos {path, color}
function renderGeneratedThumbnails(thumbnails) {
    const grid = document.getElementById('generated-thumbs-grid');
    if (!grid) return;
    
    grid.innerHTML = '';

    if (!thumbnails || thumbnails.length === 0) {
        grid.innerHTML = '<p class="text-muted text-sm">No hay sugerencias disponibles.</p>';
        return;
    }

    thumbnails.forEach(thumb => {
        let path = '';
        let color = '#000000';

        // Compatibilidad hacia atrás por si hay datos viejos
        if (typeof thumb === 'string') {
            path = thumb;
        } else {
            path = thumb.path;
            color = thumb.color;
        }

        const div = document.createElement('div');
        div.className = 'generated-thumb-item';
        div.dataset.path = path;
        div.dataset.color = color; // Guardamos el color en el DOM

        div.innerHTML = `<img src="${path}" loading="lazy" alt="Opción">`;
        grid.appendChild(div);
    });
}

// [NUEVO] Envia path Y color al seleccionar
async function handleSelectGeneratedThumb(element) {
    document.querySelectorAll('.generated-thumb-item').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');

    const path = element.dataset.path;
    const color = element.dataset.color;

    const formData = new FormData();
    formData.append('action', 'select_generated_thumbnail');
    formData.append('video_uuid', _currentEditUuid);
    formData.append('thumbnail_path', path);
    formData.append('dominant_color', color); // ¡Clave! Enviamos el color pre-calculado

    try {
        const res = await ApiService.post(STUDIO_ROUTE, formData);

        if (res.success) {
            ToastManager.show('Miniatura seleccionada', 'success');
            updateThumbnailPreview(path, color);
            // Actualizar tabla de fondo si es posible
            loadContent(true); 
        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch (e) {
        ToastManager.show('Error de red', 'error');
    }
}

async function handleCustomThumbUpload(e) {
    const file = e.target.files[0];
    if (!file || !_currentEditUuid) return;

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        ToastManager.show('Formato inválido (Use JPG/PNG)', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'upload_thumbnail');
    formData.append('video_uuid', _currentEditUuid);
    formData.append('thumbnail', file);

    const label = document.querySelector('.custom-upload-btn');
    const originalText = label ? label.textContent : 'Subir';
    if (label) label.textContent = 'Subiendo...';

    try {
        const res = await ApiService.post(STUDIO_ROUTE, formData);
        if (res.success) {
            ToastManager.show('Miniatura subida', 'success');
            updateThumbnailPreview(res.new_src, res.dominant_color);
            loadContent(true);
        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch (error) {
        ToastManager.show('Error al subir', 'error');
    } finally {
        if (label) label.textContent = originalText;
        e.target.value = '';
    }
}

async function saveChanges(publish = false) {
    if (!_currentEditUuid) return;

    const title = document.getElementById('edit-title').value.trim();
    const desc = document.getElementById('edit-desc').value.trim();

    if (!title) {
        ToastManager.show('El título es obligatorio', 'warning');
        return;
    }

    const btn = publish ? document.getElementById('publish-video-btn') : document.getElementById('save-video-changes');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Procesando...';

    const formData = new FormData();
    formData.append('action', 'save_metadata');
    formData.append('video_uuid', _currentEditUuid);
    formData.append('title', title);
    formData.append('description', desc);
    formData.append('publish', publish);

    try {
        const res = await ApiService.post(STUDIO_ROUTE, formData);

        if (res.success) {
            ToastManager.show(res.message, 'success');
            const modal = document.getElementById('edit-video-dialog');
            if (modal) modal.classList.remove('active');
            loadContent(true);
        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch (e) {
        ToastManager.show('Error al guardar', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// --- FUNCIONES ORIGINALES (Carga y Renderizado de Tabla) ---

async function loadContent(silent = false) {
    const tableBody = document.getElementById('content-table-body');
    const loading = document.getElementById('content-loading');
    const empty = document.getElementById('content-empty');

    if (!tableBody) return;

    if (!silent) {
        tableBody.innerHTML = '';
        if (loading) loading.classList.remove('d-none');
        if (empty) empty.classList.add('d-none');
    }

    const formData = new FormData();
    formData.append('page', _state.page);
    formData.append('limit', _state.limit);
    formData.append('search', _state.search);
    formData.append('status', _state.status);

    try {
        const res = await ApiService.post(ApiService.Routes.Studio.GetContent, formData, { signal: window.PAGE_SIGNAL });

        if (!silent && loading) loading.classList.add('d-none');

        if (res.success) {
            _state.totalPages = res.pagination.total_pages;
            updatePaginationUI(res.pagination);
            renderTable(res.videos);
        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch (e) {
        if (e.isAborted) return;
        console.error(e);
        if (!silent && loading) loading.classList.add('d-none');
        // ToastManager.show('Error de conexión', 'error'); // Opcional silenciar
    }
}

function renderTable(videos) {
    const tableBody = document.getElementById('content-table-body');
    const empty = document.getElementById('content-empty');

    if (videos.length === 0) {
        if (empty) empty.classList.remove('d-none');
        tableBody.innerHTML = '';
        return;
    }

    if (empty) empty.classList.add('d-none');

    let html = '';
    
    videos.forEach(v => {
        let thumbHtml = '';
        if (v.thumbnail_url) {
            thumbHtml = `<img src="${window.BASE_PATH}${v.thumbnail_url}" class="video-row-thumb" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">`;
        } else {
            thumbHtml = `<div class="video-row-thumb placeholder" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #222; color: #555;"><span class="material-symbols-rounded">movie</span></div>`;
        }

        let statusBadge = '';
        let statusText = '';
        let iconColor = 'var(--text-secondary)';
        
        switch(v.status) {
            case 'published':
                statusBadge = 'visibility'; statusText = 'Público'; iconColor = 'var(--color-success)'; break;
            case 'waiting_for_metadata':
            case 'queued':
                statusBadge = 'draft'; statusText = 'Borrador'; break;
            case 'processing':
            case 'uploading_chunks':
                statusBadge = 'hourglass_empty'; 
                const percent = v.processing_percentage || 0;
                statusText = `Procesando (${percent}%)`; 
                break;
            case 'error':
                statusBadge = 'error'; statusText = 'Error'; iconColor = 'var(--color-error)'; break;
            default:
                statusBadge = 'help'; statusText = v.status;
        }

        html += `
            <tr class="table-row-item" data-row-uuid="${v.uuid}">
                <td style="padding: 12px 16px;">
                    <div style="display: flex; gap: 12px;">
                        <div style="width: 120px; height: 68px; flex-shrink: 0; position: relative; background: #000; border-radius: 4px; overflow: hidden;">
                            ${thumbHtml}
                            <div style="position: absolute; bottom: 4px; right: 4px; background: rgba(0,0,0,0.8); color: #fff; font-size: 11px; padding: 1px 4px; border-radius: 2px;">
                                ${v.duration_formatted}
                            </div>
                        </div>
                        <div style="display: flex; flex-direction: column; justify-content: center; gap: 4px; overflow: hidden;">
                            <span style="font-weight: 500; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; max-width: 100%; color: var(--text-primary);" title="${v.title}">
                                ${v.title || 'Sin título'}
                            </span>
                            <span style="font-size: 12px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                ${v.description || 'Sin descripción'}
                            </span>
                        </div>
                    </div>
                </td>
                
                <td>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span class="material-symbols-rounded" style="font-size: 18px; color: ${iconColor};">${statusBadge}</span>
                        <span style="font-size: 13px;">${statusText}</span>
                    </div>
                </td>

                <td>
                    <span style="font-size: 13px;">${new Date(v.created_at).toLocaleDateString()}</span>
                    <div style="font-size: 11px; color: var(--text-tertiary);">${v.time_ago || ''}</div>
                </td>

                <td class="text-right">
                    <span style="font-size: 13px;">${v.duration_formatted}</span>
                </td>

                <td>
                    <div style="display: flex; justify-content: flex-end; gap: 4px;">
                        <button class="component-button square" data-action="edit-video" data-uuid="${v.uuid}" title="Editar" style="border: none;">
                            <span class="material-symbols-rounded" style="font-size: 18px;">edit</span>
                        </button>
                        <button class="component-button square" data-action="delete-video" data-uuid="${v.uuid}" data-title="${v.title}" title="Eliminar" style="color: var(--color-error); border: none;">
                            <span class="material-symbols-rounded" style="font-size: 18px;">delete</span>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = html;
}

function updatePaginationUI(pagination) {
    const info = document.querySelector('[data-element="pagination-info"]');
    const btnPrev = document.querySelector('[data-action="prev-page"]');
    const btnNext = document.querySelector('[data-action="next-page"]');

    if (info) info.textContent = `${pagination.current}/${pagination.total_pages}`;
    
    if (btnPrev) btnPrev.disabled = (pagination.current <= 1);
    if (btnNext) btnNext.disabled = (pagination.current >= pagination.total_pages);
}

async function deleteVideo(uuid, title) {
    const confirmed = await DialogManager.confirm({
        title: '¿Eliminar video?',
        message: `Se eliminará "${title || 'este video'}" permanentemente.`,
        type: 'danger',
        confirmText: 'Eliminar',
        cancelText: 'Cancelar'
    });

    if (!confirmed) return;

    DialogManager.showLoading('Eliminando...');
    const formData = new FormData();
    formData.append('video_uuid', uuid);

    try {
        const res = await ApiService.post(ApiService.Routes.Studio.DeleteVideo, formData, { signal: window.PAGE_SIGNAL });
        DialogManager.close();
        if (res.success) {
            ToastManager.show('Video eliminado', 'success');
            loadContent(true);
        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch (e) {
        if (e.isAborted) return;
        DialogManager.close();
        ToastManager.show('Error al eliminar', 'error');
    }
}