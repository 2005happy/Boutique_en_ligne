<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/panier.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$theme_actif = param('theme', 'clair');
$nom_boutique = param('nom_boutique', 'Ma Boutique');
$inscription_ouverte = param_bool('inscription_ouverte', true);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars($theme_actif, ENT_QUOTES, 'UTF-8') ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($nom_boutique, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>

<body>
    <header class="main-header">
        <div class="container">
            <div class="logo">
                <a href="index.php">🛍️ <?= htmlspecialchars($nom_boutique) ?></a>
            </div>
            <button class="main-nav-toggle" type="button" aria-controls="main-navigation" aria-expanded="false">
                <span class="main-nav-toggle-icon" aria-hidden="true">☰</span>
                <span>Menu</span>
            </button>
            <nav class="main-nav" id="main-navigation">
                <ul>
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="panier.php">🛒 Panier (<?= panier_nombre() ?>)</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="mes_commandes.php">📦 Mes commandes</a></li>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <li><a href="admin/dashboard.php">Tableau de bord</a></li>
                        <?php endif; ?>
                        <?php if ($_SESSION['role'] === 'vendeur'): ?>
                            <li><a href="vandeur/dashboard.php">🏪 Mon espace vendeur</a></li>
                        <?php endif; ?>
                        <?php
                        require_once __DIR__ . '/../config/messagerie.php';
                        $nb_msg = nb_messages_non_lus($pdo, (int) $_SESSION['user_id']);
                        $nb_notif = nb_notifications_non_lues($pdo, (int) $_SESSION['user_id']);
                        ?>
                        <li>
                            <a href="messages.php" title="Messages">
                                💬 Messages<?php if ($nb_msg > 0): ?> <span class="nav-badge"><?= $nb_msg ?></span><?php endif; ?>
                            </a>
                        </li>
                        <li>
                            <a href="notifications.php" title="Notifications">
                                🔔 <?php if ($nb_notif > 0): ?><span class="nav-badge"><?= $nb_notif ?></span><?php endif; ?>
                            </a>
                        </li>
                        <li>
                            <a href="profil.php" class="user-menu">
                                <span class="avatar-sm"><?= strtoupper(substr($_SESSION['nom'], 0, 1)) ?></span>
                                <?= htmlspecialchars($_SESSION['nom']) ?>
                            </a>
                        </li>
                        <li><a href="logout.php">Déconnexion</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Connexion</a></li>
                        <li><a href="register.php">S'inscrire</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <script>
        (() => {
            const toggle = document.querySelector('.main-nav-toggle');
            const navigation = document.getElementById('main-navigation');
            if (!toggle || !navigation) return;

            toggle.addEventListener('click', () => {
                const isOpen = navigation.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', String(isOpen));
                toggle.querySelector('.main-nav-toggle-icon').textContent = isOpen ? '✕' : '☰';
            });
        })();
    </script>
    <main class="container">