<?php

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// valider_commande.php
require_once 'config/init.php';
require_once 'config/panier.php';

// Vérifications
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=valider_commande.php");
    exit;
}

$panier_data = panier_contenu($pdo);
$articles = $panier_data['articles'];
$total = $panier_data['total'];
$erreurs = $panier_data['erreurs'];

if (empty($articles)) {
    header("Location: panier.php");
    exit;
}

// Bloquer si erreurs de stock
if (!empty($erreurs)) {
    header("Location: panier.php");
    exit;
}

$message = '';

// ==========================================
// TRAITEMENT DE LA COMMANDE
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adresse = trim($_POST['adresse_livraison'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $code_postal = trim($_POST['code_postal'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if (empty($adresse) || empty($ville) || empty($code_postal) || empty($telephone)) {
        $message = "Tous les champs obligatoires doivent être remplis.";
    } elseif (strlen($adresse) < 10) {
        $message = "L'adresse doit contenir au moins 10 caractères.";
    } elseif (!preg_match('/^[0-9A-Za-z\s\-]{3,20}$/', $code_postal)) {
        $message = "Code postal invalide.";
    } elseif (!preg_match('/^[0-9+\s\(\)\-\.]{8,30}$/', $telephone)) {
        $message = "Numéro de téléphone invalide.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1) Générer un numéro de commande unique
            $numero = 'CMD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            // 2) Insérer la commande
            $stmt = $pdo->prepare("
                INSERT INTO commandes
                    (numero, client_id, total, statut, adresse_livraison, ville, code_postal, telephone, notes)
                VALUES (?, ?, ?, 'confirmee', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $numero,
                $_SESSION['user_id'],
                $total,
                $adresse,
                $ville,
                $code_postal,
                $telephone,
                $notes ?: null
            ]);
            $commande_id = (int) $pdo->lastInsertId();

            // 3) Insérer les lignes + décrémenter le stock
            $stmt_ligne = $pdo->prepare("
                INSERT INTO commande_lignes
                    (commande_id, produit_id, nom_produit, prix_unitaire, quantite, sous_total)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt_stock = $pdo->prepare("UPDATE produits SET stock = stock - ? WHERE id = ? AND stock >= ?");

            // === DEBUG TEMPORAIRE ===
            error_log("=== COMMANDE EN COURS ===");
            foreach ($articles as $a) {
                error_log("Article: id={$a['id']}, nom={$a['nom']}, qte={$a['quantite']}, stock_avant={$a['stock']}");
            }
            // === FIN DEBUG ===

            foreach ($articles as $a) {
                // Ligne de commande
                $stmt_ligne->execute([
                    $commande_id,
                    $a['id'],
                    $a['nom'],
                    $a['prix'],
                    $a['quantite'],
                    $a['sous_total']
                ]);

                // Décrémenter le stock
                $stmt_stock->execute([$a['quantite'], $a['id'], $a['quantite']]);
                error_log("UPDATE stock : rowCount = " . $stmt_stock->rowCount() . " pour produit_id={$a['id']}, qte={$a['quantite']}");

                if ($stmt_stock->rowCount() === 0) {
                    throw new Exception("Stock insuffisant pour « {$a['nom']} ».");
                }
            }

            // 4) Journal
            $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], 'COMMANDE', "Commande $numero — Total : $total €"]);

            $pdo->commit();

            // 5) Vider le panier et rediriger
            panier_vider();
            $_SESSION['derniere_commande'] = $commande_id;
            header("Location: confirmation_commande.php?id=$commande_id");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            $message = "Erreur lors de la commande : " . $e->getMessage();
        }
    }
}

// Notifier le vendeur (1 par produit)
$vendeurs_notifies = [];
foreach ($articles as $a) {
    $stmt = $pdo->prepare("SELECT vendeur_id, nom FROM produits WHERE id = ?");
    $stmt->execute([$a['id']]);
    $p = $stmt->fetch();
    if ($p && $p['vendeur_id'] && !in_array($p['vendeur_id'], $vendeurs_notifies)) {
        notifier(
            $pdo,
            $p['vendeur_id'],
            'commande',
            "Nouvelle commande $numero contenant : {$p['nom']}",
            "vandeur/mes_commandes.php"
        );
        $vendeurs_notifies[] = $p['vendeur_id'];
    }
}
// Notifier l'admin
notifier($pdo, 1, 'commande', "Nouvelle commande $numero — Total : $total €", "admin/orders.php");

// Pré-remplir avec les infos du client
$stmt = $pdo->prepare("SELECT nom, email FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$client = $stmt->fetch();

include 'includes/header.php';
?>

<nav class="breadcrumb">
    <a href="index.php">🏠 Accueil</a>
    <span class="breadcrumb-sep">›</span>
    <a href="panier.php">Panier</a>
    <span class="breadcrumb-sep">›</span>
    <span class="breadcrumb-current">Validation</span>
</nav>

<h1 style="margin-bottom:25px;">✅ Validation de la commande</h1>

<?php if ($message): ?>
    <div class="alert alert-error"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="POST" action="valider_commande.php">
    <div class="panier-layout">
        <!-- Formulaire livraison -->
        <div class="panier-articles">
            <section class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <h2>📦 Adresse de livraison</h2>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group form-group-full">
                            <label for="adresse_livraison">Adresse complète *</label>
                            <textarea id="adresse_livraison" name="adresse_livraison" rows="3" required
                                placeholder="N° et rue, bâtiment, appartement..."><?= htmlspecialchars($_POST['adresse_livraison'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="code_postal">Code postal *</label>
                            <input type="text" id="code_postal" name="code_postal" required
                                value="<?= htmlspecialchars($_POST['code_postal'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="ville">Ville *</label>
                            <input type="text" id="ville" name="ville" required
                                value="<?= htmlspecialchars($_POST['ville'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="telephone">Téléphone *</label>
                            <input type="tel" id="telephone" name="telephone" required placeholder="+33 6 12 34 56 78"
                                value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
                        </div>

                        <div class="form-group form-group-full">
                            <label for="notes">Notes (optionnel)</label>
                            <textarea id="notes" name="notes" rows="2"
                                placeholder="Instructions de livraison, code d'accès..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-header">
                    <h2>🛍️ Articles commandés</h2>
                </div>
                <div class="card-body">
                    <?php foreach ($articles as $a): ?>
                        <div class="panier-item" style="border-bottom:1px solid #ecf0f1;padding:12px 0;">
                            <div class="panier-item-image">📦</div>
                            <div class="panier-item-info">
                                <strong><?= htmlspecialchars($a['nom']) ?></strong>
                                <p class="text-muted" style="font-size:0.85rem;">
                                    <?= $a['quantite'] ?> × <?= number_format($a['prix'], 2, ',', ' ') ?>
                                    <?= param('devise', '€') ?>
                                </p>
                            </div>
                            <div class="panier-item-soustotal">
                                <strong><?= number_format($a['sous_total'], 2, ',', ' ') ?>
                                    <?= param('devise', '€') ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
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

            <button type="submit" class="btn-primary" style="display:block;width:100%;padding:15px;margin-top:20px;">
                ✅ Confirmer la commande
            </button>

            <a href="panier.php" class="btn-secondary"
                style="display:block;text-align:center;margin-top:10px;width:100%;padding:12px;">
                ← Retour au panier
            </a>
        </aside>
    </div>
</form>

<?php include 'includes/footer.php'; ?>