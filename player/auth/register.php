<?php
/**
 * MCQG Player - Team Registration
 * Path: player/auth/register.php
 */
require_once __DIR__ . '/../../config/app_config.php';

if (Auth::isTeam()) {
    header('Location: ' . PLAYER_URL . '/dashboard.php');
    exit;
}

$error = '';
$success = false;
$registeredTeamName = '';

// Fetch all published games available for registration
$publishedGames = Game::getAllPublished();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gameId = (int)($_POST['game_id'] ?? 0);
    $teamName = trim($_POST['team_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Form Validation
    if (!$gameId) {
        $error = 'Please select a game session to register for.';
    } elseif (!Validator::required($teamName)) {
        $error = 'Please enter a Team Name.';
    } elseif (!Validator::required($username)) {
        $error = 'Please enter a Login Username.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long.';
    } elseif (!Validator::required($password)) {
        $error = 'Please enter a password.';
    } elseif (strlen($password) < 4) {
        $error = 'Password must be at least 4 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password and Confirm Password do not match.';
    } else {
        $db = Database::getInstance();
        
        // Verify game exists and is published
        $selectedGame = $db->fetchOne(
            "SELECT * FROM game_master WHERE game_id = :g AND status = :s AND (isDeleted IS NULL OR isDeleted = 0)",
            ['g' => $gameId, 's' => GAME_STATUS_PUBLISHED]
        );

        $existingTeamName = $db->fetchOne(
            "SELECT team_id FROM team_master WHERE LOWER(TRIM(team_name)) = LOWER(:tn)",
            ['tn' => $teamName]
        );

        $existingUsername = $db->fetchOne(
            "SELECT team_id FROM team_master WHERE LOWER(TRIM(username)) = LOWER(:u)",
            ['u' => $username]
        );

        if (!$selectedGame) {
            $error = 'Selected game is not active or available for registration.';
        } elseif ($existingTeamName) {
            $error = 'Team Name "' . htmlspecialchars($teamName) . '" is already registered. Please choose another Team Name.';
        } elseif ($existingUsername) {
            $error = 'Username "' . htmlspecialchars($username) . '" is already taken. Please choose another Username.';
        } else {
            // Register new team in database
            $openingInventory = (int)($selectedGame['starting_inventory'] ?? 0);
            $openingBudget = (float)($selectedGame['starting_cash'] ?? 0);

            $db->insert('team_master', [
                'game_id'           => $gameId,
                'team_name'         => $teamName,
                'username'          => $username,
                'password_hash'     => password_hash($password, PASSWORD_DEFAULT),
                'opening_inventory' => $openingInventory,
                'opening_budget'    => $openingBudget,
                'is_active'         => 1,
            ]);

            Logger::activity("New team '{$teamName}' registered for Game #{$gameId}.");
            $success = true;
            $registeredTeamName = $teamName;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Team Registration | MCQG</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="<?php echo PLAYER_URL; ?>assets/css/player-style.css">
  <style>
    body {
      background: radial-gradient(circle at top right, #1e2761, #0d1233 80%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 30px 15px;
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    }

    .register-card {
      max-width: 520px;
      width: 100%;
      background: rgba(255, 255, 255, 0.96);
      backdrop-filter: blur(12px);
      border-radius: 20px;
      padding: 40px;
      box-shadow: 0 25px 70px rgba(0, 0, 0, 0.45);
      border: 1px solid rgba(255, 255, 255, 0.2);
      animation: mcqg-slideUp 0.45s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
      overflow: hidden;
    }

    .register-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 6px;
      background: linear-gradient(90deg, #c99a2e, #e7c766, #c99a2e);
    }

    @keyframes mcqg-slideUp {
      from { opacity: 0; transform: translateY(25px) scale(0.97); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .brand-logo {
      width: 56px;
      height: 56px;
      background: linear-gradient(135deg, #1e2761, #141a46);
      color: #c99a2e;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      margin: 0 auto 12px;
      box-shadow: 0 8px 20px rgba(30, 39, 97, 0.25);
    }

    .form-floating-custom {
      position: relative;
      margin-bottom: 5px;
    }

    .form-floating-custom .form-control,
    .form-floating-custom .form-select {
      height: 50px;
      border-radius: 12px;
      border: 1.5px solid #cbd5e1;
      padding-left: 44px;
      padding-right: 44px;
      font-size: 14.5px;
      font-weight: 500;
      transition: all 0.25s ease;
      background-color: #f8fafc;
    }

    .form-floating-custom .form-control:focus,
    .form-floating-custom .form-select:focus {
      background-color: #ffffff;
      border-color: #c99a2e;
      box-shadow: 0 0 0 4px rgba(201, 154, 46, 0.18);
    }

    .form-floating-custom .input-icon {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #64748b;
      font-size: 16px;
      z-index: 5;
      transition: color 0.25s ease;
    }

    .form-floating-custom .form-control:focus ~ .input-icon,
    .form-floating-custom .form-select:focus ~ .input-icon {
      color: #c99a2e;
    }

    .toggle-password {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      cursor: pointer;
      z-index: 5;
      padding: 5px;
      transition: color 0.2s ease;
    }

    .toggle-password:hover {
      color: #1e2761;
    }

    .match-status {
      font-size: 12.5px;
      font-weight: 600;
      margin-top: 6px;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: all 0.25s ease;
    }

    .match-status.valid { color: #16a34a; }
    .match-status.invalid { color: #dc2626; }

    .btn-register {
      background: linear-gradient(135deg, #1e2761 0%, #141a46 100%);
      color: #ffffff;
      border: none;
      height: 52px;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 700;
      letter-spacing: 0.5px;
      box-shadow: 0 10px 25px rgba(30, 39, 97, 0.3);
      transition: all 0.3s ease;
    }

    .btn-register:hover {
      transform: translateY(-2px);
      box-shadow: 0 14px 30px rgba(30, 39, 97, 0.4);
      background: linear-gradient(135deg, #141a46 0%, #0d1233 100%);
      color: #e7c766;
    }

    .btn-register:active {
      transform: translateY(0);
    }
  </style>
</head>
<body>

  <div class="register-card">
    <div class="text-center mb-4">
      <div class="brand-logo">
        <i class="fa-solid fa-users-gear"></i>
      </div>
      <h3 style="color:var(--mcqg-navy); font-weight:800; margin-bottom: 2px;">Join Game Session</h3>
      <p class="text-muted small mb-2">Create your team account for MCQG Simulation</p>
      <span class="badge px-3 py-2" style="background:#eef2ff; color:#1e2761; border: 1px solid #c7d2fe; border-radius: 20px; font-weight:600;">
        <i class="fa-solid fa-user-plus me-1" style="color:var(--mcqg-gold);"></i> Team Registration
      </span>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-4" style="border-radius: 10px; font-size: 14px;">
        <i class="fa-solid fa-circle-exclamation me-2 fs-5"></i>
        <div><?php echo htmlspecialchars($error); ?></div>
      </div>
    <?php endif; ?>

    <?php if (empty($publishedGames)): ?>
      <div class="alert alert-warning text-center p-4" style="border-radius: 14px;">
        <i class="fa-solid fa-triangle-exclamation fa-2x mb-3 text-warning"></i>
        <h5 class="fw-bold mb-2">No Active Games Available</h5>
        <p class="small text-muted mb-3">Registration is currently closed because no active game sessions have been published by the administrator.</p>
        <a href="login.php" class="btn btn-mcqg-primary w-100"><i class="fa-solid fa-arrow-left me-2"></i>Back to Team Login</a>
      </div>
    <?php else: ?>

      <form method="POST" id="registerForm">
        <!-- Game Selection -->
        <div class="mb-3">
          <label class="form-label small fw-bold text-secondary mb-1">Select Game Session <span class="text-danger">*</span></label>
          <div class="form-floating-custom">
            <select name="game_id" class="form-select" required>
              <?php foreach ($publishedGames as $g): ?>
                <option value="<?php echo (int)$g['game_id']; ?>">
                  <?php echo htmlspecialchars($g['game_name']); ?> (Product: <?php echo htmlspecialchars($g['product_name']); ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <i class="fa-solid fa-trophy input-icon"></i>
          </div>
        </div>

        <!-- Team Name -->
        <div class="mb-3">
          <label class="form-label small fw-bold text-secondary mb-1">Team Name <span class="text-danger">*</span></label>
          <div class="form-floating-custom">
            <input type="text" name="team_name" id="team_name" class="form-control" placeholder="e.g. Apex Corp" value="<?php echo htmlspecialchars($_POST['team_name'] ?? ''); ?>" required autofocus>
            <i class="fa-solid fa-people-group input-icon"></i>
          </div>
          <div id="teamNameStatus" class="match-status" style="display:none;"></div>
        </div>

        <!-- Username -->
        <div class="mb-3">
          <label class="form-label small fw-bold text-secondary mb-1">Team Username <span class="text-danger">*</span></label>
          <div class="form-floating-custom">
            <input type="text" name="username" id="username" class="form-control" placeholder="e.g. team_apex" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            <i class="fa-solid fa-id-badge input-icon"></i>
          </div>
          <div id="usernameStatus" class="match-status" style="display:none;"></div>
        </div>

        <!-- Password -->
        <div class="mb-3">
          <label class="form-label small fw-bold text-secondary mb-1">Password <span class="text-danger">*</span></label>
          <div class="form-floating-custom">
            <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
            <i class="fa-solid fa-lock input-icon"></i>
            <i class="fa-solid fa-eye toggle-password" onclick="togglePass('password', this)"></i>
          </div>
        </div>

        <!-- Confirm Password -->
        <div class="mb-4">
          <label class="form-label small fw-bold text-secondary mb-1">Confirm Password <span class="text-danger">*</span></label>
          <div class="form-floating-custom">
            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Re-enter password" required>
            <i class="fa-solid fa-shield-halved input-icon"></i>
            <i class="fa-solid fa-eye toggle-password" onclick="togglePass('confirm_password', this)"></i>
          </div>
          <div id="matchStatus" class="match-status" style="display:none;"></div>
        </div>

        <button type="submit" id="submitBtn" class="btn btn-register w-100 d-flex align-items-center justify-content-center gap-2">
          <i class="fa-solid fa-user-plus"></i> Register Team
        </button>

        <div class="text-center mt-4 pt-3 border-top" style="border-color: #e2e8f0 !important;">
          <span class="small text-muted">Already registered your team?</span>
          <a href="login.php" class="small fw-bold text-decoration-none ms-1" style="color:var(--mcqg-navy);">
            <i class="fa-solid fa-right-to-bracket me-1"></i>Back to Team Login
          </a>
        </div>
      </form>

    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    function togglePass(inputId, icon) {
      const input = document.getElementById(inputId);
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }

    const teamNameInput = document.getElementById('team_name');
    const usernameInput = document.getElementById('username');
    const passInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const matchStatus = document.getElementById('matchStatus');
    const teamNameStatus = document.getElementById('teamNameStatus');
    const usernameStatus = document.getElementById('usernameStatus');

    function checkPasswordMatch() {
      if (!confirmInput.value) {
        matchStatus.style.display = 'none';
        return;
      }
      matchStatus.style.display = 'flex';
      if (passInput.value === confirmInput.value) {
        matchStatus.className = 'match-status valid';
        matchStatus.innerHTML = '<i class="fa-solid fa-circle-check"></i> Passwords match';
      } else {
        matchStatus.className = 'match-status invalid';
        matchStatus.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Passwords do not match';
      }
    }

    let availabilityTimer;
    function checkUniqueness() {
      clearTimeout(availabilityTimer);
      availabilityTimer = setTimeout(async () => {
        const teamName = teamNameInput ? teamNameInput.value.trim() : '';
        const username = usernameInput ? usernameInput.value.trim() : '';

        if (!teamName && !username) return;

        try {
          const res = await fetch('<?php echo AJAX_URL; ?>player_ajax/check_team_exists.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ team_name: teamName, username: username })
          });
          const json = await res.json();
          if (json.success) {
            if (teamName && teamNameStatus) {
              teamNameStatus.style.display = 'flex';
              if (json.data.team_name_exists) {
                teamNameStatus.className = 'match-status invalid';
                teamNameStatus.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Team Name is already registered!';
              } else {
                teamNameStatus.className = 'match-status valid';
                teamNameStatus.innerHTML = '<i class="fa-solid fa-circle-check"></i> Team Name is available';
              }
            }
            if (username && usernameStatus) {
              usernameStatus.style.display = 'flex';
              if (json.data.username_exists) {
                usernameStatus.className = 'match-status invalid';
                usernameStatus.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Username is already taken!';
              } else {
                usernameStatus.className = 'match-status valid';
                usernameStatus.innerHTML = '<i class="fa-solid fa-circle-check"></i> Username is available';
              }
            }
          }
        } catch (e) {}
      }, 350);
    }

    if (passInput && confirmInput) {
      passInput.addEventListener('input', checkPasswordMatch);
      confirmInput.addEventListener('input', checkPasswordMatch);
    }

    if (teamNameInput) teamNameInput.addEventListener('input', checkUniqueness);
    if (usernameInput) usernameInput.addEventListener('input', checkUniqueness);
  </script>

  <?php if ($success): ?>
    <script>
      Swal.fire({
        title: 'Registration Successful!',
        text: 'Team "<?php echo htmlspecialchars($registeredTeamName, ENT_QUOTES); ?>" has been added successfully. Redirecting to Team Login page...',
        icon: 'success',
        confirmButtonText: 'Proceed to Login',
        confirmButtonColor: '#1e2761',
        timer: 3500,
        timerProgressBar: true,
        allowOutsideClick: false,
        background: '#ffffff',
        customClass: {
          popup: 'shadow-lg border-0',
          title: 'fw-bold text-dark'
        }
      }).then(function() {
        window.location.href = 'login.php';
      });
    </script>
  <?php endif; ?>
</body>
</html>
