<?php
/**
 * MCQG Player - Cumulative Standing
 * Path: player/results/cumulative_standing.php
 * Source: Doc 1 "Winning the Competition" - shows every team's
 * cumulative profit and rank, from the player's point of view.
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireTeam();

$db = Database::getInstance();
$teamId = Session::currentTeamId();
$gameId = Session::activeGameId();

$teams = $db->fetchAll("SELECT team_id, team_name FROM team_master WHERE game_id = :g", ['g' => $gameId]);
$years = $db->fetchAll("SELECT DISTINCT year_no FROM team_result WHERE game_id = :g ORDER BY year_no", ['g' => $gameId]);

$standings = [];
$chartTeams = [];
foreach ($teams as $t) {
    $rows = $db->fetchAll("SELECT year_no, operating_profit FROM team_result WHERE team_id = :t ORDER BY year_no", ['t' => $t['team_id']]);
    $cumulative = array_sum(array_column($rows, 'operating_profit'));
    $standings[] = ['team_id' => $t['team_id'], 'team_name' => $t['team_name'], 'cumulative_profit' => $cumulative];

    $byYear = array_column($rows, 'operating_profit', 'year_no');
    $runningSum = 0;
    $profitTrend = [];
    foreach ($years as $y) {
        $runningSum += (float) ($byYear[$y['year_no']] ?? 0);
        $profitTrend[] = $runningSum;
    }
    $chartTeams[] = ['team_name' => $t['team_name'], 'profit_by_year' => $profitTrend];
}
usort($standings, fn($a, $b) => $b['cumulative_profit'] <=> $a['cumulative_profit']);

$myRank = null;
foreach ($standings as $i => $s) { if ($s['team_id'] == $teamId) { $myRank = $i + 1; break; } }

$pageTitle = 'Standings';
require_once __DIR__ . '/../includes/player_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/player_sidebar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-card-header"><h2>Cumulative Standings</h2></div>

  <?php if ($myRank): ?>
  <div class="mcqg-round-banner mb-4">
    <div><h3>You're currently ranked #<?php echo $myRank; ?></h3><p>out of <?php echo count($teams); ?> teams</p></div>
    <span class="mcqg-rank-badge <?php echo $myRank === 1 ? 'gold' : ($myRank === 2 ? 'silver' : ($myRank === 3 ? 'bronze' : '')); ?>" style="width:44px;height:44px;font-size:18px;"><?php echo $myRank; ?></span>
  </div>
  <?php endif; ?>

  <table class="mcqg-report-table w-100 mb-4">
    <thead><tr><th>Rank</th><th>Team</th><th>Cumulative Profit</th></tr></thead>
    <tbody>
      <?php foreach ($standings as $i => $s): $isMe = $s['team_id'] == $teamId; ?>
      <tr class="<?php echo $isMe ? 'own-team' : ''; ?>">
        <td><span class="mcqg-rank-badge <?php echo $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : '')); ?>"><?php echo $i + 1; ?></span></td>
        <td><?php echo htmlspecialchars($s['team_name']); ?><?php echo $isMe ? ' (You)' : ''; ?></td>
        <td class="<?php echo $s['cumulative_profit'] >= 0 ? 'mcqg-profit-positive' : 'mcqg-profit-negative'; ?>">&#8377;<?php echo number_format($s['cumulative_profit'], 2); ?></td>
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
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    function renderPlayerProfitChart() {
      const ctx = document.getElementById('mcqg-cumulative-profit-chart');
      const dataEl = document.getElementById('cumulative-profit-data');
      if (!ctx || !dataEl || typeof Chart === 'undefined') return;
      if (ctx.getAttribute('data-chart-rendered')) return;
      ctx.setAttribute('data-chart-rendered', 'true');

      const data = JSON.parse(dataEl.textContent);
      const colors = ['#1e2761', '#c99a2e', '#1f9d55', '#e74c3c', '#8e44ad', '#3498db', '#e67e22', '#1abc9c'];
      const labels = (data.years && data.years.length > 0) ? data.years : ['Year 1'];

      new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: (data.teams || []).map((t, i) => ({
            label: t.team_name,
            data: t.profit_by_year,
            borderColor: colors[i % colors.length],
            backgroundColor: colors[i % colors.length],
            borderWidth: 3,
            tension: 0.35,
            fill: false,
            pointRadius: 5,
            pointHoverRadius: 7
          }))
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom' },
            tooltip: {
              callbacks: {
                label: function(context) {
                  let label = context.dataset.label || '';
                  if (label) label += ': ';
                  if (context.parsed.y !== null) {
                    label += '₹' + Number(context.parsed.y).toLocaleString();
                  }
                  return label;
                }
              }
            }
          },
          scales: {
            y: {
              ticks: {
                callback: (v) => '₹' + Number(v).toLocaleString()
              }
            }
          }
        }
      });
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', renderPlayerProfitChart);
    } else {
      renderPlayerProfitChart();
    }
  </script>
  <div class="d-flex justify-content-between align-items-center mt-3">
    <a href="<?php echo PLAYER_URL; ?>landing.php" class="btn btn-mcqg-outline">&larr; Back to Home</a>
    <a href="competitive_analysis.php" class="btn btn-mcqg-gold">View Competitive Team Analysis &rarr;</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/player_footer.php'; ?>
