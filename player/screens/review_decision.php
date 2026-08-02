<?php
/**
 * MCQG Player - Screen: Review Decision
 * Path: player/screens/review_decision.php
 * Full summary of the Draft decision before the team locks it in
 * by hitting Submit (which posts to submit_decision.php).
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireTeam();

$db = Database::getInstance();
$teamId = Session::get('team_id');
$gameId = Session::get('game_id');
$game = $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]);

$currentRound = $db->fetchOne(
    "SELECT * FROM game_round_status WHERE game_id = :g AND status != 'Processed' ORDER BY year_no LIMIT 1",
    ['g' => $gameId]
);
$yearNo = $currentRound['year_no'] ?? 1;

$decision = $db->fetchOne("SELECT * FROM team_decision WHERE team_id = :t AND year_no = :y", ['t' => $teamId, 'y' => $yearNo]);
if (!$decision) { header('Location: build_production.php'); exit; }

$selections = $db->fetchAll(
    "SELECT tis.*, im.investment_name FROM team_investment_selection tis
     INNER JOIN investment_master im ON im.investment_id = tis.investment_id
     WHERE tis.decision_id = :d",
    ['d' => $decision['decision_id']]
);
$totalInvestment = array_sum(array_column($selections, 'invested_value'));
$estimatedRevenue = $decision['production_qty'] * $decision['selling_price'];

$pageTitle = 'Review Decision';
require_once __DIR__ . '/../includes/player_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/player_sidebar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-card-header"><h2>Review Your Decision &mdash; Year <?php echo (int) $yearNo; ?></h2></div>

  <?php if ($decision['status'] === 'Submitted'): ?>
    <div class="alert alert-info">This decision has already been submitted for this round. Waiting on the admin to process results.</div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="mcqg-card" style="margin-bottom:0;">
        <div class="text-muted small">Capacity Built</div>
        <div class="fs-4 fw-bold" style="color:var(--mcqg-navy);"><?php echo number_format($decision['capacity_built']); ?></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="mcqg-card" style="margin-bottom:0;">
        <div class="text-muted small">Production Quantity</div>
        <div class="fs-4 fw-bold" style="color:var(--mcqg-navy);"><?php echo number_format($decision['production_qty']); ?></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="mcqg-card" style="margin-bottom:0;">
        <div class="text-muted small">Selling Price</div>
        <div class="fs-4 fw-bold" style="color:var(--mcqg-navy);">&#8377;<?php echo number_format($decision['selling_price'], 2); ?></div>
      </div>
    </div>
  </div>

  <h5 class="fw-bold" style="color:var(--mcqg-navy);">Investments Selected</h5>
  <?php if (empty($selections)): ?>
    <p class="text-muted">No investments selected this round.</p>
  <?php else: ?>
  <table class="mcqg-report-table w-100 mb-4">
    <thead><tr><th>Investment</th><th>Amount Invested</th></tr></thead>
    <tbody>
      <?php foreach ($selections as $s): ?>
      <tr><td><?php echo htmlspecialchars($s['investment_name']); ?></td><td>&#8377;<?php echo number_format($s['invested_value'], 2); ?></td></tr>
      <?php endforeach; ?>
      <tr class="fw-bold"><td>Total</td><td>&#8377;<?php echo number_format($totalInvestment, 2); ?></td></tr>
    </tbody>
  </table>
  <?php endif; ?>

  <div class="alert alert-light border">Estimated Revenue (Production &times; Price): <strong>&#8377;<?php echo number_format($estimatedRevenue, 2); ?></strong> &mdash; actual units sold will depend on the market allocation once the round is processed.</div>

  <?php if ($decision['status'] !== 'Submitted'): ?>
  <div class="d-flex justify-content-between mt-4 pt-3" style="border-top:1px solid var(--mcqg-border);">
    <a href="demand_economics.php" class="btn btn-mcqg-outline">Back &amp; Edit</a>
    <form method="POST" action="submit_decision.php" onsubmit="return confirm('Submit this decision for Year <?php echo $yearNo; ?>? You will not be able to change it afterward.');">
      <button type="submit" class="btn btn-mcqg-gold">Submit Final Decision</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/player_footer.php'; ?>
