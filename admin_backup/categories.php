<?php
// admin/categories.php
require_once '../config/init.php';
require_once '../includes/auth.php';

// ==========================================
// TRAITEMENT DES ACTIONS (AVANT tout HTML)
// ==========================================
$message = '';
$type_message = 'success';

// --- INSERTION (Critère 11) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ajouter') {
    $nom = trim($_POST['nom'] ?? '');

    if ($nom === '') {
        $message = "Le nom de la catégorie est obligatoire.";
        $type_message = 'error';
    } elseif (mb_strlen($nom) < 2) {
        $message = "Le nom doit contenir au moins 2 caractères.";
        $type_message = 'error';
    } else {
        try {
            // Vérifier doublon (Critère 15)
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE nom = ?");
            $stmt->execute([$nom]);
            if ($stmt->fetch()) {
                $message = "Une catégorie porte déjà ce nom.";
                $type_message = 'error';
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (nom) VALUES (?)");
                $stmt->execute([$nom]);
                $message = "Catégorie « " . htmlspecialchars($nom) . " » ajoutée.";
                $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                    ->execute([$_SESSION['user_id'], 'AJOUT_CATEGORIE', "Ajout : $nom"]);
            }
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $type_message = 'error';
        }
    }
}

// --- MODIFICATION (Critère 12) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'modifier') {
    $id = (int)($_POST['id'] ?? 0);
    $nom = trim($_POST['nom'] ?? '');

    if ($id <= 0 || $nom === '') {
        $message = "Données invalides.";
        $type_message = 'error';
    } else {
        try {
            // Vérifier doublon (hors soi-même)
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE nom = ? AND id != ?");
            $stmt->execute([$nom, $id]);
            if ($stmt->fetch()) {
                $message = "Une autre catégorie porte déjà ce nom.";
                $type_message = 'error';
            } else {
                $stmt = $pdo->prepare("UPDATE categories SET nom = ? WHERE id = ?");
                $stmt->execute([$nom, $id]);
                $message = "Catégorie modifiée avec succès.";
                $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                    ->execute([$_SESSION['user_id'], 'MODIF_CATEGORIE', "Modification de la catégorie #$id"]);
            }
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
        // Vérifier si des produits y sont liés
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE categorie_id = ?");
        $stmt->execute([$id]);
        $nb_produits = $stmt->fetchColumn();

        if ($nb_produits > 0) {
            $message = "Impossible de supprimer : $nb_produits produit(s) lié(s). Archivez ou réaffectez-les d'abord.";
            $type_message = 'error';
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Catégorie supprimée définitivement.";
            $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], 'SUPPR_CATEGORIE', "Suppression de la catégorie #$id"]);
        }
    } catch (PDOException $e) {
        $message = "Erreur : " . $e->getMessage();
        $type_message = 'error';
    }
}

// --- ARCHIVAGE (Critère 18) ---
if (isset($_GET['archiver'])) {
    $id = (int)$_GET['archiver'];
    $pdo->prepare("UPDATE categories SET archive = 1 WHERE id = ?")->execute([$id]);
    $message = "Catégorie archivée.";
    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'ARCHIVE_CATEGORIE', "Archivage de la catégorie #$id"]);
}

// --- RESTAURATION (Critère 19) ---
if (isset($_GET['restaurer'])) {
    $id = (int)$_GET['restaurer'];
    $pdo->prepare("UPDATE categories SET archive = 0 WHERE id = ?")->execute([$id]);
    $message = "Catégorie restaurée.";
    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'RESTAURE_CATEGORIE', "Restauration de la catégorie #$id"]);
}

// ==========================================
// CONSULTATION avec recherche/tri (Critères 14-17)
// ==========================================
$recherche = trim($_GET['recherche'] ?? '');
$tri = $_GET['tri'] ?? 'nom_asc';
$filtre_archive = isset($_GET['archive']) ? (int)$_GET['archive'] : 0;

$sql = "SELECT c.id, c.nom, c.archive,
               (SELECT COUNT(*) FROM produits p WHERE p.categorie_id = c.id AND p.archive = 0) AS nb_produits
        FROM categories c
        WHERE c.archive = :archive";
$params = [':archive' => $filtre_archive];

if ($recherche !== '') {
    $sql .= " AND c.nom LIKE :rech";
    $params[':rech'] = "%$recherche%";
}

