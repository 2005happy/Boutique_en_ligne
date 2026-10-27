<?php
// vandeur/ajouter_produit.php
require_once '../config/init.php';
require_once '../includes/auth_vendeur.php';

$message = '';
$type_message = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix = (float)($_POST['prix'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $categorie_id = (int)($_POST['categorie_id'] ?? 0) ?: null;

    if ($nom === '' || $prix <= 0) {
        $message = "Le nom et un prix valide sont obligatoires.";
        $type_message = 'error';
    } elseif ($stock < 0) {
        $message = "Le stock ne peut pas être négatif.";
        $type_message = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO produits (nom, description, prix, stock, categorie_id, vendeur_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nom, $description, $prix, $stock, $categorie_id, $_SESSION['user_id']]);

            $message = "✅ Produit « " . htmlspecialchars($nom) . " » ajouté avec succès.";

            $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], 'AJOUT_PRODUIT_VENDEUR', "Produit : $nom"]);
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $type_message = 'error';
        }
    }
}

// Catégories disponibles
$categories = $pdo->query("SELECT id, nom FROM categories WHERE archive = 0 ORDER BY nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair')) ?>">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un produit - Vendeur</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../includes/sidebar_vendeur.php'; ?>

<main class="admin-main">
    <header class="admin-topbar">
        <div>
            <h1>➕ Ajouter un produit</h1>
            <p class="topbar-subtitle">Ajoutez une nouvelle marchandise à votre boutique</p>
        </div>
        <div class="topbar-actions">
            <a href="mes_produits.php" class="btn-secondary">← Mes produits</a>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert alert-<?= $type_message === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <div class="card-header">
            <h2>📦 Informations du produit</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="ajouter_produit.php" class="form-grid">
                <div class="form-group form-group-full">
                    <label for="nom">Nom du produit *</label>
                    <input type="text" id="nom" name="nom" required maxlength="150"
                           placeholder="Ex : T-shirt coton bio"
                           value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="prix">Prix (<?= param('devise', '€') ?>) *</label>
                    <input type="number" id="prix" name="prix" step="0.01" min="0.01" required
                           value="<?= htmlspecialchars($_POST['prix'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="stock">Stock initial</label>
                    <input type="number" id="stock" name="stock" min="0"
                           value="<?= htmlspecialchars($_POST['stock'] ?? 0) ?>">
                </div>

                <div class="form-group form-group-full">
                    <label for="categorie_id">Catégorie</label>
                    <select id="categorie_id" name="categorie_id">
                        <option value="">— Aucune —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                <?= ($_POST['categorie_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group form-group-full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="5"
                              placeholder="Description détaillée du produit..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group form-group-full">
                    <button type="submit" class="btn-submit">✅ Ajouter le produit</button>
                </div>
            </form>
        </div>
    </section>
</main>
</body>
</html>