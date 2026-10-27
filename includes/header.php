<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$theme_actif = param('theme', 'clair');
$nom_boutique = param('nom_boutique', 'Ma Boutique');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ma Boutique - Accueil</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body>
<header class="main-header">
    <div class="container">
        <div class="logo">
            <a href="index.php">🛍️ Ma Boutique</a>
        </div>
        <nav class="main-nav">
            <ul>
                <li><a href="index.php">Accueil</a></li>
                <li><a href="index.php?page=produits">Produits</a></li>
                <li><a href="index.php?page=contact">Contact</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li><a href="admin/dashboard.php">Tableau de bord</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php" class="btn-logout">Déconnexion (<?= htmlspecialchars($_SESSION['nom'] ?? 'Utilisateur') ?>)</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="btn-login">Connexion</a></li>
                    <li><a href="register.php" class="btn-register">S'inscrire</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
<main class="container">
