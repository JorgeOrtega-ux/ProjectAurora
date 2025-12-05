// public/assets/js/services/api-service.js

import { postJson, getCsrfToken, BASE_PATH } from '../core/utilities.js';

/**
 * Helper interno para enviar FormData (Archivos)
 * postJson no sirve aquí porque fuerza Content-Type: application/json
 */
async function postFormData(endpoint, formData) {
    const url = endpoint.startsWith('http') ? endpoint : `${BASE_PATH}${endpoint}`;
    
    // Inyectar CSRF si no está
    if (!formData.has('csrf_token')) {
        formData.append('csrf_token', getCsrfToken());
    }

    try {
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });
        return await response.json();
    } catch (error) {
        console.error(`API Error (FormData) [${endpoint}]:`, error);
        return { success: false, message: 'Error de conexión al subir archivo.' };
    }
}

// ==========================================
// AUTHENTICATION (api/auth_handler.php)
// ==========================================
export const AuthApi = {
    login: (email, password) => 
        postJson('api/auth_handler.php', { action: 'login', email, password }),

    registerStep1: (email, password) => 
        postJson('api/auth_handler.php', { action: 'register_step_1', email, password }),

    registerStep2: (username) => 
        postJson('api/auth_handler.php', { action: 'register_step_2', username }),

    resendCode: (type) => 
        postJson('api/auth_handler.php', { action: 'resend_code', type }),

    verifyRegisterCode: (code) => 
        postJson('api/auth_handler.php', { action: 'register_final', code }),

    verifyLogin2FA: (code) => 
        postJson('api/auth_handler.php', { action: 'login_2fa_verify', code }),

    logout: () => 
        postJson('api/auth_handler.php', { action: 'logout' }),

    recoveryStep1: (email) => 
        postJson('api/auth_handler.php', { action: 'recovery_step_1', email }),

    recoveryFinal: (token, password, passwordConfirm) => 
        postJson('api/auth_handler.php', { 
            action: 'recovery_final', 
            token, 
            password, 
            password_confirm: passwordConfirm 
        })
};

// ==========================================
// CHAT & MESSAGING (api/chat_handler.php)
// ==========================================
export const ChatApi = {
    sendMessage: (params) => {
        const { targetUuid, context, message, replyToUuid, channelUuid, attachments } = params;

        if (attachments && attachments.length > 0) {
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('target_uuid', targetUuid);
            formData.append('context', context);
            formData.append('message', message || '');
            
            if (channelUuid) formData.append('channel_uuid', channelUuid);
            if (replyToUuid) formData.append('reply_to_uuid', replyToUuid);
            
            attachments.forEach(file => formData.append('attachments[]', file));
            
            return postFormData('api/chat_handler.php', formData);
        } else {
            return postJson('api/chat_handler.php', {
                action: 'send_message',
                target_uuid: targetUuid,
                context,
                channel_uuid: channelUuid,
                message,
                reply_to_uuid: replyToUuid
            });
        }
    },

    editMessage: (messageId, newContent, context, targetUuid, channelUuid = null) => 
        postJson('api/chat_handler.php', { 
            action: 'edit_message', 
            message_id: messageId, 
            new_content: newContent,
            context,
            target_uuid: targetUuid,
            channel_uuid: channelUuid
        }),

    getMessages: (targetUuid, context, offset = 0, channelUuid = null, limit = 50) => 
        postJson('api/chat_handler.php', { 
            action: 'get_messages', 
            target_uuid: targetUuid, 
            context, 
            offset, 
            channel_uuid: channelUuid,
            limit
        }),
    
    markAsRead: (targetUuid, context, channelUuid = null) => 
        postJson('api/chat_handler.php', { 
            action: 'mark_as_read', 
            target_uuid: targetUuid, 
            context,
            channel_uuid: channelUuid
        }),
        
    deleteMessage: (messageId, context, targetUuid) => 
        postJson('api/chat_handler.php', { 
            action: 'delete_message', 
            message_id: messageId, 
            context, 
            target_uuid: targetUuid 
        }),

    deleteConversation: (targetUuid) => 
        postJson('api/chat_handler.php', { 
            action: 'delete_conversation', 
            target_uuid: targetUuid 
        }),

    reportMessage: (messageId, reason, context, targetUuid) => 
        postJson('api/chat_handler.php', { 
            action: 'report_message', 
            message_id: messageId, 
            reason,
            context,
            target_uuid: targetUuid
        }),

    reactMessage: (messageId, reaction, context, targetUuid) => 
        postJson('api/chat_handler.php', { 
            action: 'react_message', 
            message_id: messageId, 
            reaction,
            context,
            target_uuid: targetUuid
        })
};

