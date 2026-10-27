<?php
// admin/reports.php
require_once '../config/init.php';
require_once '../includes/auth.php';
require_once '../vendor/autoload.php'; // ⚠️ adaptez le chemin si besoin

// ==========================================
// PARAMÈTRES DE PÉRIODE (Critère 29)
// ==========================================
$periode = $_GET['periode'] ?? 'mois'; // jour, semaine, mois, annee, custom
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');

switch ($periode) {
    case 'jour':   $date_debut = date('Y-m-d'); $date_fin = date('Y-m-d'); break;
    case 'semaine': $date_debut = date('Y-m-d', strtotime('monday this week')); $date_fin = date('Y-m-d'); break;
    case 'mois':   $date_debut = date('Y-m-01'); $date_fin = date('Y-m-d'); break;
    case 'annee':  $date_debut = date('Y-01-01'); $date_fin = date('Y-m-d'); break;
    case 'custom': break; // garder les dates du formulaire
}

// ==========================================
// GÉNÉRATION PDF (Critère 26)
// ==========================================
if (isset($_GET['pdf'])) {
    $type_rapport = $_GET['pdf'];

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    // En-tête
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(44, 62, 80);
    $nom_boutique_pdf = param('nom_boutique', 'Ma Boutique');
    $devise_pdf = utf8_decode(' ' . param('devise', '€'));
    $pdf->Cell(0, 10, utf8_decode($nom_boutique_pdf . ' — Rapport'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(127, 140, 141);
    $pdf->Cell(0, 6, utf8_decode("Période : " . date('d/m/Y', strtotime($date_debut)) . " au " . date('d/m/Y', strtotime($date_fin))), 0, 1, 'C');
    $pdf->Cell(0, 6, utf8_decode("Généré le " . date('d/m/Y à H:i') . " par " . $_SESSION['nom']), 0, 1, 'C');
    $pdf->Ln(5);

    // Ligne de séparation
    $pdf->SetDrawColor(52, 152, 219);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(5);

    if ($type_rapport === 'produits') {
        // ---- Rapport produits en stock ----
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetTextColor(44, 62, 80);
        $pdf->Cell(0, 8, utf8_decode("État du stock des produits"), 0, 1);
        $pdf->Ln(3);

        // En-têtes
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(52, 152, 219);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(70, 8, 'Produit', 1, 0, 'L', true);
        $pdf->Cell(30, 8, 'Catégorie', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Prix', 1, 0, 'R', true);
        $pdf->Cell(20, 8, 'Stock', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Valeur', 1, 1, 'R', true);

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(44, 62, 80);
        $total_valeur = 0;
        $stmt = $pdo->query("
            SELECT p.nom, p.prix, p.stock, c.nom AS categorie
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            WHERE p.archive = 0
            ORDER BY c.nom, p.nom
        ");
        $fill = false;
        while ($row = $stmt->fetch()) {
            $valeur = $row['prix'] * $row['stock'];
            $total_valeur += $valeur;
            $pdf->SetFillColor(245, 247, 250);
            $pdf->Cell(70, 7, utf8_decode(mb_substr($row['nom'], 0, 38)), 1, 0, 'L', $fill);
            $pdf->Cell(30, 7, utf8_decode($row['categorie'] ?? '—'), 1, 0, 'C', $fill);
            $pdf->Cell(25, 7, number_format($row['prix'], 2, ',', ' ') . $devise_pdf, 1, 0, 'R', $fill);
            $pdf->Cell(20, 7, $row['stock'], 1, 0, 'C', $fill);
            $pdf->Cell(30, 7, number_format($valeur, 2, ',', ' ') . $devise_pdf, 1, 1, 'R', $fill);
            $fill = !$fill;
        }
        // Total
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(145, 8, 'Valeur totale du stock', 1, 0, 'R');
        $pdf->Cell(30, 8, number_format($total_valeur, 2, ',', ' ') . $devise_pdf, 1, 1, 'R');

    } elseif ($type_rapport === 'utilisateurs') {
        // ---- Rapport utilisateurs ----
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 8, utf8_decode("Liste des utilisateurs"), 0, 1);
        $pdf->Ln(3);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(52, 152, 219);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(15, 8, 'ID', 1, 0, 'C', true);
        $pdf->Cell(55, 8, 'Nom', 1, 0, 'L', true);
        $pdf->Cell(60, 8, 'Email', 1, 0, 'L', true);
        $pdf->Cell(25, 8, 'Rôle', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Inscription', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(44, 62, 80);
        $stmt = $pdo->query("SELECT id, nom, email, role, cree_le FROM utilisateurs ORDER BY cree_le DESC");
        $fill = false;
        while ($row = $stmt->fetch()) {
            $pdf->SetFillColor(245, 247, 250);
            $pdf->Cell(15, 7, $row['id'], 1, 0, 'C', $fill);
            $pdf->Cell(55, 7, utf8_decode($row['nom']), 1, 0, 'L', $fill);
            $pdf->Cell(60, 7, utf8_decode($row['email']), 1, 0, 'L', $fill);
            $pdf->Cell(25, 7, ucfirst($row['role']), 1, 0, 'C', $fill);
            $pdf->Cell(25, 7, date('d/m/Y', strtotime($row['cree_le'])), 1, 1, 'C', $fill);
            $fill = !$fill;
        }

    } elseif ($type_rapport === 'activites') {
        // ---- Rapport activités ----
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 8, utf8_decode("Journal d'activités"), 0, 1);
        $pdf->Ln(3);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(52, 152, 219);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(30, 8, 'Date', 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'Utilisateur', 1, 0, 'L', true);
        $pdf->Cell(40, 8, 'Action', 1, 0, 'L', true);
        $pdf->Cell(80, 8, 'Détails', 1, 1, 'L', true);

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(44, 62, 80);
        $stmt = $pdo->prepare("
            SELECT j.date_action, j.action, j.details, u.nom
            FROM journal_activites j
            LEFT JOIN utilisateurs u ON j.utilisateur_id = u.id
            WHERE DATE(j.date_action) BETWEEN ? AND ?
            ORDER BY j.date_action DESC
        ");
        $stmt->execute([$date_debut, $date_fin]);
        $fill = false;
        while ($row = $stmt->fetch()) {
            $pdf->SetFillColor(245, 247, 250);
            $pdf->Cell(30, 6, date('d/m H:i', strtotime($row['date_action'])), 1, 0, 'C', $fill);
            $pdf->Cell(40, 6, utf8_decode(mb_substr($row['nom'] ?? 'Système', 0, 20)), 1, 0, 'L', $fill);
            $pdf->Cell(40, 6, utf8_decode(mb_substr($row['action'], 0, 20)), 1, 0, 'L', $fill);
            $pdf->Cell(80, 6, utf8_decode(mb_substr($row['details'] ?? '', 0, 45)), 1, 1, 'L', $fill);
            $fill = !$fill;
        }

    } elseif ($type_rapport === 'statistiques') {
        // ---- Rapport statistiques ----
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 8, utf8_decode("Statistiques générales"), 0, 1);
        $pdf->Ln(3);

        $stats = [
            'Utilisateurs total'         => $pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn(),
            'Produits actifs'            => $pdo->query("SELECT COUNT(*) FROM produits WHERE archive = 0")->fetchColumn(),
            'Catégories actives'         => $pdo->query("SELECT COUNT(*) FROM categories WHERE archive = 0")->fetchColumn(),
            'Valeur totale du stock'     => number_format($pdo->query("SELECT COALESCE(SUM(prix * stock), 0) FROM produits WHERE archive = 0")->fetchColumn(), 2, ',', ' ') . ' ' . param('devise', '€'),
            'Produits en rupture'        => $pdo->query("SELECT COUNT(*) FROM produits WHERE stock < 5 AND archive = 0")->fetchColumn(),
            'Documents téléversés'       => $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
            'Connexions (période)'       => $pdo->prepare("SELECT COUNT(*) FROM historique_connexions WHERE DATE(date_connexion) BETWEEN ? AND ?")->execute([$date_debut, $date_fin]) ?: '—',
            'Activités (période)'        => $pdo->prepare("SELECT COUNT(*) FROM journal_activites WHERE DATE(date_action) BETWEEN ? AND ?")->execute([$date_debut, $date_fin]) ?: '—',
        ];

        $pdf->SetFont('Arial', '', 10);
        foreach ($stats as $label => $val) {
            $pdf->SetFillColor(245, 247, 250);
            $pdf->Cell(100, 8, utf8_decode($label), 1, 0, 'L', true);
            $pdf->Cell(60, 8, utf8_decode((string)$val), 1, 1, 'C');
        }
    }

    // Pied de page
    $pdf->SetY(-15);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(149, 165, 166);
    $pdf->Cell(0, 10, utf8_decode($nom_boutique_pdf . ' — Rapport généré automatiquement — Page ' . $pdf->PageNo()), 0, 0, 'C');

    // Journalisation
    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'EXPORT_PDF', "Rapport PDF : $type_rapport"]);

    $pdf->Output('D', "rapport_{$type_rapport}_" . date('Y-m-d') . '.pdf');
    exit;
}

// ==========================================
// EXPORT EXCEL / CSV (Critère 27)
// ==========================================
if (isset($_GET['excel'])) {
    $type = $_GET['excel'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="export_' . $type . '_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8

    if ($type === 'produits') {
        fputcsv($out, ['ID', 'Nom', 'Catégorie', 'Prix', 'Stock', 'Valeur'], ';');
        $rows = $pdo->query("
            SELECT p.id, p.nom, c.nom AS categorie, p.prix, p.stock
            FROM produits p LEFT JOIN categories c ON p.categorie_id = c.id
            WHERE p.archive = 0 ORDER BY p.nom
        ")->fetchAll();
        foreach ($rows as $r) {
            fputcsv($out, [$r['id'], $r['nom'], $r['categorie'] ?? '', $r['prix'], $r['stock'], $r['prix'] * $r['stock']], ';');
        }
    } elseif ($type === 'utilisateurs') {
        fputcsv($out, ['ID', 'Nom', 'Email', 'Rôle', 'Inscription'], ';');
        $rows = $pdo->query("SELECT id, nom, email, role, cree_le FROM utilisateurs ORDER BY nom")->fetchAll();
        foreach ($rows as $r) {
            fputcsv($out, [$r['id'], $r['nom'], $r['email'], $r['role'], date('d/m/Y', strtotime($r['cree_le']))], ';');
        }
    } elseif ($type === 'activites') {
        fputcsv($out, ['Date', 'Utilisateur', 'Action', 'Détails'], ';');
        $stmt = $pdo->prepare("
            SELECT j.date_action, j.action, j.details, u.nom
            FROM journal_activites j LEFT JOIN utilisateurs u ON j.utilisateur_id = u.id
            WHERE DATE(j.date_action) BETWEEN ? AND ?
            ORDER BY j.date_action DESC
        ");
        $stmt->execute([$date_debut, $date_fin]);
        foreach ($stmt->fetchAll() as $r) {
            fputcsv($out, [date('d/m/Y H:i', strtotime($r['date_action'])), $r['nom'] ?? 'Système', $r['action'], $r['details']], ';');
        }
    }

    fclose($out);

    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'EXPORT_EXCEL', "Export CSV : $type"]);

    exit;
}

// ==========================================
// STATISTIQUES POUR LE DASHBOARD
// ==========================================
$total_produits = (int)$pdo->query("SELECT COUNT(*) FROM produits WHERE archive = 0")->fetchColumn();
$total_users    = (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
$valeur_stock   = (float)$pdo->query("SELECT COALESCE(SUM(prix * stock), 0) FROM produits WHERE archive = 0")->fetchColumn();
$total_docs     = (int)$pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();

// Connexions sur la période
$stmt = $pdo->prepare("SELECT COUNT(*) FROM historique_connexions WHERE DATE(date_connexion) BETWEEN ? AND ?");
$stmt->execute([$date_debut, $date_fin]);
$connexions_periode = (int)$stmt->fetchColumn();

// Activités sur la période
$stmt = $pdo->prepare("SELECT COUNT(*) FROM journal_activites WHERE DATE(date_action) BETWEEN ? AND ?");
$stmt->execute([$date_debut, $date_fin]);
$activites_periode = (int)$stmt->fetchColumn();

// Produits par catégorie (Critère 31)
$produits_par_cat = $pdo->query("
    SELECT c.nom AS categorie, COUNT(p.id) AS nb, COALESCE(SUM(p.prix * p.stock), 0) AS valeur
    FROM categories c
    LEFT JOIN produits p ON p.categorie_id = c.id AND p.archive = 0
    WHERE c.archive = 0
    GROUP BY c.id, c.nom
    ORDER BY nb DESC
")->fetchAll();

// Top 5 produits par valeur de stock
$top_produits = $pdo->query("
    SELECT nom, prix, stock, (prix * stock) AS valeur
    FROM produits WHERE archive = 0
    ORDER BY valeur DESC LIMIT 5
")->fetchAll();

// Activité par jour (période) — pour graphique
$stmt = $pdo->prepare("
    SELECT DATE(date_action) AS jour, COUNT(*) AS nb
    FROM journal_activites
    WHERE DATE(date_action) BETWEEN ? AND ?
    GROUP BY DATE(date_action)
    ORDER BY jour ASC
");
$stmt->execute([$date_debut, $date_fin]);
$activite_par_jour = $stmt->fetchAll();

// Prévision simple (Critère 32) : moyenne des 7 derniers jours × 30
$stmt = $pdo->prepare("
    SELECT DATE(date_action) AS jour, COUNT(*) AS nb
    FROM journal_activites
    WHERE date_action >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(date_action)
");
$stmt->execute();
$jours_actifs = $stmt->fetchAll();
$moyenne_jour = count($jours_actifs) > 0
    ? array_sum(array_column($jours_actifs, 'nb')) / count($jours_actifs)
    : 0;
$prevision_mois = round($moyenne_jour * 30);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair'), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports et statistiques - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-body">

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="admin-main">

    <header class="admin-topbar">
        <div>
            <h1>📈 Rapports & Statistiques</h1>
            <p class="topbar-subtitle">Rapports PDF, exports Excel, analyses et prévisions</p>
        </div>
        <div class="topbar-actions">
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
            <form method="GET" action="reports.php" class="filters-bar">
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
                Période analysée : du <strong><?= date('d/m/Y', strtotime($date_debut)) ?></strong>
                au <strong><?= date('d/m/Y', strtotime($date_fin)) ?></strong>
            </p>
        </div>
    </section>

    <!-- Statistiques de la période -->
    <section class="stats-grid">
        <div class="stat-card stat-blue">
            <div class="stat-icon">📦</div>
            <div class="stat-content">
                <p class="stat-label">Produits actifs</p>
                <h3 class="stat-value"><?= $total_produits ?></h3>
            </div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <p class="stat-label">Utilisateurs</p>
                <h3 class="stat-value"><?= $total_users ?></h3>
            </div>
        </div>
        <div class="stat-card stat-orange">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <p class="stat-label">Valeur du stock</p>
                <h3 class="stat-value"><?= number_format($valeur_stock, 0, ',', ' ') ?> <?= htmlspecialchars(param('devise', '€')) ?></h3>
            </div>
        </div>
        <div class="stat-card stat-red">
            <div class="stat-icon">📈</div>
            <div class="stat-content">
                <p class="stat-label">Activités / période</p>
                <h3 class="stat-value"><?= $activites_periode ?></h3>
                <p class="stat-meta"><?= $connexions_periode ?> connexion(s)</p>
            </div>
        </div>
    </section>

    <!-- Rapport PDF + Export Excel (Critères 26, 27) -->
    <section class="dashboard-row">
        <div class="card card-half">
            <div class="card-header">
                <h2>📄 Rapports PDF</h2>
            </div>
            <div class="card-body">
                <p class="text-muted" style="margin-bottom:15px; font-size:0.85rem;">
                    Génère un PDF prêt à imprimer pour la période sélectionnée.
                </p>
                <div class="report-buttons">
                    <a href="?pdf=statistiques&periode=<?= $periode ?>&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>" class="btn-secondary">📊 Statistiques</a>
                    <a href="?pdf=produits&periode=<?= $periode ?>&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>" class="btn-secondary">📦 Produits</a>
                    <a href="?pdf=utilisateurs" class="btn-secondary">👥 Utilisateurs</a>
                    <a href="?pdf=activites&periode=<?= $periode ?>&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>" class="btn-secondary">📝 Activités</a>
                </div>
            </div>
        </div>

        <div class="card card-half">
            <div class="card-header">
                <h2>📊 Export Excel (CSV)</h2>
            </div>
            <div class="card-body">
                <p class="text-muted" style="margin-bottom:15px; font-size:0.85rem;">
                    Fichiers CSV compatibles Excel (séparateur <code>;</code>, UTF-8).
                </p>
                <div class="report-buttons">
                    <a href="?excel=produits" class="btn-secondary">📦 Produits</a>
                    <a href="?excel=utilisateurs" class="btn-secondary">👥 Utilisateurs</a>
                    <a href="?excel=activites&periode=<?= $periode ?>&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>" class="btn-secondary">📝 Activités</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Analyse comparative (Critère 31) -->
    <section class="dashboard-row">
        <div class="card card-half">
            <div class="card-header">
                <h2>🏷️ Répartition par catégorie</h2>
            </div>
            <div class="card-body">
                <?php if (empty($produits_par_cat)): ?>
                    <p class="empty">Aucune donnée.</p>
                <?php else: ?>
                    <canvas id="chartCategories" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-half">
            <div class="card-header">
                <h2>🏆 Top 5 produits (valeur)</h2>
            </div>
            <div class="card-body">
                <?php if (empty($top_produits)): ?>
                    <p class="empty">Aucun produit.</p>
                <?php else: ?>
                    <ul class="list-top">
                        <?php foreach ($top_produits as $i => $p): ?>
                            <li>
                                <span class="rank rank-<?= $i + 1 ?>"><?= $i + 1 ?></span>
                                <span class="list-name"><?= htmlspecialchars($p['nom']) ?></span>
                                <span class="list-value"><?= number_format($p['valeur'], 0, ',', ' ') ?> <?= htmlspecialchars(param('devise', '€')) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Prévisions (Critère 32) -->
    <section class="card">
        <div class="card-header">
            <h2>🔮 Prévisions statistiques</h2>
        </div>
        <div class="card-body">
            <div class="prevision-grid">
                <div class="prevision-card">
                    <p class="prevision-label">Moyenne d'activités / jour (7 derniers jours)</p>
                    <h3 class="prevision-value"><?= number_format($moyenne_jour, 1) ?></h3>
                </div>
                <div class="prevision-card">
                    <p class="prevision-label">Projection sur 30 jours</p>
                    <h3 class="prevision-value"><?= number_format($prevision_mois) ?> <small>actions</small></h3>
                </div>
            </div>
            <p class="text-muted" style="margin-top:15px; font-size:0.8rem;">
                <em>Méthode : moyenne des activités quotidiennes sur 7 jours × 30. Indicateur de tendance simple.</em>
            </p>
        </div>
    </section>

    <!-- Graphique activités par jour (Critère 29) -->
    <?php if (!empty($activite_par_jour)): ?>
    <section class="card">
        <div class="card-header">
            <h2>📈 Activités sur la période</h2>
        </div>
        <div class="card-body">
            <canvas id="chartActivites" height="100"></canvas>
        </div>
    </section>
    <?php endif; ?>

</main>

<!-- Graphiques Chart.js -->
<?php if (!empty($produits_par_cat)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('chartCategories').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($produits_par_cat, 'categorie')) ?>,
            datasets: [{
                data: <?= json_encode(array_map('intval', array_column($produits_par_cat, 'nb'))) ?>,
                backgroundColor: ['#3498db', '#27ae60', '#f39c12', '#e74c3c', '#9b59b6', '#1abc9c', '#e67e22'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
});
</script>
<?php endif; ?>

<?php if (!empty($activite_par_jour)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('chartActivites').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($r) => date('d/m', strtotime($r['jour'])), $activite_par_jour)) ?>,
            datasets: [{
                label: 'Activités',
                data: <?= json_encode(array_map('intval', array_column($activite_par_jour, 'nb'))) ?>,
                backgroundColor: 'rgba(52, 152, 219, 0.7)',
                borderColor: '#3498db',
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
});
</script>
<?php endif; ?>

</body>
</html>