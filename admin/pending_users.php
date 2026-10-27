<?php
// admin/pending_users.php
require_once '../config/database.php';
require_once '../includes/auth.php';

$message = '';
$type_message = 'success';

// --- APPROBATION ---
if (isset($_GET['approuver'])) {
    $id = (int)$_GET['approuver'];
    $pdo->prepare("
        UPDATE utilisateurs
        SET statut = 'actif', date_validation = NOW(), valide_par = ?
        WHERE id = ? AND statut = 'en_attente'
    ")->execute([$_SESSION['user_id'], $id]);
    $message = "Compte approuvé.";
    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'VALIDATION_USER', "Approbation du compte #$id"]);
}

// --- REJET ---
if (isset($_GET['rejeter'])) {
    $id = (int)$_GET['rejeter'];
    $pdo->prepare("
        UPDATE utilisateurs
        SET statut = 'rejete', date_validation = NOW(), valide_par = ?
        WHERE id = ? AND statut = 'en_attente'
    ")->execute([$_SESSION['user_id'], $id]);
    $message = "Compte rejeté.";
    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'REJET_USER', "Rejet du compte #$id"]);
}

// --- LISTE DES DEMANDES ---
$demandes = $pdo->query("
    SELECT id, nom, email, role, cree_le
    FROM utilisateurs
    WHERE statut = 'en_attente'
    ORDER BY cree_le ASC
")->fetchAll();

// Compteur pour badge sidebar
$nb_attente = count($demandes);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation des comptes - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
</head>
<body class="admin-body">

<aside class="sidebar">
    <div class="sidebar-header">
        <h2>🛍️ Ma Boutique</h2>
        <p class="sidebar-subtitle">Espace Admin</p>
    </div>
    <nav class="sidebar-nav">
        <p class="nav-section-title">Principal</p>
        <a href="dashboard.php" class="nav-link"><span class="nav-icon">📊</span> Tableau de bord</a>
        <p class="nav-section-title">Gestion des données</p>
        <a href="products.php" class="nav-link"><span class="nav-icon">📦</span> Produits</a>
        <a href="categories.php" class="nav-link"><span class="nav-icon">🏷️</span> Catégories</a>
        <p class="nav-section-title">Utilisateurs</p>
        <a href="users.php" class="nav-link"><span class="nav-icon">👥</span> Utilisateurs</a>
        <a href="pending_users.php" class="nav-link active">
            <span class="nav-icon">⏳</span> Validations
            <?php if ($nb_attente > 0): ?>
                <span class="badge-notif"><?= $nb_attente ?></span>
            <?php endif; ?>
        </a>
        <a href="connections.php" class="nav-link"><span class="nav-icon">🔌</span> Connexions</a>
        <a href="activities.php" class="nav-link"><span class="nav-icon">📝</span> Activités</a>
        <p class="nav-section-title">Système</p>
        <a href="backup.php" class="nav-link"><span class="nav-icon">💾</span> Sauvegardes</a>
        <a href="settings.php" class="nav-link"><span class="nav-icon">⚙️</span> Paramètres</a>
        <div class="nav-divider"></div>
        <a href="../logout.php" class="nav-link nav-logout"><span class="nav-icon">🚪</span> Déconnexion</a>
    </nav>
</aside>

<main class="admin-main">

    <header class="admin-topbar">
        <div>
            <h1>⏳ Validation des comptes</h1>
            <p class="topbar-subtitle">Approuvez ou rejetez les demandes de comptes vendeurs</p>
        </div>
        <div class="topbar-actions">
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

    <section class="card">
        <div class="card-header">
            <h2>📋 Demandes en attente (<?= $nb_attente ?>)</h2>
        </div>
        <div class="card-body">
            <?php if (empty($demandes)): ?>
                <p class="empty">Aucune demande en attente. ✅</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle demandé</th>
                            <th>Date de demande</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($demandes as $d): ?>
                            <tr>
                                <td>#<?= (int)$d['id'] ?></td>
                                <td><strong><?= htmlspecialchars($d['nom']) ?></strong></td>
                                <td><?= htmlspecialchars($d['email']) ?></td>
                                <td>
                                    <span class="badge badge-warning">
                                        <?= $d['role'] === 'vendeur' ? '💼' : '🛒' ?>
                                        <?= ucfirst($d['role']) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($d['cree_le'])) ?></td>
                                <td class="actions-cell">
                                    <a href="?approuver=<?= (int)$d['id'] ?>" class="btn-icon btn-approve" title="Approuver"
                                       onclick="return confirm('Approuver ce compte ?');">✅</a>
                                    <a href="?rejeter=<?= (int)$d['id'] ?>" class="btn-icon btn-reject" title="Rejeter"
                                       onclick="return confirm('Rejeter cette demande ?');">❌</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

    <!-- Historique des validations récentes -->
    <section class="card">
        <div class="card-header">
            <h2>📜 Historique des validations récentes</h2>
        </div>
        <div class="card-body">
            <?php
            $historique = $pdo->query("
                SELECT u.nom, u.email, u.role, u.statut, u.date_validation, v.nom AS valide_par_nom
                FROM utilisateurs u
                LEFT JOIN utilisateurs v ON u.valide_par = v.id
                WHERE u.statut IN ('actif', 'rejete') AND u.date_validation IS NOT NULL
                ORDER BY u.date_validation DESC
                LIMIT 10
            ")->fetchAll();
            ?>
            <?php if (empty($historique)): ?>
                <p class="empty">Aucune validation enregistrée.</p>
            <?php else: ?>
                <ul class="list-activities">
                    <?php foreach ($historique as $h): ?>
                        <li>
                            <strong><?= htmlspecialchars($h['nom']) ?></strong>
                            <span class="activity-action" style="background:<?= $h['statut'] === 'actif' ? '#eafaf1' : '#fdecea' ?>;color:<?= $h['statut'] === 'actif' ? '#1e8449' : '#c0392b' ?>;">
                                <?= $h['statut'] === 'actif' ? '✅ Approuvé' : '❌ Rejeté' ?>
                            </span>
                            <small>par <?= htmlspecialchars($h['valide_par_nom'] ?? 'Admin') ?></small>
                            <small><?= date('d/m H:i', strtotime($h['date_validation'])) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

</main>
</body>
</html>