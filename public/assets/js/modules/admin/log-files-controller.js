/**
 * public/assets/js/modules/admin/log-files-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { Dialog } from '../../core/dialog-manager.js';
import { navigateTo } from '../../core/url-manager.js';
import { I18n } from '../../core/i18n-manager.js'; // Importación añadida

let _container = null;
let _filesData = [];
let _selectedPaths = new Set();
let _viewMode = 'grid';

export const LogFilesController = {
    init: () => {
        _container = document.querySelector('[data-section="admin-log-files"]');
        if (!_container) return;

        console.log("LogFilesController: Inicializado");
        
        _filesData = [];
        _selectedPaths = new Set();
        _viewMode = 'grid';

        initEvents();
        loadFiles();
    }
};

function initEvents() {
    const btnView = _container.querySelector('[data-action="change-view"]');
    if(btnView) btnView.addEventListener('click', toggleView);

    const btnSearch = _container.querySelector('[data-action="toggle-search"]');
    const searchPanel = _container.querySelector('[data-element="search-panel"]');
    const inputSearch = _container.querySelector('[data-element="search-input"]');
    
    if(btnSearch && searchPanel) {
        btnSearch.addEventListener('click', () => {
            searchPanel.classList.toggle('active');
            btnSearch.classList.toggle('active');
            if(searchPanel.classList.contains('active') && inputSearch) inputSearch.focus();
        });
    }

    if(inputSearch) {
        inputSearch.addEventListener('input', (e) => {
            renderList(e.target.value.toLowerCase());
        });
    }

    _container.querySelector('[data-action="close-selection"]')?.addEventListener('click', clearSelection);
    _container.querySelector('[data-action="delete-selected"]')?.addEventListener('click', deleteSelected);
    
    _container.querySelector('[data-action="view-log-content"]')?.addEventListener('click', () => {
        if (_selectedPaths.size === 0) return;
        
        const paths = Array.from(_selectedPaths).join(',');
        navigateTo('admin/file-viewer', { files: paths });
    });

    // Listener para botón de descarga
    _container.querySelector('[data-action="download-selected"]')?.addEventListener('click', handleDownload);
}

// === Lógica de descarga SIN RECARGAR ===
async function handleDownload() {
    if (_selectedPaths.size === 0) {
        return;
    }

    const pathsArray = Array.from(_selectedPaths);
    const pathsString = pathsArray.join(','); 
    
    if (pathsArray.length > 1) {
        Toast.show(I18n.t('admin.logs.zip_compressing') || 'Comprimiendo logs en ZIP...', 'info');
    } else {
        Toast.show(I18n.t('admin.logs.download_preparing') || 'Preparando descarga...', 'info');
    }

    const formData = new FormData();
    formData.append('file', pathsString);
    formData.append('type', 'log'); 

    try {
        const route = ApiService.Routes.Admin.request_download || { route: 'admin.request_download' };
        const res = await ApiService.post(route, formData);
        
      if (res.success && res.download_url) {
            // [SOLUCIÓN ULTRA A FONDO]
            const downloadLink = document.createElement('a');
            downloadLink.href = window.BASE_PATH + 'public/' + res.download_url;
            
            // 1. Forzar atributo download (ayuda al navegador a entender la intención)
            downloadLink.setAttribute('download', '');
            
            // 2. IMPORTANTE: target="_blank"
            // Si la descarga falla (ej. PHP devuelve error de texto), se abre en nueva pestaña
            // sin matar la aplicación actual. Si es exitosa, Chrome/Firefox cierran la pestaña al instante.
            downloadLink.setAttribute('target', '_blank'); 
            
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            
            // Pequeño delay antes de remover para asegurar que el evento click se propague en todos los navegadores
            setTimeout(() => {
                document.body.removeChild(downloadLink);
            }, 100);

            Toast.show(I18n.t('admin.logs.download_started') || 'Descarga iniciada.', 'success');
            clearSelection();
        } else {
            Toast.show(res.message || (I18n.t('admin.logs.download_token_error') || 'Error al obtener token de descarga.'), 'error');
        }
    } catch (e) {
        Toast.show(I18n.t('js.core.connection_error') || 'Error de conexión.', 'error');
    }
}

async function loadFiles() {
    const list = _container.querySelector('[data-component="file-list"]');
    list.innerHTML = '<div class="state-loading"><div class="spinner-sm"></div></div>';

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.GetLogFiles);
        if(res.success) {
            _filesData = res.files;
            renderList();
        } else {
            list.innerHTML = `<div class="state-error">${res.message}</div>`;
        }
    } catch(e) {
        list.innerHTML = `<div class="state-error">${I18n.t('js.core.connection_error') || 'Error de conexión'}</div>`;
    }
}

function renderList(query = '') {
    const list = _container.querySelector('[data-component="file-list"]');
    const countLabel = _container.querySelector('[data-element="count-wrapper"]');
    
    const filtered = _filesData.filter(f => f.filename.toLowerCase().includes(query));

    if(countLabel) countLabel.innerText = `${filtered.length} ${I18n.t('admin.logs.files_count') || 'archivos'}`;

    if(filtered.length === 0) {
        list.innerHTML = `<div class="state-empty">${I18n.t('admin.logs.empty') || 'No se encontraron archivos de log.'}</div>`;
        return;
    }

    let html = (_viewMode === 'grid') ? buildGridHtml(filtered) : buildTableHtml(filtered);
    list.innerHTML = html;

    const items = list.querySelectorAll('.component-card, .table-row-item');
    items.forEach(el => {
        el.addEventListener('click', () => toggleSelection(el.dataset.path));
    });
}

function getFileIcon(category) {
    switch(category) {
        case 'app': return 'terminal'; 
        case 'database': return 'database';
        case 'security': return 'shield';
        default: return 'description';
    }
}

function getFileColor(category) {
    switch(category) {
        case 'app': return 'background-color: #e3f2fd; color: #1976d2;';
        case 'database': return 'background-color: #fff3e0; color: #ed6c02;';
        case 'security': return 'background-color: #ffebee; color: #d32f2f;';
        default: return 'background-color: #f5f5f5; color: #666;';
    }
}

function buildGridHtml(files) {
    return files.map(file => {
        const isSel = _selectedPaths.has(file.path) ? 'is-selected' : '';
        const icon = getFileIcon(file.category);
        const style = getFileColor(file.category);

        return `
        <div class="component-card ${isSel}" data-path="${file.path}">
            <div class="component-list-item-content">
                <div class="component-card__profile-picture component-avatar--list" 
                     style="display:flex; align-items:center; justify-content:center; ${style}">
                    <span class="material-symbols-rounded" style="font-size:24px;">${icon}</span>
                </div>
                <span class="component-badge" data-tooltip="${I18n.t('admin.logs.col_filename') || 'Nombre Archivo'}" style="font-family: monospace;">${file.filename}</span>
                <span class="component-badge" data-tooltip="${I18n.t('admin.logs.col_category') || 'Carpeta/Categoría'}">${file.category}</span>
                <span class="component-badge" data-tooltip="${I18n.t('admin.logs.col_size') || 'Tamaño'}">${file.size}</span>
                <span class="component-badge" data-tooltip="${I18n.t('admin.logs.col_date') || 'Fecha Modificación'}">${file.modified_at}</span>
            </div>
        </div>`;
    }).join('');
}

function buildTableHtml(files) {
    const rows = files.map(file => {
        const isSel = _selectedPaths.has(file.path) ? 'is-selected' : '';
        const icon = getFileIcon(file.category);
        const style = getFileColor(file.category);

        return `
        <tr class="table-row-item ${isSel}" data-path="${file.path}" style="cursor:pointer;">
            <td style="width:50px">
                <div class="component-card__profile-picture component-avatar--list" 
                     style="width:32px; height:32px; display:flex; align-items:center; justify-content:center; ${style}">
                    <span class="material-symbols-rounded" style="font-size:18px;">${icon}</span>
                </div>
            </td>
            <td style="font-family:monospace; font-weight:600;">${file.filename}</td>
            <td>${file.category}</td>
            <td>${file.size}</td>
            <td>${file.modified_at}</td>
        </tr>`;
    }).join('');

    return `
    <div class="component-table-wrapper">
        <table class="component-table">
            <thead>
                <tr>
                    <th style="width:50px"></th>
                    <th>${I18n.t('admin.logs.col_filename') || 'Archivo'}</th>
                    <th>${I18n.t('admin.logs.col_category') || 'Categoría'}</th>
                    <th>${I18n.t('admin.logs.col_size') || 'Tamaño'}</th>
                    <th>${I18n.t('admin.logs.col_date') || 'Fecha'}</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
    </div>`;
}

function toggleSelection(path) {
    if(_selectedPaths.has(path)) _selectedPaths.delete(path);
    else _selectedPaths.add(path);
    updateToolbarState();
    renderList(document.querySelector('[data-element="search-input"]')?.value || '');
}

function clearSelection() {
    _selectedPaths.clear();
    updateToolbarState();
    renderList(document.querySelector('[data-element="search-input"]')?.value || '');
}

function updateToolbarState() {
    const defGroup = _container.querySelector('[data-element="toolbar-group-default"]');
    const actGroup = _container.querySelector('[data-element="toolbar-group-actions"]');
    const indicator = _container.querySelector('[data-element="selection-indicator"]');

    if(_selectedPaths.size > 0) {
        defGroup.classList.add('d-none');
        actGroup.classList.remove('d-none');
        indicator.innerText = `${_selectedPaths.size} ${I18n.t('admin.logs.selected_count') || 'seleccionados'}`;
        
        // Habilitar botón descargar (permitir múltiples)
        const btnDownload = _container.querySelector('[data-action="download-selected"]');
        if (btnDownload) {
            btnDownload.style.opacity = '1';
            btnDownload.disabled = false;
            btnDownload.dataset.tooltip = _selectedPaths.size > 1 
                ? I18n.t('admin.logs.download_zip_count', [_selectedPaths.size]) || `Descargar ZIP (${_selectedPaths.size})` 
                : I18n.t('admin.logs.download_log') || 'Descargar Log';
        }

    } else {
        defGroup.classList.remove('d-none');
        actGroup.classList.add('d-none');
    }
}

function toggleView() {
    _viewMode = (_viewMode === 'grid') ? 'table' : 'grid';
    const btn = _container.querySelector('[data-action="change-view"] span');
    const header = _container.querySelector('[data-element="page-header"]');
    
    if(_viewMode === 'table') {
        btn.innerText = 'table_rows';
        _container.classList.add('component-wrapper--full');
        header.classList.add('d-none');
    } else {
        btn.innerText = 'grid_view';
        _container.classList.remove('component-wrapper--full');
        header.classList.remove('d-none');
    }
    renderList(document.querySelector('[data-element="search-input"]')?.value || '');
}

async function deleteSelected() {
    if(!await Dialog.confirm({ 
        title: I18n.t('admin.logs.delete_title') || '¿Eliminar archivos?', 
        message: I18n.t('admin.logs.delete_message') || 'Esta acción borrará los logs físicamente del servidor.', 
        type: 'danger' 
    })) return;

    const paths = Array.from(_selectedPaths);
    const formData = new FormData();
    formData.append('paths', paths.join(','));

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.DeleteLogFiles, formData);
        if(res.success) {
            Toast.show(I18n.t('admin.logs.delete_success') || 'Archivos eliminados', 'success');
            clearSelection();
            loadFiles();
        } else {
            Toast.show(res.message, 'error');
        }
    } catch(e) { Toast.show(I18n.t('admin.logs.delete_error') || 'Error al eliminar', 'error'); }
}