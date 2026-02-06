/**
 * public/assets/js/modules/admin/backups-controller.js
 * Versión Segura (DOM API)
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { Dialog } from '../../core/dialog-manager.js';
import { navigateTo } from '../../core/url-manager.js';
import { I18n } from '../../core/i18n-manager.js'; 

let _container = null;
let _selectedFilenames = new Set();
let _backupsData = [];
let _viewMode = 'grid'; 

export const BackupsController = {
    init: () => {
        console.log("BackupsController: Inicializado (Safe Mode)");
        
        _container = document.querySelector('[data-section="admin-backups"]');
        if (!_container) return;

        _selectedFilenames = new Set();
        _backupsData = [];
        _viewMode = 'grid';

        initToolbarEvents();
        loadBackups(); 

        document.removeEventListener('socket:action', handleRemoteRefresh);
        document.addEventListener('socket:action', handleRemoteRefresh);
        
        document.removeEventListener('socket:download_ready', onDownloadReady);
        document.addEventListener('socket:download_ready', onDownloadReady);
    }
};

function onDownloadReady(e) {
    if (!_container || !document.body.contains(_container)) return;

    const data = e.detail.message;
    if (data && data.url) {
        triggerBrowserDownload(data.url);
        Toast.show(I18n.t('admin.backups.download_started') || 'Descarga iniciada.', 'success');
        deselectAll();
    }
}

function handleRemoteRefresh(e) {
    const payload = e.detail.message;
    if (!_container || !document.body.contains(_container)) return;

    if (payload && payload.action === 'refresh_backups') {
        loadBackups(true);
    }
}

function initToolbarEvents() {
    const btnCreate = _container.querySelector('#btn-create-backup');
    if (btnCreate) btnCreate.addEventListener('click', createBackup);

    const btnChangeView = _container.querySelector('[data-action="change-view"]');
    if (btnChangeView) {
        btnChangeView.addEventListener('click', () => {
            _viewMode = (_viewMode === 'grid') ? 'table' : 'grid';
            updateViewUI(btnChangeView);
            renderList();
        });
    }

    _container.querySelector('[data-action="restore-selected"]')?.addEventListener('click', handleRestoreSelected);
    _container.querySelector('[data-action="delete-selected"]')?.addEventListener('click', handleDeleteSelected);
    _container.querySelector('[data-action="close-selection"]')?.addEventListener('click', deselectAll);
    _container.querySelector('[data-action="view-selected"]')?.addEventListener('click', handleViewSelected);
    
    // Botón descargar
    const btnDownload = _container.querySelector('[data-action="download-selected"]');
    if (btnDownload) {
        btnDownload.addEventListener('click', handleDownloadSelected);
    } else {
        injectDownloadButton();
    }
}

function injectDownloadButton() {
    const actionGroup = _container.querySelector('[data-element="toolbar-group-actions"] .component-toolbar__side--left');
    if (actionGroup && !actionGroup.querySelector('[data-action="download-selected"]')) {
        const btn = document.createElement('button');
        btn.className = 'header-button';
        btn.dataset.action = 'download-selected';
        btn.dataset.tooltip = I18n.t('js.core.download') || 'Descargar';
        
        const icon = document.createElement('span');
        icon.className = 'material-symbols-rounded';
        icon.textContent = 'download';
        btn.appendChild(icon);
        
        btn.addEventListener('click', handleDownloadSelected);
        
        const deleteBtn = actionGroup.querySelector('[data-action="delete-selected"]');
        if (deleteBtn) {
            actionGroup.insertBefore(btn, deleteBtn);
        } else {
            actionGroup.appendChild(btn);
        }
    }
}

async function loadBackups(isSilentRefresh = false) {
    const listContainer = _container.querySelector('[data-component="backup-list"]');
    if (!listContainer) return;

    if (!isSilentRefresh && _backupsData.length === 0) {
        listContainer.innerHTML = '';
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'state-loading';
        loadingDiv.innerHTML = `<div class="spinner-sm"></div><p class="state-text">${I18n.t('admin.backups.list_loading') || 'Cargando copias de seguridad...'}</p>`;
        listContainer.appendChild(loadingDiv);
    }

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Backups.Get);
        if (res.success) {
            _backupsData = res.backups;
            renderList();
            
            if (isSilentRefresh) {
                const btnCreate = _container.querySelector('#btn-create-backup');
                if (btnCreate && btnCreate.disabled) {
                    btnCreate.disabled = false;
                    btnCreate.innerHTML = ''; // Limpiar
                    const icon = document.createElement('span');
                    icon.className = 'material-symbols-rounded';
                    icon.textContent = 'add';
                    btnCreate.appendChild(icon);
                    // Si hubiera texto, se añade aquí
                }
            }
        } else {
            listContainer.innerHTML = '';
            const errorDiv = document.createElement('div');
            errorDiv.className = 'state-error';
            errorDiv.textContent = res.message;
            listContainer.appendChild(errorDiv);
        }
    } catch (error) {
        listContainer.innerHTML = '';
        const errorDiv = document.createElement('div');
        errorDiv.className = 'state-error';
        errorDiv.textContent = I18n.t('js.core.connection_error') || 'Error de conexión.';
        listContainer.appendChild(errorDiv);
    }
}

function renderList() {
    const container = _container.querySelector('[data-component="backup-list"]');
    if (!container) return;

    container.innerHTML = ''; // Limpieza segura

    if (_backupsData.length === 0) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'state-empty';
        const p = document.createElement('p');
        p.textContent = I18n.t('admin.backups.list_empty') || 'No hay copias de seguridad disponibles.';
        emptyDiv.appendChild(p);
        container.appendChild(emptyDiv);
        return;
    }

    const scrollTop = container.scrollTop;

    if (_viewMode === 'table') {
        renderListAsTable(container);
    } else {
        renderListAsGrid(container);
    }
    
    if (scrollTop > 0) container.scrollTop = scrollTop;
}

function renderListAsGrid(container) {
    _backupsData.forEach(file => {
        const isSelected = _selectedFilenames.has(file.filename);
        let sourceLabel = (file.source === 'system') ? (I18n.t('admin.backups.source_auto') || 'Automático') : (I18n.t('admin.backups.source_manual') || 'Manual');
        
        const card = document.createElement('div');
        card.className = `component-card ${isSelected ? 'is-selected' : ''}`;
        card.dataset.filename = file.filename;

        const content = document.createElement('div');
        content.className = 'component-list-item-content';

        // Icono
        const iconContainer = document.createElement('div');
        iconContainer.className = 'component-card__icon-container component-card__icon-container--bordered';
        const icon = document.createElement('span');
        icon.className = 'material-symbols-rounded';
        icon.textContent = 'database';
        iconContainer.appendChild(icon);
        content.appendChild(iconContainer);

        // Helper Badges
        const createBadge = (text, tooltipKey, mono = false) => {
            const span = document.createElement('span');
            span.className = 'component-badge';
            span.dataset.tooltip = I18n.t(tooltipKey);
            if (mono) span.style.fontFamily = 'monospace';
            span.textContent = text; // [SEGURIDAD]
            return span;
        };

        content.appendChild(createBadge(file.filename, 'admin.backups.col_filename', true));
        content.appendChild(createBadge(file.size, 'admin.backups.col_size'));
        content.appendChild(createBadge(file.date, 'admin.backups.col_date'));
        content.appendChild(createBadge(sourceLabel, 'admin.backups.col_source'));

        card.appendChild(content);
        
        // Listener
        card.addEventListener('click', () => toggleSelection(file.filename));
        
        container.appendChild(card);
    });
}

function renderListAsTable(container) {
    const tableWrapper = document.createElement('div');
    tableWrapper.className = 'component-table-wrapper';

    const table = document.createElement('table');
    table.className = 'component-table';

    // THEAD
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    
    const headers = [
        { w: '50px', t: '' },
        { t: I18n.t('admin.backups.col_filename') || 'Archivo' },
        { t: I18n.t('admin.backups.col_size') || 'Tamaño' },
        { t: I18n.t('admin.backups.col_date') || 'Fecha' },
        { t: I18n.t('admin.backups.col_source') || 'Origen' }
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
    
    _backupsData.forEach(file => {
        const isSelected = _selectedFilenames.has(file.filename);
        let sourceLabel = (file.source === 'system') ? (I18n.t('admin.backups.source_auto') || 'Automático') : (I18n.t('admin.backups.source_manual') || 'Manual');

        const tr = document.createElement('tr');
        tr.className = `table-row-item ${isSelected ? 'is-selected' : ''}`;
        tr.dataset.filename = file.filename;
        tr.style.cursor = 'pointer';

        // Icon Cell
        const tdIcon = document.createElement('td');
        tdIcon.style.width = '50px';
        const iconDiv = document.createElement('div');
        iconDiv.className = 'component-card__icon-container component-card__icon-container--bordered';
        iconDiv.style.cssText = "width: 32px; height: 32px; min-width: 32px;";
        const iconSpan = document.createElement('span');
        iconSpan.className = 'material-symbols-rounded';
        iconSpan.style.fontSize = '18px';
        iconSpan.textContent = 'database';
        iconDiv.appendChild(iconSpan);
        tdIcon.appendChild(iconDiv);
        tr.appendChild(tdIcon);

        // Filename
        const tdName = document.createElement('td');
        tdName.style.fontFamily = 'monospace';
        tdName.textContent = file.filename; // [SEGURIDAD]
        tr.appendChild(tdName);

        // Size
        const tdSize = document.createElement('td');
        tdSize.textContent = file.size;
        tr.appendChild(tdSize);

        // Date
        const tdDate = document.createElement('td');
        tdDate.textContent = file.date;
        tr.appendChild(tdDate);

        // Source
        const tdSource = document.createElement('td');
        const badgeSource = document.createElement('span');
        badgeSource.className = 'component-badge';
        badgeSource.style.cssText = "height: 24px; font-size: 12px;";
        badgeSource.textContent = sourceLabel;
        tdSource.appendChild(badgeSource);
        tr.appendChild(tdSource);

        tr.addEventListener('click', () => toggleSelection(file.filename));
        tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    tableWrapper.appendChild(table);
    container.appendChild(tableWrapper);
}

function toggleSelection(filename) {
    if (_selectedFilenames.has(filename)) _selectedFilenames.delete(filename);
    else _selectedFilenames.add(filename);
    updateToolbarState();
    renderList(); 
}

function deselectAll() {
    _selectedFilenames.clear();
    updateToolbarState();
    renderList();
}

function updateToolbarState() {
    const groupDefault = _container.querySelector('[data-element="toolbar-group-default"]');
    const groupActions = _container.querySelector('[data-element="toolbar-group-actions"]');
    const indicator = _container.querySelector('[data-element="selection-indicator"]');
    const count = _selectedFilenames.size;

    if (count > 0) {
        groupDefault.classList.add('d-none');
        groupActions.classList.remove('d-none');
        indicator.textContent = `${count} ${I18n.t('admin.backups.selected_count') || 'seleccionado(s)'}`;
        
        const btnRestore = groupActions.querySelector('[data-action="restore-selected"]');
        if (btnRestore) {
            btnRestore.disabled = (count !== 1);
            btnRestore.style.opacity = (count !== 1) ? '0.5' : '1';
        }
        
        const btnDownload = groupActions.querySelector('[data-action="download-selected"]');
        if (btnDownload) {
            btnDownload.disabled = false;
            btnDownload.style.opacity = '1';
            btnDownload.dataset.tooltip = count > 1 
                ? I18n.t('admin.backups.download_zip', [count]) || `Descargar ${count} archivos (ZIP)` 
                : I18n.t('admin.backups.download_file') || 'Descargar archivo';
        }

    } else {
        groupDefault.classList.remove('d-none');
        groupActions.classList.add('d-none');
    }
}

function updateViewUI(btnElement) {
    const wrapper = _container;
    const headerCard = _container.querySelector('[data-element="page-header"]');
    const iconSpan = btnElement.querySelector('.material-symbols-rounded');
    const toolbarTitle = _container.querySelector('[data-element="toolbar-title"]');

    if (_viewMode === 'table') {
        wrapper.classList.add('component-wrapper--full');
        headerCard.classList.add('d-none');
        toolbarTitle.classList.remove('d-none'); 
        iconSpan.textContent = 'table_rows'; 
        btnElement.dataset.tooltip = I18n.t('admin.backups.view_grid') || 'Vista en Cuadrícula';
    } else {
        wrapper.classList.remove('component-wrapper--full');
        headerCard.classList.remove('d-none');
        toolbarTitle.classList.add('d-none'); 
        iconSpan.textContent = 'grid_view';
        btnElement.dataset.tooltip = I18n.t('admin.backups.view_table') || 'Vista en Tabla';
    }
}

async function createBackup() {
    const btn = document.getElementById('btn-create-backup');
    
    // Guardar icono original
    const iconSpan = btn.querySelector('.material-symbols-rounded');
    const originalIcon = iconSpan ? iconSpan.textContent : 'add';
    
    btn.disabled = true; 
    btn.innerHTML = '';
    const spinner = document.createElement('div');
    spinner.className = 'spinner-sm';
    btn.appendChild(spinner);
    
    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Backups.Create);
        
        if (res.success) {
            if (res.queued) {
                Toast.show(I18n.t('admin.backups.create_queued') || 'Solicitud enviada. Esperando al servidor...', 'info');
            } else {
                Toast.show(I18n.t('admin.backups.create_success') || 'Backup creado exitosamente', 'success');
                loadBackups();
            }
        } else {
            Toast.show(res.message, 'error');
        }
    } catch(e) { 
        Toast.show(I18n.t('admin.backups.create_error') || 'Error al solicitar backup', 'error'); 
    } finally {
        btn.disabled = false; 
        btn.innerHTML = '';
        const newIcon = document.createElement('span');
        newIcon.className = 'material-symbols-rounded';
        newIcon.textContent = originalIcon;
        btn.appendChild(newIcon);
    }
}

async function handleRestoreSelected() {
    if (_selectedFilenames.size !== 1) return;
    const filename = Array.from(_selectedFilenames)[0];
    if (!await Dialog.confirm({ title: I18n.t('admin.backups.restore_title') || '¿Restaurar?', message: I18n.t('admin.backups.restore_message') || 'Se sobrescribirán los datos actuales con este respaldo.', type: 'danger' })) return;
    Dialog.showLoading(I18n.t('admin.backups.restoring') || 'Restaurando...');
    try {
        const formData = new FormData();
        formData.append('filename', filename);
        const res = await ApiService.post(ApiService.Routes.Admin.Backups.Restore, formData); 
        Dialog.close();
        if(res.success) { 
            Toast.show(I18n.t('admin.backups.restore_success') || 'Restaurado correctamente', 'success'); 
            setTimeout(() => window.location.reload(), 1500); 
        } else Toast.show(res.message, 'error');
    } catch(e) { Dialog.close(); Toast.show('Error', 'error'); }
}

async function handleDeleteSelected() {
    if (_selectedFilenames.size === 0) return;
    if (!await Dialog.confirm({ title: I18n.t('admin.backups.delete_title', [_selectedFilenames.size]) || `¿Eliminar ${_selectedFilenames.size} archivos?`, message: I18n.t('admin.backups.delete_message') || 'Esta acción es irreversible.', type: 'danger' })) return;
    const filesArray = Array.from(_selectedFilenames);
    const formData = new FormData();
    formData.append('filenames', filesArray.join(','));
    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Backups.Delete, formData);
        if (res.success) {
            Toast.show(res.message, 'success');
            deselectAll();
            loadBackups();
        } else {
            Toast.show(res.message, 'error');
        }
    } catch(e) { Toast.show(I18n.t('admin.backups.delete_error') || 'Error al eliminar', 'error'); }
}

function handleViewSelected() {
    if (_selectedFilenames.size === 0) return;
    const filesArray = Array.from(_selectedFilenames);
    navigateTo('admin/file-viewer', { 
        files: filesArray.join(','),
        source: 'backup'
    });
}

// === LÓGICA DE DESCARGA ASÍNCRONA ===
async function handleDownloadSelected() {
    if (_selectedFilenames.size === 0) return;

    const filesArray = Array.from(_selectedFilenames);
    const filesString = filesArray.join(',');
    
    Toast.show(I18n.t('admin.backups.download_requesting') || 'Solicitando descarga...', 'info');

    const formData = new FormData();
    formData.append('file', filesString);
    formData.append('type', 'backup');

    try {
        const route = ApiService.Routes.Admin.request_download || { route: 'admin.request_download' };
        const res = await ApiService.post(route, formData);
        
       if (res.success) {
            if (res.queued) {
                Toast.show(res.message || 'Generando archivo ZIP en segundo plano...', 'info');
            } else if (res.download_url) {
                triggerBrowserDownload(res.download_url);
                if (filesArray.length === 1) {
                    Toast.show(I18n.t('admin.backups.download_started') || 'Descarga iniciada.', 'success');
                } else {
                    Toast.show(I18n.t('admin.backups.zip_ready') || 'Archivo ZIP generado.', 'success');
                }
                deselectAll();
            }
        } else {
            Toast.show(res.message || (I18n.t('admin.backups.download_token_error') || 'No se pudo obtener el enlace seguro.'), 'error');
        }
    } catch (e) {
        console.error(e);
        Toast.show(I18n.t('admin.backups.download_conn_error') || 'Error de conexión al solicitar descarga.', 'error');
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
    
    setTimeout(() => {
        document.body.removeChild(downloadLink);
    }, 100);
}