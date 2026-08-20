<?php
/**
 * MCQG Player - Header
 * Path: player/includes/player_header.php
 * Included at the top of every player/*.php page.
 */
if (!isset($pageTitle)) { $pageTitle = 'Player'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle); ?> | MCQG</title>

  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/common.css">
  <link rel="stylesheet" href="<?php echo PLAYER_URL; ?>assets/css/player-style.css">
  <link rel="stylesheet" href="<?php echo PLAYER_URL; ?>assets/css/player-dashboard.css">
  <link rel="stylesheet" href="<?php echo PLAYER_URL; ?>assets/css/player-production.css">
  <link rel="stylesheet" href="<?php echo PLAYER_URL; ?>assets/css/player-results.css">
  <!-- SweetAlert2 & FontAwesome -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">

  <script>
    // Used by every player_ajax/* JS call
    const AJAX_PLAYER_URL = "<?php echo AJAX_URL; ?>player_ajax/";
  </script>
</head>
<body>
<div class="mcqg-app-shell">