// ==========================================
// COMMUNITIES & CHANNELS (api/communities_handler.php)
// ==========================================
export const CommunityApi = {
    joinByCode: (code) => 
        postJson('api/communities_handler.php', { action: 'join_by_code', access_code: code }),

    getSidebarList: () => 
        postJson('api/communities_handler.php', { action: 'get_sidebar_list' }),

    togglePin: (uuid, type) => 
        postJson('api/communities_handler.php', { action: 'toggle_pin', uuid, type }),

    toggleFavorite: (uuid, type) => 
        postJson('api/communities_handler.php', { action: 'toggle_favorite', uuid, type }),

    toggleArchive: (uuid, type) => 
        postJson('api/communities_handler.php', { action: 'toggle_archive', uuid, type }),

    getPublicCommunities: () => 
        postJson('api/communities_handler.php', { action: 'get_public_communities' }),

    joinPublic: (communityId) => 
        postJson('api/communities_handler.php', { action: 'join_public', community_id: communityId }),

    leaveCommunity: (uuid) => 
        postJson('api/communities_handler.php', { action: 'leave_community', uuid }),

    getByUuid: (uuid) => 
        postJson('api/communities_handler.php', { action: 'get_community_by_uuid', uuid }),

    getUserChatByUuid: (uuid) => 
        postJson('api/communities_handler.php', { action: 'get_user_chat_by_uuid', uuid }),

    getDetails: (uuid) => 
        postJson('api/communities_handler.php', { action: 'get_community_details', uuid }),

    getPrivateDetails: (uuid) => 
        postJson('api/communities_handler.php', { action: 'get_private_chat_details', uuid }),

    createChannel: (communityUuid, name, type = 'text') => 
        postJson('api/communities_handler.php', { action: 'create_channel', community_uuid: communityUuid, name, type }),

    deleteChannel: (channelUuid) => 
        postJson('api/communities_handler.php', { action: 'delete_channel', channel_uuid: channelUuid }),

    joinVoiceChannel: (channelUuid) =>
        postJson('api/communities_handler.php', { action: 'join_voice_channel', channel_uuid: channelUuid }),

    leaveVoiceChannel: (channelUuid) =>
        postJson('api/communities_handler.php', { action: 'leave_voice_channel', channel_uuid: channelUuid })
};

// ==========================================
// SOCIAL / FRIENDS (api/friends_handler.php)
// ==========================================
export const FriendApi = {
    startChat: (targetId) => 
        postJson('api/friends_handler.php', { action: 'start_chat', target_id: targetId }),

    sendRequest: (targetId) => 
        postJson('api/friends_handler.php', { action: 'send_request', target_id: targetId }),

    cancelRequest: (targetId) => 
        postJson('api/friends_handler.php', { action: 'cancel_request', target_id: targetId }),

    acceptRequest: (senderId) => 
        postJson('api/friends_handler.php', { action: 'accept_request', sender_id: senderId }),

    declineRequest: (senderId) => 
        postJson('api/friends_handler.php', { action: 'decline_request', sender_id: senderId }),

    removeFriend: (targetId) => 
        postJson('api/friends_handler.php', { action: 'remove_friend', target_id: targetId }),

    blockUser: (targetId) => 
        postJson('api/friends_handler.php', { action: 'block_user', target_id: targetId }),

    unblockUser: (targetId) => 
        postJson('api/friends_handler.php', { action: 'unblock_user', target_id: targetId })
};

// ==========================================
// NOTIFICATIONS (api/notifications_handler.php)
// ==========================================
export const NotificationApi = {
    getAll: () => 
        postJson('api/notifications_handler.php', { action: 'get_notifications' }),

    markAllRead: () => 
        postJson('api/notifications_handler.php', { action: 'mark_read_all' })
};

// ==========================================
// SETTINGS & USER PROFILE (api/settings_handler.php)
// ==========================================
export const SettingsApi = {
    updateProfilePicture: (file) => {
        const formData = new FormData();
        formData.append('action', 'update_profile_picture');
        formData.append('profile_picture', file);
        return postFormData('api/settings_handler.php', formData);
    },

    removeProfilePicture: () => 
        postJson('api/settings_handler.php', { action: 'remove_profile_picture' }),

    updateUsername: (newUsername) => 
        postJson('api/settings_handler.php', { action: 'update_username', username: newUsername }),

    updateEmail: (newEmail) => 
        postJson('api/settings_handler.php', { action: 'update_email', email: newEmail }),

    verifyPassword: (password) => 
        postJson('api/settings_handler.php', { action: 'verify_current_password', password }),

    updatePassword: (newPassword, logoutOthers) => 
        postJson('api/settings_handler.php', { action: 'update_password', new_password: newPassword, logout_others: logoutOthers }),

    updateUsage: (usage) => 
        postJson('api/settings_handler.php', { action: 'update_usage', usage }),

    updateLanguage: (lang) => 
        postJson('api/settings_handler.php', { action: 'update_language', language: lang }),

    updateTheme: (theme) => 
        postJson('api/settings_handler.php', { action: 'update_theme', theme }),
    
    updatePrivacy: (privacy) => 
        postJson('api/settings_handler.php', { action: 'update_privacy', privacy }),

    updateBooleanPreference: (field, value) => 
        postJson('api/settings_handler.php', { action: 'update_boolean_preference', field, value }),

    generate2FASecret: () => 
        postJson('api/settings_handler.php', { action: 'generate_2fa_secret' }),

    enable2FA: (secret, code) => 
        postJson('api/settings_handler.php', { action: 'enable_2fa_confirm', secret, code }),

    disable2FA: (password) => 
        postJson('api/settings_handler.php', { action: 'disable_2fa', password }),

    getSessions: () => 
        postJson('api/settings_handler.php', { action: 'get_sessions' }),

    revokeSession: (sessionIdDb) => 
        postJson('api/settings_handler.php', { action: 'revoke_session', session_id_db: sessionIdDb }),

    revokeAllSessions: () => 
        postJson('api/settings_handler.php', { action: 'revoke_all_sessions' }),

    deleteAccount: (password) => 
        postJson('api/settings_handler.php', { action: 'delete_account', password })
};

