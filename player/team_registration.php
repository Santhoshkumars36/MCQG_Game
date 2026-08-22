<?php
/**
 * MCQG Player - Team Registration Hub
 * Path: player/team_registration.php
 * Purpose: Interactive landing page after team login.
 *  - Displays all published games available for registration.
 *  - Displays registration details and status for each game.
 *  - Allows navigating to dedicated game registration pages.
 *  - Displays Play button for registered games to enter simulation.
 *  - Enforces restriction: disables/collapses participation and shows popup
 *    if a game has already started and the first-round result is announced.
 */
require_once __DIR__ . '/../config/app_config.php';
Auth::requireTeam();

$db = Database::getInstance();
$teamUsername = Session::currentTeamUsername() ?: Session::get(SESSION_TEAM_NAME);

// Fetch all published games
$publishedGames = $db->fetchAll(
    "SELECT * FROM game_master WHERE status = :s AND (isDeleted IS NULL OR isDeleted = 0) ORDER BY game_id DESC",
    ['s' => GAME_STATUS_PUBLISHED]
);

// Map games with registration details & round 1 status
$gameCards = [];
$totalGamesCount = count($publishedGames);
$registeredCount = 0;
$openGamesCount = 0;
$lockedGamesCount = 0;

foreach ($publishedGames as $g) {
    $gameId = (int)$g['game_id'];

    // Check if Round 1 is processed / announced
    $round1Status = $db->fetchOne(
        "SELECT status, processed_on FROM game_round_status WHERE game_id = :g AND year_no = 1",
        ['g' => $gameId]
    );
    $resultCountR1 = (int)$db->fetchOne(
        "SELECT COUNT(*) AS c FROM team_result WHERE game_id = :g AND year_no = 1",
        ['g' => $gameId]
    )['c'];

    $isFirstRoundReleased = ($round1Status && $round1Status['status'] === ROUND_STATUS_PROCESSED) || ($resultCountR1 > 0);

    // Check if team is registered for this game
    $registeredTeam = null;
    if ($teamUsername) {
        $registeredTeam = $db->fetchOne(
            "SELECT * FROM team_master WHERE game_id = :g AND LOWER(TRIM(username)) = LOWER(:u) AND (isDeleted IS NULL OR isDeleted = 0)",
            ['g' => $gameId, 'u' => trim($teamUsername)]
        );
    }
    
    // Fallback: check by current session team_id if game matches
    if (!$registeredTeam && Session::currentTeamId() && Session::activeGameId() === $gameId) {
        $registeredTeam = $db->fetchOne(
            "SELECT * FROM team_master WHERE team_id = :t AND game_id = :g",
            ['t' => Session::currentTeamId(), 'g' => $gameId]
        );
    }

    $isRegistered = !empty($registeredTeam);
    $teamId = $registeredTeam ? (int)$registeredTeam['team_id'] : 0;
    
    // Check if team submitted decision in Round 1 prior to processing
    $round1Decision = $teamId ? $db->fetchOne(
        "SELECT * FROM team_decision WHERE team_id = :t AND year_no = 1",
        ['t' => $teamId]
    ) : null;

    // Eligibility logic
    // A newly registered player/team cannot participate if Round 1 result is already announced
    $canPlay = $isRegistered && (!$isFirstRoundReleased || !empty($round1Decision));
    $canRegister = !$isRegistered && !$isFirstRoundReleased;
    $isLocked = $isFirstRoundReleased && (!$isRegistered || !$canPlay);

    if ($isRegistered) {
        $registeredCount++;
    }
    if ($canRegister) {
        $openGamesCount++;
    }
    if ($isLocked) {
        $lockedGamesCount++;
    }

    // Clean excerpt for executive summary
    $rawDesc = $g['description'] ?? '';
    $cleanDesc = trim(strip_tags($rawDesc));
    if (mb_strlen($cleanDesc) > 160) {
        $cleanDesc = mb_substr($cleanDesc, 0, 160) . '…';
    }

    $gameCards[] = [
        'game'                   => $g,
        'game_id'               => $gameId,
        'game_name'             => $g['game_name'],
        'product_name'          => $g['product_name'],
        'unit_of_measure'       => $g['unit_of_measure'],
        'currency'              => $g['currency'] ?: 'INR',
        'starting_cash'         => (float)($g['starting_cash'] ?? 0),
        'starting_capacity'     => (int)($g['starting_capacity'] ?? 0),
        'starting_inventory'    => (int)($g['starting_inventory'] ?? 0),
        'no_of_years'           => (int)($g['no_of_years'] ?? 5),
        'game_image'            => Game::getImageUrl($g),
        'summary'               => $cleanDesc ?: 'Comprehensive quantitative simulation of market competition.',
        'is_registered'         => $isRegistered,
        'registered_team_name'  => $registeredTeam['team_name'] ?? '',
        'registered_team_id'    => $teamId,
        'first_round_released'  => $isFirstRoundReleased,
        'can_play'              => $canPlay,
        'can_register'          => $canRegister,
        'is_locked'             => $isLocked,
    ];
}

