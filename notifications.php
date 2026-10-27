<?php
// notifications.php
require_once 'config/init.php';
require_once 'config/messagerie.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

// Marquer comme lues
if (isset($_GET['tout_lu'])) {
    $pdo->prepare("UPDATE notifications SET lu = 1 WHERE utilisateur_id = ?")->execute([$uid]);
    header("Location: notifications.php");
    exit;
}

if (isset($_GET['lu'])) {
    $id = (int)$_GET['lu'];
    $pdo->prepare("UPDATE notifications SET lu = 1 WHERE id = ? AND utilisateur_id = ?")
        ->execute([$id, $uid]);
    header("Location: notifications.php");
    exit;
}

// Liste des notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE utilisateur_id = ? ORDER BY date_creation DESC LIMIT 50");
$stmt->execute([$uid]);
$notifications = $stmt->fetchAll();

include 'includes/header.php';
?>

<nav class="breadcrumb">
    <a href="index.php">🏠 Accueil</a>
    <span class="breadcrumb-sep">›</span>
    <span class="breadcrumb-current">Notifications</span>
</nav>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <h1>🔔 Notifications</h1>
    <?php if (count(array_filter($notifications, fn($n) => !$n['lu'])) > 0): ?>
        <a href="?tout_lu=1" class="btn-secondary">✅ Tout marquer comme lu</a>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
    <div style="text-align:center;padding:60px 20px;background:#fff;border-radius:12px;">
        <p style="font-size:3rem;">🔔</p>
        <p class="text-muted">Aucune notification.</p>
    </div>
<?php else: ?>
    <div class="notifications-list">
        <?php foreach ($notifications as $n):
            $icone = match($n['type']) {
                'message' => '💬',
                'commande' => '🛒',
                'validation' => '✅',
                'alerte' => '⚠️',
                default => '🔔'
            };
        ?>
            <div class="notif-item <?= $n['lu'] ? 'lue' : 'non-lue' ?>">
                <div class="notif-icon"><?= $icone ?></div>
                <div class="notif-content">
                    <p><?= htmlspecialchars($n['message']) ?></p>
                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($n['date_creation'])) ?></small>
                </div>
                <div class="notif-actions">
                    <?php if ($n['lien']): ?>
                        <a href="<?= htmlspecialchars($n['lien']) ?>" class="btn-icon" title="Voir">👁️</a>
                    <?php endif; ?>
                    <?php if (!$n['lu']): ?>
                        <a href="?lu=<?= $n['id'] ?>" class="btn-icon" title="Marquer comme lu">✅</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>