<?php
// share.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/init.php';

$token = $_GET['token'] ?? '';
$erreur = '';

if ($token === '') {
    $erreur = "Lien de partage invalide.";
} else {
    $stmt = $pdo->prepare("
        SELECT * FROM documents
        WHERE token_partage = ? AND partage_actif = 1 AND archive = 0
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $doc = $stmt->fetch();

    if (!$doc) {
        $erreur = "Document introuvable ou partage révoqué.";
    } elseif (!file_exists($doc['chemin_fichier'])) {
        $erreur = "Le fichier n'est plus disponible sur le serveur.";
    } else {
        // Téléchargement direct
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $doc['type_mime']);
        header('Content-Disposition: attachment; filename="' . basename($doc['nom_fichier']) . '"');
        header('Content-Length: ' . filesize($doc['chemin_fichier']));
        header('Cache-Control: must-revalidate');
        readfile($doc['chemin_fichier']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair'), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <title>Partage de document</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>🔗 Partage de document</h1>
            <?php if ($erreur): ?>
                <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
                <div style="text-align:center; margin-top:20px;">
                    <a href="index.php" class="btn-secondary">← Retour à l'accueil</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>