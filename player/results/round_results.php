<?php
/**
 * MCQG Player - Screen 6: Round Results & Analysis
 * Path: player/results/round_results.php
 * Source: Slide 6 (Left) - Detailed post-processing results dashboard with
 * top metric cards, financial results, capacity cost analysis, demand driver analysis,
 * allocation waterfall breakdown, and team performance comparison table.
 */
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../engine/cost/calculate_capacity_cost.php';
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

// My result & decision
$myResult = $db->fetchOne(
    "SELECT * FROM team_result WHERE team_id = :t AND year_no = :y AND game_id = :g",
    ['t' => $teamId, 'y' => $yearNo, 'g' => $gameId]
);
$myDecision = $db->fetchOne(
    "SELECT * FROM team_decision WHERE team_id = :t AND year_no = :y AND game_id = :g",
    ['t' => $teamId, 'y' => $yearNo, 'g' => $gameId]
);

// All team results for this round
$allResults = $db->fetchAll(
    "SELECT r.*, t.team_name, d.selling_price, d.production_qty, d.capacity_built
     FROM team_result r
     INNER JOIN team_master t ON t.team_id = r.team_id
     LEFT JOIN team_decision d ON d.team_id = r.team_id AND d.year_no = r.year_no
     WHERE r.game_id = :g AND r.year_no = :y
     ORDER BY r.operating_profit DESC",
    ['g' => $gameId, 'y' => $yearNo]
);

// Capacity Cost Drivers Analysis
$capacityDrivers = $db->fetchAll("SELECT * FROM capacity_driver WHERE game_id = :g ORDER BY driver_name", ['g' => $gameId]);
$baseCosts = calculateCapacityDriverCosts((float) $game['capacity_cost'], $capacityDrivers);
$totalCapacityCost = array_sum(array_column($baseCosts, 'cost_value'));

// Load capacity investments made by this team
$capInvestments = [];
if ($myDecision) {
    $rows = $db->fetchAll(
        "SELECT tis.invested_value, ie.driver_id, ie.min_percent, ie.max_percent
         FROM team_investment_selection tis
         INNER JOIN investment_effect ie ON ie.investment_id = tis.investment_id
         WHERE tis.decision_id = :d AND ie.driver_type = 'Capacity'",
        ['d' => $myDecision['decision_id']]
    );
    foreach ($rows as $r) { $capInvestments[$r['driver_id']] = (float) $r['invested_value']; }
}

// Demand Drivers Analysis & Share calculation
$demandDrivers = $db->fetchAll("SELECT * FROM demand_driver WHERE game_id = :g ORDER BY driver_name", ['g' => $gameId]);
$demandDriverAnalysis = [];

// Total demand investments across all teams
$totalGameDemandInvest = (float) $db->fetchColumn(
    "SELECT SUM(tis.invested_value) 
     FROM team_investment_selection tis
     INNER JOIN team_decision td ON td.decision_id = tis.decision_id
     INNER JOIN investment_effect ie ON ie.investment_id = tis.investment_id
     WHERE td.game_id = :g AND td.year_no = :y AND ie.driver_type = 'Demand'",
    ['g' => $gameId, 'y' => $yearNo]
) ?: 1;

foreach ($demandDrivers as $dd) {
    $myInvest = 0;
    if ($myDecision) {
        $myInvest = (float) $db->fetchColumn(
            "SELECT SUM(tis.invested_value)
             FROM team_investment_selection tis
             INNER JOIN investment_effect ie ON ie.investment_id = tis.investment_id
             WHERE tis.decision_id = :d AND ie.driver_id = :did AND ie.driver_type = 'Demand'",
            ['d' => $myDecision['decision_id'], 'did' => $dd['driver_id']]
        ) ?: 0;
    }
    $teamShare = ($myInvest / max($totalGameDemandInvest, 1)) * 100;
    $impactLabel = $teamShare > 30 ? 'Very High' : ($teamShare > 15 ? 'High' : ($teamShare > 0 ? 'Medium' : 'Low'));
    $demandDriverAnalysis[] = [
        'driver_name' => $dd['driver_name'],
        'investment' => $myInvest,
        'team_share' => $teamShare,
        'result_impact' => $impactLabel,
    ];
}

// Demand Waterfall Allocation Breakdown estimates
$totalDemand = (int) $db->fetchColumn("SELECT market_demand FROM game_market_year WHERE game_id = :g AND year_no = :y", ['g' => $gameId, 'y' => $yearNo]) ?: 10000;
$allocRule = $db->fetchOne("SELECT * FROM game_demand_allocation WHERE game_id = :g AND year_no = :y", ['g' => $gameId, 'y' => $yearNo]);

