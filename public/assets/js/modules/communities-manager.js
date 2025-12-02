// public/assets/js/modules/communities-manager.js

import { CommunityApi } from '../services/api-service.js';
import { setButtonLoading } from '../core/utilities.js';
import { t } from '../core/i18n-manager.js';
import { openChat } from './chat-manager.js';

// Importar Sub-módulos y Renderizador
import * as ChannelManager from './communities/channels-manager.js';
import * as InfoManager from './communities/community-info.js';
import * as SidebarRenderer from './communities/sidebar-renderer.js';

// Importar el nuevo módulo de acciones
import * as Actions from './community-actions.js';

// Importar la vista del Explorador
import { initExplorerView } from './explorer-view.js';

let sidebarItems = []; 
let currentFilter = 'all'; 
let currentSearchQuery = ''; 

// Estado de la vista del sidebar: 'main' | 'community'
let currentSidebarView = 'main';
let currentCommunityUuid = null;

// Banderas de estado para control de inicialización (Fix SPA)
let areGlobalListenersInit = false;

// --- FUNCIONES INTERNAS (Wrappers para renderizado) ---

function refreshUI(updatedItems = null) {
    if (updatedItems) {
        sidebarItems = updatedItems;
    }
    refreshSidebarList();
    refreshRail();
}

function refreshSidebarList() {
    if (currentSidebarView === 'main') {
        SidebarRenderer.renderSidebarList(sidebarItems, currentFilter, currentSearchQuery, window.ACTIVE_CHAT_UUID);
    }
}

function refreshRail() {
    SidebarRenderer.renderRailIcons(sidebarItems, currentFilter, window.ACTIVE_CHAT_UUID, handleRailItemClick);
}

// Callback para el click en el Rail (Lógica de Negocio)
async function handleRailItemClick(item) {
    if (item.type === 'community') {
        await renderCommunityView(item.uuid);
        const channels = ChannelManager.getCachedChannels(item.uuid);
        
        if (channels && channels.length > 0) {
            let targetCh = null;
            if (item.default_channel_uuid) targetCh = channels.find(c => c.uuid === item.default_channel_uuid);
            if (!targetCh) targetCh = channels.find(c => c.type === 'text');
            if (!targetCh) targetCh = channels[0];

            if (targetCh) {
                const chatData = { 
                    ...item, 
                    channel_uuid: targetCh.uuid, 
                    channel_name: targetCh.name 
                };
                window.ACTIVE_CHANNEL_UUID = targetCh.uuid;
                
                if (targetCh.type !== 'voice') {
                    openChat(item.uuid, chatData);
                } else {
                    // Buscar alternativa de texto si el default es voz
                    const altText = channels.find(c => c.type === 'text');
                    if(altText) {
                        chatData.channel_uuid = altText.uuid;
                        chatData.channel_name = altText.name;
                        window.ACTIVE_CHANNEL_UUID = altText.uuid;
                        openChat(item.uuid, chatData);
                    } else {
                        openChat(item.uuid, chatData);
                    }
                }
            }
        }
    } else {
        openChat(item.uuid, item);
    }
}

async function renderCommunityView(uuid) {
    currentSidebarView = 'community';
    currentCommunityUuid = uuid;

    const item = sidebarItems.find(i => i.uuid === uuid);
    if (!item) return; 

    // Delegar al renderizador
    SidebarRenderer.setupCommunityHeader(item);

    const container = document.getElementById('my-communities-list');
    if (container) {
        // Si ya estamos viendo esta comunidad, no borramos el contenido para evitar parpadeo
        if (!container.querySelector('.channel-item')) {
             container.innerHTML = '<div class="small-spinner" style="margin: 40px auto;"></div>';
        }
    }

    let channels = ChannelManager.getCachedChannels(uuid);
    
    if (!channels) {
        const data = await ChannelManager.loadChannels(uuid);
        channels = data.channels;
        item.role = data.role; 
    }
    
    ChannelManager.renderChannelList(container, uuid, channels, item.role);
}

// --- LOGICA DE NEGOCIO (UPDATES) DELEGADA A ACTIONS ---

