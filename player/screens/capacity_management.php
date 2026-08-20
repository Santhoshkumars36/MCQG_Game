<?php
/**
 * MCQG Player - Screen 3: Capacity Management (Ultra Interactive Edition)
 * Path: player/screens/capacity_management.php
 * Source: Slide 3 - Dual range-slider + stepper controls, dynamic impact meters, live sidebar calculations.
 */
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../engine/cost/calculate_capacity_cost.php';
require_once __DIR__ . '/../../engine/cost/calculate_unit_cost.php';
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

$decision = $db->fetchOne(
    "SELECT * FROM team_decision WHERE team_id = :t AND year_no = :y",
    ['t' => $teamId, 'y' => $yearNo]
);

$lastResult = $db->fetchOne(
    "SELECT cash_position, closing_inventory FROM team_result WHERE team_id = :t ORDER BY year_no DESC LIMIT 1",
    ['t' => $teamId]
);
$openingBudget = $lastResult ? (float) $lastResult['cash_position'] : (float) $team['opening_budget'];
$openingInventory = $lastResult ? (int) $lastResult['closing_inventory'] : (int) $team['opening_inventory'];

// Capacity Investments catalog linked to Capacity Drivers
$capacityInvestments = $db->fetchAll(
    "SELECT DISTINCT im.*, ie.driver_id, ie.min_percent, ie.max_percent, ie.increment_percent, cd.driver_name
     FROM investment_master im
     INNER JOIN investment_effect ie ON ie.investment_id = im.investment_id
     LEFT JOIN capacity_driver cd ON cd.driver_id = ie.driver_id
     WHERE im.game_id = :g AND ie.driver_type = 'Capacity' AND im.active = 1
     ORDER BY im.display_order ASC",
    ['g' => $gameId]
);

$existingSelections = [];
if ($decision) {
    $rows = $db->fetchAll(
        "SELECT investment_id, invested_value FROM team_investment_selection WHERE decision_id = :d",
        ['d' => $decision['decision_id']]
    );
    foreach ($rows as $r) { $existingSelections[$r['investment_id']] = (float) $r['invested_value']; }
}

$capacityDrivers = $db->fetchAll("SELECT * FROM capacity_driver WHERE game_id = :g ORDER BY group_name, driver_name", ['g' => $gameId]);
$baseCosts = calculateCapacityDriverCosts((float) $game['capacity_cost'], $capacityDrivers);
$baseTotalCapacityCost = array_sum(array_column($baseCosts, 'cost_value'));

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $capacityBuilt = (int) ($_POST['capacity_built'] ?? $game['starting_capacity']);
    $productionQty = $capacityBuilt;
    $investments   = $_POST['investment'] ?? [];

    $totalInvested = array_sum(array_map('floatval', $investments));

    if ($totalInvested > $openingBudget) {
        $error = 'Total investment cost cannot exceed your available funds (' . DEFAULT_CURRENCY_SYMBOL . number_format($openingBudget, 2) . ').';
    } else {
        if ($decision) {
            $db->update('team_decision',
                ['capacity_built' => $capacityBuilt, 'production_qty' => $productionQty],
                'decision_id = :d', ['d' => $decision['decision_id']]);
            $decisionId = $decision['decision_id'];
        } else {
            $decisionId = $db->insert('team_decision', [
                'team_id' => $teamId, 'game_id' => $gameId, 'year_no' => $yearNo,
                'capacity_built' => $capacityBuilt, 'production_qty' => $productionQty,
                'selling_price' => 0, 'status' => 'Draft',
            ]);
        }

        foreach ($capacityInvestments as $inv) {
            $invId = $inv['investment_id'];
            $val = (float) ($investments[$invId] ?? 0);
            $exists = $db->fetchOne(
                "SELECT selection_id FROM team_investment_selection WHERE decision_id = :d AND investment_id = :i",
                ['d' => $decisionId, 'i' => $invId]
            );
            if ($val > 0) {
                $maxVal = max((float)$inv['max_investment_value'], 1);
                $minVal = (float)$inv['min_investment_value'];
                $pct = round((($val - $minVal) / max($maxVal - $minVal, 1)) * 100, 2);
                $data = ['invested_value' => $val, 'invested_percent' => $pct];

                if ($exists) {
                    $db->update('team_investment_selection', $data, 'selection_id = :s', ['s' => $exists['selection_id']]);
                } else {
                    $db->insert('team_investment_selection', array_merge($data, ['decision_id' => $decisionId, 'investment_id' => $invId]));
                }
            } elseif ($exists) {
                $db->execute("DELETE FROM team_investment_selection WHERE selection_id = :s", ['s' => $exists['selection_id']]);
            }
        }

        if (isset($_POST['action']) && $_POST['action'] === 'save') {
            $successMsg = 'Progress saved successfully!';
        } else {
            header('Location: demand_management.php');
            exit;
        }
    }
}

