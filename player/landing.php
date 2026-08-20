<?php
/**
 * MCQG Player - Team Landing Page
 * Path: player/landing.php
 * Purpose: First screen a team sees after login.
 *  - Left: Core Briefing / Case Study card (image + executive summary + key objectives)
 *  - Bottom: One round-card per year_no, each showing its real-time status:
 *      Completed  — shows "View Results" + "View Report" buttons, processing time
 *      Active     — shows "Play" button (only for Open/AllSubmitted rounds)
 *      Locked     — greyed out; rounds that have not been created/opened yet
 * Design source: Reference slides 11-13 from project spec ("Sample landing page").
 */
require_once __DIR__ . '/../config/app_config.php';
Auth::requireTeam();

$db     = Database::getInstance();
$teamId = Session::currentTeamId();
$gameId = Session::activeGameId();

/* ── Guard: team + game must exist ── */
$team = $teamId ? $db->fetchOne("SELECT * FROM team_master WHERE team_id = :t AND is_active = 1", ['t' => $teamId]) : null;
$game = $gameId ? $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]) : null;

if (!$team || !$game) {
    Auth::logout();
    header('Location: ' . PLAYER_URL . 'auth/login.php');
    exit;
}

$totalYears = (int) ($game['no_of_years'] ?? 5);

/* ── Load all created round rows for this game ── */
$roundRows = $db->fetchAll(
    "SELECT * FROM game_round_status WHERE game_id = :g ORDER BY year_no",
    ['g' => $gameId]
);

// Index by year_no for quick lookup
$roundsByYear = [];
foreach ($roundRows as $r) {
    $roundsByYear[(int) $r['year_no']] = $r;
}

/* ── Load this team's processed results (to know completed rounds + timings) ── */
$teamResults = $db->fetchAll(
    "SELECT year_no, cash_position, operating_profit, createdOn FROM team_result WHERE team_id = :t AND game_id = :g ORDER BY year_no",
    ['t' => $teamId, 'g' => $gameId]
);
$resultsByYear = [];
foreach ($teamResults as $res) {
    $resultsByYear[(int) $res['year_no']] = $res;
}

/* ── Team's latest financials ── */
$latestResult = $teamResults ? end($teamResults) : null;
$currentCash  = $latestResult ? (float) $latestResult['cash_position'] : (float) ($team['opening_budget'] ?? 0);
$lastInventory = $db->fetchOne(
    "SELECT closing_inventory FROM team_result WHERE team_id = :t ORDER BY year_no DESC LIMIT 1",
    ['t' => $teamId]
);
$currentInv = $lastInventory ? (int) $lastInventory['closing_inventory'] : (int) ($team['opening_inventory'] ?? 0);
$lastProfit  = $latestResult ? (float) $latestResult['operating_profit'] : null;

/* ── Count completed rounds ── */
$completedCount = count($resultsByYear);
$activeRoundNo  = null;   // the currently playable year

/* ── Determine status label for each year slot ──
   Status logic (matches spec + database):
     'Processed' in DB      → Completed  (team has a result row)
     'Open'/'AllSubmitted'  → Active     (one open round at a time)
     no row yet             → Locked
*/
function resolveRoundStatus(int $yearNo, array $roundsByYear, array $resultsByYear): string
{
    if (isset($resultsByYear[$yearNo])) {
        return 'completed';
    }
    if (!isset($roundsByYear[$yearNo])) {
        return 'locked';
    }
    $status = $roundsByYear[$yearNo]['status'] ?? '';
    if (in_array($status, ['Open', 'AllSubmitted'])) {
        return 'active';
    }
    if ($status === 'Processed') {
        // Should have a result row, but handle gracefully
        return 'completed';
    }
    return 'locked';
}

/* ── Find active year ── */
for ($y = 1; $y <= $totalYears; $y++) {
    if (resolveRoundStatus($y, $roundsByYear, $resultsByYear) === 'active') {
        $activeRoundNo = $y;
        break;
    }
}

/* ── Description: strip HTML for the plain executive summary ── */
$rawDesc    = $game['description'] ?? '';
$execSummary = trim(strip_tags($rawDesc));
// Limit to first 280 chars for the summary excerpt
if (mb_strlen($execSummary) > 280) {
    $execSummary = mb_substr($execSummary, 0, 280) . '…';
}

/* ── Game image ── */
$gameImg = Game::getImageUrl($game);

