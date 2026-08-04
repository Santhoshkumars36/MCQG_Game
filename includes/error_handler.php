<?php
/**
 * MCQG - Shared Error / Flash Alert Renderer
 * Path: includes/error_handler.php
 * Purpose: Reusable, styled (Bootstrap 5 + custom classes) rendering
 * helpers for errors and flash alerts, used by any screen so
 * validation feedback looks like a designed product rather than a
 * plain browser error string. Actual PHP error logging happens in
 * config/app_config.php (error_log ini settings) - this file only
 * renders HTML.
 */

/**
 * Renders a dismissible Bootstrap alert.
 * $type: 'success' | 'danger' | 'warning' | 'info'
 */
function renderAlert(string $type, string $message): void
{
    $icons = ['success' => '&#9989;', 'danger' => '&#10060;', 'warning' => '&#9888;', 'info' => '&#8505;'];
    $icon = $icons[$type] ?? $icons['info'];

    echo '<div class="alert alert-' . htmlspecialchars($type) . ' d-flex align-items-center gap-2 shadow-sm" role="alert">'
        . '<span>' . $icon . '</span>'
        . '<span>' . htmlspecialchars($message) . '</span>'
        . '<button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>'
        . '</div>';
}

/**
 * Reads both flash types this app actually uses (Session::setFlash
 * is called with 'success' or 'error' throughout admin/ and player/)
 * and renders whichever is present.
 */
function renderFlashIfAny(): void
{
    $success = Session::getFlash('success');
    if ($success) {
        renderAlert('success', $success);
    }
    $error = Session::getFlash('error');
    if ($error) {
        renderAlert('danger', $error);
    }
}

/** Renders a full list of validation errors (used under a form after a failed submit). */
function renderValidationErrors(array $errors): void
{
    if (empty($errors)) {
        return;
    }
    echo '<div class="alert alert-danger" role="alert">';
    echo '<strong>Please fix the following:</strong>';
    echo '<ul class="mb-0 mt-1">';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul></div>';
}

/**
 * Full-page, styled error screen for fatal conditions (403, 404, 500).
 * Uses the same visual language as the rest of the app so it never
 * looks like a broken/default server error page.
 */
function renderErrorPage(int $code, string $title, string $message): void
{
    http_response_code($code);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?php echo htmlspecialchars($title); ?> | MCQG</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/bootstrap.min.css">
        <link rel="stylesheet" href="<?php echo ASSET_URL; ?>css/common.css">
        <style>
            body { min-height:100vh; display:flex; align-items:center; justify-content:center; background:#f4f6fb; }
            .mcqg-error-card { max-width:480px; background:#fff; }
            .mcqg-error-code { color:#1e2761; }
        </style>
    </head>
    <body>
        <div class="text-center mcqg-error-card p-5 shadow-lg rounded-4">
            <div class="mcqg-error-code display-1 fw-bold mb-2"><?php echo (int) $code; ?></div>
            <h2 class="mb-3"><?php echo htmlspecialchars($title); ?></h2>
            <p class="text-muted mb-4"><?php echo htmlspecialchars($message); ?></p>
            <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-primary btn-lg px-4">Go to Home</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
