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

        case 'get_messages':
            $filter = $_GET['filter_team_id'] ?? $_POST['filter_team_id'] ?? 'all';
            $db = Database::getInstance();
            
            if ($filter === '0' || $filter === 0) {
                // Broadcasts / Common only
                $messages = $db->fetchAll(
                    "SELECT m.*, t.team_name FROM message_center m LEFT JOIN team_master t ON t.team_id = m.team_id WHERE m.game_id = :g AND m.team_id IS NULL ORDER BY m.created_on ASC",
                    ['g' => $gameId]
                );
            } elseif ($filter !== 'all' && is_numeric($filter) && (int)$filter > 0) {
                // Specific Team Thread (Team messages + Moderator direct messages to this team + Broadcasts)
                $teamId = (int)$filter;
                $messages = $db->fetchAll(
                    "SELECT m.*, t.team_name FROM message_center m LEFT JOIN team_master t ON t.team_id = m.team_id WHERE m.game_id = :g AND (m.team_id = :t OR m.team_id IS NULL) ORDER BY m.created_on ASC",
                    ['g' => $gameId, 't' => $teamId]
                );
            } else {
                // All combined
                $messages = $db->fetchAll(
                    "SELECT m.*, t.team_name FROM message_center m LEFT JOIN team_master t ON t.team_id = m.team_id WHERE m.game_id = :g ORDER BY m.created_on ASC",
                    ['g' => $gameId]
                );
            }
            
            $formatted = [];
            foreach ($messages as $m) {
                $isTeamSender = ($m['sender_type'] === 'Team');
                $formatted[] = [
                    'message_id'  => $m['message_id'],
                    'sender_type' => $m['sender_type'],
                    'sender_name' => $m['sender_name'],
                    'team_id'     => $m['team_id'],
                    'team_name'   => $m['team_name'],
                    'text'        => $m['message_text'],
                    'created_at'  => date('d M Y, h:i A', strtotime($m['created_on'])),
                    'is_team'     => $isTeamSender,
                    'is_broadcast'=> ($m['team_id'] === null)
                ];
            }
            echo json_encode(['success' => true, 'messages' => $formatted]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
