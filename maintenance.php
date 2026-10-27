<?php
// maintenance.php
require_once 'config/init.php';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair'), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <title>Maintenance — <?= htmlspecialchars(param('nom_boutique', 'Ma Boutique')) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card" style="text-align:center;">
            <h1 style="font-size:3rem;margin-bottom:20px;">🚧</h1>
            <h2><?= htmlspecialchars(param('nom_boutique', 'Ma Boutique')) ?> est en maintenance</h2>
            <p style="color:#7f8c8d;margin:20px 0;">
                Nous revenons très vite ! Merci de votre patience.
            </p>
            <a href="login.php" class="btn-secondary" style="margin-top:15px;">🔐 Connexion administrateur</a>
        </div>
    </div>
</body>
</html>