$currency = DEFAULT_CURRENCY_SYMBOL;
$pageTitle = 'Capacity Management';
$activeStep = 3;

require_once __DIR__ . '/../includes/command_header.php';
?>

<form method="POST" id="cmd-capacity-form">
  <input type="hidden" name="action" id="cmd-action-field" value="next">

  <div class="cmd-container">
    <?php if ($error): ?>
      <div class="alert alert-danger shadow-sm border-danger fw-semibold mb-4"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if (isset($successMsg)): ?>
      <div class="alert alert-success shadow-sm border-success fw-semibold mb-4"><?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>

    <div class="cmd-split-grid">
      
      <!-- ── Left Band 70%: Controls ── -->
      <div>
        
        <!-- Card 1: Change Plant Capacity -->
        <div class="cmd-card">
          <div class="cmd-card-header">
            <h2 class="cmd-card-title"><i class="fa-solid fa-industry text-primary"></i> Change Plant Capacity</h2>
            <span class="badge bg-primary px-3 py-2" style="font-size:12px;">Step 3.1</span>
          </div>
          <div class="cmd-card-body">
            <div class="cmd-field-label">Standard Production Capacity</div>
            
            <!-- Hybrid Stepper + Slider -->
            <div class="cmd-slider-wrapper mt-3">
              <input type="range" id="slider-capacity" class="cmd-range-slider"
                     value="<?php echo (int) ($decision['capacity_built'] ?? $game['starting_capacity']); ?>"
                     min="<?php echo (int) $game['minimum_capacity']; ?>" 
                     max="<?php echo (int) $game['maximum_capacity']; ?>" 
                     step="<?php echo (int) $game['capacity_increment']; ?>"
                     oninput="syncCapacityFromSlider(this.value)">

              <div class="cmd-stepper-control me-2">
                <button type="button" class="cmd-stepper-btn" onclick="adjustCapacity(-<?php echo (int) $game['capacity_increment']; ?>)">-</button>
                <input type="number" name="capacity_built" id="input-capacity" class="cmd-stepper-input" 
                       value="<?php echo (int) ($decision['capacity_built'] ?? $game['starting_capacity']); ?>"
                       min="<?php echo (int) $game['minimum_capacity']; ?>" 
                       max="<?php echo (int) $game['maximum_capacity']; ?>" 
                       step="<?php echo (int) $game['capacity_increment']; ?>"
                       onchange="validateCapacityInput(this)">
                <button type="button" class="cmd-stepper-btn" onclick="adjustCapacity(<?php echo (int) $game['capacity_increment']; ?>)">+</button>
              </div>
              <span class="fw-extrabold fs-5 text-dark" id="display-capacity-units" style="min-width:110px; font-family:'JetBrains Mono';"><?php echo (int) ($decision['capacity_built'] ?? $game['starting_capacity']); ?> Units</span>
            </div>

            <!-- Auto-generated helper message -->
            <div class="cmd-field-help mt-3 p-3 bg-light rounded-3 border">
              <i class="fa-solid fa-circle-info text-primary me-2"></i>
              Min capacity: <strong><?php echo number_format((int)$game['minimum_capacity']); ?></strong> | Max capacity: <strong><?php echo number_format((int)$game['maximum_capacity']); ?></strong> | Increment: <strong><?php echo (int)$game['capacity_increment']; ?></strong> units.
            </div>
          </div>
        </div>

        <!-- Card 2: Investment Decisions associated with Cost Drivers -->
        <div class="cmd-card">
          <div class="cmd-card-header">
            <h2 class="cmd-card-title"><i class="fa-solid fa-chart-pie text-primary"></i> Investment Decisions</h2>
            <span class="badge bg-secondary px-3 py-2" style="font-size:12px;">Step 3.2</span>
          </div>
          <div class="cmd-card-body">
            <p class="text-muted fs-6 mb-4">Allocate capital towards operational efficiency, quality, and technology to optimize your cost of capacity.</p>

            <?php foreach ($capacityInvestments as $inv): 
              $invId = $inv['investment_id'];
              $val = $existingSelections[$invId] ?? 0;
              $minVal = (float) $inv['min_investment_value'];
              $maxVal = (float) $inv['max_investment_value'];
              $stepVal = (float) $inv['increment_value'];
              $driverName = $inv['driver_name'] ?? 'Cost Driver';
              $minPct = (float) $inv['min_percent'];
              $maxPct = (float) $inv['max_percent'];
            ?>
            <div class="cmd-investment-item" data-inv-id="<?php echo $invId; ?>">
              <div class="cmd-inv-header">
                <div>
                  <div class="cmd-inv-name"><?php echo htmlspecialchars($inv['investment_name']); ?></div>
                  <div class="cmd-inv-desc"><?php echo htmlspecialchars($inv['description']); ?></div>
                </div>
                
                <div class="d-flex align-items-center gap-2">
                  <div class="cmd-stepper-control">
                    <button type="button" class="cmd-stepper-btn" onclick="adjustInvestment(<?php echo $invId; ?>, -<?php echo $stepVal; ?>)">-</button>
                    <input type="text" name="investment[<?php echo $invId; ?>]" id="input-inv-<?php echo $invId; ?>" class="cmd-stepper-input cmd-inv-input"
                           value="<?php echo number_format($val, 0, '.', ''); ?>"
                           data-min="<?php echo $minVal; ?>" data-max="<?php echo $maxVal; ?>" data-step="<?php echo $stepVal; ?>"
                           data-inv-id="<?php echo $invId; ?>" onchange="recalculateDashboard()">
                    <button type="button" class="cmd-stepper-btn" onclick="adjustInvestment(<?php echo $invId; ?>, <?php echo $stepVal; ?>)">+</button>
                  </div>
                </div>
              </div>

              <!-- Interactive Range Slider -->
              <input type="range" id="slider-inv-<?php echo $invId; ?>" class="cmd-range-slider w-100 mb-2"
                     value="<?php echo number_format($val, 0, '.', ''); ?>"
                     min="<?php echo $minVal; ?>" max="<?php echo $maxVal; ?>" step="<?php echo $stepVal; ?>"
                     oninput="syncInvestmentFromSlider(<?php echo $invId; ?>, this.value)">

              <!-- Dynamic Mapped Driver & Impact Meter -->
              <div class="cmd-impact-badge <?php echo $maxPct < 0 ? 'positive' : 'negative'; ?>" id="impact-badge-<?php echo $invId; ?>" 
                   data-min-pct="<?php echo $minPct; ?>" data-max-pct="<?php echo $maxPct; ?>">
                <div>
                  <i class="fa-solid fa-bolt me-1"></i>
                  Driver: <strong><?php echo htmlspecialchars($driverName); ?></strong> | Est. Impact: <span id="impact-val-<?php echo $invId; ?>">0.0%</span>
                </div>
                <div class="cmd-impact-bar-track">
                  <div class="cmd-impact-bar-fill" id="impact-fill-<?php echo $invId; ?>" style="width: 0%;"></div>
                </div>
              </div>

            </div>
            <?php endforeach; ?>

          </div>
        </div>

      </div>

      <!-- ── Right Band 30%: Executive Dashboard Sidebar ── -->
      <div class="cmd-sidebar-panel">
        <div class="cmd-sidebar-header">
          <h3 class="cmd-sidebar-title"><i class="fa-solid fa-gauge-high me-2"></i> Executive Dashboard</h3>
          <span class="badge bg-success" style="font-size:10px;">LIVE METRICS</span>
        </div>
        <div class="cmd-sidebar-body">

          <!-- Funds Available -->
          <div class="cmd-metric-box">
            <div class="cmd-metric-label">Funds Available</div>
            <div class="cmd-metric-value green" id="side-funds-available"><?php echo $currency . number_format($openingBudget, 2); ?></div>
            <div class="cmd-metric-sub">Capital remaining for investment</div>
          </div>

          <!-- Opening Inventory -->
          <div class="cmd-metric-box">
            <div class="cmd-metric-label">Opening Inventory</div>
            <div class="cmd-metric-value" id="side-opening-inv"><?php echo number_format($openingInventory); ?> Units</div>
          </div>

          <!-- Period Capacity -->
          <div class="cmd-metric-box">
            <div class="cmd-metric-label">Period Capacity</div>
            <div class="cmd-metric-value" id="side-period-cap"><?php echo number_format((int) ($decision['capacity_built'] ?? $game['starting_capacity'])); ?> Units</div>
          </div>

          <!-- Total Available Inventory -->
          <div class="cmd-metric-box">
            <div class="cmd-metric-label">Total Available Inventory</div>
            <div class="cmd-metric-value" id="side-total-inv"><?php echo number_format($openingInventory + (int) ($decision['capacity_built'] ?? $game['starting_capacity'])); ?> Units</div>
          </div>

          <!-- Total Capacity Cost -->
          <div class="cmd-metric-box">
            <div class="cmd-metric-label">Total Capacity Cost</div>
            <div class="cmd-metric-value red" id="side-capacity-cost"><?php echo $currency . number_format($baseTotalCapacityCost, 2); ?></div>
            <div class="cmd-metric-sub">Varies dynamically with investments</div>
          </div>

          <!-- Cost per Unit of Capacity -->
          <div class="cmd-metric-box">
            <div class="cmd-metric-label">Cost / Unit Capacity</div>
            <div class="cmd-metric-value" id="side-unit-cost"><?php echo $currency . number_format($baseTotalCapacityCost / max((int)($decision['capacity_built'] ?? $game['starting_capacity']), 1), 2); ?></div>
          </div>

          <!-- View Cost Break Up Button -->
          <button type="button" class="cmd-btn-breakup" onclick="openCostBreakupModal()">
            <i class="fa-solid fa-list-check"></i> View Cost Break Up
          </button>

        </div>
      </div>

    </div>
  </div>

  <!-- Bottom Navigation Bar -->
  <div class="cmd-bottom-bar">
    <a href="<?php echo PLAYER_URL; ?>screens/opening_statistics.php" class="cmd-btn-secondary">
      <i class="fa-solid fa-arrow-left"></i> Back
    </a>
    <div class="d-flex align-items-center gap-2">
      <button type="button" class="cmd-btn-secondary" onclick="submitFormAction('save')">
        <i class="fa-solid fa-floppy-disk me-1"></i> Save Progress
      </button>
      <button type="button" class="cmd-btn-primary" onclick="submitFormAction('next')">
        Next Step <i class="fa-solid fa-arrow-right me-1"></i>
      </button>
    </div>
  </div>