async function loadSidebarList(shouldOpenActive = false) {
    const res = await CommunityApi.getSidebarList();
    
    if (res.success) {
        sidebarItems = res.list;
    } else {
        sidebarItems = [];
    }

    refreshRail();

    if (window.ACTIVE_CHAT_UUID) {
        const itemData = sidebarItems.find(c => c.uuid === window.ACTIVE_CHAT_UUID);
        
        if (itemData) {
            if (itemData.type === 'community') {
                 let channels = ChannelManager.getCachedChannels(window.ACTIVE_CHAT_UUID);
                 if (!channels) {
                     const data = await ChannelManager.loadChannels(window.ACTIVE_CHAT_UUID);
                     channels = data.channels;
                 }
                 let targetCh = null;
                 if (window.ACTIVE_CHANNEL_UUID) targetCh = channels.find(c => c.uuid === window.ACTIVE_CHANNEL_UUID);
                 if (!targetCh && itemData.default_channel_uuid) targetCh = channels.find(c => c.uuid === itemData.default_channel_uuid);
                 if (!targetCh && channels.length > 0) targetCh = channels.find(c => c.name.toLowerCase() === 'general') || channels[0];

                 if (targetCh) {
                     itemData.channel_uuid = targetCh.uuid;
                     itemData.channel_name = targetCh.name;
                     window.ACTIVE_CHANNEL_UUID = targetCh.uuid;
                 }
                 await renderCommunityView(window.ACTIVE_CHAT_UUID);
                 
                 if (shouldOpenActive) {
                     if (targetCh.type !== 'voice') {
                        openChat(window.ACTIVE_CHAT_UUID, itemData);
                     }
                     setTimeout(() => {
                         const chEl = document.querySelector(`.channel-item[data-uuid="${targetCh.uuid}"]`);
                         if(chEl) chEl.classList.add('active');
                     }, 50);
                 }
            } else {
                refreshSidebarList();
                if (shouldOpenActive) {
                    openChat(window.ACTIVE_CHAT_UUID, itemData);
                }
            }
        } else {
            refreshSidebarList();
            if (shouldOpenActive && window.ACTIVE_CHAT_UUID) {
                openChat(window.ACTIVE_CHAT_UUID); 
            }
        }
    } else {
        refreshSidebarList();
    }
}

// --- POPUP MENU (CHAT OPTIONS) ---
function showChatMenu(btn, uuid, type, isPinned, isFav, isBlocked, friendStatus, isArchived) {
    document.querySelector('.dynamic-popover')?.remove();

    const pinText = isPinned ? 'Desfijar chat' : 'Fijar chat';
    const pinIconStyle = isPinned ? 'color:#1976d2;' : '';
    
    const favText = isFav ? 'Quitar favorito' : 'Marcar favorito';
    const favIconStyle = isFav ? 'color:#fbc02d;' : '';

    const archiveText = isArchived ? 'Desarchivar chat' : 'Archivar chat';
    const archiveIcon = isArchived ? 'unarchive' : 'archive';

    let specificOptions = '';
    let deleteOption = '';

    const createItem = (action, icon, text, style = '', danger = false) => {
        const textColor = danger ? 'color: #d32f2f;' : '';
        const iconColor = danger ? 'color: #d32f2f;' : 'color: #333;';
        const finalIconStyle = style ? style : iconColor;

        return `
        <div class="menu-link" data-action="${action}" data-uuid="${uuid}" data-type="${type}">
            <div class="menu-link-icon">
                <span class="material-symbols-rounded" style="${finalIconStyle}">${icon}</span>
            </div>
            <div class="menu-link-text" style="${textColor}">${text}</div>
        </div>`;
    };

    if (type === 'private') {
        if (isBlocked) {
            specificOptions = `
                ${createItem('unblock-user-chat', 'lock_open', 'Desbloquear', '', true)}
                ${createItem('remove-friend-chat', 'person_remove', 'Eliminar amigo', '', true)}
            `;
        } else {
            let friendOption = '';
            if (friendStatus === 'accepted') {
                friendOption = createItem('remove-friend-chat', 'person_remove', 'Eliminar amigo', '', true);
            } else if (!friendStatus || friendStatus === 'none' || friendStatus === 'null') {
                friendOption = createItem('add-friend-chat', 'person_add', 'Agregar a amigos');
            } else if (friendStatus === 'pending') {
                 friendOption = createItem('cancel-request-chat', 'person_remove', 'Cancelar solicitud', '', true);
            }

            specificOptions = `
                ${createItem('block-user-chat', 'block', t('friends.block_user') || 'Bloquear', '', true)}
                ${friendOption}
            `;
        }
        deleteOption = createItem('delete-chat-conversation', 'delete', 'Eliminar chat', '', true);
    } else {
        specificOptions = createItem('leave-community', 'logout', 'Abandonar grupo', '', true);
    }

    const menu = document.createElement('div');
    menu.className = 'popover-module dynamic-popover body-title active';
    
    menu.innerHTML = `
        <div class="menu-content">
            <div class="menu-list">
                ${createItem('toggle-pin-chat', 'push_pin', pinText, pinIconStyle)}
                ${createItem('toggle-fav-chat', 'star', favText, favIconStyle)}
                ${createItem('toggle-archive-chat', archiveIcon, archiveText)}
                ${deleteOption}
                <div class="component-divider" style="margin: 4px 0;"></div>
                ${specificOptions}
            </div>
        </div>
    `;

    const container = btn.parentElement; 
    container.appendChild(menu);
    btn.classList.add('active'); 

    setTimeout(() => {
        const closeHandler = (e) => {
            if (!menu.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                menu.remove();
                btn.classList.remove('active');
                if (!btn.matches(':hover') && !btn.closest('.chat-item:hover')) {
                    btn.remove();
                }
                document.removeEventListener('click', closeHandler);
            }
        };
        document.addEventListener('click', closeHandler);
    }, 0);
}

