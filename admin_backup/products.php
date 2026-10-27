<?php
// admin/products.php
require_once '../config/init.php';
require_once '../includes/auth.php';

// ==========================================
// TRAITEMENT DES ACTIONS (AVANT tout HTML)
// ==========================================
$message = '';
$type_message = 'success';

// --- INSERTION (Critère 11) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix = (float)($_POST['prix'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $categorie_id = (int)($_POST['categorie_id'] ?? 0) ?: null;

    if ($nom === '' || $prix <= 0) {
        $message = "Le nom et un prix valide sont obligatoires.";
        $type_message = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO produits (nom, description, prix, stock, categorie_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $description, $prix, $stock, $categorie_id]);
            $message = "Produit « " . htmlspecialchars($nom) . " » ajouté avec succès.";
            // Journal (Critère 10)
            $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], 'AJOUT_PRODUIT', "Ajout du produit : $nom"]);
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $type_message = 'error';
        }
    }
}

// --- MODIFICATION (Critère 12) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier') {
    $id = (int)($_POST['id'] ?? 0);
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix = (float)($_POST['prix'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $categorie_id = (int)($_POST['categorie_id'] ?? 0) ?: null;

    if ($id <= 0 || $nom === '' || $prix <= 0) {
        $message = "Données invalides.";
        $type_message = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE produits SET nom=?, description=?, prix=?, stock=?, categorie_id=? WHERE id=?");
            $stmt->execute([$nom, $description, $prix, $stock, $categorie_id, $id]);
            $message = "Produit modifié avec succès.";
            $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], 'MODIF_PRODUIT', "Modification du produit #$id"]);
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $type_message = 'error';
        }
    }
}

// --- SUPPRESSION (Critère 13) ---
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    try {
        $stmt = $pdo->prepare("DELETE FROM produits WHERE id=?");
        $stmt->execute([$id]);
        $message = "Produit supprimé définitivement.";
        $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
            ->execute([$_SESSION['user_id'], 'SUPPR_PRODUIT', "Suppression du produit #$id"]);
    } catch (PDOException $e) {
        $message = "Erreur : " . $e->getMessage();
        $type_message = 'error';
    }
}

// --- ARCHIVAGE (Critère 18) ---
if (isset($_GET['archiver'])) {
    $id = (int)$_GET['archiver'];
    $pdo->prepare("UPDATE produits SET archive = 1 WHERE id = ?")->execute([$id]);
    $message = "Produit archivé.";
    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'ARCHIVE_PRODUIT', "Archivage du produit #$id"]);
}

// --- RESTAURATION (Critère 19) ---
if (isset($_GET['restaurer'])) {
    $id = (int)$_GET['restaurer'];
    $pdo->prepare("UPDATE produits SET archive = 0 WHERE id = ?")->execute([$id]);
    $message = "Produit restauré.";
    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'RESTAURE_PRODUIT', "Restauration du produit #$id"]);
}

// ==========================================
// CONSULTATION avec recherche/filtrage/tri (Critères 14-17)
// ==========================================
$recherche = trim($_GET['recherche'] ?? '');
$filtre_categorie = (int)($_GET['categorie'] ?? 0);
$tri = $_GET['tri'] ?? 'recent';
$filtre_archive = isset($_GET['archive']) ? (int)$_GET['archive'] : 0;

$sql = "SELECT p.*, c.nom AS categorie FROM produits p LEFT JOIN categories c ON p.categorie_id = c.id WHERE p.archive = :archive";
$params = [':archive' => $filtre_archive];

if ($recherche !== '') {
    $sql .= " AND (p.nom LIKE :rech OR p.description LIKE :rech)";
    $params[':rech'] = "%$recherche%";
}
if ($filtre_categorie > 0) {
    $sql .= " AND p.categorie_id = :cat";
    $params[':cat'] = $filtre_categorie;
}

