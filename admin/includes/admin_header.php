<?php
/**
 * MCQG Admin - Header
 * Path: admin/includes/admin_header.php
 * Included at the top of every admin/*.php page.
 * Expects $pageTitle to be set by the calling page.
 */
if (!isset($pageTitle)) { $pageTitle = 'Admin'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle); ?> | MCQG Admin</title>

  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/common.css">
  <link rel="stylesheet" href="<?php echo ADMIN_URL; ?>assets/css/admin-style.css">
  <link rel="stylesheet" href="<?php echo ADMIN_URL; ?>assets/css/admin-dashboard.css">
  <link rel="stylesheet" href="<?php echo ADMIN_URL; ?>assets/css/admin-wizard.css">
  <link rel="stylesheet" href="<?php echo ADMIN_URL; ?>assets/css/admin-reports.css">
</head>
<body>
<div class="mcqg-app-shell">