</form>

<!-- Modal: View Cost Break Up -->
<div class="cmd-modal-overlay" id="modal-cost-breakup">
  <div class="cmd-modal-dialog">
    <div class="cmd-modal-header">
      <h3 class="cmd-modal-title"><i class="fa-solid fa-calculator me-2"></i> Cost Drivers Breakdown</h3>
      <button type="button" class="cmd-modal-close" onclick="closeCostBreakupModal()">&times;</button>
    </div>
    <div class="cmd-modal-body p-0">
      <table class="cmd-table">
        <thead>
          <tr>
            <th>Cost Driver Name</th>
            <th>% of Cost</th>
            <th>Cost Value</th>
          </tr>
        </thead>
        <tbody id="breakup-table-body">
          <?php foreach ($capacityDrivers as $cd): 
            $cVal = (float) $game['capacity_cost'] * ((float) $cd['cost_share_percent'] / 100);
          ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($cd['driver_name']); ?></strong> (<?php echo htmlspecialchars($cd['group_name']); ?>)</td>
            <td><?php echo number_format((float) $cd['cost_share_percent'], 1); ?>%</td>
            <td class="font-monospace fw-bold"><?php echo $currency . number_format($cVal, 2); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
const openingBudget = <?php echo (float) $openingBudget; ?>;
const openingInventory = <?php echo (int) $openingInventory; ?>;
const baseCapacityCost = <?php echo (float) $baseTotalCapacityCost; ?>;
const currencySymbol = "<?php echo $currency; ?>";
const minCap = <?php echo (int) $game['minimum_capacity']; ?>;
const maxCap = <?php echo (int) $game['maximum_capacity']; ?>;
const stepCap = <?php echo (int) $game['capacity_increment']; ?>;

