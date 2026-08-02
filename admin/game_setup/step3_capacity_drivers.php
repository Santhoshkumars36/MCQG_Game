<?php
/**
 * MCQG Admin - Game Setup Step 3: Capacity Drivers
 * Path: admin/game_setup/step3_capacity_drivers.php
 * Source: MG19 Slide 5 (renamed from "Financial Parameter")
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$gameId = Session::get('setup_game_id');
if (!$gameId) { header('Location: step1_title_case_study.php'); exit; }

$drivers = $db->fetchAll("SELECT * FROM capacity_driver WHERE game_id = :g", ['g' => $gameId]);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupNames = $_POST['group_name'] ?? [];
    $driverNames = $_POST['driver_name'] ?? [];
    $costShares = $_POST['cost_share_percent'] ?? [];

    $total = array_sum(array_map('floatval', $costShares));

    if (!Validator::percentTotalsTo100($costShares)) {
        $error = "Cost share % must total exactly 100%. Current total: " . round($total, 2) . "%.";
    } else {
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
        Session::setFlash('success', 'Step 3 saved - Capacity Drivers total 100%.');
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
    <p class="text-muted">Group cost-related settings. Cost share % across ALL drivers must total exactly 100%. Cost = Capacity cost &times; Cost share %.</p>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="POST" id="mcqg-wizard-form">
      <div id="capacity-driver-rows">
        <?php
        $rows = count($drivers) ? $drivers : [['group_name' => '', 'driver_name' => '', 'cost_share_percent' => '']];
        foreach ($rows as $d):
        ?>
        <div class="row g-2 mb-2 mcqg-driver-row align-items-center">
          <div class="col-4"><input type="text" class="form-control" name="group_name[]" placeholder="Group Name (e.g. Production)" value="<?php echo htmlspecialchars($d['group_name']); ?>"></div>
          <div class="col-4"><input type="text" class="form-control" name="driver_name[]" placeholder="Driver Name (e.g. Labour Cost)" value="<?php echo htmlspecialchars($d['driver_name']); ?>"></div>
          <div class="col-3"><input type="number" step="0.01" class="form-control capacity-driver-percent" name="cost_share_percent[]" placeholder="Cost Share %" value="<?php echo htmlspecialchars((string)$d['cost_share_percent']); ?>"></div>
          <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger mcqg-remove-driver-row">&times;</button></div>
        </div>
        <?php endforeach; ?>
      </div>

      <button type="button" id="mcqg-add-capacity-driver" class="btn btn-mcqg-outline btn-sm mb-3">+ Add Driver</button>

      <div id="capacity-percent-total-bar" class="mcqg-percent-total-bar">
        <div class="mcqg-percent-total-fill under" style="width:0%"></div>
      </div>
      <div id="capacity-percent-readout" class="mcqg-percent-readout"></div>

      <div class="mcqg-wizard-nav">
        <a href="step2_game_definition.php" class="btn btn-mcqg-outline">Back</a>
        <button type="submit" id="mcqg-save-drivers-btn" class="btn btn-mcqg-primary">NEXT</button>
      </div>
    </form>
  </div>
</div>

<script>
  document.getElementById('mcqg-add-capacity-driver').addEventListener('click', function () {
    const container = document.getElementById('capacity-driver-rows');
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 mcqg-driver-row align-items-center';
    row.innerHTML = `
      <div class="col-4"><input type="text" class="form-control" name="group_name[]" placeholder="Group Name"></div>
      <div class="col-4"><input type="text" class="form-control" name="driver_name[]" placeholder="Driver Name"></div>
      <div class="col-3"><input type="number" step="0.01" class="form-control capacity-driver-percent" name="cost_share_percent[]" placeholder="Cost Share %"></div>
      <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger mcqg-remove-driver-row">&times;</button></div>`;
    container.appendChild(row);
    initPercentGroup('capacity-driver-percent', 'capacity-percent-total-bar', 'capacity-percent-readout');
  });

  document.getElementById('capacity-driver-rows').addEventListener('click', function (e) {
    if (e.target.classList.contains('mcqg-remove-driver-row')) {
      e.target.closest('.mcqg-driver-row').remove();
      initPercentGroup('capacity-driver-percent', 'capacity-percent-total-bar', 'capacity-percent-readout');
    }
  });
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
