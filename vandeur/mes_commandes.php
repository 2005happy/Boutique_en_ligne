<?php
// vandeur/mes_commandes.php
require_once '../config/init.php';
require_once '../includes/auth_vendeur.php';

// ==========================================
// EXPORT CSV (uniquement les ventes du vendeur)
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->prepare("
        SELECT c.numero, c.date_commande, c.statut,
               cl.nom_produit, cl.prix_unitaire, cl.quantite, cl.sous_total,
               u.nom AS client
        FROM commande_lignes cl
        INNER JOIN produits p ON cl.produit_id = p.id
        INNER JOIN commandes c ON cl.commande_id = c.id
        LEFT JOIN utilisateurs u ON c.client_id = u.id
        WHERE p.vendeur_id = ?
        ORDER BY c.date_commande DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mes_ventes_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Numéro', 'Date', 'Statut', 'Client', 'Produit', 'Prix unitaire', 'Quantité', 'Sous-total'], ';');

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['numero'],
            date('d/m/Y H:i', strtotime($r['date_commande'])),
            ucfirst(str_replace('_', ' ', $r['statut'])),
            $r['client'] ?? 'Inconnu',
            $r['nom_produit'],
            number_format($r['prix_unitaire'], 2, ',', ' '),
            $r['quantite'],
            number_format($r['sous_total'], 2, ',', ' ')
        ], ';');
    }
    fclose($out);

    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'EXPORT_VENTES', "Export CSV des ventes (" . count($rows) . " lignes)"]);
    exit;
}

// ==========================================
// FILTRES
// ==========================================
$recherche = trim($_GET['recherche'] ?? '');
$filtre_statut = $_GET['statut'] ?? '';
$tri = $_GET['tri'] ?? 'recent';

$statuts_dispo = ['en_attente', 'confirmee', 'expediee', 'livree', 'annulee'];

// ==========================================
// CONSTRUCTION DE LA REQUÊTE
// Le vendeur ne voit QUE les commandes contenant SES produits
// ==========================================
$where = "WHERE p.vendeur_id = :vendeur_id";
$params = [':vendeur_id' => $_SESSION['user_id']];

if ($recherche !== '') {
    $where .= " AND (c.numero LIKE :rech OR u.nom LIKE :rech OR cl.nom_produit LIKE :rech)";
    $params[':rech'] = "%$recherche%";
}
if ($filtre_statut !== '' && in_array($filtre_statut, $statuts_dispo, true)) {
    $where .= " AND c.statut = :statut";
    $params[':statut'] = $filtre_statut;
}

$order = match($tri) {
    'ancien'  => "c.date_commande ASC",
    'montant' => "cl.sous_total DESC",
    default   => "c.date_commande DESC",
};

// Récupération groupée : une ligne par article vendu
$sql = "
    SELECT cl.id AS ligne_id, cl.quantite, cl.sous_total, cl.prix_unitaire, cl.nom_produit,
           c.id AS commande_id, c.numero, c.date_commande, c.statut, c.client_id,
           u.nom AS client, u.email
    FROM commande_lignes cl
    INNER JOIN produits p ON cl.produit_id = p.id
    INNER JOIN commandes c ON cl.commande_id = c.id
    LEFT JOIN utilisateurs u ON c.client_id = u.id
    $where
    ORDER BY $order
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lignes = $stmt->fetchAll();

// ==========================================
// STATISTIQUES
// ==========================================
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT c.id) AS nb_commandes,
           COALESCE(SUM(cl.sous_total), 0) AS ca_total,
           COALESCE(SUM(cl.quantite), 0) AS nb_articles
    FROM commande_lignes cl
    INNER JOIN produits p ON cl.produit_id = p.id
    INNER JOIN commandes c ON cl.commande_id = c.id
    WHERE p.vendeur_id = ? AND c.statut != 'annulee'
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

// Commandes en attente (à traiter)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT c.id)
    FROM commande_lignes cl
    INNER JOIN produits p ON cl.produit_id = p.id
    INNER JOIN commandes c ON cl.commande_id = c.id
    WHERE p.vendeur_id = ? AND c.statut IN ('en_attente', 'confirmee')
");
$stmt->execute([$_SESSION['user_id']]);
$nb_a_traiter = (int)$stmt->fetchColumn();

