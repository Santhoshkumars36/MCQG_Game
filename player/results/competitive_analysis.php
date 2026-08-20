<?php
/**
 * MCQG Player - Screen 7: Competitive Team Analysis
 * Path: player/results/competitive_analysis.php
 * Source: Slide 6 (Right) - Multi-tab competitive comparison dashboard showing
 * investment comparison tables, investment share %, driver positions, and position snapshot.
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

$processedYears = $db->fetchAll(
    "SELECT DISTINCT year_no FROM team_result WHERE game_id = :g ORDER BY year_no DESC",
    ['g' => $gameId]
);
$yearNo = (int) ($_GET['year_no'] ?? ($processedYears[0]['year_no'] ?? 1));

// Competing Teams in this game
$competingTeams = $db->fetchAll(
    "SELECT team_id, team_name FROM team_master WHERE game_id = :g ORDER BY team_id ASC",
    ['g' => $gameId]
);

// All team results for position rankings
$results = $db->fetchAll(
    "SELECT r.*, t.team_name, d.selling_price
     FROM team_result r
     INNER JOIN team_master t ON t.team_id = r.team_id
     LEFT JOIN team_decision d ON d.team_id = r.team_id AND d.year_no = r.year_no
     WHERE r.game_id = :g AND r.year_no = :y",
    ['g' => $gameId, 'y' => $yearNo]
);

// Capacity Investments comparison across teams
$capacityInvestments = $db->fetchAll(
    "SELECT DISTINCT im.* FROM investment_master im
     INNER JOIN investment_effect ie ON ie.investment_id = im.investment_id
     WHERE im.game_id = :g AND ie.driver_type = 'Capacity' AND im.active = 1
     ORDER BY im.display_order ASC",
    ['g' => $gameId]
);

// Investment matrix: [investment_id][team_id] => invested_value
$invMatrix = [];
$teamTotalInvest = [];
foreach ($competingTeams as $ct) {
    $teamTotalInvest[$ct['team_id']] = 0;
}

$selections = $db->fetchAll(
    "SELECT td.team_id, tis.investment_id, tis.invested_value
     FROM team_investment_selection tis
     INNER JOIN team_decision td ON td.decision_id = tis.decision_id
     WHERE td.game_id = :g AND td.year_no = :y",
    ['g' => $gameId, 'y' => $yearNo]
);

foreach ($selections as $sel) {
    $tid = $sel['team_id'];
    $iid = $sel['investment_id'];
    $val = (float) $sel['invested_value'];
    $invMatrix[$iid][$tid] = $val;
    $teamTotalInvest[$tid] = ($teamTotalInvest[$tid] ?? 0) + $val;
}

// Calculate team rankings
$totalTeamsCount = count($competingTeams);

usort($results, fn($a, $b) => $b['units_sold'] <=> $a['units_sold']);
$unitsSoldRank = 1;
foreach ($results as $idx => $r) { if ($r['team_id'] == $teamId) { $unitsSoldRank = $idx + 1; break; } }

usort($results, fn($a, $b) => $b['operating_profit'] <=> $a['operating_profit']);
$profitRank = 1;
foreach ($results as $idx => $r) { if ($r['team_id'] == $teamId) { $profitRank = $idx + 1; break; } }

usort($results, fn($a, $b) => $b['cash_position'] <=> $a['cash_position']);
$carryForwardRank = 1;
foreach ($results as $idx => $r) { if ($r['team_id'] == $teamId) { $carryForwardRank = $idx + 1; break; } }

$currency = DEFAULT_CURRENCY_SYMBOL;
$pageTitle = 'Competitive Team Analysis';

require_once __DIR__ . '/../includes/command_header.php';
?>

<div class="cmd-container">
  
  <!-- Header Banner -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 class="fw-bold fs-3 text-dark mb-1">Competitive Team Analysis</h1>
      <div class="text-muted small">
        Round <?php echo (int) $yearNo; ?> | Compare investment choices, competitive shares and resulting driver positions across teams.
      </div>
    </div>
    <div class="d-flex align-items-center gap-3">
      <form method="GET" class="d-flex align-items-center gap-2">
        <label class="fw-bold small text-muted">Round:</label>
        <select name="year_no" class="form-select form-select-sm" onchange="this.form.submit()" style="width:120px;">
          <?php foreach ($processedYears as $py): ?>
            <option value="<?php echo $py['year_no']; ?>" <?php echo $py['year_no'] == $yearNo ? 'selected' : ''; ?>>Year <?php echo $py['year_no']; ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <span class="badge bg-primary px-3 py-2 fs-6">Your Team: <?php echo htmlspecialchars($team['team_name']); ?></span>
    </div>
  </div>

  <!-- Multi-Tab Navigation Bar -->
  <ul class="nav nav-pills cmd-card p-2 gap-2 mb-4 bg-white border" id="compTab" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active fw-bold px-3 py-2" id="cap-inv-tab" data-bs-toggle="tab" data-bs-target="#cap-inv" type="button" role="tab">
        <i class="fa-solid fa-industry me-1"></i> Capacity Investments
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-bold px-3 py-2" id="cap-drv-tab" data-bs-toggle="tab" data-bs-target="#cap-drv" type="button" role="tab">
        <i class="fa-solid fa-sliders me-1"></i> Capacity Drivers
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-bold px-3 py-2" id="dem-inv-tab" data-bs-toggle="tab" data-bs-target="#dem-inv" type="button" role="tab">
        <i class="fa-solid fa-bullhorn me-1"></i> Demand Investments
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-bold px-3 py-2" id="dem-drv-tab" data-bs-toggle="tab" data-bs-target="#dem-drv" type="button" role="tab">
        <i class="fa-solid fa-chart-line me-1"></i> Demand Drivers
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link fw-bold px-3 py-2" id="mkt-pos-tab" data-bs-toggle="tab" data-bs-target="#mkt-pos" type="button" role="tab">
        <i class="fa-solid fa-trophy me-1"></i> Market Position
      </button>
    </li>
  </ul>

  <!-- Tab Content Panels -->
  <div class="tab-content" id="compTabContent">

    <!-- ── Tab 1: Capacity Investments ── -->
    <div class="tab-pane fade show active" id="cap-inv" role="tabpanel">
      
      <!-- Section 1: Capacity Investment Comparison Table -->
      <div class="cmd-card mb-4">
        <div class="cmd-card-header">
          <h3 class="cmd-card-title"><i class="fa-solid fa-table me-2 text-primary"></i> 1. Capacity Investment Comparison</h3>
        </div>
        <div class="cmd-card-body p-0">
          <table class="cmd-table">
            <thead>
              <tr>
                <th>Investment Type</th>
                <?php foreach ($competingTeams as $ct): $isMe = $ct['team_id'] == $teamId; ?>
                  <th class="<?php echo $isMe ? 'highlight-col text-center' : 'text-center'; ?>">
                    <?php echo htmlspecialchars($ct['team_name']); ?>
                    <?php if ($isMe): ?><div class="small" style="color:#fbbf24; font-size:10px;">YOUR TEAM</div><?php endif; ?>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($capacityInvestments as $inv): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($inv['investment_name']); ?></strong></td>
                <?php foreach ($competingTeams as $ct): $isMe = $ct['team_id'] == $teamId; $v = $invMatrix[$inv['investment_id']][$ct['team_id']] ?? 0; ?>
                  <td class="<?php echo $isMe ? 'highlight-col text-center font-monospace' : 'text-center font-monospace'; ?>">
                    <?php echo $currency . number_format($v, 0); ?>
                  </td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr class="table-light fw-bold">
                <td>Total Investment</td>
                <?php foreach ($competingTeams as $ct): $isMe = $ct['team_id'] == $teamId; $tot = $teamTotalInvest[$ct['team_id']] ?? 0; ?>
                  <td class="<?php echo $isMe ? 'highlight-col text-center font-monospace text-warning' : 'text-center font-monospace text-primary'; ?>">
                    <?php echo $currency . number_format($tot, 0); ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Section 2: Capacity Investment Share Table -->
      <div class="cmd-card mb-4">
        <div class="cmd-card-header">
          <h3 class="cmd-card-title"><i class="fa-solid fa-pie-chart me-2 text-primary"></i> 2. Capacity Investment Share</h3>
        </div>
        <div class="cmd-card-body p-0">
          <table class="cmd-table">
            <thead>
              <tr>
                <th>Capacity Driver</th>
                <th class="text-center">My Investment</th>
                <?php foreach ($competingTeams as $ct): $isMe = $ct['team_id'] == $teamId; ?>
                  <th class="<?php echo $isMe ? 'highlight-col text-center' : 'text-center'; ?>">
                    <?php echo htmlspecialchars($ct['team_name']); ?> Share %
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($capacityInvestments as $inv): 
                $myVal = $invMatrix[$inv['investment_id']][$teamId] ?? 0;
                $sumAll = array_sum($invMatrix[$inv['investment_id']] ?? [1]);
              ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($inv['investment_name']); ?></strong></td>
                <td class="text-center font-monospace fw-bold text-primary"><?php echo $currency . number_format($myVal, 0); ?></td>
                <?php foreach ($competingTeams as $ct): $isMe = $ct['team_id'] == $teamId; 
                  $tv = $invMatrix[$inv['investment_id']][$ct['team_id']] ?? 0;
                  $pct = $sumAll > 0 ? ($tv / $sumAll) * 100 : (100 / max(count($competingTeams), 1));
                ?>
                  <td class="<?php echo $isMe ? 'highlight-col text-center font-monospace fw-bold' : 'text-center font-monospace'; ?>">
                    <?php echo number_format($pct, 1); ?>%
                  </td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Section 3: Your Position Snapshot Card -->
      <div class="cmd-card">
        <div class="cmd-card-header">
          <h3 class="cmd-card-title"><i class="fa-solid fa-ranking-star me-2 text-primary"></i> Your Position Snapshot</h3>
        </div>
        <div class="cmd-card-body">
          <div class="row g-3">
            <div class="col-md-3">
              <div class="cmd-metric-box text-center">
                <div class="cmd-metric-label">Cost / Unit</div>
                <div class="cmd-metric-value text-primary">2nd of <?php echo $totalTeamsCount; ?></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="cmd-metric-box text-center">
                <div class="cmd-metric-label">Units Sold</div>
                <div class="cmd-metric-value text-success"><?php echo $unitsSoldRank; ?><?php echo $unitsSoldRank == 1 ? 'st' : ($unitsSoldRank == 2 ? 'nd' : 'rd'); ?> of <?php echo $totalTeamsCount; ?></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="cmd-metric-box text-center">
                <div class="cmd-metric-label">Profit</div>
                <div class="cmd-metric-value text-primary"><?php echo $profitRank; ?><?php echo $profitRank == 1 ? 'st' : ($profitRank == 2 ? 'nd' : 'rd'); ?> of <?php echo $totalTeamsCount; ?></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="cmd-metric-box text-center">
                <div class="cmd-metric-label">Carry Forward Fund</div>
                <div class="cmd-metric-value text-primary"><?php echo $carryForwardRank; ?><?php echo $carryForwardRank == 1 ? 'st' : ($carryForwardRank == 2 ? 'nd' : 'rd'); ?> of <?php echo $totalTeamsCount; ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- ── Tab 2: Capacity Drivers ── -->
    <div class="tab-pane fade" id="cap-drv" role="tabpanel">
      <div class="cmd-card">
        <div class="cmd-card-header">
          <h3 class="cmd-card-title"><i class="fa-solid fa-sliders me-2 text-primary"></i> Capacity Cost Drivers Overview</h3>
        </div>
        <div class="cmd-card-body p-0">
          <table class="cmd-table">
            <thead>
              <tr>
                <th>Driver Name</th>
                <th>Group</th>
                <th>Cost Share %</th>
                <th>Base Cost Value</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $capDrvs = $db->fetchAll("SELECT * FROM capacity_driver WHERE game_id = :g", ['g' => $gameId]);
              foreach ($capDrvs as $cd): 
                $cv = (float)$game['capacity_cost'] * ((float)$cd['cost_share_percent'] / 100);
              ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($cd['driver_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($cd['group_name']); ?></td>
                <td><?php echo number_format((float)$cd['cost_share_percent'], 1); ?>%</td>
                <td class="font-monospace"><?php echo $currency . number_format($cv, 2); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ── Tab 3: Demand Investments ── -->
    <div class="tab-pane fade" id="dem-inv" role="tabpanel">
      <div class="cmd-card">
        <div class="cmd-card-header">
          <h3 class="cmd-card-title"><i class="fa-solid fa-bullhorn me-2 text-primary"></i> Demand Investment Comparison</h3>
        </div>
        <div class="cmd-card-body p-0">
          <table class="cmd-table">
            <thead>
              <tr>
                <th>Investment Item</th>
                <?php foreach ($competingTeams as $ct): $isMe = $ct['team_id'] == $teamId; ?>
                  <th class="<?php echo $isMe ? 'highlight-col text-center' : 'text-center'; ?>"><?php echo htmlspecialchars($ct['team_name']); ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php 
              $demInvs = $db->fetchAll("SELECT DISTINCT im.* FROM investment_master im INNER JOIN investment_effect ie ON ie.investment_id = im.investment_id WHERE im.game_id = :g AND ie.driver_type = 'Demand'", ['g' => $gameId]);
              foreach ($demInvs as $di):
              ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($di['investment_name']); ?></strong></td>
                <?php foreach ($competingTeams as $ct): $isMe = $ct['team_id'] == $teamId; $v = $invMatrix[$di['investment_id']][$ct['team_id']] ?? 0; ?>
                  <td class="<?php echo $isMe ? 'highlight-col text-center font-monospace' : 'text-center font-monospace'; ?>"><?php echo $currency . number_format($v, 0); ?></td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ── Tab 4: Demand Drivers ── -->
    <div class="tab-pane fade" id="dem-drv" role="tabpanel">
      <div class="cmd-card">
        <div class="cmd-card-header">
          <h3 class="cmd-card-title"><i class="fa-solid fa-chart-line me-2 text-primary"></i> Demand Drivers Share</h3>
        </div>
        <div class="cmd-card-body p-0">
          <table class="cmd-table">
            <thead>
              <tr>
                <th>Demand Driver Name</th>
                <th>Group</th>
                <th>Demand Share %</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $demDrvs = $db->fetchAll("SELECT * FROM demand_driver WHERE game_id = :g", ['g' => $gameId]);
              foreach ($demDrvs as $dd):
              ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($dd['driver_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($dd['group_name']); ?></td>
                <td class="font-monospace fw-bold"><?php echo number_format((float)$dd['demand_share_percent'], 1); ?>%</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ── Tab 5: Market Position ── -->
    <div class="tab-pane fade" id="mkt-pos" role="tabpanel">
      <div class="cmd-card">
        <div class="cmd-card-header">
          <h3 class="cmd-card-title"><i class="fa-solid fa-trophy me-2 text-primary"></i> Market Standings &amp; Performance</h3>
        </div>
        <div class="cmd-card-body p-0">
          <table class="cmd-table">
            <thead>
              <tr>
                <th>Rank</th>
                <th>Team Name</th>
                <th>Units Sold</th>
                <th>Operating Profit</th>
                <th>Carry Forward Fund</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($results as $idx => $r): $isMe = $r['team_id'] == $teamId; ?>
              <tr class="<?php echo $isMe ? 'highlight-own' : ''; ?>">
                <td><strong>#<?php echo $idx + 1; ?></strong></td>
                <td><?php echo htmlspecialchars($r['team_name']); ?> <?php if ($isMe): ?><span class="badge bg-primary ms-1">You</span><?php endif; ?></td>
                <td class="font-monospace fw-bold"><?php echo number_format((int)$r['units_sold']); ?></td>
                <td class="font-monospace fw-bold <?php echo (float)$r['operating_profit'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $currency . number_format((float)$r['operating_profit'], 2); ?></td>
                <td class="font-monospace fw-bold text-primary"><?php echo $currency . number_format((float)$r['cash_position'], 2); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

</div>

<!-- Bottom Navigation Bar -->
<div class="cmd-bottom-bar">
  <a href="<?php echo PLAYER_URL; ?>results/round_results.php?year_no=<?php echo $yearNo; ?>" class="cmd-btn-secondary">
    <i class="fa-solid fa-arrow-left"></i> Back to Results Summary
  </a>
  <a href="<?php echo PLAYER_URL; ?>landing.php" class="cmd-btn-primary">
    <i class="fa-solid fa-house me-1"></i> Return to Home Dashboard
  </a>
</div>

<?php require_once __DIR__ . '/../includes/command_footer.php'; ?>
