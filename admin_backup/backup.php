<?php
// admin/backup.php
require_once '../config/init.php';
require_once '../includes/auth.php';

$dossier_backups = __DIR__ . '/../backups/';
if (!is_dir($dossier_backups)) {
    mkdir($dossier_backups, 0775, true);
}

$message = '';
$type_message = 'success';

// Paramètres de connexion (récupérés depuis database.php)
$db_host = 'localhost';
$db_name = 'gestion_boutique';
$db_user = 'mbappy';
$db_pass = 'happy@257';
$db_socket = '/run/mysqld/mysqld.sock';

// ==========================================
// SAUVEGARDE (Critère 39)
// ==========================================
if (isset($_GET['sauvegarder'])) {
    $nom_fichier = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $chemin = $dossier_backups . $nom_fichier;
    $debug = [];
    $succes_mysqldump = false;

    // ===== MÉTHODE 1 : mysqldump =====
    $mysqldump = '/usr/bin/mysqldump';
    if (!file_exists($mysqldump)) {
        $mysqldump = trim(shell_exec('which mysqldump 2>/dev/null') ?? '');
    }

    if (!empty($mysqldump) && file_exists($mysqldump)) {
        $cmd = sprintf(
            '%s --socket=%s --user=%s --password=%s %s -r %s 2>&1',
            escapeshellarg($mysqldump),
            escapeshellarg($db_socket),
            escapeshellarg($db_user),
            escapeshellarg($db_pass),
            escapeshellarg($db_name),
            escapeshellarg($chemin)
        );

        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        // Filtrer le warning "Using a password on the command line" (non bloquant)
        $output_filtre = array_filter($output, fn($l) => !str_contains($l, 'Using a password'));

        $debug[] = "mysqldump : code $code";
        if (!empty($output_filtre)) {
            $debug[] = "Sortie : " . implode(' | ', $output_filtre);
        }

        if ($code === 0 && file_exists($chemin) && filesize($chemin) > 100) {
            $succes_mysqldump = true;
        }
    } else {
        $debug[] = "mysqldump introuvable";
    }

    // ===== MÉTHODE 2 : fallback PHP (si mysqldump échoue) =====
    if (!$succes_mysqldump) {
        $debug[] = "→ Fallback PHP activé";
        try {
            generer_backup_php($pdo, $chemin);
        } catch (Throwable $e) {
            $debug[] = "Erreur fallback : " . $e->getMessage();
        }
    }

    // ===== VÉRIFICATION FINALE =====
    if (file_exists($chemin) && filesize($chemin) > 100) {
        $taille = round(filesize($chemin) / 1024, 1);
        $methode = $succes_mysqldump ? 'mysqldump' : 'fallback PHP';
        $message = "✅ Sauvegarde créée : <strong>$nom_fichier</strong> ($taille Ko) <em>(via $methode)</em>";
        $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
            ->execute([$_SESSION['user_id'], 'SAUVEGARDE', "Création de $nom_fichier ($methode)"]);
    } else {
        $message = "❌ Impossible de créer la sauvegarde.<br>"
                 . "<strong>Diagnostic :</strong><br>"
                 . "<pre style='background:#fdecea;padding:10px;border-radius:6px;font-size:0.8rem;white-space:pre-wrap;'>"
                 . htmlspecialchars(implode("\n", $debug))
                 . "</pre>";
        $type_message = 'error';
    }
}

