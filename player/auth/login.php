<?php
/**
 * MCQG Player - Login
 * Path: player/auth/login.php
 */
require_once __DIR__ . '/../../config/app_config.php';

if (Auth::isTeam()) {
    header('Location: ' . PLAYER_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!Validator::required($username) || !Validator::required($password)) {
        $error = 'Please enter both username and password.';
    } elseif (Auth::attemptTeamLogin($username, $password)) {
        header('Location: ' . PLAYER_URL . '/dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Team Login | MCQG</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo PLAYER_URL; ?>assets/css/player-style.css">
  <style>
    body { background: linear-gradient(135deg, var(--mcqg-navy) 0%, var(--mcqg-navy-dark) 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; }
    .login-card { max-width:420px; width:100%; background:#fff; border-radius:16px; padding:38px; box-shadow:0 20px 60px rgba(0,0,0,0.35); animation: mcqg-pop 0.4s ease; }
    @keyframes mcqg-pop { from{opacity:0; transform:scale(0.95);} to{opacity:1; transform:scale(1);} }
    .btn-register-link {
      background: #f8fafc;
      color: var(--mcqg-navy);
      border: 1.5px dashed #cbd5e1;
      border-radius: 8px;
      font-weight: 700;
      padding: 10px;
      transition: all 0.25s ease;
    }
    .btn-register-link:hover {
      background: #eef2ff;
      border-color: var(--mcqg-navy);
      color: var(--mcqg-navy);
      transform: translateY(-1px);
    }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="text-center mb-4">
      <h3 style="color:var(--mcqg-navy); font-weight:800;">MCQG</h3>
      <p class="text-muted small mb-0">Market Competition Quantitative Game</p>
      <span class="badge mt-1" style="background:var(--mcqg-gold); color:var(--mcqg-navy-dark); font-weight:700;">Team Login</span>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label fw-bold small text-secondary">Team Username</label>
        <input type="text" name="username" class="form-control mcqg-input-live" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold small text-secondary">Password</label>
        <input type="password" name="password" class="form-control mcqg-input-live" required>
      </div>
      <button type="submit" class="btn btn-mcqg-primary w-100 py-2 fw-bold" style="font-size:16px;">Enter Game</button>
    </form>

    <div class="text-center mt-4 pt-3 border-top" style="border-color:#e2e8f0 !important;">
      <p class="text-muted small mb-2">New team participating in the game?</p>
      <a href="register.php" class="btn btn-register-link w-100 d-flex align-items-center justify-content-center gap-2 text-decoration-none">
        <i class="fa-solid fa-user-plus" style="color:var(--mcqg-gold);"></i> Register New Team
      </a>
    </div>
  </div>

  <script>
    document.querySelectorAll('.mcqg-input-live').forEach(function (input) {
      input.addEventListener('input', function () {
        this.classList.toggle('is-valid-live', this.value.trim() !== '');
      });
    });
  </script>
</body>
</html>
