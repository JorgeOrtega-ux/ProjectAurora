/**
 * public/assets/js/modules/admin/audit-log-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';

let _container = null;
let _currentPage = 1;
let _limit = 50;
let _totalPages = 1;

export const AuditLogController = {
    init: () => {
        console.log("AuditLogController: Inicializado");
        _container = document.querySelector('[data-section="admin-audit-log"]');
        if (!_container) return;

        _currentPage = 1;
        initEvents();
        loadLogs();
    }
};

function initEvents() {
    const btnRefresh = _container.querySelector('[data-action="refresh-log"]');
    if (btnRefresh) btnRefresh.addEventListener('click', () => loadLogs());

    const btnPrev = _container.querySelector('[data-action="prev-page"]');
    const btnNext = _container.querySelector('[data-action="next-page"]');

    if (btnPrev) {
        btnPrev.addEventListener('click', () => {
            if (_currentPage > 1) {
                _currentPage--;
                loadLogs();
            }
        });
    }

    if (btnNext) {
        btnNext.addEventListener('click', () => {
            if (_currentPage < _totalPages) {
                _currentPage++;
                loadLogs();
            }
        });
    }
}

async function loadLogs() {
    const tbody = document.getElementById('audit-log-body');
    const loading = document.getElementById('audit-loading');
    const btnPrev = _container.querySelector('[data-action="prev-page"]');
    const btnNext = _container.querySelector('[data-action="next-page"]');
    const pageInfo = document.getElementById('audit-page-info');

    if (!tbody) return;

    tbody.innerHTML = '';
    if (loading) loading.style.display = 'block';

    const formData = new FormData();
    formData.append('page', _currentPage);
    formData.append('limit', _limit);

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.GetAuditLogs, formData);

        if (loading) loading.style.display = 'none';

        if (res.success) {
            renderTable(res.logs, tbody);
            
            // Actualizar Paginación
            const pagination = res.pagination;
            _totalPages = pagination.total_pages;
            
            if (pageInfo) pageInfo.textContent = `${_currentPage} / ${_totalPages || 1}`;
            
            if (btnPrev) btnPrev.disabled = (_currentPage <= 1);
            if (btnNext) btnNext.disabled = (_currentPage >= _totalPages);

        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="state-error">${res.message}</td></tr>`;
        }

    } catch (e) {
        console.error(e);
        if (loading) loading.style.display = 'none';
        tbody.innerHTML = `<tr><td colspan="5" class="state-error">${I18n.t('js.core.connection_error')}</td></tr>`;
    }
}

function renderTable(logs, tbody) {
    if (logs.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="state-empty" style="text-align:center; padding: 20px;">${I18n.t('admin.audit.empty')}</td></tr>`;
        return;
    }

    let html = '';
    logs.forEach(log => {
        const date = new Date(log.created_at).toLocaleString();
        
        let details = '';
        if (log.changes) {
            details = formatChanges(log.changes);
        }

        // Estilo de badge para la acción
        let actionColor = 'var(--text-primary)';
        if (log.action.includes('DELETE')) actionColor = '#d32f2f';
        else if (log.action.includes('CREATE')) actionColor = '#2e7d32';
        else if (log.action.includes('UPDATE')) actionColor = '#ed6c02';

        const actionBadge = `<span class="component-badge" style="font-size: 11px; border-color: ${actionColor}40; color: ${actionColor}; height: 22px;">${log.action}</span>`;

        html += `
        <tr>
            <td style="font-size: 12px; color: var(--text-secondary);">${date}</td>
            <td>
                <div style="display: flex; flex-direction: column;">
                    <span style="font-weight: 500; font-size: 13px;">${log.admin_name || 'Sistema'}</span>
                    <span style="font-size: 11px; color: var(--text-tertiary);">ID: ${log.admin_id}</span>
                </div>
            </td>
            <td>${actionBadge}</td>
            <td>
                <span style="font-family: monospace; font-size: 12px; background: var(--bg-hover-light); padding: 2px 4px; border-radius: 4px;">
                    ${log.target_type}:${log.target_id || '?'}
                </span>
            </td>
            <td>
                <div style="font-size: 12px; font-family: monospace; max-height: 60px; overflow-y: auto;">
                    ${details}
                </div>
            </td>
        </tr>`;
    });

    tbody.innerHTML = html;
}

function formatChanges(changesObj) {
    if (typeof changesObj !== 'object' || changesObj === null) return '';
    
    // Si es un array simple
    if (Array.isArray(changesObj)) return JSON.stringify(changesObj);

    return Object.entries(changesObj)
        .map(([key, val]) => {
            // Manejo especial para valores largos o nulos
            const safeVal = (val === null) ? 'NULL' : String(val);
            return `<span style="color:var(--text-tertiary);">${key}:</span> ${safeVal}`;
        })
        .join('<br>');
}