$minPct = (float) ($allocRule['min_percent'] ?? 20);
$ddPct  = (float) ($allocRule['demand_driver_percent'] ?? 40);

$myUnitsSold = $myResult ? (int) $myResult['units_sold'] : 0;
$minMarketAlloc = min((int) round(($totalDemand * ($minPct / 100)) / max(count($allResults), 1)), $myUnitsSold);
$remainingSold = $myUnitsSold - $minMarketAlloc;
$ddAlloc = min((int) round($remainingSold * 0.5), $remainingSold);
$priceAlloc = $remainingSold - $ddAlloc;

// Financial details
$openingFund = $yearNo > 1 ? (float) $db->fetchColumn("SELECT cash_position FROM team_result WHERE team_id = :t AND year_no = :y AND game_id = :g", ['t' => $teamId, 'y' => $yearNo - 1, 'g' => $gameId]) : (float) $team['opening_budget'];
$periodProfit = $myResult ? (float) $myResult['operating_profit'] : 0;

$modAdjRow = $db->fetchOne(
    "SELECT SUM(amount) AS total FROM moderator_adjustment WHERE team_id = :t AND game_id = :g AND year_no = :y",
    ['t' => $teamId, 'g' => $gameId, 'y' => $yearNo]
);
$moderatorAdjustment = (float)($modAdjRow['total'] ?? 0);

$carryForwardFund = $myResult ? (float) $myResult['cash_position'] : ($openingFund + $periodProfit + $moderatorAdjustment);
$closingInventory = $myResult ? (int) $myResult['closing_inventory'] : 0;
$unitCost = $myDecision && (int)$myDecision['production_qty'] > 0 ? $totalCapacityCost / (int)$myDecision['production_qty'] : ($game['unit_cost'] > 0 ? $game['unit_cost'] : 82.40);
$closingInventoryValue = $closingInventory * $unitCost;

$currency = DEFAULT_CURRENCY_SYMBOL;
$pageTitle = 'Round Results & Analysis';

require_once __DIR__ . '/../includes/command_header.php';
?>

