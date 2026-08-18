<?php
/**
 * MCQG Admin - Reports: Cumulative Report
 * Path: admin/reports/cumulative_report.php
 * Source: Doc 1 "Winning the Competition" - ranks teams by
 * cumulative profit (or other configured winning basis).
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$games = $db->fetchAll("SELECT game_id, game_name FROM game_master ORDER BY created_on DESC");
$gameId = (int) ($_GET['game_id'] ?? ($games[0]['game_id'] ?? 0));

$teams = $db->fetchAll("SELECT team_id, team_name FROM team_master WHERE game_id = :g", ['g' => $gameId]);
$years = $db->fetchAll("SELECT DISTINCT year_no FROM team_result WHERE game_id = :g ORDER BY year_no", ['g' => $gameId]);

$standings = [];
$chartTeams = [];
foreach ($teams as $t) {
    $rows = $db->fetchAll(
        "SELECT year_no, operating_profit FROM team_result WHERE team_id = :t ORDER BY year_no",
        ['t' => $t['team_id']]
    );
    $cumulative = array_sum(array_column($rows, 'operating_profit'));
    $standings[] = ['team_name' => $t['team_name'], 'cumulative_profit' => $cumulative];

    $byYear = array_column($rows, 'operating_profit', 'year_no');
    $runningSum = 0;
    $profitTrend = [];
    foreach ($years as $y) {
        $yNo = $y['year_no'];
        $runningSum += (float) ($byYear[$yNo] ?? 0);
        $profitTrend[] = $runningSum;
    }

    $chartTeams[] = [
        'team_name' => $t['team_name'],
        'profit_by_year' => $profitTrend,
    ];
}
usort($standings, fn($a, $b) => $b['cumulative_profit'] <=> $a['cumulative_profit']);

$pageTitle = 'Cumulative Report';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-card-header">
    <h2>Cumulative Standings</h2>
    <form method="GET">
      <select name="game_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($games as $g): ?>
          <option value="<?php echo $g['game_id']; ?>" <?php echo $g['game_id'] == $gameId ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['game_name']); ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <table class="mcqg-report-table w-100 mb-4">
    <thead><tr><th>Rank</th><th>Team</th><th>Cumulative Profit</th></tr></thead>
    <tbody>
      <?php foreach ($standings as $i => $s):
        $badgeClass = $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : ''));
        $profitClass = $s['cumulative_profit'] >= 0 ? 'mcqg-profit-positive' : 'mcqg-profit-negative';
      ?>
      <tr class="<?php echo $i === 0 ? 'rank-1' : ''; ?>">
        <td><span class="mcqg-rank-badge <?php echo $badgeClass; ?>"><?php echo $i + 1; ?></span></td>
        <td class="fw-bold"><?php echo htmlspecialchars($s['team_name']); ?></td>
        <td class="<?php echo $profitClass; ?>">&#8377;<?php echo number_format($s['cumulative_profit'], 2); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="mcqg-card-header"><h3>Profit Trend</h3></div>
  <div class="mcqg-chart-card" style="position: relative; height: 350px; width: 100%;">
    <canvas id="mcqg-cumulative-profit-chart"></canvas>
  </div>
  <script type="application/json" id="cumulative-profit-data">
    <?php echo json_encode(['years' => array_map(fn($y) => 'Year ' . $y['year_no'], $years), 'teams' => $chartTeams]); ?>
  </script>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
