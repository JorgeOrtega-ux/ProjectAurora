/**
 * public/assets/js/modules/admin/backups-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { Dialog } from '../../core/dialog-manager.js';
import { navigateTo } from '../../core/url-manager.js';

let _container = null;
let _selectedFilenames = new Set();
let _backupsData = [];
let _viewMode = 'grid'; 

export const BackupsController = {
    init: () => {
        console.log("BackupsController: Inicializado (Realtime)");
        
        _container = document.querySelector('[data-section="admin-backups"]');
        if (!_container) return;

        _selectedFilenames = new Set();
        _backupsData = [];
        _viewMode = 'grid';

        initToolbarEvents();
        loadBackups(); 

        // === LISTENER DE SOCKET ===
        document.removeEventListener('socket:action', handleRemoteRefresh);
        document.addEventListener('socket:action', handleRemoteRefresh);
        
        document.addEventListener('socket:notification', (e) => {
            console.log("🔔 Socket Notification:", e.detail);
        });
    }
};

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
        btn.dataset.tooltip = 'Descargar';
        btn.innerHTML = '<span class="material-symbols-rounded">download</span>';
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
        listContainer.innerHTML = '<div class="state-loading"><div class="spinner-sm"></div><p class="state-text">Cargando copias de seguridad...</p></div>';
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
                    btnCreate.innerHTML = '<span class="material-symbols-rounded">add</span> Crear Copia';
                }
            }
        } else {
            listContainer.innerHTML = `<div class="state-error">${res.message}</div>`;
        }
    } catch (error) {
        listContainer.innerHTML = `<div class="state-error">Error de conexión.</div>`;
    }
}

function renderList() {
    const container = _container.querySelector('[data-component="backup-list"]');
    if (!container) return;

    if (_backupsData.length === 0) {
        container.innerHTML = `<div class="state-empty"><p>No hay copias de seguridad disponibles.</p></div>`;
        return;
    }

    const scrollTop = container.scrollTop;

    if (_viewMode === 'table') {
        renderListAsTable(container);
    } else {
        renderListAsGrid(container);
    }

    container.querySelectorAll('.component-card, .table-row-item').forEach(item => {
        item.addEventListener('click', () => toggleSelection(item.dataset.filename));
    });
    
    if (scrollTop > 0) container.scrollTop = scrollTop;
}

function renderListAsGrid(container) {
    let html = '';
    _backupsData.forEach(file => {
        const isSelected = _selectedFilenames.has(file.filename);
        const selectedClass = isSelected ? 'is-selected' : '';
        let sourceLabel = (file.source === 'system') ? 'Automático' : 'Manual';
        
        html += `
        <div class="component-card ${selectedClass}" data-filename="${file.filename}">
            <div class="component-list-item-content">
                <div class="component-card__icon-container component-card__icon-container--bordered">
                    <span class="material-symbols-rounded">database</span>
                </div>
                <span class="component-badge" data-tooltip="Archivo" style="font-family: monospace;">${file.filename}</span>
                <span class="component-badge" data-tooltip="Tamaño">${file.size}</span>
                <span class="component-badge" data-tooltip="Fecha">${file.date}</span>
                <span class="component-badge" data-tooltip="Origen">${sourceLabel}</span>
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

function renderListAsTable(container) {
    let rows = '';
    _backupsData.forEach(file => {
        const isSelected = _selectedFilenames.has(file.filename);
        const selectedClass = isSelected ? 'is-selected' : '';
        let sourceLabel = (file.source === 'system') ? 'Automático' : 'Manual';

        rows += `
        <tr class="table-row-item ${selectedClass}" data-filename="${file.filename}" style="cursor: pointer;">
            <td style="width: 50px;">
                <div class="component-card__icon-container component-card__icon-container--bordered" style="width: 32px; height: 32px; min-width: 32px;">
                    <span class="material-symbols-rounded" style="font-size: 18px;">database</span>
                </div>
            </td>
            <td style="font-family: monospace;">${file.filename}</td>
            <td>${file.size}</td>
            <td>${file.date}</td>
            <td><span class="component-badge" style="height: 24px; font-size: 12px;">${sourceLabel}</span></td>
        </tr>`;
    });

    container.innerHTML = `
    <div class="component-table-wrapper">
        <table class="component-table">
            <thead>
                <tr><th style="width: 50px;"></th><th>Archivo</th><th>Tamaño</th><th>Fecha</th><th>Origen</th></tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
    </div>`;
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
        indicator.textContent = `${count} seleccionado(s)`;
        
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
                ? `Descargar ${count} archivos (ZIP)` 
                : 'Descargar archivo';
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
        btnElement.dataset.tooltip = 'Vista en Cuadrícula';
    } else {
        wrapper.classList.remove('component-wrapper--full');
        headerCard.classList.remove('d-none');
        toolbarTitle.classList.add('d-none'); 
        iconSpan.textContent = 'grid_view';
        btnElement.dataset.tooltip = 'Vista en Tabla';
    }
}

async function createBackup() {
    const btn = document.getElementById('btn-create-backup');
    const originalText = btn.innerHTML;
    
    btn.disabled = true; 
    btn.innerHTML = '<div class="spinner-sm"></div> Procesando...';
    
    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Backups.Create);
        
        if (res.success) {
            if (res.queued) {
                Toast.show('Solicitud enviada. Esperando al servidor...', 'info');
            } else {
                Toast.show('Backup creado exitosamente', 'success');
                loadBackups();
                btn.disabled = false; 
                btn.innerHTML = originalText; 
            }
        } else {
            Toast.show(res.message, 'error');
            btn.disabled = false; 
            btn.innerHTML = originalText; 
        }
    } catch(e) { 
        Toast.show('Error al solicitar backup', 'error'); 
        btn.disabled = false; 
        btn.innerHTML = originalText; 
    } 
}

async function handleRestoreSelected() {
    if (_selectedFilenames.size !== 1) return;
    const filename = Array.from(_selectedFilenames)[0];
    if (!await Dialog.confirm({ title: '¿Restaurar?', message: 'Se sobrescribirán los datos actuales con este respaldo.', type: 'danger' })) return;
    Dialog.showLoading('Restaurando...');
    try {
        const formData = new FormData();
        formData.append('filename', filename);
        const res = await ApiService.post(ApiService.Routes.Admin.Backups.Restore, formData); 
        Dialog.close();
        if(res.success) { 
            Toast.show('Restaurado correctamente', 'success'); 
            setTimeout(() => window.location.reload(), 1500); 
        } else Toast.show(res.message, 'error');
    } catch(e) { Dialog.close(); Toast.show('Error', 'error'); }
}

async function handleDeleteSelected() {
    if (_selectedFilenames.size === 0) return;
    if (!await Dialog.confirm({ title: `¿Eliminar ${_selectedFilenames.size} archivos?`, message: 'Esta acción es irreversible.', type: 'danger' })) return;
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
    } catch(e) { Toast.show('Error al eliminar', 'error'); }
}

function handleViewSelected() {
    if (_selectedFilenames.size === 0) return;
    const filesArray = Array.from(_selectedFilenames);
    navigateTo('admin/file-viewer', { 
        files: filesArray.join(','),
        source: 'backup'
    });
}

// === LÓGICA DE DESCARGA SIN RECARGAR PAGINA ===
async function handleDownloadSelected() {
    if (_selectedFilenames.size === 0) return;

    const filesArray = Array.from(_selectedFilenames);
    const filesString = filesArray.join(',');
    
    if (filesArray.length > 1) {
        Toast.show('Generando ZIP, por favor espera...', 'info');
    } else {
        Toast.show('Solicitando descarga...', 'info');
    }

    const formData = new FormData();
    formData.append('file', filesString);
    formData.append('type', 'backup');

    try {
        const route = ApiService.Routes.Admin.request_download || { route: 'admin.request_download' };
        const res = await ApiService.post(route, formData);
        
       if (res.success && res.download_url) {
            const downloadLink = document.createElement('a');
            downloadLink.href = window.BASE_PATH + 'public/' + res.download_url;
            
            // [SOLUCIÓN] Atributos de seguridad contra recarga
            downloadLink.setAttribute('download', '');
            downloadLink.setAttribute('target', '_blank');
            
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            
            setTimeout(() => {
                document.body.removeChild(downloadLink);
            }, 100);
            
            if (filesArray.length === 1) {
                Toast.show('Descarga iniciada.', 'success');
            } else {
                Toast.show('Archivo ZIP generado.', 'success');
            }
            deselectAll();
        } else {
            Toast.show(res.message || 'No se pudo obtener el enlace seguro.', 'error');
        }
    } catch (e) {
        console.error(e);
        Toast.show('Error de conexión al solicitar descarga.', 'error');
    }
}