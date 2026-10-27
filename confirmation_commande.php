<?php
// confirmation_commande.php
require_once 'config/init.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

// Charger la commande (uniquement si elle appartient au client connecté, sauf admin)
$sql = "SELECT * FROM commandes WHERE id = ?";
$params = [$id];
if ($_SESSION['role'] !== 'admin') {
    $sql .= " AND client_id = ?";
    $params[] = $_SESSION['user_id'];
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$commande = $stmt->fetch();

if (!$commande) {
    header("Location: index.php");
    exit;
}

// Charger les lignes
$stmt = $pdo->prepare("SELECT * FROM commande_lignes WHERE commande_id = ?");
$stmt->execute([$id]);
$lignes = $stmt->fetchAll();

include 'includes/header.php';
?>

<div style="max-width:800px;margin:40px auto;padding:0 20px;">
    <div style="text-align:center;margin-bottom:30px;">
        <div style="font-size:4rem;">🎉</div>
        <h1 style="color:#27ae60;margin:10px 0;">Commande confirmée !</h1>
        <p style="color:#7f8c8d;">Merci pour votre achat. Votre commande a bien été enregistrée.</p>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>📋 Commande <?= htmlspecialchars($commande['numero']) ?></h2>
        </div>
        <div class="card-body">
            <div class="info-grid" style="margin-bottom:20px;">
                <div class="info-item">
                    <p class="info-label">Statut</p>
                    <p class="info-value">
                        <span class="badge badge-success">✅ Confirmée</span>
                    </p>
                </div>
                <div class="info-item">
                    <p class="info-label">Date</p>
                    <p class="info-value"><?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></p>
                </div>
                <div class="info-item">
                    <p class="info-label">Total</p>
                    <p class="info-value" style="color:#27ae60;font-size:1.2rem;">
                        <?= number_format($commande['total'], 2, ',', ' ') ?> <?= param('devise', '€') ?>
                    </p>
                </div>
            </div>

            <h3 style="margin:20px 0 10px;">📦 Livraison</h3>
            <p style="color:#555;line-height:1.6;">
                <?= nl2br(htmlspecialchars($commande['adresse_livraison'])) ?><br>
                <?= htmlspecialchars($commande['code_postal']) ?> <?= htmlspecialchars($commande['ville']) ?><br>
                📞 <?= htmlspecialchars($commande['telephone']) ?>
            </p>

            <?php if ($commande['notes']): ?>
                <h3 style="margin:20px 0 10px;">📝 Notes</h3>
                <p style="color:#555;"><?= nl2br(htmlspecialchars($commande['notes'])) ?></p>
            <?php endif; ?>

            <h3 style="margin:20px 0 10px;">🛍️ Articles</h3>
            <div class="table-responsive">
            <table class="data-table order-table order-table-items">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Prix unitaire</th>
                        <th>Qté</th>
                        <th>Sous-total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lignes as $l): ?>
                        <tr>
                            <td><?= htmlspecialchars($l['nom_produit']) ?></td>
                            <td><?= number_format($l['prix_unitaire'], 2, ',', ' ') ?> <?= param('devise', '€') ?></td>
                            <td><?= $l['quantite'] ?></td>
                            <td><?= number_format($l['sous_total'], 2, ',', ' ') ?> <?= param('devise', '€') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:15px;margin-top:30px;flex-wrap:wrap;justify-content:center;">
        <a href="mes_commandes.php" class="btn-primary">📦 Voir mes commandes</a>
        <a href="index.php" class="btn-secondary">← Continuer mes achats</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>