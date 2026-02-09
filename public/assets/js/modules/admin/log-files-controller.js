/**
 * public/assets/js/modules/admin/log-files-controller.js
 * Versión Segura (DOM API)
 */

import { ApiService } from '../../core/services/api-service.js';
import { ToastManager } from '../../core/components/toast-manager.js';
import { DialogManager } from '../../core/components/dialog-manager.js';
import { navigateTo } from '../../core/utils/url-manager.js';
import { I18nManager } from '../../core/utils/i18n-manager.js';
let _container = null;
let _filesData = [];
let _selectedPaths = new Set();
let _viewMode = 'grid';

export const LogFilesController = {
    init: () => {
        _container = document.querySelector('[data-section="admin-log-files"]');
        if (!_container) return;

        console.log("LogFilesController: Inicializado (Safe Mode)");
        
        _filesData = [];
        _selectedPaths = new Set();
        _viewMode = 'grid';

        initEvents();
        loadFiles();

        document.removeEventListener('socket:download_ready', onDownloadReady);
        document.addEventListener('socket:download_ready', onDownloadReady);
    }
};

function onDownloadReady(e) {
    if (!_container || !document.body.contains(_container)) return;

    const data = e.detail.message;
    if (data && data.url) {
        triggerBrowserDownload(data.url);
        ToastManager.show(I18nManager.t('admin.logs.download_started') || 'Descarga iniciada.', 'success');
        clearSelection();
    }
}

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

    _container.querySelector('[data-action="download-selected"]')?.addEventListener('click', handleDownload);
}

async function handleDownload() {
    if (_selectedPaths.size === 0) return;

    const pathsArray = Array.from(_selectedPaths);
    const pathsString = pathsArray.join(','); 
    
    ToastManager.show(I18nManager.t('admin.logs.download_preparing') || 'Solicitando descarga...', 'info');

    const formData = new FormData();
    formData.append('file', pathsString);
    formData.append('type', 'log'); 

    try {
        const route = ApiService.Routes.Admin.request_download || { route: 'admin.request_download' };
        const res = await ApiService.post(route, formData);
        
        if (res.success) {
            if (res.queued) {
                ToastManager.show(res.message || 'Tu descarga se está generando en segundo plano...', 'info');
            } else if (res.download_url) {
                triggerBrowserDownload(res.download_url);
                ToastManager.show(I18nManager.t('admin.logs.download_started') || 'Descarga iniciada.', 'success');
                clearSelection();
            }
        } else {
            ToastManager.show(res.message || (I18nManager.t('admin.logs.download_token_error') || 'Error al solicitar descarga.'), 'error');
        }
    } catch (e) {
        ToastManager.show(I18nManager.t('js.core.connection_error') || 'Error de conexión.', 'error');
    }
}

function triggerBrowserDownload(url) {
    const downloadLink = document.createElement('a');
    downloadLink.href = window.BASE_PATH + 'public/' + url;
    downloadLink.setAttribute('download', '');
    downloadLink.setAttribute('target', '_blank'); 
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    setTimeout(() => { document.body.removeChild(downloadLink); }, 100);
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
        list.innerHTML = `<div class="state-error">${I18nManager.t('js.core.connection_error') || 'Error de conexión'}</div>`;
    }
}

function renderList(query = '') {
    const list = _container.querySelector('[data-component="file-list"]');
    const countLabel = _container.querySelector('[data-element="count-wrapper"]');
    
    const filtered = _filesData.filter(f => f.filename.toLowerCase().includes(query));

    if(countLabel) countLabel.innerText = `${filtered.length} ${I18nManager.t('admin.logs.files_count') || 'archivos'}`;

    list.innerHTML = ''; // Limpieza segura

    if(filtered.length === 0) {
        list.innerHTML = `<div class="state-empty">${I18nManager.t('admin.logs.empty') || 'No se encontraron archivos de log.'}</div>`;
        return;
    }

    if (_viewMode === 'grid') {
        renderGrid(filtered, list);
    } else {
        renderTable(filtered, list);
    }
}

function getFileIcon(category) {
    switch(category) {
        case 'app': return 'terminal'; 
        case 'database': return 'database';
        case 'security': return 'shield';
        default: return 'description';
    }
}

function getFileStyle(category) {
    switch(category) {
        case 'app': return { bg: '#e3f2fd', color: '#1976d2' };
        case 'database': return { bg: '#fff3e0', color: '#ed6c02' };
        case 'security': return { bg: '#ffebee', color: '#d32f2f' };
        default: return { bg: '#f5f5f5', color: '#666' };
    }
}

