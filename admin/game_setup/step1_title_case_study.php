<?php
/**
 * MCQG Admin - Game Setup Step 1: Title & Case Study
 * Path: admin/game_setup/step1_title_case_study.php
 * Source: MG19 Slide 3 & Slide 4 Game Identity (Name & Image)
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
    $gameName   = trim($_POST['game_name'] ?? '');
    $caseStudy  = trim($_POST['case_study'] ?? ''); // rich text HTML from contenteditable
    $resetImage = isset($_POST['reset_image']) && $_POST['reset_image'] === '1';

    $gameImagePath = $game['game_image'] ?? null;

    if ($resetImage) {
        $gameImagePath = null;
    } elseif (isset($_FILES['game_image']) && $_FILES['game_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['game_image']['tmp_name'];
        $fileName      = $_FILES['game_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        if (in_array($fileExtension, $allowedExtensions, true)) {
            $uploadDir = ROOT_PATH . '/uploads/games/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $newFileName = 'game_' . time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
            $destPath    = $uploadDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $gameImagePath = 'uploads/games/' . $newFileName;
            } else {
                $error = 'Failed to save the uploaded image file.';
            }
        } else {
            $error = 'Invalid image file type. Allowed formats: JPG, JPEG, PNG, GIF, WEBP, SVG.';
        }
    }

    if (!Validator::required($gameName)) {
        $error = 'Please enter a game name.';
    } elseif (!Validator::required($caseStudy)) {
        $error = 'Please write the case study before continuing.';
    } elseif (empty($error)) {
        if ($game) {
            $db->update('game_master', [
                'game_name'   => $gameName,
                'description' => $caseStudy,
                'game_image'  => $gameImagePath,
            ], 'game_id = :g', ['g' => $gameId]);
        } else {
            $newId = $db->insert('game_master', [
                'game_name'    => $gameName,
                'description'  => $caseStudy,
                'game_image'   => $gameImagePath,
                'product_name' => 'Untitled Product',
                'status'       => GAME_STATUS_DRAFT,
                'created_by'   => Session::currentAdminId(),
            ]);
            Session::set('setup_game_id', $newId);
        }
        Session::setFlash('success', 'Step 1 saved.');
        header('Location: step2_game_definition.php');
        exit;
    }
}

$hasCustomImage = !empty($game['game_image']) && Game::getImageUrl($game);
$previewSrc = $hasCustomImage ? Game::getImageUrl($game) : BASE_URL . '/assets/images/default_game.png';

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
    <p class="text-muted">Name the game, choose a game thumbnail, and write the business scenario players will read before deciding.</p>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="POST" id="mcqg-wizard-form" enctype="multipart/form-data">
      <div class="mb-3">
        <label class="form-label fw-bold">Game Name</label>
        <input type="text" name="game_name" class="form-control mcqg-input-live" required
               value="<?php echo htmlspecialchars($game['game_name'] ?? ''); ?>"
               placeholder="e.g., MG19 Bicycle Challenge">
      </div>

      <!-- Game Identity Image Upload / Selection -->
      <div class="mb-4 p-3 rounded" style="background-color: #f8fafc; border: 1px solid var(--mcqg-border, #e2e8f0);">
        <label class="form-label fw-bold d-block mb-2">
          <i class="fas fa-image me-1" style="color:var(--mcqg-navy, #1e293b);"></i> Game Image
        </label>
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <div class="position-relative" style="width: 100px; height: 100px;">
            <img id="game-image-preview"
                 src="<?php echo $previewSrc; ?>"
                 alt="Game Thumbnail"
                 class="rounded shadow-sm border"
                 style="width: 100px; height: 100px; object-fit: cover; background: #fff;">
          </div>
          <div class="flex-grow-1">
            <div class="mb-2">
              <span id="image-status-badge" class="badge <?php echo $hasCustomImage ? 'bg-success' : 'bg-secondary'; ?>">
                <?php echo $hasCustomImage ? 'Custom Logo Uploaded' : 'No Custom Image Uploaded'; ?>
              </span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <label class="btn btn-sm btn-outline-primary m-0 cursor-pointer">
                <i class="fas fa-upload me-1"></i> <span id="change-image-btn-label"><?php echo $hasCustomImage ? 'Change Image' : 'Select Image'; ?></span>
                <input type="file" name="game_image" id="game_image_input" accept="image/*" class="d-none" onchange="previewGameImage(this)">
              </label>
              <button type="button" id="btn-reset-image" class="btn btn-sm btn-outline-danger"
                      style="display: <?php echo $hasCustomImage ? 'inline-block' : 'none'; ?>;"
                      onclick="resetToDefaultImage()">
                <i class="fas fa-trash-alt me-1"></i> Remove Image
              </button>
              <input type="hidden" name="reset_image" id="reset_image_input" value="0">
            </div>
          </div>
        </div>
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
  const placeholderImageUrl = '<?php echo BASE_URL . '/assets/images/default_game.png'; ?>';

  function previewGameImage(input) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('game-image-preview').src = e.target.result;
        document.getElementById('reset_image_input').value = '0';
        const badge = document.getElementById('image-status-badge');
        badge.textContent = 'New Image Selected';
        badge.className = 'badge bg-primary';
        document.getElementById('change-image-btn-label').textContent = 'Change Image';
        document.getElementById('btn-reset-image').style.display = 'inline-block';
      };
      reader.readAsDataURL(input.files[0]);
    }
  }

  function resetToDefaultImage() {
    document.getElementById('game-image-preview').src = placeholderImageUrl;
    document.getElementById('game_image_input').value = '';
    document.getElementById('reset_image_input').value = '1';
    const badge = document.getElementById('image-status-badge');
    badge.textContent = 'No Custom Image Uploaded';
    badge.className = 'badge bg-secondary';
    document.getElementById('change-image-btn-label').textContent = 'Select Image';
    document.getElementById('btn-reset-image').style.display = 'none';
  }

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
