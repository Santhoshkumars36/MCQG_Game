<?php
/**
 * MCQG Admin - Game Setup Step 4: Demand Drivers
 * Path: admin/game_setup/step4_demand_drivers.php
 * Source: MG19 Slide 8 (Demand driver section)
 * Validation: Capacity drivers + Demand drivers must sum to 100% combined.
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$gameId = Session::get('setup_game_id');
if (!$gameId) {
    header('Location: step1_title_case_study.php');
    exit;
}

$game            = $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]);
$drivers         = $db->fetchAll("SELECT * FROM demand_driver WHERE game_id = :g", ['g' => $gameId]);
$capacityDrivers = $db->fetchAll("SELECT SUM(cost_share_percent) AS cap_total FROM capacity_driver WHERE game_id = :g", ['g' => $gameId]);
$capTotal        = (float)($capacityDrivers[0]['cap_total'] ?? 0);
$error           = '';

$startingCap = (float)($game['starting_capacity'] ?? 0);
$capCost     = (float)($game['capacity_cost'] ?? 0);
$unitCost    = $startingCap > 0 ? ($capCost / $startingCap) : 0;
$currency    = htmlspecialchars($game['currency'] ?? 'INR');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupNames   = $_POST['group_name'] ?? [];
    $driverNames  = $_POST['driver_name'] ?? [];
    $demandShares = $_POST['demand_share_percent'] ?? [];

    $demandTotal = array_sum(array_map('floatval', $demandShares));
    $combined    = round($capTotal + $demandTotal, 2);

    if (empty($groupNames)) {
        $error = 'Please add at least one demand driver.';
    } elseif ($combined != 100.00) {
        $error = "Capacity Drivers ({$capTotal}%) + Demand Drivers (" . round($demandTotal, 2) . "%) = {$combined}% — must equal exactly 100%.";
    } else {
        $db->query("DELETE FROM demand_driver WHERE game_id = :g", ['g' => $gameId]);
        foreach ($groupNames as $i => $group) {
            if (trim($group) === '') continue;
            $db->insert('demand_driver', [
                'game_id'              => $gameId,
                'group_name'           => trim($group),
                'driver_name'          => trim($driverNames[$i] ?? ''),
                'demand_share_percent' => (float)$demandShares[$i],
            ]);
        }
        Session::setFlash('success', 'Step 4 saved — combined drivers total 100%.');
        header('Location: step5_investments.php');
        exit;
    }
}

$pageTitle = 'Game Setup - Step 4';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-stepper">
    <div class="mcqg-stepper-fill" style="width:45%"></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Title &amp; Case Study</div></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Game Definition</div></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Capacity Drivers</div></div>
    <div class="mcqg-step active"><div class="mcqg-step-circle">4</div><div class="mcqg-step-label">Demand Drivers</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">5</div><div class="mcqg-step-label">Investments</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">6</div><div class="mcqg-step-label">Configuration</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">7</div><div class="mcqg-step-label">Publish</div></div>
  </div>

  <div class="mcqg-wizard-panel">
    <h3>Step 4 of 7 &mdash; Demand Drivers</h3>
    <p class="text-muted">
      Cost share % for Capacity Drivers + Demand Drivers must total <strong>100%</strong> combined.
      Cost values show the monetary value at 1 decimal place.
    </p>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <!-- Combined 100% summary banner -->
    <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded" style="background:#f8fafc; border:1px solid #e2e8f0;">
      <div>
        <span class="fw-bold small text-muted">Capacity Drivers Used:</span>
        <span class="badge bg-primary ms-1" id="cap-total-badge"><?php echo number_format($capTotal, 2); ?>%</span>
      </div>
      <div>
        <span class="fw-bold small text-muted">Demand Drivers Total:</span>
        <span class="badge bg-secondary ms-1" id="demand-total-badge">0%</span>
      </div>
      <div>
        <span class="fw-bold small text-muted">Combined Total:</span>
        <span class="badge ms-1" id="combined-total-badge" style="background:#6b7280;">0%</span>
      </div>
      <div class="ms-auto">
        <span class="fw-bold small text-muted">Remaining for Demand:</span>
        <span class="badge bg-warning text-dark ms-1" id="remaining-badge"><?php echo number_format(100 - $capTotal, 2); ?>%</span>
      </div>
    </div>

    <form method="POST" id="mcqg-wizard-form">
      <div class="table-responsive mb-3">
        <table class="table table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th style="width: 25%;">Group</th>
              <th style="width: 35%;">Driver</th>
              <th style="width: 20%;">%cost</th>
              <th style="width: 15%;">Cost value</th>
              <th style="width: 5%; text-align:center;">Actions</th>
            </tr>
          </thead>
          <tbody id="demand-driver-rows">
            <?php
            $rows = count($drivers) ? $drivers : [
              ['group_name' => 'Marketing', 'driver_name' => 'Advertisement', 'demand_share_percent' => 4],
              ['group_name' => 'Marketing', 'driver_name' => 'Research', 'demand_share_percent' => 5],
              ['group_name' => 'Sales', 'driver_name' => 'Dealer cost', 'demand_share_percent' => 6],
              ['group_name' => 'Sales', 'driver_name' => 'Sales cost', 'demand_share_percent' => 7],
            ];
            foreach ($rows as $d):
              $pct = (float)$d['demand_share_percent'];
              $val = ($pct / 100) * $unitCost;
            ?>
            <tr class="mcqg-driver-row">
              <td><input type="text" class="form-control" name="group_name[]" placeholder="e.g. Marketing" value="<?php echo htmlspecialchars($d['group_name']); ?>" required></td>
              <td><input type="text" class="form-control" name="driver_name[]" placeholder="e.g. Advertisement" value="<?php echo htmlspecialchars($d['driver_name']); ?>" required></td>
              <td>
                <div class="input-group">
                  <input type="number" step="0.01" class="form-control demand-driver-percent" name="demand_share_percent[]" placeholder="0" value="<?php echo htmlspecialchars((string)$pct); ?>" required>
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

      <button type="button" id="mcqg-add-demand-driver" class="btn btn-mcqg-outline btn-sm mb-4">+ Add Driver</button>

      <!-- Progress bar for demand drivers only -->
      <div id="demand-percent-total-bar" class="mcqg-percent-total-bar">
        <div class="mcqg-percent-total-fill under" style="width:0%"></div>
      </div>
      <div id="demand-percent-readout" class="mcqg-percent-readout mb-3"></div>

      <div class="mcqg-wizard-nav mt-3">
        <a href="step3_capacity_drivers.php" class="btn btn-mcqg-outline">Back</a>
        <button type="submit" id="mcqg-save-drivers-btn" class="btn btn-mcqg-primary">NEXT</button>
      </div>
    </form>
  </div>
</div>

<script>
  const unitCost   = <?php echo (float)$unitCost; ?>;
  const capTotal   = <?php echo (float)$capTotal; ?>;

  function updateDemandTotals() {
    let demandTotal = 0;
    document.querySelectorAll('#demand-driver-rows .mcqg-driver-row').forEach(row => {
      const pctInput = row.querySelector('.demand-driver-percent');
      const valInput = row.querySelector('.cost-value-display');
      const pct = parseFloat(pctInput ? pctInput.value : 0) || 0;
      demandTotal += pct;
      if (valInput) {
        valInput.value = ((pct / 100) * unitCost).toFixed(1);
      }
    });

    const combined = parseFloat((capTotal + demandTotal).toFixed(2));
    const remaining = parseFloat((100 - capTotal).toFixed(2));

    // Badges
    document.getElementById('demand-total-badge').textContent = demandTotal.toFixed(2) + '%';
    const combinedBadge = document.getElementById('combined-total-badge');
    combinedBadge.textContent = combined.toFixed(2) + '%';
    if (combined === 100) {
      combinedBadge.style.background = '#16a34a';
    } else if (combined > 100) {
      combinedBadge.style.background = '#dc2626';
    } else {
      combinedBadge.style.background = '#6b7280';
    }

    // Progress bar
    const fill = document.querySelector('#demand-percent-total-bar .mcqg-percent-total-fill');
    const readout = document.getElementById('demand-percent-readout');
    const pctOfRemaining = remaining > 0 ? Math.min((demandTotal / remaining) * 100, 100) : 0;
    if (fill) {
      fill.style.width = pctOfRemaining + '%';
      fill.className = 'mcqg-percent-total-fill ' + (demandTotal === remaining ? 'exact' : (demandTotal > remaining ? 'over' : 'under'));
    }
    if (readout) {
      readout.textContent = 'Demand Drivers: ' + demandTotal.toFixed(2) + '% | Combined Total: ' + combined.toFixed(2) + '% (must be 100%)';
      readout.className = 'mcqg-percent-readout ' + (combined > 100 ? 'text-danger fw-bold' : (combined === 100 ? 'text-success fw-bold' : 'text-muted'));
    }
  }

  document.getElementById('mcqg-add-demand-driver').addEventListener('click', function () {
    const container = document.getElementById('demand-driver-rows');
    const tr = document.createElement('tr');
    tr.className = 'mcqg-driver-row';
    tr.innerHTML = `
      <td><input type="text" class="form-control" name="group_name[]" placeholder="Group Name" required></td>
      <td><input type="text" class="form-control" name="driver_name[]" placeholder="Driver Name" required></td>
      <td>
        <div class="input-group">
          <input type="number" step="0.01" class="form-control demand-driver-percent" name="demand_share_percent[]" placeholder="0" required>
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
    updateDemandTotals();
  });

  document.getElementById('demand-driver-rows').addEventListener('click', function (e) {
    if (e.target.classList.contains('mcqg-remove-driver-row')) {
      e.target.closest('.mcqg-driver-row').remove();
      updateDemandTotals();
    }
  });

  document.getElementById('demand-driver-rows').addEventListener('input', function (e) {
    if (e.target.classList.contains('demand-driver-percent')) {
      updateDemandTotals();
    }
  });

  // Initial calculation
  updateDemandTotals();
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
