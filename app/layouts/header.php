<?php
/**
 * QRAttend :: Shared Layout Header
 * -----------------------------------------------------------------------------
 * HTML5 document skeleton, mobile-first viewport, Bootstrap 5 CDN, custom
 * stylesheet, and the global :root brand-variable block (from config.php).
 *
 * Expected before include:
 *   require_once __DIR__ . '/../config/config.php';  (defines constants)
 *   Optionally set $pageTitle in the calling script.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- Strict mobile rendering -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="<?= sanitize_input(INSTITUTION_NAME) ?> — QR Attendance Monitoring System">
    <meta name="theme-color" content="<?= sanitize_input(BRAND_PRIMARY) ?>">

    <title><?= sanitize_input($pageTitle ?? INSTITUTION_SHORT . ' - QRAttend') ?></title>

    <!-- Bootstrap 5 CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom stylesheet -->
    <link rel="stylesheet" href="/assets/css/style.css">

    <!-- Global brand palette as CSS custom properties -->
    <style>
        :root {
<?= BRAND_CSS_VARS ?>
        }
        body {
            background-color: var(--brand-surface);
            color: var(--brand-text);
            min-height: 100vh;
        }
    </style>
</head>
<body>

