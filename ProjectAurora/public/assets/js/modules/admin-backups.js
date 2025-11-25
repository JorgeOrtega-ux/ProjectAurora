// public/assets/js/modules/admin-backups.js

import { t } from '../core/i18n-manager.js';

const API_ADMIN = (window.BASE_PATH || '/ProjectAurora/') + 'api/admin_handler.php';
let selectedBackup = null;

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

async function fetchApi(payload) {
    try {
        const res = await fetch(API_ADMIN, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify(payload)
        });
        return await res.json();
    } catch (e) {
        console.error("API Error:", e);
        return { success: false, message: t('global.error_connection') };
    }
}

function setLoading(btn, isLoading) {
    if (!btn) return;
    if (isLoading) {
        btn.dataset.originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<div class="small-spinner" style="border-color:currentColor; border-top-color:transparent;"></div>';
    } else {
        btn.innerHTML = btn.dataset.originalHTML || btn.innerHTML;
        btn.disabled = false;
    }
}

export function initAdminBackups() {
    loadBackups();
    initListeners();
}

function initListeners() {
    document.body.addEventListener('click', (e) => {
        // Seleccionar fila
        const row = e.target.closest('.component-table-row[data-action="select-backup-row"]');
        if (row) {
            handleRowSelection(row);
            return;
        }

        // Crear Backup
        const createBtn = e.target.closest('[data-action="create-backup"]');
        if (createBtn) {
            createBackup(createBtn);
            return;
        }

        // Restaurar Backup
        const restoreBtn = e.target.closest('#btn-restore-backup');
        if (restoreBtn) {
            restoreBackup(restoreBtn);
            return;
        }

        // Eliminar Backup
        const deleteBtn = e.target.closest('[data-action="delete-backup"]');
        if (deleteBtn) {
            deleteBackup(deleteBtn);
            return;
        }

        // Deseleccionar
        const deselectBtn = e.target.closest('[data-action="deselect-backup"]');
        if (deselectBtn) {
            deselectAll();
            return;
        }

        // Clic fuera para deseleccionar
        if (selectedBackup && !e.target.closest('.component-table-container') && !e.target.closest('.component-toolbar')) {
            deselectAll();
        }
    });
}

async function loadBackups() {
    const tbody = document.getElementById('backups-table-body');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="3" class="component-table-empty"><div class="small-spinner"></div></td></tr>`;

    const res = await fetchApi({ action: 'list_backups' });

    if (res.success) {
        renderTable(res.backups);
    } else {
        tbody.innerHTML = `<tr><td colspan="3" class="component-table-empty" style="color: #d32f2f;">${res.message}</td></tr>`;
    }
}

function renderTable(backups) {
    const tbody = document.getElementById('backups-table-body');
    if (!backups || backups.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="3" class="component-table-empty">
                    <span class="material-symbols-rounded component-table-empty-icon">backup</span>
                    <p>${t('admin.backups.empty')}</p>
                </td>
            </tr>`;
        return;
    }

    let html = '';
    backups.forEach(b => {
        html += `
            <tr class="component-table-row" 
                data-selectable="true" 
                data-action="select-backup-row" 
                data-filename="${b.filename}">
                <td>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="material-symbols-rounded" style="color:#666;">description</span>
                        <span style="font-weight:600;">${b.filename}</span>
                    </div>
                </td>
                <td>${b.created_at}</td>
                <td>${b.size}</td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function handleRowSelection(row) {
    const filename = row.dataset.filename;
    
    if (row.classList.contains('selected')) {
        deselectAll();
        return;
    }

    document.querySelectorAll('.component-table-row.selected').forEach(r => r.classList.remove('selected'));
    row.classList.add('selected');
    selectedBackup = filename;

    updateToolbars(true, filename);
}

function deselectAll() {
    document.querySelectorAll('.component-table-row.selected').forEach(r => r.classList.remove('selected'));
    selectedBackup = null;
    updateToolbars(false);
}

function updateToolbars(isSelected, filename = '') {
    const defToolbar = document.getElementById('backup-toolbar-default');
    const selToolbar = document.getElementById('backup-toolbar-selected');
    const nameDisplay = document.getElementById('selected-backup-name');

    if (isSelected) {
        defToolbar.classList.add('d-none');
        selToolbar.classList.remove('d-none');
        if (nameDisplay) nameDisplay.textContent = filename;
    } else {
        selToolbar.classList.add('d-none');
        defToolbar.classList.remove('d-none');
    }
}

async function createBackup(btn) {
    setLoading(btn, true);
    
    const res = await fetchApi({ action: 'create_backup' });
    
    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        loadBackups();
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
    setLoading(btn, false);
}

async function restoreBackup(btn) {
    if (!selectedBackup) return;
    if (!confirm(t('admin.backups.confirm_restore'))) return;

    setLoading(btn, true);
    
    const res = await fetchApi({ action: 'restore_backup', filename: selectedBackup });
    
    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
        setTimeout(() => {
            window.location.reload(); // Importante recargar tras restaurar DB
        }, 2000);
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
        setLoading(btn, false);
    }
}

async function deleteBackup(btn) {
    if (!selectedBackup) return;
    if (!confirm(t('admin.backups.confirm_delete'))) return;

    setLoading(btn, true);

    const res = await fetchApi({ action: 'delete_backup', filename: selectedBackup });

    if (res.success) {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'info');
        deselectAll();
        loadBackups();
    } else {
        if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
    }
    setLoading(btn, false);
}