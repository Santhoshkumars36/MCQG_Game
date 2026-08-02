<?php
/**
 * MCQG Admin - Team Management: Add Team
 * Path: admin/team_management/add_team.php
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$gameId = (int) ($_GET['game_id'] ?? $_POST['game_id'] ?? 0);
$game = $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teamName = trim($_POST['team_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $openingInventory = (int) ($_POST['opening_inventory'] ?? $game['starting_inventory'] ?? 0);
    $openingBudget = (float) ($_POST['opening_budget'] ?? $game['starting_cash'] ?? 0);

    if (!Validator::required($teamName) || !Validator::required($username) || !Validator::required($password)) {
        $error = 'Team name, username and password are all required.';
    } else {
        $existing = $db->fetchOne("SELECT team_id FROM team_master WHERE username = :u", ['u' => $username]);
        if ($existing) {
            $error = 'That username is already taken - please choose another.';
        } else {
            $db->insert('team_master', [
                'game_id' => $gameId,
                'team_name' => $teamName,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'opening_inventory' => $openingInventory,
                'opening_budget' => $openingBudget,
                'is_active' => 1,
            ]);
            Logger::activity("Team '{$teamName}' added to game {$gameId}.");
            Session::setFlash('success', "Team '{$teamName}' added successfully.");
            header('Location: team_list.php?game_id=' . $gameId);
            exit;
        }
    }
}

$pageTitle = 'Add Team';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card" style="max-width:600px;">
  <div class="mcqg-card-header"><h2>Add Team</h2></div>
  <p class="text-muted">Adding to game: <strong><?php echo htmlspecialchars($game['game_name'] ?? 'Unknown'); ?></strong></p>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <form method="POST">
    <input type="hidden" name="game_id" value="<?php echo (int) $gameId; ?>">
    <div class="mb-3">
      <label class="form-label fw-bold">Team Name</label>
      <input type="text" name="team_name" class="form-control mcqg-input-live" required placeholder="e.g. Falcon">
    </div>
    <div class="row g-3 mb-3">
      <div class="col-6">
        <label class="form-label fw-bold">Login Username</label>
        <input type="text" name="username" class="form-control mcqg-input-live" required>
      </div>
      <div class="col-6">
        <label class="form-label fw-bold">Login Password</label>
        <input type="password" name="password" class="form-control mcqg-input-live" required>
      </div>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-6">
        <label class="form-label fw-bold">Opening Inventory</label>
        <input type="number" name="opening_inventory" class="form-control" value="<?php echo (int) ($game['starting_inventory'] ?? 0); ?>">
      </div>
      <div class="col-6">
        <label class="form-label fw-bold">Opening Budget</label>
        <input type="number" step="0.01" name="opening_budget" class="form-control" value="<?php echo (float) ($game['starting_cash'] ?? 0); ?>">
      </div>
    </div>
    <button type="submit" class="btn btn-mcqg-gold">Add Team</button>
    <a href="team_list.php?game_id=<?php echo (int) $gameId; ?>" class="btn btn-mcqg-outline">Cancel</a>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
