<?php
/**
 * MCQG Player - Investment Matrix View
 * Path: player/results/investment_matrix_view.php
 * Source: MG19 Slide 13 - "matrix of share of demand drivers and
 * capacity drivers so that teams know who is investing where and
 * take correction in the next round."
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireTeam();

$db = Database::getInstance();
$teamId = Session::get('team_id');
$gameId = Session::get('game_id');
$yearNo = (int) ($_GET['year_no'] ?? 1);

$investments = $db->fetchAll("SELECT investment_id, investment_name FROM investment_master WHERE game_id = :g ORDER BY display_order", ['g' => $gameId]);
$teams = $db->fetchAll("SELECT team_id, team_name FROM team_master WHERE game_id = :g", ['g' => $gameId]);

$matrix = [];
foreach ($teams as $t) {
    $decision = $db->fetchOne(
        "SELECT decision_id FROM team_decision WHERE team_id = :t AND year_no = :y AND status = 'Submitted'",
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
    $matrix[] = ['team_name' => $t['team_name'], 'is_me' => $t['team_id'] == $teamId, 'shares' => $shares];
}

$pageTitle = 'Investment Matrix';
require_once __DIR__ . '/../includes/player_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/player_sidebar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-card-header"><h2>Investment Matrix - Year <?php echo $yearNo; ?></h2></div>
  <p class="text-muted small">See where every team invested this round - use this to adjust your strategy for next year.</p>

  <table class="mcqg-report-table w-100">
    <thead>
      <tr><th>Team</th>
        <?php foreach ($investments as $inv): ?><th><?php echo htmlspecialchars($inv['investment_name']); ?></th><?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($matrix as $row): ?>
      <tr class="<?php echo $row['is_me'] ? 'own-team' : ''; ?>">
        <td><?php echo htmlspecialchars($row['team_name']); ?><?php echo $row['is_me'] ? ' (You)' : ''; ?></td>
        <?php foreach ($row['shares'] as $share):
          $intensity = min($share / 100, 1);
          $bg = "rgba(30,39,97," . (0.2 + $intensity * 0.7) . ")";
        ?>
          <td class="mcqg-matrix-cell" style="background:<?php echo $bg; ?>"><?php echo number_format($share, 0); ?>%</td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <a href="round_results.php?year_no=<?php echo $yearNo; ?>" class="btn btn-mcqg-outline mt-3">Back to Results</a>
</div>

<?php require_once __DIR__ . '/../includes/player_footer.php'; ?>
