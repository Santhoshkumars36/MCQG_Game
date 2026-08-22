<?php
/**
 * MCQG - Root Entry Point
 * Path: index.php
 * The first page anyone hits when visiting the site root.
 * If already logged in (either role), sends them straight to their
 * dashboard. Otherwise shows a simple role-choice landing page.
 */
require_once __DIR__ . '/config/app_config.php';

if (Auth::isAdmin()) {
    header('Location: ' . ADMIN_URL . 'dashboard.php');
    exit;
}
if (Auth::isTeam()) {
    header('Location: ' . PLAYER_URL . 'team_registration.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MCQG - Market Competition Quantitative Game</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/common.css">
  <style>
    :root { --mcqg-navy:#1e2761; --mcqg-navy-dark:#141a46; --mcqg-gold:#c99a2e; --mcqg-gold-light:#e7c766; --mcqg-ice:#cadcfc; }
    body {
      margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
      background: linear-gradient(135deg, var(--mcqg-navy) 0%, var(--mcqg-navy-dark) 100%);
      font-family: "Segoe UI", Roboto, Arial, sans-serif;
    }
    .landing-card {
      max-width:520px; width:100%; background:#fff; border-radius:16px; padding:44px;
      box-shadow:0 24px 70px rgba(0,0,0,0.4); text-align:center; animation: mcqgCommonFadeIn 0.4s ease;
    }
    .role-btn {
      display:block; width:100%; padding:16px; border-radius:10px; font-weight:700; font-size:16px;
      text-decoration:none; margin-bottom:14px; transition:transform 0.2s ease;
    }
    .role-btn:hover { transform: translateY(-2px); }
    .role-admin { background: var(--mcqg-navy); color:#fff; }
    .role-player { background: var(--mcqg-gold); color: var(--mcqg-navy-dark); }
  </style>
</head>
<body>
  <div class="landing-card">
    <h1 style="color:var(--mcqg-navy); font-weight:800; font-size:28px; margin-bottom:4px;">MCQG</h1>
    <p style="color:#5b5f6b; margin-bottom:30px;">Market Competition Quantitative Game</p>

    <a href="<?php echo ADMIN_URL; ?>auth/login.php" class="role-btn role-admin">Admin Login</a>
    <a href="<?php echo PLAYER_URL; ?>auth/login.php" class="role-btn role-player">Team Login</a>
  </div>
</body>
</html>