/* ── Round name helper: uses year_no for "Round N" labeling ── */
$pageTitle = 'Home';
$currency  = DEFAULT_CURRENCY_SYMBOL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($team['team_name']); ?> — MCQG</title>
  <meta name="description" content="Team landing page — view round statuses and access the case study.">

  <!-- Shared assets -->
  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Landing-specific CSS (no sidebar on this page) -->
  <link rel="stylesheet" href="<?php echo PLAYER_URL; ?>assets/css/player-landing.css">

  <script>
    const AJAX_PLAYER_URL = "<?php echo AJAX_URL; ?>player_ajax/";
    const PLAYER_URL      = "<?php echo PLAYER_URL; ?>";
  </script>
</head>
<body>
<div class="landing-shell">

  <!-- ══════════════════════════════════════
       HEADER
       ══════════════════════════════════════ -->
  <header class="land-header">
    <div class="land-header-brand">
      <div class="land-header-logo">MQ</div>
      <div>
        <span class="land-header-team-name"><?php echo htmlspecialchars($team['team_name']); ?></span>
        <span class="land-header-game-label">&nbsp;·&nbsp;<?php echo htmlspecialchars($game['game_name']); ?></span>
      </div>
    </div>
    <div class="land-header-actions">
      <a href="<?php echo PLAYER_URL; ?>auth/logout.php" class="land-logout-btn" id="btn-logout">
        <i class="fa-solid fa-right-from-bracket"></i>
        Logout
      </a>
    </div>
  </header>

  <!-- ══════════════════════════════════════
       MAIN CONTENT
       ══════════════════════════════════════ -->
  <main class="land-content">

    <?php if ($activeRoundNo === null && $completedCount === 0): ?>
    <!-- No rounds launched yet -->
    <div class="land-waiting-alert">
      <i class="fa-solid fa-clock"></i>
      Waiting for the Moderator to launch Round 1. You can read the Case Study below while you wait.
    </div>
    <?php endif; ?>

    <!-- ── QUICK STATS BAR ── -->
    <div class="land-stats-bar">
      <div class="land-stat-pill">
        <div class="land-stat-icon ico-budget"><i class="fa-solid fa-wallet"></i></div>
        <div>
          <div class="land-stat-val"><?php echo $currency . number_format($currentCash, 0); ?></div>
          <div class="land-stat-lbl"><?php echo $completedCount > 0 ? 'Current Cash' : 'Opening Budget'; ?></div>
        </div>
      </div>
      <div class="land-stat-pill">
        <div class="land-stat-icon ico-inv"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div>
          <div class="land-stat-val"><?php echo number_format($currentInv); ?> Units</div>
          <div class="land-stat-lbl"><?php echo $completedCount > 0 ? 'Closing Inventory' : 'Opening Inventory'; ?></div>
        </div>
      </div>
      <?php if ($lastProfit !== null): ?>
      <div class="land-stat-pill">
        <div class="land-stat-icon ico-profit"><i class="fa-solid fa-chart-line"></i></div>
        <div>
          <div class="land-stat-val" style="color:<?php echo $lastProfit >= 0 ? '#1a9d55' : '#d9364f'; ?>">
            <?php echo $currency . number_format($lastProfit, 0); ?>
          </div>
          <div class="land-stat-lbl">Last Round Profit</div>
        </div>
      </div>
      <?php endif; ?>
      <div class="land-stat-pill">
        <div class="land-stat-icon ico-rounds"><i class="fa-solid fa-flag-checkered"></i></div>
        <div>
          <div class="land-stat-val"><?php echo $completedCount; ?> / <?php echo $totalYears; ?></div>
          <div class="land-stat-lbl">Rounds Completed</div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════
         CORE BRIEFING CARD
         ══════════════════════════════════════ -->
    <div class="land-briefing-card" id="briefing-card">

      <!-- Left: Image -->
      <div class="land-briefing-img-wrap">
        <?php if ($gameImg): ?>
          <img src="<?php echo htmlspecialchars($gameImg); ?>"
               alt="<?php echo htmlspecialchars($game['game_name']); ?>"
               class="land-briefing-img">
        <?php else: ?>
          <div class="land-briefing-img-placeholder">
            <i class="fa-solid fa-globe land-briefing-img-icon"></i>
          </div>
        <?php endif; ?>
        <div class="land-briefing-overlay">
          <div class="land-core-tag">Core Briefing</div>
          <p class="land-briefing-title"><?php echo htmlspecialchars($game['game_name']); ?></p>
        </div>
      </div>

      <!-- Right: Executive Summary + Objectives -->
      <div class="land-briefing-body">
        <div>
          <div class="land-briefing-top">
            <div>
              <h2 class="land-exec-title">Executive Summary</h2>
              <p class="land-exec-summary">
                <?php echo $execSummary ?: 'This case study presents the strategic business scenario for the game. Read the full briefing for detailed context and objectives.'; ?>
              </p>
            </div>
            <div class="land-briefing-actions">
              <a href="<?php echo PLAYER_URL; ?>screens/case_study.php"
                 class="land-btn-open" id="btn-open-case">
                <i class="fa-solid fa-up-right-from-square"></i>
                Open
              </a>
              <?php
              // PDF: if the game description is rich HTML, we link to case study page for print
              ?>
              <a href="<?php echo PLAYER_URL; ?>screens/case_study.php?action=pdf"
                 class="land-btn-pdf" id="btn-pdf-case" target="_blank">
                <i class="fa-solid fa-file-pdf"></i>
                PDF
              </a>
            </div>
          </div>

          <!-- Key Objectives parsed from HTML description bullet points -->
          <?php
          // Extract bullet points from the description if it's rich text
          $objectives = [];
          if (!empty($rawDesc)) {
              // Try <li> tags first
              preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $rawDesc, $liMatches);
              if (!empty($liMatches[1])) {
                  foreach (array_slice($liMatches[1], 0, 4) as $li) {
                      $clean = trim(strip_tags($li));
                      if ($clean) $objectives[] = $clean;
                  }
              }
          }
          // Fallback defaults if none found
          if (empty($objectives)) {
              $objectives = [
                  'Identify competitive gaps and market opportunities',
                  'Optimise production capacity and supply chain',
                  'Manage cash flow and investment decisions',
                  'Maximise operating profit across all rounds',
              ];
          }
          ?>

          <div class="land-objectives-wrap">
            <div class="land-objectives-header">
              <i class="fa-solid fa-bullseye-arrow"></i>
              Key Objectives
            </div>
            <div class="land-objectives-grid">
              <?php foreach ($objectives as $obj): ?>
              <div class="land-objective-item">
                <div class="land-objective-dot"></div>
                <span><?php echo htmlspecialchars($obj); ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- / briefing card -->

    <!-- ══════════════════════════════════════
         ROUNDS ROW
         ══════════════════════════════════════ -->
    <div class="land-rounds-heading">
      <h3 class="land-rounds-title">Your Rounds</h3>
      <span class="land-rounds-subtitle"><?php echo $totalYears; ?> round<?php echo $totalYears !== 1 ? 's' : ''; ?> in this game</span>
    </div>

    <?php if ($totalYears === 0): ?>
    <div class="land-no-rounds">
      <i class="fa-solid fa-circle-info"></i>
      No rounds configured for this game yet. Check back once the admin has set up the rounds.
    </div>
    <?php else: ?>
    <div class="land-rounds-row" id="rounds-row">

      <?php for ($y = 1; $y <= $totalYears; $y++):
        $roundStatus = resolveRoundStatus($y, $roundsByYear, $resultsByYear);
        $roundRow    = $roundsByYear[$y] ?? null;
        $result      = $resultsByYear[$y] ?? null;

        // Time displayed: if completed, show processing timestamp formatted as HH:MM
        $timeDisplay = '--:--';
        if ($roundStatus === 'completed' && $roundRow && !empty($roundRow['processed_on'])) {
            $timeDisplay = date('H:i', strtotime($roundRow['processed_on']));
        }

        // Is this team's decision submitted for active round?
        $teamDecision = null;
        if ($roundStatus === 'active') {
            $teamDecision = $db->fetchOne(
                "SELECT * FROM team_decision WHERE team_id = :t AND year_no = :y",
                ['t' => $teamId, 'y' => $y]
            );
        }
        $isSubmitted = $teamDecision && $teamDecision['status'] === 'Submitted';
      ?>

      <!-- Round <?php echo $y; ?> -->
      <div class="land-round-card state-<?php echo $roundStatus; ?>" id="round-card-<?php echo $y; ?>">

        <?php if ($roundStatus === 'active'): ?>
        <div class="land-active-badge-wrap">
          <span class="land-active-badge"><i class="fa-solid fa-circle-dot" style="font-size:8px;margin-right:4px;"></i>Active</span>
        </div>
        <?php endif; ?>

        <!-- Card Header -->
        <div class="land-round-header">
          <div>
            <p class="land-round-label">Round <?php echo $y; ?></p>
            <h4 class="land-round-name">Year <?php echo $y; ?></h4>
          </div>
          <div class="land-round-icon <?php echo $roundStatus === 'completed' ? 'icon-completed' : ($roundStatus === 'locked' ? 'icon-locked' : ''); ?>">
            <?php if ($roundStatus === 'completed'): ?>
              <i class="fa-solid fa-circle-check"></i>
            <?php elseif ($roundStatus === 'locked'): ?>
              <i class="fa-solid fa-lock"></i>
            <?php endif; ?>
          </div>
        </div>

        <!-- Status / Time Meta -->
        <div class="land-round-meta">
          <div class="land-round-meta-row">
            <span class="land-meta-label">Status:</span>
            <?php if ($roundStatus === 'completed'): ?>
              <span class="land-meta-value val-completed">Completed</span>
            <?php elseif ($roundStatus === 'active' && $isSubmitted): ?>
              <span class="land-meta-value val-submitted">Submitted</span>
            <?php elseif ($roundStatus === 'active'): ?>
              <span class="land-meta-value val-inprogress">In Progress</span>
            <?php else: ?>
              <span class="land-meta-value val-locked">Locked</span>
            <?php endif; ?>
          </div>
          <div class="land-round-meta-row">
            <span class="land-meta-label">Time:</span>
            <span class="land-meta-value" style="font-variant-numeric:tabular-nums;">
              <?php echo htmlspecialchars($timeDisplay); ?>
            </span>
          </div>
        </div>

        <!-- Actions -->
        <div class="land-round-actions">
          <?php if ($roundStatus === 'completed'): ?>
            <a href="<?php echo PLAYER_URL; ?>results/round_results.php?year_no=<?php echo $y; ?>"
               class="land-btn-results" id="btn-results-<?php echo $y; ?>">
              <i class="fa-solid fa-chart-bar" style="margin-right:5px;"></i>View Results
            </a>
            <a href="<?php echo PLAYER_URL; ?>results/competitive_analysis.php?year_no=<?php echo $y; ?>"
               class="land-btn-results" id="btn-report-<?php echo $y; ?>">
              <i class="fa-solid fa-file-lines" style="margin-right:5px;"></i>View Report
            </a>

          <?php elseif ($roundStatus === 'active'): ?>
            <?php if ($isSubmitted): ?>
              <a href="<?php echo PLAYER_URL; ?>screens/review_decision.php"
                 class="land-btn-play" id="btn-play-<?php echo $y; ?>"
                 style="background:var(--land-orange);">
                <i class="fa-solid fa-eye" style="margin-right:6px;"></i>View Decision
              </a>
            <?php else: ?>
              <a href="<?php echo PLAYER_URL; ?>screens/case_study.php"
                 class="land-btn-play" id="btn-play-<?php echo $y; ?>">
                <i class="fa-solid fa-play" style="margin-right:6px;"></i>Play
              </a>
            <?php endif; ?>

          <?php else: /* locked */ ?>
            <span class="land-btn-locked" id="btn-locked-r-<?php echo $y; ?>">
              <i class="fa-solid fa-lock" style="margin-right:4px;"></i>Locked
            </span>
            <span class="land-btn-locked" id="btn-locked-rep-<?php echo $y; ?>">
              <i class="fa-solid fa-lock" style="margin-right:4px;"></i>Locked
            </span>
          <?php endif; ?>
        </div>
      </div>

      <?php endfor; ?>
    </div><!-- /#rounds-row -->
    <?php endif; ?>

  </main>
  <!-- /main content -->

  <!-- ══════════════════════════════════════
       FOOTER
       ══════════════════════════════════════ -->
  <footer class="land-footer">
    <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($game['game_name']); ?>. All rights reserved.</span>
    <div class="land-footer-links">
      <a href="<?php echo PLAYER_URL; ?>screens/case_study.php">Case Study</a>
      <a href="<?php echo PLAYER_URL; ?>results/cumulative_standing.php">Standings</a>
      <a href="<?php echo PLAYER_URL; ?>auth/logout.php">Logout</a>
    </div>
  </footer>

</div><!-- /.landing-shell -->

<!-- JS (Bootstrap only — no sidebar JS needed) -->
<script src="<?php echo ASSET_URL; ?>js/jquery.min.js"></script>
<script src="<?php echo ASSET_URL; ?>js/bootstrap.bundle.min.js"></script>

<script>
/* Auto-scroll active round card into view on load */
document.addEventListener('DOMContentLoaded', function () {
  <?php if ($activeRoundNo): ?>
  const activeCard = document.getElementById('round-card-<?php echo $activeRoundNo; ?>');
  if (activeCard) {
    setTimeout(function () {
      activeCard.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }, 300);
  }
  <?php endif; ?>
});
</script>
</body>
</html>
