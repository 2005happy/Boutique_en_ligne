<?php
// admin/settings.php
require_once '../config/init.php';
require_once '../includes/auth.php';

$message = '';
$type_message = 'success';

// ==========================================
// TRAITEMENT DES FORMULAIRES
// ==========================================

// --- Paramètres généraux ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generaux') {
    $champs = [
        'nom_boutique'    => trim($_POST['nom_boutique'] ?? ''),
        'slogan'          => trim($_POST['slogan'] ?? ''),
        'email_contact'   => trim($_POST['email_contact'] ?? ''),
        'telephone'       => trim($_POST['telephone'] ?? ''),
        'devise'          => trim($_POST['devise'] ?? '€'),
        'elements_par_page' => (int)($_POST['elements_par_page'] ?? 20),
    ];

    // Validation
    if ($champs['nom_boutique'] === '') {
        $message = "Le nom de la boutique est obligatoire.";
        $type_message = 'error';
    } elseif (!empty($champs['email_contact']) && !filter_var($champs['email_contact'], FILTER_VALIDATE_EMAIL)) {
        $message = "Email de contact invalide.";
        $type_message = 'error';
    } elseif ($champs['elements_par_page'] < 5 || $champs['elements_par_page'] > 100) {
        $message = "Le nombre d'éléments par page doit être entre 5 et 100.";
        $type_message = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO parametres (cle, valeur)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)
            ");
            foreach ($champs as $cle => $valeur) {
                $stmt->execute([$cle, $valeur]);
            }
            $message = "Paramètres généraux enregistrés.";
            $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], 'MODIF_PARAMETRES', "Mise à jour des paramètres généraux"]);
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $type_message = 'error';
        }
    }
}

// --- Choix du thème (Critère 45) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'theme') {
    $theme = $_POST['theme'] ?? 'clair';
    if (!in_array($theme, ['clair', 'sombre'], true)) {
        $theme = 'clair';
    }
    try {
        $pdo->prepare("
            INSERT INTO parametres (cle, valeur)
            VALUES ('theme', ?)
            ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)
        ")->execute([$theme]);

        $pdo->prepare("UPDATE utilisateurs SET theme = ? WHERE id = ?")
            ->execute([$theme, $_SESSION['user_id']]);
        $_SESSION['theme'] = $theme;
        $GLOBALS['parametres']['theme'] = $theme;

        $message = "Thème « $theme » activé.";
        $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
            ->execute([$_SESSION['user_id'], 'MODIF_THEME', "Thème changé pour : $theme"]);
    } catch (PDOException $e) {
        $message = "Erreur : " . $e->getMessage();
        $type_message = 'error';
    }
}

// --- Options de sécurité / fonctionnement ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'options') {
    $maintenance = isset($_POST['maintenance']) ? '1' : '0';
    $inscription_ouverte = isset($_POST['inscription_ouverte']) ? '1' : '0';
    $validation_vendeurs = isset($_POST['validation_vendeurs']) ? '1' : '0';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO parametres (cle, valeur)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)
        ");
        $stmt->execute(['maintenance', $maintenance]);
        $stmt->execute(['inscription_ouverte', $inscription_ouverte]);
        $stmt->execute(['validation_vendeurs', $validation_vendeurs]);

        $message = "Options de fonctionnement enregistrées.";
        $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
            ->execute([$_SESSION['user_id'], 'MODIF_OPTIONS', "Mise à jour des options de fonctionnement"]);
    } catch (PDOException $e) {
        $message = "Erreur : " . $e->getMessage();
        $type_message = 'error';
    }
}

