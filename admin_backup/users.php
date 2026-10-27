<?php
// admin/users.php
require_once '../config/init.php';
require_once '../includes/auth.php';

// ==========================================
// TRAITEMENT DES ACTIONS
// ==========================================
$message = '';
$type_message = 'success';

// Rôles autorisés (Critère 2)
$roles_valides = ['admin', 'vendeur', 'client'];

// --- AJOUT D'UTILISATEUR (Critère 7) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ajouter') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'client';

    if ($nom === '' || $email === '' || $password === '') {
        $message = "Tous les champs sont obligatoires.";
        $type_message = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Adresse email invalide.";
        $type_message = 'error';
    } elseif (strlen($password) < 6) {
        $message = "Le mot de passe doit contenir au moins 6 caractères.";
        $type_message = 'error';
    } elseif (!in_array($role, $roles_valides, true)) {
        $message = "Rôle invalide.";
        $type_message = 'error';
    } else {
        try {
            // Vérifier doublon
            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $message = "Un compte existe déjà avec cet email.";
                $type_message = 'error';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom, email, mot_de_passe, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nom, $email, $hash, $role]);
                $message = "Utilisateur « " . htmlspecialchars($nom) . " » créé (rôle : $role).";
                $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                    ->execute([$_SESSION['user_id'], 'AJOUT_USER', "Création de $email ($role)"]);
            }
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $type_message = 'error';
        }
    }
}

// --- MODIFICATION (nom, email, rôle) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'modifier') {
    $id = (int)($_POST['id'] ?? 0);
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'client';

    if ($id <= 0 || $nom === '' || $email === '') {
        $message = "Données invalides.";
        $type_message = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email invalide.";
        $type_message = 'error';
    } elseif (!in_array($role, $roles_valides, true)) {
        $message = "Rôle invalide.";
        $type_message = 'error';
    } else {
        try {
            // Empêcher de se retirer soi-même le rôle admin
            if ($id === (int)$_SESSION['user_id'] && $role !== 'admin') {
                $message = "Vous ne pouvez pas retirer votre propre rôle administrateur.";
                $type_message = 'error';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
                $stmt->execute([$email, $id]);
                if ($stmt->fetch()) {
                    $message = "Un autre compte utilise déjà cet email.";
                    $type_message = 'error';
                } else {
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET nom = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->execute([$nom, $email, $role, $id]);
                    $message = "Utilisateur modifié avec succès.";
                    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                        ->execute([$_SESSION['user_id'], 'MODIF_USER', "Modification de l'utilisateur #$id"]);
                }
            }
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $type_message = 'error';
        }
    }
}

// --- RÉINITIALISATION DU MOT DE PASSE (Critère 4) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    $id = (int)($_POST['id'] ?? 0);
    $nouveau = $_POST['nouveau_mdp'] ?? '';

    if ($id <= 0 || strlen($nouveau) < 6) {
        $message = "Mot de passe trop court (min. 6 caractères).";
        $type_message = 'error';
    } else {
        $hash = password_hash($nouveau, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ?, tentatives_connexion = 0, verrouille_jusqua = NULL WHERE id = ?")
            ->execute([$hash, $id]);
        $message = "Mot de passe réinitialisé et compte déverrouillé.";
        $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
            ->execute([$_SESSION['user_id'], 'RESET_MDP', "Réinitialisation du mot de passe de l'utilisateur #$id"]);
    }
}

// --- VERROUILLAGE / DÉVERROUILLAGE (Critère 5) ---
if (isset($_GET['verrouiller'])) {
    $id = (int)$_GET['verrouiller'];
    if ($id === (int)$_SESSION['user_id']) {
        $message = "Vous ne pouvez pas verrouiller votre propre compte.";
        $type_message = 'error';
    } else {
        $pdo->prepare("UPDATE utilisateurs SET verrouille_jusqua = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?")
            ->execute([$id]);
        $message = "Compte verrouillé pour 24h.";
        $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
            ->execute([$_SESSION['user_id'], 'VERROU_USER', "Verrouillage du compte #$id"]);
    }
}

if (isset($_GET['deverrouiller'])) {
    $id = (int)$_GET['deverrouiller'];
    $pdo->prepare("UPDATE utilisateurs SET verrouille_jusqua = NULL, tentatives_connexion = 0 WHERE id = ?")
        ->execute([$id]);
    $message = "Compte déverrouillé.";
    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'DEVERROU_USER', "Déverrouillage du compte #$id"]);
}

// --- SUPPRESSION ---
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    if ($id === (int)$_SESSION['user_id']) {
        $message = "Vous ne pouvez pas supprimer votre propre compte.";
        $type_message = 'error';
    } else {
        try {
            $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?")->execute([$id]);
            $message = "Utilisateur supprimé.";
            $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], 'SUPPR_USER', "Suppression de l'utilisateur #$id"]);
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $type_message = 'error';
        }
    }
}

