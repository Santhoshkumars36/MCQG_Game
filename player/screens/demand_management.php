<?php
/**
 * MCQG Player - Screen 4: Demand Management (Ultra Interactive Edition)
 * Path: player/screens/demand_management.php
 * Source: Slide 4 - Dual range-sliders + steppers, real-time profit/loss indicator, and interactive demand investments.
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
$openingInventory = $lastResult ? (int) $lastResult['closing_inventory'] : (int) $team['opening_inventory'];
$capacityBuilt = (int) ($decision['capacity_built'] ?? $game['starting_capacity']);
$totalAvailableInventory = $openingInventory + $capacityBuilt;

$capacityDrivers = $db->fetchAll("SELECT * FROM capacity_driver WHERE game_id = :g", ['g' => $gameId]);
$baseCosts = calculateCapacityDriverCosts((float) $game['capacity_cost'], $capacityDrivers);
$baseTotalCapacityCost = array_sum(array_column($baseCosts, 'cost_value'));

$capInvestmentsSum = 0;
if ($decision) {
    $rows = $db->fetchAll(
        "SELECT invested_value FROM team_investment_selection tis
         INNER JOIN investment_effect ie ON ie.investment_id = tis.investment_id
         WHERE tis.decision_id = :d AND ie.driver_type = 'Capacity'",
        ['d' => $decision['decision_id']]
    );
    foreach ($rows as $r) { $capInvestmentsSum += (float) $r['invested_value']; }
}
$estimatedUnitCost = ($baseTotalCapacityCost + $capInvestmentsSum) / max($capacityBuilt, 1);

$benchmarkPrice = (float) ($game['sales_price'] > 0 ? $game['sales_price'] : 100);
$tolerancePct   = (float) ($game['sales_price_tolerance_percent'] ?? 20);
$minPrice       = round($benchmarkPrice * (1 - ($tolerancePct / 100)), 2);
$maxPrice       = round($benchmarkPrice * (1 + ($tolerancePct / 100)), 2);

$currentPrice   = $decision && (float)$decision['selling_price'] > 0 ? (float)$decision['selling_price'] : $benchmarkPrice;
$currentUnitsForSale = $decision && (int)$decision['production_qty'] > 0 ? (int)$decision['production_qty'] : $totalAvailableInventory;

$demandInvestments = $db->fetchAll(
    "SELECT DISTINCT im.*, ie.driver_id, ie.min_percent, ie.max_percent, ie.increment_percent, dd.driver_name
     FROM investment_master im
     INNER JOIN investment_effect ie ON ie.investment_id = im.investment_id
     LEFT JOIN demand_driver dd ON dd.driver_id = ie.driver_id
     WHERE im.game_id = :g AND ie.driver_type = 'Demand' AND im.active = 1
     ORDER BY im.display_order ASC",
    ['g' => $gameId]
);

$existingDemandSelections = [];
if ($decision) {
    $rows = $db->fetchAll(
        "SELECT investment_id, invested_value FROM team_investment_selection WHERE decision_id = :d",
        ['d' => $decision['decision_id']]
    );
    foreach ($rows as $r) { $existingDemandSelections[$r['investment_id']] = (float) $r['invested_value']; }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sellingPrice = (float) ($_POST['selling_price'] ?? $benchmarkPrice);
    $unitsForSale = (int) ($_POST['units_for_sale'] ?? $totalAvailableInventory);
    $demandInvs   = $_POST['demand_investment'] ?? [];
    $isSubmit     = isset($_POST['action']) && $_POST['action'] === 'submit';

    if ($sellingPrice < $minPrice || $sellingPrice > $maxPrice) {
        $error = "Selling price must be within the allowed tolerance range (" . DEFAULT_CURRENCY_SYMBOL . number_format($minPrice, 2) . " to " . DEFAULT_CURRENCY_SYMBOL . number_format($maxPrice, 2) . ").";
    } elseif ($unitsForSale < 0 || $unitsForSale > $totalAvailableInventory) {
        $error = "Units offered for sale cannot exceed total available inventory (" . number_format($totalAvailableInventory) . " units).";
    } else {
        if ($decision) {
            $status = $isSubmit ? 'Submitted' : 'Draft';
            $db->update('team_decision',
                ['selling_price' => $sellingPrice, 'production_qty' => $unitsForSale, 'status' => $status, 'submitted_on' => $isSubmit ? date('Y-m-d H:i:s') : null],
                'decision_id = :d', ['d' => $decision['decision_id']]);
            $decisionId = $decision['decision_id'];
        } else {
            $decisionId = $db->insert('team_decision', [
                'team_id' => $teamId, 'game_id' => $gameId, 'year_no' => $yearNo,
                'capacity_built' => $capacityBuilt, 'production_qty' => $unitsForSale,
                'selling_price' => $sellingPrice, 'status' => $isSubmit ? 'Submitted' : 'Draft',
                'submitted_on' => $isSubmit ? date('Y-m-d H:i:s') : null,
            ]);
        }

        foreach ($demandInvestments as $inv) {
            $invId = $inv['investment_id'];
            $val = (float) ($demandInvs[$invId] ?? 0);
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

        if ($isSubmit) {
            header('Location: ' . PLAYER_URL . 'landing.php?msg=submitted');
            exit;
        } else {
            $successMsg = 'Draft saved successfully.';
        }
    }
}

$currency = DEFAULT_CURRENCY_SYMBOL;
$pageTitle = 'Demand Management';
$activeStep = 4;

require_once __DIR__ . '/../includes/command_header.php';
?>

<form method="POST" id="cmd-demand-form">
  <input type="hidden" name="action" id="cmd-demand-action" value="draft">

  <div class="cmd-container">
    <?php if ($error): ?>
      <div class="alert alert-danger shadow-sm border-danger fw-semibold mb-4"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if (isset($successMsg)): ?>
      <div class="alert alert-success shadow-sm border-success fw-semibold mb-4"><?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>

    <div class="cmd-split-grid">
      
      <!-- ── Left Band 70%: Demand Controls ── -->
      <div>
        
        <!-- Card 1: Sale Price Input -->
        <div class="cmd-card">
          <div class="cmd-card-header">
            <h2 class="cmd-card-title"><i class="fa-solid fa-tag text-primary"></i> Sale Price Strategy</h2>
            <span class="badge bg-primary px-3 py-2" style="font-size:12px;">Step 4.1</span>
          </div>
          <div class="cmd-card-body">
            <div class="cmd-field-label">Unit Sale Price</div>
            
            <!-- Dual Stepper-Slider for Price -->
            <div class="cmd-slider-wrapper mt-3">
              <input type="range" id="slider-selling-price" class="cmd-range-slider"
                     value="<?php echo number_format($currentPrice, 2, '.', ''); ?>"
                     min="<?php echo $minPrice; ?>" max="<?php echo $maxPrice; ?>" step="0.50"
                     oninput="syncPriceFromSlider(this.value)">

              <div class="cmd-stepper-control me-2">
                <button type="button" class="cmd-stepper-btn" onclick="adjustPrice(-0.50)">-</button>
                <input type="number" step="0.01" name="selling_price" id="input-selling-price" class="cmd-stepper-input" style="width:110px;"
                       value="<?php echo number_format($currentPrice, 2, '.', ''); ?>"
                       min="<?php echo $minPrice; ?>" max="<?php echo $maxPrice; ?>"
                       onchange="recalculateDemandDashboard()">
                <button type="button" class="cmd-stepper-btn" onclick="adjustPrice(0.50)">+</button>
              </div>
              <span class="fw-extrabold fs-4 text-dark" id="display-price-readout" style="min-width:120px; font-family:'JetBrains Mono';"><?php echo $currency . number_format($currentPrice, 2); ?></span>
            </div>

            <!-- Auto-generated sales price tolerance message -->
            <div class="cmd-field-help mt-3 p-3 bg-light rounded-3 border">
              <i class="fa-solid fa-circle-info text-primary me-2"></i>
              Sale price range: <strong><?php echo $currency . number_format($minPrice, 2); ?></strong> to <strong><?php echo $currency . number_format($maxPrice, 2); ?></strong> (Tolerance: <strong>&plusmn;<?php echo number_format($tolerancePct, 1); ?>%</strong>).
            </div>
          </div>
        </div>

        <!-- Card 2: Inventory Available for Sale -->
        <div class="cmd-card">
          <div class="cmd-card-header">
            <h2 class="cmd-card-title"><i class="fa-solid fa-boxes-stacked text-primary"></i> Inventory Offered for Sale</h2>
            <span class="badge bg-secondary px-3 py-2" style="font-size:12px;">Step 4.2</span>
          </div>
          <div class="cmd-card-body">
            <div class="cmd-field-label">Units Offered for Sale</div>

            <!-- Dual Stepper-Slider for Inventory -->
            <div class="cmd-slider-wrapper mt-3">
              <input type="range" id="slider-units-sale" class="cmd-range-slider"
                     value="<?php echo (int) $currentUnitsForSale; ?>"
                     min="0" max="<?php echo (int) $totalAvailableInventory; ?>" step="10"
                     oninput="syncUnitsFromSlider(this.value)">

              <div class="cmd-stepper-control me-2">
                <button type="button" class="cmd-stepper-btn" onclick="adjustUnitsForSale(-50)">-</button>
                <input type="number" name="units_for_sale" id="input-units-sale" class="cmd-stepper-input" style="width:110px;"
                       value="<?php echo (int) $currentUnitsForSale; ?>"
                       min="0" max="<?php echo (int) $totalAvailableInventory; ?>" step="10"
                       onchange="recalculateDemandDashboard()">
                <button type="button" class="cmd-stepper-btn" onclick="adjustUnitsForSale(50)">+</button>
              </div>
              <span class="fw-extrabold fs-5 text-dark" id="display-units-readout" style="min-width:120px; font-family:'JetBrains Mono';"><?php echo number_format($currentUnitsForSale); ?> Units</span>
            </div>

            <div class="cmd-field-help mt-3 p-3 bg-light rounded-3 border">
              Total Available Inventory for Year <?php echo (int)$yearNo; ?>: <strong><?php echo number_format($totalAvailableInventory); ?> Units</strong> (Opening Inventory + Capacity Built).
            </div>
          </div>
        </div>

        <!-- Card 3: Demand Investments -->
        <div class="cmd-card">
          <div class="cmd-card-header">
            <h2 class="cmd-card-title"><i class="fa-solid fa-bullhorn text-primary"></i> Demand Investments</h2>
            <span class="badge bg-secondary px-3 py-2" style="font-size:12px;">Step 4.3</span>
          </div>
          <div class="cmd-card-body">
            <p class="text-muted fs-6 mb-4">Invest in commercial marketing, sales distribution, and customer acquisition drivers to boost your market share allocation.</p>

            <?php if (empty($demandInvestments)): ?>
              <div class="text-muted small py-2">No demand investment items configured for this game.</div>
            <?php else: ?>
              <?php foreach ($demandInvestments as $inv): 
                $invId = $inv['investment_id'];
                $val = $existingDemandSelections[$invId] ?? 0;
                $minVal = (float) $inv['min_investment_value'];
                $maxVal = (float) $inv['max_investment_value'];
                $stepVal = (float) $inv['increment_value'];
                $driverName = $inv['driver_name'] ?? 'Demand Driver';
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
                      <button type="button" class="cmd-stepper-btn" onclick="adjustDemandInvestment(<?php echo $invId; ?>, -<?php echo $stepVal; ?>)">-</button>
                      <input type="text" name="demand_investment[<?php echo $invId; ?>]" id="input-demand-inv-<?php echo $invId; ?>" class="cmd-stepper-input cmd-demand-inv-input"
                             value="<?php echo number_format($val, 0, '.', ''); ?>"
                             data-min="<?php echo $minVal; ?>" data-max="<?php echo $maxVal; ?>" data-step="<?php echo $stepVal; ?>"
                             data-inv-id="<?php echo $invId; ?>" onchange="recalculateDemandDashboard()">
                      <button type="button" class="cmd-stepper-btn" onclick="adjustDemandInvestment(<?php echo $invId; ?>, <?php echo $stepVal; ?>)">+</button>
                    </div>
                  </div>
                </div>

                <!-- Interactive Range Slider -->
                <input type="range" id="slider-demand-inv-<?php echo $invId; ?>" class="cmd-range-slider w-100 mb-2"
                       value="<?php echo number_format($val, 0, '.', ''); ?>"
                       min="<?php echo $minVal; ?>" max="<?php echo $maxVal; ?>" step="<?php echo $stepVal; ?>"
                       oninput="syncDemandInvestmentFromSlider(<?php echo $invId; ?>, this.value)">

                <!-- Dynamic Mapped Demand Driver & Impact Meter -->
                <div class="cmd-impact-badge positive" id="demand-impact-badge-<?php echo $invId; ?>"
                     data-min-pct="<?php echo $minPct; ?>" data-max-pct="<?php echo $maxPct; ?>">
                  <div>
                    <i class="fa-solid fa-chart-line me-1"></i>
                    Driver: <strong><?php echo htmlspecialchars($driverName); ?></strong> | Est. Market Share Boost: <span id="demand-impact-val-<?php echo $invId; ?>">0.0%</span>
                  </div>
                  <div class="cmd-impact-bar-track">
                    <div class="cmd-impact-bar-fill" id="demand-impact-fill-<?php echo $invId; ?>" style="width: 0%;"></div>
                  </div>
                </div>

              </div>
              <?php endforeach; ?>
            <?php endif; ?>

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

          <!-- Sales Revenue -->
          <div class="cmd-metric-box">
            <div class="cmd-metric-label">Sales Revenue</div>
            <div class="cmd-metric-value green" id="side-sales-revenue"><?php echo $currency; ?>0.00</div>
            <div class="cmd-metric-sub">Sale Price &times; Units Offered</div>
          </div>

          <!-- Cost of Sales -->
          <div class="cmd-metric-box">
            <div class="cmd-metric-label">Cost of Sales</div>
            <div class="cmd-metric-value red" id="side-cost-sales"><?php echo $currency; ?>0.00</div>
            <div class="cmd-metric-sub">Units Offered &times; Unit Capacity Cost</div>
          </div>

          <!-- Profit / Loss -->
          <div class="cmd-metric-box">
            <div class="cmd-metric-label">Estimated Profit / Loss</div>
            <div class="cmd-metric-value" id="side-profit-loss"><?php echo $currency; ?>0.00</div>
            <div class="cmd-metric-sub">Sales Revenue &minus; Cost of Sales</div>
          </div>

          <!-- Closing Inventory -->
          <div class="cmd-metric-box">
            <div class="cmd-metric-label">Closing Inventory</div>
            <div class="cmd-metric-value" id="side-closing-inv"><?php echo number_format($totalAvailableInventory - $currentUnitsForSale); ?> Units</div>
            <div class="cmd-metric-sub">Available Inventory &minus; Units Offered</div>
          </div>

        </div>
      </div>

    </div>
  </div>

  <!-- Bottom Navigation Bar -->
  <div class="cmd-bottom-bar">
    <a href="<?php echo PLAYER_URL; ?>screens/capacity_management.php" class="cmd-btn-secondary">
      <i class="fa-solid fa-arrow-left"></i> Back
    </a>
    <div class="d-flex align-items-center gap-2">
      <button type="button" class="cmd-btn-secondary" onclick="saveDraft()">
        <i class="fa-solid fa-floppy-disk me-1"></i> Save Draft
      </button>
      <button type="button" class="cmd-btn-submit" onclick="confirmFinalSubmit()">
        <i class="fa-solid fa-paper-plane me-1"></i> SUBMIT ROUND
      </button>
    </div>
  </div>
</form>

<script>
const estimatedUnitCost = <?php echo (float) $estimatedUnitCost; ?>;
const totalAvailableInventory = <?php echo (int) $totalAvailableInventory; ?>;
const minPrice = <?php echo (float) $minPrice; ?>;
const maxPrice = <?php echo (float) $maxPrice; ?>;
const currencySymbol = "<?php echo $currency; ?>";

function syncPriceFromSlider(val) {
  document.getElementById('input-selling-price').value = parseFloat(val).toFixed(2);
  recalculateDemandDashboard();
}

function adjustPrice(delta) {
  const input = document.getElementById('input-selling-price');
  let current = parseFloat(input.value) || minPrice;
  let nextVal = Math.min(Math.max(current + delta, minPrice), maxPrice);
  input.value = nextVal.toFixed(2);
  document.getElementById('slider-selling-price').value = nextVal;
  recalculateDemandDashboard();
}

function syncUnitsFromSlider(val) {
  document.getElementById('input-units-sale').value = val;
  recalculateDemandDashboard();
}

function adjustUnitsForSale(delta) {
  const input = document.getElementById('input-units-sale');
  let current = parseInt(input.value) || 0;
  let nextVal = Math.min(Math.max(current + delta, 0), totalAvailableInventory);
  input.value = nextVal;
  document.getElementById('slider-units-sale').value = nextVal;
  recalculateDemandDashboard();
}

function syncDemandInvestmentFromSlider(invId, val) {
  document.getElementById('input-demand-inv-' + invId).value = val;
  recalculateDemandDashboard();
}

function adjustDemandInvestment(invId, delta) {
  const input = document.getElementById('input-demand-inv-' + invId);
  let min = parseFloat(input.getAttribute('data-min')) || 0;
  let max = parseFloat(input.getAttribute('data-max')) || 999999999;
  let current = parseFloat(input.value) || 0;
  let nextVal = Math.min(Math.max(current + delta, min), max);
  input.value = nextVal.toFixed(0);
  document.getElementById('slider-demand-inv-' + invId).value = nextVal;
  recalculateDemandDashboard();
}

function recalculateDemandDashboard() {
  let price = parseFloat(document.getElementById('input-selling-price').value) || 0;
  let units = parseInt(document.getElementById('input-units-sale').value) || 0;

  // Sync sliders
  document.getElementById('slider-selling-price').value = price;
  document.getElementById('slider-units-sale').value = units;

  document.getElementById('display-price-readout').textContent = currencySymbol + price.toFixed(2);
  document.getElementById('display-units-readout').textContent = units.toLocaleString() + ' Units';

  // Recalculate demand investment impact meters
  document.querySelectorAll('.cmd-demand-inv-input').forEach(input => {
    let invId = input.getAttribute('data-inv-id');
    let val = parseFloat(input.value) || 0;

    const slider = document.getElementById('slider-demand-inv-' + invId);
    if (slider) slider.value = val;

    let badge = document.getElementById('demand-impact-badge-' + invId);
    let impactSpan = document.getElementById('demand-impact-val-' + invId);
    let impactFill = document.getElementById('demand-impact-fill-' + invId);

    if (badge && impactSpan) {
      let minPct = parseFloat(badge.getAttribute('data-min-pct')) || 0;
      let maxPct = parseFloat(badge.getAttribute('data-max-pct')) || 0;
      let minVal = parseFloat(input.getAttribute('data-min')) || 0;
      let maxVal = parseFloat(input.getAttribute('data-max')) || 1;
      
      let rangeVal = Math.max(maxVal - minVal, 1);
      let pctRatio = Math.min(Math.max((val - minVal) / rangeVal, 0), 1);
      let calcImpact = minPct + (pctRatio * (maxPct - minPct));
      
      let formattedPct = '+' + calcImpact.toFixed(1) + '%';
      impactSpan.textContent = formattedPct;

      if (impactFill) {
        impactFill.style.width = (pctRatio * 100) + '%';
      }
    }
  });

  // Live sidebar metrics
  let revenue = price * units;
  let costOfSales = units * estimatedUnitCost;
  let profit = revenue - costOfSales;
  let closingInv = totalAvailableInventory - units;

  document.getElementById('side-sales-revenue').textContent = currencySymbol + revenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
  document.getElementById('side-cost-sales').textContent = currencySymbol + costOfSales.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

  const profitEl = document.getElementById('side-profit-loss');
  profitEl.textContent = (profit >= 0 ? '+' : '') + currencySymbol + profit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
  if (profit >= 0) {
    profitEl.className = 'cmd-metric-value green';
  } else {
    profitEl.className = 'cmd-metric-value red';
  }

  document.getElementById('side-closing-inv').textContent = Math.max(closingInv, 0).toLocaleString() + ' Units';
}

function saveDraft() {
  document.getElementById('cmd-demand-action').value = 'draft';
  document.getElementById('cmd-demand-form').submit();
}

function confirmFinalSubmit() {
  Swal.fire({
    title: 'Submit Round Decisions?',
    text: 'This completes your decisions for Year <?php echo (int)$yearNo; ?>. You will not be able to modify your choices after submission.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d97706',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Yes, Submit Final Decision',
    cancelButtonText: 'Review Decisions'
  }).then((result) => {
    if (result.isConfirmed) {
      document.getElementById('cmd-demand-action').value = 'submit';
      document.getElementById('cmd-demand-form').submit();
    }
  });
}

document.addEventListener('DOMContentLoaded', function() {
  recalculateDemandDashboard();
});
</script>

<?php require_once __DIR__ . '/../includes/command_footer.php'; ?>
