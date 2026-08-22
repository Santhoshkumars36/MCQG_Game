<?php
/**
 * MCQG Player - Game Registration Screen
 * Path: player/game_register.php
 * Purpose: Dedicated page for registering a team into a specific game session.
 *  - Fulfills Point 2: "if a team needs to register for more than one game,
 *    it should be able to navigate to a new page for each game registration."
 *  - Enforces Point 6: blocks registration if first-round result is announced.
 */
require_once __DIR__ . '/../config/app_config.php';
Auth::requireTeam();

$db = Database::getInstance();
$teamUsername = Session::currentTeamUsername() ?: Session::get(SESSION_TEAM_NAME);
$gameId = (int)($_GET['game_id'] ?? $_POST['game_id'] ?? 0);

if (!$gameId) {
    header('Location: ' . PLAYER_URL . 'team_registration.php');
    exit;
}

// Fetch game master details
$game = $db->fetchOne(
    "SELECT * FROM game_master WHERE game_id = :g AND status = :s AND (isDeleted IS NULL OR isDeleted = 0)",
    ['g' => $gameId, 's' => GAME_STATUS_PUBLISHED]
);

if (!$game) {
    header('Location: ' . PLAYER_URL . 'team_registration.php');
    exit;
}

// Check if Round 1 is already processed / announced (Point 6 restriction)
$round1Status = $db->fetchOne(
    "SELECT status FROM game_round_status WHERE game_id = :g AND year_no = 1",
    ['g' => $gameId]
);
$resultCountR1 = (int)$db->fetchOne(
    "SELECT COUNT(*) AS c FROM team_result WHERE game_id = :g AND year_no = 1",
    ['g' => $gameId]
)['c'];

$isFirstRoundReleased = ($round1Status && $round1Status['status'] === ROUND_STATUS_PROCESSED) || ($resultCountR1 > 0);

// Check if team is already registered for this game
$existingTeam = $db->fetchOne(
    "SELECT * FROM team_master WHERE game_id = :g AND LOWER(TRIM(username)) = LOWER(:u) AND (isDeleted IS NULL OR isDeleted = 0)",
    ['g' => $gameId, 'u' => trim($teamUsername)]
);

$error = '';
$success = false;
$registeredTeamName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isFirstRoundReleased) {
        $error = 'Participation is no longer allowed because the game has already started or the first-round result has been released.';
    } elseif ($existingTeam) {
        $error = 'Your team username is already registered in this game session.';
    } else {
        $teamName = trim($_POST['team_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!Validator::required($teamName)) {
            $error = 'Please enter a Team Name.';
        } elseif (!Validator::required($username)) {
            $error = 'Please enter a Username.';
        } elseif (strlen($username) < 3) {
            $error = 'Username must be at least 3 characters long.';
        } elseif (!Validator::required($password)) {
            $error = 'Please enter a password.';
        } elseif (strlen($password) < 4) {
            $error = 'Password must be at least 4 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Password and Confirm Password do not match.';
        } else {
            // Check uniqueness in this game
            $checkTeamName = $db->fetchOne(
                "SELECT team_id FROM team_master WHERE LOWER(TRIM(team_name)) = LOWER(:tn) AND game_id = :gid",
                ['tn' => $teamName, 'gid' => $gameId]
            );
            $checkUsername = $db->fetchOne(
                "SELECT team_id FROM team_master WHERE LOWER(TRIM(username)) = LOWER(:u) AND game_id = :gid",
                ['u' => $username, 'gid' => $gameId]
            );

            if ($checkTeamName) {
                $error = 'Team Name "' . htmlspecialchars($teamName) . '" is already registered in this game session.';
            } elseif ($checkUsername) {
                $error = 'Username "' . htmlspecialchars($username) . '" is already registered in this game session.';
            } else {
                // Register team into team_master for this game
                $openingInventory = (int)($game['starting_inventory'] ?? 0);
                $openingBudget = (float)($game['starting_cash'] ?? 0);

                $newTeamId = $db->insert('team_master', [
                    'game_id'           => $gameId,
                    'team_name'         => $teamName,
                    'username'          => $username,
                    'password_hash'     => password_hash($password, PASSWORD_DEFAULT),
                    'opening_inventory' => $openingInventory,
                    'opening_budget'    => $openingBudget,
                    'is_active'         => 1,
                ]);

                Logger::activity("Team '{$teamName}' registered for Game #{$gameId}.");
                $success = true;
                $registeredTeamName = $teamName;
            }
        }
    }
}

