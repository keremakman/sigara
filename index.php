<?php
require_once __DIR__ . '/bootstrap.php';

if (is_logged_in()) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $password = trim($password);
    $remember = isset($_POST['remember']);
    if ($password === ($config['auth']['user1_password'] ?? '')) {
        $_SESSION['user_key'] = 'user1';
        generate_csrf_token();
        if ($remember) { set_remember_cookie('user1'); } else { clear_remember_cookie(); }
        header('Location: dashboard.php'); exit;
    } else if ($password === ($config['auth']['user2_password'] ?? '')) {
        $_SESSION['user_key'] = 'user2';
        generate_csrf_token();
        if ($remember) { set_remember_cookie('user2'); } else { clear_remember_cookie(); }
        header('Location: dashboard.php'); exit;
    } else {
        $error = 'Şifre yanlış.';
    }
}
?>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($config['site']['title'] ?? 'Sigara Sayaç'); ?> - Giriş</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link href="assets/styles.css" rel="stylesheet">
</head>
<body class="app-bg">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
<div class="card app-card shadow">
                <div class="card-body p-4">
                    <h1 class="h4 mb-3 text-center"><?php echo htmlspecialchars($config['site']['title'] ?? 'Sigara Sayaç'); ?></h1>
                    <p class="text-muted text-center mb-4">Şifreni girerek giriş yap.</p>
                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <form method="post" autocomplete="off">
                        <div class="mb-3">
                            <label for="password" class="form-label">Şifre</label>
                            <input type="password" class="form-control" id="password" name="password" required autofocus autocomplete="current-password">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" checked>
                            <label class="form-check-label" for="remember">Beni bu tarayıcıda hatırla</label>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Giriş yap</button>
                        </div>
                    </form>
                    <div class="mt-3 small text-muted">
                        Not: İki ayrı şifre var; kendi şifreni gir.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

