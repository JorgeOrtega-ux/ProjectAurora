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
    const pageInfo = _container.querySelector('[data-element="pagination-info"]');

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
            
            const pagination = res.pagination;
            _totalPages = pagination.total_pages;
            
            if (pageInfo) pageInfo.textContent = `${_currentPage} / ${_totalPages || 1}`;
            
            if (btnPrev) btnPrev.disabled = (_currentPage <= 1);
            if (btnNext) btnNext.disabled = (_currentPage >= _totalPages);

        } else {
            tbody.innerHTML = `<tr><td colspan="6" class="state-error">${res.message}</td></tr>`;
        }

    } catch (e) {
        console.error(e);
        if (loading) loading.style.display = 'none';
        tbody.innerHTML = `<tr><td colspan="6" class="state-error">${I18n.t('js.core.connection_error')}</td></tr>`;
    }
}

function renderTable(logs, tbody) {
    if (logs.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="state-empty" style="text-align:center; padding: 20px;">${I18n.t('admin.audit.empty')}</td></tr>`;
        return;
    }

    let html = '';
    logs.forEach(log => {
        const date = new Date(log.created_at).toLocaleDateString() + ' ' + new Date(log.created_at).toLocaleTimeString();
        
        let details = '';
        if (log.changes) {
            details = formatChanges(log.changes);
        }

        // Se usa la nueva variante --sm para la acción
        const actionBadge = `<span class="component-badge component-badge--sm">${log.action}</span>`;

        // Avatar fallback si no viene definido
        const avatarSrc = log.admin_avatar_src || `https://ui-avatars.com/api/?name=${encodeURIComponent(log.admin_name || 'System')}&background=random&color=fff&size=128`;

        html += `
        <tr class="table-row-item">
            <td>
                <div class="component-card__profile-picture component-avatar--list" 
                     data-role="${log.admin_role || 'system'}" 
                     style="width: 32px; height: 32px; min-width: 32px;">
                    <img src="${avatarSrc}" class="component-card__avatar-image" loading="lazy">
                </div>
            </td>
            <td>
                <div style="display: flex; flex-direction: column;">
                    <span style="font-weight: 600; font-size: 13px;">${log.admin_name || 'Sistema'}</span>
                    <span style="font-size: 11px; color: var(--text-tertiary);">ID: ${log.admin_id}</span>
                </div>
            </td>
            <td>${actionBadge}</td>
            <td>
                <span class="component-badge component-badge--sm">
                    ${log.target_type}:${log.target_id || '?'}
                </span>
            </td>
            <td style="white-space: normal;">
                <div style="font-size: 12px; font-family: monospace; max-height: 80px; overflow-y: auto; color: var(--text-secondary);">
                    ${details}
                </div>
            </td>
            <td style="font-size: 12px; color: var(--text-secondary); white-space: nowrap;">${date}</td>
        </tr>`;
    });

    tbody.innerHTML = html;
}

function formatChanges(changesObj) {
    if (typeof changesObj !== 'object' || changesObj === null) return '';
    if (Array.isArray(changesObj)) return JSON.stringify(changesObj);

    return Object.entries(changesObj)
        .map(([key, val]) => {
            const safeVal = (val === null) ? 'NULL' : String(val);
            // Uso de clases utilitarias para texto truncado si es muy largo
            return `<div style="display:flex; gap:4px;"><span style="color:var(--text-tertiary); font-weight:500;">${key}:</span> <span style="color:var(--text-primary); word-break: break-all;">${safeVal}</span></div>`;
        })
        .join('');
}