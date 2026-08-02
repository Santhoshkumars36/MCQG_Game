<?php
/**
 * MCQG Admin - Round Control: Round Status
 * Path: admin/round_control/round_status.php
 * Source: MG19 Slide 15 - "Results for a period will only be
 * processed once all teams have submitted." This screen shows
 * exactly who has/hasn't submitted, live.
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$games = $db->fetchAll("SELECT game_id, game_name FROM game_master WHERE status = 'Published' ORDER BY created_on DESC");
$gameId = (int) ($_GET['game_id'] ?? ($games[0]['game_id'] ?? 0));

$rounds = $gameId ? $db->fetchAll("SELECT * FROM game_round_status WHERE game_id = :g ORDER BY year_no", ['g' => $gameId]) : [];
$teams = $gameId ? $db->fetchAll("SELECT team_id, team_name FROM team_master WHERE game_id = :g AND is_active = 1", ['g' => $gameId]) : [];

$submissionMatrix = [];
foreach ($rounds as $r) {
    foreach ($teams as $t) {
        $d = $db->fetchOne(
            "SELECT status FROM team_decision WHERE team_id = :t AND year_no = :y",
            ['t' => $t['team_id'], 'y' => $r['year_no']]
        );
        $submissionMatrix[$r['year_no']][$t['team_id']] = $d['status'] ?? 'Not Started';
    }
}

$pageTitle = 'Round Status';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-card-header"><h2>Round Status</h2></div>

  <form method="GET" class="mb-3" style="max-width:360px;">
    <select name="game_id" class="form-select" onchange="this.form.submit()">
      <?php foreach ($games as $g): ?>
        <option value="<?php echo $g['game_id']; ?>" <?php echo $g['game_id'] == $gameId ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['game_name']); ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if (empty($rounds)): ?>
    <p class="text-muted">No rounds found - publish a game first.</p>
  <?php else: ?>
  <table class="mcqg-report-table w-100">
    <thead>
      <tr>
        <th>Team</th>
        <?php foreach ($rounds as $r): ?><th>Year <?php echo (int) $r['year_no']; ?></th><?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($teams as $t): ?>
      <tr>
        <td class="fw-bold"><?php echo htmlspecialchars($t['team_name']); ?></td>
        <?php foreach ($rounds as $r):
          $status = $submissionMatrix[$r['year_no']][$t['team_id']] ?? 'Not Started'; ?>
          <td>
            <?php if ($status === 'Submitted'): ?>
              <span class="mcqg-badge mcqg-badge-published">Submitted &#10003;</span>
            <?php else: ?>
              <span class="mcqg-badge mcqg-badge-draft">Pending</span>
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <hr class="my-4">

  <h5 class="fw-bold" style="color:var(--mcqg-navy);">Round Processing Status</h5>
  <div class="row g-3">
    <?php foreach ($rounds as $r):
      $submittedCount = 0;
      foreach ($teams as $t) { if (($submissionMatrix[$r['year_no']][$t['team_id']] ?? '') === 'Submitted') $submittedCount++; }
      $allSubmitted = count($teams) > 0 && $submittedCount === count($teams);
    ?>
    <div class="col-md-4">
      <div class="mcqg-driver-group-card">
        <h6 class="fw-bold">Year <?php echo (int) $r['year_no']; ?></h6>
        <p class="mb-1">Status: <span class="mcqg-badge <?php echo $r['status'] === 'Processed' ? 'mcqg-badge-published' : 'mcqg-badge-draft'; ?>"><?php echo $r['status']; ?></span></p>
        <p class="mb-2 small text-muted"><?php echo $submittedCount; ?> / <?php echo count($teams); ?> teams submitted</p>
        <?php if ($r['status'] !== 'Processed'): ?>
          <a href="process_round.php?game_id=<?php echo $gameId; ?>&year_no=<?php echo $r['year_no']; ?>"
             class="btn btn-sm btn-mcqg-gold" <?php echo $allSubmitted ? '' : 'style="pointer-events:none;opacity:0.5;"'; ?>>
            Process Round
          </a>
        <?php else: ?>
          <a href="../reports/round_report.php?game_id=<?php echo $gameId; ?>&year_no=<?php echo $r['year_no']; ?>" class="btn btn-sm btn-mcqg-outline">View Results</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
