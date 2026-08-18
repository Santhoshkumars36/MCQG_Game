<?php
/**
 * MCQG Player - Dashboard
 * Path: player/dashboard.php
 * Landing page after team login - shows current round status and
 * the 4-step journey (Case Study -> Production -> Demand -> Submit).
 */
require_once __DIR__ . '/../config/app_config.php';
Auth::requireTeam();

$db = Database::getInstance();
$teamId = Session::currentTeamId();
$gameId = Session::activeGameId();

$team = $teamId ? $db->fetchOne("SELECT * FROM team_master WHERE team_id = :t", ['t' => $teamId]) : null;
$game = $gameId ? $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]) : null;

if (!$team || !$game) {
    Auth::logout();
    header('Location: ' . PLAYER_URL . 'auth/login.php');
    exit;
}

$currentRound = $db->fetchOne(
    "SELECT * FROM game_round_status WHERE game_id = :g AND status != 'Processed' ORDER BY year_no LIMIT 1",
    ['g' => $gameId]
);
$yearNo = $currentRound['year_no'] ?? null;

$decision = ($yearNo && $teamId) ? $db->fetchOne(
    "SELECT * FROM team_decision WHERE team_id = :t AND year_no = :y",
    ['t' => $teamId, 'y' => $yearNo]
) : null;

$hasProduction = $decision && (int) $decision['production_qty'] > 0;
$hasPrice = $decision && (float) $decision['selling_price'] > 0;
$isSubmitted = $decision && $decision['status'] === 'Submitted';

$latestResult = $teamId ? $db->fetchOne(
    "SELECT * FROM team_result WHERE team_id = :t ORDER BY year_no DESC LIMIT 1",
    ['t' => $teamId]
) : null;

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/player_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/includes/player_sidebar.php'; ?>

<h2 class="mb-3" style="color:var(--mcqg-navy); font-weight:800;">Welcome, <?php echo htmlspecialchars($team['team_name'] ?? 'Team'); ?></h2>

<?php if ($currentRound): ?>
<div class="mcqg-round-banner d-flex align-items-center justify-content-between">
  <div class="d-flex align-items-center gap-3">
    <?php if ($pGameImg = Game::getImageUrl($game)): ?>
      <img src="<?php echo $pGameImg; ?>" alt="Game Logo" class="rounded border shadow-sm" style="width:48px; height:48px; object-fit:cover; background:#fff;">
    <?php endif; ?>
    <div>
      <h3 class="m-0">Year <?php echo (int) $yearNo; ?> of <?php echo (int) ($game['no_of_years'] ?? 0); ?></h3>
      <p class="m-0 text-white-50"><?php echo htmlspecialchars($game['game_name'] ?? ''); ?></p>
    </div>
  </div>
  <span class="mcqg-badge <?php echo $isSubmitted ? 'mcqg-badge-processed' : 'mcqg-badge-open'; ?>">
    <?php echo $isSubmitted ? 'Submitted - Waiting for other teams' : 'Decision In Progress'; ?>
  </span>
</div>

<div class="mcqg-card">
  <div class="mcqg-card-header"><h3>Your Journey This Round</h3></div>
  <div class="mcqg-journey-steps">
    <a href="screens/case_study.php" class="mcqg-journey-step done" style="text-decoration:none; color:inherit;">
      <div class="icon">&#128214;</div><div class="fw-bold mt-2">1. Case Study</div><div class="small text-muted">Review</div>
    </a>
    <a href="screens/build_production.php" class="mcqg-journey-step <?php echo $hasProduction ? 'done' : 'active'; ?>" style="text-decoration:none; color:inherit;">
      <div class="icon">&#127981;</div><div class="fw-bold mt-2">2. Production</div><div class="small text-muted"><?php echo $hasProduction ? 'Complete' : 'Pending'; ?></div>
    </a>
    <a href="screens/demand_economics.php" class="mcqg-journey-step <?php echo $hasPrice ? 'done' : ($hasProduction ? 'active' : ''); ?>" style="text-decoration:none; color:inherit;">
      <div class="icon">&#128176;</div><div class="fw-bold mt-2">3. Demand &amp; Price</div><div class="small text-muted"><?php echo $hasPrice ? 'Complete' : 'Pending'; ?></div>
    </a>
    <a href="screens/review_decision.php" class="mcqg-journey-step <?php echo $isSubmitted ? 'done' : ($hasPrice ? 'active' : ''); ?>" style="text-decoration:none; color:inherit;">
      <div class="icon"><?php echo $isSubmitted ? '&#9989;' : '&#128228;'; ?></div><div class="fw-bold mt-2">4. Review &amp; Submit</div><div class="small text-muted"><?php echo $isSubmitted ? 'Submitted' : 'Pending'; ?></div>
    </a>
  </div>
</div>
<?php else: ?>
<div class="mcqg-card"><p class="text-muted mb-0">No active round right now - check back once the admin opens the next round.</p></div>
<?php endif; ?>

<div class="mcqg-stat-grid">
  <div class="mcqg-stat-card">
    <div class="mcqg-stat-value">&#8377;<?php echo number_format((float)($team['opening_budget'] ?? 0)); ?></div>
    <div class="mcqg-stat-label">Starting Budget</div>
  </div>
  <div class="mcqg-stat-card">
    <div class="mcqg-stat-value"><?php echo (int) ($team['opening_inventory'] ?? 0); ?></div>
    <div class="mcqg-stat-label">Starting Inventory</div>
  </div>
  <?php if ($latestResult): ?>
  <div class="mcqg-stat-card">
    <div class="mcqg-stat-value">&#8377;<?php echo number_format((float)($latestResult['cash_position'] ?? 0)); ?></div>
    <div class="mcqg-stat-label">Current Cash (Year <?php echo (int) ($latestResult['year_no'] ?? 0); ?>)</div>
  </div>
  <div class="mcqg-stat-card">
    <div class="mcqg-stat-value <?php echo ($latestResult['operating_profit'] ?? 0) >= 0 ? '' : ''; ?>">&#8377;<?php echo number_format((float)($latestResult['operating_profit'] ?? 0)); ?></div>
    <div class="mcqg-stat-label">Last Round Profit</div>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/player_footer.php'; ?>