// ==========================================
// CONSULTATION : recherche, filtre, tri
// ==========================================
$recherche = trim($_GET['recherche'] ?? '');
$filtre_role = $_GET['role'] ?? '';
$tri = $_GET['tri'] ?? 'recent';

$sql = "SELECT id, nom, email, role, tentatives_connexion, verrouille_jusqua, cree_le FROM utilisateurs WHERE 1=1";
$params = [];

if ($recherche !== '') {
    $sql .= " AND (nom LIKE :rech OR email LIKE :rech)";
    $params[':rech'] = "%$recherche%";
}
if ($filtre_role !== '' && in_array($filtre_role, $roles_valides, true)) {
    $sql .= " AND role = :role";
    $params[':role'] = $filtre_role;
}

switch ($tri) {
    case 'nom_asc':  $sql .= " ORDER BY nom ASC"; break;
    case 'nom_desc': $sql .= " ORDER BY nom DESC"; break;
    case 'role':     $sql .= " ORDER BY role ASC, nom ASC"; break;
    default:         $sql .= " ORDER BY cree_le DESC"; break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Utilisateur à modifier
$user_edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT id, nom, email, role FROM utilisateurs WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $user_edit = $stmt->fetch();
}

// Statistiques par rôle
$stats_roles = $pdo->query("SELECT role, COUNT(*) AS nb FROM utilisateurs GROUP BY role")->fetchAll();
$stats = ['admin' => 0, 'vendeur' => 0, 'client' => 0];
foreach ($stats_roles as $s) $stats[$s['role']] = (int)$s['nb'];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair'), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="admin-body">