<div class="cmd-container">
  
  <!-- Header Banner -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 class="fw-bold fs-3 text-dark mb-1">Round Results &amp; Analysis</h1>
      <div class="text-muted small">
        Round <?php echo (int) $yearNo; ?> | Results Processed
      </div>
    </div>
    <div class="d-flex align-items-center gap-3">
      <form method="GET" class="d-flex align-items-center gap-2">
        <label class="fw-bold small text-muted">Select Round:</label>
        <select name="year_no" class="form-select form-select-sm" onchange="this.form.submit()" style="width:120px;">
          <?php foreach ($processedYears as $py): ?>
            <option value="<?php echo $py['year_no']; ?>" <?php echo $py['year_no'] == $yearNo ? 'selected' : ''; ?>>Year <?php echo $py['year_no']; ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <span class="badge bg-primary px-3 py-2 fs-6">Your Team: <?php echo htmlspecialchars($team['team_name']); ?></span>
    </div>
  </div>

  <!-- Top Metrics Ribbon -->
  <div class="row g-3 mb-4">
    <div class="col-md-2.4" style="flex:0 0 20%;">
      <div class="cmd-metric-box">
        <div class="cmd-metric-label">Units Sold</div>
        <div class="cmd-metric-value"><?php echo number_format($myUnitsSold); ?></div>
      </div>
    </div>
    <div class="col-md-2.4" style="flex:0 0 20%;">
      <div class="cmd-metric-box">
        <div class="cmd-metric-label">Profit This Period</div>
        <div class="cmd-metric-value <?php echo $periodProfit >= 0 ? 'green' : 'red'; ?>">
          <?php echo ($periodProfit >= 0 ? '+' : '') . $currency . number_format($periodProfit, 2); ?>
        </div>
      </div>
    </div>
    <div class="col-md-2.4" style="flex:0 0 20%;">
      <div class="cmd-metric-box">
        <div class="cmd-metric-label">Cost / Unit</div>
        <div class="cmd-metric-value"><?php echo $currency . number_format($unitCost, 2); ?></div>
      </div>
    </div>
    <div class="col-md-2.4" style="flex:0 0 20%;">
      <div class="cmd-metric-box">
        <div class="cmd-metric-label">Closing Inventory</div>
        <div class="cmd-metric-value"><?php echo number_format($closingInventory); ?> Units</div>
      </div>
    </div>
    <div class="col-md-2.4" style="flex:0 0 20%;">
      <div class="cmd-metric-box">
        <div class="cmd-metric-label">Carry Forward Fund</div>
        <div class="cmd-metric-value green"><?php echo $currency . number_format($carryForwardFund, 2); ?></div>
      </div>
    </div>
  </div>

  <!-- Main Grid Dashboard Cards -->
  <div class="row g-4 mb-4">
    
    <!-- Financial Result Card -->
    <div class="col-md-6">
      <div class="cmd-card h-100">
        <div class="cmd-card-header">
          <h3 class="cmd-card-title"><i class="fa-solid fa-receipt text-primary"></i> Financial Result</h3>
        </div>
        <div class="cmd-card-body p-0">
          <table class="cmd-table">
            <tbody>
              <tr><td>Opening Fund</td><td class="text-end font-monospace"><?php echo $currency . number_format($openingFund, 2); ?></td></tr>
              <tr><td>Profit from Period</td><td class="text-end font-monospace <?php echo $periodProfit >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold'; ?>"><?php echo ($periodProfit >= 0 ? '+' : '') . $currency . number_format($periodProfit, 2); ?></td></tr>
              <?php if ($moderatorAdjustment != 0): ?>
              <tr class="table-warning"><td><i class="fa-solid fa-sliders me-1"></i> Moderator Adjustment</td><td class="text-end font-monospace <?php echo $moderatorAdjustment >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold'; ?>"><?php echo ($moderatorAdjustment >= 0 ? '+' : '') . $currency . number_format($moderatorAdjustment, 2); ?></td></tr>
              <?php endif; ?>
              <tr class="table-light"><td class="fw-bold">Total Carry Forward Fund</td><td class="text-end font-monospace fw-bold text-primary"><?php echo $currency . number_format($carryForwardFund, 2); ?></td></tr>
              <tr><td>Closing Inventory</td><td class="text-end font-monospace"><?php echo number_format($closingInventory); ?> Units</td></tr>
              <tr><td>Closing Inventory Value</td><td class="text-end font-monospace"><?php echo $currency . number_format($closingInventoryValue, 2); ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Next Round Position Card -->
    <div class="col-md-6">
      <div class="cmd-card h-100">
        <div class="cmd-card-header">
          <h3 class="cmd-card-title"><i class="fa-solid fa-forward text-primary"></i> Next Round Position</h3>
        </div>
        <div class="cmd-card-body p-0">
          <table class="cmd-table">
            <tbody>
              <tr><td>Available Fund</td><td class="text-end font-monospace fw-bold text-success"><?php echo $currency . number_format($carryForwardFund, 2); ?></td></tr>
              <tr><td>Current Unit Cost</td><td class="text-end font-monospace"><?php echo $currency . number_format($unitCost, 2); ?></td></tr>
              <tr><td>Closing Inventory Units</td><td class="text-end font-monospace"><?php echo number_format($closingInventory); ?> Units</td></tr>
              <tr><td>Inventory Value</td><td class="text-end font-monospace"><?php echo $currency . number_format($closingInventoryValue, 2); ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Capacity Cost Analysis Table Card -->
    <div class="col-md-7">
      <div class="cmd-card">
        <div class="cmd-card-header">
          <h3 class="cmd-card-title"><i class="fa-solid fa-calculator text-primary"></i> Capacity Cost Analysis</h3>
        </div>
        <div class="cmd-card-body p-0">
          <table class="cmd-table">
            <thead>
              <tr>
                <th>Cost Driver</th>
                <th>Opening %</th>
                <th>Invest Impact</th>
                <th>Resulting %</th>
                <th>Cost Value</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($capacityDrivers as $cd): 
                $opPct = (float) $cd['cost_share_percent'];
                $invImp = isset($capInvestments[$cd['driver_id']]) ? -2.5 : 0.0;
                $resPct = max($opPct + $invImp, 0);
                $cVal = (float) $game['capacity_cost'] * ($resPct / 100);
              ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($cd['driver_name']); ?></strong></td>
                <td><?php echo number_format($opPct, 1); ?>%</td>
                <td class="<?php echo $invImp < 0 ? 'text-success' : ''; ?>"><?php echo number_format($invImp, 1); ?>%</td>
                <td><strong><?php echo number_format($resPct, 1); ?>%</strong></td>
                <td class="font-monospace"><?php echo $currency . number_format($cVal, 2); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr class="table-light fw-bold">
                <td colspan="4">Final Cost of Capacity: <?php echo $currency . number_format($totalCapacityCost, 2); ?></td>
                <td class="text-primary">Final Cost per Unit: <?php echo $currency . number_format($unitCost, 2); ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- Demand Driver Analysis & Allocation Breakdown -->
    <div class="col-md-5">
      
      <!-- Demand Driver Analysis -->
      <div class="cmd-card mb-4">
        <div class="cmd-card-header">
          <h3 class="cmd-card-title"><i class="fa-solid fa-chart-column text-primary"></i> Demand Driver Analysis</h3>
        </div>
        <div class="cmd-card-body p-0">
          <table class="cmd-table">
            <thead>
              <tr>
                <th>Demand Driver</th>
                <th>Investment</th>
                <th>Team Share</th>
                <th>Result Impact</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($demandDriverAnalysis as $dda): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($dda['driver_name']); ?></strong></td>
                <td><?php echo $currency . number_format($dda['investment'], 0); ?></td>
                <td><?php echo number_format($dda['team_share'], 1); ?>%</td>
                <td><span class="badge bg-success"><?php echo $dda['result_impact']; ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Demand Allocation Breakdown -->
      <div class="cmd-card">
        <div class="cmd-card-header">
          <h3 class="cmd-card-title"><i class="fa-solid fa-filter text-primary"></i> Demand Allocation Breakdown</h3>
        </div>
        <div class="cmd-card-body p-0">
          <table class="cmd-table">
            <tbody>
              <tr><td>Minimum Market Allocation (Rule 1)</td><td class="text-end font-monospace"><?php echo number_format($minMarketAlloc); ?></td></tr>
              <tr><td>Demand Driver Allocation (Rule 2)</td><td class="text-end font-monospace"><?php echo number_format($ddAlloc); ?></td></tr>
              <tr><td>Price-Based Allocation (Rule 3)</td><td class="text-end font-monospace"><?php echo number_format($priceAlloc); ?></td></tr>
              <tr class="table-light fw-bold"><td>Total Units Sold</td><td class="text-end font-monospace text-primary fs-6"><?php echo number_format($myUnitsSold); ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Team Performance Comparison Table -->
    <div class="col-12">
      <div class="cmd-card">
        <div class="cmd-card-header">
          <h3 class="cmd-card-title"><i class="fa-solid fa-users text-primary"></i> Team Performance Comparison</h3>
        </div>
        <div class="cmd-card-body p-0">
          <table class="cmd-table">
            <thead>
              <tr>
                <th>Team</th>
                <th>Selling Price</th>
                <th>Units Sold</th>
                <th>Cost / Unit</th>
                <th>Profit</th>
                <th>Closing Inv</th>
                <th>Carry Forward</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allResults as $res): $isMe = $res['team_id'] == $teamId; ?>
              <tr class="<?php echo $isMe ? 'highlight-own' : ''; ?>">
                <td>
                  <strong><?php echo htmlspecialchars($res['team_name']); ?></strong>
                  <?php if ($isMe): ?><span class="badge bg-primary ms-1">Your Team</span><?php endif; ?>
                </td>
                <td class="font-monospace"><?php echo $currency . number_format((float) $res['selling_price'], 2); ?></td>
                <td class="font-monospace fw-bold"><?php echo number_format((int) $res['units_sold']); ?></td>
                <td class="font-monospace"><?php echo $currency . number_format((float) $res['fixed_cost'] > 0 ? ($res['variable_cost'] + $res['fixed_cost']) / max((int)$res['units_sold'],1) : $unitCost, 2); ?></td>
                <td class="font-monospace fw-bold <?php echo (float)$res['operating_profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                  <?php echo ((float)$res['operating_profit'] >= 0 ? '+' : '') . $currency . number_format((float)$res['operating_profit'], 2); ?>
                </td>
                <td class="font-monospace"><?php echo number_format((int) $res['closing_inventory']); ?></td>
                <td class="font-monospace fw-bold"><?php echo $currency . number_format((float) $res['cash_position'], 2); ?></td>
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
  <a href="<?php echo PLAYER_URL; ?>landing.php" class="cmd-btn-secondary">
    <i class="fa-solid fa-house"></i> Home Dashboard
  </a>
  <a href="<?php echo PLAYER_URL; ?>results/competitive_analysis.php?year_no=<?php echo $yearNo; ?>" class="cmd-btn-primary">
    Continue to Competitive Analysis <i class="fa-solid fa-arrow-right me-1"></i>
  </a>
</div>

<?php require_once __DIR__ . '/../includes/command_footer.php'; ?>