// --- INITIALIZATION ---

function initSidebarFilters() {
    const container = document.querySelector('.chat-sidebar-badges');
    const searchInput = document.getElementById('sidebar-search-input');

    if (container) {
        container.addEventListener('click', (e) => {
            const badge = e.target.closest('.sidebar-badge');
            if (badge) {
                container.querySelectorAll('.sidebar-badge').forEach(b => b.classList.remove('active'));
                badge.classList.add('active');
                currentFilter = badge.dataset.filter || 'all';
                refreshSidebarList();
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            currentSearchQuery = e.target.value.trim();
            refreshSidebarList();
        });
    }
}

function initRailListeners() {
    const filterTrigger = document.getElementById('rail-filter-trigger');
    const filterMenu = document.getElementById('rail-filter-menu');

    if (filterTrigger && filterMenu) {
        filterTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            filterMenu.classList.toggle('disabled');
        });

        document.addEventListener('click', (e) => {
            if (!filterMenu.classList.contains('disabled')) {
                if (!filterMenu.contains(e.target) && !filterTrigger.contains(e.target)) {
                    filterMenu.classList.add('disabled');
                }
            }
        });

        const filterBtns = filterMenu.querySelectorAll('[data-action="rail-filter-apply"]');
        filterBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const filter = btn.dataset.filter;
                filterMenu.querySelectorAll('.menu-link').forEach(l => l.classList.remove('active'));
                btn.classList.add('active');
                currentFilter = filter; 
                refreshSidebarList(); 
                refreshRail();
                filterMenu.classList.add('disabled');
            });
        });
    }
}

