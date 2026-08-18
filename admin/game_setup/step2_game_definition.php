<?php
/**
 * MCQG Admin - Game Setup Step 2: Game Definition (Starting Baseline Data)
 * Path: admin/game_setup/step2_game_definition.php
 * Source: MG19 Slide 7 Baseline Data Requirements
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$gameId = Session::get('setup_game_id');
if (!$gameId) {
    header('Location: step1_title_case_study.php');
    exit;
}

$game = $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]);
$existingYears = $db->fetchAll("SELECT * FROM game_market_year WHERE game_id = :g ORDER BY year_no", ['g' => $gameId]);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $noOfYears        = (int) ($_POST['no_of_years'] ?? 0);
    $currency         = trim($_POST['currency'] ?? 'INR');
    $unitOfMeasure    = trim($_POST['unit_of_measure'] ?? 'Numbers');
    $demand           = (int) ($_POST['demand'] ?? 0);
    $startingCash     = (float) ($_POST['starting_cash'] ?? 0);
    $capacityCost     = (float) ($_POST['capacity_cost'] ?? 0);
    $minCapacity      = (int) ($_POST['minimum_capacity'] ?? 0);
    $maxCapacity      = (int) ($_POST['maximum_capacity'] ?? 0);
    $capacityIncrement= (int) ($_POST['capacity_increment'] ?? 1);
    $startingCapacity = (int) ($_POST['starting_capacity'] ?? 0);

    if ($noOfYears < 1 || !Validator::required($currency) || !Validator::required($unitOfMeasure) || $startingCapacity <= 0) {
        $error = 'Please fill in all required fields correctly.';
    } else {
        Game::updateDefinition($gameId, [
            'no_of_years'        => $noOfYears,
            'unit_of_measure'    => $unitOfMeasure,
            'currency'           => $currency,
            'demand'             => $demand,
            'starting_cash'      => $startingCash,
            'capacity_cost'      => $capacityCost,
            'minimum_capacity'   => $minCapacity,
            'maximum_capacity'   => $maxCapacity,
            'capacity_increment' => $capacityIncrement,
            'starting_capacity'  => $startingCapacity,
        ]);

        // Rebuild annual market years to match no_of_years
        $db->query("DELETE FROM game_market_year WHERE game_id = :g", ['g' => $gameId]);
        for ($y = 1; $y <= $noOfYears; $y++) {
            $marketDemand     = (int) ($_POST['market_demand'][$y] ?? 0);
            $inflationPercent = (float) ($_POST['inflation_percent'][$y] ?? 0);
            $db->insert('game_market_year', [
                'game_id'          => $gameId,
                'year_no'          => $y,
                'market_demand'    => $marketDemand,
                'inflation_percent'=> $inflationPercent,
            ]);
        }

        Session::setFlash('success', 'Step 2 saved.');
        header('Location: step3_capacity_drivers.php');
        exit;
    }
}

// Initial defaults
$currencyVal      = htmlspecialchars($game['currency'] ?? 'INR');
$uomVal           = htmlspecialchars($game['unit_of_measure'] ?? 'Numbers');
$noOfYearsVal     = (int) ($game['no_of_years'] ?? DEFAULT_NO_OF_YEARS);
$startingCashVal  = (float) ($game['starting_cash'] ?? 0);
$demandVal        = (int) ($game['demand'] ?? 0);
$startingCapVal   = (int) ($game['starting_capacity'] ?? 0);
$capacityCostVal  = (float) ($game['capacity_cost'] ?? 0);
$minCapVal        = (int) ($game['minimum_capacity'] ?? 0);
$maxCapVal        = (int) ($game['maximum_capacity'] ?? 0);
$capIncVal        = (int) ($game['capacity_increment'] ?? 1);

// Ensure we have rows for initial display
if (empty($existingYears)) {
    $existingYears = [];
    for ($y = 1; $y <= $noOfYearsVal; $y++) {
        $existingYears[] = [
            'year_no'           => $y,
            'market_demand'     => $demandVal > 0 ? $demandVal : 0,
            'inflation_percent' => 5,
        ];
    }
}

$pageTitle = 'Game Setup - Step 2';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-stepper">
    <div class="mcqg-stepper-fill" style="width:15%"></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Title &amp; Case Study</div></div>
    <div class="mcqg-step active"><div class="mcqg-step-circle">2</div><div class="mcqg-step-label">Game Definition</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">3</div><div class="mcqg-step-label">Capacity Drivers</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">4</div><div class="mcqg-step-label">Demand Drivers</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">5</div><div class="mcqg-step-label">Investments</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">6</div><div class="mcqg-step-label">Configuration</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">7</div><div class="mcqg-step-label">Publish</div></div>
  </div>

  <div class="mcqg-wizard-panel">
    <h3>Step 2 of 7 &mdash; Game Definition</h3>
    <p class="text-muted">Core numbers for this game. Enter starting baseline data and annual period parameters below.</p>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="POST" id="mcqg-wizard-form">
      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <label class="form-label fw-bold">Number of Periods</label>
          <input type="number" min="1" id="no_of_years" name="no_of_years" class="form-control mcqg-input-live" required
                 value="<?php echo $noOfYearsVal; ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Currency</label>
          <input type="text" id="currency" name="currency" class="form-control mcqg-input-live" placeholder="e.g. INR, USD" required
                 value="<?php echo $currencyVal; ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Unit of Measure</label>
          <input type="text" id="unit_of_measure" name="unit_of_measure" class="form-control mcqg-input-live" placeholder="e.g. Numbers, Units" required
                 value="<?php echo $uomVal; ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Initial Fund</label>
          <div class="input-group">
            <input type="number" step="any" id="starting_cash" name="starting_cash" class="form-control mcqg-input-live" required
                   value="<?php echo $startingCashVal; ?>">
            <span class="input-group-text currency-suffix fw-semibold"><?php echo $currencyVal; ?></span>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <label class="form-label fw-bold">Demand</label>
          <div class="input-group">
            <input type="number" id="demand" name="demand" class="form-control mcqg-input-live" value="<?php echo $demandVal; ?>">
            <span class="input-group-text uom-suffix fw-semibold"><?php echo $uomVal; ?></span>
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Starting Capacity</label>
          <div class="input-group">
            <input type="number" id="starting_capacity" name="starting_capacity" class="form-control mcqg-input-live" required
                   value="<?php echo $startingCapVal; ?>">
            <span class="input-group-text uom-suffix fw-semibold"><?php echo $uomVal; ?></span>
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Capacity Cost</label>
          <div class="input-group">
            <input type="number" step="any" id="capacity_cost" name="capacity_cost" class="form-control mcqg-input-live" value="<?php echo $capacityCostVal; ?>">
            <span class="input-group-text currency-suffix fw-semibold"><?php echo $currencyVal; ?></span>
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Capacity Increment</label>
          <div class="input-group">
            <input type="number" id="capacity_increment" name="capacity_increment" class="form-control" value="<?php echo $capIncVal; ?>">
            <span class="input-group-text uom-suffix fw-semibold"><?php echo $uomVal; ?></span>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <label class="form-label fw-bold">Minimum Capacity</label>
          <div class="input-group">
            <input type="number" id="minimum_capacity" name="minimum_capacity" class="form-control" value="<?php echo $minCapVal; ?>">
            <span class="input-group-text uom-suffix fw-semibold"><?php echo $uomVal; ?></span>
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Maximum Capacity</label>
          <div class="input-group">
            <input type="number" id="maximum_capacity" name="maximum_capacity" class="form-control" value="<?php echo $maxCapVal; ?>">
            <span class="input-group-text uom-suffix fw-semibold"><?php echo $uomVal; ?></span>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold">Unit Cost (auto-calculated, read-only)</label>
          <div class="input-group">
            <input type="text" id="unit_cost_display" class="form-control" readonly style="background:#f1f5f9; font-weight:700;">
          </div>
        </div>
      </div>

      <div class="border-top pt-4 mt-4">
        <h5 class="fw-bold mb-1">Annual Parameters</h5>
        <p class="text-muted small mb-3">One row per period, matching "Number of Periods" above.</p>

        <div class="table-responsive">
          <table class="table table-bordered align-middle" style="max-width: 750px;">
            <thead class="table-light">
              <tr>
                <th style="width: 140px;">Period</th>
                <th>Demand</th>
                <th>Estimated demand growth %</th>
              </tr>
            </thead>
            <tbody id="annual-parameters-table-body">
              <?php foreach ($existingYears as $row):
                $yNo = (int) $row['year_no'];
                $mDemand = (int) $row['market_demand'];
                $infPerc = (float) $row['inflation_percent'];
              ?>
                <tr data-year="<?php echo $yNo; ?>">
                  <td class="fw-bold">Year <?php echo $yNo; ?></td>
                  <td>
                    <div class="input-group input-group-sm">
                      <input type="number" class="form-control mcqg-input-live market-demand-input"
                             name="market_demand[<?php echo $yNo; ?>]"
                             placeholder="Demand" value="<?php echo $mDemand; ?>" required>
                      <span class="input-group-text uom-suffix fw-semibold"><?php echo $uomVal; ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="input-group input-group-sm">
                      <input type="number" step="0.01" class="form-control mcqg-input-live"
                             name="inflation_percent[<?php echo $yNo; ?>]"
                             placeholder="Growth %" value="<?php echo $infPerc; ?>" required>
                      <span class="input-group-text fw-bold">%</span>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mcqg-wizard-nav mt-4">
        <a href="step1_title_case_study.php" class="btn btn-mcqg-outline">Back</a>
        <button type="submit" id="mcqg-next-btn" class="btn btn-mcqg-primary">NEXT</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Dynamic Suffix Updates
  function updateSuffixes() {
    const curr = document.getElementById('currency').value.trim() || 'INR';
    const uom  = document.getElementById('unit_of_measure').value.trim() || 'Numbers';

    document.querySelectorAll('.currency-suffix').forEach(el => el.textContent = curr);
    document.querySelectorAll('.uom-suffix').forEach(el => el.textContent = uom);
    refreshUnitCost();
  }

  // Live Unit-Cost Calculation (No Decimals as required by Slide 7 rules)
  function refreshUnitCost() {
    const cost = parseFloat(document.getElementById('capacity_cost').value) || 0;
    const cap  = parseFloat(document.getElementById('starting_capacity').value) || 0;
    const curr = document.getElementById('currency').value.trim() || 'INR';
    
    if (cap > 0 && cost > 0) {
      const unitVal = Math.round(cost / cap); // Currency with no decimals
      document.getElementById('unit_cost_display').value = curr + ' ' + unitVal.toLocaleString();
    } else {
      document.getElementById('unit_cost_display').value = curr + ' 0';
    }
  }

  // Dynamic row generation when Number of Periods changes
  function updatePeriodRows() {
    const numPeriods = parseInt(document.getElementById('no_of_years').value) || 1;
    const tbody = document.getElementById('annual-parameters-table-body');
    const uom = document.getElementById('unit_of_measure').value.trim() || 'Numbers';
    const currentRows = tbody.querySelectorAll('tr');
    const currentCount = currentRows.length;

    if (numPeriods > currentCount) {
      for (let y = currentCount + 1; y <= numPeriods; y++) {
        const tr = document.createElement('tr');
        tr.setAttribute('data-year', y);
        tr.innerHTML = `
          <td class="fw-bold">Year ${y}</td>
          <td>
            <div class="input-group input-group-sm">
              <input type="number" class="form-control mcqg-input-live market-demand-input"
                     name="market_demand[${y}]" placeholder="Demand" value="0" required>
              <span class="input-group-text uom-suffix fw-semibold">${uom}</span>
            </div>
          </td>
          <td>
            <div class="input-group input-group-sm">
              <input type="number" step="0.01" class="form-control mcqg-input-live"
                     name="inflation_percent[${y}]" placeholder="Growth %" value="5" required>
              <span class="input-group-text fw-bold">%</span>
            </div>
          </td>
        `;
        tbody.appendChild(tr);
      }
    } else if (numPeriods < currentCount && numPeriods >= 1) {
      for (let y = currentCount; y > numPeriods; y--) {
        const lastRow = tbody.querySelector(`tr[data-year="${y}"]`);
        if (lastRow) lastRow.remove();
      }
    }
    updateSuffixes();
  }

  document.getElementById('currency').addEventListener('input', updateSuffixes);
  document.getElementById('unit_of_measure').addEventListener('input', updateSuffixes);
  document.getElementById('capacity_cost').addEventListener('input', refreshUnitCost);
  document.getElementById('starting_capacity').addEventListener('input', refreshUnitCost);
  document.getElementById('no_of_years').addEventListener('change', updatePeriodRows);
  document.getElementById('no_of_years').addEventListener('input', updatePeriodRows);

  // Initial calculation on page load
  updateSuffixes();
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