$pageTitle = 'Register for ' . $game['game_name'];
$gameImg = Game::getImageUrl($game);
$currency = $game['currency'] ?: 'INR';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($pageTitle); ?> — MCQG</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="<?php echo PLAYER_URL; ?>assets/css/team-registration.css">
  <style>
    .game-reg-card {
      max-width: 680px;
      margin: 40px auto;
      background: rgba(30, 41, 59, 0.95);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 24px;
      padding: 40px;
      box-shadow: 0 25px 70px rgba(0,0,0,0.5);
    }
    .form-input-custom {
      background: rgba(15, 23, 42, 0.6);
      border: 1.5px solid rgba(255, 255, 255, 0.15);
      color: #ffffff !important;
      border-radius: 12px;
      padding: 12px 16px;
      font-size: 14.5px;
      transition: all 0.25s ease;
    }
    .form-input-custom:focus {
      background: rgba(15, 23, 42, 0.9);
      border-color: var(--mcqg-gold);
      box-shadow: 0 0 0 4px rgba(201, 154, 46, 0.25);
    }
    .btn-submit-reg {
      background: linear-gradient(135deg, var(--mcqg-gold) 0%, var(--mcqg-gold-light) 100%);
      color: var(--mcqg-navy-dark);
      border: none;
      height: 52px;
      border-radius: 12px;
      font-weight: 800;
      font-size: 16px;
      transition: all 0.25s ease;
      box-shadow: 0 10px 25px rgba(201, 154, 46, 0.3);
    }
    .btn-submit-reg:hover {
      transform: translateY(-2px);
      box-shadow: 0 14px 30px rgba(201, 154, 46, 0.45);
      color: #000000;
    }
  </style>
