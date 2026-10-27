<?php
// admin/activities.php
require_once '../config/init.php';
require_once '../includes/auth.php';

// ==========================================
// EXPORT CSV (Critère 27)
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Récupérer toutes les activités (avec filtres éventuels)
    $recherche = trim($_GET['recherche'] ?? '');
    $filtre_action = trim($_GET['action'] ?? '');
    $filtre_user = (int)($_GET['utilisateur'] ?? 0);

    $sql = "SELECT j.id, j.action, j.details, j.date_action, u.nom AS utilisateur, u.email
            FROM journal_activites j
            LEFT JOIN utilisateurs u ON j.utilisateur_id = u.id
            WHERE 1=1";
    $params = [];

    if ($recherche !== '') {
        $sql .= " AND (j.details LIKE :rech OR u.nom LIKE :rech OR u.email LIKE :rech)";
        $params[':rech'] = "%$recherche%";
    }
    if ($filtre_action !== '') {
        $sql .= " AND j.action = :action";
        $params[':action'] = $filtre_action;
    }
    if ($filtre_user > 0) {
        $sql .= " AND j.utilisateur_id = :uid";
        $params[':uid'] = $filtre_user;
    }
    $sql .= " ORDER BY j.date_action DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // En-têtes CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="journal_activites_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    // BOM UTF-8 pour Excel
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['ID', 'Date', 'Utilisateur', 'Email', 'Action', 'Détails'], ';');

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['id'],
            date('d/m/Y H:i:s', strtotime($r['date_action'])),
            $r['utilisateur'] ?? 'Système',
            $r['email'] ?? '',
            $r['action'],
            $r['details']
        ], ';');
    }
    fclose($output);

    // Journal de l'export
    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'EXPORT_CSV', "Export CSV du journal (" . count($rows) . " lignes)"]);

    exit;
}

// ==========================================
// SUPPRESSION / PURGE
// ==========================================
$message = '';
$type_message = 'success';

if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM journal_activites WHERE id = ?")->execute([$id]);
    $message = "Entrée supprimée du journal.";
    // On ne journalise PAS la suppression d'une entrée de journal (sinon boucle infinie)
}

// Purge des entrées > 30 jours (Critère 18 : archivage/nettoyage)
if (isset($_GET['purger'])) {
    $stmt = $pdo->prepare("DELETE FROM journal_activites WHERE date_action < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $nb = $stmt->rowCount();
    $message = "$nb entrée(s) de plus de 30 jours supprimée(s).";
    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'PURGE_JOURNAL', "Purge de $nb entrées anciennes"]);
}

// ==========================================
// FILTRES
// ==========================================
$recherche = trim($_GET['recherche'] ?? '');
$filtre_action = trim($_GET['action'] ?? '');
$filtre_user = (int)($_GET['utilisateur'] ?? 0);
$tri = $_GET['tri'] ?? 'recent';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$par_page = max(5, min(100, (int)param('elements_par_page', 20)));
$offset = ($page - 1) * $par_page;

// ==========================================
// CONSTRUCTION DE LA REQUÊTE
// ==========================================
$where = "WHERE 1=1";
$params = [];

if ($recherche !== '') {
    $where .= " AND (j.details LIKE :rech OR u.nom LIKE :rech OR u.email LIKE :rech OR j.action LIKE :rech)";
    $params[':rech'] = "%$recherche%";
}
if ($filtre_action !== '') {
    $where .= " AND j.action = :action";
    $params[':action'] = $filtre_action;
}
if ($filtre_user > 0) {
    $where .= " AND j.utilisateur_id = :uid";
    $params[':uid'] = $filtre_user;
}

// Tri
$order = match($tri) {
    'ancien'    => "j.date_action ASC",
    'user'      => "u.nom ASC, j.date_action DESC",
    'action'    => "j.action ASC, j.date_action DESC",
    default     => "j.date_action DESC",
};

// Total pour pagination
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM journal_activites j
    LEFT JOIN utilisateurs u ON j.utilisateur_id = u.id
    $where
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$total_pages = max(1, ceil($total / $par_page));

