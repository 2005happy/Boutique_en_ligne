<?php
// produit.php
require_once 'config/init.php';

// Récupérer l'ID du produit
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

// Charger le produit
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.nom AS categorie, c.id AS categorie_id
        FROM produits p
        LEFT JOIN categories c ON p.categorie_id = c.id
        WHERE p.id = ? AND p.archive = 0
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $produit = $stmt->fetch();
} catch (PDOException $e) {
    $produit = null;
}

// Produit introuvable → redirection
if (!$produit) {
    include 'includes/header.php';
    echo '<div class="container" style="padding:60px 20px;text-align:center;">';
    echo '<h1 style="font-size:3rem;margin-bottom:20px;">🔍</h1>';
    echo '<h2>Produit introuvable</h2>';
    echo '<p style="color:#7f8c8d;margin:20px 0;">Ce produit n\'existe pas ou a été retiré du catalogue.</p>';
    echo '<a href="index.php" class="btn-primary">← Retour à l\'accueil</a>';
    echo '</div>';
    include 'includes/footer.php';
    exit;
}

// Produits similaires (même catégorie, sauf ce produit)
$similaires = [];
if ($produit['categorie_id']) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, nom, prix, stock
            FROM produits
            WHERE categorie_id = ? AND id != ? AND archive = 0
            ORDER BY RAND()
            LIMIT 4
        ");
        $stmt->execute([$produit['categorie_id'], $id]);
        $similaires = $stmt->fetchAll();
    } catch (PDOException $e) {}
}

// Journal de consultation (Critère 10)
if (isset($_SESSION['user_id'])) {
    try {
        $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
            ->execute([$_SESSION['user_id'], 'CONSULTATION_PRODUIT', "Produit #$id : " . $produit['nom']]);
    } catch (PDOException $e) {}
}

// Message ajout panier (si l'utilisateur a cliqué)
$message_panier = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_panier'])) {
    if (!isset($_SESSION['user_id'])) {
        $message_panier = '<div class="alert alert-error">Vous devez être connecté pour ajouter au panier. <a href="login.php" class="link">Se connecter</a></div>';
    } else {
        $quantite = max(1, (int)($_POST['quantite'] ?? 1));
        if ($quantite > $produit['stock']) {
            $message_panier = '<div class="alert alert-error">Quantité demandée supérieure au stock disponible.</div>';
        } else {
            $_SESSION['panier'][$id] = ($_SESSION['panier'][$id] ?? 0) + $quantite;
            $message_panier = '<div class="alert alert-success">✅ Produit ajouté au panier (' . $_SESSION['panier'][$id] . ' au total).</div>';
        }
    }
}

include 'includes/header.php';
?>

<!-- Fil d'Ariane -->
<nav class="breadcrumb">
    <a href="index.php">🏠 Accueil</a>
    <span class="breadcrumb-sep">›</span>
    <?php if ($produit['categorie']): ?>
        <a href="index.php?page=produits&categorie=<?= (int)$produit['categorie_id'] ?>">
            <?= htmlspecialchars($produit['categorie']) ?>
        </a>
        <span class="breadcrumb-sep">›</span>
    <?php endif; ?>
    <span class="breadcrumb-current"><?= htmlspecialchars($produit['nom']) ?></span>
</nav>

<?= $message_panier ?>

<!-- Fiche produit -->
<section class="produit-detail">
    <div class="produit-detail-image">
        <div class="produit-detail-placeholder">📦</div>
    </div>

    <div class="produit-detail-info">
        <?php if ($produit['categorie']): ?>
            <span class="badge"><?= htmlspecialchars($produit['categorie']) ?></span>
        <?php endif; ?>

        <h1><?= htmlspecialchars($produit['nom']) ?></h1>

        <p class="produit-detail-prix">
            <?= number_format($produit['prix'], 2, ',', ' ') ?> <?= htmlspecialchars(param('devise', '€')) ?>
        </p>

        <div class="produit-detail-stock">
            <?php if ($produit['stock'] > 10): ?>
                <span class="badge badge-success">✅ En stock (<?= $produit['stock'] ?> disponibles)</span>
            <?php elseif ($produit['stock'] > 0): ?>
                <span class="badge badge-warning">⚠️ Plus que <?= $produit['stock'] ?> en stock</span>
            <?php else: ?>
                <span class="badge badge-danger">❌ Rupture de stock</span>
            <?php endif; ?>
        </div>

        <div class="produit-detail-description">
            <h2>Description</h2>
            <p><?= nl2br(htmlspecialchars($produit['description'] ?? 'Aucune description disponible.')) ?></p>
        </div>

        <div class="produit-detail-actions">
            <?php if ($produit['stock'] > 0): ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="POST" action="produit.php?id=<?= $id ?>" class="panier-form">
                        <label for="quantite">Quantité :</label>
                        <input type="number" id="quantite" name="quantite" value="1"
                               min="1" max="<?= $produit['stock'] ?>" required>
                        <button type="submit" name="ajouter_panier" class="btn-primary">
                            🛒 Ajouter au panier
                        </button>
                    </form>
                <?php else: ?>
                    <a href="login.php" class="btn-primary">🔐 Se connecter pour commander</a>
                <?php endif; ?>
            <?php else: ?>
                <button class="btn-secondary" disabled style="opacity:0.5;cursor:not-allowed;">
                    ❌ Rupture de stock
                </button>
            <?php endif; ?>

            <a href="index.php" class="btn-secondary">← Retour au catalogue</a>
        </div>

        <!-- Informations complémentaires -->
        <div class="produit-detail-meta">
            <div class="meta-item">
                <strong>Référence</strong>
                <span>#<?= str_pad($produit['id'], 5, '0', STR_PAD_LEFT) ?></span>
            </div>
            <div class="meta-item">
                <strong>Ajouté le</strong>
                <span><?= date('d/m/Y', strtotime($produit['cree_le'])) ?></span>
            </div>
            <div class="meta-item">
                <strong>Catégorie</strong>
                <span><?= htmlspecialchars($produit['categorie'] ?? 'Non classé') ?></span>
            </div>
        </div>
    </div>
</section>

<!-- Produits similaires -->
<?php if (!empty($similaires)): ?>
<section class="similaires-section">
    <h2>🔗 Produits similaires</h2>
    <div class="produits-grid">
        <?php foreach ($similaires as $s): ?>
            <div class="produit-card">
                <div class="produit-image">📦</div>
                <div class="produit-info">
                    <h3><?= htmlspecialchars($s['nom']) ?></h3>
                    <p class="prix"><?= number_format($s['prix'], 2, ',', ' ') ?> <?= htmlspecialchars(param('devise', '€')) ?></p>
                    <p class="stock <?= $s['stock'] > 0 ? 'en-stock' : 'rupture' ?>">
                        <?= $s['stock'] > 0 ? '✅ En stock' : '❌ Rupture' ?>
                    </p>
                    <a href="produit.php?id=<?= (int)$s['id'] ?>" class="btn-secondary">Voir le détail</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>