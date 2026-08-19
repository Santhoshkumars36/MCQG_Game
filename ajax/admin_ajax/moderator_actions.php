<?php
/**
 * MCQG Admin AJAX - Moderator Control Center Actions
 * Path: ajax/admin_ajax/moderator_actions.php
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$gameId = (int)($_POST['game_id'] ?? $_GET['game_id'] ?? 0);
$adminId = Session::get('admin_id') ? (int)Session::get('admin_id') : 1;

if (!$gameId) {
    echo json_encode(['success' => false, 'message' => 'Invalid Game ID']);
    exit;
}

try {
    switch ($action) {
        case 'get_live_status':
            $state = Moderator::getDashboardState($gameId);
            echo json_encode(['success' => true, 'data' => $state]);
            break;

        case 'launch_round':
            $yearNo = (int)($_POST['year_no'] ?? 1);
            $res = Moderator::launchRound($gameId, $yearNo, $adminId);
            echo json_encode($res);
            break;

        case 'process_round':
            $yearNo = (int)($_POST['year_no'] ?? 1);
            $force = isset($_POST['force']) && $_POST['force'] == '1';
            $res = Moderator::processRound($gameId, $yearNo, $adminId, $force);
            echo json_encode($res);
            break;

        case 'save_adjustment':
            $teamId = (int)($_POST['team_id'] ?? 0);
            $yearNo = (int)($_POST['year_no'] ?? 1);
            $amount = (float)($_POST['amount'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            if (!$teamId || !$reason) {
                echo json_encode(['success' => false, 'message' => 'Please select a team and provide a reason.']);
                exit;
            }
            $res = Moderator::saveAdjustment($gameId, $teamId, $yearNo, $amount, $reason);
            echo json_encode($res);
            break;

        case 'send_message':
            $teamId = !empty($_POST['team_id']) ? (int)$_POST['team_id'] : null;
            $yearNo = (int)($_POST['year_no'] ?? 1);
            $text = trim($_POST['message_text'] ?? '');
            if (!$text) {
                echo json_encode(['success' => false, 'message' => 'Message text cannot be empty.']);
                exit;
            }
            $db = Database::getInstance();
            $db->insert('message_center', [
                'game_id' => $gameId,
                'team_id' => $teamId,
                'year_no' => $yearNo,
                'sender_type' => 'Moderator',
                'sender_name' => 'Moderator',
                'message_text' => $text
            ]);
            echo json_encode(['success' => true, 'message' => 'Message sent successfully.']);
            break;

        case 'get_team_round_detail':
            $teamId = (int)($_POST['team_id'] ?? $_GET['team_id'] ?? 0);
            $yearNo = (int)($_POST['year_no'] ?? $_GET['year_no'] ?? 1);
            if (!$teamId) {
                echo json_encode(['success' => false, 'message' => 'Invalid Team ID']);
                exit;
            }
            $data = Moderator::getTeamRoundDetail($gameId, $teamId, $yearNo);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'get_round_overview':
            $yearNo = (int)($_POST['year_no'] ?? $_GET['year_no'] ?? 1);
            $data = Moderator::getRoundOverview($gameId, $yearNo);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
