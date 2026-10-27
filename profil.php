<?php
// profil.php
require_once 'config/init.php';

// Vérifier la connexion
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=profil.php");
    exit;
}

$message = '';
$type_message = 'success';
$onglet = $_GET['onglet'] ?? 'infos';

// ==========================================
// CHARGEMENT DES INFOS CLIENT
// ==========================================
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// ==========================================
// TRAITEMENT DES ACTIONS
// ==========================================

// --- Modification des infos ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'modifier_infos') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($nom === '' || $email === '') {
        $message = "Le nom et l'email sont obligatoires.";
        $type_message = 'error';
        $onglet = 'infos';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Adresse email invalide.";
        $type_message = 'error';
        $onglet = 'infos';
    } else {
        try {
            // Vérifier email unique
            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $message = "Cet email est déjà utilisé.";
                $type_message = 'error';
            } else {
                $pdo->prepare("UPDATE utilisateurs SET nom = ?, email = ? WHERE id = ?")
                    ->execute([$nom, $email, $_SESSION['user_id']]);

                $_SESSION['nom'] = $nom;
                $_SESSION['email'] = $email;

                $message = "✅ Profil mis à jour avec succès.";
                $onglet = 'infos';

                $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                    ->execute([$_SESSION['user_id'], 'MODIF_PROFIL', "Mise à jour du profil client"]);
            }
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $type_message = 'error';
        }
    }
}

// --- Modification du mot de passe ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'modifier_mdp') {
    $ancien = $_POST['ancien_mdp'] ?? '';
    $nouveau = $_POST['nouveau_mdp'] ?? '';
    $confirm = $_POST['confirm_mdp'] ?? '';

    $onglet = 'mot_de_passe';

    if (empty($ancien) || empty($nouveau) || empty($confirm)) {
        $message = "Tous les champs sont obligatoires.";
        $type_message = 'error';
    } elseif (!password_verify($ancien, $user['mot_de_passe'])) {
        $message = "Mot de passe actuel incorrect.";
        $type_message = 'error';
    } elseif (strlen($nouveau) < 6) {
        $message = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
        $type_message = 'error';
    } elseif ($nouveau !== $confirm) {
        $message = "Les nouveaux mots de passe ne correspondent pas.";
        $type_message = 'error';
    } else {
        try {
            $hash = password_hash($nouveau, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?")
                ->execute([$hash, $_SESSION['user_id']]);

            $message = "✅ Mot de passe modifié avec succès.";
            $type_message = 'success';

            $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], 'MODIF_MDP', "Changement de mot de passe"]);
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $type_message = 'error';
        }
    }
}

// --- Préférence de thème personnelle ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'modifier_theme') {
    $theme = $_POST['theme'] ?? '';

    $onglet = 'apparence';
    if (!in_array($theme, ['clair', 'sombre'], true)) {
        $message = "Veuillez choisir un thème valide.";
        $type_message = 'error';
    } else {
        try {
            $pdo->prepare("UPDATE utilisateurs SET theme = ? WHERE id = ?")
                ->execute([$theme, $_SESSION['user_id']]);
            $_SESSION['theme'] = $theme;
            $GLOBALS['parametres']['theme'] = $theme;
            $user['theme'] = $theme;
            $message = "Votre thème personnel a été enregistré.";
        } catch (PDOException $e) {
            $message = "Erreur lors de l'enregistrement du thème.";
            $type_message = 'error';
        }
    }
}

// ==========================================
// DONNÉES À AFFICHER
// ==========================================

