<?php
$admin_page = basename($_SERVER['PHP_SELF'] ?? '');
$nom_boutique = param('nom_boutique', 'Ma Boutique');
$sections = [
    'Principal' => [
        ['dashboard.php', '📊', 'Tableau de bord'],
    ],
    'Gestion des données' => [
        ['products.php', '📦', 'Produits'],
        ['categories.php', '🏷️', 'Catégories'],
        ['documents.php', '📁', 'Documents'],
    ],
    'Utilisateurs' => [
        ['users.php', '👥', 'Utilisateurs'],
        ['pending_users.php', '⏳', 'Validations'],
        ['connections.php', '🔌', 'Connexions'],
        ['activities.php', '📝', 'Activités'],
    ],
    'Rapports' => [
        ['reports.php', '📈', 'Rapports & statistiques'],
    ],
    'Système' => [
        ['backup.php', '💾', 'Sauvegardes'],
        ['settings.php', '⚙️', 'Paramètres'],
    ],
];
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>🛍️ <?= htmlspecialchars($nom_boutique, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="sidebar-subtitle">Espace Admin</p>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($sections as $section => $links): ?>
            <p class="nav-section-title"><?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?></p>
            <?php foreach ($links as [$href, $icon, $label]): ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                   class="nav-link<?= $admin_page === $href ? ' active' : '' ?>"
                   <?= $admin_page === $href ? 'aria-current="page"' : '' ?>>
                    <span class="nav-icon"><?= $icon ?></span> <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <div class="nav-divider"></div>
        <a href="../logout.php" class="nav-link nav-logout">
            <span class="nav-icon">🚪</span> Déconnexion
        </a>
    </nav>
</aside>
