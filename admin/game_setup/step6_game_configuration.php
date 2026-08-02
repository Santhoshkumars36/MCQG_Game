<?php
/**
 * MCQG Admin - Game Setup Step 6: Game Configuration
 * Path: admin/game_setup/step6_game_configuration.php
 * Source: MG19 Slide 7
 *  - "Game configuration" / "winning criteria" (old design) NOT required
 *  - Add Sales Price Tolerance %
 *  - Demand Allocation Logic table: one row per period -
 *    Price (tick, default ON), Min %, Demand driver %
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$gameId = Session::get('setup_game_id');
if (!$gameId) { header('Location: step1_title_case_study.php'); exit; }

$game = $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]);
$years = (int) $game['no_of_years'];
$existingRules = $db->fetchAll("SELECT * FROM game_demand_allocation WHERE game_id = :g ORDER BY year_no", ['g' => $gameId]);
$rulesByYear = [];
foreach ($existingRules as $r) { $rulesByYear[$r['year_no']] = $r; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tolerance = (float) ($_POST['sales_price_tolerance_percent'] ?? 0);
    $priceTick = $_POST['price_tick'] ?? [];
    $minPct = $_POST['min_percent'] ?? [];
    $demandDriverPct = $_POST['demand_driver_percent'] ?? [];

    if (!Validator::inRange($tolerance, 0, 100)) {
        $error = 'Sales price tolerance must be between 0 and 100%.';
    } else {
        $db->update('game_master', ['sales_price_tolerance_percent' => $tolerance], 'game_id = :g', ['g' => $gameId]);

        for ($y = 1; $y <= $years; $y++) {
            $data = [
                'price_tick' => isset($priceTick[$y]) ? 1 : 0,
                'min_percent' => (float) ($minPct[$y] ?? 0),
                'demand_driver_percent' => (float) ($demandDriverPct[$y] ?? 0),
            ];
            if (isset($rulesByYear[$y])) {
                $db->update('game_demand_allocation', $data, 'game_id = :g AND year_no = :y', ['g' => $gameId, 'y' => $y]);
            } else {
                $db->insert('game_demand_allocation', array_merge($data, ['game_id' => $gameId, 'year_no' => $y]));
            }
        }

        Session::setFlash('success', 'Step 6 saved.');
        header('Location: step7_publish.php');
        exit;
    }
}

$pageTitle = 'Game Setup - Step 6';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-stepper">
    <div class="mcqg-stepper-fill" style="width:83%"></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Title &amp; Case Study</div></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Game Definition</div></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Capacity Drivers</div></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Demand Drivers</div></div>
    <div class="mcqg-step completed"><div class="mcqg-step-circle">&#10003;</div><div class="mcqg-step-label">Investments</div></div>
    <div class="mcqg-step active"><div class="mcqg-step-circle">6</div><div class="mcqg-step-label">Configuration</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">7</div><div class="mcqg-step-label">Publish</div></div>
  </div>

  <div class="mcqg-wizard-panel">
    <h3>Step 6 of 7 &mdash; Game Configuration</h3>
    <p class="text-muted">Set the sales price tolerance and how each period's demand will be shared out among teams.</p>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="POST">
      <div class="mb-4" style="max-width:320px;">
        <label class="form-label fw-bold">Sales Price Tolerance %</label>
        <input type="number" step="0.01" name="sales_price_tolerance_percent" class="form-control"
               value="<?php echo htmlspecialchars($game['sales_price_tolerance_percent'] ?? '0'); ?>" required>
        <small class="text-muted">How far a team's selling price is allowed to move.</small>
      </div>

      <h5 class="fw-bold" style="color:var(--mcqg-navy);">Demand Allocation Logic (per period)</h5>
      <table class="table align-middle">
        <thead>
          <tr><th>Period</th><th>Price (Tick)</th><th>Min %</th><th>Demand Driver %</th></tr>
        </thead>
        <tbody>
          <?php for ($y = 1; $y <= $years; $y++): $r = $rulesByYear[$y] ?? null; ?>
          <tr>
            <td class="fw-bold">Year <?php echo $y; ?></td>
            <td><input type="checkbox" name="price_tick[<?php echo $y; ?>]" class="form-check-input"
                       <?php echo (!$r || $r['price_tick']) ? 'checked' : ''; ?>></td>
            <td><input type="number" step="0.01" name="min_percent[<?php echo $y; ?>]" class="form-control"
                       value="<?php echo htmlspecialchars($r['min_percent'] ?? '0'); ?>"></td>
            <td><input type="number" step="0.01" name="demand_driver_percent[<?php echo $y; ?>]" class="form-control"
                       value="<?php echo htmlspecialchars($r['demand_driver_percent'] ?? '0'); ?>"></td>
          </tr>
          <?php endfor; ?>
        </tbody>
      </table>

      <div class="mcqg-wizard-nav">
        <a href="step5_investments.php" class="btn btn-mcqg-outline">Back</a>
        <button type="submit" id="mcqg-next-btn" class="btn btn-mcqg-primary">NEXT</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