// Top 5 produits vendus
$stmt = $pdo->prepare("
    SELECT cl.nom_produit, SUM(cl.quantite) AS total_vendu, SUM(cl.sous_total) AS ca
    FROM commande_lignes cl
    INNER JOIN produits p ON cl.produit_id = p.id
    INNER JOIN commandes c ON cl.commande_id = c.id
    WHERE p.vendeur_id = ? AND c.statut != 'annulee'
    GROUP BY cl.nom_produit
    ORDER BY total_vendu DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$top_produits = $stmt->fetchAll();

// ==========================================
// HELPERS
// ==========================================
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
    <title>Mes ventes - Espace Vendeur</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/../includes/sidebar_vendeur.php'; ?>

<main class="admin-main">

    <header class="admin-topbar">
        <div>
            <h1>🛒 Mes ventes</h1>
            <p class="topbar-subtitle">Toutes les commandes contenant vos produits</p>
        </div>
        <div class="topbar-actions">
            <a href="?export=csv&<?= http_build_query([
                'recherche'=>$recherche,'statut'=>$filtre_statut
            ]) ?>" class="btn-secondary" title="Exporter CSV">📤 Exporter CSV</a>
            <div class="topbar-user">
                <span class="avatar"><?= strtoupper(substr($_SESSION['nom'], 0, 1)) ?></span>
                <span><?= htmlspecialchars($_SESSION['nom']) ?></span>
            </div>
        </div>
    </header>

    <!-- Statistiques -->
    <section class="stats-grid">
        <div class="stat-card stat-blue">
            <div class="stat-icon">🛒</div>
            <div class="stat-content">
                <p class="stat-label">Commandes</p>
                <h3 class="stat-value"><?= (int)$stats['nb_commandes'] ?></h3>
            </div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <p class="stat-label">Chiffre d'affaires</p>
                <h3 class="stat-value" style="font-size:1.1rem;">
                    <?= number_format((float)$stats['ca_total'], 2, ',', ' ') ?> <?= param('devise', '€') ?>
                </h3>
            </div>
        </div>
        <div class="stat-card stat-orange">
            <div class="stat-icon">📦</div>
            <div class="stat-content">
                <p class="stat-label">Articles vendus</p>
                <h3 class="stat-value"><?= (int)$stats['nb_articles'] ?></h3>
            </div>
        </div>
        <div class="stat-card stat-red">
            <div class="stat-icon">⏳</div>
            <div class="stat-content">
                <p class="stat-label">À traiter</p>
                <h3 class="stat-value"><?= $nb_a_traiter ?></h3>
            </div>
        </div>
    </section>

    <!-- Top produits vendus -->
    <?php if (!empty($top_produits)): ?>
    <section class="dashboard-row">
        <div class="card">
            <div class="card-header">
                <h2>🏆 Mes meilleures ventes</h2>
            </div>
            <div class="card-body">
                <ul class="list-top">
                    <?php foreach ($top_produits as $i => $tp): ?>
                        <li>
                            <span class="rank rank-<?= $i + 1 ?>"><?= $i + 1 ?></span>
                            <span class="list-name"><?= htmlspecialchars($tp['nom_produit']) ?></span>
                            <span class="list-value"><?= $tp['total_vendu'] ?> vendu(s)</span>
                            <span class="list-stock">
                                <?= number_format((float)$tp['ca'], 2, ',', ' ') ?> <?= param('devise', '€') ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Filtres -->
    <section class="card">
        <div class="card-header">
            <h2>🔍 Recherche & filtres</h2>
        </div>
        <div class="card-body">
            <form method="GET" action="mes_commandes.php" class="filters-bar">
                <input type="text" name="recherche" placeholder="Numéro, client, produit..."
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
                </select>

                <button type="submit" class="btn-submit">Filtrer</button>
                <a href="mes_commandes.php" class="btn-secondary">Réinitialiser</a>
            </form>
        </div>
    </section>

    <!-- Liste des ventes -->
    <section class="card">
        <div class="card-header">
            <h2>📋 Mes ventes (<?= count($lignes) ?>)</h2>
        </div>
        <div class="card-body">
            <?php if (empty($lignes)): ?>
                <p class="empty">
                    Aucune vente pour le moment.
                    <?php if ($recherche || $filtre_statut): ?>
                        <br><a href="mes_commandes.php" class="link">Réinitialiser les filtres</a>
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <div class="table-responsive">
                <table class="data-table order-table order-table-wide">
                    <thead>
                        <tr>
                            <th>Commande</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Produit vendu</th>
                            <th>Qté</th>
                            <th>Sous-total</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lignes as $l): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($l['numero']) ?></strong></td>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($l['date_commande'])) ?></strong><br>
                                    <small class="text-muted"><?= date('H:i', strtotime($l['date_commande'])) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($l['client'] ?? 'Inconnu') ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($l['email'] ?? '') ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($l['nom_produit']) ?><br>
                                    <small class="text-muted">
                                        <?= number_format((float)$l['prix_unitaire'], 2, ',', ' ') ?> <?= param('devise', '€') ?> / unité
                                    </small>
                                </td>
                                <td><?= (int)$l['quantite'] ?></td>
                                <td>
                                    <strong style="color:#27ae60;">
                                        <?= number_format((float)$l['sous_total'], 2, ',', ' ') ?> <?= param('devise', '€') ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge <?= badge_statut($l['statut']) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $l['statut'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <p class="text-muted" style="margin-top:15px; font-size:0.85rem;">
                    💡 Vous ne voyez que vos produits. Pour le détail complet d'une commande
                    (adresse de livraison, autres articles), contactez l'administrateur.
                </p>
            <?php endif; ?>
        </div>
    </section>

</main>
</body>
</html>