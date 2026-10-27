<?php
// vandeur/mon_profil.php
require_once '../config/init.php';
require_once '../includes/auth_vendeur.php';

$message = '';
$type_message = 'success';

// Traitement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nouveau_mdp = $_POST['nouveau_mdp'] ?? '';
    $confirm_mdp = $_POST['confirm_mdp'] ?? '';

    if ($nom === '' || $email === '') {
        $message = "Le nom et l'email sont obligatoires.";
        $type_message = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email invalide.";
        $type_message = 'error';
    } elseif ($nouveau_mdp !== '' && strlen($nouveau_mdp) < 6) {
        $message = "Le mot de passe doit contenir au moins 6 caractères.";
        $type_message = 'error';
    } elseif ($nouveau_mdp !== $confirm_mdp) {
        $message = "Les mots de passe ne correspondent pas.";
        $type_message = 'error';
    } else {
        try {
            // Vérifier email unique
            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $message = "Cet email est déjà utilisé.";
                $type_message = 'error';
            } else {
                if ($nouveau_mdp !== '') {
                    $hash = password_hash($nouveau_mdp, PASSWORD_BCRYPT);
                    $pdo->prepare("UPDATE utilisateurs SET nom=?, email=?, mot_de_passe=? WHERE id=?")
                        ->execute([$nom, $email, $hash, $_SESSION['user_id']]);
                    $message = "Profil et mot de passe mis à jour.";
                } else {
                    $pdo->prepare("UPDATE utilisateurs SET nom=?, email=? WHERE id=?")
                        ->execute([$nom, $email, $_SESSION['user_id']]);
                    $message = "Profil mis à jour.";
                }
                $_SESSION['nom'] = $nom;
                $_SESSION['email'] = $email;

                $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                    ->execute([$_SESSION['user_id'], 'MODIF_PROFIL', "Mise à jour du profil vendeur"]);
            }
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $type_message = 'error';
        }
    }
}

// Charger les infos actuelles
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair')) ?>">
<head>
    <meta charset="UTF-8">
    <title>Mon profil - Vendeur</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../includes/sidebar_vendeur.php'; ?>

<main class="admin-main">
    <header class="admin-topbar">
        <div>
            <h1>👤 Mon profil</h1>
            <p class="topbar-subtitle">Gérez vos informations personnelles</p>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert alert-<?= $type_message === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <div class="card-header">
            <h2>📝 Informations</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="mon_profil.php" class="form-grid">
                <div class="form-group">
                    <label for="nom">Nom complet *</label>
                    <input type="text" id="nom" name="nom" required
                           value="<?= htmlspecialchars($user['nom']) ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required
                           value="<?= htmlspecialchars($user['email']) ?>">
                </div>

                <div class="form-group form-group-full">
                    <hr style="border:none;border-top:1px solid #ecf0f1;margin:10px 0;">
                    <p class="text-muted" style="font-size:0.85rem;">
                        Laissez les champs ci-dessous vides pour ne pas changer le mot de passe.
                    </p>
                </div>

                <div class="form-group">
                    <label for="nouveau_mdp">Nouveau mot de passe</label>
                    <input type="password" id="nouveau_mdp" name="nouveau_mdp"
                           placeholder="••••••••" minlength="6">
                </div>

                <div class="form-group">
                    <label for="confirm_mdp">Confirmer</label>
                    <input type="password" id="confirm_mdp" name="confirm_mdp"
                           placeholder="••••••••">
                </div>

                <div class="form-group form-group-full">
                    <button type="submit" class="btn-submit">💾 Enregistrer</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>ℹ️ Informations du compte</h2>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <p class="info-label">Rôle</p>
                    <p class="info-value">
                        <span class="badge badge-warning">💼 Vendeur</span>
                    </p>
                </div>
                <div class="info-item">
                    <p class="info-label">Statut</p>
                    <p class="info-value">
                        <span class="badge badge-success">✅ Actif</span>
                    </p>
                </div>
                <div class="info-item">
                    <p class="info-label">Inscrit le</p>
                    <p class="info-value"><?= date('d/m/Y', strtotime($user['cree_le'])) ?></p>
                </div>
            </div>
        </div>
    </section>
</main>
</body>
</html>