$pageTitle = 'Team Registration Hub';
$triggerError = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Team Registration Hub — MCQG</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="<?php echo PLAYER_URL; ?>assets/css/team-registration.css">
</head>
<body class="team-reg-body">

  <!-- Top Navigation Header -->
  <header class="reg-navbar">
    <div class="reg-brand">
      <div class="reg-logo-box">MQ</div>
      <span class="reg-brand-title">MCQG Executive Portal</span>
    </div>
    <div class="d-flex align-items-center gap-3">
      <div class="reg-user-badge">
        <i class="fa-solid fa-user-gear" style="color:var(--mcqg-gold);"></i>
        <span>Logged in as: <strong><?php echo htmlspecialchars($teamUsername); ?></strong></span>
      </div>
      <a href="<?php echo PLAYER_URL; ?>auth/logout.php" class="btn-reg-logout">
        <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
      </a>
    </div>
  </header>

  <div class="reg-container">
    
    <!-- Hero Header -->
    <div class="reg-hero">
      <h1 class="reg-hero-title">Team Game Registration Hub</h1>
      <p class="reg-hero-subtitle">
        Select a game session to register your team, review initial competitive parameters, or launch into active simulation rounds.
      </p>
    </div>

    <!-- Quick Statistics Bar -->
    <div class="reg-stats-grid">
      <div class="reg-stat-card">
        <div class="reg-stat-icon icon-blue"><i class="fa-solid fa-trophy"></i></div>
        <div>
          <div class="reg-stat-val"><?php echo $totalGamesCount; ?></div>
          <div class="reg-stat-lbl">Total Published Games</div>
        </div>
      </div>
      <div class="reg-stat-card">
        <div class="reg-stat-icon icon-green"><i class="fa-solid fa-square-check"></i></div>
        <div>
          <div class="reg-stat-val"><?php echo $registeredCount; ?></div>
          <div class="reg-stat-lbl">My Game Registrations</div>
        </div>
      </div>
      <div class="reg-stat-card">
        <div class="reg-stat-icon icon-gold"><i class="fa-solid fa-door-open"></i></div>
        <div>
          <div class="reg-stat-val"><?php echo $openGamesCount; ?></div>
          <div class="reg-stat-lbl">Open for Registration</div>
        </div>
      </div>
      <div class="reg-stat-card">
        <div class="reg-stat-icon icon-red"><i class="fa-solid fa-lock"></i></div>
        <div>
          <div class="reg-stat-val"><?php echo $lockedGamesCount; ?></div>
          <div class="reg-stat-lbl">Locked / Started</div>
        </div>
      </div>
    </div>

    <!-- Filter & Search Control -->
    <div class="reg-filter-bar">
      <div class="reg-search-box">
        <i class="fa-solid fa-magnifying-glass reg-search-icon"></i>
        <input type="text" id="searchInput" class="reg-search-input" placeholder="Search games by title or product name...">
      </div>
      <div class="reg-filter-pills">
        <button class="filter-btn active" data-filter="all">All Games (<?php echo $totalGamesCount; ?>)</button>
        <button class="filter-btn" data-filter="registered">Registered (<?php echo $registeredCount; ?>)</button>
        <button class="filter-btn" data-filter="open">Open (<?php echo $openGamesCount; ?>)</button>
        <button class="filter-btn" data-filter="locked">Locked (<?php echo $lockedGamesCount; ?>)</button>
      </div>
    </div>

    <!-- Games Grid -->
    <?php if (empty($gameCards)): ?>
      <div class="text-center py-5 bg-dark rounded-4 border border-secondary">
        <i class="fa-solid fa-folder-open fa-3x text-muted mb-3"></i>
        <h4 class="text-white font-weight-bold">No Active Games Available</h4>
        <p class="text-muted">There are currently no published games available for registration.</p>
      </div>
    <?php else: ?>
      <div class="reg-games-grid" id="gamesGrid">
        <?php foreach ($gameCards as $card): 
          $filterCategory = 'open';
          if ($card['is_registered']) { $filterCategory = 'registered'; }
          if ($card['is_locked']) { $filterCategory = 'locked'; }
        ?>
          <div class="game-card <?php echo $card['is_locked'] ? 'card-disabled' : ''; ?>" 
               data-title="<?php echo htmlspecialchars(strtolower($card['game_name'] . ' ' . $card['product_name'])); ?>"
               data-category="<?php echo $filterCategory; ?>">
            
            <!-- Banner / Thumbnail Header -->
            <div class="card-banner">
              <?php if ($card['game_image']): ?>
                <img src="<?php echo htmlspecialchars($card['game_image']); ?>" alt="Game Banner" class="card-banner-img">
              <?php endif; ?>
              <div class="card-banner-overlay">
                <span class="badge-status <?php echo $card['is_registered'] ? 'badge-registered' : ($card['is_locked'] ? 'badge-locked' : 'badge-open'); ?>">
                  <?php if ($card['is_registered']): ?>
                    <i class="fa-solid fa-circle-check me-1"></i> Registered
                  <?php elseif ($card['is_locked']): ?>
                    <i class="fa-solid fa-lock me-1"></i> Round 1 Closed
                  <?php else: ?>
                    <i class="fa-solid fa-user-plus me-1"></i> Open Registration
                  <?php endif; ?>
                </span>
                <span class="text-white small fw-bold"><i class="fa-solid fa-calendar-days me-1"></i> <?php echo $card['no_of_years']; ?> Rounds</span>
              </div>
            </div>

            <!-- Card Body -->
            <div class="card-body-content">
              <h3 class="game-title"><?php echo htmlspecialchars($card['game_name']); ?></h3>
              <div class="game-product-tag">
                <i class="fa-solid fa-box-open" style="color:var(--mcqg-gold);"></i>
                <span>Product: <strong><?php echo htmlspecialchars($card['product_name']); ?></strong> (<?php echo htmlspecialchars($card['unit_of_measure']); ?>)</span>
              </div>

              <!-- Collapsed / Disabled Locked Banner (Point 6) -->
              <?php if ($card['is_locked']): ?>
                <div class="locked-banner" onclick="showLockedPopup()">
                  <i class="fa-solid fa-circle-exclamation fs-5"></i>
                  <div>
                    <strong>Participation Locked:</strong> Game started &amp; first-round result released.
                  </div>
                </div>
              <?php endif; ?>

              <!-- Specifications Grid -->
              <div class="specs-grid">
                <div class="spec-item">
                  <span class="spec-lbl">Starting Cash</span>
                  <span class="spec-val"><?php echo htmlspecialchars($card['currency']); ?> <?php echo number_format($card['starting_cash'], 0); ?></span>
                </div>
                <div class="spec-item">
                  <span class="spec-lbl">Starting Capacity</span>
                  <span class="spec-val"><?php echo number_format($card['starting_capacity']); ?> Units</span>
                </div>
                <div class="spec-item">
                  <span class="spec-lbl">Starting Inventory</span>
                  <span class="spec-val"><?php echo number_format($card['starting_inventory']); ?> Units</span>
                </div>
                <div class="spec-item">
                  <span class="spec-lbl">Registration</span>
                  <span class="spec-val" style="color:<?php echo $card['is_registered'] ? '#34d399' : '#f59e0b'; ?>;">
                    <?php echo $card['is_registered'] ? htmlspecialchars($card['registered_team_name']) : 'Not Enrolled'; ?>
                  </span>
                </div>
              </div>

              <div class="summary-preview">
                <?php echo htmlspecialchars($card['summary']); ?>
              </div>

              <!-- Card Actions (Points 2, 5, 6) -->
              <div class="card-actions">
                <?php if ($card['can_play']): ?>
                  <!-- Registered & Allowed: Play Button (Point 5) -->
                  <a href="<?php echo PLAYER_URL; ?>select_game.php?game_id=<?php echo $card['game_id']; ?>" class="btn-mcqg-play">
                    <i class="fa-solid fa-play"></i> Play Game
                  </a>
                <?php elseif ($card['can_register']): ?>
                  <!-- Open for Registration: Register Button (Point 2 - Dedicated Page) -->
                  <a href="<?php echo PLAYER_URL; ?>game_register.php?game_id=<?php echo $card['game_id']; ?>" class="btn-mcqg-register">
                    <i class="fa-solid fa-user-plus"></i> Register for Game
                  </a>
                <?php else: ?>
                  <!-- Game Started / First Round Released: Disabled/Collapsed Button (Point 6) -->
                  <button type="button" class="btn-mcqg-disabled" onclick="showLockedPopup()">
                    <i class="fa-solid fa-lock"></i> Participation Locked
                  </button>
                <?php endif; ?>
              </div>

            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>

  <script src="<?php echo ASSET_URL; ?>js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    // Popup message when trying to access a locked game (Point 6 requirement)
    function showLockedPopup() {
      Swal.fire({
        icon: 'error',
        title: 'Participation Not Allowed',
        html: `
          <div class="text-start mt-2">
            <p style="color:#475569; font-size:15px; line-height:1.6; margin-bottom:12px;">
              Participation is no longer allowed because this game session has already started or the first-round result has been announced (released).
            </p>
            <div class="p-3 bg-light rounded-3 border text-secondary small">
              <i class="fa-solid fa-circle-info text-primary me-1"></i>
              Newly registered teams cannot enter or submit decisions for games where Round 1 results have already been processed.
            </div>
          </div>
        `,
        confirmButtonText: 'I Understand',
        confirmButtonColor: '#1e2761',
        customClass: {
          popup: 'rounded-4 shadow-lg border-0'
        }
      });
    }

    // Auto trigger lock popup if redirected with ?error=game_started
    <?php if ($triggerError === 'game_started'): ?>
      document.addEventListener('DOMContentLoaded', function() {
        showLockedPopup();
      });
    <?php endif; ?>

    // Search and Filter functionality
    const searchInput = document.getElementById('searchInput');
    const filterBtns = document.querySelectorAll('.filter-btn');
    const gameCards = document.querySelectorAll('.game-card');

    function filterCards() {
      const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
      const activeFilterBtn = document.querySelector('.filter-btn.active');
      const activeFilter = activeFilterBtn ? activeFilterBtn.dataset.filter : 'all';

      gameCards.forEach(card => {
        const title = card.dataset.title || '';
        const category = card.dataset.category || '';

        const matchesSearch = title.includes(query);
        const matchesCategory = (activeFilter === 'all') || (category === activeFilter);

        if (matchesSearch && matchesCategory) {
          card.style.display = 'flex';
        } else {
          card.style.display = 'none';
        }
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', filterCards);
    }

    filterBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        filterBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        filterCards();
      });
    });
  </script>
</body>
</html>
