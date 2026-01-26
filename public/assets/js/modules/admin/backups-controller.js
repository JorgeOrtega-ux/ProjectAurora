/**
 * public/assets/js/modules/admin/backups-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { Dialog } from '../../core/dialog-manager.js';
import { I18n } from '../../core/i18n-manager.js';

let _container = null;
let _selectedFilename = null;
let _backupsData = [];
let _viewMode = 'grid'; // Estado de la vista: 'grid' o 'table'

export const BackupsController = {
    init: () => {
        console.log("BackupsController: Inicializado");
        
        _container = document.querySelector('[data-section="admin-backups"]');
        if (!_container) return;

        _selectedFilename = null;
        _backupsData = [];
        _viewMode = 'grid';

        initToolbarEvents();
        loadBackups();
    }
};

function initToolbarEvents() {
    const btnCreate = _container.querySelector('#btn-create-backup');
    if (btnCreate) btnCreate.addEventListener('click', createBackup);

    // Botón de Cambiar Vista
    const btnChangeView = _container.querySelector('[data-action="change-view"]');
    if (btnChangeView) {
        btnChangeView.addEventListener('click', () => {
            _viewMode = (_viewMode === 'grid') ? 'table' : 'grid';
            updateViewUI(btnChangeView);
            renderList();
        });
    }

    const btnRestore = _container.querySelector('[data-action="restore-selected"]');
    if (btnRestore) btnRestore.addEventListener('click', () => { if (_selectedFilename) handleRestore(_selectedFilename); });

    const btnDelete = _container.querySelector('[data-action="delete-selected"]');
    if (btnDelete) btnDelete.addEventListener('click', () => { if (_selectedFilename) handleDelete(_selectedFilename); });

    const btnClose = _container.querySelector('[data-action="close-selection"]');
    if (btnClose) btnClose.addEventListener('click', deselectBackup);
}

function updateViewUI(btnElement) {
    if (!_container) return;
    
    const wrapper = _container;
    const headerCard = _container.querySelector('[data-element="page-header"]');
    const iconSpan = btnElement.querySelector('.material-symbols-rounded');
    const toolbarTitle = _container.querySelector('[data-element="toolbar-title"]');

    if (_viewMode === 'table') {
        if (wrapper) wrapper.classList.add('component-wrapper--full');
        if (headerCard) headerCard.classList.add('d-none');
        if (toolbarTitle) toolbarTitle.classList.remove('d-none'); 
        if (iconSpan) iconSpan.textContent = 'table_rows'; 
        btnElement.dataset.tooltip = 'Vista en Cuadrícula';
    } else {
        if (wrapper) wrapper.classList.remove('component-wrapper--full');
        if (headerCard) headerCard.classList.remove('d-none');
        if (toolbarTitle) toolbarTitle.classList.add('d-none'); 
        if (iconSpan) iconSpan.textContent = 'grid_view';
        btnElement.dataset.tooltip = 'Vista en Tabla';
    }
}

// === LÓGICA DE CARGA Y RENDERIZADO ===

async function loadBackups() {
    const listContainer = _container.querySelector('[data-component="backup-list"]');
    if (!listContainer) return;

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Backups.Get);
        if (res.success) {
            _backupsData = res.backups;
            renderList();
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

    if (_viewMode === 'table') {
        renderListAsTable(container);
    } else {
        renderListAsGrid(container);
    }

    // Attach listeners comunes
    container.querySelectorAll('.component-card, .table-row-item').forEach(item => {
        item.addEventListener('click', () => toggleSelection(item.dataset.filename));
    });
}

function renderListAsGrid(container) {
    let html = '';
    
    _backupsData.forEach(file => {
        const isSelected = (_selectedFilename === file.filename);
        const selectedClass = isSelected ? 'is-selected' : '';
        
        let sourceLabel = (file.source === 'system') ? 'Automático' : 'Manual';
        
        html += `
        <div class="component-card ${selectedClass}" data-filename="${file.filename}">
            <div class="component-list-item-content">
                
                <div class="component-card__icon-container component-card__icon-container--bordered">
                    <span class="material-symbols-rounded">database</span>
                </div>

                <span class="component-badge" data-tooltip="Archivo" style="font-family: monospace;">
                    ${file.filename}
                </span>

                <span class="component-badge" data-tooltip="Tamaño"> 
                    ${file.size}
                </span>

                <span class="component-badge" data-tooltip="Fecha">
                    ${file.date}
                </span>

                <span class="component-badge" data-tooltip="Origen">
                    ${sourceLabel}
                </span>

            </div>
        </div>`;
    });

    container.innerHTML = html;
    container.scrollTop = 0;
}

function renderListAsTable(container) {
    let rows = '';
    
    _backupsData.forEach(file => {
        const isSelected = (_selectedFilename === file.filename);
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

    const tableHtml = `
    <div class="component-table-wrapper">
        <table class="component-table">
            <thead>
                <tr>
                    <th style="width: 50px;"></th>
                    <th>Archivo</th>
                    <th>Tamaño</th>
                    <th>Fecha</th>
                    <th>Origen</th>
                </tr>
            </thead>
            <tbody>
                ${rows}
            </tbody>
        </table>
    </div>
    `;

    container.innerHTML = tableHtml;
    container.scrollTop = 0;
}

// === ACCIONES ===

function toggleSelection(filename) {
    _selectedFilename = (_selectedFilename === filename) ? null : filename;
    
    const groupDefault = _container.querySelector('[data-element="toolbar-group-default"]');
    const groupActions = _container.querySelector('[data-element="toolbar-group-actions"]');
    
    // Actualizar indicador de selección
    const selectionIndicator = _container.querySelector('[data-element="selection-indicator"]');
    if (selectionIndicator) selectionIndicator.textContent = _selectedFilename ? '1 seleccionado' : '';

    if (_selectedFilename) {
        groupDefault.classList.add('d-none');
        groupActions.classList.remove('d-none');
    } else {
        groupDefault.classList.remove('d-none');
        groupActions.classList.add('d-none');
    }
    
    // Re-renderizar para actualizar clases visuales sin recargar datos
    renderList();
}

function deselectBackup() {
    _selectedFilename = null;
    toggleSelection(null); 
}

async function createBackup() {
    const btn = document.getElementById('btn-create-backup');
    btn.disabled = true; btn.innerHTML = '<div class="spinner-sm"></div> Creando...';
    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Backups.Create);
        res.success ? (Toast.show('Backup creado', 'success'), loadBackups()) : Toast.show(res.message, 'error');
    } catch(e) { Toast.show('Error', 'error'); } 
    finally { btn.disabled = false; btn.innerHTML = '<span class="material-symbols-rounded">add</span> Crear Copia'; }
}

async function handleRestore(filename) {
    if (!await Dialog.confirm({ title: '¿Restaurar?', message: 'Se sobrescribirán los datos actuales.', type: 'danger' })) return;
    Dialog.showLoading('Restaurando...');
    try {
        const formData = new FormData();
        formData.append('filename', filename);
        const res = await ApiService.post(ApiService.Routes.Admin.Backups.Restore, formData); 
        Dialog.close();
        if(res.success) { Toast.show('Restaurado', 'success'); setTimeout(() => window.location.reload(), 1500); } else Toast.show(res.message, 'error');
    } catch(e) { Dialog.close(); Toast.show('Error', 'error'); }
}

async function handleDelete(filename) {
    if (!await Dialog.confirm({ title: '¿Eliminar?', message: 'Esta acción es irreversible.', type: 'danger' })) return;
    const formData = new FormData(); formData.append('filename', filename);
    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Backups.Delete, formData);
        res.success ? (Toast.show('Eliminado', 'success'), deselectBackup(), loadBackups()) : Toast.show(res.message, 'error');
    } catch(e) { Toast.show('Error', 'error'); }
}