// Récupération des entrées
$sql = "SELECT j.id, j.action, j.details, j.date_action, j.utilisateur_id,
               u.nom AS utilisateur, u.email, u.role
        FROM journal_activites j
        LEFT JOIN utilisateurs u ON j.utilisateur_id = u.id
        $where
        ORDER BY $order
        LIMIT $par_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activites = $stmt->fetchAll();

// Liste des actions distinctes (pour le filtre)
$actions_dispo = $pdo->query("SELECT DISTINCT action FROM journal_activites ORDER BY action ASC")->fetchAll();

// Liste des utilisateurs (pour le filtre)
$users_dispo = $pdo->query("
    SELECT DISTINCT u.id, u.nom
    FROM utilisateurs u
    INNER JOIN journal_activites j ON j.utilisateur_id = u.id
    ORDER BY u.nom ASC
")->fetchAll();

// Statistiques globales
$stats = [
    'total'     => (int)$pdo->query("SELECT COUNT(*) FROM journal_activites")->fetchColumn(),
    'aujourdhui'=> (int)$pdo->query("SELECT COUNT(*) FROM journal_activites WHERE DATE(date_action) = CURDATE()")->fetchColumn(),
    'cette_semaine' => (int)$pdo->query("SELECT COUNT(*) FROM journal_activites WHERE date_action >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'actions_distinctes' => (int)$pdo->query("SELECT COUNT(DISTINCT action) FROM journal_activites")->fetchColumn(),
];

// ==========================================
// HELPERS D'AFFICHAGE
// ==========================================
function badge_action(string $action): array {
    // Retourne [emoji, classe CSS]
    if (str_contains($action, 'AJOUT'))     return ['➕', 'badge-success'];
    if (str_contains($action, 'MODIF'))     return ['✏️', 'badge-info'];
    if (str_contains($action, 'SUPPR'))     return ['🗑️', 'badge-danger'];
    if (str_contains($action, 'ARCHIVE'))   return ['📥', 'badge-warning'];
    if (str_contains($action, 'RESTAURE'))  return ['♻️', 'badge-success'];
    if (str_contains($action, 'CONNEXION')) return ['🔓', 'badge-info'];
    if (str_contains($action, 'RESET'))     return ['🔑', 'badge-warning'];
    if (str_contains($action, 'VERROU'))    return ['🔒', 'badge-danger'];
    if (str_contains($action, 'DEVERROU'))  return ['🔓', 'badge-success'];
    if (str_contains($action, 'EXPORT'))    return ['📤', 'badge-info'];
    if (str_contains($action, 'PURGE'))     return ['🧹', 'badge-warning'];
    if (str_contains($action, 'INSCRIPTION')) return ['👤', 'badge-success'];
    return ['📌', 'badge-info'];
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair'), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal des activités - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="admin-body">

<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="admin-main">

    <header class="admin-topbar">
        <div>
            <h1>📝 Journal des activités</h1>
            <p class="topbar-subtitle">Traçabilité complète des actions effectuées</p>
        </div>
        <div class="topbar-actions">
            <a href="?export=csv&<?= http_build_query(['recherche'=>$recherche,'action'=>$filtre_action,'utilisateur'=>$filtre_user]) ?>"
               class="btn-secondary" title="Exporter en CSV">
                📤 Exporter CSV
            </a>
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
            <div class="stat-icon">📋</div>
            <div class="stat-content">
                <p class="stat-label">Total entrées</p>
                <h3 class="stat-value"><?= number_format($stats['total']) ?></h3>
            </div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-icon">📅</div>
            <div class="stat-content">
                <p class="stat-label">Aujourd'hui</p>
                <h3 class="stat-value"><?= number_format($stats['aujourdhui']) ?></h3>
            </div>
        </div>
        <div class="stat-card stat-orange">
            <div class="stat-icon">🗓️</div>
            <div class="stat-content">
                <p class="stat-label">7 derniers jours</p>
                <h3 class="stat-value"><?= number_format($stats['cette_semaine']) ?></h3>
            </div>
        </div>
        <div class="stat-card stat-blue">
            <div class="stat-icon">🔖</div>
            <div class="stat-content">
                <p class="stat-label">Types d'actions</p>
                <h3 class="stat-value"><?= $stats['actions_distinctes'] ?></h3>
            </div>
        </div>
    </section>

    <!-- Filtres -->
    <section class="card">
        <div class="card-header">
            <h2>🔍 Recherche & filtres</h2>
        </div>
        <div class="card-body">
            <form method="GET" action="activities.php" class="filters-bar">
                <input type="text" name="recherche" placeholder="Rechercher dans les détails..."
                       value="<?= htmlspecialchars($recherche) ?>">

                <select name="action">
                    <option value="">Toutes les actions</option>
                    <?php foreach ($actions_dispo as $a): ?>
                        <option value="<?= htmlspecialchars($a['action']) ?>"
                            <?= $filtre_action === $a['action'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['action']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="utilisateur">
                    <option value="0">Tous les utilisateurs</option>
                    <?php foreach ($users_dispo as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $filtre_user === (int)$u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="tri">
                    <option value="recent"  <?= $tri === 'recent'  ? 'selected' : '' ?>>Plus récents</option>
                    <option value="ancien"  <?= $tri === 'ancien'  ? 'selected' : '' ?>>Plus anciens</option>
                    <option value="user"    <?= $tri === 'user'    ? 'selected' : '' ?>>Par utilisateur</option>
                    <option value="action"  <?= $tri === 'action'  ? 'selected' : '' ?>>Par action</option>
                </select>

                <button type="submit" class="btn-submit">Filtrer</button>
                <a href="activities.php" class="btn-secondary">Réinitialiser</a>
            </form>
        </div>
    </section>

    <!-- Liste des activités -->
    <section class="card">
        <div class="card-header">
            <h2>📋 Journal (<?= number_format($total) ?> entrée<?= $total > 1 ? 's' : '' ?>)</h2>
            <a href="?purger=1" class="card-link"
               onclick="return confirm('Supprimer toutes les entrées de plus de 30 jours ?');">
                🧹 Purger > 30 jours
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($activites)): ?>
                <p class="empty">Aucune activité trouvée.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Détails</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activites as $a):
                            [$emoji, $badge] = badge_action($a['action']);
                            $initiales = strtoupper(substr($a['utilisateur'] ?? 'S', 0, 2));
                        ?>
                            <tr>
                                <td>#<?= (int)$a['id'] ?></td>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($a['date_action'])) ?></strong><br>
                                    <small class="text-muted"><?= date('H:i:s', strtotime($a['date_action'])) ?></small>
                                </td>
                                <td>
                                    <?php if ($a['utilisateur']): ?>
                                        <div class="user-cell">
                                            <span class="user-avatar-sm"><?= $initiales ?></span>
                                            <div>
                                                <strong><?= htmlspecialchars($a['utilisateur']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($a['email'] ?? '') ?></small>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Système</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $badge ?>"><?= $emoji ?> <?= htmlspecialchars($a['action']) ?></span>
                                </td>
                                <td class="details-cell"><?= htmlspecialchars($a['details'] ?? '') ?></td>
                                <td class="actions-cell">
                                    <a href="?supprimer=<?= (int)$a['id'] ?>" class="btn-icon btn-danger" title="Supprimer"
                                       onclick="return confirm('Supprimer cette entrée du journal ?');">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="pagination">
                        <?php
                        $query_base = $_GET;
                        unset($query_base['page']);
                        $build_url = fn($p) => '?' . http_build_query(array_merge($query_base, ['page' => $p]));
                        ?>
                        <?php if ($page > 1): ?>
                            <a href="<?= $build_url(1) ?>" class="page-link">« Première</a>
                            <a href="<?= $build_url($page - 1) ?>" class="page-link">‹ Précédente</a>
                        <?php endif; ?>

                        <?php
                        $debut = max(1, $page - 2);
                        $fin = min($total_pages, $page + 2);
                        for ($i = $debut; $i <= $fin; $i++): ?>
                            <a href="<?= $build_url($i) ?>"
                               class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?= $build_url($page + 1) ?>" class="page-link">Suivante ›</a>
                            <a href="<?= $build_url($total_pages) ?>" class="page-link">Dernière »</a>
                        <?php endif; ?>
                    </nav>
                    <p class="text-muted" style="text-align:center; margin-top:10px; font-size:0.85rem;">
                        Page <?= $page ?> / <?= $total_pages ?> — <?= number_format($total) ?> entrée(s)
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

</main>
</body>
</html>