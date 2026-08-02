<?php
/**
 * MCQG Admin - Forgot Password
 * Path: admin/auth/forgot_password.php
 */
require_once __DIR__ . '/../../config/app_config.php';

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!Validator::required($email)) {
        $message = 'Please enter your registered email address.';
        $messageType = 'danger';
    } else {
        $db = Database::getInstance();
        $admin = $db->fetchOne("SELECT * FROM admin_user WHERE email = :e", ['e' => $email]);

        // Always show the same message whether or not the email exists,
        // to avoid leaking which emails are registered admins.
        $message = 'If that email is registered, a password reset link has been sent.';
        $messageType = 'success';

        if ($admin) {
            Logger::activity("Password reset requested for admin email '{$email}'.");
            // Actual email-sending would be wired here once mail settings are provided.
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password | MCQG Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo ADMIN_URL; ?>assets/css/admin-style.css">
  <style>
    body { background: linear-gradient(135deg, var(--mcqg-navy) 0%, var(--mcqg-navy-dark) 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; }
    .card-box { max-width:400px; width:100%; background:#fff; border-radius:14px; padding:38px; box-shadow:0 20px 60px rgba(0,0,0,0.35); }
  </style>
</head>
<body>
  <div class="card-box">
    <h4 style="color:var(--mcqg-navy); font-weight:800;">Reset Password</h4>
    <p class="text-muted small">Enter your registered admin email and we'll send you a reset link.</p>

    <?php if ($message): ?>
      <div class="alert alert-<?php echo $messageType; ?> py-2"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-mcqg-primary w-100">Send Reset Link</button>
      <div class="text-center mt-3">
        <a href="login.php" class="small text-muted">Back to login</a>
      </div>
    </form>
  </div>
</body>
</html>