// --- Réinitialisation des paramètres ---
if (isset($_GET['reset'])) {
    try {
        $pdo->exec("DELETE FROM parametres");
        $pdo->exec("
            INSERT INTO parametres (cle, valeur, description) VALUES
            ('nom_boutique', 'Ma Boutique', 'Nom affiché de la boutique'),
            ('slogan', 'Qualité et confiance depuis 2024', 'Slogan de la boutique'),
            ('email_contact', 'contact@maboutique.com', 'Email de contact public'),
            ('telephone', '+33 1 23 45 67 89', 'Téléphone de contact'),
            ('devise', '€', 'Symbole monétaire'),
            ('theme', 'clair', 'Thème par défaut'),
            ('maintenance', '0', 'Mode maintenance'),
            ('inscription_ouverte', '1', 'Autoriser les inscriptions'),
            ('validation_vendeurs', '1', 'Validation requise pour vendeurs'),
            ('elements_par_page', '20', 'Éléments par page')
        ");
        $message = "Paramètres réinitialisés aux valeurs par défaut.";
        $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
            ->execute([$_SESSION['user_id'], 'RESET_PARAMETRES', "Réinitialisation des paramètres"]);
    } catch (PDOException $e) {
        $message = "Erreur : " . $e->getMessage();
        $type_message = 'error';
    }
}

// ==========================================
// LECTURE DES PARAMÈTRES ACTUELS
// ==========================================
$p = get_tous_parametres($pdo);
$theme_actuel = $p['theme'] ?? 'clair';
$theme_page = param('theme', 'clair');

// Statistiques de la base (info utile pour l'admin)
$stats_bdd = [
    'utilisateurs' => (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn(),
    'produits'     => (int)$pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn(),
    'documents'    => (int)$pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
    'activites'    => (int)$pdo->query("SELECT COUNT(*) FROM journal_activites")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars($theme_page, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="admin-body">

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="admin-main">

    <header class="admin-topbar">
        <div>
            <h1>⚙️ Paramètres du système</h1>
            <p class="topbar-subtitle">Configuration générale de la boutique</p>
        </div>
        <div class="topbar-actions">
            <a href="?reset=1" class="btn-secondary" title="Réinitialiser"
               onclick="return confirm('Réinitialiser TOUS les paramètres aux valeurs par défaut ?');">
                🔄 Réinitialiser
            </a>
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

    <!-- ==========================================
         PARAMÈTRES GÉNÉRAUX
         ========================================== -->
    <section class="card">
        <div class="card-header">
            <h2>🏪 Informations de la boutique</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="settings.php" class="form-grid">
                <input type="hidden" name="action" value="generaux">

                <div class="form-group form-group-full">
                    <label for="nom_boutique">Nom de la boutique *</label>
                    <input type="text" id="nom_boutique" name="nom_boutique" required maxlength="100"
                           value="<?= htmlspecialchars($p['nom_boutique'] ?? 'Ma Boutique') ?>">
                </div>

                <div class="form-group form-group-full">
                    <label for="slogan">Slogan</label>
                    <input type="text" id="slogan" name="slogan" maxlength="200"
                           value="<?= htmlspecialchars($p['slogan'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="email_contact">Email de contact</label>
                    <input type="email" id="email_contact" name="email_contact"
                           value="<?= htmlspecialchars($p['email_contact'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="telephone">Téléphone</label>
                    <input type="text" id="telephone" name="telephone" maxlength="30"
                           value="<?= htmlspecialchars($p['telephone'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="devise">Symbole monétaire</label>
                    <input type="text" id="devise" name="devise" maxlength="5"
                           value="<?= htmlspecialchars($p['devise'] ?? '€') ?>">
                </div>

                <div class="form-group">
                    <label for="elements_par_page">Éléments par page</label>
                    <input type="number" id="elements_par_page" name="elements_par_page" min="5" max="100"
                           value="<?= htmlspecialchars($p['elements_par_page'] ?? 20) ?>">
                </div>

                <div class="form-group form-group-full">
                    <button type="submit" class="btn-submit">💾 Enregistrer les informations</button>
                </div>
            </form>
        </div>
    </section>

    <!-- ==========================================
         THÈMES (Critère 45)
         ========================================== -->
    <section class="card">
        <div class="card-header">
            <h2>🎨 Apparence — Thème</h2>
        </div>
        <div class="card-body">
            <p class="text-muted" style="margin-bottom:15px;">
                Ce thème devient le défaut du site et s'applique aussi à votre compte administrateur.
                Les autres utilisateurs conservent leur thème personnel.
            </p>
            <form method="POST" action="settings.php" class="theme-selector">
                <input type="hidden" name="action" value="theme">

                <label class="theme-card <?= $theme_actuel === 'clair' ? 'active' : '' ?>">
                    <input type="radio" name="theme" value="clair" <?= $theme_actuel === 'clair' ? 'checked' : '' ?>>
                    <div class="theme-preview theme-preview-light">
                        <div class="theme-preview-header"></div>
                        <div class="theme-preview-body">
                            <div class="theme-preview-line"></div>
                            <div class="theme-preview-line short"></div>
                        </div>
                    </div>
                    <span class="theme-label">☀️ Thème clair</span>
                    <small>Fond blanc, texte sombre</small>
                </label>

                <label class="theme-card <?= $theme_actuel === 'sombre' ? 'active' : '' ?>">
                    <input type="radio" name="theme" value="sombre" <?= $theme_actuel === 'sombre' ? 'checked' : '' ?>>
                    <div class="theme-preview theme-preview-dark">
                        <div class="theme-preview-header"></div>
                        <div class="theme-preview-body">
                            <div class="theme-preview-line"></div>
                            <div class="theme-preview-line short"></div>
                        </div>
                    </div>
                    <span class="theme-label">🌙 Thème sombre</span>
                    <small>Fond sombre, texte clair</small>
                </label>

                <div class="form-group form-group-full">
                    <button type="submit" class="btn-submit">🎨 Appliquer le thème</button>
                </div>
            </form>
        </div>
    </section>

    <!-- ==========================================
         OPTIONS DE FONCTIONNEMENT
         ========================================== -->
    <section class="card">
        <div class="card-header">
            <h2>🔧 Options de fonctionnement</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="options">

                <div class="options-list">
                    <label class="option-row">
                        <div>
                            <strong>🚧 Mode maintenance</strong>
                            <small>Ferme l'accès public au site pour les non-admins.</small>
                        </div>
                        <input type="checkbox" name="maintenance" value="1"
                               <?= ($p['maintenance'] ?? '0') === '1' ? 'checked' : '' ?>>
                    </label>

                    <label class="option-row">
                        <div>
                            <strong>📝 Inscriptions ouvertes</strong>
                            <small>Autoriser la création de nouveaux comptes.</small>
                        </div>
                        <input type="checkbox" name="inscription_ouverte" value="1"
                               <?= ($p['inscription_ouverte'] ?? '1') === '1' ? 'checked' : '' ?>>
                    </label>

                    <label class="option-row">
                        <div>
                            <strong>⏳ Validation des vendeurs</strong>
                            <small>Les comptes vendeurs doivent être approuvés par un admin.</small>
                        </div>
                        <input type="checkbox" name="validation_vendeurs" value="1"
                               <?= ($p['validation_vendeurs'] ?? '1') === '1' ? 'checked' : '' ?>>
                    </label>
                </div>

                <div class="form-group" style="margin-top:20px;">
                    <button type="submit" class="btn-submit">💾 Enregistrer les options</button>
                </div>
            </form>
        </div>
    </section>

    <!-- ==========================================
         INFORMATIONS SYSTÈME
         ========================================== -->
    <section class="card">
        <div class="card-header">
            <h2>ℹ️ Informations système</h2>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <p class="info-label">Version PHP</p>
                    <p class="info-value"><?= PHP_VERSION ?></p>
                </div>
                <div class="info-item">
                    <p class="info-label">Base de données</p>
                    <p class="info-value">gestion_boutique</p>
                </div>
                <div class="info-item">
                    <p class="info-label">Utilisateurs</p>
                    <p class="info-value"><?= number_format($stats_bdd['utilisateurs']) ?></p>
                </div>
                <div class="info-item">
                    <p class="info-label">Produits</p>
                    <p class="info-value"><?= number_format($stats_bdd['produits']) ?></p>
                </div>
                <div class="info-item">
                    <p class="info-label">Documents</p>
                    <p class="info-value"><?= number_format($stats_bdd['documents']) ?></p>
                </div>
                <div class="info-item">
                    <p class="info-label">Entrées du journal</p>
                    <p class="info-value"><?= number_format($stats_bdd['activites']) ?></p>
                </div>
            </div>
        </div>
    </section>

</main>

<script>
// Aperçu dynamique des thèmes
document.querySelectorAll('.theme-card input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('active'));
        this.closest('.theme-card').classList.add('active');
    });
});
</script>

</body>
</html>