<?php
// panier.php
require_once 'config/init.php';
require_once 'config/panier.php';

// Actions rapides
if (isset($_GET['vider'])) {
    panier_vider();
    header("Location: panier.php");
    exit;
}

if (isset($_GET['supprimer'])) {
    panier_retirer((int)$_GET['supprimer']);
    header("Location: panier.php");
    exit;
}

// Mise à jour des quantités
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quantites'])) {
    foreach ($_POST['quantites'] as $pid => $qte) {
        $pid = (int)$pid;
        $qte = (int)$qte;
        if ($qte <= 0) {
            panier_retirer($pid);
        } else {
            $_SESSION['panier'][$pid] = $qte;
        }
    }
    header("Location: panier.php");
    exit;
}

$panier_data = panier_contenu($pdo);
$articles = $panier_data['articles'];
$total = $panier_data['total'];
$erreurs = $panier_data['erreurs'];

include 'includes/header.php';
?>

<nav class="breadcrumb">
    <a href="index.php">🏠 Accueil</a>
    <span class="breadcrumb-sep">›</span>
    <span class="breadcrumb-current">Mon panier</span>
</nav>

<h1 style="margin-bottom:25px;">🛒 Mon panier (<?= panier_nombre() ?> article(s))</h1>

<?php if (!empty($erreurs)): ?>
    <div class="alert alert-error">
        ⚠️ <strong>Attention :</strong>
        <ul style="margin:10px 0 0 20px;">
            <?php foreach ($erreurs as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (empty($articles)): ?>
    <div style="text-align:center;padding:60px 20px;background:#fff;border-radius:12px;">
        <p style="font-size:3rem;margin-bottom:20px;">🛒</p>
        <p style="color:#7f8c8d;margin-bottom:20px;">Votre panier est vide.</p>
        <a href="index.php" class="btn-primary">Découvrir les produits</a>
    </div>
<?php else: ?>

<form method="POST" action="panier.php">
    <div class="panier-layout">
        <!-- Liste des articles -->
        <div class="panier-articles">
            <?php foreach ($articles as $a): ?>
                <div class="panier-item">
                    <div class="panier-item-image">📦</div>
                    <div class="panier-item-info">
                        <h3><?= htmlspecialchars($a['nom']) ?></h3>
                        <p class="text-muted">
                            Prix unitaire : <?= number_format($a['prix'], 2, ',', ' ') ?> <?= param('devise', '€') ?>
                        </p>
                        <p class="text-muted" style="font-size:0.85rem;">
                            Stock disponible : <?= $a['stock'] ?>
                        </p>
                    </div>
                    <div class="panier-item-qte">
                        <label>Qté :</label>
                        <input type="number"
                               name="quantites[<?= $a['id'] ?>]"
                               value="<?= $a['quantite'] ?>"
                               min="1" max="<?= $a['stock'] ?>">
                    </div>
                    <div class="panier-item-soustotal">
                        <strong><?= number_format($a['sous_total'], 2, ',', ' ') ?> <?= param('devise', '€') ?></strong>
                    </div>
                    <a href="?supprimer=<?= $a['id'] ?>" class="btn-icon btn-danger" title="Retirer">🗑️</a>
                </div>
            <?php endforeach; ?>

            <div class="panier-actions">
                <a href="?vider=1" class="btn-secondary" onclick="return confirm('Vider le panier ?');">
                    🗑️ Vider le panier
                </a>
                <button type="submit" class="btn-secondary">🔄 Mettre à jour les quantités</button>
            </div>
        </div>

        <!-- Récapitulatif -->
        <aside class="panier-recap">
            <h2>Récapitulatif</h2>
            <div class="recap-line">
                <span>Sous-total</span>
                <span><?= number_format($total, 2, ',', ' ') ?> <?= param('devise', '€') ?></span>
            </div>
            <div class="recap-line">
                <span>Livraison</span>
                <span style="color:#27ae60;">Gratuite</span>
            </div>
            <div class="recap-total">
                <span>Total</span>
                <strong><?= number_format($total, 2, ',', ' ') ?> <?= param('devise', '€') ?></strong>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="valider_commande.php" class="btn-primary"
                   style="display:block;text-align:center;margin-top:20px;width:100%;padding:15px;">
                    ✅ Valider ma commande
                </a>
            <?php else: ?>
                <a href="login.php?redirect=panier.php" class="btn-primary"
                   style="display:block;text-align:center;margin-top:20px;width:100%;padding:15px;">
                    🔐 Se connecter pour commander
                </a>
            <?php endif; ?>

            <a href="index.php" class="btn-secondary"
               style="display:block;text-align:center;margin-top:10px;width:100%;padding:12px;">
                ← Continuer mes achats
            </a>
        </aside>
    </div>
</form>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>