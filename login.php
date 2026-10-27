<?php
// login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/init.php';

// Si l'utilisateur est déjà connecté, redirection
if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'index.php'));
    exit;
}

$erreur = '';
$succes = '';

// Message si session expirée
if (isset($_GET['expired'])) {
    $erreur = "Votre session a expiré. Veuillez vous reconnecter.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation basique
    if (empty($email) || empty($password)) {
        $erreur = "Veuillez remplir tous les champs.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Adresse email invalide.";
    } else {
        try {
            // Requête préparée (Critère 11-14)
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if (!$user) {
                $erreur = "Email ou mot de passe incorrect.";
            } else {
                // Vérification du verrouillage (Critère 5)
                if (!empty($user['verrouille_jusqua']) && strtotime($user['verrouille_jusqua']) > time()) {
                    $restant = ceil((strtotime($user['verrouille_jusqua']) - time()) / 60);
                    $erreur = "Compte verrouillé. Réessayez dans {$restant} minute(s).";
                } else {
                    // Vérification du mot de passe (Critère 1)
                    if (password_verify($password, $user['mot_de_passe'])) {
                        // ⚠️ Vérification du statut AVANT de connecter
                        if ($user['statut'] === 'en_attente') {
                            $erreur = "⏳ Votre compte vendeur est en attente de validation par un administrateur.";
                        } elseif ($user['statut'] === 'rejete') {
                            $erreur = "❌ Votre demande de compte a été rejetée. Contactez l'administrateur.";
                        } else {
                            // Réinitialiser les tentatives
                            $pdo->prepare("UPDATE utilisateurs SET tentatives_connexion = 0, verrouille_jusqua = NULL WHERE id = :id")
                                ->execute([':id' => $user['id']]);

                            // Sécurité session (Critère 42)
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['nom'] = $user['nom'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['theme'] = in_array($user['theme'] ?? null, ['clair', 'sombre'], true)
                                ? $user['theme']
                                : param('theme', 'clair');
                            $_SESSION['login_time'] = time();
                            $_SESSION['last_activity'] = time();

                            // Historique des connexions (Critère 6)
                            $pdo->prepare("INSERT INTO historique_connexions (utilisateur_id, ip_adresse) VALUES (:uid, :ip)")
                                ->execute([
                                    ':uid' => $user['id'],
                                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                                ]);

                            // Journal des activités (Critère 10)
                            $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (:uid, :action, :details)")
                                ->execute([
                                    ':uid' => $user['id'],
                                    ':action' => 'CONNEXION',
                                    ':details' => 'Connexion réussie depuis ' . ($_SERVER['REMOTE_ADDR'] ?? 'inconnu')
                                ]);

                            // Redirection selon le rôle
                            // Après : session_regenerate_id(true); ...
                            if ($user['role'] === 'admin') {
                                header("Location: admin/dashboard.php");
                            } elseif ($user['role'] === 'vendeur') {
                                header("Location: vandeur/dashboard.php");
                            } else {
                                header("Location: index.php");
                            }
                            exit;
                        }
                    } else {
                        // Incrémentation des tentatives (Critère 5)
                        $tentatives = $user['tentatives_connexion'] + 1;
                        $verrou = null;
                        if ($tentatives >= 5) {
                            $verrou = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                            $erreur = "Trop de tentatives échouées. Compte verrouillé pendant 15 minutes.";
                        } else {
                            $restant = 5 - $tentatives;
                            $erreur = "Email ou mot de passe incorrect. {$restant} tentative(s) restante(s).";
                        }
                        $pdo->prepare("UPDATE utilisateurs SET tentatives_connexion = :t, verrouille_jusqua = :v WHERE id = :id")
                            ->execute([':t' => $tentatives, ':v' => $verrou, ':id' => $user['id']]);
                    }
                }
            }
        } catch (PDOException $e) {
            $erreur = "Erreur serveur. Veuillez réessayer.";
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h1>🔐 Connexion</h1>
        <p class="auth-subtitle">Accédez à votre espace personnel</p>

        <?php if ($erreur): ?>
            <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <?php if ($succes): ?>
            <div class="alert alert-success"><?= htmlspecialchars($succes) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="auth-form">
            <div class="form-group">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email" placeholder="exemple@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>

            <div class="form-options">
                <label class="checkbox-label">
                    <input type="checkbox" name="remember"> Se souvenir de moi
                </label>
                <a href="forgot_password.php" class="link">Mot de passe oublié ?</a>
            </div>

            <button type="submit" class="btn-submit">Se connecter</button>
        </form>

        <div class="auth-footer">
            <?php if (param_bool('inscription_ouverte', true)): ?>
                <p>Pas encore de compte ? <a href="register.php" class="link">Créer un compte</a></p>
            <?php else: ?>
                <p>Les inscriptions sont actuellement fermées.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>