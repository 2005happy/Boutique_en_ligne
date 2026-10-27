<?php
// mes_commandes.php
require_once 'config/init.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM commande_lignes WHERE commande_id = c.id) AS nb_articles
    FROM commandes c
    WHERE c.client_id = ?
    ORDER BY c.date_commande DESC
");
$stmt->execute([$_SESSION['user_id']]);
$commandes = $stmt->fetchAll();

include 'includes/header.php';
?>

<nav class="breadcrumb">
    <a href="index.php">🏠 Accueil</a>
    <span class="breadcrumb-sep">›</span>
    <span class="breadcrumb-current">Mes commandes</span>
</nav>

<h1 style="margin-bottom:25px;">📦 Mes commandes</h1>

<?php if (empty($commandes)): ?>
    <div style="text-align:center;padding:60px 20px;background:#fff;border-radius:12px;">
        <p style="font-size:3rem;margin-bottom:20px;">📦</p>
        <p style="color:#7f8c8d;margin-bottom:20px;">Vous n'avez encore passé aucune commande.</p>
        <a href="index.php" class="btn-primary">Découvrir les produits</a>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
            <table class="data-table order-table">
                <thead>
                    <tr>
                        <th>Numéro</th>
                        <th>Date</th>
                        <th>Articles</th>
                        <th>Total</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commandes as $c):
                        $badge = match($c['statut']) {
                            'en_attente' => 'badge-warning',
                            'confirmee'  => 'badge-info',
                            'expediee'   => 'badge-info',
                            'livree'     => 'badge-success',
                            'annulee'    => 'badge-danger',
                            default      => 'badge-info'
                        };
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['numero']) ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($c['date_commande'])) ?></td>
                            <td><?= $c['nb_articles'] ?></td>
                            <td><strong><?= number_format($c['total'], 2, ',', ' ') ?> <?= param('devise', '€') ?></strong></td>
                            <td><span class="badge <?= $badge ?>"><?= ucfirst(str_replace('_', ' ', $c['statut'])) ?></span></td>
                            <td>
                                <a href="confirmation_commande.php?id=<?= $c['id'] ?>" class="btn-icon" title="Détails">👁️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>