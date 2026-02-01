/**
 * public/assets/js/modules/admin/redis-manager-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { Dialog } from '../../core/dialog-manager.js';
import { navigateTo } from '../../core/url-manager.js';
import { I18n } from '../../core/i18n-manager.js'; // Importación añadida

let _container = null;

export const RedisManagerController = {
    init: () => {
        console.log("RedisManagerController: Inicializado");
        _container = document.querySelector('[data-section="admin-redis-manager"]');
        if (!_container) return;

        initEvents();
        loadStats();
        loadKeys('*');
    }
};

function initEvents() {
    const btnBack = _container.querySelector('[data-nav="admin/server"]');
    if (btnBack) btnBack.addEventListener('click', () => navigateTo('admin/server'));

    const btnRefresh = _container.querySelector('[data-action="refresh-all"]');
    if (btnRefresh) btnRefresh.addEventListener('click', () => {
        loadStats();
        const pattern = document.getElementById('redis-search-input').value || '*';
        loadKeys(pattern);
    });

    const btnFlush = _container.querySelector('[data-action="flush-db"]');
    if (btnFlush) btnFlush.addEventListener('click', handleFlushDB);

    const btnSearch = document.getElementById('btn-redis-search');
    const inputSearch = document.getElementById('redis-search-input');

    if (btnSearch && inputSearch) {
        btnSearch.addEventListener('click', () => loadKeys(inputSearch.value));
        inputSearch.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') loadKeys(inputSearch.value);
        });
    }
}

async function loadStats() {
    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Redis.GetStats);
        if (res.success) {
            updateStat('version', res.stats.version);
            updateStat('uptime', res.stats.uptime);
            updateStat('memory_used', res.stats.memory_used);
            updateStat('memory_peak', res.stats.memory_peak); // [NUEVO] Dato extra para el footer
            updateStat('connected_clients', res.stats.connected_clients);
            updateStat('total_keys', res.stats.total_keys);
        }
    } catch (e) { console.error(e); }
}

function updateStat(key, value) {
    const el = _container.querySelector(`[data-stat="${key}"]`);
    if (el) el.textContent = value;
}

async function loadKeys(pattern) {
    const tbody = document.getElementById('redis-keys-body');
    const loader = document.getElementById('redis-loading');
    
    if (!tbody) return;
    
    tbody.innerHTML = '';
    loader.classList.remove('d-none');

    const formData = new FormData();
    formData.append('pattern', pattern);

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Redis.GetKeys, formData);
        loader.classList.add('d-none');

        if (res.success) {
            renderKeysTable(res.keys, tbody);
        } else {
            tbody.innerHTML = `<tr><td colspan="4" class="state-error">${res.message}</td></tr>`;
        }
    } catch (e) {
        loader.classList.add('d-none');
        tbody.innerHTML = `<tr><td colspan="4" class="state-error">${I18n.t('js.core.connection_error')}</td></tr>`;
    }
}

function renderKeysTable(keys, tbody) {
    if (keys.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="state-empty" style="text-align:center; padding: 20px;">${I18n.t('admin.redis.no_keys') || 'No se encontraron claves.'}</td></tr>`;
        return;
    }

    let html = '';
    keys.forEach(k => {
        // Mapeo a clases genéricas component-badge--*
        let colorClass = 'component-badge--gray';
        if (k.type === 'string') colorClass = 'component-badge--green';
        else if (k.type === 'hash') colorClass = 'component-badge--blue';
        else if (k.type === 'list') colorClass = 'component-badge--orange';
        else if (k.type === 'set') colorClass = 'component-badge--purple';
        else if (k.type === 'zset') colorClass = 'component-badge--pink';
        else if (k.type === 'stream') colorClass = 'component-badge--cyan';

        const badgeType = `<span class="component-badge ${colorClass}" style="height:20px; font-size:11px;">${k.type}</span>`;
        
        let ttlDisplay = k.ttl === -1 ? (I18n.t('admin.redis.ttl_infinite') || 'Infinito') : `${k.ttl}s`;
        if (k.ttl === -2) ttlDisplay = (I18n.t('admin.redis.ttl_expired') || 'Expirada');

        const deleteTitle = I18n.t('js.core.delete') || 'Eliminar';

        html += `
        <tr class="table-row-item" style="cursor: pointer;" data-key="${k.key}">
            <td class="font-mono" style="font-size:13px; word-break:break-all;">${k.key}</td>
            <td>${badgeType}</td>
            <td style="font-size:12px; color:var(--text-secondary);">${ttlDisplay}</td>
            <td class="text-right">
                <button class="component-button btn-delete-key" data-key="${k.key}" style="width:28px; height:28px; padding:0; border:none; color:var(--text-tertiary);" title="${deleteTitle}">
                    <span class="material-symbols-rounded" style="font-size:18px;">delete</span>
                </button>
            </td>
        </tr>`;
    });

    tbody.innerHTML = html;

    // Listeners
    tbody.querySelectorAll('tr').forEach(row => {
        row.addEventListener('click', (e) => {
            if (e.target.closest('.btn-delete-key')) return;
            showValueDialog(row.dataset.key);
        });
    });

    tbody.querySelectorAll('.btn-delete-key').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            deleteKey(btn.dataset.key);
        });
    });
}

async function showValueDialog(key) {
    Dialog.showLoading(I18n.t('admin.redis.loading_value') || 'Cargando valor...');
    
    const formData = new FormData();
    formData.append('key', key);

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Redis.GetValue, formData);
        Dialog.close();

        if (res.success) {
            const data = res.data;
            let displayValue = '';

            if (typeof data.value === 'string') {
                displayValue = data.value;
            } else {
                displayValue = JSON.stringify(data.value, null, 2);
            }

            const htmlContent = `
                <div class="component-data-viewer">
                    <div class="component-data-meta">
                        <span><strong>${I18n.t('admin.redis.meta_type') || 'Tipo:'}</strong> ${data.type}</span>
                        <span><strong>${I18n.t('admin.redis.meta_size') || 'Tamaño:'}</strong> ${data.size} items/bytes</span>
                        <span><strong>TTL:</strong> ${data.ttl}</span>
                    </div>
                    <textarea class="component-textarea-read" readonly>${displayValue}</textarea>
                </div>
            `;

            Dialog.confirm({
                title: `${I18n.t('admin.redis.key_label') || 'Clave'}: ${key}`,
                message: '', 
                confirmText: I18n.t('js.core.close') || 'Cerrar',
                cancelText: null, 
                onReady: (wrapper) => {
                    const contentArea = wrapper.querySelector('[data-element="message"]');
                    if(contentArea) {
                        contentArea.innerHTML = htmlContent;
                        contentArea.style.whiteSpace = 'normal'; 
                    }
                }
            });

        } else {
            Toast.show(res.message, 'error');
        }
    } catch (e) {
        Dialog.close();
        Toast.show(I18n.t('admin.redis.value_error') || 'Error al obtener valor', 'error');
    }
}

async function deleteKey(key) {
    if (!await Dialog.confirm({ title: I18n.t('admin.redis.delete_confirm') || '¿Eliminar clave?', message: key, type: 'danger' })) return;

    const formData = new FormData();
    formData.append('key', key);

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Redis.DeleteKey, formData);
        if (res.success) {
            Toast.show(I18n.t('js.core.deleted') || 'Eliminado', 'success');
            const pattern = document.getElementById('redis-search-input').value || '*';
            loadKeys(pattern);
            loadStats(); 
        } else {
            Toast.show(res.message, 'error');
        }
    } catch (e) { Toast.show(I18n.t('admin.redis.delete_error') || 'Error al eliminar', 'error'); }
}

async function handleFlushDB() {
    const confirm1 = await Dialog.confirm({ 
        title: I18n.t('admin.redis.flush_title') || 'PELIGRO: ¿VACIAR REDIS?', 
        message: I18n.t('admin.redis.flush_message') || 'Esto eliminará TODAS las claves de la base de datos actual. Sesiones, caché, tokens temporales... TODO.', 
        type: 'danger', 
        confirmText: I18n.t('admin.redis.flush_confirm_1') || 'SÍ, VACIAR TODO' 
    });

    if (!confirm1) return;

    const confirm2 = await Dialog.confirm({ 
        title: I18n.t('admin.redis.flush_sure') || '¿Estás absolutamente seguro?', 
        message: I18n.t('admin.redis.flush_warning') || 'Esta acción no se puede deshacer. Los usuarios serán desconectados.', 
        type: 'danger', 
        confirmText: I18n.t('admin.redis.flush_confirm_2') || 'ESTOY SEGURO' 
    });

    if (!confirm2) return;

    Dialog.showLoading(I18n.t('admin.redis.flushing') || 'Vaciando base de datos...');

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Redis.FlushDB);
        Dialog.close();
        if (res.success) {
            Toast.show(res.message, 'success');
            loadStats();
            loadKeys('*');
        } else {
            Toast.show(res.message, 'error');
        }
    } catch (e) {
        Dialog.close();
        Toast.show(I18n.t('admin.redis.critical_error') || 'Error crítico', 'error');
    }
}