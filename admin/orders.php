<?php
// admin/orders.php
require_once '../config/init.php';
require_once '../includes/auth.php';

$message = '';
$type_message = 'success';

// ==========================================
// CHANGEMENT DE STATUT
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changer_statut'])) {
    $id = (int)($_POST['commande_id'] ?? 0);
    $nouveau_statut = $_POST['statut'] ?? '';
    $statuts_valides = ['en_attente', 'confirmee', 'expediee', 'livree', 'annulee'];

    if ($id <= 0 || !in_array($nouveau_statut, $statuts_valides, true)) {
        $message = "Données invalides.";
        $type_message = 'error';
    } else {
        try {
            // Charger l'ancien statut
            $stmt = $pdo->prepare("SELECT numero, statut FROM commandes WHERE id = ?");
            $stmt->execute([$id]);
            $cmd = $stmt->fetch();

            if (!$cmd) {
                $message = "Commande introuvable.";
                $type_message = 'error';
            } else {
                // Mise à jour
                $pdo->prepare("UPDATE commandes SET statut = ? WHERE id = ?")
                    ->execute([$nouveau_statut, $id]);

                // Si annulation → remettre le stock
                if ($nouveau_statut === 'annulee' && $cmd['statut'] !== 'annulee') {
                    $lignes = $pdo->prepare("SELECT produit_id, quantite FROM commande_lignes WHERE commande_id = ?");
                    $lignes->execute([$id]);
                    $stmt_stock = $pdo->prepare("UPDATE produits SET stock = stock + ? WHERE id = ?");
                    foreach ($lignes->fetchAll() as $l) {
                        $stmt_stock->execute([$l['quantite'], $l['produit_id']]);
                    }
                    $message = "Commande {$cmd['numero']} annulée — stock restauré.";
                } else {
                    $message = "Statut de {$cmd['numero']} changé en « $nouveau_statut ».";
                }

                // Journal
                $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                    ->execute([$_SESSION['user_id'], 'MAJ_COMMANDE', "{$cmd['numero']} : {$cmd['statut']} → $nouveau_statut"]);
            }
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $type_message = 'error';
        }
    }
}

// ==========================================
// SUPPRESSION
// ==========================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    try {
        $stmt = $pdo->prepare("SELECT numero FROM commandes WHERE id = ?");
        $stmt->execute([$id]);
        $numero = $stmt->fetchColumn();

        if ($numero) {
            $pdo->prepare("DELETE FROM commandes WHERE id = ?")->execute([$id]);
            $message = "Commande $numero supprimée (lignes incluses).";
            $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], 'SUPPR_COMMANDE', "Suppression de $numero"]);
        }
    } catch (PDOException $e) {
        $message = "Erreur : " . $e->getMessage();
        $type_message = 'error';
    }
}

// ==========================================
// EXPORT CSV
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->query("
        SELECT c.numero, c.date_commande, u.nom AS client, u.email,
               c.total, c.statut, c.ville, c.telephone
        FROM commandes c
        LEFT JOIN utilisateurs u ON c.client_id = u.id
        ORDER BY c.date_commande DESC
    ");
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="commandes_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Numéro', 'Date', 'Client', 'Email', 'Total', 'Statut', 'Ville', 'Téléphone'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['numero'],
            date('d/m/Y H:i', strtotime($r['date_commande'])),
            $r['client'] ?? 'Inconnu',
            $r['email'] ?? '',
            number_format($r['total'], 2, ',', ' '),
            $r['statut'],
            $r['ville'],
            $r['telephone']
        ], ';');
    }
    fclose($out);
    exit;
}

// ==========================================
// FILTRES
// ==========================================
$recherche = trim($_GET['recherche'] ?? '');
$filtre_statut = $_GET['statut'] ?? '';
$tri = $_GET['tri'] ?? 'recent';

$statuts_dispo = ['en_attente', 'confirmee', 'expediee', 'livree', 'annulee'];

$where = "WHERE 1=1";
$params = [];

if ($recherche !== '') {
    $where .= " AND (c.numero LIKE :rech OR u.nom LIKE :rech OR u.email LIKE :rech)";
    $params[':rech'] = "%$recherche%";
}
if ($filtre_statut !== '' && in_array($filtre_statut, $statuts_dispo, true)) {
    $where .= " AND c.statut = :statut";
    $params[':statut'] = $filtre_statut;
}

$order = match($tri) {
    'ancien'   => "c.date_commande ASC",
    'montant'  => "c.total DESC",
    'client'   => "u.nom ASC, c.date_commande DESC",
    default    => "c.date_commande DESC",
};

$sql = "SELECT c.*, u.nom AS client, u.email,
        (SELECT COUNT(*) FROM commande_lignes WHERE commande_id = c.id) AS nb_articles
        FROM commandes c
        LEFT JOIN utilisateurs u ON c.client_id = u.id
        $where
        ORDER BY $order";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$commandes = $stmt->fetchAll();

// Statistiques
$stats = [
    'total'      => (int)$pdo->query("SELECT COUNT(*) FROM commandes")->fetchColumn(),
    'en_attente' => (int)$pdo->query("SELECT COUNT(*) FROM commandes WHERE statut = 'en_attente'")->fetchColumn(),
    'en_cours'   => (int)$pdo->query("SELECT COUNT(*) FROM commandes WHERE statut IN ('confirmee','expediee')")->fetchColumn(),
    'ca_total'   => (float)$pdo->query("SELECT COALESCE(SUM(total), 0) FROM commandes WHERE statut != 'annulee'")->fetchColumn(),
];

