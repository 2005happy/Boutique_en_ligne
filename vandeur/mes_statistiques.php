<?php
// vandeur/mes_statistiques.php
require_once '../config/init.php';
require_once '../includes/auth_vendeur.php';

// ==========================================
// PÉRIODE D'ANALYSE
// ==========================================
$periode = $_GET['periode'] ?? 'mois';
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');

switch ($periode) {
    case 'jour':    $date_debut = date('Y-m-d'); $date_fin = date('Y-m-d'); break;
    case 'semaine': $date_debut = date('Y-m-d', strtotime('monday this week')); $date_fin = date('Y-m-d'); break;
    case 'mois':    $date_debut = date('Y-m-01'); $date_fin = date('Y-m-d'); break;
    case 'annee':   $date_debut = date('Y-01-01'); $date_fin = date('Y-m-d'); break;
    case 'custom':  break;
}

$vendeur_id = (int)$_SESSION['user_id'];

// ==========================================
// STATISTIQUES GLOBALES
// ==========================================
$stmt = $pdo->prepare("
    SELECT
        COUNT(DISTINCT c.id) AS nb_commandes,
        COALESCE(SUM(cl.quantite), 0) AS nb_articles,
        COALESCE(SUM(cl.sous_total), 0) AS ca_total,
        COALESCE(AVG(cl.sous_total), 0) AS panier_moyen
    FROM commande_lignes cl
    INNER JOIN produits p ON cl.produit_id = p.id
    INNER JOIN commandes c ON cl.commande_id = c.id
    WHERE p.vendeur_id = ?
      AND c.statut != 'annulee'
      AND DATE(c.date_commande) BETWEEN ? AND ?
");
$stmt->execute([$vendeur_id, $date_debut, $date_fin]);
$stats = $stmt->fetch();

// ==========================================
// VENTES PAR JOUR (graphique)
// ==========================================
$stmt = $pdo->prepare("
    SELECT DATE(c.date_commande) AS jour,
           COUNT(DISTINCT c.id) AS nb_commandes,
           COALESCE(SUM(cl.sous_total), 0) AS ca
    FROM commande_lignes cl
    INNER JOIN produits p ON cl.produit_id = p.id
    INNER JOIN commandes c ON cl.commande_id = c.id
    WHERE p.vendeur_id = ?
      AND c.statut != 'annulee'
      AND DATE(c.date_commande) BETWEEN ? AND ?
    GROUP BY DATE(c.date_commande)
    ORDER BY jour ASC
");
$stmt->execute([$vendeur_id, $date_debut, $date_fin]);
$ventes_par_jour = $stmt->fetchAll();

// ==========================================
// RÉPARTITION PAR PRODUIT (graphique)
// ==========================================
$stmt = $pdo->prepare("
    SELECT cl.nom_produit,
           SUM(cl.quantite) AS total_vendu,
           SUM(cl.sous_total) AS ca
    FROM commande_lignes cl
    INNER JOIN produits p ON cl.produit_id = p.id
    INNER JOIN commandes c ON cl.commande_id = c.id
    WHERE p.vendeur_id = ?
      AND c.statut != 'annulee'
      AND DATE(c.date_commande) BETWEEN ? AND ?
    GROUP BY cl.nom_produit
    ORDER BY ca DESC
    LIMIT 8
");
$stmt->execute([$vendeur_id, $date_debut, $date_fin]);
$produits_stats = $stmt->fetchAll();

// ==========================================
// TOP CLIENTS
// ==========================================
$stmt = $pdo->prepare("
    SELECT u.nom, u.email,
           COUNT(DISTINCT c.id) AS nb_commandes,
           SUM(cl.sous_total) AS total_depense
    FROM commande_lignes cl
    INNER JOIN produits p ON cl.produit_id = p.id
    INNER JOIN commandes c ON cl.commande_id = c.id
    INNER JOIN utilisateurs u ON c.client_id = u.id
    WHERE p.vendeur_id = ?
      AND c.statut != 'annulee'
      AND DATE(c.date_commande) BETWEEN ? AND ?
    GROUP BY u.id, u.nom, u.email
    ORDER BY total_depense DESC
    LIMIT 5
");
$stmt->execute([$vendeur_id, $date_debut, $date_fin]);
$top_clients = $stmt->fetchAll();

// ==========================================
// STATUTS DES COMMANDES
// ==========================================
$stmt = $pdo->prepare("
    SELECT c.statut, COUNT(DISTINCT c.id) AS nb
    FROM commande_lignes cl
    INNER JOIN produits p ON cl.produit_id = p.id
    INNER JOIN commandes c ON cl.commande_id = c.id
    WHERE p.vendeur_id = ?
      AND DATE(c.date_commande) BETWEEN ? AND ?
    GROUP BY c.statut
");
$stmt->execute([$vendeur_id, $date_debut, $date_fin]);
$statuts_rows = $stmt->fetchAll();

$statuts_data = ['en_attente'=>0,'confirmee'=>0,'expediee'=>0,'livree'=>0,'annulee'=>0];
foreach ($statuts_rows as $r) {
    $statuts_data[$r['statut']] = (int)$r['nb'];
}

// ==========================================
// COMPARAISON PÉRIODE PRÉCÉDENTE (Critère 31)
// ==========================================
$duree = (strtotime($date_fin) - strtotime($date_debut)) / 86400 + 1;
$date_debut_prec = date('Y-m-d', strtotime("$date_debut -" . (int)$duree . " days"));
$date_fin_prec = date('Y-m-d', strtotime("$date_debut -1 day"));

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(cl.sous_total), 0) AS ca_prec,
           COUNT(DISTINCT c.id) AS nb_prec
    FROM commande_lignes cl
    INNER JOIN produits p ON cl.produit_id = p.id
    INNER JOIN commandes c ON cl.commande_id = c.id
    WHERE p.vendeur_id = ?
      AND c.statut != 'annulee'
      AND DATE(c.date_commande) BETWEEN ? AND ?
");
$stmt->execute([$vendeur_id, $date_debut_prec, $date_fin_prec]);
$stats_prec = $stmt->fetch();

$evolution_ca = ($stats_prec['ca_prec'] > 0)
    ? round((($stats['ca_total'] - $stats_prec['ca_prec']) / $stats_prec['ca_prec']) * 100, 1)
    : null;

$evolution_cmd = ($stats_prec['nb_prec'] > 0)
    ? round((($stats['nb_commandes'] - $stats_prec['nb_prec']) / $stats_prec['nb_prec']) * 100, 1)
    : null;

// ==========================================
// PRÉVISIONS (Critère 32)
// ==========================================
$stmt = $pdo->prepare("
    SELECT DATE(c.date_commande) AS jour, SUM(cl.sous_total) AS ca
    FROM commande_lignes cl
    INNER JOIN produits p ON cl.produit_id = p.id
    INNER JOIN commandes c ON cl.commande_id = c.id
    WHERE p.vendeur_id = ?
      AND c.statut != 'annulee'
      AND c.date_commande >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(c.date_commande)
");
$stmt->execute([$vendeur_id]);
$jours_actifs = $stmt->fetchAll();

$moyenne_jour = count($jours_actifs) > 0
    ? array_sum(array_column($jours_actifs, 'ca')) / count($jours_actifs)
    : 0;
$prevision_mois = round($moyenne_jour * 30, 2);

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
    <title>Mes statistiques - Espace Vendeur</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-body">

<?php include __DIR__ . '/../includes/sidebar_vendeur.php'; ?>

<main class="admin-main">

    <header class="admin-topbar">
        <div>
            <h1>📈 Mes statistiques</h1>
            <p class="topbar-subtitle">Analysez vos ventes et votre activité</p>
        </div>
        <div class="topbar-actions">
            <a href="mes_commandes.php" class="btn-secondary">🛒 Mes ventes</a>
            <div class="topbar-user">
                <span class="avatar"><?= strtoupper(substr($_SESSION['nom'], 0, 1)) ?></span>
                <span><?= htmlspecialchars($_SESSION['nom']) ?></span>
            </div>
        </div>
    </header>

    <!-- Sélecteur de période -->
    <section class="card">
        <div class="card-header">
            <h2>🗓️ Période d'analyse</h2>
        </div>
        <div class="card-body">
            <form method="GET" action="mes_statistiques.php" class="filters-bar">
                <select name="periode" onchange="this.form.submit()">
                    <option value="jour"    <?= $periode === 'jour'    ? 'selected' : '' ?>>Aujourd'hui</option>
                    <option value="semaine" <?= $periode === 'semaine' ? 'selected' : '' ?>>Cette semaine</option>
                    <option value="mois"    <?= $periode === 'mois'    ? 'selected' : '' ?>>Ce mois</option>
                    <option value="annee"   <?= $periode === 'annee'   ? 'selected' : '' ?>>Cette année</option>
                    <option value="custom"  <?= $periode === 'custom'  ? 'selected' : '' ?>>Personnalisée</option>
                </select>
                <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>">
                <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>">
                <button type="submit" class="btn-submit">Appliquer</button>
            </form>
            <p class="text-muted" style="margin-top:10px; font-size:0.85rem;">
                Du <strong><?= date('d/m/Y', strtotime($date_debut)) ?></strong>
                au <strong><?= date('d/m/Y', strtotime($date_fin)) ?></strong>
            </p>
        </div>
    </section>

    <!-- Statistiques principales -->
    <section class="stats-grid">
        <div class="stat-card stat-green">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <p class="stat-label">Chiffre d'affaires</p>
                <h3 class="stat-value" style="font-size:1.2rem;">
                    <?= number_format((float)$stats['ca_total'], 2, ',', ' ') ?> <?= param('devise', '€') ?>
                </h3>
                <?php if ($evolution_ca !== null): ?>
                    <p class="stat-meta" style="color:<?= $evolution_ca >= 0 ? '#27ae60' : '#e74c3c' ?>;">
                        <?= $evolution_ca >= 0 ? '📈 +' : '📉 ' ?><?= $evolution_ca ?>%
                        vs période précédente
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="stat-card stat-blue">
            <div class="stat-icon">🛒</div>
            <div class="stat-content">
                <p class="stat-label">Commandes</p>
                <h3 class="stat-value"><?= (int)$stats['nb_commandes'] ?></h3>
                <?php if ($evolution_cmd !== null): ?>
                    <p class="stat-meta" style="color:<?= $evolution_cmd >= 0 ? '#27ae60' : '#e74c3c' ?>;">
                        <?= $evolution_cmd >= 0 ? '📈 +' : '📉 ' ?><?= $evolution_cmd ?>%
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="stat-card stat-orange">
            <div class="stat-icon">📦</div>
            <div class="stat-content">
                <p class="stat-label">Articles vendus</p>
                <h3 class="stat-value"><?= (int)$stats['nb_articles'] ?></h3>
            </div>
        </div>

        <div class="stat-card stat-blue">
            <div class="stat-icon">💳</div>
            <div class="stat-content">
                <p class="stat-label">Panier moyen</p>
                <h3 class="stat-value" style="font-size:1.1rem;">
                    <?= number_format((float)$stats['panier_moyen'], 2, ',', ' ') ?> <?= param('devise', '€') ?>
                </h3>
            </div>
        </div>
    </section>

    <!-- Graphique : évolution du CA -->
    <section class="card">
        <div class="card-header">
            <h2>📈 Évolution du chiffre d'affaires</h2>
        </div>
        <div class="card-body">
            <?php if (empty($ventes_par_jour)): ?>
                <p class="empty">Aucune vente sur cette période.</p>
            <?php else: ?>
                <canvas id="chartVentes" height="90"></canvas>
            <?php endif; ?>
        </div>
    </section>

    <!-- Graphiques côte à côte -->
    <section class="dashboard-row">
        <div class="card card-half">
            <div class="card-header">
                <h2>🏆 Répartition par produit</h2>
            </div>
            <div class="card-body">
                <?php if (empty($produits_stats)): ?>
                    <p class="empty">Aucune donnée.</p>
                <?php else: ?>
                    <canvas id="chartProduits" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-half">
            <div class="card-header">
                <h2>📊 Statuts des commandes</h2>
            </div>
            <div class="card-body">
                <canvas id="chartStatuts" height="200"></canvas>
                <ul class="list-top" style="margin-top:15px;">
                    <?php foreach ($statuts_data as $s => $n): ?>
                        <li>
                            <span class="badge <?= badge_statut($s) ?>" style="margin-right:10px;">
                                <?= ucfirst(str_replace('_', ' ', $s)) ?>
                            </span>
                            <span class="list-name"><?= $n ?> commande(s)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>

    <!-- Top clients + détail produits -->
    <section class="dashboard-row">
        <div class="card card-half">
            <div class="card-header">
                <h2>👥 Top 5 clients</h2>
            </div>
            <div class="card-body">
                <?php if (empty($top_clients)): ?>
                    <p class="empty">Aucun client sur cette période.</p>
                <?php else: ?>
                    <ul class="list-top">
                        <?php foreach ($top_clients as $i => $tc): ?>
                            <li>
                                <span class="rank rank-<?= $i + 1 ?>"><?= $i + 1 ?></span>
                                <span class="list-name">
                                    <strong><?= htmlspecialchars($tc['nom']) ?></strong><br>
                                    <small class="text-muted"><?= $tc['nb_commandes'] ?> commande(s)</small>
                                </span>
                                <span class="list-value">
                                    <?= number_format((float)$tc['total_depense'], 2, ',', ' ') ?> <?= param('devise', '€') ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-half">
            <div class="card-header">
                <h2>📋 Détail par produit</h2>
            </div>
            <div class="card-body">
                <?php if (empty($produits_stats)): ?>
                    <p class="empty">Aucune vente.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Vendus</th>
                                <th>CA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produits_stats as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['nom_produit']) ?></td>
                                    <td><?= (int)$p['total_vendu'] ?></td>
                                    <td><strong><?= number_format((float)$p['ca'], 2, ',', ' ') ?> <?= param('devise', '€') ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Prévisions -->
    <section class="card">
        <div class="card-header">
            <h2>🔮 Prévisions</h2>
        </div>
        <div class="card-body">
            <div class="prevision-grid">
                <div class="prevision-card">
                    <p class="prevision-label">CA moyen / jour (30 derniers jours)</p>
                    <h3 class="prevision-value">
                        <?= number_format($moyenne_jour, 2, ',', ' ') ?> <?= param('devise', '€') ?>
                    </h3>
                </div>
                <div class="prevision-card">
                    <p class="prevision-label">Projection sur 30 jours</p>
                    <h3 class="prevision-value" style="color:#27ae60;">
                        <?= number_format($prevision_mois, 2, ',', ' ') ?> <?= param('devise', '€') ?>
                    </h3>
                </div>
            </div>
            <p class="text-muted" style="margin-top:15px; font-size:0.8rem;">
                <em>Méthode : moyenne du CA quotidien × 30. Indicateur de tendance simple.</em>
            </p>
        </div>
    </section>

</main>

<!-- Chart.js scripts -->
<?php if (!empty($ventes_par_jour)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('chartVentes').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(fn($r) => date('d/m', strtotime($r['jour'])), $ventes_par_jour)) ?>,
            datasets: [{
                label: 'CA (€)',
                data: <?= json_encode(array_map(fn($r) => round((float)$r['ca'], 2), $ventes_par_jour)) ?>,
                borderColor: '#27ae60',
                backgroundColor: 'rgba(39, 174, 96, 0.15)',
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#27ae60',
                pointRadius: 4,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { callback: v => v + ' €' } } }
        }
    });
});
</script>
<?php endif; ?>

<?php if (!empty($produits_stats)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('chartProduits').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($produits_stats, 'nom_produit')) ?>,
            datasets: [{
                data: <?= json_encode(array_map(fn($p) => round((float)$p['ca'], 2), $produits_stats)) ?>,
                backgroundColor: ['#27ae60', '#3498db', '#f39c12', '#e74c3c', '#9b59b6', '#1abc9c', '#e67e22', '#34495e'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('chartStatuts').getContext('2d'), {
        type: 'pie',
        data: {
            labels: ['En attente', 'Confirmée', 'Expédiée', 'Livrée', 'Annulée'],
            datasets: [{
                data: <?= json_encode(array_values($statuts_data)) ?>,
                backgroundColor: ['#f39c12', '#3498db', '#9b59b6', '#27ae60', '#e74c3c'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
});
</script>

</body>
</html>