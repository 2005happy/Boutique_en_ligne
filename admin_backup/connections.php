<?php
// admin/connections.php
require_once '../config/init.php';
require_once '../includes/auth.php';

// ==========================================
// EXPORT CSV (Critère 27)
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $recherche = trim($_GET['recherche'] ?? '');
    $filtre_user = (int)($_GET['utilisateur'] ?? 0);
    $date_debut = $_GET['date_debut'] ?? '';
    $date_fin = $_GET['date_fin'] ?? '';

    $sql = "SELECT h.id, h.date_connexion, h.ip_adresse, u.nom, u.email, u.role
            FROM historique_connexions h
            LEFT JOIN utilisateurs u ON h.utilisateur_id = u.id
            WHERE 1=1";
    $params = [];

    if ($recherche !== '') {
        $sql .= " AND (u.nom LIKE :rech OR u.email LIKE :rech OR h.ip_adresse LIKE :rech)";
        $params[':rech'] = "%$recherche%";
    }
    if ($filtre_user > 0) {
        $sql .= " AND h.utilisateur_id = :uid";
        $params[':uid'] = $filtre_user;
    }
    if ($date_debut !== '') {
        $sql .= " AND DATE(h.date_connexion) >= :dd";
        $params[':dd'] = $date_debut;
    }
    if ($date_fin !== '') {
        $sql .= " AND DATE(h.date_connexion) <= :df";
        $params[':df'] = $date_fin;
    }
    $sql .= " ORDER BY h.date_connexion DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="connexions_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['ID', 'Date', 'Heure', 'Utilisateur', 'Email', 'Rôle', 'Adresse IP'], ';');

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['id'],
            date('d/m/Y', strtotime($r['date_connexion'])),
            date('H:i:s', strtotime($r['date_connexion'])),
            $r['nom'] ?? 'Inconnu',
            $r['email'] ?? '',
            $r['role'] ?? '',
            $r['ip_adresse']
        ], ';');
    }
    fclose($output);

    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'EXPORT_CONNEXIONS', "Export CSV des connexions (" . count($rows) . " lignes)"]);

    exit;
}

// ==========================================
// SUPPRESSION D'UNE CONNEXION
// ==========================================
$message = '';
$type_message = 'success';

if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM historique_connexions WHERE id = ?")->execute([$id]);
    $message = "Entrée de connexion supprimée.";
}

// Purge des connexions > 90 jours
if (isset($_GET['purger'])) {
    $stmt = $pdo->prepare("DELETE FROM historique_connexions WHERE date_connexion < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $stmt->execute();
    $nb = $stmt->rowCount();
    $message = "$nb entrée(s) de plus de 90 jours supprimée(s).";
    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'PURGE_CONNEXIONS', "Purge de $nb connexions anciennes"]);
}

// ==========================================
// FILTRES
// ==========================================
$recherche = trim($_GET['recherche'] ?? '');
$filtre_user = (int)($_GET['utilisateur'] ?? 0);
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
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
    $where .= " AND (u.nom LIKE :rech OR u.email LIKE :rech OR h.ip_adresse LIKE :rech)";
    $params[':rech'] = "%$recherche%";
}
if ($filtre_user > 0) {
    $where .= " AND h.utilisateur_id = :uid";
    $params[':uid'] = $filtre_user;
}
if ($date_debut !== '') {
    $where .= " AND DATE(h.date_connexion) >= :dd";
    $params[':dd'] = $date_debut;
}
if ($date_fin !== '') {
    $where .= " AND DATE(h.date_connexion) <= :df";
    $params[':df'] = $date_fin;
}

$order = match($tri) {
    'ancien' => "h.date_connexion ASC",
    'user'   => "u.nom ASC, h.date_connexion DESC",
    'ip'     => "h.ip_adresse ASC, h.date_connexion DESC",
    default  => "h.date_connexion DESC",
};

// Total
$stmt = $pdo->prepare("SELECT COUNT(*) FROM historique_connexions h LEFT JOIN utilisateurs u ON h.utilisateur_id = u.id $where");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$total_pages = max(1, ceil($total / $par_page));

// Données
$sql = "SELECT h.id, h.date_connexion, h.ip_adresse, h.utilisateur_id,
               u.nom, u.email, u.role
        FROM historique_connexions h
        LEFT JOIN utilisateurs u ON h.utilisateur_id = u.id
        $where
        ORDER BY $order
        LIMIT $par_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$connexions = $stmt->fetchAll();

