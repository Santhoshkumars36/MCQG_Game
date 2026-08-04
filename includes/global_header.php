<?php
/**
 * MCQG - Global Header (shared <head> + opening <body>)
 * Path: includes/global_header.php
 * Purpose: The shared markup used by BOTH admin/includes/admin_header.php
 * and player/includes/player_header.php - only truly shared assets
 * (Bootstrap, common.css) load here. Admin-only and player-only CSS
 * are added separately by their own header files, keeping the two
 * modules' styling fully independent as required.
 *
 * Expected variable set by the including page BEFORE this loads:
 *   $pageTitle (string) e.g. "Game Definition"
 */
if (!isset($pageTitle)) { $pageTitle = 'MCQG'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle); ?> | <?php echo htmlspecialchars(APP_NAME); ?></title>

  <!-- Shared, module-agnostic assets only -->
  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/common.css">
</head>
<body>
<div class="mcqg-app-shell">
