<!-- Atlanta Daniel -->
<!-- May 2026 -->
<!-- index.php -->


<?php
session_start();

//check if user is already logged in
// if not, direct to login page
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_email = htmlspecialchars($_SESSION['user_email'] ?? '');
$user_zip = htmlspecialchars($_SESSION['user_zip'] ?? '');

//flash message
$flash_type = '';
$flash_msg = '';
if (!empty($_SESSION['flash_message'])) {
    $flash_type = htmlspecialchars($_SESSION['flash_type'] ?? 'success');
    $flash_msg = htmlspecialchars($_SESSION['flash_message']);
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚨MovieSign!</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --red:    #e8182a;
            --dark:   #0d0d0f;
            --panel:  #18181c;
            --border: #2e2e35;
            --muted:  #6b6b78;
            --text:   #f0eff4;
            --sub:    #a8a7b4;
            --yellow: #f5c542;
            --font-display: 'Bebas Neue', sans-serif;
            --font-body:    'DM Sans', sans-serif;
        }

        body {
            min-height: 100dvh;
            background: var(--dark);
            color: var(--text);
            font-family: var(--font-body);
        }

        /* ── Header ── */
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--panel);
        }

        .wordmark {
            font-family: var(--font-display);
            font-size: 1.9rem;
            color: var(--red);
            letter-spacing: 0.04em;
            text-shadow: 0 0 18px rgba(232,24,42,0.4);
        }
        .wordmark span { color: var(--text); }

        .user-bar {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.85rem;
            color: var(--sub);
        }

        .user-bar strong { color: var(--text); font-weight: 500; }

        .btn-logout {
            background: none;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--muted);
            font-family: var(--font-body);
            font-size: 0.82rem;
            padding: 0.35rem 0.75rem;
            cursor: pointer;
            transition: border-color 0.2s, color 0.2s;
        }
        .btn-logout:hover { border-color: var(--red); color: var(--red); }

        /* ── Main ── */
        main {
            padding: 2rem 1.5rem;
            max-width: 860px;
            margin: 0 auto;
        }

        /* ── Flash ── */
        .flash {
            border-radius: 8px;
            padding: 0.8rem 1rem;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        .flash.success { background: rgba(245,197,66,0.12); border: 1px solid #7a6318; color: var(--yellow); }
        .flash.error   { background: rgba(232,24,42,0.13); border: 1px solid #7a111e; color: #ff9499; }

        /* ── Placeholder content ── */
        .coming-soon {
            border: 1px dashed var(--border);
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            color: var(--muted);
        }
        .coming-soon .icon { font-size: 3rem; margin-bottom: 0.75rem; }
        .coming-soon h2 { font-family: var(--font-display); font-size: 1.6rem; color: var(--sub); letter-spacing: 0.06em; margin-bottom: 0.5rem; }
        .coming-soon p  { font-size: 0.9rem; }
    </style>

</head>

<body>

<header>
    <div class="wordmark">🚨Movie<span>Sign</span>!</div>
    <div class="user-bar">
        <span><?= $user_email ?> &middot; <?= $user_zip ?></span>
        <form method="POST" action="../app/Controller/auth.php" style="margin:0;">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn-logout">Sign out</button>
        </form>
    </div>
</header>

<main>
    <?php if ($flash_msg): ?>
        <div class="flash <?= $flash_type ?>"><?= $flash_msg ?></div>
    <?php endif; ?>

    <div class="coming-soon">
        <div class="icon">🎬</div>
        <h2>Watchlist Coming Soon</h2>
        <p>Add films to your watchlist and find showtimes near <?= $user_zip ?>.</p>
    </div>
</main>

</body>
</html>
