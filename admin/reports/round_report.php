<?php
/**
 * MCQG Admin - Reports: Round Report
 * Path: admin/reports/round_report.php
 * Source: MG19 Slides 12-13 - Sale value, Cost of sale, Profit/loss,
 * Inventory carrying, per team, for one round.
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$gameId = (int) ($_GET['game_id'] ?? 0);
$yearNo = (int) ($_GET['year_no'] ?? 1);

$game = $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]);
$results = $db->fetchAll(
    "SELECT r.*, t.team_name FROM team_result r
     INNER JOIN team_master t ON t.team_id = r.team_id
     WHERE r.game_id = :g AND r.year_no = :y ORDER BY r.operating_profit DESC",
    ['g' => $gameId, 'y' => $yearNo]
);
$allRounds = $db->fetchAll("SELECT DISTINCT year_no FROM game_round_status WHERE game_id = :g ORDER BY year_no", ['g' => $gameId]);

$pageTitle = 'Round Report';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-card-header">
    <h2>Round Report &mdash; <?php echo htmlspecialchars($game['game_name'] ?? ''); ?></h2>
    <form method="GET" class="d-flex gap-2">
      <input type="hidden" name="game_id" value="<?php echo $gameId; ?>">
      <select name="year_no" class="form-select" onchange="this.form.submit()">
        <?php foreach ($allRounds as $r): ?>
          <option value="<?php echo $r['year_no']; ?>" <?php echo $r['year_no'] == $yearNo ? 'selected' : ''; ?>>Year <?php echo $r['year_no']; ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if (empty($results)): ?>
    <p class="text-muted">This round hasn't been processed yet.</p>
  <?php else: ?>
  <table class="mcqg-report-table w-100">
    <thead>
      <tr>
        <th>Rank</th><th>Team</th><th>Units Sold</th><th>Closing Inventory</th>
        <th>Capacity Util %</th><th>Revenue</th><th>Cost of Sale</th><th>Operating Profit</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($results as $i => $r):
        $costOfSale = $r['variable_cost'] + $r['fixed_cost'];
        $rankClass = $i === 0 ? 'rank-1' : '';
        $profitClass = $r['operating_profit'] >= 0 ? 'mcqg-profit-positive' : 'mcqg-profit-negative';
      ?>
      <tr class="<?php echo $rankClass; ?>">
        <td><?php echo $i + 1; ?></td>
        <td class="fw-bold"><?php echo htmlspecialchars($r['team_name']); ?></td>
        <td><?php echo number_format($r['units_sold']); ?></td>
        <td><?php echo number_format($r['closing_inventory']); ?></td>
        <td><?php echo number_format($r['capacity_utilization_percent'], 1); ?>%</td>
        <td>&#8377;<?php echo number_format($r['revenue'], 2); ?></td>
        <td>&#8377;<?php echo number_format($costOfSale, 2); ?></td>
        <td class="<?php echo $profitClass; ?>">&#8377;<?php echo number_format($r['operating_profit'], 2); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="small text-muted mt-2">Note: "Service Level" is not shown here - its exact calculation formula was not
     defined in the source requirement documents and has been flagged for confirmation.</p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
