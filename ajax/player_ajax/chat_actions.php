<?php
/**
 * MCQG Player AJAX - Live Chat Actions with Moderator
 * Path: ajax/player_ajax/chat_actions.php
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireTeam();

header('Content-Type: application/json');

$db = Database::getInstance();
$teamId = Session::currentTeamId();
$gameId = Session::activeGameId();

$team = $teamId ? $db->fetchOne("SELECT * FROM team_master WHERE team_id = :t AND is_active = 1", ['t' => $teamId]) : null;

if (!$team || !$gameId) {
    echo json_encode(['success' => false, 'message' => 'Invalid session or team']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'get_chat_history') {
        $messages = $db->fetchAll(
            "SELECT * FROM message_center WHERE game_id = :g AND (team_id IS NULL OR team_id = :t) ORDER BY created_on ASC",
            ['g' => $gameId, 't' => $teamId]
        );

        $unreadRow = $db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM message_center WHERE game_id = :g AND (team_id IS NULL OR team_id = :t) AND sender_type != 'Team' AND is_read = 0",
            ['g' => $gameId, 't' => $teamId]
        );
        $unreadCount = (int)($unreadRow['cnt'] ?? 0);

        $formatted = [];
        foreach ($messages as $m) {
            $isMe = ($m['sender_type'] === 'Team' && (int)$m['team_id'] === (int)$teamId);
            $formatted[] = [
                'id'          => $m['message_id'],
                'sender_type' => $m['sender_type'],
                'sender_name' => $isMe ? 'You' : $m['sender_name'],
                'is_me'       => $isMe,
                'text'        => $m['message_text'],
                'is_read'     => (int)$m['is_read'],
                'created_at'  => date('h:i A', strtotime($m['created_on'])),
                'is_broadcast'=> ($m['team_id'] === null)
            ];
        }

        echo json_encode(['success' => true, 'messages' => $formatted, 'unread_count' => $unreadCount, 'team_name' => $team['team_name']]);
        exit;
    }

    if ($action === 'mark_read') {
        $db->query(
            "UPDATE message_center SET is_read = 1 WHERE game_id = :g AND (team_id IS NULL OR team_id = :t) AND sender_type != 'Team' AND is_read = 0",
            ['g' => $gameId, 't' => $teamId]
        );
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'send_message') {
        $text = trim($_POST['message_text'] ?? '');
        if (!$text) {
            echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
            exit;
        }

        // Active year
        $round = $db->fetchOne("SELECT year_no FROM game_round_status WHERE game_id = :g AND status != 'Processed' ORDER BY year_no LIMIT 1", ['g' => $gameId]);
        $yearNo = $round ? (int)$round['year_no'] : 1;

        $db->insert('message_center', [
            'game_id'     => $gameId,
            'team_id'     => $teamId,
            'year_no'     => $yearNo,
            'sender_type' => 'Team',
            'sender_name' => $team['team_name'],
            'message_text'=> $text,
            'is_read'     => 0
        ]);

        echo json_encode(['success' => true, 'message' => 'Message sent successfully.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
