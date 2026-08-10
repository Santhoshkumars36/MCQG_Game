<?php
/**
 * MCQG Player - Screen 1: Case Study
 * Path: player/screens/case_study.php
 * Source: MG19 Slide 9 - screen split 2/3 case study (rich text) /
 * 1/3 opening inventory, opening budget, number of periods.
 */
require_once __DIR__ . '/../../config/app_config.php';
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
$yearNo = $currentRound['year_no'] ?? (int) $game['no_of_years'];

// Team's running cash position, if any prior rounds were processed
$lastResult = $db->fetchOne(
    "SELECT cash_position FROM team_result WHERE team_id = :t ORDER BY year_no DESC LIMIT 1",
    ['t' => $teamId]
);
$currentBudget = $lastResult ? (float) $lastResult['cash_position'] : (float) $team['opening_budget'];

$lastInventory = $db->fetchOne(
    "SELECT closing_inventory FROM team_result WHERE team_id = :t ORDER BY year_no DESC LIMIT 1",
    ['t' => $teamId]
);
$currentInventory = $lastInventory ? (int) $lastInventory['closing_inventory'] : (int) $team['opening_inventory'];

$pageTitle = 'Case Study';
require_once __DIR__ . '/../includes/player_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/player_sidebar.php'; ?>

<div class="mcqg-round-banner">
  <div>
    <h3>Year <?php echo (int) $yearNo; ?> of <?php echo (int) $game['no_of_years']; ?></h3>
    <p>Read the scenario, then move on to build your production plan.</p>
  </div>
  <span class="mcqg-badge mcqg-badge-open">Round Open</span>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="mcqg-card">
      <div class="mcqg-card-header"><h2><?php echo htmlspecialchars($game['game_name']); ?></h2></div>
      <div style="line-height:1.7;"><?php echo $game['description']; ?></div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="mcqg-card">
      <div class="mcqg-card-header"><h3>Your Position</h3></div>
      <div class="mcqg-live-stat" style="border-bottom-color:var(--mcqg-border); color:var(--mcqg-text);">
        <span class="label" style="color:var(--mcqg-text-muted);">Opening Inventory</span>
        <span class="value" style="color:var(--mcqg-navy);"><?php echo number_format($currentInventory); ?> units</span>
      </div>
      <div class="mcqg-live-stat" style="border-bottom-color:var(--mcqg-border); color:var(--mcqg-text);">
        <span class="label" style="color:var(--mcqg-text-muted);">Opening Budget</span>
        <span class="value" style="color:var(--mcqg-navy);">&#8377;<?php echo number_format($currentBudget, 2); ?></span>
      </div>
      <div class="mcqg-live-stat" style="color:var(--mcqg-text);">
        <span class="label" style="color:var(--mcqg-text-muted);">Periods Remaining</span>
        <span class="value" style="color:var(--mcqg-navy);"><?php echo (int) $game['no_of_years'] - $yearNo + 1; ?></span>
      </div>

      <a href="build_production.php" class="btn btn-mcqg-gold w-100 mt-3">Start Building Production &rarr;</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/player_footer.php'; ?>
