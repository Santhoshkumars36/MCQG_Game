<?php
/**
 * MCQG Admin - Team Management: Assign / Reset Login
 * Path: admin/team_management/assign_login.php
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$teamId = (int) ($_GET['team_id'] ?? $_POST['team_id'] ?? 0);
$team = $db->fetchOne("SELECT * FROM team_master WHERE team_id = :t", ['t' => $teamId]);

if (!$team) { header('Location: team_list.php'); exit; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    if (!Validator::required($username) || !Validator::required($newPassword)) {
        $error = 'Username and new password are both required.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password should be at least 6 characters.';
    } else {
        $taken = $db->fetchOne("SELECT team_id FROM team_master WHERE username = :u AND team_id != :t", ['u' => $username, 't' => $teamId]);
        if ($taken) {
            $error = 'That username is already used by another team.';
        } else {
            $db->update('team_master', [
                'username' => $username,
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ], 'team_id = :t', ['t' => $teamId]);

            Logger::activity("Login credentials reset for team ID {$teamId}.");
            $success = 'Login credentials updated successfully.';
            $team['username'] = $username;
        }
    }
}

$pageTitle = 'Reset Team Login';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card" style="max-width:520px;">
  <div class="mcqg-card-header"><h2>Reset Login - <?php echo htmlspecialchars($team['team_name']); ?></h2></div>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

  <form method="POST">
    <input type="hidden" name="team_id" value="<?php echo (int) $teamId; ?>">
    <div class="mb-3">
      <label class="form-label fw-bold">Username</label>
      <input type="text" name="username" class="form-control mcqg-input-live" required
             value="<?php echo htmlspecialchars($team['username']); ?>">
    </div>
    <div class="mb-3">
      <label class="form-label fw-bold">New Password</label>
      <input type="password" name="new_password" class="form-control mcqg-input-live" required minlength="6">
      <small class="text-muted">Minimum 6 characters.</small>
    </div>
    <button type="submit" class="btn btn-mcqg-gold">Update Login</button>
    <a href="team_list.php?game_id=<?php echo (int) $team['game_id']; ?>" class="btn btn-mcqg-outline">Back</a>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
