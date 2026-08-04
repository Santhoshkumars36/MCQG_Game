<?php
/**
 * MCQG - Global Footer (shared closing markup + shared JS libraries)
 * Path: includes/global_footer.php
 * Purpose: The shared closing tags + library scripts used by BOTH
 * admin/includes/admin_footer.php and player/includes/player_footer.php.
 * Admin-only and player-only JS files are included separately by
 * their own footer files, after this one.
 */
?>
    </div><!-- /.mcqg-app-shell -->

  <!-- Shared, module-agnostic scripts only -->
  <script src="<?php echo ASSET_URL; ?>js/jquery.min.js"></script>
  <script src="<?php echo ASSET_URL; ?>js/bootstrap.bundle.min.js"></script>
  <script src="<?php echo ASSET_URL; ?>js/common.js"></script>

  <script>
    // Shared, app-wide JS config - every module-specific JS file can
    // read from this instead of hardcoding URLs, so AJAX calls always
    // hit the right endpoint regardless of which module is loaded.
    window.MCQG = {
      baseUrl: <?php echo json_encode(BASE_URL); ?>,
      adminAjaxUrl: <?php echo json_encode(AJAX_URL . 'admin_ajax/'); ?>,
      playerAjaxUrl: <?php echo json_encode(AJAX_URL . 'player_ajax/'); ?>
    };
  </script>
</body>
</html>
