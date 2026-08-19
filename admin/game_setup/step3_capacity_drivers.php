<?php
/**
 * MCQG Admin - Game Setup Step 3: Capacity Drivers
 * Path: admin/game_setup/step3_capacity_drivers.php
 * Source: MG19 Slide 8 Capacity & Demand Drivers
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
$drivers = $db->fetchAll("SELECT * FROM capacity_driver WHERE game_id = :g", ['g' => $gameId]);
$error = '';

$startingCap = (float)($game['starting_capacity'] ?? 0);
$capCost     = (float)($game['capacity_cost'] ?? 0);
$unitCost    = $startingCap > 0 ? ($capCost / $startingCap) : 0;
$currency    = htmlspecialchars($game['currency'] ?? 'INR');
$tolerance   = (float)($game['tolerance_percent'] ?? 20.00);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupNames   = $_POST['group_name'] ?? [];
    $driverNames  = $_POST['driver_name'] ?? [];
    $costShares   = $_POST['cost_share_percent'] ?? [];
    $toleranceVal = (float)($_POST['tolerance_percent'] ?? 20.00);

    $total = array_sum(array_map('floatval', $costShares));

    if (empty($groupNames)) {
        $error = "Please add at least one capacity driver.";
    } elseif ($total <= 0) {
        $error = "Capacity drivers cost share total must be greater than 0%.";
    } elseif ($total > 100) {
        $error = "Capacity drivers cost share total cannot exceed 100% (currently {$total}%).";
    } else {
        // Save Tolerance % to game_master
        $db->update('game_master', [
            'tolerance_percent'             => $toleranceVal,
            'sales_price_tolerance_percent' => $toleranceVal,
        ], 'game_id = :g', ['g' => $gameId]);

        $db->query("DELETE FROM capacity_driver WHERE game_id = :g", ['g' => $gameId]);
        foreach ($groupNames as $i => $group) {
            if (trim($group) === '') continue;
            $db->insert('capacity_driver', [
                'game_id'            => $gameId,
                'group_name'         => trim($group),
                'driver_name'        => trim($driverNames[$i] ?? ''),
                'cost_share_percent' => (float) $costShares[$i],
            ]);
        }
        Session::setFlash('success', 'Step 3 saved - Capacity Drivers updated.');
        header('Location: step4_demand_drivers.php');
        exit;
    }
}

$pageTitle = 'Game Setup - Step 3';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-stepper">
    <div class="mcqg-stepper-fill" style="width:30%"></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Title &amp; Case Study</div></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Game Definition</div></div>
    <div class="mcqg-step active"><div class="mcqg-step-circle">3</div><div class="mcqg-step-label">Capacity Drivers</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">4</div><div class="mcqg-step-label">Demand Drivers</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">5</div><div class="mcqg-step-label">Investments</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">6</div><div class="mcqg-step-label">Configuration</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">7</div><div class="mcqg-step-label">Publish</div></div>
  </div>

  <div class="mcqg-wizard-panel">
    <h3>Step 3 of 7 &mdash; Capacity Drivers</h3>
    <p class="text-muted">Break down unit production cost by group and driver. Cost values auto-calculate to 1 decimal place.</p>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="POST" id="mcqg-wizard-form">
      <div class="row g-3 mb-4 p-3 rounded" style="background:#f8fafc; border:1px solid #e2e8f0;">
        <div class="col-md-6">
          <label class="form-label fw-bold mb-1">Derived Unit Cost (from Step 2)</label>
          <div class="input-group">
            <span class="input-group-text fw-bold"><?php echo $currency; ?></span>
            <input type="text" class="form-control fw-bold" readonly value="<?php echo number_format($unitCost, 1); ?>" style="background:#fff;">
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold mb-1">Tolerance %</label>
          <div class="input-group">
            <input type="number" step="0.01" name="tolerance_percent" id="tolerance_percent" class="form-control fw-bold" value="<?php echo $tolerance; ?>" required>
            <span class="input-group-text fw-bold">%</span>
          </div>
          <div class="form-text small">Default: 20%. Allowed range for driver adjustments.</div>
        </div>
      </div>

      <div class="table-responsive mb-3">
        <table class="table table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th style="width: 25%;">Group</th>
              <th style="width: 35%;">Driver</th>
              <th style="width: 20%;">%cost</th>
              <th style="width: 15%;">Cost value</th>
              <th style="width: 5%; text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody id="capacity-driver-rows">
            <?php
            $rows = count($drivers) ? $drivers : [
              ['group_name' => 'Production', 'driver_name' => 'Labor', 'cost_share_percent' => 10],
              ['group_name' => 'Production', 'driver_name' => 'Utility', 'cost_share_percent' => 6],
              ['group_name' => 'Administration', 'driver_name' => 'Rentals', 'cost_share_percent' => 6],
              ['group_name' => 'Quality', 'driver_name' => 'Testing', 'cost_share_percent' => 5],
              ['group_name' => 'Packaging', 'driver_name' => 'Packing material', 'cost_share_percent' => 3],
              ['group_name' => 'Material cost', 'driver_name' => 'Direct material', 'cost_share_percent' => 39],
              ['group_name' => 'Material cost', 'driver_name' => 'Indirect material', 'cost_share_percent' => 9],
            ];
            foreach ($rows as $d):
              $pct = (float) $d['cost_share_percent'];
              $val = ($pct / 100) * $unitCost;
            ?>
            <tr class="mcqg-driver-row">
              <td><input type="text" class="form-control" name="group_name[]" placeholder="e.g. Production" value="<?php echo htmlspecialchars($d['group_name']); ?>" required></td>
              <td><input type="text" class="form-control" name="driver_name[]" placeholder="e.g. Labor" value="<?php echo htmlspecialchars($d['driver_name']); ?>" required></td>
              <td>
                <div class="input-group">
                  <input type="number" step="0.01" class="form-control capacity-driver-percent" name="cost_share_percent[]" placeholder="10" value="<?php echo htmlspecialchars((string)$pct); ?>" required>
                  <span class="input-group-text">%</span>
                </div>
              </td>
              <td>
                <input type="text" class="form-control cost-value-display fw-bold" readonly value="<?php echo number_format($val, 1, '.', ''); ?>" style="background:#f1f5f9;">
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger mcqg-remove-driver-row">&times;</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <button type="button" id="mcqg-add-capacity-driver" class="btn btn-mcqg-outline btn-sm mb-3">+ Add Driver</button>

      <div id="capacity-percent-total-bar" class="mcqg-percent-total-bar">
        <div class="mcqg-percent-total-fill under" style="width:0%"></div>
      </div>
      <div id="capacity-percent-readout" class="mcqg-percent-readout mb-3"></div>

      <div class="mcqg-wizard-nav">
        <a href="step2_game_definition.php" class="btn btn-mcqg-outline">Back</a>
        <button type="submit" id="mcqg-save-drivers-btn" class="btn btn-mcqg-primary">NEXT</button>
      </div>
    </form>
  </div>
</div>

<script>
  const unitCost = <?php echo (float) $unitCost; ?>;

  function updateCostValues() {
    let totalPct = 0;
    document.querySelectorAll('#capacity-driver-rows .mcqg-driver-row').forEach(row => {
      const pctInput = row.querySelector('.capacity-driver-percent');
      const valInput = row.querySelector('.cost-value-display');
      const pct = parseFloat(pctInput ? pctInput.value : 0) || 0;
      totalPct += pct;
      if (valInput) {
        const val = (pct / 100) * unitCost;
        valInput.value = val.toFixed(1); // Format to 1 decimal place
      }
    });

    const fill = document.querySelector('#capacity-percent-total-bar .mcqg-percent-total-fill');
    const readout = document.getElementById('capacity-percent-readout');
    if (fill && readout) {
      const pctClamped = Math.min(totalPct, 100);
      fill.style.width = pctClamped + '%';
      fill.className = 'mcqg-percent-total-fill ' + (totalPct === 100 ? 'exact' : (totalPct > 100 ? 'over' : 'under'));
      readout.textContent = 'Capacity Drivers Total: ' + totalPct.toFixed(2) + '%';
      readout.className = 'mcqg-percent-readout ' + (totalPct > 100 ? 'text-danger fw-bold' : 'text-muted');
    }
  }

  document.getElementById('mcqg-add-capacity-driver').addEventListener('click', function () {
    const container = document.getElementById('capacity-driver-rows');
    const tr = document.createElement('tr');
    tr.className = 'mcqg-driver-row';
    tr.innerHTML = `
      <td><input type="text" class="form-control" name="group_name[]" placeholder="Group Name" required></td>
      <td><input type="text" class="form-control" name="driver_name[]" placeholder="Driver Name" required></td>
      <td>
        <div class="input-group">
          <input type="number" step="0.01" class="form-control capacity-driver-percent" name="cost_share_percent[]" placeholder="0" required>
          <span class="input-group-text">%</span>
        </div>
      </td>
      <td>
        <input type="text" class="form-control cost-value-display fw-bold" readonly value="0.0" style="background:#f1f5f9;">
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger mcqg-remove-driver-row">&times;</button>
      </td>`;
    container.appendChild(tr);
    updateCostValues();
  });

  document.getElementById('capacity-driver-rows').addEventListener('click', function (e) {
    if (e.target.classList.contains('mcqg-remove-driver-row')) {
      e.target.closest('.mcqg-driver-row').remove();
      updateCostValues();
    }
  });

  document.getElementById('capacity-driver-rows').addEventListener('input', function (e) {
    if (e.target.classList.contains('capacity-driver-percent')) {
      updateCostValues();
    }
  });

  updateCostValues();
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
