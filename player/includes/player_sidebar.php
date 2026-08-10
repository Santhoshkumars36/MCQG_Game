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
    <a href="<?php echo PLAYER_URL; ?>dashboard.php" class="mcqg-nav-link <?php echo playerNavActive('dashboard.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">Dashboard</a>
    <a href="<?php echo PLAYER_URL; ?>screens/case_study.php" class="mcqg-nav-link <?php echo playerNavActive('case_study.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">1. Case Study</a>
    <a href="<?php echo PLAYER_URL; ?>screens/build_production.php" class="mcqg-nav-link <?php echo playerNavActive('build_production.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">2. Build Production</a>
    <a href="<?php echo PLAYER_URL; ?>screens/demand_economics.php" class="mcqg-nav-link <?php echo playerNavActive('demand_economics.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">3. Demand Economics</a>
    <a href="<?php echo PLAYER_URL; ?>screens/review_decision.php" class="mcqg-nav-link <?php echo playerNavActive('review_decision.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">4. Review &amp; Submit</a>
    <a href="<?php echo PLAYER_URL; ?>results/round_results.php" class="mcqg-nav-link <?php echo playerNavActive('round_results.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">Round Results</a>
    <a href="<?php echo PLAYER_URL; ?>results/cumulative_standing.php" class="mcqg-nav-link <?php echo playerNavActive('cumulative_standing.php', $currentPage); ?>" style="display:block; padding:11px 14px; color:#cadcfc; text-decoration:none; border-radius:8px; margin-bottom:4px;">Standings</a>
    <hr style="border-color:rgba(255,255,255,0.1);">
    <a href="<?php echo PLAYER_URL; ?>auth/logout.php" style="display:block; padding:11px 14px; color:#ffb4b4; text-decoration:none; border-radius:8px;">Logout</a>
  </nav>
</aside>

<style>
  .mcqg-nav-link:hover { background: rgba(255,255,255,0.08); color:#fff !important; }
  .mcqg-nav-link.active { background: var(--mcqg-gold); color: var(--mcqg-navy-dark) !important; font-weight:700; }
</style>