function renderGrid(files, container) {
    files.forEach(file => {
        const isSel = _selectedPaths.has(file.path);
        const iconName = getFileIcon(file.category);
        const styles = getFileStyle(file.category);

        const card = document.createElement('div');
        card.className = `component-card ${isSel ? 'is-selected' : ''}`;
        card.dataset.path = file.path;

        const content = document.createElement('div');
        content.className = 'component-list-item-content';

        // Icono
        const iconDiv = document.createElement('div');
        iconDiv.className = 'component-card__profile-picture component-avatar--list';
        iconDiv.style.display = 'flex';
        iconDiv.style.alignItems = 'center';
        iconDiv.style.justifyContent = 'center';
        iconDiv.style.backgroundColor = styles.bg;
        iconDiv.style.color = styles.color;
        
        const iconSpan = document.createElement('span');
        iconSpan.className = 'material-symbols-rounded';
        iconSpan.style.fontSize = '24px';
        iconSpan.textContent = iconName;
        iconDiv.appendChild(iconSpan);

        content.appendChild(iconDiv);

        // Badges helper
        const createBadge = (text, tooltipKey, mono = false) => {
            const span = document.createElement('span');
            span.className = 'component-badge';
            span.dataset.tooltip = I18nManager.t(tooltipKey);
            if (mono) span.style.fontFamily = 'monospace';
            span.textContent = text; // [SEGURIDAD]
            return span;
        };

        content.appendChild(createBadge(file.filename, 'admin.logs.col_filename', true));
        content.appendChild(createBadge(file.category, 'admin.logs.col_category'));
        content.appendChild(createBadge(file.size, 'admin.logs.col_size'));
        content.appendChild(createBadge(file.modified_at, 'admin.logs.col_date'));

        card.appendChild(content);
        
        // Listener
        card.addEventListener('click', () => toggleSelection(file.path));
        
        container.appendChild(card);
    });
}

function renderTable(files, container) {
    const tableWrapper = document.createElement('div');
    tableWrapper.className = 'component-table-wrapper';

    const table = document.createElement('table');
    table.className = 'component-table';

    // THEAD
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    const headers = [
        { w: '50px', t: '' },
        { t: I18nManager.t('admin.logs.col_filename') || 'Archivo' },
        { t: I18nManager.t('admin.logs.col_category') || 'Categoría' },
        { t: I18nManager.t('admin.logs.col_size') || 'Tamaño' },
        { t: I18nManager.t('admin.logs.col_date') || 'Fecha' }
    ];
    headers.forEach(h => {
        const th = document.createElement('th');
        if (h.w) th.style.width = h.w;
        th.textContent = h.t;
        headerRow.appendChild(th);
    });
    thead.appendChild(headerRow);
    table.appendChild(thead);

    // TBODY
    const tbody = document.createElement('tbody');
    files.forEach(file => {
        const isSel = _selectedPaths.has(file.path);
        const iconName = getFileIcon(file.category);
        const styles = getFileStyle(file.category);

        const tr = document.createElement('tr');
        tr.className = `table-row-item ${isSel ? 'is-selected' : ''}`;
        tr.style.cursor = 'pointer';
        tr.dataset.path = file.path;

        // Icon Cell
        const tdIcon = document.createElement('td');
        const iconDiv = document.createElement('div');
        iconDiv.className = 'component-card__profile-picture component-avatar--list';
        iconDiv.style.cssText = `width:32px; height:32px; display:flex; align-items:center; justify-content:center; background-color:${styles.bg}; color:${styles.color};`;
        const iconSpan = document.createElement('span');
        iconSpan.className = 'material-symbols-rounded';
        iconSpan.style.fontSize = '18px';
        iconSpan.textContent = iconName;
        iconDiv.appendChild(iconSpan);
        tdIcon.appendChild(iconDiv);
        tr.appendChild(tdIcon);

        // Name
        const tdName = document.createElement('td');
        tdName.style.fontFamily = 'monospace';
        tdName.style.fontWeight = '600';
        tdName.textContent = file.filename; // [SEGURIDAD]
        tr.appendChild(tdName);

        // Category
        const tdCat = document.createElement('td');
        tdCat.textContent = file.category;
        tr.appendChild(tdCat);

        // Size
        const tdSize = document.createElement('td');
        tdSize.textContent = file.size;
        tr.appendChild(tdSize);

        // Date
        const tdDate = document.createElement('td');
        tdDate.textContent = file.modified_at;
        tr.appendChild(tdDate);

        // Listener
        tr.addEventListener('click', () => toggleSelection(file.path));

        tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    tableWrapper.appendChild(table);
    container.appendChild(tableWrapper);
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
        indicator.innerText = `${_selectedPaths.size} ${I18nManager.t('admin.logs.selected_count') || 'seleccionados'}`;
        
        const btnDownload = _container.querySelector('[data-action="download-selected"]');
        if (btnDownload) {
            btnDownload.style.opacity = '1';
            btnDownload.disabled = false;
            btnDownload.dataset.tooltip = _selectedPaths.size > 1 
                ? I18nManager.t('admin.logs.download_zip_count', [_selectedPaths.size]) || `Descargar ZIP (${_selectedPaths.size})` 
                : I18nManager.t('admin.logs.download_log') || 'Descargar Log';
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
    if(!await DialogManager.confirm({ 
        title: I18nManager.t('admin.logs.delete_title') || '¿Eliminar archivos?', 
        message: I18nManager.t('admin.logs.delete_message') || 'Esta acción borrará los logs físicamente del servidor.', 
        type: 'danger' 
    })) return;

    const paths = Array.from(_selectedPaths);
    const formData = new FormData();
    formData.append('paths', paths.join(','));

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.DeleteLogFiles, formData);
        if(res.success) {
            ToastManager.show(I18nManager.t('admin.logs.delete_success') || 'Archivos eliminados', 'success');
            clearSelection();
            loadFiles();
        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch(e) { ToastManager.show(I18nManager.t('admin.logs.delete_error') || 'Error al eliminar', 'error'); }
}