<?php
/**
 * MCQG Admin - Round Control: Process Round
 * Path: admin/round_control/process_round.php
 * Source: MG19 Slide 15 - only the admin can trigger this, and only
 * once every team has submitted. Calls engine/engine_bootstrap.php,
 * which runs the full 6-step calculation pipeline.
 */
require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../engine/engine_bootstrap.php';
Auth::requireAdmin();

$db = Database::getInstance();
$gameId = (int) ($_GET['game_id'] ?? 0);
$yearNo = (int) ($_GET['year_no'] ?? 0);

$game = $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]);
$teams = $db->fetchAll("SELECT team_id FROM team_master WHERE game_id = :g AND is_active = 1", ['g' => $gameId]);
$submittedCount = $db->fetchOne(
    "SELECT COUNT(*) AS c FROM team_decision WHERE game_id = :g AND year_no = :y AND status = 'Submitted'",
    ['g' => $gameId, 'y' => $yearNo]
)['c'];

$allSubmitted = count($teams) > 0 && (int) $submittedCount === count($teams);
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_process'])) {
    if (!$allSubmitted) {
        Session::setFlash('error', 'Cannot process - not all teams have submitted their decisions yet.');
        header('Location: round_status.php?game_id=' . $gameId);
        exit;
    }

    $result = processGameRound($gameId, $yearNo);

    if ($result['success']) {
        Session::setFlash('success', "Year {$yearNo} processed successfully. Results are now visible to teams.");
        header('Location: ../reports/round_report.php?game_id=' . $gameId . '&year_no=' . $yearNo);
        exit;
    } else {
        Session::setFlash('error', $result['message']);
    }
}

$pageTitle = 'Process Round';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card" style="max-width:600px;">
  <div class="mcqg-card-header"><h2>Process Round - Year <?php echo $yearNo; ?></h2></div>
  <p class="text-muted"><?php echo htmlspecialchars($game['game_name'] ?? ''); ?></p>

  <div class="mcqg-driver-group-card mb-3">
    <p class="mb-1">Teams submitted: <strong><?php echo (int) $submittedCount; ?> / <?php echo count($teams); ?></strong></p>
    <p class="mb-0">
      Status:
      <?php if ($allSubmitted): ?>
        <span class="effect-positive">Ready to process &#10003;</span>
      <?php else: ?>
        <span class="effect-negative">Waiting on teams</span>
      <?php endif; ?>
    </p>
  </div>

  <?php if (!$allSubmitted): ?>
    <div class="alert alert-warning">All teams must submit their decisions before this round can be processed.</div>
    <a href="round_status.php?game_id=<?php echo $gameId; ?>" class="btn btn-mcqg-outline">Back to Round Status</a>
  <?php else: ?>
    <p>Processing will run the allocation engine (guaranteed minimum &rarr; demand-driver share &rarr; lowest price),
       calculate cost and profit for every team, and open the results to all teams. This cannot be undone.</p>
    <form method="POST" onsubmit="return confirm('Process Year <?php echo $yearNo; ?> now? This cannot be undone.');">
      <button type="submit" name="confirm_process" value="1" class="btn btn-mcqg-gold">Confirm &amp; Process Round</button>
      <a href="round_status.php?game_id=<?php echo $gameId; ?>" class="btn btn-mcqg-outline">Cancel</a>
    </form>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
