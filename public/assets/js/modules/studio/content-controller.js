/**
 * public/assets/js/modules/studio/content-controller.js
 * Controlador para la gestión de contenido del canal.
 */

import { ApiService } from '../../core/services/api-service.js';
import { ToastManager } from '../../core/components/toast-manager.js';
import { DialogManager } from '../../core/components/dialog-manager.js';
import { navigateTo } from '../../core/utils/url-manager.js';

let _container = null;
let _state = {
    page: 1,
    limit: 20,
    search: '',
    status: 'all', // all, published, queued, processing
    totalPages: 1
};
let _searchTimeout = null;

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
        
        // Escuchar eventos de Dropdown (UiManager)
        document.removeEventListener('ui:dropdown-selected', onFilterSelected);
        document.addEventListener('ui:dropdown-selected', onFilterSelected);

        loadContent();

        // Listener para actualizaciones en tiempo real (Completado)
        document.removeEventListener('socket:processing_complete', onVideoProcessed);
        document.addEventListener('socket:processing_complete', onVideoProcessed);

        // [NUEVO] Listener para progreso en tiempo real (Porcentaje)
        document.removeEventListener('socket:processing_progress', onProcessingProgress);
        document.addEventListener('socket:processing_progress', onProcessingProgress);
    }
};

function onVideoProcessed() {
    if (_container && document.body.contains(_container)) {
        loadContent(true);
    }
}

// [NUEVO] Handler para actualizar la fila de la tabla sin recargar
function onProcessingProgress(e) {
    const data = e.detail.message;
    if (data && data.uuid) {
        // Buscar la fila específica en la tabla mediante el atributo data-row-uuid
        const row = document.querySelector(`tr[data-row-uuid="${data.uuid}"]`);
        if (row) {
            const statusCell = row.cells[1]; // La segunda columna es "Estado"
            if (statusCell) {
                // Actualizamos el contenido visual
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
    // 1. Buscador Expandible
    const btnToggleSearch = _container.querySelector('[data-action="toggle-content-search"]');
    const searchDropdown = document.getElementById('content-search-dropdown');
    const inputSearch = document.getElementById('content-search-input');

    if (btnToggleSearch && searchDropdown) {
        btnToggleSearch.addEventListener('click', (e) => {
            e.stopPropagation();
            const isVisible = searchDropdown.style.display === 'block';
            
            if (isVisible) {
                searchDropdown.style.display = 'none';
                btnToggleSearch.classList.remove('active'); // Quitar estado activo
                btnToggleSearch.style.backgroundColor = '';
            } else {
                searchDropdown.style.display = 'block';
                btnToggleSearch.classList.add('active'); // Poner estado activo (bg-hover)
                btnToggleSearch.style.backgroundColor = 'var(--bg-hover-light)';
                if (inputSearch) setTimeout(() => inputSearch.focus(), 50);
            }
        });

        // Cerrar al hacer click fuera
        document.addEventListener('click', (e) => {
            if (searchDropdown.style.display === 'block' && 
                !searchDropdown.contains(e.target) && 
                !btnToggleSearch.contains(e.target)) {
                searchDropdown.style.display = 'none';
                btnToggleSearch.classList.remove('active');
                btnToggleSearch.style.backgroundColor = '';
            }
        });

        // Busqueda Input
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
                navigateTo(`s/channel/upload/${videoUuid}`);
            }

            if (btnDelete) {
                const uuid = btnDelete.dataset.uuid;
                const title = btnDelete.dataset.title;
                deleteVideo(uuid, title);
            }
        });
    }
}

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
        ToastManager.show('Error de conexión', 'error');
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
                // [MEJORA] Mostrar porcentaje inicial si viene del servidor
                const percent = v.processing_percentage || 0;
                statusText = `Procesando (${percent}%)`; 
                break;
            case 'error':
                statusBadge = 'error'; statusText = 'Error'; iconColor = 'var(--color-error)'; break;
            default:
                statusBadge = 'help'; statusText = v.status;
        }

        // [MODIFICADO] Añadido data-row-uuid para identificación precisa
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