// ==========================================
// ADMIN PANEL (api/admin_handler.php)
// ==========================================
export const AdminApi = {
    getDashboardStats: () => 
        postJson('api/admin_handler.php', { action: 'get_dashboard_stats' }),

    getAlertStatus: () => 
        postJson('api/admin_handler.php', { action: 'get_alert_status' }),

    activateAlert: (type, metaData) => 
        postJson('api/admin_handler.php', { action: 'activate_alert', type, meta_data: metaData }),

    stopAlert: () => 
        postJson('api/admin_handler.php', { action: 'stop_alert' }),

    getUserDetails: (targetId) => 
        postJson('api/admin_handler.php', { action: 'get_user_details', target_id: targetId }),

    updateUserStatus: (targetId, status, reason = null, durationDays = 0) => 
        postJson('api/admin_handler.php', { action: 'update_user_status', target_id: targetId, status, reason, duration_days: durationDays }),

    updateUserGeneral: (params) => 
        postJson('api/admin_handler.php', { action: 'update_user_general', ...params }),

    updateUserRole: (targetId, role) => 
        postJson('api/admin_handler.php', { action: 'update_user_role', target_id: targetId, role }),

    adminUpdateProfilePicture: (targetId, file) => {
        const formData = new FormData();
        formData.append('action', 'admin_update_profile_picture');
        formData.append('target_id', targetId);
        formData.append('profile_picture', file);
        return postFormData('api/admin_handler.php', formData);
    },

    adminRemoveProfilePicture: (targetId) => 
        postJson('api/admin_handler.php', { action: 'admin_remove_profile_picture', target_id: targetId }),

    adminUpdateUsername: (targetId, username) => 
        postJson('api/admin_handler.php', { action: 'admin_update_username', target_id: targetId, username }),

    adminUpdateEmail: (targetId, email) => 
        postJson('api/admin_handler.php', { action: 'admin_update_email', target_id: targetId, email }),

    listBackups: () => 
        postJson('api/admin_handler.php', { action: 'list_backups' }),

    createBackup: () => 
        postJson('api/admin_handler.php', { action: 'create_backup' }),

    deleteBackup: (filename) => 
        postJson('api/admin_handler.php', { action: 'delete_backup', filename }),

    restoreBackup: (filename) => 
        postJson('api/admin_handler.php', { action: 'restore_backup', filename }),

    updateServerConfig: (key, value) => 
        postJson('api/admin_handler.php', { action: 'update_server_config', key, value }),

    listCommunities: (query = '') => 
        postJson('api/admin_handler.php', { action: 'list_communities', q: query }),

    getCommunityDetails: (id) => 
        postJson('api/admin_handler.php', { action: 'get_admin_community_details', id }),

    saveCommunity: (params) => 
        postJson('api/admin_handler.php', { action: 'save_community', ...params }),

    deleteCommunity: (id) => 
        postJson('api/admin_handler.php', { action: 'delete_community', id }),

    getCommunityMembers: (communityId) => 
        postJson('api/admin_handler.php', { action: 'get_community_members', community_id: communityId }),

    getCommunityBannedUsers: (communityId) => 
        postJson('api/admin_handler.php', { action: 'get_community_banned_users', community_id: communityId }),

    kickMember: (communityId, userId) => 
        postJson('api/admin_handler.php', { action: 'kick_member', community_id: communityId, user_id: userId }),

    banMember: (communityId, userId, reason) => 
        postJson('api/admin_handler.php', { action: 'ban_member', community_id: communityId, user_id: userId, reason }),

    muteMember: (communityId, userId, duration) => 
        postJson('api/admin_handler.php', { action: 'mute_member', community_id: communityId, user_id: userId, duration }),

    unbanMember: (communityId, userId) => 
        postJson('api/admin_handler.php', { action: 'unban_member', community_id: communityId, user_id: userId }),

    // [NUEVO] Métodos de Diagnóstico
    getRedisStatus: () => 
        postJson('api/admin_handler.php', { action: 'get_redis_status' }),

    clearRedis: () => 
        postJson('api/admin_handler.php', { action: 'clear_redis' }),

    testBridge: () => 
        postJson('api/admin_handler.php', { action: 'test_bridge' })
};