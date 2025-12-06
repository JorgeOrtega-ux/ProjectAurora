<?php
// api/communities_handler.php

$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/communities_error.log';
if (!file_exists($logDir)) { mkdir($logDir, 0777, true); }
ini_set('log_errors', TRUE);
ini_set('error_log', $logFile);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
date_default_timezone_set('America/Matamoros');

require_once '../config/core/database.php';
require_once '../config/helpers/utilities.php';
require_once '../includes/logic/i18n_server.php';

// Importamos todos los servicios
require_once '../includes/logic/communities/sidebar_service.php';
require_once '../includes/logic/communities/moderation_service.php';
require_once '../includes/logic/communities/channels_service.php';
require_once '../includes/logic/communities/preferences_service.php';
require_once '../includes/logic/communities/membership_service.php';
require_once '../includes/logic/communities/details_service.php';
// [NUEVO] Importamos el servicio de admin para gestión de solicitudes
require_once '../includes/logic/admin/communities_service.php';

$lang = $_SESSION['user_lang'] ?? detect_browser_language() ?? 'es-latam';
I18n::load($lang);

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? '';

if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => translation('global.error_csrf')]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => translation('global.session_expired')]);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    switch ($action) {
        
        // --- MEMBERSHIP SERVICE ---
        case 'join_by_code':
            echo json_encode(MembershipService::joinByCode($pdo, $userId, $data['access_code'] ?? ''));
            break;

        case 'join_public':
            echo json_encode(MembershipService::joinPublic($pdo, $userId, $data['community_id'] ?? 0));
            break;

        case 'leave_community':
            echo json_encode(MembershipService::leaveCommunity($pdo, $userId, $data['uuid'] ?? '', $data['community_id'] ?? 0));
            break;

        case 'get_public_communities':
            echo json_encode(MembershipService::getPublicCommunities($pdo, $userId));
            break;

        // [NUEVO] Solicitud de acceso
        case 'request_access':
            // Se puede enviar 'community_uuid' o el nombre (dependiendo de la implementación del front)
            // Asumiremos que el front resuelve el UUID o enviamos el nombre para buscar.
            $commUuid = $data['community_uuid'] ?? '';
            $commName = $data['community_name'] ?? '';
            echo json_encode(MembershipService::requestAccess($pdo, $userId, $commUuid, $commName));
            break;

        // --- SOLICITUDES (ADMIN) ---
        case 'get_join_requests':
            // Opcional: pasar community_id si se quiere filtrar
            echo json_encode(get_join_requests($pdo));
            break;

        case 'resolve_join_request':
            $reqId = $data['request_id'] ?? 0;
            $decision = $data['decision'] ?? ''; // 'accept' or 'reject'
            echo json_encode(resolve_join_request($pdo, $reqId, $decision, $userId));
            break;


        // --- SIDEBAR SERVICE ---
        case 'get_sidebar_list':
            $fullList = SidebarService::getSidebarList($pdo, $redis ?? null, $userId);
            echo json_encode(['success' => true, 'list' => $fullList]);
            break;


        // --- PREFERENCES SERVICE ---
        case 'toggle_pin':
            echo json_encode(PreferencesService::togglePin($pdo, $userId, $data['uuid'] ?? '', $data['type'] ?? ''));
            break;

        case 'toggle_favorite':
            echo json_encode(PreferencesService::toggleFavorite($pdo, $userId, $data['uuid'] ?? '', $data['type'] ?? ''));
            break;

        case 'toggle_archive':
            echo json_encode(PreferencesService::toggleArchive($pdo, $userId, $data['uuid'] ?? '', $data['type'] ?? ''));
            break;


        // --- MODERATION SERVICE ---
        case 'kick_member':
            echo json_encode(ModerationService::kickMember($pdo, $userId, $data['community_uuid'] ?? '', $data['target_uuid'] ?? ''));
            break;

        case 'ban_member':
            echo json_encode(ModerationService::banMember(
                $pdo, 
                $userId, 
                $data['community_uuid'] ?? '', 
                $data['target_uuid'] ?? '', 
                $data['reason'] ?? '', 
                $data['duration'] ?? 'permanent'
            ));
            break;

        case 'unban_member':
            echo json_encode(ModerationService::unbanMember($pdo, $userId, $data['community_uuid'] ?? '', $data['target_uuid'] ?? ''));
            break;

        case 'mute_member':
            echo json_encode(ModerationService::muteMember($pdo, $userId, $data['community_uuid'] ?? '', $data['target_uuid'] ?? '', $data['duration'] ?? 5));
            break;


        // --- DETAILS SERVICE ---
        case 'get_community_details':
            echo json_encode(DetailsService::getCommunityDetails($pdo, $userId, $data['uuid'] ?? ''));
            break;
            
        case 'get_private_chat_details':
            echo json_encode(DetailsService::getPrivateChatDetails($pdo, $userId, $data['uuid'] ?? ''));
            break;

        case 'get_community_by_uuid':
            echo json_encode(DetailsService::getCommunityByUuid($pdo, $userId, $data['uuid'] ?? ''));
            break;

        case 'get_user_chat_by_uuid':
            echo json_encode(DetailsService::getUserChatByUuid($pdo, $userId, $data['uuid'] ?? ''));
            break;


        // --- CHANNELS SERVICE ---
        case 'create_channel':
            echo json_encode(ChannelsService::createChannel($pdo, $userId, $data['community_uuid'] ?? '', $data['name'] ?? ''));
            break;

        case 'delete_channel':
            echo json_encode(ChannelsService::deleteChannel($pdo, $userId, $data['channel_uuid'] ?? ''));
            break;

        default:
            throw new Exception(translation('global.action_invalid'));
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>