switch ($tri) {
    case 'prix_asc':  $sql .= " ORDER BY p.prix ASC"; break;
    case 'prix_desc': $sql .= " ORDER BY p.prix DESC"; break;
    case 'nom_asc':   $sql .= " ORDER BY p.nom ASC"; break;
    case 'stock_asc': $sql .= " ORDER BY p.stock ASC"; break;
    default:          $sql .= " ORDER BY p.cree_le DESC"; break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produits = $stmt->fetchAll();

// Catégories pour le formulaire et le filtre
$categories = $pdo->query("SELECT id, nom FROM categories WHERE archive = 0 ORDER BY nom")->fetchAll();

// Produit à modifier (si demandé)
$produit_edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $produit_edit = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair'), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des produits - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="admin-body">

<!-- Sidebar (identique au dashboard) -->
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="admin-main">

    <header class="admin-topbar">
        <div>
            <h1>📦 Gestion des produits</h1>
            <p class="topbar-subtitle">Ajouter, modifier, rechercher et archiver vos produits</p>
        </div>
        <div class="topbar-actions">
            <a href="dashboard.php" class="topbar-icon" title="Retour au dashboard">📊</a>
            <div class="topbar-user">
                <span class="avatar"><?= strtoupper(substr($_SESSION['nom'], 0, 1)) ?></span>
                <span><?= htmlspecialchars($_SESSION['nom']) ?></span>
            </div>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert alert-<?= $type_message === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- ==========================================
         FORMULAIRE AJOUT / MODIFICATION (Critères 11-12)
         ========================================== -->
    <section class="card">
        <div class="card-header">
            <h2><?= $produit_edit ? '✏️ Modifier le produit' : '➕ Ajouter un produit' ?></h2>
            <?php if ($produit_edit): ?>
                <a href="products.php" class="card-link">Annuler la modification</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="products.php" class="form-grid">
                <input type="hidden" name="action" value="<?= $produit_edit ? 'modifier' : 'ajouter' ?>">
                <?php if ($produit_edit): ?>
                    <input type="hidden" name="id" value="<?= (int)$produit_edit['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="nom">Nom du produit *</label>
                    <input type="text" id="nom" name="nom" required
                           value="<?= htmlspecialchars($produit_edit['nom'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="prix">Prix (<?= htmlspecialchars(param('devise', '€')) ?>) *</label>
                    <input type="number" id="prix" name="prix" step="0.01" min="0.01" required
                           value="<?= htmlspecialchars($produit_edit['prix'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="stock">Stock</label>
                    <input type="number" id="stock" name="stock" min="0"
                           value="<?= htmlspecialchars($produit_edit['stock'] ?? 0) ?>">
                </div>

                <div class="form-group">
                    <label for="categorie_id">Catégorie</label>
                    <select id="categorie_id" name="categorie_id">
                        <option value="">— Aucune —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                <?= (isset($produit_edit['categorie_id']) && $produit_edit['categorie_id'] == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group form-group-full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"><?= htmlspecialchars($produit_edit['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group form-group-full">
                    <button type="submit" class="btn-submit">
                        <?= $produit_edit ? '💾 Enregistrer les modifications' : '➕ Ajouter le produit' ?>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- ==========================================
         RECHERCHE / FILTRE / TRI (Critères 15-17)
         ========================================== -->
    <section class="card">
        <div class="card-header">
            <h2>🔍 Recherche & filtres</h2>
        </div>
        <div class="card-body">
            <form method="GET" action="products.php" class="filters-bar">
                <input type="text" name="recherche" placeholder="Rechercher un produit..."
                       value="<?= htmlspecialchars($recherche) ?>">

                <select name="categorie">
                    <option value="">Toutes catégories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $filtre_categorie == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="tri">
                    <option value="recent"    <?= $tri === 'recent'    ? 'selected' : '' ?>>Plus récents</option>
                    <option value="nom_asc"   <?= $tri === 'nom_asc'   ? 'selected' : '' ?>>Nom (A→Z)</option>
                    <option value="prix_asc"  <?= $tri === 'prix_asc'  ? 'selected' : '' ?>>Prix croissant</option>
                    <option value="prix_desc" <?= $tri === 'prix_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                    <option value="stock_asc" <?= $tri === 'stock_asc' ? 'selected' : '' ?>>Stock croissant</option>
                </select>

                <select name="archive">
                    <option value="0" <?= $filtre_archive === 0 ? 'selected' : '' ?>>Produits actifs</option>
                    <option value="1" <?= $filtre_archive === 1 ? 'selected' : '' ?>>Produits archivés</option>
                </select>

                <button type="submit" class="btn-submit">Filtrer</button>
                <a href="products.php" class="btn-secondary">Réinitialiser</a>
            </form>
        </div>
    </section>

    <!-- ==========================================
         LISTE DES PRODUITS (Critères 14, 17, 18, 19)
         ========================================== -->
    <section class="card">
        <div class="card-header">
            <h2>📋 Liste des produits (<?= count($produits) ?>)</h2>
        </div>
        <div class="card-body">
            <?php if (empty($produits)): ?>
                <p class="empty">Aucun produit trouvé.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Stock</th>
                            <th>Créé le</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produits as $p): ?>
                            <tr>
                                <td>#<?= (int)$p['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($p['nom']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars(mb_substr($p['description'] ?? '', 0, 50)) ?></small>
                                </td>
                                <td><?= htmlspecialchars($p['categorie'] ?? '—') ?></td>
                                <td><?= number_format($p['prix'], 2, ',', ' ') ?> <?= htmlspecialchars(param('devise', '€')) ?></td>
                                <td>
                                    <span class="badge <?= $p['stock'] < 5 ? 'badge-danger' : 'badge-success' ?>">
                                        <?= (int)$p['stock'] ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($p['cree_le'])) ?></td>
                                <td class="actions-cell">
                                    <?php if ($filtre_archive === 1): ?>
                                        <a href="products.php?restaurer=<?= (int)$p['id'] ?>" class="btn-icon" title="Restaurer">♻️</a>
                                    <?php else: ?>
                                        <a href="products.php?edit=<?= (int)$p['id'] ?>" class="btn-icon" title="Modifier">✏️</a>
                                        <a href="products.php?archiver=<?= (int)$p['id'] ?>" class="btn-icon" title="Archiver"
                                           onclick="return confirm('Archiver ce produit ?');">📥</a>
                                    <?php endif; ?>
                                    <a href="products.php?supprimer=<?= (int)$p['id'] ?>" class="btn-icon btn-danger" title="Supprimer"
                                       onclick="return confirm('Supprimer définitivement ce produit ? Cette action est irréversible.');">🗑️</a>
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