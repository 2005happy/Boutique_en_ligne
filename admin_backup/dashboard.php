<?php
// admin/dashboard.php
require_once '../config/init.php';
if (session_status() === PHP_SESSION_NONE)
    session_start();
// echo "<pre>SESSION sur dashboard : ";
// var_dump($_SESSION);
// echo "</pre>";
// exit;
require_once '../includes/auth.php';

// ==========================================
// RÉCUPÉRATION DES STATISTIQUES (Critère 28)
// ==========================================

$stats = [];

try {
    // Nombre total d'utilisateurs
    $stats['utilisateurs'] = $pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();

    // Nombre de produits actifs
    $stats['produits'] = $pdo->query("SELECT COUNT(*) FROM produits WHERE archive = 0")->fetchColumn();

    // Nombre de catégories
    $stats['categories'] = $pdo->query("SELECT COUNT(*) FROM categories WHERE archive = 0")->fetchColumn();

    // Valeur totale du stock
    $stats['valeur_stock'] = $pdo->query("SELECT COALESCE(SUM(prix * stock), 0) FROM produits WHERE archive = 0")->fetchColumn();

    // Produits en rupture de stock (< 5)
    $stats['rupture'] = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock < 5 AND archive = 0")->fetchColumn();

    // Documents téléversés
    $stats['documents'] = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();

    // Connexions aujourd'hui
    $stats['connexions_jour'] = $pdo->query("
        SELECT COUNT(*) FROM historique_connexions
        WHERE DATE(date_connexion) = CURDATE()
    ")->fetchColumn();

    // Chiffre d'affaires simulé (somme des prix × quantités vendues — si table commandes existe)
    $stats['ca_mois'] = 0;
    try {
        $stats['ca_mois'] = $pdo->query("
            SELECT COALESCE(SUM(total), 0) FROM commandes
            WHERE MONTH(date_commande) = MONTH(CURDATE())
            AND YEAR(date_commande) = YEAR(CURDATE())
        ")->fetchColumn();
    } catch (PDOException $e) {
        // Table commandes non encore créée
    }

    // ==========================================
    // DONNÉES POUR GRAPHIQUES
    // ==========================================

    // Produits par catégorie (Critère 31 : analyse comparative)
    $produitsParCategorie = $pdo->query("
        SELECT c.nom AS categorie, COUNT(p.id) AS nb
        FROM categories c
        LEFT JOIN produits p ON p.categorie_id = c.id AND p.archive = 0
        WHERE c.archive = 0
        GROUP BY c.id, c.nom
        ORDER BY nb DESC
    ")->fetchAll();

    // Top 5 produits les plus chers (Critère 17 : tri)
    $topProduits = $pdo->query("
        SELECT nom, prix, stock FROM produits
        WHERE archive = 0
        ORDER BY prix DESC
        LIMIT 5
    ")->fetchAll();

    // Produits en rupture (alerte automatique — Critère 37)
    $alertesStock = $pdo->query("
        SELECT nom, stock FROM produits
        WHERE stock < 5 AND archive = 0
        ORDER BY stock ASC
        LIMIT 5
    ")->fetchAll();

    // Dernières activités (Critère 10)
    $dernieresActivites = $pdo->query("
        SELECT j.action, j.details, j.date_action, u.nom AS utilisateur
        FROM journal_activites j
        LEFT JOIN utilisateurs u ON j.utilisateur_id = u.id
        ORDER BY j.date_action DESC
        LIMIT 8
    ")->fetchAll();

    // Dernières connexions (Critère 6)
    $dernieresConnexions = $pdo->query("
        SELECT h.date_connexion, h.ip_adresse, u.nom, u.email
        FROM historique_connexions h
        LEFT JOIN utilisateurs u ON h.utilisateur_id = u.id
        ORDER BY h.date_connexion DESC
        LIMIT 5
    ")->fetchAll();

    // Produits récemment ajoutés (Critère 14)
    $produitsRecents = $pdo->query("
        SELECT nom, prix, stock, cree_le FROM produits
        WHERE archive = 0
        ORDER BY cree_le DESC
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    die("Erreur lors du chargement des statistiques : " . $e->getMessage());
}

// Compteur de notifications (Critère 34, 37)
$notifications = count($alertesStock);
?>
<?php
$nb_attente = (int) $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE statut = 'en_attente'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair'), ENT_QUOTES, 'UTF-8') ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="admin-body">

    <!-- ==========================================
     SIDEBAR : MENU COMPLET (Critères 2, 3, 44)
     ========================================== -->
    <?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- ==========================================
     CONTENU PRINCIPAL
     ========================================== -->
    <main class="admin-main">

        <!-- Barre supérieure -->
        <header class="admin-topbar">
            <div>
                <h1>Tableau de bord</h1>
                <p class="topbar-subtitle">
                    Bienvenue, <strong><?= htmlspecialchars($_SESSION['nom']) ?></strong> —
                    <?= date('d/m/Y H:i') ?>
                </p>
            </div>
            <div class="topbar-actions">
                <a href="products.php" class="topbar-icon" title="Produits à faible stock">
                    🔔
                    <?php if ($notifications > 0): ?>
                        <span class="badge-notif-top"><?= $notifications ?></span>
                    <?php endif; ?>
                </a>
                <a href="../index.php" target="_blank" class="topbar-icon" title="Voir le site">
                    🌐
                </a>
                <div class="topbar-user">
                    <span class="avatar"><?= strtoupper(substr($_SESSION['nom'], 0, 1)) ?></span>
                    <span><?= htmlspecialchars($_SESSION['nom']) ?></span>
                </div>
            </div>
        </header>

        <!-- ==========================================
         CARTES DE STATISTIQUES (Critère 28)
         ========================================== -->
        <section class="stats-grid">
            <div class="stat-card stat-blue">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <p class="stat-label">Utilisateurs</p>
                    <h3 class="stat-value"><?= number_format($stats['utilisateurs']) ?></h3>
                    <p class="stat-meta"><?= $stats['connexions_jour'] ?> connexion(s) aujourd'hui</p>
                </div>
            </div>

            <div class="stat-card stat-green">
                <div class="stat-icon">📦</div>
                <div class="stat-content">
                    <p class="stat-label">Produits actifs</p>
                    <h3 class="stat-value"><?= number_format($stats['produits']) ?></h3>
                    <p class="stat-meta"><?= $stats['categories'] ?> catégorie(s)</p>
                </div>
            </div>

            <div class="stat-card stat-orange">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <p class="stat-label">Valeur du stock</p>
                    <h3 class="stat-value"><?= number_format($stats['valeur_stock'], 2, ',', ' ') ?> <?= htmlspecialchars(param('devise', '€')) ?></h3>
                    <p class="stat-meta">CA mois : <?= number_format($stats['ca_mois'], 2, ',', ' ') ?> <?= htmlspecialchars(param('devise', '€')) ?></p>
                </div>
            </div>

            <div class="stat-card stat-red">
                <div class="stat-icon">⚠️</div>
                <div class="stat-content">
                    <p class="stat-label">Alertes stock</p>
                    <h3 class="stat-value"><?= number_format($stats['rupture']) ?></h3>
                    <p class="stat-meta"><?= $stats['documents'] ?> document(s)</p>
                </div>
            </div>
        </section>

        <?php if ($nb_attente > 0): ?>
            <div class="alert alert-info" style="background:#fef5e7;border-left-color:#f39c12;color:#b9770e;">
                ⏳ <strong>
                    <?= $nb_attente ?>
                </strong> compte(s) vendeur(s) en attente de validation.
                <a href="pending_users.php" style="font-weight:700;text-decoration:underline;">Valider maintenant →</a>
            </div>
        <?php endif; ?>

        <!-- ==========================================
         GRAPHIQUE (Critères 28, 31)
         ========================================== -->
        <section class="dashboard-row">
            <div class="card card-half">
                <div class="card-header">
                    <h2>📊 Répartition par catégorie</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($produitsParCategorie)): ?>
                        <p class="empty">Aucune donnée disponible.</p>
                    <?php else: ?>
                        <canvas id="chartCategories" height="200"></canvas>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card card-half">
                <div class="card-header">
                    <h2>🏆 Top 5 produits (prix)</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($topProduits)): ?>
                        <p class="empty">Aucun produit enregistré.</p>
                    <?php else: ?>
                        <ul class="list-top">
                            <?php foreach ($topProduits as $i => $p): ?>
                                <li>
                                    <span class="rank rank-<?= $i + 1 ?>"><?= $i + 1 ?></span>
                                    <span class="list-name"><?= htmlspecialchars($p['nom']) ?></span>
                                    <span class="list-value"><?= number_format($p['prix'], 2, ',', ' ') ?> <?= htmlspecialchars(param('devise', '€')) ?></span>
                                    <span class="list-stock <?= $p['stock'] < 5 ? 'low' : '' ?>">
                                        stock : <?= $p['stock'] ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ==========================================
         ALERTES + ACTIVITÉS + CONNEXIONS
         ========================================== -->
        <section class="dashboard-row">
            <!-- Alertes stock (Critère 37) -->
            <div class="card card-third">
                <div class="card-header">
                    <h2>⚠️ Alertes stock</h2>
                    <a href="products.php" class="card-link">Tout voir →</a>
                </div>
                <div class="card-body">
                    <?php if (empty($alertesStock)): ?>
                        <p class="empty">Aucune alerte. Tout est OK ✅</p>
                    <?php else: ?>
                        <ul class="list-alerts">
                            <?php foreach ($alertesStock as $a): ?>
                                <li>
                                    <span class="alert-dot"></span>
                                    <span class="list-name"><?= htmlspecialchars($a['nom']) ?></span>
                                    <span class="list-badge low"><?= $a['stock'] ?> restant(s)</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dernières activités (Critère 10) -->
            <div class="card card-third">
                <div class="card-header">
                    <h2>📝 Activités récentes</h2>
                    <a href="activities.php" class="card-link">Tout voir →</a>
                </div>
                <div class="card-body">
                    <?php if (empty($dernieresActivites)): ?>
                        <p class="empty">Aucune activité enregistrée.</p>
                    <?php else: ?>
                        <ul class="list-activities">
                            <?php foreach ($dernieresActivites as $act): ?>
                                <li>
                                    <strong><?= htmlspecialchars($act['utilisateur'] ?? 'Système') ?></strong>
                                    <span class="activity-action"><?= htmlspecialchars($act['action']) ?></span>
                                    <small><?= date('d/m H:i', strtotime($act['date_action'])) ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Connexions récentes (Critère 6) -->
            <div class="card card-third">
                <div class="card-header">
                    <h2>🔌 Connexions récentes</h2>
                    <a href="connections.php" class="card-link">Tout voir →</a>
                </div>
                <div class="card-body">
                    <?php if (empty($dernieresConnexions)): ?>
                        <p class="empty">Aucune connexion enregistrée.</p>
                    <?php else: ?>
                        <ul class="list-connections">
                            <?php foreach ($dernieresConnexions as $c): ?>
                                <li>
                                    <span class="user-avatar"><?= strtoupper(substr($c['nom'] ?? '?', 0, 1)) ?></span>
                                    <div>
                                        <strong><?= htmlspecialchars($c['nom'] ?? 'Inconnu') ?></strong>
                                        <small><?= htmlspecialchars($c['ip_adresse']) ?></small>
                                    </div>
                                    <small><?= date('d/m H:i', strtotime($c['date_connexion'])) ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ==========================================
         PRODUITS RÉCENTS (Critère 14)
         ========================================== -->
        <section class="card">
            <div class="card-header">
                <h2>🆕 Produits récemment ajoutés</h2>
                <a href="products.php" class="card-link">Gérer →</a>
            </div>
            <div class="card-body">
                <?php if (empty($produitsRecents)): ?>
                    <p class="empty">Aucun produit. <a href="products.php" class="link">Ajouter un produit</a></p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Prix</th>
                                <th>Stock</th>
                                <th>Date d'ajout</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produitsRecents as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['nom']) ?></td>
                                    <td><?= number_format($p['prix'], 2, ',', ' ') ?> <?= htmlspecialchars(param('devise', '€')) ?></td>
                                    <td>
                                        <span class="badge <?= $p['stock'] < 5 ? 'badge-danger' : 'badge-success' ?>">
                                            <?= $p['stock'] ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($p['cree_le'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>

    </main>

    <!-- ==========================================
     SCRIPT CHART.JS (Critère 31)
     ========================================== -->
    <?php if (!empty($produitsParCategorie)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const ctx = document.getElementById('chartCategories').getContext('2d');
                const labels = <?= json_encode(array_column($produitsParCategorie, 'categorie')) ?>;
                const data = <?= json_encode(array_map('intval', array_column($produitsParCategorie, 'nb'))) ?>;

                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: [
                                '#3498db', '#27ae60', '#f39c12', '#e74c3c',
                                '#9b59b6', '#1abc9c', '#e67e22', '#34495e'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            });
        </script>
    <?php endif; ?>

</body>

</html>