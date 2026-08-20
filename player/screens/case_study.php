<?php
/**
 * MCQG Player - Screen 1: Case Study
 * Path: player/screens/case_study.php
 * Source: Slide 2 - Entire screen case study with rich text and small image.
 */
require_once __DIR__ . '/../../config/app_config.php';
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

$pageTitle = 'Case Study: ' . $game['game_name'];
$activeStep = 1;

require_once __DIR__ . '/../includes/command_header.php';
?>

<div class="cmd-container">
  <div class="cmd-full-grid">
    <div class="cmd-card">
      <div class="cmd-card-header">
        <h1 class="cmd-card-title" style="font-size:20px;">
          <i class="fa-solid fa-book-open text-primary me-2"></i>
          Case Study: <?php echo htmlspecialchars($game['game_name']); ?>
        </h1>
        <span class="badge bg-primary px-3 py-2" style="font-size:12px;">Strategic Briefing</span>
      </div>
      <div class="cmd-card-body">
        
        <?php if ($csImg = Game::getImageUrl($game)): ?>
          <div class="text-center mb-4 p-3 bg-light rounded-3 border">
            <img src="<?php echo htmlspecialchars($csImg); ?>" alt="Case Study Scenario" class="img-fluid rounded shadow-sm" style="max-height: 280px; object-fit: contain;">
          </div>
        <?php endif; ?>

        <div class="case-study-content style-rich-text" style="font-size: 15px; line-height: 1.8; color: #334155;">
          <?php if (!empty($game['description'])): ?>
            <?php echo $game['description']; ?>
          <?php else: ?>
            <p>Welcome to <strong><?php echo htmlspecialchars($game['game_name']); ?></strong>. In this strategic management simulation, your executive leadership team is tasked with driving operational excellence, balancing capacity expansions, optimizing unit cost structures, and capturing target market share across multiple periods.</p>
            <p>Review the briefing parameters, opening inventory position, and market parameters before formulating your decisions for Year <?php echo (int) $yearNo; ?>.</p>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Bottom Navigation Bar -->
<div class="cmd-bottom-bar">
  <a href="<?php echo PLAYER_URL; ?>landing.php" class="cmd-btn-secondary">
    <i class="fa-solid fa-house"></i> Home Dashboard
  </a>
  <a href="<?php echo PLAYER_URL; ?>screens/opening_statistics.php" class="cmd-btn-primary">
    Opening Statistics <i class="fa-solid fa-arrow-right"></i>
  </a>
</div>

<?php require_once __DIR__ . '/../includes/command_footer.php'; ?>
