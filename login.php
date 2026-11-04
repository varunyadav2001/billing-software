<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = get_db_connection();
$settings = get_store_settings($pdo);

$error = '';

if (is_logged_in()) {
    header('Location: billing.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $pin = trim($_POST['pin'] ?? '');

    if ($pin) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE pin = ? LIMIT 1");
        $stmt->execute([$pin]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: billing.php');
            exit;
        } else {
            $error = 'चुकीचा PIN नंबर!';
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: billing.php');
            exit;
        } else {
            $error = 'ईमेल किंवा पासवर्ड चुकीचा आहे.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>लॉगिन - <?= htmlspecialchars($settings['store_name_mr']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0a3528 0%, #0f4c3a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .login-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
            max-width: 420px;
            width: 100%;
            overflow: hidden;
            border: 2px solid #d4af37;
        }
        .login-banner {
            background: linear-gradient(135deg, #0a3528 0%, #166b52 100%);
            color: #ffffff;
            padding: 2rem 1.5rem;
            text-align: center;
            border-bottom: 3px solid #d4af37;
        }
        .login-form-body {
            padding: 2rem 1.75rem;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-banner">
        <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">✨</div>
        <h2 style="font-size: 1.35rem; color: #f7e7a9; margin-bottom: 0.25rem;">
            <?= htmlspecialchars($settings['store_name_mr'] ?: $settings['store_name']) ?>
        </h2>
        <p style="font-size: 0.8rem; color: #cbd5e1;">रिटेल POS बिलिंग व स्टॉक सिस्टीम</p>
    </div>

    <div class="login-form-body">
        <?php if ($error): ?>
            <div style="padding: 0.65rem; background: var(--danger-light); color: var(--danger); border-radius: 6px; margin-bottom: 1rem; font-size: 0.85rem; font-weight: 600;">
                ✕ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">ईमेल आयडी</label>
                <input type="email" name="email" class="form-control" value="admin@yourstore.com" required>
            </div>

            <div class="form-group">
                <label class="form-label">पासवर्ड</label>
                <input type="password" name="password" class="form-control" value="password123" required>
            </div>

            <button type="submit" class="btn btn-gold" style="width: 100%; padding: 0.75rem; font-size: 1rem; margin-top: 0.5rem;">
                लॉगिन करा (Login)
            </button>
        </form>

        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed #cbd5e1; text-align: center;">
            <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.5rem;">किंवा थेट बिलिंग सुरू करा:</p>
            <a href="billing.php" class="btn btn-primary" style="width: 100%; justify-content: center;">
                ⚡ थेट बिलिंग स्क्रीनवर जा (Skip to POS)
            </a>
        </div>
    </div>
</div>

</body>
</html>