function initGlobalListeners() {
    if (areGlobalListenersInit) return;

    // Escuchar evento del explorador para refrescar sidebar
    document.addEventListener('refresh-sidebar-request', () => {
        loadSidebarList();
    });

    document.body.addEventListener('click', async (e) => {
        
        // Join Voice Channel
        const joinVoiceBtn = e.target.closest('[data-action="join-voice-channel"]');
        if (joinVoiceBtn) {
            e.preventDefault();
            e.stopPropagation();
            const channelUuid = joinVoiceBtn.dataset.uuid;
            
            const res = await CommunityApi.joinVoiceChannel(channelUuid);
            if (res.success) {
                ChannelManager.updateVoiceState(channelUuid, res.members);
                if (currentSidebarView === 'community') {
                    renderCommunityView(currentCommunityUuid);
                }
            } else {
                if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
            }
            return;
        }

        // Leave Voice Channel
        const leaveVoiceBtn = e.target.closest('[data-action="leave-voice-channel"]');
        if (leaveVoiceBtn) {
            e.preventDefault();
            e.stopPropagation();
            const channelUuid = leaveVoiceBtn.dataset.uuid;
            
            await CommunityApi.leaveVoiceChannel(channelUuid);
            return;
        }

        const backBtn = e.target.closest('#btn-sidebar-back');
        if (backBtn) {
             refreshSidebarList();
             SidebarRenderer.restoreMainHeader();
             currentSidebarView = 'main';
             window.history.pushState({ section: 'main' }, '', window.BASE_PATH);
             return;
        }

        // --- DELEGACIÓN DE ACCIONES A community-actions.js ---

        const blockBtn = e.target.closest('[data-action="block-user-chat"]');
        if (blockBtn) { 
            e.preventDefault(); document.querySelector('.dynamic-popover')?.remove(); 
            await Actions.blockUserFromChat(blockBtn.dataset.uuid); 
            return; 
        }
        
        const unblockBtn = e.target.closest('[data-action="unblock-user-chat"]');
        if (unblockBtn) { 
            e.preventDefault(); document.querySelector('.dynamic-popover')?.remove(); 
            await Actions.unblockUserFromChat(unblockBtn.dataset.uuid); 
            return; 
        }
        
        const pinAction = e.target.closest('[data-action="toggle-pin-chat"]');
        if (pinAction) { 
            document.querySelector('.dynamic-popover')?.remove(); 
            await Actions.togglePinChat(pinAction.dataset.uuid, pinAction.dataset.type, () => loadSidebarList()); 
            return; 
        }
        
        const favAction = e.target.closest('[data-action="toggle-fav-chat"]');
        if (favAction) { 
            document.querySelector('.dynamic-popover')?.remove(); 
            await Actions.toggleFavChat(favAction.dataset.uuid, favAction.dataset.type, () => loadSidebarList()); 
            return; 
        }
        
        const archiveAction = e.target.closest('[data-action="toggle-archive-chat"]');
        if (archiveAction) { 
            document.querySelector('.dynamic-popover')?.remove(); 
            await Actions.toggleArchiveChat(archiveAction.dataset.uuid, archiveAction.dataset.type, sidebarItems, () => loadSidebarList()); 
            return; 
        }

        const leaveAction = e.target.closest('[data-action="leave-community"]');
        if (leaveAction) { 
            document.querySelector('.dynamic-popover')?.remove(); 
            await Actions.leaveCommunity(leaveAction.dataset.uuid, window.ACTIVE_CHAT_UUID, () => loadSidebarList()); 
            return; 
        }
        
        const deleteChatAction = e.target.closest('[data-action="delete-chat-conversation"]');
        if (deleteChatAction) {
            e.preventDefault(); const uuid = deleteChatAction.dataset.uuid; document.querySelector('.dynamic-popover')?.remove();
            await Actions.deleteChatConversation(uuid, window.ACTIVE_CHAT_UUID, () => loadSidebarList());
            return;
        }

        const removeFriendAction = e.target.closest('[data-action="remove-friend-chat"]');
        if (removeFriendAction) {
            e.preventDefault(); 
            document.querySelector('.dynamic-popover')?.remove(); 
            await Actions.removeFriendFromChat(removeFriendAction.dataset.uuid, () => loadSidebarList()); 
            return;
        }

        const addFriendAction = e.target.closest('[data-action="add-friend-chat"]');
        if (addFriendAction) {
            e.preventDefault(); 
            document.querySelector('.dynamic-popover')?.remove(); 
            await Actions.addFriendFromChat(addFriendAction.dataset.uuid, () => loadSidebarList()); 
            return;
        }

        const cancelRequestAction = e.target.closest('[data-action="cancel-request-chat"]');
        if (cancelRequestAction) {
            e.preventDefault(); 
            document.querySelector('.dynamic-popover')?.remove(); 
            await Actions.cancelRequestFromChat(cancelRequestAction.dataset.uuid, () => loadSidebarList()); 
            return;
        }
    });

    document.addEventListener('socket-message', async (e) => {
        const { type, payload } = e.detail;
        
        if (type === 'voice_channel_update') {
            const { channel_uuid, current_users } = payload;
            
            ChannelManager.updateVoiceState(channel_uuid, current_users);
            
            if (currentSidebarView === 'community' && currentCommunityUuid) {
                const channelEl = document.querySelector(`.channel-item[data-uuid="${channel_uuid}"]`);
                if (channelEl) {
                     renderCommunityView(currentCommunityUuid);
                }
            }
        }

        if (type === 'new_chat_message' || type === 'private_message') {
            // [MODIFICADO] Delegar a Actions y actualizar sidebarItems con el resultado
            const updatedList = await Actions.handleSidebarUpdate(payload, sidebarItems, refreshUI);
            sidebarItems = updatedList;
        }
    });

    document.addEventListener('local-chat-read', (e) => {
        const uuid = e.detail.uuid;
        const dataItem = sidebarItems.find(i => i.uuid === uuid);
        
        if (currentSidebarView === 'community' && currentCommunityUuid === uuid) {
            const activeChannelUuid = window.ACTIVE_CHANNEL_UUID;
            const channelItem = document.querySelector(`.channel-item[data-uuid="${activeChannelUuid}"]`);
            
            if (channelItem) {
                const badge = channelItem.querySelector('.channel-unread-badge');
                if (badge) {
                    const countInChannel = parseInt(badge.innerText) || 0;
                    if (dataItem && dataItem.unread_count > 0) {
                        dataItem.unread_count = Math.max(0, dataItem.unread_count - countInChannel);
                    }
                    badge.remove();
                    channelItem.classList.remove('has-unread');
                }
            }
        } 
        else if (dataItem && dataItem.type === 'private') {
            dataItem.unread_count = 0;
        }
    
        if (currentSidebarView === 'main') {
            const itemEl = document.querySelector(`.chat-item[data-uuid="${uuid}"]`);
            if (itemEl && dataItem) {
                const badge = itemEl.querySelector('.unread-counter');
                if (dataItem.unread_count === 0) {
                    if (badge) badge.remove();
                    const preview = itemEl.querySelector('.chat-item-preview');
                    if (preview) {
                        preview.style.fontWeight = 'normal';
                        preview.style.color = '';
                    }
                } else {
                    if (badge) {
                        badge.innerText = dataItem.unread_count > 99 ? '99+' : dataItem.unread_count;
                    }
                }
            }
        }
        refreshRail();
    });

    document.addEventListener('reset-chat-view', () => {
        refreshSidebarList();
    });

    document.addEventListener('chat-opened', (e) => {
        const chatData = e.detail;
        refreshRail();

        if (chatData && chatData.type === 'community') {
             if (currentSidebarView !== 'community' || currentCommunityUuid !== chatData.uuid) {
                 renderCommunityView(chatData.uuid);
             }
             
             setTimeout(() => {
                 const channelUuid = window.ACTIVE_CHANNEL_UUID;
                 if (channelUuid) {
                     document.querySelectorAll('.channel-item').forEach(el => el.classList.remove('active'));
                     const chEl = document.querySelector(`.channel-item[data-uuid="${channelUuid}"]`);
                     if(chEl) chEl.classList.add('active');
                 }
             }, 50);
        }
    });

    areGlobalListenersInit = true;
}

