<?php
/**
 * MCQG Admin - Round Control: Open Next Round
 * Path: admin/round_control/open_next_round.php
 * Source: MG19 Slide 15 - "Only after the admin manually processes
 * the results will the next round be opened up for teams."
 * Round rows are created at Publish time already (status = Open),
 * so "opening" the next round just confirms the previous one is
 * Processed and notifies teams it's live (flash + activity log).
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$gameId = (int) ($_GET['game_id'] ?? 0);
$game = $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]);

$rounds = $db->fetchAll("SELECT * FROM game_round_status WHERE game_id = :g ORDER BY year_no", ['g' => $gameId]);

$nextRound = null;
foreach ($rounds as $i => $r) {
    if ($r['status'] !== 'Processed') {
        $prev = $i > 0 ? $rounds[$i - 1] : null;
        if (!$prev || $prev['status'] === 'Processed') {
            $nextRound = $r;
        }
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_open']) && $nextRound) {
    Logger::activity("Year {$nextRound['year_no']} opened for game {$gameId}.");
    Session::setFlash('success', "Year {$nextRound['year_no']} is now open for teams.");
    header('Location: round_status.php?game_id=' . $gameId);
    exit;
}

$pageTitle = 'Open Next Round';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card" style="max-width:600px;">
  <div class="mcqg-card-header"><h2>Open Next Round</h2></div>
  <p class="text-muted"><?php echo htmlspecialchars($game['game_name'] ?? ''); ?></p>

  <?php if (!$nextRound): ?>
    <div class="alert alert-info">All rounds are already open or completed - there's nothing to open right now.</div>
  <?php else: ?>
    <div class="mcqg-driver-group-card mb-3">
      <p class="mb-0">Ready to open: <strong>Year <?php echo (int) $nextRound['year_no']; ?></strong></p>
    </div>
    <form method="POST">
      <button type="submit" name="confirm_open" value="1" class="btn btn-mcqg-gold">Open Year <?php echo (int) $nextRound['year_no']; ?> for Teams</button>
      <a href="round_status.php?game_id=<?php echo $gameId; ?>" class="btn btn-mcqg-outline">Cancel</a>
    </form>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
