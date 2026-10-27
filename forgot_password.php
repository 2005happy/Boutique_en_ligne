<?php
// forgot_password.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config/database.php';
require 'config/mail.php';

$erreur = '';
$succes = '';
$lien_dev = ''; // Lien affiché en mode local pour test

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Veuillez saisir une adresse email valide.";
    } else {
        try {
            // Vérifier que l'utilisateur existe
            $stmt = $pdo->prepare("SELECT id, nom, email FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // ⚠️ Sécurité : toujours afficher le même message (ne pas révéler si l'email existe)
            $succes = "Si un compte existe avec cet email, un lien de réinitialisation a été envoyé.";

            if ($user) {
                // Invalider les anciens jetons non utilisés
                $pdo->prepare("UPDATE password_resets SET utilise = 1 WHERE utilisateur_id = ? AND utilise = 0")
                    ->execute([$user['id']]);

                // Générer un jeton sécurisé (Critère 41 : chiffrement/aléatoire cryptographique)
                $token = bin2hex(random_bytes(32));
                $hash_token = hash('sha256', $token); // stocker le hash, pas le token en clair
                $expire = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $pdo->prepare("INSERT INTO password_resets (utilisateur_id, token, expire_le) VALUES (?, ?, ?)")
                    ->execute([$user['id'], $hash_token, $expire]);

                // Construire le lien
                $protocole = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $dossier = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $lien = "$protocole://$host$dossier/reset_password.php?token=$token&id={$user['id']}";

                // Envoi email
                $envoye = envoyer_email_reinitialisation($user['email'], $user['nom'], $lien);

                // En local, afficher le lien pour test
                if (!$envoye) {
                    $lien_dev = $lien;
                }

                // Journal (Critère 10)
                $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                    ->execute([$user['id'], 'DEMANDE_RESET', "Demande de réinitialisation pour {$user['email']}"]);
            }
        } catch (PDOException $e) {
            $erreur = "Erreur serveur. Réessayez plus tard.";
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h1>🔑 Mot de passe oublié</h1>
        <p class="auth-subtitle">Recevez un lien pour réinitialiser votre mot de passe</p>

        <?php if ($erreur): ?>
            <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <?php if ($succes): ?>
            <div class="alert alert-success"><?= htmlspecialchars($succes) ?></div>
        <?php endif; ?>

        <?php if ($lien_dev): ?>
            <div class="alert alert-info" style="background:#eaf2fb;color:#1f618d;border-left:4px solid #3498db;">
                <strong>🛠️ Mode développement (pas de serveur mail) :</strong><br>
                Cliquez sur ce lien pour tester :<br>
                <a href="<?= htmlspecialchars($lien_dev) ?>" style="word-break:break-all;">
                    <?= htmlspecialchars($lien_dev) ?>
                </a>
            </div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php" class="auth-form">
            <div class="form-group">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email" placeholder="exemple@email.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>

            <button type="submit" class="btn-submit">📧 Envoyer le lien</button>
        </form>

        <div class="auth-footer">
            <p><a href="login.php" class="link">← Retour à la connexion</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>