function initDOMListeners() {
    const listContainer = document.getElementById('my-communities-list');
    const sidebarPanel = document.getElementById('chat-sidebar-panel');

    if (listContainer) {
        listContainer.addEventListener('mouseover', (e) => {
            const item = e.target.closest('.chat-item');
            if (!item) return;
            const actionsDiv = item.querySelector('.chat-item-actions');
            if (!actionsDiv || actionsDiv.querySelector('.chat-hover-btn')) return;

            const btn = document.createElement('button');
            btn.className = 'chat-hover-btn';
            btn.dataset.action = 'open-chat-menu';
            
            btn.dataset.uuid = item.dataset.uuid;
            btn.dataset.type = item.dataset.type;
            btn.dataset.pinned = item.dataset.pinned;
            btn.dataset.fav = item.dataset.fav;
            btn.dataset.blocked = item.dataset.blocked;
            btn.dataset.friendStatus = item.dataset.friendStatus;
            btn.dataset.archived = item.dataset.archived;
            
            btn.innerHTML = '<span class="material-symbols-rounded">expand_more</span>';
            actionsDiv.appendChild(btn);
        });

        listContainer.addEventListener('mouseout', (e) => {
            const item = e.target.closest('.chat-item');
            if (!item) return;
            if (item.contains(e.relatedTarget)) return;
            const btn = item.querySelector('.chat-hover-btn');
            if (btn && !btn.classList.contains('active')) btn.remove();
        });

        listContainer.addEventListener('click', async (e) => {
            
            // Toggle Categorías
            const toggleCat = e.target.closest('[data-action="toggle-channel-category"]');
            if (toggleCat) {
                e.preventDefault();
                e.stopPropagation();
                const group = toggleCat.closest('.channel-category-group');
                if (group) {
                    group.classList.toggle('expanded');
                    const arrow = toggleCat.querySelector('.category-arrow');
                    if (arrow) {
                        arrow.style.transform = group.classList.contains('expanded') ? 'rotate(0deg)' : 'rotate(-90deg)';
                        arrow.style.transition = 'transform 0.2s';
                    }
                }
                return;
            }

            const chatMenuBtn = e.target.closest('[data-action="open-chat-menu"]');
            if (chatMenuBtn) {
                e.preventDefault(); e.stopPropagation(); 
                const isPinned = chatMenuBtn.dataset.pinned === 'true';
                const isFav = chatMenuBtn.dataset.fav === 'true';
                const isBlocked = chatMenuBtn.dataset.blocked === 'true';
                const friendStatus = chatMenuBtn.dataset.friendStatus;
                const isArchived = chatMenuBtn.dataset.archived === 'true';
                
                showChatMenu(chatMenuBtn, chatMenuBtn.dataset.uuid, chatMenuBtn.dataset.type, isPinned, isFav, isBlocked, friendStatus, isArchived);
                return;
            }

            const channelItem = e.target.closest('[data-action="select-channel"]');
            if (channelItem && !e.target.closest('.channel-action-btn')) {
                const uuid = channelItem.dataset.uuid;
                const commUuid = channelItem.dataset.community;
                const channelStatus = channelItem.dataset.status; 
                
                const commItem = sidebarItems.find(i => i.uuid === commUuid);
                
                const chatData = { 
                    ...commItem, 
                    channel_uuid: uuid, 
                    channel_name: channelItem.querySelector('.channel-name').innerText,
                    channel_status: channelStatus 
                };
                
                const parentList = channelItem.parentElement.parentElement;
                if (parentList) {
                    parentList.querySelectorAll('.channel-item').forEach(el => el.classList.remove('active'));
                    channelItem.classList.add('active');
                }
                
                const badge = channelItem.querySelector('.channel-unread-badge');
                if (badge) {
                    badge.remove();
                    channelItem.classList.remove('has-unread');
                }
                
                window.ACTIVE_CHANNEL_UUID = uuid;
                openChat(commUuid, chatData);
                return;
            }

            const createBtn = e.target.closest('[data-action="create-channel-prompt"]');
            if (createBtn) {
                const commUuid = createBtn.dataset.community;
                const res = await ChannelManager.handleCreateChannel(commUuid);
                if (res.success) {
                    const item = sidebarItems.find(i => i.uuid === commUuid);
                    ChannelManager.renderChannelList(listContainer, commUuid, res.channels, item.role);
                }
                return;
            }

            const deleteChBtn = e.target.closest('[data-action="delete-channel"]');
            if (deleteChBtn) {
                e.stopPropagation();
                const chUuid = deleteChBtn.dataset.uuid;
                const channelItem = deleteChBtn.closest('.channel-item');
                const commUuid = channelItem.dataset.community;
                
                const res = await ChannelManager.handleDeleteChannel(chUuid, commUuid);
                if (res.success) {
                    const item = sidebarItems.find(i => i.uuid === commUuid);
                    ChannelManager.renderChannelList(listContainer, commUuid, res.channels, item.role);
                    
                    if (window.ACTIVE_CHANNEL_UUID === chUuid) {
                        const firstCh = res.channels[0];
                        if(firstCh) {
                            const itemData = sidebarItems.find(i => i.uuid === commUuid);
                            openChat(commUuid, itemData);
                        }
                    }
                }
                return;
            }

            const item = e.target.closest('.chat-item');
            if (item) {
                if (e.target.closest('.popover-module') || 
                (e.target.closest('[data-action]') && e.target.closest('[data-action]') !== item)) {
                    return;
                }

                const uuid = item.dataset.uuid;
                const type = item.dataset.type; 

                if (type === 'community') {
                    await renderCommunityView(uuid);
                    
                    const channels = ChannelManager.getCachedChannels(uuid) || [];
                    let targetChannel = null;
                    const itemData = sidebarItems.find(c => c.uuid === uuid);
                    
                    if (window.ACTIVE_CHANNEL_UUID && channels.find(c => c.uuid === window.ACTIVE_CHANNEL_UUID)) {
                        targetChannel = channels.find(c => c.uuid === window.ACTIVE_CHANNEL_UUID);
                    }

                    if (!targetChannel && itemData && itemData.default_channel_uuid) {
                        targetChannel = channels.find(c => c.uuid === itemData.default_channel_uuid);
                    }
                    
                    if (!targetChannel && channels.length > 0) {
                        targetChannel = channels.find(c => c.name.toLowerCase() === 'general') || channels[0];
                    }
                    
                    if (itemData && targetChannel) {
                        itemData.channel_uuid = targetChannel.uuid;
                        itemData.channel_name = targetChannel.name;
                        window.ACTIVE_CHANNEL_UUID = targetChannel.uuid;
                        
                        if (targetChannel.type !== 'voice') {
                            openChat(uuid, itemData);
                        }
                        
                        setTimeout(() => {
                            const chEl = document.querySelector(`.channel-item[data-uuid="${targetChannel.uuid}"]`);
                            if(chEl) {
                                chEl.classList.add('active');
                                const badge = chEl.querySelector('.channel-unread-badge');
                                if (badge) {
                                    badge.remove();
                                    chEl.classList.remove('has-unread');
                                }
                            }
                        }, 50);
                    }

                } else {
                    const itemData = sidebarItems.find(c => c.uuid === uuid);
                    openChat(uuid, itemData);
                }
            }
        });
    }

    if (sidebarPanel) {
        sidebarPanel.addEventListener('click', (e) => {
             const backBtn = e.target.closest('#btn-sidebar-back');
             if (backBtn) {
                 refreshSidebarList();
             }
        });
    }
}

