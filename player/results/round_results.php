<?php
/**
 * MCQG Player - Round Results
 * Path: player/results/round_results.php
 * Source: MG19 Slides 12-13 - Sale value, Cost of sale, Profit/loss,
 * Inventory carrying, shown to teams after the admin processes a round.
 * Also shows competitor prices/market share if game_configuration
 * "Dashboard" rules allow it (Doc 2, Entity 6).
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireTeam();

$db = Database::getInstance();
$teamId = Session::get('team_id');
$gameId = Session::get('game_id');

$processedYears = $db->fetchAll(
    "SELECT DISTINCT year_no FROM team_result WHERE game_id = :g AND team_id = :t ORDER BY year_no DESC",
    ['g' => $gameId, 't' => $teamId]
);
$yearNo = (int) ($_GET['year_no'] ?? ($processedYears[0]['year_no'] ?? 0));

$myResult = $yearNo ? $db->fetchOne(
    "SELECT * FROM team_result WHERE team_id = :t AND year_no = :y",
    ['t' => $teamId, 'y' => $yearNo]
) : null;

$showPrices = $db->fetchOne(
    "SELECT configuration_value FROM game_configuration WHERE game_id = :g AND configuration_key = 'Show Prices'",
    ['g' => $gameId]
);
$allResults = [];
if ($yearNo && $showPrices && strtolower($showPrices['configuration_value']) === 'yes') {
    $allResults = $db->fetchAll(
        "SELECT r.*, t.team_name, d.selling_price
         FROM team_result r
         INNER JOIN team_master t ON t.team_id = r.team_id
         INNER JOIN team_decision d ON d.team_id = r.team_id AND d.year_no = r.year_no
         WHERE r.game_id = :g AND r.year_no = :y ORDER BY r.operating_profit DESC",
        ['g' => $gameId, 'y' => $yearNo]
    );
}

$pageTitle = 'Round Results';
require_once __DIR__ . '/../includes/player_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/player_sidebar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-card-header">
    <h2>Round Results</h2>
    <form method="GET">
      <select name="year_no" class="form-select" onchange="this.form.submit()">
        <?php foreach ($processedYears as $y): ?>
          <option value="<?php echo $y['year_no']; ?>" <?php echo $y['year_no'] == $yearNo ? 'selected' : ''; ?>>Year <?php echo $y['year_no']; ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if (!$myResult): ?>
    <p class="text-muted">No processed results yet - results appear here once the admin processes a round.</p>
  <?php else: ?>
  <div class="mcqg-scoreboard-hero">
    <div><div class="metric-value">&#8377;<?php echo number_format($myResult['revenue']); ?></div><div class="metric-label">Revenue</div></div>
    <div><div class="metric-value <?php echo $myResult['operating_profit'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">&#8377;<?php echo number_format($myResult['operating_profit']); ?></div><div class="metric-label">Operating Profit</div></div>
    <div><div class="metric-value"><?php echo number_format($myResult['units_sold']); ?></div><div class="metric-label">Units Sold</div></div>
    <div><div class="metric-value"><?php echo number_format($myResult['closing_inventory']); ?></div><div class="metric-label">Closing Inventory</div></div>
    <div><div class="metric-value"><?php echo number_format($myResult['capacity_utilization_percent'], 1); ?>%</div><div class="metric-label">Capacity Utilization</div></div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4"><div class="mcqg-driver-group-card"><strong>Cost of Sale:</strong> &#8377;<?php echo number_format($myResult['variable_cost'] + $myResult['fixed_cost'], 2); ?></div></div>
    <div class="col-md-4"><div class="mcqg-driver-group-card"><strong>Investment Cost:</strong> &#8377;<?php echo number_format($myResult['investment_cost'], 2); ?></div></div>
    <div class="col-md-4"><div class="mcqg-driver-group-card"><strong>Cash Position:</strong> &#8377;<?php echo number_format($myResult['cash_position'], 2); ?></div></div>
  </div>

  <?php if (!empty($allResults)): ?>
  <h5 class="fw-bold mt-4" style="color:var(--mcqg-navy);">Competitive Intelligence</h5>
  <table class="mcqg-report-table w-100">
    <thead><tr><th>Team</th><th>Price</th><th>Units Sold</th><th>Revenue</th><th>Profit</th></tr></thead>
    <tbody>
      <?php foreach ($allResults as $r): $isMe = $r['team_id'] == $teamId; ?>
      <tr class="<?php echo $isMe ? 'own-team' : ''; ?>">
        <td><?php echo htmlspecialchars($r['team_name']); ?><?php echo $isMe ? ' (You)' : ''; ?></td>
        <td>&#8377;<?php echo number_format($r['selling_price'], 2); ?></td>
        <td><?php echo number_format($r['units_sold']); ?></td>
        <td>&#8377;<?php echo number_format($r['revenue']); ?></td>
        <td class="<?php echo $r['operating_profit'] >= 0 ? 'mcqg-profit-positive' : 'mcqg-profit-negative'; ?>">&#8377;<?php echo number_format($r['operating_profit']); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <a href="investment_matrix_view.php?year_no=<?php echo $yearNo; ?>" class="btn btn-mcqg-outline mt-3">View Investment Matrix</a>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/player_footer.php'; ?>