<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="admin-main">

    <header class="admin-topbar">
        <div>
            <h1>👥 Gestion des utilisateurs</h1>
            <p class="topbar-subtitle">Rôles, permissions, verrouillage et réinitialisation</p>
        </div>
        <div class="topbar-actions">
            <a href="dashboard.php" class="topbar-icon" title="Dashboard">📊</a>
            <div class="topbar-user">
                <span class="avatar"><?= strtoupper(substr($_SESSION['nom'], 0, 1)) ?></span>
                <span><?= htmlspecialchars($_SESSION['nom']) ?></span>
            </div>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert alert-<?= $type_message === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Statistiques par rôle (Critère 2) -->
    <section class="stats-grid">
        <div class="stat-card stat-blue">
            <div class="stat-icon">👑</div>
            <div class="stat-content">
                <p class="stat-label">Administrateurs</p>
                <h3 class="stat-value"><?= $stats['admin'] ?></h3>
            </div>
        </div>
        <div class="stat-card stat-orange">
            <div class="stat-icon">💼</div>
            <div class="stat-content">
                <p class="stat-label">Vendeurs</p>
                <h3 class="stat-value"><?= $stats['vendeur'] ?></h3>
            </div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-icon">🛒</div>
            <div class="stat-content">
                <p class="stat-label">Clients</p>
                <h3 class="stat-value"><?= $stats['client'] ?></h3>
            </div>
        </div>
        <div class="stat-card stat-blue">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <p class="stat-label">Total</p>
                <h3 class="stat-value"><?= array_sum($stats) ?></h3>
            </div>
        </div>
    </section>

    <!-- ==========================================
         FORMULAIRE AJOUT / MODIFICATION
         ========================================== -->
    <section class="card">
        <div class="card-header">
            <h2><?= $user_edit ? '✏️ Modifier l\'utilisateur' : '➕ Créer un utilisateur' ?></h2>
            <?php if ($user_edit): ?>
                <a href="users.php" class="card-link">Annuler</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="users.php" class="form-grid">
                <input type="hidden" name="action" value="<?= $user_edit ? 'modifier' : 'ajouter' ?>">
                <?php if ($user_edit): ?>
                    <input type="hidden" name="id" value="<?= (int)$user_edit['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="nom">Nom complet *</label>
                    <input type="text" id="nom" name="nom" required
                           value="<?= htmlspecialchars($user_edit['nom'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required
                           value="<?= htmlspecialchars($user_edit['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="role">Rôle *</label>
                    <select id="role" name="role" required>
                        <?php foreach ($roles_valides as $r): ?>
                            <option value="<?= $r ?>" <?= (isset($user_edit['role']) && $user_edit['role'] === $r) ? 'selected' : '' ?>>
                                <?= ucfirst($r) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!$user_edit): ?>
                <div class="form-group">
                    <label for="password">Mot de passe *</label>
                    <input type="password" id="password" name="password" required minlength="6" placeholder="Min. 6 caractères">
                </div>
                <?php endif; ?>

                <div class="form-group form-group-full">
                    <button type="submit" class="btn-submit">
                        <?= $user_edit ? '💾 Enregistrer' : '➕ Créer le compte' ?>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- ==========================================
         RECHERCHE / FILTRE / TRI
         ========================================== -->
    <section class="card">
        <div class="card-header">
            <h2>🔍 Recherche & filtres</h2>
        </div>
        <div class="card-body">
            <form method="GET" action="users.php" class="filters-bar">
                <input type="text" name="recherche" placeholder="Rechercher par nom ou email..."
                       value="<?= htmlspecialchars($recherche) ?>">

                <select name="role">
                    <option value="">Tous les rôles</option>
                    <?php foreach ($roles_valides as $r): ?>
                        <option value="<?= $r ?>" <?= $filtre_role === $r ? 'selected' : '' ?>>
                            <?= ucfirst($r) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="tri">
                    <option value="recent"   <?= $tri === 'recent'   ? 'selected' : '' ?>>Plus récents</option>
                    <option value="nom_asc"  <?= $tri === 'nom_asc'  ? 'selected' : '' ?>>Nom (A→Z)</option>
                    <option value="nom_desc" <?= $tri === 'nom_desc' ? 'selected' : '' ?>>Nom (Z→A)</option>
                    <option value="role"     <?= $tri === 'role'     ? 'selected' : '' ?>>Par rôle</option>
                </select>

                <button type="submit" class="btn-submit">Filtrer</button>
                <a href="users.php" class="btn-secondary">Réinitialiser</a>
            </form>
        </div>
    </section>

    <!-- ==========================================
         LISTE DES UTILISATEURS
         ========================================== -->
    <section class="card">
        <div class="card-header">
            <h2>📋 Liste des utilisateurs (<?= count($users) ?>)</h2>
        </div>
        <div class="card-body">
            <?php if (empty($users)): ?>
                <p class="empty">Aucun utilisateur trouvé.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th>Créé le</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u):
                            $verrouille = !empty($u['verrouille_jusqua']) && strtotime($u['verrouille_jusqua']) > time();
                            $soi_meme = ((int)$u['id'] === (int)$_SESSION['user_id']);
                        ?>
                            <tr>
                                <td>#<?= (int)$u['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($u['nom']) ?></strong>
                                    <?php if ($soi_meme): ?>
                                        <small class="text-muted">(vous)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <?php
                                    $badge_class = match($u['role']) {
                                        'admin'   => 'badge-danger',
                                        'vendeur' => 'badge-warning',
                                        default   => 'badge-success'
                                    };
                                    ?>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= $u['role'] === 'admin' ? '👑 ' : ($u['role'] === 'vendeur' ? '💼 ' : '🛒 ') ?>
                                        <?= ucfirst($u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($verrouille): ?>
                                        <span class="badge badge-danger">🔒 Verrouillé</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">✅ Actif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($u['cree_le'])) ?></td>
                                <td class="actions-cell">
                                    <a href="users.php?edit=<?= (int)$u['id'] ?>" class="btn-icon" title="Modifier">✏️</a>

                                    <?php if ($verrouille): ?>
                                        <a href="users.php?deverrouiller=<?= (int)$u['id'] ?>" class="btn-icon" title="Déverrouiller">🔓</a>
                                    <?php elseif (!$soi_meme): ?>
                                        <a href="users.php?verrouiller=<?= (int)$u['id'] ?>" class="btn-icon" title="Verrouiller"
                                           onclick="return confirm('Verrouiller ce compte pour 24h ?');">🔒</a>
                                    <?php endif; ?>

                                    <?php if (!$soi_meme): ?>
                                        <a href="users.php?supprimer=<?= (int)$u['id'] ?>" class="btn-icon btn-danger" title="Supprimer"
                                           onclick="return confirm('Supprimer définitivement cet utilisateur ?');">🗑️</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (isset($_GET['reset']) && (int)$_GET['reset'] === (int)$u['id']): ?>
                                <tr class="reset-row">
                                    <td colspan="7">
                                        <form method="POST" action="users.php" class="reset-form">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                            <strong>Réinitialiser le mot de passe de <?= htmlspecialchars($u['nom']) ?> :</strong>
                                            <input type="password" name="nouveau_mdp" placeholder="Nouveau mot de passe (min. 6)" required minlength="6">
                                            <button type="submit" class="btn-submit">🔑 Réinitialiser</button>
                                            <a href="users.php" class="btn-secondary">Annuler</a>
                                        </form>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="text-muted" style="margin-top:15px; font-size:0.85rem;">
                    💡 Astuce : cliquez sur ✏️ pour modifier un utilisateur et accéder à la réinitialisation du mot de passe.
                </p>
            <?php endif; ?>
        </div>
    </section>

</main>
</body>
</html>