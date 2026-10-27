<?php
// vandeur/mes_produits.php
require_once '../config/init.php';
require_once '../includes/auth_vendeur.php';

$message = '';
$type_message = 'success';

// Actions
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $stmt = $pdo->prepare("DELETE FROM produits WHERE id = ? AND vendeur_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    if ($stmt->rowCount() > 0) {
        $message = "Produit supprimé.";
    }
}

if (isset($_GET['archiver'])) {
    $id = (int)$_GET['archiver'];
    $pdo->prepare("UPDATE produits SET archive = 1 WHERE id = ? AND vendeur_id = ?")
        ->execute([$id, $_SESSION['user_id']]);
    $message = "Produit archivé.";
}

if (isset($_GET['restaurer'])) {
    $id = (int)$_GET['restaurer'];
    $pdo->prepare("UPDATE produits SET archive = 0 WHERE id = ? AND vendeur_id = ?")
        ->execute([$id, $_SESSION['user_id']]);
    $message = "Produit restauré.";
}

// Filtres
$recherche = trim($_GET['recherche'] ?? '');
$filtre_archive = (int)($_GET['archive'] ?? 0);

$sql = "SELECT p.*, c.nom AS categorie
        FROM produits p
        LEFT JOIN categories c ON p.categorie_id = c.id
        WHERE p.vendeur_id = ? AND p.archive = ?";
$params = [$_SESSION['user_id'], $filtre_archive];

if ($recherche !== '') {
    $sql .= " AND p.nom LIKE ?";
    $params[] = "%$recherche%";
}
$sql .= " ORDER BY p.cree_le DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produits = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair')) ?>">
<head>
    <meta charset="UTF-8">
    <title>Mes produits - Vendeur</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../includes/sidebar_vendeur.php'; ?>

<main class="admin-main">
    <header class="admin-topbar">
        <div>
            <h1>📦 Mes produits</h1>
            <p class="topbar-subtitle">Gérez votre catalogue</p>
        </div>
        <div class="topbar-actions">
            <a href="ajouter_produit.php" class="btn-submit" style="width:auto;padding:12px 22px;">➕ Nouveau produit</a>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert alert-<?= $type_message === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <div class="card-header">
            <h2>🔍 Filtres</h2>
        </div>
        <div class="card-body">
            <form method="GET" action="mes_produits.php" class="filters-bar">
                <input type="text" name="recherche" placeholder="Rechercher..."
                       value="<?= htmlspecialchars($recherche) ?>">
                <select name="archive">
                    <option value="0" <?= $filtre_archive === 0 ? 'selected' : '' ?>>Actifs</option>
                    <option value="1" <?= $filtre_archive === 1 ? 'selected' : '' ?>>Archivés</option>
                </select>
                <button type="submit" class="btn-submit">Filtrer</button>
                <a href="mes_produits.php" class="btn-secondary">Réinitialiser</a>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>📋 Liste (<?= count($produits) ?>)</h2>
        </div>
        <div class="card-body">
            <?php if (empty($produits)): ?>
                <p class="empty">Aucun produit. <a href="ajouter_produit.php" class="link">Ajouter mon premier produit</a></p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Stock</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produits as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['nom']) ?></strong></td>
                                <td><?= htmlspecialchars($p['categorie'] ?? '—') ?></td>
                                <td><?= number_format($p['prix'], 2, ',', ' ') ?> <?= param('devise', '€') ?></td>
                                <td>
                                    <span class="badge <?= $p['stock'] < 5 ? 'badge-danger' : 'badge-success' ?>">
                                        <?= $p['stock'] ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($p['cree_le'])) ?></td>
                                <td class="actions-cell">
                                    <?php if ($filtre_archive === 1): ?>
                                        <a href="?restaurer=<?= $p['id'] ?>" class="btn-icon" title="Restaurer">♻️</a>
                                    <?php else: ?>
                                        <a href="?archiver=<?= $p['id'] ?>" class="btn-icon" title="Archiver"
                                           onclick="return confirm('Archiver ce produit ?');">📥</a>
                                    <?php endif; ?>
                                    <a href="?supprimer=<?= $p['id'] ?>" class="btn-icon btn-danger" title="Supprimer"
                                       onclick="return confirm('Supprimer définitivement ?');">🗑️</a>
                                </td>
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