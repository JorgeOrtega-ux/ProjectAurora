/**
 * public/assets/js/modules/admin/audit-log-controller.js
 * Versión Segura (DOM API)
 */

import { ApiService } from '../../core/services/api-service.js';
import { ToastManager } from '../../core/components/toast-manager.js';
import { I18nManager } from '../../core/utils/i18n-manager.js';
let _container = null;
let _currentPage = 1;
let _limit = 50;
let _totalPages = 1;

export const AuditLogController = {
    init: () => {
        console.log("AuditLogController: Inicializado (Safe Mode)");
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

    tbody.innerHTML = ''; // Limpieza segura
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
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 6;
            td.className = 'state-error';
            td.textContent = res.message;
            tr.appendChild(td);
            tbody.appendChild(tr);
        }

    } catch (e) {
        console.error(e);
        if (loading) loading.style.display = 'none';
        
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 6;
        td.className = 'state-error';
        td.textContent = I18nManager.t('js.core.connection_error');
        tr.appendChild(td);
        tbody.appendChild(tr);
    }
}

function renderTable(logs, tbody) {
    if (logs.length === 0) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 6;
        td.className = 'state-empty';
        td.style.textAlign = 'center';
        td.style.padding = '20px';
        td.textContent = I18nManager.t('admin.audit.empty');
        tr.appendChild(td);
        tbody.appendChild(tr);
        return;
    }

    logs.forEach(log => {
        const tr = document.createElement('tr');
        tr.className = 'table-row-item';

        const date = new Date(log.created_at).toLocaleDateString() + ' ' + new Date(log.created_at).toLocaleTimeString();
        const systemName = I18nManager.t('admin.audit.system_actor') || 'System';
        const adminName = log.admin_name || systemName;
        const avatarSrc = log.admin_avatar_src || `https://ui-avatars.com/api/?name=${encodeURIComponent(adminName)}&background=random&color=fff&size=128`;
        const displayAdminName = log.admin_name || I18nManager.t('admin.audit.system_default') || 'Sistema';

        // 1. Columna Avatar
        const tdAvatar = document.createElement('td');
        const divAvatar = document.createElement('div');
        divAvatar.className = 'component-card__profile-picture component-avatar--list';
        divAvatar.dataset.role = log.admin_role || 'system';
        divAvatar.style.cssText = "width: 32px; height: 32px; min-width: 32px;";
        
        const img = document.createElement('img');
        img.src = avatarSrc;
        img.className = 'component-card__avatar-image';
        img.loading = 'lazy';
        
        divAvatar.appendChild(img);
        tdAvatar.appendChild(divAvatar);
        tr.appendChild(tdAvatar);

        // 2. Columna Actor
        const tdActor = document.createElement('td');
        const divActor = document.createElement('div');
        divActor.style.display = 'flex';
        divActor.style.flexDirection = 'column';
        
        const spanName = document.createElement('span');
        spanName.style.fontWeight = '600';
        spanName.style.fontSize = '13px';
        spanName.textContent = displayAdminName; // [SEGURIDAD] textContent
        
        const spanId = document.createElement('span');
        spanId.style.fontSize = '11px';
        spanId.style.color = 'var(--text-tertiary)';
        spanId.textContent = `ID: ${log.admin_id}`;
        
        divActor.appendChild(spanName);
        divActor.appendChild(spanId);
        tdActor.appendChild(divActor);
        tr.appendChild(tdActor);

        // 3. Columna Acción
        const tdAction = document.createElement('td');
        const badgeAction = document.createElement('span');
        badgeAction.className = 'component-badge component-badge--sm';
        badgeAction.textContent = log.action; // [SEGURIDAD]
        tdAction.appendChild(badgeAction);
        tr.appendChild(tdAction);

        // 4. Columna Target
        const tdTarget = document.createElement('td');
        const badgeTarget = document.createElement('span');
        badgeTarget.className = 'component-badge component-badge--sm';
        badgeTarget.textContent = `${log.target_type}:${log.target_id || '?'}`; // [SEGURIDAD]
        tdTarget.appendChild(badgeTarget);
        tr.appendChild(tdTarget);

        // 5. Columna Detalles
        const tdDetails = document.createElement('td');
        tdDetails.style.whiteSpace = 'normal';
        const divDetails = document.createElement('div');
        divDetails.style.cssText = "font-size: 12px; font-family: monospace; max-height: 80px; overflow-y: auto; color: var(--text-secondary);";
        
        // Renderizar cambios de forma segura
        if (log.changes) {
            const changesNodes = formatChangesSafe(log.changes);
            divDetails.appendChild(changesNodes);
        }
        
        tdDetails.appendChild(divDetails);
        tr.appendChild(tdDetails);

        // 6. Columna Fecha
        const tdDate = document.createElement('td');
        tdDate.style.cssText = "font-size: 12px; color: var(--text-secondary); white-space: nowrap;";
        tdDate.textContent = date;
        tr.appendChild(tdDate);

        tbody.appendChild(tr);
    });
}

function formatChangesSafe(changesObj) {
    const container = document.createDocumentFragment();

    if (typeof changesObj !== 'object' || changesObj === null) return container;
    
    if (Array.isArray(changesObj)) {
        const textNode = document.createTextNode(JSON.stringify(changesObj));
        container.appendChild(textNode);
        return container;
    }

    Object.entries(changesObj).forEach(([key, val]) => {
        const row = document.createElement('div');
        row.style.cssText = "display:flex; gap:4px;";

        const keySpan = document.createElement('span');
        keySpan.style.cssText = "color:var(--text-tertiary); font-weight:500;";
        keySpan.textContent = `${key}:`;

        const valSpan = document.createElement('span');
        valSpan.style.cssText = "color:var(--text-primary); word-break: break-all;";
        valSpan.textContent = (val === null) ? (I18nManager.t('admin.audit.value_null') || 'NULL') : String(val);

        row.appendChild(keySpan);
        row.appendChild(valSpan);
        container.appendChild(row);
    });

    return container;
}