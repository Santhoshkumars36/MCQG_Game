<?php
/**
 * MCQG Player - Screen 2: Opening Statistics
 * Path: player/screens/opening_statistics.php
 * Source: Slide 2 - Non-editable baseline setup data (full scroll view).
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

$currentRound = $db->fetchOne(
    "SELECT * FROM game_round_status WHERE game_id = :g AND status != 'Processed' ORDER BY year_no LIMIT 1",
    ['g' => $gameId]
);
$yearNo = $currentRound['year_no'] ?? (int) $game['no_of_years'];

// Running cash & inventory balances
$lastResult = $db->fetchOne(
    "SELECT cash_position, closing_inventory FROM team_result WHERE team_id = :t ORDER BY year_no DESC LIMIT 1",
    ['t' => $teamId]
);
$currentBudget = $lastResult ? (float) $lastResult['cash_position'] : (float) $team['opening_budget'];
$currentInventory = $lastResult ? (int) $lastResult['closing_inventory'] : (int) $team['opening_inventory'];

// Market Years & Demand Allocations
$marketYears = $db->fetchAll(
    "SELECT my.*, da.min_percent, da.demand_driver_percent, da.price_tick 
     FROM game_market_year my
     LEFT JOIN game_demand_allocation da ON da.game_id = my.game_id AND da.year_no = my.year_no
     WHERE my.game_id = :g ORDER BY my.year_no ASC",
    ['g' => $gameId]
);

// Drivers
$capacityDrivers = $db->fetchAll("SELECT * FROM capacity_driver WHERE game_id = :g ORDER BY group_name, driver_name", ['g' => $gameId]);
$demandDrivers   = $db->fetchAll("SELECT * FROM demand_driver WHERE game_id = :g ORDER BY group_name, driver_name", ['g' => $gameId]);

$baseCosts = calculateCapacityDriverCosts((float) $game['capacity_cost'], $capacityDrivers);

$currency = DEFAULT_CURRENCY_SYMBOL;
$pageTitle = 'Opening Statistics';
$activeStep = 2;

require_once __DIR__ . '/../includes/command_header.php';
?>

<div class="cmd-container">
  <div class="cmd-full-grid">

    <!-- Header Hero Banner -->
    <div class="cmd-card">
      <div class="cmd-card-header">
        <h2 class="cmd-card-title">
          <i class="fa-solid fa-chart-line text-primary"></i>
          Opening Statistics &amp; Simulation Parameters
        </h2>
        <span class="badge bg-secondary px-3 py-2">Read Only Reference</span>
      </div>
      <div class="cmd-card-body">
        <p class="text-muted mb-4">Starting operational parameters, financial positions, demand trajectory, and baseline cost structure configured for this game.</p>

        <!-- KPI Cards Grid -->
        <div class="row g-3 mb-4">
          <div class="col-md-4 col-lg-2">
            <div class="cmd-metric-box">
              <div class="cmd-metric-label">Current Inventory</div>
              <div class="cmd-metric-value"><?php echo number_format($currentInventory); ?></div>
              <div class="cmd-metric-sub"><?php echo htmlspecialchars($game['unit_of_measure']); ?></div>
            </div>
          </div>
          <div class="col-md-4 col-lg-2.5" style="flex:0 0 20%;">
            <div class="cmd-metric-box">
              <div class="cmd-metric-label">Current Plant Capacity</div>
              <div class="cmd-metric-value"><?php echo number_format((int) $game['starting_capacity']); ?></div>
              <div class="cmd-metric-sub"><?php echo htmlspecialchars($game['unit_of_measure']); ?> / period</div>
            </div>
          </div>
          <div class="col-md-4 col-lg-2.5" style="flex:0 0 20%;">
            <div class="cmd-metric-box">
              <div class="cmd-metric-label">Cost of Capacity</div>
              <div class="cmd-metric-value"><?php echo $currency . number_format((float) $game['capacity_cost'], 2); ?></div>
              <div class="cmd-metric-sub">Base total capacity cost</div>
            </div>
          </div>
          <div class="col-md-4 col-lg-2.5" style="flex:0 0 20%;">
            <div class="cmd-metric-box">
              <div class="cmd-metric-label">Available Funds</div>
              <div class="cmd-metric-value green"><?php echo $currency . number_format($currentBudget, 2); ?></div>
              <div class="cmd-metric-sub">Capital available</div>
            </div>
          </div>
          <div class="col-md-4 col-lg-2" style="flex:0 0 20%;">
            <div class="cmd-metric-box">
              <div class="cmd-metric-label">Number of Periods</div>
              <div class="cmd-metric-value"><?php echo (int) $game['no_of_years']; ?></div>
              <div class="cmd-metric-sub">Total annual rounds</div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Demand Forecast Table Card -->
    <div class="cmd-card">
      <div class="cmd-card-header">
        <h3 class="cmd-card-title"><i class="fa-solid fa-calendar-days text-primary"></i> Market Demand Forecast &amp; Rules</h3>
      </div>
      <div class="cmd-card-body p-0">
        <table class="cmd-table">
          <thead>
            <tr>
              <th>Year</th>
              <th>Market Demand</th>
              <th>Inflation %</th>
              <th>Min Allocation % (Rule 1)</th>
              <th>Demand Driver % (Rule 2)</th>
              <th>Price Competition (Rule 3)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($marketYears as $my): ?>
            <tr class="<?php echo $my['year_no'] == $yearNo ? 'highlight-own' : ''; ?>">
              <td><strong>Year <?php echo (int) $my['year_no']; ?></strong> <?php if ($my['year_no'] == $yearNo): ?><span class="badge bg-primary ms-1">Active</span><?php endif; ?></td>
              <td><strong><?php echo number_format((int) $my['market_demand']); ?></strong> <?php echo htmlspecialchars($game['unit_of_measure']); ?></td>
              <td><?php echo number_format((float) $my['inflation_percent'], 1); ?>%</td>
              <td><?php echo number_format((float) ($my['min_percent'] ?? 0), 1); ?>%</td>
              <td><?php echo number_format((float) ($my['demand_driver_percent'] ?? 0), 1); ?>%</td>
              <td><span class="badge bg-success">Active (Lowest Price Priority)</span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Capacity Drivers Card -->
    <div class="cmd-card">
      <div class="cmd-card-header">
        <h3 class="cmd-card-title"><i class="fa-solid fa-gears text-primary"></i> Capacity Cost Drivers</h3>
      </div>
      <div class="cmd-card-body p-0">
        <table class="cmd-table">
          <thead>
            <tr>
              <th>Group Name</th>
              <th>Driver Name</th>
              <th>Cost Share %</th>
              <th>Cost Value</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($capacityDrivers)): ?>
              <tr><td colspan="4" class="text-muted text-center py-3">No capacity drivers defined.</td></tr>
            <?php else: ?>
              <?php foreach ($capacityDrivers as $cd): 
                $cVal = (float) $game['capacity_cost'] * ((float) $cd['cost_share_percent'] / 100);
              ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($cd['group_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($cd['driver_name']); ?></td>
                <td><?php echo number_format((float) $cd['cost_share_percent'], 1); ?>%</td>
                <td><strong><?php echo $currency . number_format($cVal, 2); ?></strong></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Demand Drivers Card -->
    <div class="cmd-card">
      <div class="cmd-card-header">
        <h3 class="cmd-card-title"><i class="fa-solid fa-bullhorn text-primary"></i> Demand Drivers</h3>
      </div>
      <div class="cmd-card-body p-0">
        <table class="cmd-table">
          <thead>
            <tr>
              <th>Group Name</th>
              <th>Driver Name</th>
              <th>Demand Share %</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($demandDrivers)): ?>
              <tr><td colspan="3" class="text-muted text-center py-3">No demand drivers defined.</td></tr>
            <?php else: ?>
              <?php foreach ($demandDrivers as $dd): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($dd['group_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($dd['driver_name']); ?></td>
                <td><strong><?php echo number_format((float) $dd['demand_share_percent'], 1); ?>%</strong></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Bottom Navigation Bar -->
<div class="cmd-bottom-bar">
  <a href="<?php echo PLAYER_URL; ?>screens/case_study.php" class="cmd-btn-secondary">
    <i class="fa-solid fa-arrow-left"></i> Back to Case Study
  </a>
  <a href="<?php echo PLAYER_URL; ?>screens/capacity_management.php" class="cmd-btn-primary">
    Proceed to Capacity Management <i class="fa-solid fa-arrow-right"></i>
  </a>
</div>

<?php require_once __DIR__ . '/../includes/command_footer.php'; ?>
