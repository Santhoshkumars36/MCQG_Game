<?php
/**
 * MCQG Admin - Sidebar Navigation
 * Path: admin/includes/admin_sidebar.php
 */
$currentPage = basename($_SERVER['PHP_SELF']);
function navActive($page, $current) { return $page === $current ? 'active' : ''; }
?>
<aside class="mcqg-sidebar" style="width:260px; background:var(--mcqg-navy); min-height:100vh; position:fixed; left:0; top:0; padding:20px 0; transition:var(--mcqg-transition);">
  <div style="padding:0 22px 22px; border-bottom:1px solid rgba(255,255,255,0.1);">
    <span style="color:#fff; font-weight:800; font-size:18px;">MCQG</span>
    <div style="color:var(--mcqg-ice); font-size:11.5px;">Admin Console</div>
  </div>

  <nav style="padding:18px 12px;">
    <a href="<?php echo ADMIN_URL; ?>dashboard.php" class="mcqg-nav-link <?php echo navActive('dashboard.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">Dashboard</a>
    <a href="<?php echo ADMIN_URL; ?>manage_games.php" class="mcqg-nav-link <?php echo navActive('manage_games.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">Manage Games</a>
    <a href="<?php echo ADMIN_URL; ?>game_setup/step1_title_case_study.php" class="mcqg-nav-link" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">New Game Setup</a>
    <a href="<?php echo ADMIN_URL; ?>team_management/team_list.php" class="mcqg-nav-link" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">Team Management</a>
    <a href="<?php echo ADMIN_URL; ?>round_control/round_status.php" class="mcqg-nav-link" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">Round Control</a>
    <a href="<?php echo ADMIN_URL; ?>reports/cumulative_report.php" class="mcqg-nav-link" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">Reports</a>
    <hr style="border-color:rgba(255,255,255,0.1);">
    <a href="<?php echo ADMIN_URL; ?>auth/logout.php" style="display:block; padding:11px 14px; color:#ffb4b4; text-decoration:none; border-radius:8px;">Logout</a>
  </nav>
</aside>

<style>
  .mcqg-nav-link:hover { background: rgba(255,255,255,0.08); color:#fff !important; }
  .mcqg-nav-link.active { background: var(--mcqg-gold); color: var(--mcqg-navy-dark) !important; font-weight:700; }
</style>