function syncCapacityFromSlider(val) {
  document.getElementById('input-capacity').value = val;
  validateCapacityInput(document.getElementById('input-capacity'));
}

function adjustCapacity(delta) {
  const input = document.getElementById('input-capacity');
  let current = parseInt(input.value) || minCap;
  let nextVal = Math.min(Math.max(current + delta, minCap), maxCap);
  input.value = nextVal;
  document.getElementById('slider-capacity').value = nextVal;
  validateCapacityInput(input);
}

function validateCapacityInput(input) {
  let val = parseInt(input.value) || minCap;
  if (val < minCap) val = minCap;
  if (val > maxCap) val = maxCap;
  input.value = val;
  document.getElementById('slider-capacity').value = val;
  document.getElementById('display-capacity-units').textContent = val.toLocaleString() + ' Units';
  recalculateDashboard();
}

function syncInvestmentFromSlider(invId, val) {
  document.getElementById('input-inv-' + invId).value = val;
  recalculateDashboard();
}

function adjustInvestment(invId, delta) {
  const input = document.getElementById('input-inv-' + invId);
  let min = parseFloat(input.getAttribute('data-min')) || 0;
  let max = parseFloat(input.getAttribute('data-max')) || 999999999;
  let current = parseFloat(input.value) || 0;
  let nextVal = Math.min(Math.max(current + delta, min), max);
  input.value = nextVal.toFixed(0);
  document.getElementById('slider-inv-' + invId).value = nextVal;
  recalculateDashboard();
}

