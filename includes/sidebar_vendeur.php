<?php
// includes/sidebar_vendeur.php
$page_active = basename($_SERVER['PHP_SELF']);

// Compteurs
$nb_produits = 0;
$nb_commandes = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE vendeur_id = ? AND archive = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $nb_produits = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
}

function nav_vendeur_active(string $page, string $active): string
{
    return $page === $active ? 'active' : '';
}
?>
<button class="sidebar-toggle" type="button" aria-controls="admin-sidebar" aria-expanded="false">
    <span aria-hidden="true">☰</span> Menu
</button>
<button class="sidebar-backdrop" type="button" aria-label="Fermer le menu" tabindex="-1"></button>
<aside class="sidebar" id="admin-sidebar">
    <div class="sidebar-header">
        <h2>🏪 Espace Vendeur</h2>
        <p class="sidebar-subtitle"><?= htmlspecialchars($_SESSION['nom']) ?></p>
    </div>
    <nav class="sidebar-nav">
        <p class="nav-section-title">Mon activité</p>
        <a href="dashboard.php" class="nav-link <?= nav_vendeur_active('dashboard.php', $page_active) ?>">
            <span class="nav-icon">📊</span> Tableau de bord
        </a>

        <p class="nav-section-title">Ma boutique</p>
        <a href="mes_produits.php" class="nav-link <?= nav_vendeur_active('mes_produits.php', $page_active) ?>">
            <span class="nav-icon">📦</span> Mes produits
            <?php if ($nb_produits > 0): ?>
                <span class="badge-notif" style="background:#27ae60;"><?= $nb_produits ?></span>
            <?php endif; ?>
        </a>
        <a href="ajouter_produit.php" class="nav-link <?= nav_vendeur_active('ajouter_produit.php', $page_active) ?>">
            <span class="nav-icon">➕</span> Ajouter un produit
        </a>

        <p class="nav-section-title">Commandes</p>
        <a href="mes_commandes.php" class="nav-link <?= nav_vendeur_active('mes_commandes.php', $page_active) ?>">
            <span class="nav-icon">🛒</span> Mes ventes
        </a>

        <a href="mes_statistiques.php" class="nav-link <?= nav_vendeur_active('mes_statistiques.php', $page_active) ?>">
            <span class="nav-icon">📈</span> Mes statistiques
        </a>

        <p class="nav-section-title">Profil</p>
        <a href="mon_profil.php" class="nav-link <?= nav_vendeur_active('mon_profil.php', $page_active) ?>">
            <span class="nav-icon">👤</span> Mon profil
        </a>

        <a href="../index.php" class="nav-link">
            <span class="nav-icon">🌐</span> Voir le site
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