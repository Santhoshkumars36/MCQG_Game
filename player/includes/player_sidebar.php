<?php
/**
 * MCQG Player - Sidebar Navigation
 * Path: player/includes/player_sidebar.php
 */
$currentPage = basename($_SERVER['PHP_SELF']);
function playerNavActive($page, $current) { return $page === $current ? 'active' : ''; }
?>
<aside class="mcqg-sidebar" style="width:250px; background:var(--mcqg-navy); min-height:100vh; position:fixed; left:0; top:0; padding:20px 0;">
  <div style="padding:0 22px 22px; border-bottom:1px solid rgba(255,255,255,0.1);">
    <span style="color:#fff; font-weight:800; font-size:18px;">MCQG</span>
    <div style="color:var(--mcqg-ice); font-size:11.5px;">Team: <?php echo htmlspecialchars(Session::get(SESSION_TEAM_NAME, 'Team')); ?></div>
  </div>

  <nav style="padding:18px 12px;">
    <a href="<?php echo PLAYER_URL; ?>landing.php" class="mcqg-nav-link <?php echo playerNavActive('landing.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;"><i class="fa-solid fa-house" style="width:16px;margin-right:6px;"></i>Home</a>
    <a href="<?php echo PLAYER_URL; ?>screens/case_study.php" class="mcqg-nav-link <?php echo playerNavActive('case_study.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">1. Case Study</a>
    <a href="<?php echo PLAYER_URL; ?>screens/opening_statistics.php" class="mcqg-nav-link <?php echo playerNavActive('opening_statistics.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">2. Opening Statistics</a>
    <a href="<?php echo PLAYER_URL; ?>screens/capacity_management.php" class="mcqg-nav-link <?php echo playerNavActive('capacity_management.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">3. Capacity Management</a>
    <a href="<?php echo PLAYER_URL; ?>screens/demand_management.php" class="mcqg-nav-link <?php echo playerNavActive('demand_management.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">4. Demand Management</a>
    <a href="<?php echo PLAYER_URL; ?>results/round_results.php" class="mcqg-nav-link <?php echo playerNavActive('round_results.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">6. Round Results</a>
    <a href="<?php echo PLAYER_URL; ?>results/competitive_analysis.php" class="mcqg-nav-link <?php echo playerNavActive('competitive_analysis.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">7. Competitive Analysis</a>
    <hr style="border-color:rgba(255,255,255,0.1);">
    <a href="<?php echo PLAYER_URL; ?>auth/logout.php" style="display:block; padding:11px 14px; color:#ffb4b4; text-decoration:none; border-radius:8px;">Logout</a>
  </nav>
</aside>

<style>
  .mcqg-nav-link:hover { background: rgba(255,255,255,0.08); color:#fff !important; }
  .mcqg-nav-link.active { background: var(--mcqg-gold); color: var(--mcqg-navy-dark) !important; font-weight:700; }
</style>