function recalculateDashboard() {
  let totalInvested = 0;
  document.querySelectorAll('.cmd-inv-input').forEach(input => {
    let invId = input.getAttribute('data-inv-id');
    let val = parseFloat(input.value) || 0;
    totalInvested += val;

    // Sync slider
    const slider = document.getElementById('slider-inv-' + invId);
    if (slider) slider.value = val;

    // Recalculate dynamic impact badge & progress meter
    let badge = document.getElementById('impact-badge-' + invId);
    let impactSpan = document.getElementById('impact-val-' + invId);
    let impactFill = document.getElementById('impact-fill-' + invId);

    if (badge && impactSpan) {
      let minPct = parseFloat(badge.getAttribute('data-min-pct')) || 0;
      let maxPct = parseFloat(badge.getAttribute('data-max-pct')) || 0;
      let minVal = parseFloat(input.getAttribute('data-min')) || 0;
      let maxVal = parseFloat(input.getAttribute('data-max')) || 1;
      
      let rangeVal = Math.max(maxVal - minVal, 1);
      let pctRatio = Math.min(Math.max((val - minVal) / rangeVal, 0), 1);
      let calcImpact = minPct + (pctRatio * (maxPct - minPct));
      
      let formattedPct = (calcImpact >= 0 ? '+' : '') + calcImpact.toFixed(1) + '%';
      impactSpan.textContent = formattedPct;

      if (impactFill) {
        impactFill.style.width = (pctRatio * 100) + '%';
      }
    }
  });

  // Calculate Funds Available
  let fundsLeft = openingBudget - totalInvested;
  const fundsEl = document.getElementById('side-funds-available');
  fundsEl.textContent = currencySymbol + Math.max(fundsLeft, 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
  if (fundsLeft < 0) {
    fundsEl.classList.remove('green');
    fundsEl.classList.add('red');
  } else {
    fundsEl.classList.remove('red');
    fundsEl.classList.add('green');
  }

  // Calculate Capacity & Inventories
  let cap = parseInt(document.getElementById('input-capacity').value) || minCap;
  document.getElementById('side-period-cap').textContent = cap.toLocaleString() + ' Units';
  document.getElementById('side-total-inv').textContent = (openingInventory + cap).toLocaleString() + ' Units';

  // Calculate Capacity Costs
  let estimatedCapacityCost = baseCapacityCost + totalInvested;
  let unitCost = cap > 0 ? estimatedCapacityCost / cap : 0;

  document.getElementById('side-capacity-cost').textContent = currencySymbol + estimatedCapacityCost.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
  document.getElementById('side-unit-cost').textContent = currencySymbol + unitCost.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function openCostBreakupModal() {
  document.getElementById('modal-cost-breakup').style.display = 'flex';
}

function closeCostBreakupModal() {
  document.getElementById('modal-cost-breakup').style.display = 'none';
}

function submitFormAction(action) {
  document.getElementById('cmd-action-field').value = action;
  document.getElementById('cmd-capacity-form').submit();
}

document.addEventListener('DOMContentLoaded', function() {
  recalculateDashboard();
});
</script>

<?php require_once __DIR__ . '/../includes/command_footer.php'; ?>