// ==========================================
// RESTAURATION (Critère 40)
// ==========================================
if (isset($_GET['restaurer'])) {
    $fichier = basename($_GET['restaurer']);
    $chemin = $dossier_backups . $fichier;

    if (!file_exists($chemin)) {
        $message = "❌ Fichier introuvable.";
        $type_message = 'error';
    } else {
        try {
            // 1) Sauvegarde de sécurité AVANT restauration
            $backup_secours = 'secours_avant_restauration_' . date('Y-m-d_H-i-s') . '.sql';
            $chemin_secours = $dossier_backups . $backup_secours;
            generer_backup_php($pdo, $chemin_secours);

            // 2) Lecture du contenu
            $contenu = file_get_contents($chemin);
            if ($contenu === false || strlen($contenu) < 50) {
                throw new Exception("Fichier vide ou illisible.");
            }

            // 3) Désactiver les contraintes de clés étrangères
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            // 4) Découpage en requêtes
            $requetes = [];
            $buffer = '';
            $lignes = explode("\n", $contenu);
            foreach ($lignes as $ligne) {
                $ligne = rtrim($ligne);
                if ($ligne === '' || str_starts_with($ligne, '--') || str_starts_with($ligne, '/*')) {
                    continue;
                }
                $buffer .= $ligne . "\n";
                if (str_ends_with(rtrim($ligne), ';')) {
                    $requetes[] = trim($buffer);
                    $buffer = '';
                }
            }

            // 5) Exécution SANS transaction (DDL = commit implicite)
            $nb_executees = 0;
            $nb_erreurs = 0;

            foreach ($requetes as $req) {
                $req = trim($req);
                if ($req === '') continue;

                // Avant CREATE TABLE, supprimer la table si elle existe
                if (preg_match('/^CREATE TABLE\s+`?(\w+)`?/i', $req, $m)) {
                    $table = $m[1];
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$table`");
                    } catch (PDOException $e) {
                        // Ignorer si la table n'existe pas ou dépendances
                    }
                }

                try {
                    $pdo->exec($req);
                    $nb_executees++;
                } catch (PDOException $e) {
                    $nb_erreurs++;
                    // Certaines erreurs sont bénignes (ex: table déjà supprimée)
                    // On continue quand même pour restaurer un maximum
                }
            }

            // 6) Réactiver les contraintes
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            $message = "✅ Base restaurée depuis <strong>$fichier</strong>.<br>"
                     . "📊 <strong>$nb_executees</strong> requête(s) exécutée(s)";
            if ($nb_erreurs > 0) {
                $message .= " — ⚠️ <strong>$nb_erreurs</strong> requête(s) ignorée(s) (probablement sans impact).";
            }
            $message .= "<br>💾 Sauvegarde de secours : <strong>$backup_secours</strong>";

            $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], 'RESTAURATION', "Restauration depuis $fichier ($nb_executees requêtes, $nb_erreurs erreurs)"]);

        } catch (Exception $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $message = "❌ Erreur de restauration : " . $e->getMessage();
            $type_message = 'error';
        }
    }
}

// ==========================================
// TÉLÉCHARGEMENT
// ==========================================
if (isset($_GET['telecharger'])) {
    $fichier = basename($_GET['telecharger']);
    $chemin = $dossier_backups . $fichier;

    if (file_exists($chemin)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $fichier . '"');
        header('Content-Length: ' . filesize($chemin));
        readfile($chemin);

        $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
            ->execute([$_SESSION['user_id'], 'TELECHARGEMENT_BACKUP', "Téléchargement de $fichier"]);
        exit;
    }
}

// ==========================================
// SUPPRESSION
// ==========================================
if (isset($_GET['supprimer'])) {
    $fichier = basename($_GET['supprimer']);
    $chemin = $dossier_backups . $fichier;

    if (file_exists($chemin)) {
        unlink($chemin);
        $message = "🗑️ Sauvegarde <strong>$fichier</strong> supprimée.";
        $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
            ->execute([$_SESSION['user_id'], 'SUPPR_BACKUP', "Suppression de $fichier"]);
    }
}

// ==========================================
// LISTE DES SAUVEGARDES
// ==========================================
$sauvegardes = [];
if (is_dir($dossier_backups)) {
    $fichiers = glob($dossier_backups . '*.sql');
    foreach ($fichiers as $f) {
        $sauvegardes[] = [
            'nom'     => basename($f),
            'taille'  => filesize($f),
            'date'    => filemtime($f),
            'chemin'  => $f
        ];
    }
    // Trier par date décroissante
    usort($sauvegardes, fn($a, $b) => $b['date'] - $a['date']);
}

$total_taille = array_sum(array_column($sauvegardes, 'taille'));

// ==========================================
// FONCTION FALLBACK : backup en pur PHP
// ==========================================
function generer_backup_php(PDO $pdo, string $chemin): void {
    $sql = "-- Sauvegarde Ma Boutique\n";
    $sql .= "-- Généré le " . date('d/m/Y H:i:s') . "\n\n";
    $sql .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // Structure
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $create['Create Table'] . ";\n\n";

        // Données
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $colonnes = array_keys($rows[0]);
            $cols = '`' . implode('`, `', $colonnes) . '`';
            $sql .= "INSERT INTO `$table` ($cols) VALUES\n";

            $valeurs = [];
            foreach ($rows as $row) {
                $vals = array_map(function ($v) use ($pdo) {
                    if ($v === null) return 'NULL';
                    if (is_int($v) || is_float($v)) return $v;
                    return $pdo->quote($v);
                }, array_values($row));
                $valeurs[] = '(' . implode(', ', $vals) . ')';
            }
            $sql .= implode(",\n", $valeurs) . ";\n\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    file_put_contents($chemin, $sql);
}

// ==========================================
// HELPERS
// ==========================================
function taille_lisible(int $o): string {
    if ($o < 1024) return $o . ' o';
    if ($o < 1024 * 1024) return round($o / 1024, 1) . ' Ko';
    return round($o / (1024 * 1024), 2) . ' Mo';
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair')) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sauvegardes - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="admin-body">

<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="admin-main">

    <header class="admin-topbar">
        <div>
            <h1>💾 Sauvegardes</h1>
            <p class="topbar-subtitle">Sauvegardez et restaurez la base de données</p>
        </div>
        <div class="topbar-actions">
            <a href="?sauvegarder=1" class="btn-submit" style="width:auto;padding:12px 22px;">
                💾 Nouvelle sauvegarde
            </a>
            <div class="topbar-user">
                <span class="avatar"><?= strtoupper(substr($_SESSION['nom'], 0, 1)) ?></span>
                <span><?= htmlspecialchars($_SESSION['nom']) ?></span>
            </div>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert alert-<?= $type_message === 'success' ? 'success' : 'error' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <section class="stats-grid">
        <div class="stat-card stat-blue">
            <div class="stat-icon">📦</div>
            <div class="stat-content">
                <p class="stat-label">Sauvegardes</p>
                <h3 class="stat-value"><?= count($sauvegardes) ?></h3>
            </div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-icon">💾</div>
            <div class="stat-content">
                <p class="stat-label">Taille totale</p>
                <h3 class="stat-value"><?= taille_lisible($total_taille) ?></h3>
            </div>
        </div>
        <div class="stat-card stat-orange">
            <div class="stat-icon">📅</div>
            <div class="stat-content">
                <p class="stat-label">Dernière sauvegarde</p>
                <h3 class="stat-value" style="font-size:1rem;">
                    <?= !empty($sauvegardes) ? date('d/m/Y H:i', $sauvegardes[0]['date']) : 'Aucune' ?>
                </h3>
            </div>
        </div>
        <div class="stat-card stat-red">
            <div class="stat-icon">🗄️</div>
            <div class="stat-content">
                <p class="stat-label">Base de données</p>
                <h3 class="stat-value" style="font-size:1rem;"><?= $db_name ?></h3>
            </div>
        </div>
    </section>

    <!-- Avertissement -->
    <div class="alert" style="background:#fef5e7;border-left-color:#f39c12;color:#b9770e;">
        ⚠️ <strong>Attention :</strong> la restauration remplace toutes les données actuelles par celles de la sauvegarde.
        Assurez-vous de vouloir effectuer cette opération.
    </div>

    <!-- Liste des sauvegardes -->
    <section class="card">
        <div class="card-header">
            <h2>📋 Sauvegardes disponibles (<?= count($sauvegardes) ?>)</h2>
        </div>
        <div class="card-body">
            <?php if (empty($sauvegardes)): ?>
                <p class="empty">
                    Aucune sauvegarde. Cliquez sur <strong>💾 Nouvelle sauvegarde</strong> pour en créer une.
                </p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fichier</th>
                            <th>Date</th>
                            <th>Taille</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sauvegardes as $s): ?>
                            <tr>
                                <td>
                                    <span style="font-size:1.3rem;">🗄️</span>
                                    <strong><?= htmlspecialchars($s['nom']) ?></strong>
                                </td>
                                <td>
                                    <strong><?= date('d/m/Y', $s['date']) ?></strong><br>
                                    <small class="text-muted"><?= date('H:i:s', $s['date']) ?></small>
                                </td>
                                <td><?= taille_lisible($s['taille']) ?></td>
                                <td class="actions-cell">
                                    <a href="?telecharger=<?= urlencode($s['nom']) ?>" class="btn-icon" title="Télécharger">⬇️</a>
                                    <a href="?restaurer=<?= urlencode($s['nom']) ?>" class="btn-icon" title="Restaurer"
                                       onclick="return confirm('⚠️ Restaurer cette sauvegarde ?\n\nToutes les données actuelles seront REMPLACÉES par celles de ce fichier.\n\nCette action est irréversible.');">
                                        ♻️
                                    </a>
                                    <a href="?supprimer=<?= urlencode($s['nom']) ?>" class="btn-icon btn-danger" title="Supprimer"
                                       onclick="return confirm('Supprimer cette sauvegarde ?');">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

    <!-- Informations -->
    <section class="card">
        <div class="card-header">
            <h2>ℹ️ Informations</h2>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <p class="info-label">Dossier des sauvegardes</p>
                    <p class="info-value" style="font-size:0.85rem;">/backups/</p>
                </div>
                <div class="info-item">
                    <p class="info-label">Format</p>
                    <p class="info-value">SQL</p>
                </div>
                <div class="info-item">
                    <p class="info-label">Méthode</p>
                    <p class="info-value" style="font-size:0.85rem;">mysqldump / PHP</p>
                </div>
                <div class="info-item">
                    <p class="info-label">Emplacement</p>
                    <p class="info-value" style="font-size:0.85rem;"><?= htmlspecialchars($dossier_backups) ?></p>
                </div>
            </div>
            <p class="text-muted" style="margin-top:20px; font-size:0.85rem;">
                💡 <strong>Conseil :</strong> téléchargez régulièrement vos sauvegardes sur un stockage externe
                (clé USB, cloud) pour éviter toute perte de données en cas de problème serveur.
            </p>
        </div>
    </section>

</main>
</body>
</html>