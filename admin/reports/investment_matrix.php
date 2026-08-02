<?php
/**
 * MCQG Admin - Reports: Investment Matrix
 * Path: admin/reports/investment_matrix.php
 * Source: MG19 Slide 13 - "a matrix of share of demand drivers and
 * capacity drivers so that teams know who is investing where."
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$games = $db->fetchAll("SELECT game_id, game_name FROM game_master ORDER BY created_on DESC");
$gameId = (int) ($_GET['game_id'] ?? ($games[0]['game_id'] ?? 0));
$yearNo = (int) ($_GET['year_no'] ?? 1);

$teams = $db->fetchAll("SELECT team_id, team_name FROM team_master WHERE game_id = :g", ['g' => $gameId]);
$investments = $db->fetchAll("SELECT investment_id, investment_name FROM investment_master WHERE game_id = :g ORDER BY display_order", ['g' => $gameId]);

$matrix = [];
foreach ($teams as $t) {
    $decision = $db->fetchOne(
        "SELECT decision_id FROM team_decision WHERE team_id = :t AND year_no = :y",
        ['t' => $t['team_id'], 'y' => $yearNo]
    );
    $shares = [];
    foreach ($investments as $inv) {
        if (!$decision) { $shares[] = 0; continue; }
        $sel = $db->fetchOne(
            "SELECT invested_percent FROM team_investment_selection WHERE decision_id = :d AND investment_id = :i",
            ['d' => $decision['decision_id'], 'i' => $inv['investment_id']]
        );
        $shares[] = $sel ? (float) $sel['invested_percent'] : 0;
    }
    $matrix[] = ['team_name' => $t['team_name'], 'shares' => $shares];
}

$pageTitle = 'Investment Matrix';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-card-header">
    <h2>Investment Matrix - Year <?php echo $yearNo; ?></h2>
    <form method="GET" class="d-flex gap-2">
      <select name="game_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($games as $g): ?>
          <option value="<?php echo $g['game_id']; ?>" <?php echo $g['game_id'] == $gameId ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['game_name']); ?></option>
        <?php endforeach; ?>
      </select>
      <input type="number" name="year_no" class="form-control" value="<?php echo $yearNo; ?>" style="width:100px;">
      <button class="btn btn-mcqg-outline" type="submit">Go</button>
    </form>
  </div>

  <p class="text-muted small">Shows what % of each investment's range every team invested in this round - visible
     to teams as competitive intelligence after results are processed.</p>

  <div id="mcqg-investment-matrix"></div>
  <script type="application/json" id="investment-matrix-data">
    <?php echo json_encode(['drivers' => array_column($investments, 'investment_name'), 'teams' => $matrix]); ?>
  </script>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
