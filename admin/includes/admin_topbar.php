<?php
/**
 * MCQG Admin - Top Bar
 * Path: admin/includes/admin_topbar.php
 */
$flashSuccess = Session::getFlash('success');
$flashError = Session::getFlash('error');
?>
<div class="mcqg-topbar" style="display:flex; justify-content:space-between; align-items:center; padding:14px 0 22px;">
  <button id="mcqg-sidebar-toggle" class="btn btn-sm btn-mcqg-outline">&#9776;</button>
  <div style="display:flex; align-items:center; gap:14px;">
    <span style="color:var(--mcqg-text-muted); font-size:13.5px;">
      Signed in as <strong><?php echo htmlspecialchars(Session::get('admin_name', 'Admin')); ?></strong>
    </span>
    <div style="width:36px;height:36px;border-radius:50%;background:var(--mcqg-gold);display:flex;align-items:center;justify-content:center;font-weight:800;color:var(--mcqg-navy-dark);">
      <?php echo strtoupper(substr(Session::get('admin_name', 'A'), 0, 1)); ?>
    </div>
  </div>
</div>

<?php if ($flashSuccess): ?>
  <div data-mcqg-flash="<?php echo htmlspecialchars($flashSuccess); ?>" data-mcqg-flash-type="success" style="display:none;"></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div data-mcqg-flash="<?php echo htmlspecialchars($flashError); ?>" data-mcqg-flash-type="error" style="display:none;"></div>
<?php endif; ?>