// Liste des utilisateurs ayant des connexions
$users_dispo = $pdo->query("
    SELECT DISTINCT u.id, u.nom
    FROM utilisateurs u
    INNER JOIN historique_connexions h ON h.utilisateur_id = u.id
    ORDER BY u.nom ASC
")->fetchAll();

// ==========================================
// STATISTIQUES
// ==========================================
$stats = [
    'total'        => (int)$pdo->query("SELECT COUNT(*) FROM historique_connexions")->fetchColumn(),
    'aujourdhui'   => (int)$pdo->query("SELECT COUNT(*) FROM historique_connexions WHERE DATE(date_connexion) = CURDATE()")->fetchColumn(),
    'cette_semaine'=> (int)$pdo->query("SELECT COUNT(*) FROM historique_connexions WHERE date_connexion >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'users_uniques'=> (int)$pdo->query("SELECT COUNT(DISTINCT utilisateur_id) FROM historique_connexions")->fetchColumn(),
];

// Top 5 utilisateurs les plus actifs
$top_users = $pdo->query("
    SELECT u.nom, u.email, COUNT(h.id) AS nb
    FROM historique_connexions h
    INNER JOIN utilisateurs u ON h.utilisateur_id = u.id
    GROUP BY u.id, u.nom, u.email
    ORDER BY nb DESC
    LIMIT 5
")->fetchAll();

// Top 5 IP
$top_ips = $pdo->query("
    SELECT ip_adresse, COUNT(*) AS nb
    FROM historique_connexions
    GROUP BY ip_adresse
    ORDER BY nb DESC
    LIMIT 5
")->fetchAll();

// Connexions par jour (7 derniers jours) pour mini-graphique
$connexions_par_jour = $pdo->query("
    SELECT DATE(date_connexion) AS jour, COUNT(*) AS nb
    FROM historique_connexions
    WHERE date_connexion >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(date_connexion)
    ORDER BY jour ASC
")->fetchAll();

// ==========================================
// HELPERS
// ==========================================
function badge_role(string $role): string {
    return match($role) {
        'admin'   => 'badge-danger',
        'vendeur' => 'badge-warning',
        default   => 'badge-success'
    };
}

function icone_role(string $role): string {
    return match($role) {
        'admin'   => '👑',
        'vendeur' => '💼',
        default   => '🛒'
    };
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair'), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des connexions - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-body">

<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="admin-main">

    <header class="admin-topbar">
        <div>
            <h1>🔌 Historique des connexions</h1>
            <p class="topbar-subtitle">Traçabilité des accès au système</p>
        </div>
        <div class="topbar-actions">
            <a href="?export=csv&<?= http_build_query([
                'recherche'=>$recherche,'utilisateur'=>$filtre_user,
                'date_debut'=>$date_debut,'date_fin'=>$date_fin
            ]) ?>" class="btn-secondary" title="Exporter en CSV">📤 Exporter CSV</a>
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
            <div class="stat-icon">🔌</div>
            <div class="stat-content">
                <p class="stat-label">Total connexions</p>
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
            <div class="stat-icon">👤</div>
            <div class="stat-content">
                <p class="stat-label">Utilisateurs uniques</p>
                <h3 class="stat-value"><?= number_format($stats['users_uniques']) ?></h3>
            </div>
        </div>
    </section>

    <!-- Graphique + Top users + Top IP -->
    <section class="dashboard-row">
        <div class="card card-half">
            <div class="card-header">
                <h2>📈 Connexions (7 derniers jours)</h2>
            </div>
            <div class="card-body">
                <?php if (empty($connexions_par_jour)): ?>
                    <p class="empty">Aucune connexion sur les 7 derniers jours.</p>
                <?php else: ?>
                    <canvas id="chartConnexions" height="180"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-half">
            <div class="card-header">
                <h2>🏆 Top 5 utilisateurs</h2>
            </div>
            <div class="card-body">
                <?php if (empty($top_users)): ?>
                    <p class="empty">Aucune donnée.</p>
                <?php else: ?>
                    <ul class="list-top">
                        <?php foreach ($top_users as $i => $u): ?>
                            <li>
                                <span class="rank rank-<?= $i + 1 ?>"><?= $i + 1 ?></span>
                                <span class="list-name"><?= htmlspecialchars($u['nom']) ?></span>
                                <span class="list-value"><?= $u['nb'] ?> connexions</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="dashboard-row">
        <div class="card">
            <div class="card-header">
                <h2>🌐 Top 5 adresses IP</h2>
            </div>
            <div class="card-body">
                <?php if (empty($top_ips)): ?>
                    <p class="empty">Aucune donnée.</p>
                <?php else: ?>
                    <ul class="list-top">
                        <?php foreach ($top_ips as $i => $ip): ?>
                            <li>
                                <span class="rank rank-<?= $i + 1 ?>"><?= $i + 1 ?></span>
                                <span class="list-name"><code><?= htmlspecialchars($ip['ip_adresse']) ?></code></span>
                                <span class="list-value"><?= $ip['nb'] ?> connexions</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Filtres -->
    <section class="card">
        <div class="card-header">
            <h2>🔍 Recherche & filtres</h2>
        </div>
        <div class="card-body">
            <form method="GET" action="connections.php" class="filters-bar">
                <input type="text" name="recherche" placeholder="Nom, email ou IP..."
                       value="<?= htmlspecialchars($recherche) ?>">

                <select name="utilisateur">
                    <option value="0">Tous les utilisateurs</option>
                    <?php foreach ($users_dispo as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $filtre_user === (int)$u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>" title="Du">
                <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>" title="Au">

                <select name="tri">
                    <option value="recent" <?= $tri === 'recent' ? 'selected' : '' ?>>Plus récentes</option>
                    <option value="ancien" <?= $tri === 'ancien' ? 'selected' : '' ?>>Plus anciennes</option>
                    <option value="user"   <?= $tri === 'user'   ? 'selected' : '' ?>>Par utilisateur</option>
                    <option value="ip"     <?= $tri === 'ip'     ? 'selected' : '' ?>>Par IP</option>
                </select>

                <button type="submit" class="btn-submit">Filtrer</button>
                <a href="connections.php" class="btn-secondary">Réinitialiser</a>
            </form>
        </div>
    </section>

    <!-- Liste -->
    <section class="card">
        <div class="card-header">
            <h2>📋 Connexions (<?= number_format($total) ?>)</h2>
            <a href="?purger=1" class="card-link"
               onclick="return confirm('Supprimer les connexions de plus de 90 jours ?');">
                🧹 Purger > 90 jours
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($connexions)): ?>
                <p class="empty">Aucune connexion trouvée.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date / Heure</th>
                            <th>Utilisateur</th>
                            <th>Rôle</th>
                            <th>Adresse IP</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($connexions as $c):
                            $initiales = strtoupper(substr($c['nom'] ?? '?', 0, 2));
                        ?>
                            <tr>
                                <td>#<?= (int)$c['id'] ?></td>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($c['date_connexion'])) ?></strong><br>
                                    <small class="text-muted"><?= date('H:i:s', strtotime($c['date_connexion'])) ?></small>
                                </td>
                                <td>
                                    <?php if ($c['nom']): ?>
                                        <div class="user-cell">
                                            <span class="user-avatar-sm"><?= $initiales ?></span>
                                            <div>
                                                <strong><?= htmlspecialchars($c['nom']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($c['email'] ?? '') ?></small>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Utilisateur supprimé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($c['role'])): ?>
                                        <span class="badge <?= badge_role($c['role']) ?>">
                                            <?= icone_role($c['role']) ?> <?= ucfirst($c['role']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= htmlspecialchars($c['ip_adresse']) ?></code></td>
                                <td class="actions-cell">
                                    <a href="?supprimer=<?= (int)$c['id'] ?>" class="btn-icon btn-danger" title="Supprimer"
                                       onclick="return confirm('Supprimer cette entrée ?');">🗑️</a>
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
                        Page <?= $page ?> / <?= $total_pages ?> — <?= number_format($total) ?> connexion(s)
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

</main>

<?php if (!empty($connexions_par_jour)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('chartConnexions').getContext('2d');
    const labels = <?= json_encode(array_map(fn($r) => date('d/m', strtotime($r['jour'])), $connexions_par_jour)) ?>;
    const data = <?= json_encode(array_map('intval', array_column($connexions_par_jour, 'nb'))) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Connexions',
                data: data,
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.15)',
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#3498db',
                pointRadius: 4,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
});
</script>
<?php endif; ?>

</body>
</html>