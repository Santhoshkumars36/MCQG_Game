<?php
/**
 * MCQG Admin - Game Setup Step 5: Investments
 * Path: admin/game_setup/step5_investments.php
 * Source: MG19 Slide 6
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$gameId = Session::get('setup_game_id');
if (!$gameId) { header('Location: step1_title_case_study.php'); exit; }

$capacityDrivers = $db->fetchAll("SELECT * FROM capacity_driver WHERE game_id = :g", ['g' => $gameId]);
$demandDrivers = $db->fetchAll("SELECT * FROM demand_driver WHERE game_id = :g", ['g' => $gameId]);
$investments = $db->fetchAll("SELECT * FROM investment_master WHERE game_id = :g ORDER BY display_order", ['g' => $gameId]);
$error = '';
$success = '';

// Add a new investment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_investment'])) {
    $name = trim($_POST['investment_name'] ?? '');
    if (!Validator::required($name)) {
        $error = 'Please enter an investment name.';
    } else {
        $investmentId = $db->insert('investment_master', [
            'game_id'               => $gameId,
            'investment_name'       => $name,
            'description'           => trim($_POST['description'] ?? ''),
            'min_investment_value'  => (float) ($_POST['min_investment_value'] ?? 0),
            'max_investment_value'  => (float) ($_POST['max_investment_value'] ?? 0),
            'increment_value'       => (float) ($_POST['increment_value'] ?? 1),
            'effective_from'        => 'Immediate',
            'repeat_allowed'        => isset($_POST['repeat_allowed']) ? 1 : 0,
            'purchase_limit'        => 1,
            'display_order'         => count($investments) + 1,
            'active'                => 1,
        ]);

        $driverTypes = $_POST['driver_type'] ?? [];
        $driverIds = $_POST['driver_id'] ?? [];
        $effectDirections = $_POST['effect_direction'] ?? [];

        foreach ($driverTypes as $i => $type) {
            if (!Validator::required($driverIds[$i] ?? '')) continue;
            $dir = ($effectDirections[$i] ?? 'Negative') === 'Positive' ? 'Positive' : 'Negative';
            $val = ($dir === 'Positive') ? 1.0 : -1.0;
            $injectMessage = "{$name} " . ($dir === 'Positive' ? 'increases' : 'reduces') . " the linked {$type} driver.";
            $db->insert('investment_effect', [
                'investment_id'    => $investmentId,
                'driver_type'      => $type,
                'driver_id'        => (int) $driverIds[$i],
                'min_percent'      => $val,
                'max_percent'      => $val,
                'increment_percent'=> 0,
                'inject_message'   => $injectMessage,
            ]);
        }

        $success = "Investment '{$name}' added.";
        $investments = $db->fetchAll("SELECT * FROM investment_master WHERE game_id = :g ORDER BY display_order", ['g' => $gameId]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['go_next'])) {
    if (count($investments) === 0) {
        $error = 'Please add at least one investment before continuing.';
    } else {
        header('Location: step6_publish.php');
        exit;
    }
}

$pageTitle = 'Game Setup - Step 5';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-stepper">
    <div class="mcqg-stepper-fill" style="width:80%"></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Title &amp; Case Study</div></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Game Definition</div></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Capacity Drivers</div></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Demand Drivers</div></div>
    <div class="mcqg-step active"><div class="mcqg-step-circle">5</div><div class="mcqg-step-label">Investments</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">6</div><div class="mcqg-step-label">Publish</div></div>
  </div>

  <div class="mcqg-wizard-panel">
    <h3>Step 5 of 6 &mdash; Investments</h3>
    <p class="text-muted">Build the catalogue of investments teams can buy, and map each to Capacity/Demand Drivers.</p>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <!-- Existing investments -->
    <table class="table table-sm mb-4">
      <thead><tr><th>Investment</th><th>Min</th><th>Max</th><th>Increment</th></tr></thead>
      <tbody>
      <?php foreach ($investments as $inv): ?>
        <tr>
          <td class="fw-bold"><?php echo htmlspecialchars($inv['investment_name']); ?></td>
          <td><?php echo number_format($inv['min_investment_value'], 0); ?></td>
          <td><?php echo number_format($inv['max_investment_value'], 0); ?></td>
          <td><?php echo number_format($inv['increment_value'], 0); ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!count($investments)): ?>
        <tr><td colspan="4" class="text-muted text-center py-3">No investments added yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>

    <!-- Add investment form -->
    <div class="mcqg-driver-group-card">
      <h5>Add New Investment</h5>
      <form method="POST">
        <input type="hidden" name="add_investment" value="1">
        <div class="row g-2 mb-2">
          <div class="col-md-4"><input type="text" id="investment_name" name="investment_name" class="form-control" placeholder="Investment Name" required></div>
          <div class="col-md-8"><input type="text" name="description" class="form-control" placeholder="Description"></div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-md-4"><input type="number" step="0.01" name="min_investment_value" class="form-control" placeholder="Min Value" required></div>
          <div class="col-md-4"><input type="number" step="0.01" name="max_investment_value" class="form-control" placeholder="Max Value" required></div>
          <div class="col-md-4"><input type="number" step="0.01" name="increment_value" class="form-control" placeholder="Increment" required></div>
        </div>
        <div class="form-check mb-3">
          <input type="checkbox" class="form-check-input" name="repeat_allowed" id="repeat_allowed">
          <label class="form-check-label" for="repeat_allowed">Repeat purchase allowed</label>
        </div>

        <h6>Driver Mapping (effect on Capacity/Demand Drivers)</h6>
        <div id="mcqg-mapping-rows">
          <div class="row g-2 mb-2 mcqg-mapping-row align-items-center">
            <div class="col-md-4">
              <select class="form-select" name="driver_type[]">
                <option value="Capacity">Capacity Driver</option>
                <option value="Demand">Demand Driver</option>
              </select>
            </div>
            <div class="col-md-4">
              <select class="form-select" name="driver_id[]">
                <optgroup label="Capacity Drivers">
                  <?php foreach ($capacityDrivers as $cd): ?>
                    <option value="<?php echo (int) $cd['driver_id']; ?>"><?php echo htmlspecialchars($cd['driver_name']); ?></option>
                  <?php endforeach; ?>
                </optgroup>
                <optgroup label="Demand Drivers">
                  <?php foreach ($demandDrivers as $dd): ?>
                    <option value="<?php echo (int) $dd['driver_id']; ?>"><?php echo htmlspecialchars($dd['driver_name']); ?></option>
                  <?php endforeach; ?>
                </optgroup>
              </select>
            </div>
            <div class="col-md-3">
              <select class="form-select" name="effect_direction[]">
                <option value="Negative">Negative (-) Reduces driver share</option>
                <option value="Positive">Positive (+) Increases driver share</option>
              </select>
            </div>
            <div class="col-md-1 text-center">
              <button type="button" class="btn btn-sm btn-outline-danger mcqg-remove-row">&times;</button>
            </div>
          </div>
        </div>
        <button type="button" id="mcqg-add-mapping-btn" class="btn btn-mcqg-outline btn-sm mb-2">+ Add Mapping</button>

        <p class="small text-muted" id="inject-message-preview">Inject message will be auto-generated once saved.</p>

        <button type="submit" class="btn btn-mcqg-gold">Add Investment</button>
      </form>
    </div>

    <form method="POST" class="mcqg-wizard-nav">
      <a href="step4_demand_drivers.php" class="btn btn-mcqg-outline">Back</a>
      <button type="submit" name="go_next" value="1" id="mcqg-next-btn" class="btn btn-mcqg-primary">NEXT</button>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
