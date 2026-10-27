<?php
// vandeur/dashboard.php
require_once '../config/init.php';
require_once '../includes/auth_vendeur.php';

// Statistiques du vendeur
$stats = [];
try {
    // Nombre de produits
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE vendeur_id = ? AND archive = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['produits'] = (int)$stmt->fetchColumn();

    // Valeur du stock
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(prix * stock), 0) FROM produits WHERE vendeur_id = ? AND archive = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['valeur_stock'] = (float)$stmt->fetchColumn();

    // Produits en rupture
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE vendeur_id = ? AND stock < 5 AND archive = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['rupture'] = (int)$stmt->fetchColumn();

    // Nombre de commandes contenant mes produits
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT cl.commande_id)
        FROM commande_lignes cl
        INNER JOIN produits p ON cl.produit_id = p.id
        WHERE p.vendeur_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['commandes'] = (int)$stmt->fetchColumn();

    // Chiffre d'affaires (mes produits)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(cl.sous_total), 0)
        FROM commande_lignes cl
        INNER JOIN produits p ON cl.produit_id = p.id
        INNER JOIN commandes c ON cl.commande_id = c.id
        WHERE p.vendeur_id = ? AND c.statut != 'annulee'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['ca'] = (float)$stmt->fetchColumn();

    // 5 derniers produits
    $stmt = $pdo->prepare("
        SELECT nom, prix, stock, cree_le FROM produits
        WHERE vendeur_id = ? AND archive = 0
        ORDER BY cree_le DESC LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $derniers_produits = $stmt->fetchAll();

} catch (PDOException $e) {
    $stats = ['produits'=>0,'valeur_stock'=>0,'rupture'=>0,'commandes'=>0,'ca'=>0];
    $derniers_produits = [];
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair')) ?>">
<head>
    <meta charset="UTF-8">
    <title>Espace Vendeur - Tableau de bord</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="admin-body vendor-dashboard-page">
<?php include __DIR__ . '/../includes/sidebar_vendeur.php'; ?>

<main class="admin-main">
    <header class="admin-topbar">
        <div>
            <h1>📊 Tableau de bord vendeur</h1>
            <p class="topbar-subtitle">Bienvenue, <?= htmlspecialchars($_SESSION['nom']) ?></p>
        </div>
        <div class="topbar-actions">
            <a href="ajouter_produit.php" class="btn-submit" style="width:auto;padding:12px 22px;">➕ Ajouter un produit</a>
        </div>
    </header>

    <section class="stats-grid vendor-dashboard-stats">
        <div class="stat-card stat-blue">
            <div class="stat-icon">📦</div>
            <div class="stat-content">
                <p class="stat-label">Mes produits</p>
                <h3 class="stat-value"><?= $stats['produits'] ?></h3>
            </div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <p class="stat-label">Chiffre d'affaires</p>
                <h3 class="stat-value" style="font-size:1.2rem;">
                    <?= number_format($stats['ca'], 2, ',', ' ') ?> <?= param('devise', '€') ?>
                </h3>
            </div>
        </div>
        <div class="stat-card stat-orange">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
                <p class="stat-label">Valeur du stock</p>
                <h3 class="stat-value" style="font-size:1.2rem;">
                    <?= number_format($stats['valeur_stock'], 2, ',', ' ') ?> <?= param('devise', '€') ?>
                </h3>
            </div>
        </div>
        <div class="stat-card stat-red">
            <div class="stat-icon">⚠️</div>
            <div class="stat-content">
                <p class="stat-label">Alertes stock</p>
                <h3 class="stat-value"><?= $stats['rupture'] ?></h3>
                <p class="stat-meta"><?= $stats['commandes'] ?> commande(s)</p>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>🆕 Mes derniers produits</h2>
            <a href="mes_produits.php" class="card-link">Tout voir →</a>
        </div>
        <div class="card-body">
            <?php if (empty($derniers_produits)): ?>
                <p class="empty">Aucun produit. <a href="ajouter_produit.php" class="link">Ajouter mon premier produit</a></p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prix</th>
                            <th>Stock</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($derniers_produits as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['nom']) ?></strong></td>
                                <td><?= number_format($p['prix'], 2, ',', ' ') ?> <?= param('devise', '€') ?></td>
                                <td>
                                    <span class="badge <?= $p['stock'] < 5 ? 'badge-danger' : 'badge-success' ?>">
                                        <?= $p['stock'] ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($p['cree_le'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>