function badge_statut(string $statut): string {
    return match($statut) {
        'en_attente' => 'badge-warning',
        'confirmee'  => 'badge-info',
        'expediee'   => 'badge-info',
        'livree'     => 'badge-success',
        'annulee'    => 'badge-danger',
        default      => 'badge-info'
    };
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair')) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="admin-main">

    <header class="admin-topbar">
        <div>
            <h1>🛒 Gestion des commandes</h1>
            <p class="topbar-subtitle">Suivi, changement de statut et export</p>
        </div>
        <div class="topbar-actions">
            <a href="?export=csv" class="btn-secondary" title="Exporter CSV">📤 Exporter CSV</a>
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

    <!-- Statistiques -->
    <section class="stats-grid">
        <div class="stat-card stat-blue">
            <div class="stat-icon">🛒</div>
            <div class="stat-content">
                <p class="stat-label">Total commandes</p>
                <h3 class="stat-value"><?= $stats['total'] ?></h3>
            </div>
        </div>
        <div class="stat-card stat-orange">
            <div class="stat-icon">⏳</div>
            <div class="stat-content">
                <p class="stat-label">En attente</p>
                <h3 class="stat-value"><?= $stats['en_attente'] ?></h3>
            </div>
        </div>
        <div class="stat-card stat-blue">
            <div class="stat-icon">🚚</div>
            <div class="stat-content">
                <p class="stat-label">En cours</p>
                <h3 class="stat-value"><?= $stats['en_cours'] ?></h3>
            </div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <p class="stat-label">Chiffre d'affaires</p>
                <h3 class="stat-value" style="font-size:1.1rem;">
                    <?= number_format($stats['ca_total'], 0, ',', ' ') ?> <?= param('devise', '€') ?>
                </h3>
            </div>
        </div>
    </section>

    <!-- Filtres -->
    <section class="card">
        <div class="card-header">
            <h2>🔍 Recherche & filtres</h2>
        </div>
        <div class="card-body">
            <form method="GET" action="orders.php" class="filters-bar">
                <input type="text" name="recherche" placeholder="Numéro, client, email..."
                       value="<?= htmlspecialchars($recherche) ?>">

                <select name="statut">
                    <option value="">Tous les statuts</option>
                    <?php foreach ($statuts_dispo as $s): ?>
                        <option value="<?= $s ?>" <?= $filtre_statut === $s ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_', ' ', $s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="tri">
                    <option value="recent"  <?= $tri === 'recent'  ? 'selected' : '' ?>>Plus récentes</option>
                    <option value="ancien"  <?= $tri === 'ancien'  ? 'selected' : '' ?>>Plus anciennes</option>
                    <option value="montant" <?= $tri === 'montant' ? 'selected' : '' ?>>Montant décroissant</option>
                    <option value="client"  <?= $tri === 'client'  ? 'selected' : '' ?>>Par client</option>
                </select>

                <button type="submit" class="btn-submit">Filtrer</button>
                <a href="orders.php" class="btn-secondary">Réinitialiser</a>
            </form>
        </div>
    </section>

    <!-- Liste des commandes -->
    <section class="card">
        <div class="card-header">
            <h2>📋 Commandes (<?= count($commandes) ?>)</h2>
        </div>
        <div class="card-body">
            <?php if (empty($commandes)): ?>
                <p class="empty">Aucune commande trouvée.</p>
            <?php else: ?>
                <div class="table-responsive">
                <table class="data-table order-table order-table-wide">
                    <thead>
                        <tr>
                            <th>Numéro</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Articles</th>
                            <th>Total</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commandes as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['numero']) ?></strong></td>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($c['date_commande'])) ?></strong><br>
                                    <small class="text-muted"><?= date('H:i', strtotime($c['date_commande'])) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($c['client'] ?? 'Inconnu') ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($c['email'] ?? '') ?></small>
                                </td>
                                <td><?= $c['nb_articles'] ?></td>
                                <td><strong><?= number_format($c['total'], 2, ',', ' ') ?> <?= param('devise', '€') ?></strong></td>
                                <td>
                                    <form method="POST" action="orders.php" class="statut-form">
                                        <input type="hidden" name="commande_id" value="<?= $c['id'] ?>">
                                        <select name="statut" onchange="this.form.submit()" class="statut-select">
                                            <?php foreach ($statuts_dispo as $s): ?>
                                                <option value="<?= $s ?>" <?= $c['statut'] === $s ? 'selected' : '' ?>>
                                                    <?= ucfirst(str_replace('_', ' ', $s)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="changer_statut" value="1">
                                    </form>
                                </td>
                                <td class="actions-cell">
                                    <a href="order_detail.php?id=<?= $c['id'] ?>" class="btn-icon" title="Voir le détail">👁️</a>
                                    <a href="?supprimer=<?= $c['id'] ?>" class="btn-icon btn-danger" title="Supprimer"
                                       onclick="return confirm('Supprimer définitivement cette commande ?');">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

</main>
</body>
</html>