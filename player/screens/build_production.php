<?php
/**
 * MCQG Player - Screen 2: Build Production (Supply Economics)
 * Path: player/screens/build_production.php
 * Source: MG19 Slide 10 - capacity entry (increment field), live
 * right-panel stats (opening inventory, capacity cost, cost per
 * unit), and Capacity Driver investments that raise cost live.
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
$yearNo = $currentRound['year_no'] ?? 1;

$decision = $db->fetchOne(
    "SELECT * FROM team_decision WHERE team_id = :t AND year_no = :y",
    ['t' => $teamId, 'y' => $yearNo]
);

// Investments linked to Capacity Drivers only (MG19 Slide 10)
$capacityInvestments = $db->fetchAll(
    "SELECT DISTINCT im.* FROM investment_master im
     INNER JOIN investment_effect ie ON ie.investment_id = im.investment_id
     WHERE im.game_id = :g AND ie.driver_type = 'Capacity' AND im.active = 1
     ORDER BY im.display_order",
    ['g' => $gameId]
);

$existingSelections = [];
if ($decision) {
    $rows = $db->fetchAll(
        "SELECT investment_id, invested_value FROM team_investment_selection WHERE decision_id = :d",
        ['d' => $decision['decision_id']]
    );
    foreach ($rows as $r) { $existingSelections[$r['investment_id']] = $r['invested_value']; }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $capacityBuilt = (int) ($_POST['capacity_built'] ?? $game['starting_capacity']);
    $productionQty = (int) ($_POST['production_qty'] ?? 0);
    $investments = $_POST['investment'] ?? [];

    if ($productionQty <= 0) {
        $error = 'Please enter a production quantity greater than zero.';
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
            $val = (float) ($investments[$inv['investment_id']] ?? 0);
            $exists = $db->fetchOne(
                "SELECT selection_id FROM team_investment_selection WHERE decision_id = :d AND investment_id = :i",
                ['d' => $decisionId, 'i' => $inv['investment_id']]
            );
            if ($val > 0) {
                $data = ['invested_value' => $val, 'invested_percent' =>
                    round((($val - $inv['min_investment_value']) / max($inv['max_investment_value'] - $inv['min_investment_value'], 1)) * 100, 2)];
                if ($exists) {
                    $db->update('team_investment_selection', $data, 'selection_id = :s', ['s' => $exists['selection_id']]);
                } else {
                    $db->insert('team_investment_selection', array_merge($data, ['decision_id' => $decisionId, 'investment_id' => $inv['investment_id']]));
                }
            } elseif ($exists) {
                $db->execute("DELETE FROM team_investment_selection WHERE selection_id = :s", ['s' => $exists['selection_id']]);
            }
        }

        header('Location: demand_economics.php');
        exit;
    }
}

$baseCosts = calculateCapacityDriverCosts((float) $game['capacity_cost'], $db->fetchAll("SELECT * FROM capacity_driver WHERE game_id = :g", ['g' => $gameId]));
$totalCapacityCost = array_sum(array_column($baseCosts, 'cost_value'));

$pageTitle = 'Build Production';
require_once __DIR__ . '/../includes/player_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/player_sidebar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-card-header"><h2>Build Your Production &mdash; Year <?php echo (int) $yearNo; ?></h2></div>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <form method="POST" id="mcqg-production-form">
    <div class="mcqg-production-layout">
      <div>
        <div class="mb-4">
          <label class="form-label fw-bold">Plant Capacity</label>
          <input type="range" class="mcqg-slider" min="<?php echo (int) $game['minimum_capacity']; ?>" max="<?php echo (int) $game['maximum_capacity']; ?>"
                 step="<?php echo (int) $game['capacity_increment']; ?>" name="capacity_built" id="capacity_built"
                 value="<?php echo (int) ($decision['capacity_built'] ?? $game['starting_capacity']); ?>"
                 oninput="document.getElementById('capacity-readout').textContent = this.value">
          <div class="d-flex justify-content-between small text-muted">
            <span><?php echo (int) $game['minimum_capacity']; ?></span>
            <strong id="capacity-readout" style="color:var(--mcqg-navy);"><?php echo (int) ($decision['capacity_built'] ?? $game['starting_capacity']); ?></strong>
            <span><?php echo (int) $game['maximum_capacity']; ?></span>
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label fw-bold">Production Quantity</label>
          <input type="number" name="production_qty" class="form-control mcqg-input-live" required
                 value="<?php echo (int) ($decision['production_qty'] ?? 0); ?>" placeholder="Units to produce this year">
        </div>

        <h5 class="fw-bold" style="color:var(--mcqg-navy);">Capacity Driver Investments</h5>
        <p class="text-muted small">Investing here reduces production cost per unit (e.g. Automation, Lean Manufacturing).</p>

        <?php foreach ($capacityInvestments as $inv): ?>
        <div class="mcqg-driver-investment-row">
          <div>
            <strong><?php echo htmlspecialchars($inv['investment_name']); ?></strong>
            <div class="small text-muted"><?php echo htmlspecialchars($inv['description']); ?></div>
          </div>
          <input type="range" class="mcqg-slider" id="slider-<?php echo $inv['investment_id']; ?>"
                 data-investment-id="<?php echo $inv['investment_id']; ?>"
                 min="0" max="<?php echo (float) $inv['max_investment_value']; ?>" step="<?php echo (float) $inv['increment_value']; ?>"
                 value="<?php echo $existingSelections[$inv['investment_id']] ?? 0; ?>"
                 oninput="document.querySelector('[data-slider-value-for=slider-<?php echo $inv['investment_id']; ?>]').textContent = '\u20B9' + Number(this.value).toLocaleString()">
          <input type="hidden" name="investment[<?php echo $inv['investment_id']; ?>]" data-investment-id="<?php echo $inv['investment_id']; ?>"
                 value="<?php echo $existingSelections[$inv['investment_id']] ?? 0; ?>">
          <span class="mcqg-slider-value" data-slider-value-for="slider-<?php echo $inv['investment_id']; ?>">
            &#8377;<?php echo number_format($existingSelections[$inv['investment_id']] ?? 0); ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="mcqg-live-panel">
        <h5>Live Production Stats</h5>
        <div class="mcqg-live-stat"><span class="label">Opening Inventory</span><span class="value" id="live-opening-inventory"><?php echo (int) $team['opening_inventory']; ?></span></div>
        <div class="mcqg-live-stat"><span class="label">Capacity Cost</span><span class="value" id="live-capacity-cost">&#8377;<?php echo number_format($totalCapacityCost); ?></span></div>
        <div class="mcqg-live-stat"><span class="label">Cost per Unit</span><span class="value" id="live-unit-cost">&#8377;0.00</span></div>
        <div class="mcqg-live-stat"><span class="label">Total Investment</span><span class="value" id="live-total-investment">&#8377;0</span></div>
      </div>
    </div>

    <div class="mcqg-wizard-nav" style="border-top:1px solid var(--mcqg-border); margin-top:26px; padding-top:18px; display:flex; justify-content:space-between;">
      <a href="case_study.php" class="btn btn-mcqg-outline">Back</a>
      <button type="submit" class="btn btn-mcqg-gold">Continue to Demand Economics &rarr;</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/player_footer.php'; ?>
