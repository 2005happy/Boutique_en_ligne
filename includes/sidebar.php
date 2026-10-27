<?php
// includes/sidebar.php
// ⚠️ Nécessite : $_SESSION, $pdo, et la fonction param() (via init.php)

// Compteurs pour les badges
$nb_attente_validation = 0;
$nb_commandes_attente = 0;
try {
    $nb_attente_validation = (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE statut = 'en_attente'")->fetchColumn();
    $nb_commandes_attente = (int)$pdo->query("SELECT COUNT(*) FROM commandes WHERE statut = 'en_attente'")->fetchColumn();
} catch (PDOException $e) {}

// Déterminer la page active
$page_active = basename($_SERVER['PHP_SELF']);

// Helper : ajouter la classe active
function nav_active(string $page, string $active): string {
    return $page === $active ? 'active' : '';
}
?>
<button class="sidebar-toggle" type="button" aria-controls="admin-sidebar" aria-expanded="false">
    <span aria-hidden="true">☰</span> Menu
</button>
<button class="sidebar-backdrop" type="button" aria-label="Fermer le menu" tabindex="-1"></button>
<aside class="sidebar" id="admin-sidebar">
    <div class="sidebar-header">
        <h2>🛍️ <?= htmlspecialchars(param('nom_boutique', 'Ma Boutique')) ?></h2>
        <p class="sidebar-subtitle">Espace Admin</p>
    </div>
    <nav class="sidebar-nav">
        <p class="nav-section-title">Principal</p>
        <a href="dashboard.php" class="nav-link <?= nav_active('dashboard.php', $page_active) ?>">
            <span class="nav-icon">📊</span> Tableau de bord
        </a>

        <p class="nav-section-title">Gestion des données</p>
        <a href="products.php" class="nav-link <?= nav_active('products.php', $page_active) ?>">
            <span class="nav-icon">📦</span> Produits
        </a>
        <a href="categories.php" class="nav-link <?= nav_active('categories.php', $page_active) ?>">
            <span class="nav-icon">🏷️</span> Catégories
        </a>
        <a href="orders.php" class="nav-link <?= nav_active('orders.php', $page_active) ?><?= nav_active('order_detail.php', $page_active) ? ' active' : '' ?>">
            <span class="nav-icon">🛒</span> Commandes
            <?php if ($nb_commandes_attente > 0): ?>
                <span class="badge-notif"><?= $nb_commandes_attente ?></span>
            <?php endif; ?>
        </a>

        <p class="nav-section-title">Utilisateurs</p>
        <a href="users.php" class="nav-link <?= nav_active('users.php', $page_active) ?>">
            <span class="nav-icon">👥</span> Utilisateurs
        </a>
        <a href="pending_users.php" class="nav-link <?= nav_active('pending_users.php', $page_active) ?>">
            <span class="nav-icon">⏳</span> Validations
            <?php if ($nb_attente_validation > 0): ?>
                <span class="badge-notif"><?= $nb_attente_validation ?></span>
            <?php endif; ?>
        </a>
        <a href="connections.php" class="nav-link <?= nav_active('connections.php', $page_active) ?>">
            <span class="nav-icon">🔌</span> Connexions
        </a>
        <a href="activities.php" class="nav-link <?= nav_active('activities.php', $page_active) ?>">
            <span class="nav-icon">📝</span> Activités
        </a>

        <p class="nav-section-title">Documents</p>
        <a href="documents.php" class="nav-link <?= nav_active('documents.php', $page_active) ?>">
            <span class="nav-icon">📁</span> Documents
        </a>

        <p class="nav-section-title">Rapports</p>
        <a href="reports.php" class="nav-link <?= nav_active('reports.php', $page_active) ?>">
            <span class="nav-icon">📈</span> Rapports & Stats
        </a>

        <p class="nav-section-title">Système</p>
        <a href="backup.php" class="nav-link <?= nav_active('backup.php', $page_active) ?>">
            <span class="nav-icon">💾</span> Sauvegardes
        </a>
        <a href="settings.php" class="nav-link <?= nav_active('settings.php', $page_active) ?>">
            <span class="nav-icon">⚙️</span> Paramètres
        </a>

    </nav>
    <div class="sidebar-footer">
        <a href="../logout.php" class="nav-link nav-logout">
            <span class="nav-icon">🚪</span> Déconnexion
        </a>
    </div>
</aside>
<script>
    (() => {
        const toggle = document.querySelector('.sidebar-toggle');
        const backdrop = document.querySelector('.sidebar-backdrop');
        if (!toggle || !backdrop) return;

        const closeMenu = () => {
            document.body.classList.remove('sidebar-open');
            toggle.setAttribute('aria-expanded', 'false');
        };

        toggle.addEventListener('click', () => {
            const isOpen = document.body.classList.toggle('sidebar-open');
            toggle.setAttribute('aria-expanded', String(isOpen));
        });
        backdrop.addEventListener('click', closeMenu);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeMenu();
        });
        document.querySelectorAll('.sidebar .nav-link').forEach((link) => {
            link.addEventListener('click', closeMenu);
        });
    })();
</script>