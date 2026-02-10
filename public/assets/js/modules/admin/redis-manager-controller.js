/**
 * public/assets/js/modules/admin/redis-manager-controller.js
 * Versión Refactorizada: Arquitectura Signal & Interceptors
 */

import { ApiService } from '../../core/services/api-service.js';
import { ToastManager } from '../../core/components/toast-manager.js';
import { DialogManager } from '../../core/components/dialog-manager.js';
import { navigateTo } from '../../core/utils/url-manager.js';
import { I18nManager } from '../../core/utils/i18n-manager.js';

let _container = null;

export const RedisManagerController = {
    init: () => {
        console.log("RedisManagerController: Inicializado (Safe Mode)");
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
        // Signal added
        const res = await ApiService.post(
            ApiService.Routes.Admin.Redis.GetStats, 
            new FormData(), 
            { signal: window.PAGE_SIGNAL }
        );
        if (res.success) {
            updateStat('version', res.stats.version);
            updateStat('uptime', res.stats.uptime);
            updateStat('memory_used', res.stats.memory_used);
            updateStat('memory_peak', res.stats.memory_peak);
            updateStat('connected_clients', res.stats.connected_clients);
            updateStat('total_keys', res.stats.total_keys);
        }
    } catch (e) { 
        if (!e.isAborted) console.error(e); 
    }
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
        // Signal added
        const res = await ApiService.post(
            ApiService.Routes.Admin.Redis.GetKeys, 
            formData, 
            { signal: window.PAGE_SIGNAL }
        );
        loader.classList.add('d-none');

        if (res.success) {
            renderKeysTable(res.keys, tbody);
        } else {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td colspan="4" class="state-error">${res.message}</td>`; 
            tbody.appendChild(tr);
        }
    } catch (e) {
        if (e.isAborted) return;
        loader.classList.add('d-none');
        const tr = document.createElement('tr');
        tr.innerHTML = `<td colspan="4" class="state-error">${I18nManager.t('js.core.connection_error')}</td>`;
        tbody.appendChild(tr);
    }
}

function renderKeysTable(keys, tbody) {
    if (keys.length === 0) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 4;
        td.className = 'state-empty';
        td.style.textAlign = 'center';
        td.style.padding = '20px';
        td.textContent = I18nManager.t('admin.redis.no_keys') || 'No se encontraron claves.';
        tr.appendChild(td);
        tbody.appendChild(tr);
        return;
    }

    keys.forEach(k => {
        const tr = document.createElement('tr');
        tr.className = 'table-row-item';
        tr.style.cursor = 'pointer';
        tr.dataset.key = k.key; 

        // 1. Key
        const tdKey = document.createElement('td');
        tdKey.className = 'font-mono';
        tdKey.style.fontSize = '13px';
        tdKey.style.wordBreak = 'break-all';
        tdKey.textContent = k.key; 
        tr.appendChild(tdKey);

        // 2. Type
        let colorClass = 'component-badge--gray';
        if (k.type === 'string') colorClass = 'component-badge--green';
        else if (k.type === 'hash') colorClass = 'component-badge--blue';
        else if (k.type === 'list') colorClass = 'component-badge--orange';
        else if (k.type === 'set') colorClass = 'component-badge--purple';
        else if (k.type === 'zset') colorClass = 'component-badge--pink';
        else if (k.type === 'stream') colorClass = 'component-badge--cyan';

        const tdType = document.createElement('td');
        const badge = document.createElement('span');
        badge.className = `component-badge ${colorClass}`;
        badge.style.height = '20px';
        badge.style.fontSize = '11px';
        badge.textContent = k.type;
        tdType.appendChild(badge);
        tr.appendChild(tdType);

        // 3. TTL
        let ttlDisplay = k.ttl === -1 ? (I18nManager.t('admin.redis.ttl_infinite') || 'Infinito') : `${k.ttl}s`;
        if (k.ttl === -2) ttlDisplay = (I18nManager.t('admin.redis.ttl_expired') || 'Expirada');

        const tdTTL = document.createElement('td');
        tdTTL.style.fontSize = '12px';
        tdTTL.style.color = 'var(--text-secondary)';
        tdTTL.textContent = ttlDisplay;
        tr.appendChild(tdTTL);

        // 4. Actions
        const tdActions = document.createElement('td');
        tdActions.className = 'text-right';
        
        const btnDelete = document.createElement('button');
        btnDelete.className = 'component-button btn-delete-key';
        btnDelete.style.cssText = "width:28px; height:28px; padding:0; border:none; color:var(--text-tertiary);";
        btnDelete.title = I18nManager.t('js.core.delete') || 'Eliminar';
        
        const icon = document.createElement('span');
        icon.className = 'material-symbols-rounded';
        icon.style.fontSize = '18px';
        icon.textContent = 'delete';
        
        btnDelete.appendChild(icon);
        
        btnDelete.onclick = (e) => {
            e.stopPropagation();
            deleteKey(k.key);
        };

        tdActions.appendChild(btnDelete);
        tr.appendChild(tdActions);

        tr.onclick = () => showValueDialog(k.key);

        tbody.appendChild(tr);
    });
}

async function showValueDialog(key) {
    DialogManager.showLoading(I18nManager.t('admin.redis.loading_value') || 'Cargando valor...');
    
    const formData = new FormData();
    formData.append('key', key);

    try {
        // Signal added
        const res = await ApiService.post(
            ApiService.Routes.Admin.Redis.GetValue, 
            formData, 
            { signal: window.PAGE_SIGNAL }
        );
        DialogManager.close();

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
                        <span><strong>${I18nManager.t('admin.redis.meta_type') || 'Tipo:'}</strong> ${data.type}</span>
                        <span><strong>${I18nManager.t('admin.redis.meta_size') || 'Tamaño:'}</strong> ${data.size} items/bytes</span>
                        <span><strong>TTL:</strong> ${data.ttl}</span>
                    </div>
                    <textarea class="component-textarea-read" readonly></textarea>
                </div>
            `;

            DialogManager.confirm({
                title: `${I18nManager.t('admin.redis.key_label') || 'Clave'}: ${key}`, 
                message: '', 
                confirmText: I18nManager.t('js.core.close') || 'Cerrar',
                cancelText: null, 
                onReady: (wrapper) => {
                    const contentArea = wrapper.querySelector('[data-element="message"]');
                    if(contentArea) {
                        contentArea.innerHTML = htmlContent;
                        contentArea.style.whiteSpace = 'normal'; 
                        
                        const textarea = contentArea.querySelector('textarea');
                        if (textarea) textarea.value = displayValue;
                    }
                }
            });

        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch (e) {
        if (e.isAborted) return;
        DialogManager.close();
        ToastManager.show(I18nManager.t('admin.redis.value_error') || 'Error al obtener valor', 'error');
    }
}

async function deleteKey(key) {
    if (!await DialogManager.confirm({ title: I18nManager.t('admin.redis.delete_confirm') || '¿Eliminar clave?', message: key, type: 'danger' })) return;

    const formData = new FormData();
    formData.append('key', key);

    try {
        // Signal added
        const res = await ApiService.post(
            ApiService.Routes.Admin.Redis.DeleteKey, 
            formData, 
            { signal: window.PAGE_SIGNAL }
        );
        if (res.success) {
            ToastManager.show(I18nManager.t('js.core.deleted') || 'Eliminado', 'success');
            const pattern = document.getElementById('redis-search-input').value || '*';
            loadKeys(pattern);
            loadStats(); 
        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch (e) { 
        if (e.isAborted) return;
        ToastManager.show(I18nManager.t('admin.redis.delete_error') || 'Error al eliminar', 'error'); 
    }
}

async function handleFlushDB() {
    const confirm1 = await DialogManager.confirm({ 
        title: I18nManager.t('admin.redis.flush_title') || 'PELIGRO: ¿VACIAR REDIS?', 
        message: I18nManager.t('admin.redis.flush_message') || 'Esto eliminará TODAS las claves de la base de datos actual. Sesiones, caché, tokens temporales... TODO.', 
        type: 'danger', 
        confirmText: I18nManager.t('admin.redis.flush_confirm_1') || 'SÍ, VACIAR TODO' 
    });

    if (!confirm1) return;

    const confirm2 = await DialogManager.confirm({ 
        title: I18nManager.t('admin.redis.flush_sure') || '¿Estás absolutamente seguro?', 
        message: I18nManager.t('admin.redis.flush_warning') || 'Esta acción no se puede deshacer. Los usuarios serán desconectados.', 
        type: 'danger', 
        confirmText: I18nManager.t('admin.redis.flush_confirm_2') || 'ESTOY SEGURO' 
    });

    if (!confirm2) return;

    DialogManager.showLoading(I18nManager.t('admin.redis.flushing') || 'Vaciando base de datos...');

    try {
        // Signal added
        const res = await ApiService.post(
            ApiService.Routes.Admin.Redis.FlushDB, 
            new FormData(), 
            { signal: window.PAGE_SIGNAL }
        );
        DialogManager.close();
        if (res.success) {
            ToastManager.show(res.message, 'success');
            loadStats();
            loadKeys('*');
        } else {
            ToastManager.show(res.message, 'error');
        }
    } catch (e) {
        if (e.isAborted) return;
        DialogManager.close();
        ToastManager.show(I18nManager.t('admin.redis.critical_error') || 'Error crítico', 'error');
    }
}