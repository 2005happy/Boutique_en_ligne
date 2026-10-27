<?php
// index.php
require 'config/database.php';

// Récupération des produits récents (non archivés)
try {
    $stmt = $pdo->query("
        SELECT p.id, p.nom, p.description, p.prix, p.stock, c.nom AS categorie
        FROM produits p
        LEFT JOIN categories c ON p.categorie_id = c.id
        WHERE p.archive = 0
        ORDER BY p.cree_le DESC
        LIMIT 6
    ");
    $produits = $stmt->fetchAll();
} catch (PDOException $e) {
    $produits = [];
    $erreur = "Impossible de charger les produits : " . $e->getMessage();
}

// Récupération des catégories pour le menu
try {
    $categories = $pdo->query("SELECT id, nom FROM categories WHERE archive = 0 ORDER BY nom")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

include 'includes/header.php';
?>

<!-- Bannière d'accueil -->
<section class="hero">
    <div class="hero-content">
        <h1>Bienvenue sur Ma Boutique</h1>
        <p>Découvrez nos produits de qualité au meilleur prix.</p>
        <a href="index.php?page=produits" class="btn-primary">Voir les produits</a>
    </div>
</section>

<!-- Section catégories -->
<section class="categories-section">
    <h2>Nos catégories</h2>
    <div class="categories-grid">
        <?php if (empty($categories)): ?>
            <p class="empty">Aucune catégorie disponible.</p>
        <?php else: ?>
            <?php foreach ($categories as $cat): ?>
                <a href="index.php?page=produits&categorie=<?= (int)$cat['id'] ?>" class="categorie-card">
                    <?= htmlspecialchars($cat['nom']) ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Section produits récents -->
<section class="produits-section">
    <h2>Produits récents</h2>

    <?php if (isset($erreur)): ?>
        <p class="error"><?= htmlspecialchars($erreur) ?></p>
    <?php endif; ?>

    <?php if (empty($produits)): ?>
        <p class="empty">Aucun produit disponible pour le moment.</p>
    <?php else: ?>
        <div class="produits-grid">
            <?php foreach ($produits as $p): ?>
                <div class="produit-card">
                    <div class="produit-image">📦</div>
                    <div class="produit-info">
                        <h3><?= htmlspecialchars($p['nom']) ?></h3>
                        <span class="badge"><?= htmlspecialchars($p['categorie'] ?? 'Sans catégorie') ?></span>
                        <p class="description"><?= htmlspecialchars(mb_substr($p['description'] ?? '', 0, 80)) ?>...</p>
                        <p class="prix"><?= number_format($p['prix'], 2, ',', ' ') ?> €</p>
                        <p class="stock <?= $p['stock'] > 0 ? 'en-stock' : 'rupture' ?>">
                            <?= $p['stock'] > 0 ? 'En stock (' . $p['stock'] . ')' : 'Rupture de stock' ?>
                        </p>
                        <a href="produit.php?id=<?= (int)$p['id'] ?>" class="btn-secondary">Détails</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Section à propos / contact rapide -->
<section class="about-section">
    <h2>Pourquoi nous choisir ?</h2>
    <div class="features-grid">
        <div class="feature">
            <span class="feature-icon">🚚</span>
            <h3>Livraison rapide</h3>
            <p>Vos commandes livrées sous 48h.</p>
        </div>
        <div class="feature">
            <span class="feature-icon">🔒</span>
            <h3>Paiement sécurisé</h3>
            <p>Transactions 100% sécurisées.</p>
        </div>
        <div class="feature">
            <span class="feature-icon">💬</span>
            <h3>Support client</h3>
            <p>Une équipe à votre écoute 7j/7.</p>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
