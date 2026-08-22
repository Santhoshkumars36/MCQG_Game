<?php
/**
 * MCQG Player - Executive Command Centre Header
 * Path: player/includes/command_header.php
 */
if (!isset($pageTitle)) { $pageTitle = 'Executive Simulation'; }
if (!isset($activeStep)) { $activeStep = 1; }

$db = Database::getInstance();
$teamId = Session::currentTeamId();
$gameId = Session::activeGameId();

$team = $teamId ? $db->fetchOne("SELECT * FROM team_master WHERE team_id = :t", ['t' => $teamId]) : null;
$game = $gameId ? $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]) : null;

$currentRound = $db->fetchOne(
    "SELECT * FROM game_round_status WHERE game_id = :g AND status != 'Processed' ORDER BY year_no LIMIT 1",
    ['g' => $gameId]
);
if (!isset($yearNo)) { 
    $yearNo = $currentRound ? (int)$currentRound['year_no'] : 1; 
}
$unreadHeaderCount = (int) ($db->fetchOne(
    "SELECT COUNT(*) AS cnt FROM message_center WHERE game_id = :g AND (team_id IS NULL OR team_id = :t) AND sender_type != 'Team' AND is_read = 0",
    ['g' => $gameId, 't' => $teamId]
)['cnt'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle); ?> — MCQG</title>

  <!-- Core Styles -->
  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="<?php echo PLAYER_URL; ?>assets/css/player-command-centre.css">

  <script>
    const AJAX_PLAYER_URL = "<?php echo AJAX_URL; ?>player_ajax/";
    const PLAYER_URL      = "<?php echo PLAYER_URL; ?>";
  </script>
</head>
<body>

<!-- Executive Top Navbar -->
<header class="cmd-navbar">
  <div class="cmd-navbar-brand">
    <a href="<?php echo PLAYER_URL; ?>landing.php" style="color:inherit; text-decoration:none;" class="d-flex align-items-center gap-2">
      <div style="background:var(--cmd-blue-accent); color:#fff; width:32px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px;">MQ</div>
      <span class="cmd-brand-title"><?php echo htmlspecialchars($game['game_name'] ?? 'Executive Simulation'); ?></span>
    </a>
    <span class="cmd-round-badge"><i class="fa-solid fa-clock-rotate-left me-1"></i>Year <?php echo (int) $yearNo; ?></span>
  </div>

  <!-- Stepper navigation in top navbar -->
  <div class="cmd-stepper d-none d-md-flex">
    <a href="<?php echo PLAYER_URL; ?>screens/case_study.php" class="cmd-step-item <?php echo $activeStep == 1 ? 'active' : ($activeStep > 1 ? 'completed' : ''); ?>">
      <span class="cmd-step-num"><?php echo $activeStep > 1 ? '✓' : '1'; ?></span>
      <span>Case Study</span>
    </a>
    <span style="color:#475569; font-size:12px;">›</span>
    <a href="<?php echo PLAYER_URL; ?>screens/opening_statistics.php" class="cmd-step-item <?php echo $activeStep == 2 ? 'active' : ($activeStep > 2 ? 'completed' : ''); ?>">
      <span class="cmd-step-num"><?php echo $activeStep > 2 ? '✓' : '2'; ?></span>
      <span>Opening Statistics</span>
    </a>
    <span style="color:#475569; font-size:12px;">›</span>
    <a href="<?php echo PLAYER_URL; ?>screens/capacity_management.php" class="cmd-step-item <?php echo $activeStep == 3 ? 'active' : ($activeStep > 3 ? 'completed' : ''); ?>">
      <span class="cmd-step-num"><?php echo $activeStep > 3 ? '✓' : '3'; ?></span>
      <span>Capacity Management</span>
    </a>
    <span style="color:#475569; font-size:12px;">›</span>
    <a href="<?php echo PLAYER_URL; ?>screens/demand_management.php" class="cmd-step-item <?php echo $activeStep == 4 ? 'active' : ($activeStep > 4 ? 'completed' : ''); ?>">
      <span class="cmd-step-num"><?php echo $activeStep > 4 ? '✓' : '4'; ?></span>
      <span>Demand Management</span>
    </a>
  </div>

  <div class="cmd-navbar-right">
    <!-- Moderator Messages Button with WhatsApp Red Count Badge -->
    <button class="btn btn-outline-light btn-sm me-2 position-relative fw-bold" style="border-radius:8px; font-size:12.5px;" type="button" onclick="openLiveChatModal()">
      <i class="fa-solid fa-comments text-warning me-1"></i> Moderator Chat
      <span id="header-unread-badge" class="whatsapp-badge position-absolute top-0 start-100 translate-middle" style="<?php echo $unreadHeaderCount > 0 ? '' : 'display:none;'; ?>">
        <?php echo $unreadHeaderCount; ?>
      </span>
    </button>

    <a href="<?php echo PLAYER_URL; ?>team_registration.php" class="btn btn-outline-warning btn-sm me-2 fw-bold" style="border-radius:8px; font-size:12.5px;">
      <i class="fa-solid fa-gamepad me-1"></i> Games Hub
    </a>
    <div class="cmd-team-info">
      <div>Team</div>
      <div class="cmd-team-name"><?php echo htmlspecialchars($team['team_name'] ?? 'Player'); ?></div>
    </div>
    <a href="<?php echo PLAYER_URL; ?>auth/logout.php" class="cmd-logout-btn">
      <i class="fa-solid fa-right-from-bracket me-1"></i>Logout
    </a>
  </div>
</header>
