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
    <title>🚨MovieSign! - <?= htmlspecialchars($title) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../public/styles/auth.css">
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