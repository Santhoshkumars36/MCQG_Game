<?php
/**
 * MCQG Admin - Team Management: Edit Team
 * Path: admin/team_management/edit_team.php
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$teamId = (int) ($_GET['team_id'] ?? $_POST['team_id'] ?? 0);
$team = $db->fetchOne("SELECT * FROM team_master WHERE team_id = :t", ['t' => $teamId]);

if (!$team) { header('Location: team_list.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teamName = trim($_POST['team_name'] ?? '');
    $openingInventory = (int) ($_POST['opening_inventory'] ?? 0);
    $openingBudget = (float) ($_POST['opening_budget'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (!Validator::required($teamName)) {
        $error = 'Team name is required.';
    } else {
        $db->update('team_master', [
            'team_name' => $teamName,
            'opening_inventory' => $openingInventory,
            'opening_budget' => $openingBudget,
            'is_active' => $isActive,
        ], 'team_id = :t', ['t' => $teamId]);

        Logger::activity("Team '{$teamName}' (ID {$teamId}) updated.");
        Session::setFlash('success', 'Team updated successfully.');
        header('Location: team_list.php?game_id=' . $team['game_id']);
        exit;
    }
}

$pageTitle = 'Edit Team';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card" style="max-width:600px;">
  <div class="mcqg-card-header"><h2>Edit Team</h2></div>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <form method="POST">
    <input type="hidden" name="team_id" value="<?php echo (int) $teamId; ?>">
    <div class="mb-3">
      <label class="form-label fw-bold">Team Name</label>
      <input type="text" name="team_name" class="form-control mcqg-input-live" required
             value="<?php echo htmlspecialchars($team['team_name']); ?>">
    </div>
    <div class="row g-3 mb-3">
      <div class="col-6">
        <label class="form-label fw-bold">Opening Inventory</label>
        <input type="number" name="opening_inventory" class="form-control" value="<?php echo (int) $team['opening_inventory']; ?>">
      </div>
      <div class="col-6">
        <label class="form-label fw-bold">Opening Budget</label>
        <input type="number" step="0.01" name="opening_budget" class="form-control" value="<?php echo (float) $team['opening_budget']; ?>">
      </div>
    </div>
    <div class="form-check mb-3">
      <input type="checkbox" name="is_active" class="form-check-input" id="isActive" <?php echo $team['is_active'] ? 'checked' : ''; ?>>
      <label class="form-check-label" for="isActive">Active (team can log in and submit decisions)</label>
    </div>
    <button type="submit" class="btn btn-mcqg-gold">Save Changes</button>
    <a href="team_list.php?game_id=<?php echo (int) $team['game_id']; ?>" class="btn btn-mcqg-outline">Cancel</a>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
