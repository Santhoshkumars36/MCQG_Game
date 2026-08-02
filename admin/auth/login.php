<?php
/**
 * MCQG Admin - Login
 * Path: admin/auth/login.php
 */
require_once __DIR__ . '/../../config/app_config.php';

if (Auth::isAdmin()) {
    header('Location: ' . ADMIN_URL . 'dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!Validator::required($username) || !Validator::required($password)) {
        $error = 'Please enter both username and password.';
    } elseif (Auth::attemptAdminLogin($username, $password)) {
        header('Location: ' . ADMIN_URL . 'dashboard.php');
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
  <title>Admin Login | MCQG</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo ADMIN_URL; ?>assets/css/admin-style.css">
  <style>
    body { background: linear-gradient(135deg, var(--mcqg-navy) 0%, var(--mcqg-navy-dark) 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; }
    .login-card { max-width:400px; width:100%; background:#fff; border-radius:14px; padding:38px; box-shadow:0 20px 60px rgba(0,0,0,0.35); animation: mcqg-pop 0.4s ease; }
    @keyframes mcqg-pop { from{opacity:0; transform:scale(0.95);} to{opacity:1; transform:scale(1);} }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="text-center mb-4">
      <h3 style="color:var(--mcqg-navy); font-weight:800;">MCQG</h3>
      <p class="text-muted small mb-0">Market Competition Quantitative Game</p>
      <span class="badge" style="background:var(--mcqg-gold); color:var(--mcqg-navy-dark);">Admin Console</span>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" id="adminLoginForm">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control mcqg-input-live" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control mcqg-input-live" required>
      </div>
      <button type="submit" class="btn btn-mcqg-primary w-100">Sign In</button>
      <div class="text-center mt-3">
        <a href="forgot_password.php" class="small text-muted">Forgot password?</a>
      </div>
    </form>
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
