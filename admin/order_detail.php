<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// admin/order_detail.php
require_once '../config/init.php';
require_once '../includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: orders.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.*, u.nom AS client, u.email
    FROM commandes c
    LEFT JOIN utilisateurs u ON c.client_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$commande = $stmt->fetch();

if (!$commande) {
    header("Location: orders.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM commande_lignes WHERE commande_id = ?");
$stmt->execute([$id]);
$lignes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair')) ?>">
<head>
    <meta charset="UTF-8">
    <title>Commande <?= htmlspecialchars($commande['numero']) ?> - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="admin-main">

    <header class="admin-topbar">
        <div>
            <h1>🛒 Commande <?= htmlspecialchars($commande['numero']) ?></h1>
            <p class="topbar-subtitle">
                Passée le <?= date('d/m/Y à H:i', strtotime($commande['date_commande'])) ?>
            </p>
        </div>
        <div class="topbar-actions">
            <a href="orders.php" class="btn-secondary">← Retour</a>
        </div>
    </header>

    <div class="order-detail-grid">
        <!-- Articles -->
        <section class="card">
            <div class="card-header">
                <h2>🛍️ Articles commandés (<?= count($lignes) ?>)</h2>
            </div>
            <div class="card-body">
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
                                <td><strong><?= number_format($l['sous_total'], 2, ',', ' ') ?> <?= param('devise', '€') ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <div style="text-align:right;margin-top:20px;padding-top:20px;border-top:2px solid #ecf0f1;">
                    <p style="color:#7f8c8d;font-size:0.9rem;">Total de la commande</p>
                    <p style="font-size:1.8rem;font-weight:700;color:#27ae60;">
                        <?= number_format($commande['total'], 2, ',', ' ') ?> <?= param('devise', '€') ?>
                    </p>
                </div>
            </div>
        </section>

        <!-- Infos client & livraison -->
        <aside>
            <section class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <h2>👤 Client</h2>
                </div>
                <div class="card-body">
                    <p><strong><?= htmlspecialchars($commande['client'] ?? 'Inconnu') ?></strong></p>
                    <p class="text-muted" style="font-size:0.9rem;"><?= htmlspecialchars($commande['email'] ?? '') ?></p>
                    <?php if ($commande['telephone']): ?>
                        <p class="text-muted" style="font-size:0.9rem;">📞 <?= htmlspecialchars($commande['telephone']) ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <h2>📦 Livraison</h2>
                </div>
                <div class="card-body">
                    <p><?= nl2br(htmlspecialchars($commande['adresse_livraison'])) ?></p>
                    <p><strong><?= htmlspecialchars($commande['code_postal']) ?> <?= htmlspecialchars($commande['ville']) ?></strong></p>
                    <p>📞 <?= htmlspecialchars($commande['telephone']) ?></p>
                    <?php if ($commande['notes']): ?>
                        <hr style="margin:15px 0;border:none;border-top:1px solid #ecf0f1;">
                        <p class="text-muted" style="font-size:0.9rem;"><?= nl2br(htmlspecialchars($commande['notes'])) ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card">
                <div class="card-header">
                    <h2>📊 Statut</h2>
                </div>
                <div class="card-body">
                    <?php
                    $badge = match($commande['statut']) {
                        'en_attente' => 'badge-warning',
                        'confirmee'  => 'badge-info',
                        'expediee'   => 'badge-info',
                        'livree'     => 'badge-success',
                        'annulee'    => 'badge-danger',
                        default      => 'badge-info'
                    };
                    ?>
                    <p style="text-align:center;margin-bottom:15px;">
                        <span class="badge <?= $badge ?>" style="font-size:1rem;padding:8px 16px;">
                            <?= ucfirst(str_replace('_', ' ', $commande['statut'])) ?>
                        </span>
                    </p>
                    <form method="POST" action="orders.php">
                        <input type="hidden" name="commande_id" value="<?= $commande['id'] ?>">
                        <input type="hidden" name="changer_statut" value="1">
                        <select name="statut" class="statut-select" style="margin-bottom:10px;">
                            <?php foreach (['en_attente','confirmee','expediee','livree','annulee'] as $s): ?>
                                <option value="<?= $s ?>" <?= $commande['statut'] === $s ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $s)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-submit" style="width:100%;">💾 Mettre à jour</button>
                    </form>
                </div>
            </section>
        </aside>
    </div>

</main>

</body>
</html>