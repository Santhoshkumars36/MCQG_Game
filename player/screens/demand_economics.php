<?php
/**
 * MCQG Player - Screen 3: Demand Economics
 * Path: player/screens/demand_economics.php
 * Source: MG19 Slide 11 - 10 Demand Driver investments, live
 * capacity-cost/unit-cost recalculation, sale price validated
 * against Min/Max range, live estimated Profit/Loss.
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireTeam();

$db = Database::getInstance();
$teamId = Session::currentTeamId();
$gameId = Session::activeGameId();
$game = $gameId ? $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]) : null;

if (!$game) {
    Auth::logout();
    header('Location: ' . PLAYER_URL . 'auth/login.php');
    exit;
}

$currentRound = $db->fetchOne(
    "SELECT * FROM game_round_status WHERE game_id = :g AND status != 'Processed' ORDER BY year_no LIMIT 1",
    ['g' => $gameId]
);
$yearNo = $currentRound['year_no'] ?? 1;

$decision = $db->fetchOne("SELECT * FROM team_decision WHERE team_id = :t AND year_no = :y", ['t' => $teamId, 'y' => $yearNo]);
if (!$decision) { header('Location: build_production.php'); exit; }

$demandInvestments = $db->fetchAll(
    "SELECT DISTINCT im.* FROM investment_master im
     INNER JOIN investment_effect ie ON ie.investment_id = im.investment_id
     WHERE im.game_id = :g AND ie.driver_type = 'Demand' AND im.active = 1
     ORDER BY im.display_order",
    ['g' => $gameId]
);

$existingSelections = [];
$rows = $db->fetchAll("SELECT investment_id, invested_value FROM team_investment_selection WHERE decision_id = :d", ['d' => $decision['decision_id']]);
foreach ($rows as $r) { $existingSelections[$r['investment_id']] = $r['invested_value']; }

$minPriceRow = $db->fetchOne("SELECT configuration_value FROM game_configuration WHERE game_id = :g AND configuration_key = 'Minimum Selling Price'", ['g' => $gameId]);
$maxPriceRow = $db->fetchOne("SELECT configuration_value FROM game_configuration WHERE game_id = :g AND configuration_key = 'Maximum Selling Price'", ['g' => $gameId]);
$minPrice = (float) ($minPriceRow['configuration_value'] ?? 0);
$maxPrice = (float) ($maxPriceRow['configuration_value'] ?? 999999);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sellingPrice = (float) ($_POST['selling_price'] ?? 0);
    $investments = $_POST['investment'] ?? [];

    if (!Validator::priceWithinRange($sellingPrice, $minPrice, $maxPrice)) {
        $error = "Price must be between {$minPrice} and {$maxPrice}.";
    } else {
        $db->update('team_decision', ['selling_price' => $sellingPrice], 'decision_id = :d', ['d' => $decision['decision_id']]);

        foreach ($demandInvestments as $inv) {
            $val = (float) ($investments[$inv['investment_id']] ?? 0);
            $exists = $db->fetchOne(
                "SELECT selection_id FROM team_investment_selection WHERE decision_id = :d AND investment_id = :i",
                ['d' => $decision['decision_id'], 'i' => $inv['investment_id']]
            );
            if ($val > 0) {
                $data = ['invested_value' => $val, 'invested_percent' =>
                    round((($val - $inv['min_investment_value']) / max($inv['max_investment_value'] - $inv['min_investment_value'], 1)) * 100, 2)];
                if ($exists) {
                    $db->update('team_investment_selection', $data, 'selection_id = :s', ['s' => $exists['selection_id']]);
                } else {
                    $db->insert('team_investment_selection', array_merge($data, ['decision_id' => $decision['decision_id'], 'investment_id' => $inv['investment_id']]));
                }
            } elseif ($exists) {
                $db->execute("DELETE FROM team_investment_selection WHERE selection_id = :s", ['s' => $exists['selection_id']]);
            }
        }

        header('Location: review_decision.php');
        exit;
    }
}

$pageTitle = 'Demand Economics';
require_once __DIR__ . '/../includes/player_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/player_sidebar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-card-header"><h2>Demand Economics &mdash; Year <?php echo (int) $yearNo; ?></h2></div>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <form method="POST" id="mcqg-demand-form">
    <div class="mcqg-production-layout">
      <div>
        <h5 class="fw-bold" style="color:var(--mcqg-navy);">Demand Driver Investments</h5>
        <p class="text-muted small">Investing here helps capture more of the market demand (e.g. Advertising, Demand Analytics).</p>

        <?php foreach ($demandInvestments as $inv): ?>
        <div class="mcqg-driver-investment-row">
          <div>
            <strong><?php echo htmlspecialchars($inv['investment_name']); ?></strong>
            <div class="small text-muted"><?php echo htmlspecialchars($inv['description']); ?></div>
          </div>
          <input type="range" class="mcqg-slider" id="slider-<?php echo $inv['investment_id']; ?>"
                 min="0" max="<?php echo (float) $inv['max_investment_value']; ?>" step="<?php echo (float) $inv['increment_value']; ?>"
                 value="<?php echo $existingSelections[$inv['investment_id']] ?? 0; ?>"
                 oninput="document.querySelector('[data-slider-value-for=slider-<?php echo $inv['investment_id']; ?>]').textContent = '\u20B9' + Number(this.value).toLocaleString(); document.getElementById('hidden-<?php echo $inv['investment_id']; ?>').value = this.value;">
          <input type="hidden" id="hidden-<?php echo $inv['investment_id']; ?>" name="investment[<?php echo $inv['investment_id']; ?>]"
                 data-investment-id="<?php echo $inv['investment_id']; ?>" value="<?php echo $existingSelections[$inv['investment_id']] ?? 0; ?>">
          <span class="mcqg-slider-value" data-slider-value-for="slider-<?php echo $inv['investment_id']; ?>">
            &#8377;<?php echo number_format($existingSelections[$inv['investment_id']] ?? 0); ?>
          </span>
        </div>
        <?php endforeach; ?>

        <div class="mt-4 mcqg-price-input-wrapper" style="max-width:320px;">
          <label class="form-label fw-bold">Selling Price</label>
          <input type="number" step="0.01" name="selling_price" id="selling_price" class="form-control mcqg-input-live"
                 data-min="<?php echo $minPrice; ?>" data-max="<?php echo $maxPrice; ?>"
                 value="<?php echo (float) $decision['selling_price']; ?>" required>
          <div id="price-range-hint" class="mcqg-price-range-hint">Allowed range: &#8377;<?php echo $minPrice; ?> - &#8377;<?php echo $maxPrice; ?></div>
        </div>
      </div>

      <div class="mcqg-live-panel">
        <h5>Live Estimate</h5>
        <div id="mcqg-profit-preview" class="mcqg-profit-preview positive">
          <div style="font-size:13px; text-transform:uppercase;">Estimated Profit</div>
          <div style="font-size:26px; font-weight:800;">&#8377;0</div>
        </div>
      </div>
    </div>

    <div class="mcqg-wizard-nav" style="border-top:1px solid var(--mcqg-border); margin-top:26px; padding-top:18px; display:flex; justify-content:space-between;">
      <a href="build_production.php" class="btn btn-mcqg-outline">Back</a>
      <button type="submit" class="btn btn-mcqg-gold">Continue to Review &rarr;</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/player_footer.php'; ?>
