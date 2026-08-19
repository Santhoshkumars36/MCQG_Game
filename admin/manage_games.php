<?php
/**
 * MCQG Admin - Manage Games
 * Path: admin/manage_games.php
 * Central hub linking out to team management, round control and
 * reports for a selected game.
 */
require_once __DIR__ . '/../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM game_master";
$params = [];
if ($search !== '') {
    $sql .= " WHERE game_name LIKE :s";
    $params['s'] = '%' . $search . '%';
}
$sql .= " ORDER BY created_on DESC";
$games = $db->fetchAll($sql, $params);

$selectedGameId = (int) ($_GET['game_id'] ?? 0);
$selectedGame = $selectedGameId ? $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $selectedGameId]) : null;

$pageTitle = 'Manage Games';
require_once __DIR__ . '/includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/includes/admin_topbar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-card-header">
    <h2>Manage Games</h2>
    <a href="game_setup/step1_title_case_study.php" class="btn btn-mcqg-gold">+ New Game</a>
  </div>

  <form method="GET" class="mb-3" style="max-width:360px;">
    <input type="text" name="search" class="form-control" placeholder="Search games..." value="<?php echo htmlspecialchars($search); ?>" onkeyup="this.form.submit()">
  </form>

  <table class="mcqg-report-table w-100">
    <thead><tr><th>Game Name</th><th>Years</th><th>Currency</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($games as $g):
        $badgeClass = $g['status'] === 'Published' ? 'mcqg-badge-published' : ($g['status'] === 'Completed' ? 'mcqg-badge-completed' : 'mcqg-badge-draft');
      ?>
      <tr>
        <td class="fw-bold">
          <div class="d-flex align-items-center gap-2">
            <?php if ($gImg = Game::getImageUrl($g)): ?>
              <img src="<?php echo $gImg; ?>" alt="Game Image" class="rounded border" style="width:32px; height:32px; object-fit:cover;">
            <?php endif; ?>
            <span><?php echo htmlspecialchars($g['game_name']); ?></span>
          </div>
        </td>
        <td><?php echo (int) $g['no_of_years']; ?></td>
        <td><?php echo htmlspecialchars($g['currency']); ?></td>
        <td><span class="mcqg-badge <?php echo $badgeClass; ?>"><?php echo $g['status']; ?></span></td>
        <td><?php echo date('d M Y', strtotime($g['created_on'])); ?></td>
        <td>
          <a href="moderator/moderator_dashboard.php?game_id=<?php echo $g['game_id']; ?>" class="btn btn-sm btn-mcqg-gold fw-bold" title="Open Moderator Control Center"><i class="fas fa-desktop me-1"></i> Moderator</a>
          <a href="game_setup/step1_title_case_study.php?game_id=<?php echo $g['game_id']; ?>" class="btn btn-sm btn-outline-warning" title="Edit Game"><i class="fas fa-edit"></i> Edit</a>
          <button type="button" onclick="confirmDeleteGame(<?php echo $g['game_id']; ?>, '<?php echo addslashes(htmlspecialchars($g['game_name'])); ?>')" class="btn btn-sm btn-outline-danger" title="Delete Game"><i class="fas fa-trash-alt"></i> Delete</button>
          <a href="team_management/team_list.php?game_id=<?php echo $g['game_id']; ?>" class="btn btn-sm btn-mcqg-outline">Teams</a>
          <a href="round_control/round_status.php?game_id=<?php echo $g['game_id']; ?>" class="btn btn-sm btn-mcqg-outline">Rounds</a>
          <a href="reports/cumulative_report.php?game_id=<?php echo $g['game_id']; ?>" class="btn btn-sm btn-mcqg-outline">Reports</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($selectedGame): ?>
<div class="mcqg-card">
  <div class="mcqg-card-header d-flex justify-content-between align-items-center">
    <h3 class="m-0 d-flex align-items-center gap-2">
      <?php if ($selImg = Game::getImageUrl($selectedGame)): ?>
        <img src="<?php echo $selImg; ?>" alt="Game Logo" class="rounded border" style="width:36px; height:36px; object-fit:cover;">
      <?php endif; ?>
      <span><?php echo htmlspecialchars($selectedGame['game_name']); ?> - Overview</span>
    </h3>
    <div>
      <a href="game_setup/step1_title_case_study.php?game_id=<?php echo $selectedGame['game_id']; ?>" class="btn btn-sm btn-warning me-1"><i class="fas fa-edit me-1"></i> Edit Game</a>
      <button type="button" onclick="confirmDeleteGame(<?php echo $selectedGame['game_id']; ?>, '<?php echo addslashes(htmlspecialchars($selectedGame['game_name'])); ?>')" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt me-1"></i> Delete Game</button>
    </div>
  </div>
  <div class="mcqg-driver-group-card" style="background:#fff;">
    <?php echo $selectedGame['description']; ?>
  </div>
  <div class="row g-3 mt-2">
    <div class="col-md-3"><strong>Product:</strong> <?php echo htmlspecialchars($selectedGame['product_name']); ?></div>
    <div class="col-md-3"><strong>Capacity:</strong> <?php echo (int) $selectedGame['starting_capacity']; ?></div>
    <div class="col-md-3"><strong>Starting Cash:</strong> &#8377;<?php echo number_format($selectedGame['starting_cash'], 2); ?></div>
    <div class="col-md-3"><strong>Unit Cost:</strong> &#8377;<?php echo number_format($selectedGame['unit_cost'], 4); ?></div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
