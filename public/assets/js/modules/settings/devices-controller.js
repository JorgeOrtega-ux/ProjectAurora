/**
 * public/assets/js/modules/settings/devices-controller.js
 * Versión Segura (DOM API)
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';
import { I18n } from '../../core/i18n-manager.js';
import { Dialog } from '../../core/dialog-manager.js';
import { DialogDefinitions } from '../../core/dialog-definitions.js';

// Atajo
const SettingsAPI = ApiService.Routes.Settings;

// --- ESTADO PRIVADO DEL MÓDULO ---
let isLoading = false;

// --- MÉTODOS PRIVADOS (Lógica Interna) ---

async function _loadDevices() {
    const container = document.getElementById('devices-list-container');
    if (!container) return;

    if (isLoading) return;
    isLoading = true;

    try {
        const res = await ApiService.post(SettingsAPI.GetSessions);

        if (res.success) {
            _renderList(res.sessions);
        } else {
            container.innerHTML = '';
            const msg = document.createElement('div');
            msg.style.cssText = 'padding:20px; text-align:center;';
            msg.textContent = I18n.t('js.devices.load_error');
            container.appendChild(msg);
        }

    } catch (e) {
        console.error(e);
        container.innerHTML = '';
        const msg = document.createElement('div');
        msg.style.cssText = 'padding:20px; text-align:center;';
        msg.textContent = I18n.t('js.devices.connection_error');
        container.appendChild(msg);
    } finally {
        isLoading = false;
    }
}

function _renderList(sessions) {
    const container = document.getElementById('devices-list-container');
    if (!container) return;

    container.innerHTML = ''; // Limpieza segura

    if (sessions.length === 0) {
        const msg = document.createElement('div');
        msg.style.cssText = 'padding:24px; text-align:center; color:#666;';
        msg.textContent = I18n.t('js.devices.empty');
        container.appendChild(msg);
        return;
    }

    sessions.forEach((s, index) => {
        let iconName = 'devices';
        const plat = (s.platform || '').toLowerCase();
        if (plat.includes('win') || plat.includes('mac') || plat.includes('linux')) iconName = 'computer';
        if (plat.includes('android') || plat.includes('iphone')) iconName = 'smartphone';

        const itemWrapper = document.createElement('div');
        
        // Group Item
        const groupItem = document.createElement('div');
        groupItem.className = 'component-group-item';

        // Content (Icon + Text)
        const content = document.createElement('div');
        content.className = 'component-card__content';

        // Icon Container
        const iconDiv = document.createElement('div');
        iconDiv.className = 'component-card__icon-container component-card__icon-container--bordered';
        const iconSpan = document.createElement('span');
        iconSpan.className = 'material-symbols-rounded';
        iconSpan.textContent = iconName;
        iconDiv.appendChild(iconSpan);
        content.appendChild(iconDiv);

        // Text Content
        const textDiv = document.createElement('div');
        textDiv.className = 'component-card__text';

        const h2 = document.createElement('h2');
        h2.className = 'component-card__title';
        // [SEGURIDAD] Usamos textContent para los datos inseguros
        h2.textContent = `${s.platform} - ${s.browser}`;

        // Badge si es actual
        if (s.is_current) {
            const badge = document.createElement('span');
            badge.className = 'component-badge component-badge--sm';
            badge.style.marginLeft = '8px';
            badge.textContent = I18n.t('js.devices.current_device');
            h2.appendChild(badge);
        }
        textDiv.appendChild(h2);

        const pDesc = document.createElement('p');
        pDesc.className = 'component-card__description';
        
        // Construimos el texto "IP: ..." de forma segura
        const ipText = document.createTextNode(`IP: ${s.ip} `);
        pDesc.appendChild(ipText);
        
        const br = document.createElement('br');
        pDesc.appendChild(br);

        const dateSpan = document.createElement('span');
        dateSpan.style.cssText = 'font-size:12px; color:#999;';
        dateSpan.textContent = `Iniciado: ${s.created_at}`;
        pDesc.appendChild(dateSpan);

        textDiv.appendChild(pDesc);
        content.appendChild(textDiv);
        groupItem.appendChild(content);

        // Actions
        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'component-card__actions actions-right';

        if (!s.is_current) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'component-button btn-revoke-one';
            btn.dataset.id = s.id;
            btn.textContent = I18n.t('js.devices.btn_revoke');
            actionsDiv.appendChild(btn);
        }
        groupItem.appendChild(actionsDiv);
        itemWrapper.appendChild(groupItem);

        // Divider
        if (index < sessions.length - 1) {
            const hr = document.createElement('hr');
            hr.className = 'component-divider';
            itemWrapper.appendChild(hr);
        }

        container.appendChild(itemWrapper);
    });

    _bindListEvents();
}

function _bindListEvents() {
    document.querySelectorAll('.btn-revoke-one').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const id = e.target.dataset.id;
            if(!id) return;

            const confirmed = await Dialog.confirm(DialogDefinitions.Devices.REVOKE_ONE);
            if (!confirmed) return;

            const originalText = e.target.innerText;
            e.target.innerText = '...';
            e.target.disabled = true;

            const formData = new FormData();
            formData.append('token_id', id);

            try {
                const res = await ApiService.post(SettingsAPI.RevokeSession, formData);
                if(res.success) {
                    Toast.show(I18n.t('js.devices.revoke_success'), 'success');
                    _loadDevices(); 
                } else {
                    Toast.show(res.message, 'error');
                    e.target.innerText = originalText;
                    e.target.disabled = false;
                }
            } catch(err) {
                Toast.show(I18n.t('js.devices.connection_error'), 'error');
                e.target.innerText = originalText;
                e.target.disabled = false;
            }
        });
    });
}

function _initRevokeAllButton() {
    const btnAll = document.getElementById('btn-revoke-all');
    if(btnAll) {
        const newBtnAll = btnAll.cloneNode(true);
        btnAll.parentNode.replaceChild(newBtnAll, btnAll);
        
        newBtnAll.addEventListener('click', async () => {
            const confirmed = await Dialog.confirm(DialogDefinitions.Devices.REVOKE_ALL);
            
            if (!confirmed) return;

            const formData = new FormData();
            
            try {
                const res = await ApiService.post(SettingsAPI.RevokeAllSessions, formData);
                if(res.success) {
                    window.location.href = window.BASE_PATH + 'login';
                } else {
                    Toast.show(res.message, 'error');
                }
            } catch(err) {
                Toast.show(I18n.t('js.devices.connection_error'), 'error');
            }
        });
    }
}

export const DevicesController = {
    init: () => {
        const container = document.getElementById('devices-list-container');
        if (!container) return;

        console.log("DevicesController: Inicializado (Safe Mode)");
        
        _loadDevices();
        _initRevokeAllButton();
    }
};