// Commandes du client
$stmt = $pdo->prepare("
    SELECT c.*,
        (SELECT COUNT(*) FROM commande_lignes WHERE commande_id = c.id) AS nb_articles
    FROM commandes c
    WHERE c.client_id = ?
    ORDER BY c.date_commande DESC
    LIMIT 20
");
$stmt->execute([$_SESSION['user_id']]);
$commandes = $stmt->fetchAll();

// Statistiques client
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS nb_total,
           COALESCE(SUM(total), 0) AS total_depense
    FROM commandes
    WHERE client_id = ? AND statut != 'annulee'
");
$stmt->execute([$_SESSION['user_id']]);
$stats_client = $stmt->fetch();

// Dernières connexions
$stmt = $pdo->prepare("
    SELECT date_connexion, ip_adresse
    FROM historique_connexions
    WHERE utilisateur_id = ?
    ORDER BY date_connexion DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$connexions = $stmt->fetchAll();

// Statut de validation
$statut_ok = ($user['statut'] === 'actif');

include 'includes/header.php';
?>

<nav class="breadcrumb">
    <a href="index.php">🏠 Accueil</a>
    <span class="breadcrumb-sep">›</span>
    <span class="breadcrumb-current">Mon profil</span>
</nav>

<div class="profil-header">
    <div class="profil-avatar-large">
        <?= strtoupper(substr($user['nom'], 0, 2)) ?>
    </div>
    <div class="profil-header-info">
        <h1><?= htmlspecialchars($user['nom']) ?></h1>
        <p class="text-muted"><?= htmlspecialchars($user['email']) ?></p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
            <span class="badge <?= $user['role'] === 'admin' ? 'badge-danger' : ($user['role'] === 'vendeur' ? 'badge-warning' : 'badge-success') ?>">
                <?= $user['role'] === 'admin' ? '👑' : ($user['role'] === 'vendeur' ? '💼' : '🛒') ?>
                <?= ucfirst($user['role']) ?>
            </span>
            <span class="badge <?= $statut_ok ? 'badge-success' : 'badge-warning' ?>">
                <?= $statut_ok ? '✅ Actif' : '⏳ ' . ucfirst($user['statut']) ?>
            </span>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $type_message === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Statistiques client -->
<section class="stats-grid profile-stats">
    <div class="stat-card stat-blue">
        <div class="stat-icon">🛒</div>
        <div class="stat-content">
            <p class="stat-label">Commandes</p>
            <h3 class="stat-value"><?= (int)$stats_client['nb_total'] ?></h3>
        </div>
    </div>
    <div class="stat-card stat-green">
        <div class="stat-icon">💰</div>
        <div class="stat-content">
            <p class="stat-label">Total dépensé</p>
            <h3 class="stat-value" style="font-size:1.1rem;">
                <?= number_format((float)$stats_client['total_depense'], 2, ',', ' ') ?> <?= param('devise', '€') ?>
            </h3>
        </div>
    </div>
    <div class="stat-card stat-orange">
        <div class="stat-icon">📅</div>
        <div class="stat-content">
            <p class="stat-label">Membre depuis</p>
            <h3 class="stat-value" style="font-size:1.1rem;">
                <?= date('d/m/Y', strtotime($user['cree_le'])) ?>
            </h3>
        </div>
    </div>
    <div class="stat-card stat-blue">
        <div class="stat-icon">🔐</div>
        <div class="stat-content">
            <p class="stat-label">Dernière connexion</p>
            <h3 class="stat-value" style="font-size:0.95rem;">
                <?= !empty($connexions) ? date('d/m/Y H:i', strtotime($connexions[0]['date_connexion'])) : '—' ?>
            </h3>
        </div>
    </div>
</section>

<!-- Onglets -->
<div class="tabs-nav">
    <a href="?onglet=infos" class="tab-link <?= $onglet === 'infos' ? 'active' : '' ?>">
        👤 Informations
    </a>
    <a href="?onglet=commandes" class="tab-link <?= $onglet === 'commandes' ? 'active' : '' ?>">
        🛒 Mes commandes
    </a>
    <a href="?onglet=mot_de_passe" class="tab-link <?= $onglet === 'mot_de_passe' ? 'active' : '' ?>">
        🔐 Mot de passe
    </a>
    <a href="?onglet=apparence" class="tab-link <?= $onglet === 'apparence' ? 'active' : '' ?>">
        🎨 Apparence
    </a>
    <a href="?onglet=securite" class="tab-link <?= $onglet === 'securite' ? 'active' : '' ?>">
        🔒 Sécurité
    </a>
</div>

<!-- Contenu des onglets -->
<section class="card profile-content <?= in_array($onglet, ['commandes', 'securite'], true) ? 'profile-table-content' : '' ?>">
    <div class="card-body">

    <?php if ($onglet === 'infos'): ?>
        <!-- ONGLET INFOS -->
        <h2 style="margin-bottom:20px;">👤 Mes informations</h2>
        <form method="POST" action="profil.php" class="form-grid">
            <input type="hidden" name="action" value="modifier_infos">

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
                <button type="submit" class="btn-submit">💾 Enregistrer les modifications</button>
            </div>
        </form>

        <div style="margin-top:30px;padding-top:20px;border-top:1px solid #ecf0f1;">
            <p class="text-muted" style="font-size:0.85rem;">
                💡 Pour changer votre mot de passe, rendez-vous dans l'onglet
                <a href="?onglet=mot_de_passe" class="link">Mot de passe</a>.
            </p>
        </div>

    <?php elseif ($onglet === 'commandes'): ?>
        <!-- ONGLET COMMANDES -->
        <h2 style="margin-bottom:20px;">🛒 Mes commandes (<?= count($commandes) ?>)</h2>

        <?php if (empty($commandes)): ?>
            <div style="text-align:center;padding:40px 20px;">
                <p style="font-size:3rem;margin-bottom:15px;">📦</p>
                <p class="text-muted">Vous n'avez pas encore passé de commande.</p>
                <a href="index.php" class="btn-primary" style="margin-top:20px;">Découvrir les produits</a>
            </div>
        <?php else: ?>
            <table class="data-table">
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
                            <td><?= date('d/m/Y', strtotime($c['date_commande'])) ?></td>
                            <td><?= $c['nb_articles'] ?></td>
                            <td><strong><?= number_format((float)$c['total'], 2, ',', ' ') ?> <?= param('devise', '€') ?></strong></td>
                            <td><span class="badge <?= $badge ?>"><?= ucfirst(str_replace('_', ' ', $c['statut'])) ?></span></td>
                            <td>
                                <a href="confirmation_commande.php?id=<?= $c['id'] ?>" class="btn-icon" title="Détails">👁️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php elseif ($onglet === 'mot_de_passe'): ?>
        <!-- ONGLET MOT DE PASSE -->
        <h2 style="margin-bottom:20px;">🔐 Changer mon mot de passe</h2>
        <form method="POST" action="profil.php" class="form-grid" style="max-width:500px;">
            <input type="hidden" name="action" value="modifier_mdp">

            <div class="form-group form-group-full">
                <label for="ancien_mdp">Mot de passe actuel *</label>
                <input type="password" id="ancien_mdp" name="ancien_mdp" required
                       placeholder="••••••••">
            </div>

            <div class="form-group form-group-full">
                <label for="nouveau_mdp">Nouveau mot de passe *</label>
                <input type="password" id="nouveau_mdp" name="nouveau_mdp" required minlength="6"
                       placeholder="••••••••">
                <small class="form-hint">Minimum 6 caractères.</small>
            </div>

            <div class="form-group form-group-full">
                <label for="confirm_mdp">Confirmer le nouveau mot de passe *</label>
                <input type="password" id="confirm_mdp" name="confirm_mdp" required minlength="6"
                       placeholder="••••••••">
            </div>

            <div class="form-group form-group-full">
                <button type="submit" class="btn-submit">🔐 Modifier le mot de passe</button>
            </div>
        </form>

    <?php elseif ($onglet === 'apparence'): ?>
        <h2 style="margin-bottom:20px;">🎨 Mon thème</h2>
        <p class="text-muted" style="margin-bottom:20px;">
            Ce choix ne modifie que l'apparence de votre compte.
        </p>
        <?php $theme_utilisateur = $user['theme'] ?? param('theme', 'clair'); ?>
        <form method="POST" action="profil.php?onglet=apparence" class="theme-selector">
            <input type="hidden" name="action" value="modifier_theme">
            <label class="theme-card <?= $theme_utilisateur === 'clair' ? 'active' : '' ?>">
                <input type="radio" name="theme" value="clair" <?= $theme_utilisateur === 'clair' ? 'checked' : '' ?>>
                <strong>☀️ Clair</strong>
            </label>
            <label class="theme-card <?= $theme_utilisateur === 'sombre' ? 'active' : '' ?>">
                <input type="radio" name="theme" value="sombre" <?= $theme_utilisateur === 'sombre' ? 'checked' : '' ?>>
                <strong>🌙 Sombre</strong>
            </label>
            <div class="form-group form-group-full">
                <button type="submit" class="btn-submit">Enregistrer mon thème</button>
            </div>
        </form>

    <?php elseif ($onglet === 'securite'): ?>
        <!-- ONGLET SÉCURITÉ -->
        <h2 style="margin-bottom:20px;">🔒 Sécurité du compte</h2>

        <h3 style="margin-bottom:15px;font-size:1.1rem;">📋 Historique des connexions</h3>
        <?php if (empty($connexions)): ?>
            <p class="empty">Aucune connexion enregistrée.</p>
        <?php else: ?>
            <table class="data-table" style="margin-bottom:30px;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Adresse IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($connexions as $cn): ?>
                        <tr>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($cn['date_connexion'])) ?></strong>
                                <small class="text-muted"> à <?= date('H:i', strtotime($cn['date_connexion'])) ?></small>
                            </td>
                            <td><code><?= htmlspecialchars($cn['ip_adresse']) ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h3 style="margin-bottom:15px;font-size:1.1rem;">📊 Informations du compte</h3>
        <div class="info-grid">
            <div class="info-item">
                <p class="info-label">Rôle</p>
                <p class="info-value"><?= ucfirst($user['role']) ?></p>
            </div>
            <div class="info-item">
                <p class="info-label">Statut</p>
                <p class="info-value">
                    <span class="badge <?= $statut_ok ? 'badge-success' : 'badge-warning' ?>">
                        <?= $statut_ok ? 'Actif' : ucfirst($user['statut']) ?>
                    </span>
                </p>
            </div>
            <div class="info-item">
                <p class="info-label">Tentatives échouées</p>
                <p class="info-value"><?= (int)$user['tentatives_connexion'] ?></p>
            </div>
            <div class="info-item">
                <p class="info-label">Verrouillé jusqu'à</p>
                <p class="info-value">
                    <?= $user['verrouille_jusqua'] ? date('d/m/Y H:i', strtotime($user['verrouille_jusqua'])) : '—' ?>
                </p>
            </div>
        </div>

        <div class="alert" style="margin-top:30px;background:#eaf2fb;border-left-color:#3498db;color:#1f618d;">
            💡 <strong>Conseil :</strong> si vous suspectez une activité suspecte sur votre compte,
            changez immédiatement votre mot de passe dans l'onglet
            <a href="?onglet=mot_de_passe" class="link">Mot de passe</a>.
        </div>

    <?php endif; ?>

    </div>
</section>

<?php include 'includes/footer.php'; ?>