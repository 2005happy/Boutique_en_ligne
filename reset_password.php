<?php
// reset_password.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/init.php';

$erreur = '';
$succes = '';
$token_valide = false;
$user_id = 0;

// Récupérer les paramètres
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// Vérifier le jeton
if ($token === '' || $id <= 0) {
    $erreur = "Lien invalide ou incomplet.";
} else {
    try {
        $hash_token = hash('sha256', $token);
        $stmt = $pdo->prepare("
            SELECT id, utilisateur_id, expire_le, utilise
            FROM password_resets
            WHERE token = ? AND utilisateur_id = ?
            ORDER BY cree_le DESC
            LIMIT 1
        ");
        $stmt->execute([$hash_token, $id]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $erreur = "Ce lien de réinitialisation est invalide.";
        } elseif ((int)$reset['utilise'] === 1) {
            $erreur = "Ce lien a déjà été utilisé. Demandez-en un nouveau.";
        } elseif (strtotime($reset['expire_le']) < time()) {
            $erreur = "Ce lien a expiré (valable 1 heure). Demandez-en un nouveau.";
        } else {
            $token_valide = true;
            $user_id = (int)$reset['utilisateur_id'];

            // Si formulaire soumis → changer le mot de passe
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $new_mdp = $_POST['password'] ?? '';
                $confirm = $_POST['confirm'] ?? '';

                if (strlen($new_mdp) < 6) {
                    $erreur = "Le mot de passe doit contenir au moins 6 caractères.";
                } elseif ($new_mdp !== $confirm) {
                    $erreur = "Les mots de passe ne correspondent pas.";
                } else {
                    // Mettre à jour le mot de passe (Critère 41 : hachage)
                    $hash = password_hash($new_mdp, PASSWORD_BCRYPT);
                    $pdo->prepare("
                        UPDATE utilisateurs
                        SET mot_de_passe = ?, tentatives_connexion = 0, verrouille_jusqua = NULL
                        WHERE id = ?
                    ")->execute([$hash, $user_id]);

                    // Marquer le jeton comme utilisé
                    $pdo->prepare("UPDATE password_resets SET utilise = 1 WHERE id = ?")
                        ->execute([$reset['id']]);

                    // Journal (Critère 10)
                    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                        ->execute([$user_id, 'RESET_MDP_OK', "Mot de passe réinitialisé via token"]);

                    $succes = "Votre mot de passe a été réinitialisé avec succès. Vous pouvez vous connecter.";
                    // Redirection après 2 s
                    header("refresh:2;url=login.php");
                    $token_valide = false;
                }
            }
        }
    } catch (PDOException $e) {
        $erreur = "Erreur serveur. Réessayez plus tard.";
    }
}

include 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h1>🔐 Nouveau mot de passe</h1>
        <p class="auth-subtitle">Définissez un nouveau mot de passe sécurisé</p>

        <?php if ($erreur): ?>
            <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <?php if ($succes): ?>
            <div class="alert alert-success"><?= htmlspecialchars($succes) ?></div>
        <?php endif; ?>

        <?php if ($token_valide): ?>
            <form method="POST" action="reset_password.php" class="auth-form">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="id" value="<?= (int)$id ?>">

                <div class="form-group">
                    <label for="password">Nouveau mot de passe</label>
                    <input type="password" id="password" name="password" placeholder="••••••••"
                           minlength="6" required autofocus>
                    <small class="form-hint">Minimum 6 caractères.</small>
                </div>

                <div class="form-group">
                    <label for="confirm">Confirmer le mot de passe</label>
                    <input type="password" id="confirm" name="confirm" placeholder="••••••••"
                           minlength="6" required>
                </div>

                <button type="submit" class="btn-submit">🔐 Enregistrer le nouveau mot de passe</button>
            </form>
        <?php elseif (!$succes): ?>
            <div style="text-align:center; margin-top:20px;">
                <a href="forgot_password.php" class="btn-secondary">🔄 Demander un nouveau lien</a>
            </div>
        <?php endif; ?>

        <div class="auth-footer">
            <p><a href="login.php" class="link">← Retour à la connexion</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>