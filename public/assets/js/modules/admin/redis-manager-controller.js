/**
 * public/assets/js/modules/admin/redis-manager-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { Dialog } from '../../core/dialog-manager.js';
import { navigateTo } from '../../core/url-manager.js';

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
        tbody.innerHTML = `<tr><td colspan="4" class="state-error">Error de conexión</td></tr>`;
    }
}

function renderKeysTable(keys, tbody) {
    if (keys.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="state-empty" style="text-align:center;">No se encontraron claves.</td></tr>`;
        return;
    }

    let html = '';
    keys.forEach(k => {
        let typeColor = '#666';
        if (k.type === 'string') typeColor = '#2e7d32'; // verde
        if (k.type === 'hash') typeColor = '#1976d2';   // azul
        if (k.type === 'list') typeColor = '#ed6c02';   // naranja
        if (k.type === 'set') typeColor = '#9c27b0';    // morado

        const badgeType = `<span class="component-badge" style="height:20px; font-size:11px; color:${typeColor}; border-color:${typeColor}40;">${k.type}</span>`;
        
        let ttlDisplay = k.ttl === -1 ? 'Infinito' : `${k.ttl}s`;
        if (k.ttl === -2) ttlDisplay = 'Expirada';

        html += `
        <tr class="table-row-item" style="cursor: pointer;" data-key="${k.key}">
            <td style="font-family:monospace; font-size:13px; word-break:break-all;">${k.key}</td>
            <td>${badgeType}</td>
            <td style="font-size:12px; color:var(--text-secondary);">${ttlDisplay}</td>
            <td style="text-align:right;">
                <button class="component-button btn-delete-key" data-key="${k.key}" style="width:28px; height:28px; padding:0; border:none; color:var(--text-tertiary);" title="Eliminar">
                    <span class="material-symbols-rounded" style="font-size:18px;">delete</span>
                </button>
            </td>
        </tr>`;
    });

    tbody.innerHTML = html;

    // Listeners
    tbody.querySelectorAll('tr').forEach(row => {
        row.addEventListener('click', (e) => {
            // Evitar disparar si se clicó el botón de borrar
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
    Dialog.showLoading('Cargando valor...');
    
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

            // Crear contenido HTML para el diálogo
            // Usamos un textarea de solo lectura para mostrar el valor grande
            // y resaltamos con clases de estilo de código si es JSON
            const htmlContent = `
                <div style="display:flex; flex-direction:column; gap:12px; max-height:400px;">
                    <div style="font-size:12px; color:var(--text-secondary);">
                        <strong>Tipo:</strong> ${data.type} &nbsp;|&nbsp; <strong>Tamaño:</strong> ${data.size} items/bytes
                    </div>
                    <textarea class="component-text-input" readonly style="height:300px; font-family:monospace; font-size:12px; resize:vertical;">${displayValue}</textarea>
                </div>
            `;

            // Usamos Dialog.alert modificado (o confirm sin cancel) para mostrar info
            Dialog.confirm({
                title: `Clave: ${key}`,
                message: '', // Usamos HTML custom abajo
                confirmText: 'Cerrar',
                cancelText: null, // Ocultar cancelar
                onReady: (wrapper) => {
                    const contentArea = wrapper.querySelector('[data-element="message"]');
                    if(contentArea) {
                        contentArea.innerHTML = htmlContent;
                        contentArea.style.whiteSpace = 'normal'; // Reset
                    }
                }
            });

        } else {
            Toast.show(res.message, 'error');
        }
    } catch (e) {
        Dialog.close();
        Toast.show('Error al obtener valor', 'error');
    }
}

async function deleteKey(key) {
    if (!await Dialog.confirm({ title: '¿Eliminar clave?', message: key, type: 'danger' })) return;

    const formData = new FormData();
    formData.append('key', key);

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.Redis.DeleteKey, formData);
        if (res.success) {
            Toast.show('Eliminado', 'success');
            // Recargar búsqueda actual
            const pattern = document.getElementById('redis-search-input').value || '*';
            loadKeys(pattern);
            loadStats(); // Actualizar contador total
        } else {
            Toast.show(res.message, 'error');
        }
    } catch (e) { Toast.show('Error al eliminar', 'error'); }
}

async function handleFlushDB() {
    // Doble confirmación por seguridad
    const confirm1 = await Dialog.confirm({ 
        title: 'PELIGRO: ¿VACIAR REDIS?', 
        message: 'Esto eliminará TODAS las claves de la base de datos actual. Sesiones, caché, tokens temporales... TODO.', 
        type: 'danger', 
        confirmText: 'SÍ, VACIAR TODO' 
    });

    if (!confirm1) return;

    // Segunda confirmación
    const confirm2 = await Dialog.confirm({ 
        title: '¿Estás absolutamente seguro?', 
        message: 'Esta acción no se puede deshacer. Los usuarios serán desconectados.', 
        type: 'danger', 
        confirmText: 'ESTOY SEGURO' 
    });

    if (!confirm2) return;

    Dialog.showLoading('Vaciando base de datos...');

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
        Toast.show('Error crítico', 'error');
    }
}