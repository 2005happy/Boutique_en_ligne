<?php
// register.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/init.php';

// Si déjà connecté, redirection
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$erreur = '';
$succes = '';
$inscription_ouverte = param_bool('inscription_ouverte', true);
$validation_vendeurs = param_bool('validation_vendeurs', true);
$role_souhaite = 'client';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$inscription_ouverte) {
    $erreur = "Les inscriptions sont actuellement fermées.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $role_souhaite = $_POST['role_souhaite'] ?? 'client';

    // Validation des champs
    if (empty($nom) || empty($email) || empty($password) || empty($confirm)) {
        $erreur = "Tous les champs sont obligatoires.";
    } elseif (strlen($nom) < 2) {
        $erreur = "Le nom doit contenir au moins 2 caractères.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Adresse email invalide.";
    } elseif (strlen($password) < 6) {
        $erreur = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $confirm) {
        $erreur = "Les mots de passe ne correspondent pas.";
    } else {
        // Validation du rôle (jamais 'admin')
        $roles_autorises = ['client', 'vendeur'];
        if (!in_array($role_souhaite, $roles_autorises, true)) {
            $role_souhaite = 'client';
        }

        // Statut : vendeur = en attente, client = actif
        $statut = ($role_souhaite === 'vendeur' && $validation_vendeurs) ? 'en_attente' : 'actif';

        try {
            // Vérifier email unique
            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $erreur = "Un compte existe déjà avec cet email.";
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $pdo->prepare("
                    INSERT INTO utilisateurs (nom, email, mot_de_passe, role, statut)
                    VALUES (:nom, :email, :mdp, :role, :statut)
                ");
                $stmt->execute([
                    ':nom'    => $nom,
                    ':email'  => $email,
                    ':mdp'    => $hash,
                    ':role'   => $role_souhaite,
                    ':statut' => $statut
                ]);

                $new_id = $pdo->lastInsertId();

                // Journal des activités (Critère 10)
                $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                    ->execute([$new_id, 'INSCRIPTION', "Nouveau compte : $role_souhaite (statut: $statut)"]);

                // Message adapté au rôle
                if ($role_souhaite === 'vendeur' && $validation_vendeurs) {
                    $succes = "Votre demande de compte vendeur a été enregistrée. Un administrateur doit la valider avant que vous puissiez vous connecter.";
                } else {
                    $succes = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
                    header("refresh:3;url=login.php");
                }
            }
        } catch (PDOException $e) {
            $erreur = "Erreur serveur : " . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h1>📝 Créer un compte</h1>
        <p class="auth-subtitle">Rejoignez <?= htmlspecialchars(param('nom_boutique', 'Ma Boutique')) ?> dès aujourd'hui</p>

        <?php if (!$inscription_ouverte): ?>
            <div class="alert alert-error">Les inscriptions sont actuellement fermées.</div>
        <?php endif; ?>
        <?php if ($erreur && $inscription_ouverte): ?>
            <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <?php if ($succes): ?>
            <div class="alert alert-success"><?= htmlspecialchars($succes) ?></div>
            <?php if ($role_souhaite === 'client'): ?>
                <p style="text-align:center; margin-top:10px; color:#7f8c8d; font-size:0.9rem;">
                    Redirection vers la connexion...
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($inscription_ouverte && !$succes): ?>
        <form method="POST" action="register.php" class="auth-form">
            <div class="form-group">
                <label for="nom">Nom complet *</label>
                <input type="text" id="nom" name="nom" placeholder="Jean Dupont"
                       value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Adresse email *</label>
                <input type="email" id="email" name="email" placeholder="exemple@email.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="role_souhaite">Je souhaite m'inscrire comme : *</label>
                <select id="role_souhaite" name="role_souhaite" required>
                    <option value="client" <?= ($_POST['role_souhaite'] ?? '') === 'client' ? 'selected' : '' ?>>
                        🛒 Client — Acheter des produits
                    </option>
                    <option value="vendeur" <?= ($_POST['role_souhaite'] ?? '') === 'vendeur' ? 'selected' : '' ?>>
                        💼 Vendeur — Proposer des produits<?= $validation_vendeurs ? ' (validation requise)' : '' ?>
                    </option>
                </select>
                <?php if ($validation_vendeurs): ?>
                    <small class="form-hint">Les comptes vendeurs sont validés par un administrateur.</small>
                <?php else: ?>
                    <small class="form-hint">Les comptes vendeurs sont activés dès leur inscription.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe *</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required minlength="6">
                <small class="form-hint">Au moins 6 caractères.</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe *</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
            </div>

            <div class="form-options">
                <label class="checkbox-label">
                    <input type="checkbox" name="cgu" required> J'accepte les <a href="#" class="link">CGU</a>
                </label>
            </div>

            <button type="submit" class="btn-submit">Créer mon compte</button>
        </form>
        <?php endif; ?>

        <div class="auth-footer">
            <p>Déjà inscrit ? <a href="login.php" class="link">Se connecter</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>