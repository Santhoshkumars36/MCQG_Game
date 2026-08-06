<?php
/**
 * MCQG Admin - Game Setup Step 1: Title & Case Study
 * Path: admin/game_setup/step1_title_case_study.php
 * Source: MG19 Slide 3
 */
require_once __DIR__ . '/../../config/app_config.php';
Auth::requireAdmin();

$db = Database::getInstance();
$error = '';

// Resume an in-progress draft or edit a specified game
if (isset($_GET['game_id'])) {
    $gameId = (int) $_GET['game_id'];
    Session::set('setup_game_id', $gameId);
} else {
    $gameId = Session::get('setup_game_id');
}
$game = $gameId ? $db->fetchOne("SELECT * FROM game_master WHERE game_id = :g", ['g' => $gameId]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gameName = trim($_POST['game_name'] ?? '');
    $caseStudy = trim($_POST['case_study'] ?? ''); // rich text HTML from contenteditable

    if (!Validator::required($gameName)) {
        $error = 'Please enter a game name.';
    } elseif (!Validator::required($caseStudy)) {
        $error = 'Please write the case study before continuing.';
    } else {
        if ($game) {
            $db->update('game_master', ['game_name' => $gameName, 'description' => $caseStudy], 'game_id = :g', ['g' => $gameId]);
        } else {
            $newId = $db->insert('game_master', [
                'game_name'   => $gameName,
                'description' => $caseStudy,
                'product_name' => 'Untitled Product',
                'status'      => GAME_STATUS_DRAFT,
                'created_by'  => Session::currentAdminId(),
            ]);
            Session::set('setup_game_id', $newId);
        }
        Session::setFlash('success', 'Step 1 saved.');
        header('Location: step2_game_definition.php');
        exit;
    }
}

$pageTitle = 'Game Setup - Step 1';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="mcqg-main">
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

<div class="mcqg-card">
  <div class="mcqg-stepper">
    <div class="mcqg-stepper-fill" style="width:0%"></div>
    <div class="mcqg-step active"><div class="mcqg-step-circle">1</div><div class="mcqg-step-label">Title &amp; Case Study</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">2</div><div class="mcqg-step-label">Game Definition</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">3</div><div class="mcqg-step-label">Capacity Drivers</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">4</div><div class="mcqg-step-label">Demand Drivers</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">5</div><div class="mcqg-step-label">Investments</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">6</div><div class="mcqg-step-label">Configuration</div></div>
    <div class="mcqg-step"><div class="mcqg-step-circle">7</div><div class="mcqg-step-label">Publish</div></div>
  </div>

  <div class="mcqg-wizard-panel">
    <h3>Step 1 of 7 &mdash; Title &amp; Case Study</h3>
    <p class="text-muted">Name the game and write the business scenario players will read before deciding.</p>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="POST" id="mcqg-wizard-form">
      <div class="mb-3">
        <label class="form-label fw-bold">Game Name</label>
        <input type="text" name="game_name" class="form-control mcqg-input-live" required
               value="<?php echo htmlspecialchars($game['game_name'] ?? ''); ?>">
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold">Case Study (rich text)</label>
        <div class="btn-group mb-2" role="group">
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.execCommand('bold')"><b>B</b></button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.execCommand('italic')"><i>I</i></button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.execCommand('insertUnorderedList')">&bull; List</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.execCommand('insertOrderedList')">1. List</button>
        </div>
        <div id="case-study-editor" contenteditable="true" class="form-control mcqg-input-live"
             style="min-height:220px; overflow-y:auto;"><?php echo $game['description'] ?? ''; ?></div>
        <textarea name="case_study" id="case-study-hidden" style="display:none;" required></textarea>
      </div>

      <div class="mcqg-wizard-nav">
        <span></span>
        <button type="submit" id="mcqg-next-btn" class="btn btn-mcqg-primary">NEXT</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Sync the rich-text contenteditable div into the hidden textarea
  const editor = document.getElementById('case-study-editor');
  const hidden = document.getElementById('case-study-hidden');
  if (editor && hidden) {
    const syncCaseStudy = function() {
      const text = editor.innerText ? editor.innerText.trim() : '';
      hidden.value = text ? editor.innerHTML : '';
    };
    editor.addEventListener('input', syncCaseStudy);
    editor.addEventListener('blur', syncCaseStudy);
    editor.addEventListener('keyup', syncCaseStudy);
    syncCaseStudy();
  }
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