</head>
<body class="team-reg-body">

  <header class="reg-navbar">
    <div class="reg-brand">
      <a href="<?php echo PLAYER_URL; ?>team_registration.php" class="text-decoration-none d-flex align-items-center gap-2">
        <div class="reg-logo-box">MQ</div>
        <span class="reg-brand-title">MCQG Game Registration</span>
      </a>
    </div>
    <a href="<?php echo PLAYER_URL; ?>team_registration.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
      <i class="fa-solid fa-arrow-left me-1"></i> Back to Hub
    </a>
  </header>

  <div class="container py-4">
    <div class="game-reg-card">

      <!-- Header Section -->
      <div class="text-center mb-4">
        <span class="badge px-3 py-2 mb-2" style="background:rgba(201, 154, 46, 0.2); color:var(--mcqg-gold); border:1px solid rgba(201, 154, 46, 0.4); border-radius:20px; font-weight:700;">
          <i class="fa-solid fa-trophy me-1"></i> Game Session #<?php echo $gameId; ?>
        </span>
        <h2 class="text-white font-weight-bold mb-1"><?php echo htmlspecialchars($game['game_name']); ?></h2>
        <p class="text-muted small">Register your team to participate in this simulation session</p>
      </div>

      <!-- Error Alert -->
      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-4" style="border-radius:12px;">
          <i class="fa-solid fa-circle-exclamation me-2 fs-5"></i>
          <div><?php echo htmlspecialchars($error); ?></div>
        </div>
      <?php endif; ?>

      <!-- Check Point 6 Restriction -->
      <?php if ($isFirstRoundReleased): ?>
        <div class="alert alert-danger text-center p-4 rounded-4 my-4" style="background:rgba(239, 68, 68, 0.15); border:1px solid rgba(239, 68, 68, 0.4);">
          <i class="fa-solid fa-ban fa-3x mb-3 text-danger"></i>
          <h4 class="fw-bold text-white mb-2">Participation Not Allowed</h4>
          <p class="text-light mb-3" style="font-size:15px;">
            Participation is no longer allowed because this game session has already started or the first-round result has been released.
          </p>
          <a href="<?php echo PLAYER_URL; ?>team_registration.php" class="btn btn-danger px-4 py-2 fw-bold rounded-3">
            <i class="fa-solid fa-arrow-left me-2"></i>Return to Registration Hub
          </a>
        </div>
      <?php elseif ($existingTeam): ?>
        <div class="alert alert-success text-center p-4 rounded-4 my-4" style="background:rgba(16, 185, 129, 0.15); border:1px solid rgba(16, 185, 129, 0.4);">
          <i class="fa-solid fa-circle-check fa-3x mb-3 text-success"></i>
          <h4 class="fw-bold text-white mb-2">Already Registered</h4>
          <p class="text-light mb-3">Your team "<strong><?php echo htmlspecialchars($existingTeam['team_name']); ?></strong>" is already registered for this game session.</p>
          <a href="<?php echo PLAYER_URL; ?>select_game.php?game_id=<?php echo $gameId; ?>" class="btn btn-success px-4 py-2 fw-bold rounded-3">
            <i class="fa-solid fa-play me-2"></i>Play Game Now
          </a>
        </div>
      <?php else: ?>

        <!-- Game Parameters Summary Box -->
        <div class="specs-grid mb-4">
          <div class="spec-item">
            <span class="spec-lbl">Product</span>
            <span class="spec-val"><?php echo htmlspecialchars($game['product_name']); ?> (<?php echo htmlspecialchars($game['unit_of_measure']); ?>)</span>
          </div>
          <div class="spec-item">
            <span class="spec-lbl">Starting Cash</span>
            <span class="spec-val"><?php echo htmlspecialchars($currency); ?> <?php echo number_format((float)$game['starting_cash'], 0); ?></span>
          </div>
          <div class="spec-item">
            <span class="spec-lbl">Starting Capacity</span>
            <span class="spec-val"><?php echo number_format((int)$game['starting_capacity']); ?> Units</span>
          </div>
          <div class="spec-item">
            <span class="spec-lbl">Duration</span>
            <span class="spec-val"><?php echo (int)$game['no_of_years']; ?> Simulation Years</span>
          </div>
        </div>

        <!-- Registration Form -->
        <form method="POST" id="gameRegForm">
          <input type="hidden" name="game_id" value="<?php echo $gameId; ?>">

          <!-- Team Name -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-light mb-1">Team Name <span class="text-warning">*</span></label>
            <input type="text" name="team_name" id="team_name" class="form-control form-input-custom" placeholder="e.g. Apex Dynamics" value="<?php echo htmlspecialchars($_POST['team_name'] ?? ''); ?>" required autofocus>
            <div id="teamNameStatus" class="small mt-1" style="display:none;"></div>
          </div>

          <!-- Username -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-light mb-1">Team Username <span class="text-warning">*</span></label>
            <input type="text" name="username" id="username" class="form-control form-input-custom" value="<?php echo htmlspecialchars($_POST['username'] ?? $teamUsername); ?>" required>
            <div id="usernameStatus" class="small mt-1" style="display:none;"></div>
          </div>

          <!-- Password -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-light mb-1">Password <span class="text-warning">*</span></label>
            <input type="password" name="password" id="password" class="form-control form-input-custom" placeholder="Enter password for this session" required>
          </div>

          <!-- Confirm Password -->
          <div class="mb-4">
            <label class="form-label small fw-bold text-light mb-1">Confirm Password <span class="text-warning">*</span></label>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control form-input-custom" placeholder="Confirm password" required>
            <div id="passMatchStatus" class="small mt-1" style="display:none;"></div>
          </div>

          <button type="submit" class="btn btn-submit-reg w-100 d-flex align-items-center justify-content-center gap-2">
            <i class="fa-solid fa-user-plus"></i> Register Team for Game
          </button>
        </form>

      <?php endif; ?>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    // Point 6 locked modal popup trigger
    <?php if ($isFirstRoundReleased): ?>
      document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
          icon: 'error',
          title: 'Participation Not Allowed',
          html: `
            <div class="text-start mt-2">
              <p style="color:#475569; font-size:15px; line-height:1.6; margin-bottom:12px;">
                Participation is no longer allowed because this game session has already started or the first-round result has been released.
              </p>
              <div class="p-3 bg-light rounded-3 border text-secondary small">
                <i class="fa-solid fa-circle-info text-primary me-1"></i>
                Newly registered teams cannot enter or submit decisions for games where Round 1 results have already been processed.
              </div>
            </div>
          `,
          confirmButtonText: 'Back to Hub',
          confirmButtonColor: '#1e2761',
          allowOutsideClick: false
        }).then(function() {
          window.location.href = 'team_registration.php';
        });
      });
    <?php endif; ?>

    // Success popup
    <?php if ($success): ?>
      Swal.fire({
        title: 'Registration Successful!',
        text: 'Team "<?php echo htmlspecialchars($registeredTeamName, ENT_QUOTES); ?>" has been registered for <?php echo htmlspecialchars($game['game_name'], ENT_QUOTES); ?>.',
        icon: 'success',
        confirmButtonText: 'Return to Hub',
        confirmButtonColor: '#1e2761',
        timer: 2500,
        timerProgressBar: true
      }).then(function() {
        window.location.href = 'team_registration.php';
      });
    <?php endif; ?>

    // Real-time password match and uniqueness validation
    const passInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const passMatchStatus = document.getElementById('passMatchStatus');

    if (passInput && confirmInput) {
      function checkPass() {
        if (!confirmInput.value) { passMatchStatus.style.display = 'none'; return; }
        passMatchStatus.style.display = 'block';
        if (passInput.value === confirmInput.value) {
          passMatchStatus.className = 'small mt-1 text-success fw-bold';
          passMatchStatus.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i> Passwords match';
        } else {
          passMatchStatus.className = 'small mt-1 text-danger fw-bold';
          passMatchStatus.innerHTML = '<i class="fa-solid fa-circle-xmark me-1"></i> Passwords do not match';
        }
      }
      passInput.addEventListener('input', checkPass);
      confirmInput.addEventListener('input', checkPass);
    }
  </script>
</body>
</html>
