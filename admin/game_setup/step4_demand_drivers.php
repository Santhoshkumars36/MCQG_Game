<?php
/**
 * MCQG Admin - Game Setup Step 4: Demand Drivers
 * Path: admin/game_setup/step4_demand_drivers.php
 * Source: MG19 Slide 5 (new section)
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$gameId = Session::get('setup_game_id');
if (!$gameId) { header('Location: step1_title_case_study.php'); exit; }

$drivers = $db->fetchAll("SELECT * FROM demand_driver WHERE game_id = :g", ['g' => $gameId]);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupNames = $_POST['group_name'] ?? [];
    $driverNames = $_POST['driver_name'] ?? [];
    $demandShares = $_POST['demand_share_percent'] ?? [];

    $total = array_sum(array_map('floatval', $demandShares));

    if (!Validator::percentTotalsTo100($demandShares)) {
        $error = "Demand share % must total exactly 100%. Current total: " . round($total, 2) . "%.";
    } else {
        $db->query("DELETE FROM demand_driver WHERE game_id = :g", ['g' => $gameId]);
        foreach ($groupNames as $i => $group) {
            if (trim($group) === '') continue;
            $db->insert('demand_driver', [
                'game_id'              => $gameId,
                'group_name'           => trim($group),
                'driver_name'          => trim($driverNames[$i] ?? ''),
                'demand_share_percent' => (float) $demandShares[$i],
            ]);
        }
        Session::setFlash('success', 'Step 4 saved - Demand Drivers total 100%.');
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
    <p class="text-muted">Group demand-related settings. Demand share % across ALL drivers must total exactly 100%.</p>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="POST" id="mcqg-wizard-form">
      <div id="demand-driver-rows">
        <?php
        $rows = count($drivers) ? $drivers : [['group_name' => '', 'driver_name' => '', 'demand_share_percent' => '']];
        foreach ($rows as $d):
        ?>
        <div class="row g-2 mb-2 mcqg-driver-row align-items-center">
          <div class="col-4"><input type="text" class="form-control" name="group_name[]" placeholder="Group Name (e.g. Marketing)" value="<?php echo htmlspecialchars($d['group_name']); ?>"></div>
          <div class="col-4"><input type="text" class="form-control" name="driver_name[]" placeholder="Driver Name (e.g. Advertising Reach)" value="<?php echo htmlspecialchars($d['driver_name']); ?>"></div>
          <div class="col-3"><input type="number" step="0.01" class="form-control demand-driver-percent" name="demand_share_percent[]" placeholder="Demand Share %" value="<?php echo htmlspecialchars((string)$d['demand_share_percent']); ?>"></div>
          <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger mcqg-remove-driver-row">&times;</button></div>
        </div>
        <?php endforeach; ?>
      </div>

      <button type="button" id="mcqg-add-demand-driver" class="btn btn-mcqg-outline btn-sm mb-3">+ Add Driver</button>

      <div id="demand-percent-total-bar" class="mcqg-percent-total-bar">
        <div class="mcqg-percent-total-fill under" style="width:0%"></div>
      </div>
      <div id="demand-percent-readout" class="mcqg-percent-readout"></div>

      <div class="mcqg-wizard-nav">
        <a href="step3_capacity_drivers.php" class="btn btn-mcqg-outline">Back</a>
        <button type="submit" id="mcqg-save-drivers-btn" class="btn btn-mcqg-primary">NEXT</button>
      </div>
    </form>
  </div>
</div>

<script>
  document.getElementById('mcqg-add-demand-driver').addEventListener('click', function () {
    const container = document.getElementById('demand-driver-rows');
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 mcqg-driver-row align-items-center';
    row.innerHTML = `
      <div class="col-4"><input type="text" class="form-control" name="group_name[]" placeholder="Group Name"></div>
      <div class="col-4"><input type="text" class="form-control" name="driver_name[]" placeholder="Driver Name"></div>
      <div class="col-3"><input type="number" step="0.01" class="form-control demand-driver-percent" name="demand_share_percent[]" placeholder="Demand Share %"></div>
      <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger mcqg-remove-driver-row">&times;</button></div>`;
    container.appendChild(row);
    initPercentGroup('demand-driver-percent', 'demand-percent-total-bar', 'demand-percent-readout');
  });

  document.getElementById('demand-driver-rows').addEventListener('click', function (e) {
    if (e.target.classList.contains('mcqg-remove-driver-row')) {
      e.target.closest('.mcqg-driver-row').remove();
      initPercentGroup('demand-driver-percent', 'demand-percent-total-bar', 'demand-percent-readout');
    }
  });
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
