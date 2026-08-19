<?php
/**
 * MCQG Admin - Game Setup Step 7: Publish
 * Path: admin/game_setup/step7_publish.php
 * Source: MG19 Slide 3-7 (end of 7-step wizard) + Doc 2 (game_master.status)
 * Final review screen - on confirm, status changes Draft -> Published
 * and per-year game_round_status rows are created so round_control/
 * can track progress from Year 1 onward.
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$gameId = Session::get('setup_game_id');
if (!$gameId) { header('Location: step1_title_case_study.php'); exit; }

$game = $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]);
$marketYears = $db->fetchAll("SELECT * FROM game_market_year WHERE game_id = :g ORDER BY year_no", ['g' => $gameId]);
$capacityDrivers = $db->fetchAll("SELECT * FROM capacity_driver WHERE game_id = :g", ['g' => $gameId]);
$demandDrivers = $db->fetchAll("SELECT * FROM demand_driver WHERE game_id = :g", ['g' => $gameId]);
$investments = $db->fetchAll("SELECT * FROM investment_master WHERE game_id = :g", ['g' => $gameId]);

$capacityTotal = array_sum(array_column($capacityDrivers, 'cost_share_percent'));
$demandTotal = array_sum(array_column($demandDrivers, 'demand_share_percent'));
$combinedTotal = round($capacityTotal + $demandTotal, 2);
$readyToPublish = (abs($combinedTotal - 100.00) < 0.01) && count($investments) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish'])) {
    if (!$readyToPublish) {
        Session::setFlash('error', 'Cannot publish — Combined Capacity and Demand Driver totals must equal 100% and at least one investment must exist.');
    } else {
        $db->update('game_master', ['status' => GAME_STATUS_PUBLISHED], 'game_id = :g', ['g' => $gameId]);

        foreach ($marketYears as $my) {
            $exists = $db->fetchOne(
                "SELECT round_status_id FROM game_round_status WHERE game_id = :g AND year_no = :y",
                ['g' => $gameId, 'y' => $my['year_no']]
            );
            if (!$exists) {
                $db->insert('game_round_status', [
                    'game_id' => $gameId, 'year_no' => $my['year_no'], 'status' => ROUND_STATUS_OPEN,
                ]);
            }
        }

        Logger::activity("Game {$gameId} published.");
        Session::remove('setup_game_id');
        Session::setFlash('success', 'Game published successfully! Teams can now be added and the game can begin.');
        header('Location: ' . ADMIN_URL . 'manage_games.php');
        exit;
    }
}

$pageTitle = 'Game Setup - Step 7';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-stepper">
    <div class="mcqg-stepper-fill" style="width:100%"></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Title &amp; Case Study</div></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Game Definition</div></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Capacity Drivers</div></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Demand Drivers</div></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Investments</div></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Configuration</div></div>
    <div class="mcqg-step active"><div class="mcqg-step-circle">7</div><div class="mcqg-step-label">Publish</div></div>
  </div>

  <div class="mcqg-wizard-panel">
    <h3>Step 7 of 7 &mdash; Review &amp; Publish</h3>
    <p class="text-muted">Review everything below, then publish to make this game available to teams.</p>

    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="mcqg-driver-group-card">
          <h6 class="fw-bold">Game</h6>
          <p class="mb-1"><?php echo htmlspecialchars($game['game_name']); ?></p>
          <p class="text-muted small mb-0"><?php echo (int) $game['no_of_years']; ?> years &middot; <?php echo htmlspecialchars($game['currency']); ?> &middot; Capacity <?php echo (int) $game['starting_capacity']; ?></p>
        </div>
      </div>
      <div class="col-md-6">
        <div class="mcqg-driver-group-card">
          <h6 class="fw-bold">Readiness Check</h6>
          <p class="mb-1">
            Capacity Drivers total:
            <span><?php echo number_format($capacityTotal, 2); ?>%</span>
          </p>
          <p class="mb-1">
            Demand Drivers total:
            <span><?php echo number_format($demandTotal, 2); ?>%</span>
          </p>
          <p class="mb-1 fw-bold">
            Combined Driver total:
            <span class="<?php echo abs($combinedTotal - 100.00) < 0.01 ? 'effect-positive' : 'effect-negative'; ?>"><?php echo number_format($combinedTotal, 2); ?>% (must be 100%)</span>
          </p>
          <p class="mb-0">Investments configured: <strong><?php echo count($investments); ?></strong></p>
        </div>
      </div>
    </div>

    <h6 class="fw-bold" style="color:var(--mcqg-navy);">Annual Market Setup</h6>
    <table class="table table-sm mb-4">
      <thead><tr><th>Year</th><th>Demand</th><th>Inflation %</th></tr></thead>
      <tbody>
        <?php foreach ($marketYears as $my): ?>
        <tr><td>Year <?php echo (int) $my['year_no']; ?></td><td><?php echo number_format($my['market_demand']); ?></td><td><?php echo number_format($my['inflation_percent'], 2); ?>%</td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if (!$readyToPublish): ?>
      <div class="alert alert-warning">Some items still need attention above before this game can be published.</div>
    <?php endif; ?>

    <form method="POST">
      <div class="mcqg-wizard-nav">
        <a href="step6_game_configuration.php" class="btn btn-mcqg-outline">Back</a>
        <button type="submit" name="publish" value="1" class="btn btn-mcqg-gold" <?php echo $readyToPublish ? '' : 'disabled'; ?>>
          Publish Game
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
