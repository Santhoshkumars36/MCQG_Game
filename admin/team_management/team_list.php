<?php
/**
 * MCQG Admin - Team Management: Team List
 * Path: admin/team_management/team_list.php
 * Closes the "multiplayer / team setup" gap flagged during requirement
 * analysis - no document defined this screen, so it was added here.
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$gameId = (int) ($_GET['game_id'] ?? 0);
$games = $db->fetchAll("SELECT game_id, game_name, status FROM game_master ORDER BY created_on DESC");

if (!$gameId && count($games) > 0) {
    $gameId = $games[0]['game_id'];
}

$teams = $gameId ? $db->fetchAll(
    "SELECT t.*, 
            (SELECT COUNT(*) FROM team_decision d WHERE d.team_id = t.team_id AND d.status = 'Submitted') AS rounds_submitted
     FROM team_master t WHERE t.game_id = :g ORDER BY t.team_name",
    ['g' => $gameId]
) : [];

$pageTitle = 'Team Management';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-card-header">
    <h2>Team Management</h2>
    <a href="add_team.php?game_id=<?php echo (int) $gameId; ?>" class="btn btn-mcqg-gold">+ Add Team</a>
  </div>

  <form method="GET" class="mb-3" style="max-width:360px;">
    <select name="game_id" class="form-select" onchange="this.form.submit()">
      <?php foreach ($games as $g): ?>
        <option value="<?php echo $g['game_id']; ?>" <?php echo $g['game_id'] == $gameId ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($g['game_name']); ?> (<?php echo $g['status']; ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if (empty($teams)): ?>
    <p class="text-muted">No teams added yet for this game.</p>
  <?php else: ?>
  <table class="mcqg-report-table w-100">
    <thead><tr><th>Team Name</th><th>Username</th><th>Opening Budget</th><th>Opening Inventory</th><th>Rounds Submitted</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($teams as $t): ?>
      <tr>
        <td class="fw-bold"><?php echo htmlspecialchars($t['team_name']); ?></td>
        <td><?php echo htmlspecialchars($t['username']); ?></td>
        <td>&#8377;<?php echo number_format($t['opening_budget'], 2); ?></td>
        <td><?php echo (int) $t['opening_inventory']; ?></td>
        <td><?php echo (int) $t['rounds_submitted']; ?></td>
        <td>
          <span class="mcqg-badge <?php echo $t['is_active'] ? 'mcqg-badge-published' : 'mcqg-badge-completed'; ?>">
            <?php echo $t['is_active'] ? 'Active' : 'Inactive'; ?>
          </span>
        </td>
        <td>
          <a href="edit_team.php?team_id=<?php echo $t['team_id']; ?>" class="btn btn-sm btn-mcqg-outline">Edit</a>
          <a href="assign_login.php?team_id=<?php echo $t['team_id']; ?>" class="btn btn-sm btn-mcqg-outline">Reset Login</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
