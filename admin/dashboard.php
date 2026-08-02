<?php
/**
 * MCQG Admin - Dashboard
 * Path: admin/dashboard.php
 */
require_once __DIR__ . '/../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$totalGames = $db->fetchOne("SELECT COUNT(*) AS c FROM game_master")['c'];
$publishedGames = $db->fetchOne("SELECT COUNT(*) AS c FROM game_master WHERE status = 'Published'")['c'];
$totalTeams = $db->fetchOne("SELECT COUNT(*) AS c FROM team_master")['c'];
$roundsProcessed = $db->fetchOne("SELECT COUNT(*) AS c FROM game_round_status WHERE status = 'Processed'")['c'];

$recentGames = $db->fetchAll("SELECT * FROM game_master ORDER BY created_on DESC LIMIT 6");

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/includes/admin_topbar.php'; ?>

<h2 class="mb-3" style="color:var(--mcqg-navy); font-weight:800;">Welcome back, <?php echo htmlspecialchars(Session::get('admin_name', 'Admin')); ?></h2>

<div class="mcqg-stat-grid">
  <div class="mcqg-stat-card">
    <div class="mcqg-stat-icon">&#127918;</div>
    <div class="mcqg-stat-value"><?php echo (int) $totalGames; ?></div>
    <div class="mcqg-stat-label">Total Games</div>
  </div>
  <div class="mcqg-stat-card">
    <div class="mcqg-stat-icon">&#9989;</div>
    <div class="mcqg-stat-value"><?php echo (int) $publishedGames; ?></div>
    <div class="mcqg-stat-label">Published Games</div>
  </div>
  <div class="mcqg-stat-card">
    <div class="mcqg-stat-icon">&#128101;</div>
    <div class="mcqg-stat-value"><?php echo (int) $totalTeams; ?></div>
    <div class="mcqg-stat-label">Teams Registered</div>
  </div>
  <div class="mcqg-stat-card">
    <div class="mcqg-stat-icon">&#128202;</div>
    <div class="mcqg-stat-value"><?php echo (int) $roundsProcessed; ?></div>
    <div class="mcqg-stat-label">Rounds Processed</div>
  </div>
</div>

<div class="mcqg-card">
  <div class="mcqg-card-header">
    <h3>Recent Games</h3>
    <a href="game_setup/step1_title_case_study.php" class="btn btn-mcqg-gold">+ New Game</a>
  </div>

  <?php if (empty($recentGames)): ?>
    <p class="text-muted">No games created yet. Click "New Game" to get started.</p>
  <?php else: ?>
  <div class="mcqg-game-grid">
    <?php foreach ($recentGames as $g):
      $badgeClass = $g['status'] === 'Published' ? 'mcqg-badge-published' : ($g['status'] === 'Completed' ? 'mcqg-badge-completed' : 'mcqg-badge-draft');
    ?>
    <div class="mcqg-game-tile">
      <div class="mcqg-game-tile-header">
        <span><?php echo htmlspecialchars($g['game_name']); ?></span>
        <span class="mcqg-badge <?php echo $badgeClass; ?>"><?php echo $g['status']; ?></span>
      </div>
      <div class="mcqg-game-tile-body">
        <p><?php echo (int) $g['no_of_years']; ?> years &middot; <?php echo htmlspecialchars($g['currency']); ?></p>
        <p>Created <?php echo date('d M Y', strtotime($g['created_on'])); ?></p>
        <a href="manage_games.php?game_id=<?php echo $g['game_id']; ?>" class="btn btn-sm btn-mcqg-outline w-100 mt-2">Manage</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
