<?php
/**
 * MCQG Admin - Game Setup Step 2: Game Definition
 * Path: admin/game_setup/step2_game_definition.php
 * Source: MG19 Slide 4
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$gameId = Session::get('setup_game_id');
if (!$gameId) { header('Location: step1_title_case_study.php'); exit; }

$game = $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]);
$existingYears = $db->fetchAll("SELECT * FROM game_market_year WHERE game_id = :g ORDER BY year_no", ['g' => $gameId]);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $noOfYears = (int) ($_POST['no_of_years'] ?? 0);
    $currency = trim($_POST['currency'] ?? '');
    $demand = (int) ($_POST['demand'] ?? 0);
    $capacityCost = (float) ($_POST['capacity_cost'] ?? 0);
    $minCapacity = (int) ($_POST['minimum_capacity'] ?? 0);
    $maxCapacity = (int) ($_POST['maximum_capacity'] ?? 0);
    $capacityIncrement = (int) ($_POST['capacity_increment'] ?? 1);
    $startingCapacity = (int) ($_POST['starting_capacity'] ?? 0);

    if ($noOfYears < 1 || !Validator::required($currency) || $startingCapacity <= 0) {
        $error = 'Please fill in all required fields correctly.';
    } else {
        $db->update('game_master', [
            'no_of_years'        => $noOfYears,
            'currency'           => $currency,
            'demand'             => $demand,
            'capacity_cost'      => $capacityCost,
            'minimum_capacity'   => $minCapacity,
            'maximum_capacity'   => $maxCapacity,
            'capacity_increment' => $capacityIncrement,
            'starting_capacity'  => $startingCapacity,
        ], 'game_id = :g', ['g' => $gameId]);

        // Rebuild annual market years to match no_of_years (MG19 Slide 4 rule)
        $db->query("DELETE FROM game_market_year WHERE game_id = :g", ['g' => $gameId]);
        for ($y = 1; $y <= $noOfYears; $y++) {
            $db->insert('game_market_year', [
                'game_id'          => $gameId,
                'year_no'          => $y,
                'market_demand'    => (int) ($_POST['market_demand'][$y] ?? 0),
                'inflation_percent'=> (float) ($_POST['inflation_percent'][$y] ?? 0),
            ]);
        }

        Session::setFlash('success', 'Step 2 saved.');
        header('Location: step3_capacity_drivers.php');
        exit;
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
    <p class="text-muted">Core numbers for this game. Currency is free text (not a dropdown). Unit cost is auto-calculated.</p>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="POST" id="mcqg-wizard-form">
      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <label class="form-label fw-bold">Number of Periods</label>
          <input type="number" min="1" id="no_of_years" name="no_of_years" class="form-control mcqg-input-live" required
                 value="<?php echo (int) ($game['no_of_years'] ?? DEFAULT_NO_OF_YEARS); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Currency</label>
          <input type="text" name="currency" class="form-control mcqg-input-live" placeholder="e.g. INR, USD" required
                 value="<?php echo htmlspecialchars($game['currency'] ?? ''); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Demand</label>
          <input type="number" name="demand" class="form-control" value="<?php echo (int) ($game['demand'] ?? 0); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Starting Capacity</label>
          <input type="number" id="starting_capacity" name="starting_capacity" class="form-control mcqg-input-live" required
                 value="<?php echo (int) ($game['starting_capacity'] ?? 0); ?>">
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <label class="form-label fw-bold">Capacity Cost</label>
          <input type="number" step="0.01" id="capacity_cost" name="capacity_cost" class="form-control" value="<?php echo (float) ($game['capacity_cost'] ?? 0); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Minimum Capacity</label>
          <input type="number" name="minimum_capacity" class="form-control" value="<?php echo (int) ($game['minimum_capacity'] ?? 0); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Maximum Capacity</label>
          <input type="number" name="maximum_capacity" class="form-control" value="<?php echo (int) ($game['maximum_capacity'] ?? 0); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Capacity Increment</label>
          <input type="number" name="capacity_increment" class="form-control" value="<?php echo (int) ($game['capacity_increment'] ?? 1); ?>">
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-bold">Unit Cost (auto-calculated, read-only)</label>
        <input type="text" id="unit_cost_display" class="form-control" disabled style="background:#f0f2f8; font-weight:700;">
      </div>

      <h5 class="mt-4">Annual Parameters</h5>
      <p class="text-muted small">One row is generated automatically per period, matching "Number of Periods" above.</p>
      <div id="annual-parameters-rows">
        <?php foreach ($existingYears as $row): ?>
          <div class="row g-2 mb-2 align-items-center">
            <div class="col-2"><span class="fw-bold">Year <?php echo (int) $row['year_no']; ?></span></div>
            <div class="col-4">
              <input type="number" class="form-control mcqg-input-live" name="market_demand[<?php echo (int) $row['year_no']; ?>]"
                     placeholder="Market Demand" value="<?php echo (int) $row['market_demand']; ?>" required>
            </div>
            <div class="col-4">
              <div class="input-group">
                <input type="number" step="0.01" class="form-control mcqg-input-live" name="inflation_percent[<?php echo (int) $row['year_no']; ?>]"
                       placeholder="Inflation" value="<?php echo (float) $row['inflation_percent']; ?>" required>
                <span class="input-group-text fw-bold">%</span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="mcqg-wizard-nav">
        <a href="step1_title_case_study.php" class="btn btn-mcqg-outline">Back</a>
        <button type="submit" id="mcqg-next-btn" class="btn btn-mcqg-primary">NEXT</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Live unit-cost preview: Capacity cost / Starting Capacity (MG19 Slide 4)
  function refreshUnitCost() {
    const cost = parseFloat(document.getElementById('capacity_cost').value) || 0;
    const cap = parseFloat(document.getElementById('starting_capacity').value) || 0;
    const unit = cap > 0 ? (cost / cap).toFixed(2) : '0.00';
    document.getElementById('unit_cost_display').value = unit;
  }
  document.getElementById('capacity_cost').addEventListener('input', refreshUnitCost);
  document.getElementById('starting_capacity').addEventListener('input', refreshUnitCost);
  refreshUnitCost();
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
