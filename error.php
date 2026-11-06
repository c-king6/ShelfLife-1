<?php
// Custom error page for 404 (not found) and other server errors.
// The URL parameters are read to display the right message.

$code = isset($_GET['code']) ? (int)$_GET['code'] : 404;

$messages = [
    400 => ['Bad request', 'The request could not be understood. Please check the URL and try again.'],
    403 => ['Access denied', 'You don\'t have permission to view this page.'],
    404 => ['Page not found', 'The page you\'re looking for doesn\'t exist or has been moved.'],
    500 => ['Server error', 'Something went wrong on our end. Please try again in a moment.'],
];
$info = $messages[$code] ?? $messages[404];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $info[0]; ?> · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" href="icons/icon-192.png">
    <link rel="shortcut icon" href="favicon.ico">

    <style>
        body {
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; margin: 0; padding: 24px;
        }
        .error-card {
            max-width: 480px; width: 100%;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-xl);
            padding: 48px 36px;
            box-shadow: var(--sh-lg);
            text-align: center;
        }
        .error-icon {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: var(--sand-100);
            color: var(--ink-400);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 22px;
        }
        .error-icon .lucide { width: 34px; height: 34px; }
        .error-code {
            font-family: var(--font-display);
            font-size: 60px;
            font-weight: 700;
            color: var(--green-700);
            line-height: 1;
            letter-spacing: -0.04em;
            margin-bottom: 8px;
        }
        .error-card h1 {
            font-size: 22px;
            margin-bottom: 8px;
        }
        .error-card p {
            color: var(--ink-500);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 28px;
        }
        .error-actions {
            display: flex; gap: 10px; justify-content: center;
            flex-wrap: wrap;
        }
    </style>
</head>
<body class="page page--auth">
    <div class="error-card">
        <div class="error-icon">
            <i data-lucide="compass"></i>
        </div>
        <div class="error-code"><?php echo $code; ?></div>
        <h1><?php echo htmlspecialchars($info[0]); ?></h1>
        <p><?php echo htmlspecialchars($info[1]); ?></p>
        <div class="error-actions">
            <a href="dashboard.php" class="btn btn-primary">
                <i data-lucide="home" class="lucide-sm"></i> Back to pantry
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i data-lucide="arrow-left" class="lucide-sm"></i> Go back
            </a>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
</body>
</html>