switch ($tri) {
    case 'nom_desc':   $sql .= " ORDER BY c.nom DESC"; break;
    case 'nb_asc':     $sql .= " ORDER BY nb_produits ASC"; break;
    case 'nb_desc':    $sql .= " ORDER BY nb_produits DESC"; break;
    case 'id_desc':    $sql .= " ORDER BY c.id DESC"; break;
    default:           $sql .= " ORDER BY c.nom ASC"; break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();

// Catégorie à modifier
$categorie_edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $categorie_edit = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair'), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des catégories - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="admin-body">

<!-- Sidebar (identique) -->
<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="admin-main">

    <header class="admin-topbar">
        <div>
            <h1>🏷️ Gestion des catégories</h1>
            <p class="topbar-subtitle">Organisez vos produits par catégories</p>
        </div>
        <div class="topbar-actions">
            <a href="dashboard.php" class="topbar-icon" title="Dashboard">📊</a>
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
         FORMULAIRE AJOUT / MODIFICATION
         ========================================== -->
    <section class="card">
        <div class="card-header">
            <h2><?= $categorie_edit ? '✏️ Modifier la catégorie' : '➕ Ajouter une catégorie' ?></h2>
            <?php if ($categorie_edit): ?>
                <a href="categories.php" class="card-link">Annuler la modification</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="categories.php" class="form-grid">
                <input type="hidden" name="action" value="<?= $categorie_edit ? 'modifier' : 'ajouter' ?>">
                <?php if ($categorie_edit): ?>
                    <input type="hidden" name="id" value="<?= (int)$categorie_edit['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="nom">Nom de la catégorie *</label>
                    <input type="text" id="nom" name="nom" required minlength="2"
                           placeholder="Ex. : Électronique, Vêtements..."
                           value="<?= htmlspecialchars($categorie_edit['nom'] ?? '') ?>">
                </div>

                <div class="form-group" style="display:flex; align-items:flex-end;">
                    <button type="submit" class="btn-submit" style="width:100%;">
                        <?= $categorie_edit ? '💾 Enregistrer' : '➕ Ajouter' ?>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- ==========================================
         RECHERCHE / FILTRE / TRI
         ========================================== -->
    <section class="card">
        <div class="card-header">
            <h2>🔍 Recherche & filtres</h2>
        </div>
        <div class="card-body">
            <form method="GET" action="categories.php" class="filters-bar">
                <input type="text" name="recherche" placeholder="Rechercher une catégorie..."
                       value="<?= htmlspecialchars($recherche) ?>">

                <select name="tri">
                    <option value="nom_asc"  <?= $tri === 'nom_asc'  ? 'selected' : '' ?>>Nom (A→Z)</option>
                    <option value="nom_desc" <?= $tri === 'nom_desc' ? 'selected' : '' ?>>Nom (Z→A)</option>
                    <option value="nb_desc"  <?= $tri === 'nb_desc'  ? 'selected' : '' ?>>Plus de produits</option>
                    <option value="nb_asc"   <?= $tri === 'nb_asc'   ? 'selected' : '' ?>>Moins de produits</option>
                    <option value="id_desc"  <?= $tri === 'id_desc'  ? 'selected' : '' ?>>Plus récentes</option>
                </select>

                <select name="archive">
                    <option value="0" <?= $filtre_archive === 0 ? 'selected' : '' ?>>Actives</option>
                    <option value="1" <?= $filtre_archive === 1 ? 'selected' : '' ?>>Archivées</option>
                </select>

                <button type="submit" class="btn-submit">Filtrer</button>
                <a href="categories.php" class="btn-secondary">Réinitialiser</a>
            </form>
        </div>
    </section>

    <!-- ==========================================
         LISTE DES CATÉGORIES
         ========================================== -->
    <section class="card">
        <div class="card-header">
            <h2>📋 Liste des catégories (<?= count($categories) ?>)</h2>
        </div>
        <div class="card-body">
            <?php if (empty($categories)): ?>
                <p class="empty">Aucune catégorie trouvée.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Produits actifs</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td>#<?= (int)$c['id'] ?></td>
                                <td><strong><?= htmlspecialchars($c['nom']) ?></strong></td>
                                <td>
                                    <span class="badge <?= $c['nb_produits'] > 0 ? 'badge-success' : 'badge-danger' ?>">
                                        <?= (int)$c['nb_produits'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($c['archive']): ?>
                                        <span class="badge" style="background:#fdecea;color:#c0392b;">Archivée</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <?php if ($filtre_archive === 1): ?>
                                        <a href="categories.php?restaurer=<?= (int)$c['id'] ?>" class="btn-icon" title="Restaurer">♻️</a>
                                    <?php else: ?>
                                        <a href="categories.php?edit=<?= (int)$c['id'] ?>" class="btn-icon" title="Modifier">✏️</a>
                                        <a href="categories.php?archiver=<?= (int)$c['id'] ?>" class="btn-icon" title="Archiver"
                                           onclick="return confirm('Archiver cette catégorie ?');">📥</a>
                                    <?php endif; ?>
                                    <a href="categories.php?supprimer=<?= (int)$c['id'] ?>" class="btn-icon btn-danger" title="Supprimer"
                                       onclick="return confirm('Supprimer définitivement cette catégorie ?');">🗑️</a>
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