function initJoinByCode() {
    const btn = document.querySelector('[data-action="submit-join-community"]');
    if (!btn) return;
    
    const input = document.querySelector('[data-input="community-code"]');
    if(input) {
        input.addEventListener('input', (e) => {
            let v = e.target.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
            if (v.length > 12) v = v.slice(0, 12);
            const parts = [];
            if (v.length > 0) parts.push(v.slice(0, 4));
            if (v.length > 4) parts.push(v.slice(4, 8));
            if (v.length > 8) parts.push(v.slice(8, 12));
            e.target.value = parts.join('-');
        });
    }

    btn.onclick = async () => {
        if (input.value.length < 14) return alert('Código incompleto.');
        setButtonLoading(btn, true);
        const res = await CommunityApi.joinByCode(input.value);
        if (res.success) {
            if(window.alertManager) window.alertManager.showAlert(res.message, 'success');
            if(window.navigateTo) window.navigateTo('main'); else window.location.href = window.BASE_PATH + 'main';
        } else {
            if(window.alertManager) window.alertManager.showAlert(res.message, 'error');
            setButtonLoading(btn, false, 'Unirse');
        }
    };
}

export function initCommunitiesManager() {
    loadSidebarList(true); 
    
    // [MODIFICADO] Ahora iniciamos la vista del explorador (si existe el contenedor)
    initExplorerView();
    
    initJoinByCode(); 
    initSidebarFilters(); 
    InfoManager.initInfoPanelListener(); 
    
    initGlobalListeners();
    initRailListeners(); 
    initDOMListeners();
}