<?php
// Atlanta Daniel
// May 2026
// auth_layout.php - layout helper for auth pages (login / register)
// Call render_auth_page($title, $content_html) to output a full page

function render_auth_page($title, $content_html) {
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MovieSign! - <?= htmlspecialchars($title) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <link rel="icon" type="image/x-icon" href="../public/icons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../public/icons/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../public/icons/favicon-180.png">
    
    <link rel="stylesheet" href="../public/styles/auth.css?v=<?= filemtime(__DIR__ . '/../../public/styles/auth.css') ?>"> <!-- added the 'filemtime' section to force the browser to load the latest CSS changes -->

    <link rel="manifest" href="../public/manifest.json">
    <meta name="theme-color" content="#e63946">

</head>

<body>
    <div class="logo">🚨Movie<span>Sign</span>!</div>
    <p class="tagline">We've got movie sign</p>

    <div class="card">
        <?php
        //display flash message if one exists in the session
        if (!empty($_SESSION['flash_message'])) {
            $type = htmlspecialchars($_SESSION['flash_type'] ?? 'error');
            $msg = htmlspecialchars($_SESSION['flash_message']);
            echo "<div class=\"flash $type\">$msg</div>";
            unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        }
        ?>
        <?= $content_html ?>
    </div>

</body>
</html>

<?php
}
?>