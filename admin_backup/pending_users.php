<?php
// admin/pending_users.php
require_once '../config/init.php';
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
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair'), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation des comptes - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="admin-body">

<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

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