<?php
// admin/documents.php
require_once '../config/init.php';
require_once '../includes/auth.php';

// ==========================================
// CONFIGURATION
// ==========================================
$dossier_uploads = '../uploads/';
if (!is_dir($dossier_uploads)) {
    mkdir($dossier_uploads, 0775, true);
}

// Types MIME autorisés (sécurité)
$types_autorises = [
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/gif'       => 'gif',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'text/plain'      => 'txt',
    'application/zip' => 'zip',
];
$taille_max = 5 * 1024 * 1024; // 5 Mo

$message = '';
$type_message = 'success';

// ==========================================
// TÉLÉVERSEMENT (Critère 20) + VERSIONING (Critère 22)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'televerser') {
    if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        $message = "Aucun fichier valide n'a été téléversé.";
        $type_message = 'error';
    } elseif ($_FILES['fichier']['size'] > $taille_max) {
        $message = "Fichier trop volumineux (max 5 Mo).";
        $type_message = 'error';
    } else {
        $nom_original = basename($_FILES['fichier']['name']);
        $type_mime = mime_content_type($_FILES['fichier']['tmp_name']);
        $taille = (int)$_FILES['fichier']['size'];

        if (!array_key_exists($type_mime, $types_autorises)) {
            $message = "Type de fichier non autorisé ($type_mime).";
            $type_message = 'error';
        } else {
            try {
                // Vérifier s'il existe déjà un doc du même nom pour cet utilisateur (versioning)
                $stmt = $pdo->prepare("
                    SELECT id, version FROM documents
                    WHERE nom_fichier = ? AND televerse_par = ? AND archive = 0
                    ORDER BY version DESC LIMIT 1
                ");
                $stmt->execute([$nom_original, $_SESSION['user_id']]);
                $parent = $stmt->fetch();

                $nouvelle_version = $parent ? $parent['version'] + 1 : 1;
                $id_parent = $parent ? $parent['id'] : null;

                // Nom unique sur disque
                $extension = $types_autorises[$type_mime];
                $nom_stockage = uniqid('doc_', true) . '.' . $extension;
                $chemin_final = $dossier_uploads . $nom_stockage;

                if (move_uploaded_file($_FILES['fichier']['tmp_name'], $chemin_final)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO documents
                            (nom_fichier, chemin_fichier, type_mime, taille, version, id_parent, televerse_par)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $nom_original, $chemin_final, $type_mime, $taille,
                        $nouvelle_version, $id_parent, $_SESSION['user_id']
                    ]);

                    $message = "Fichier « " . htmlspecialchars($nom_original) . " » téléversé (version $nouvelle_version).";

                    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
                        ->execute([$_SESSION['user_id'], 'TELEVERSEMENT', "Fichier : $nom_original (v$nouvelle_version)"]);
                } else {
                    $message = "Échec de l'enregistrement du fichier sur le disque.";
                    $type_message = 'error';
                }
            } catch (PDOException $e) {
                $message = "Erreur : " . $e->getMessage();
                $type_message = 'error';
            }
        }
    }
}

// ==========================================
// TÉLÉCHARGEMENT (Critère 21)
// ==========================================
if (isset($_GET['telecharger'])) {
    $id = (int)$_GET['telecharger'];
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    if ($doc && file_exists($doc['chemin_fichier'])) {
        // Journal du téléchargement
        $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
            ->execute([$_SESSION['user_id'], 'TELECHARGEMENT', "Fichier : {$doc['nom_fichier']} (v{$doc['version']})"]);

        // Envoi du fichier
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $doc['type_mime']);
        header('Content-Disposition: attachment; filename="' . basename($doc['nom_fichier']) . '"');
        header('Content-Length: ' . filesize($doc['chemin_fichier']));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        readfile($doc['chemin_fichier']);
        exit;
    } else {
        $message = "Fichier introuvable sur le serveur.";
        $type_message = 'error';
    }
}

// ==========================================
// PARTAGE (Critère 23)
// ==========================================
if (isset($_GET['partager'])) {
    $id = (int)$_GET['partager'];
    // Générer un lien de partage unique (token)
    $token = bin2hex(random_bytes(16));
    try {
        $pdo->prepare("UPDATE documents SET token_partage = ?, partage_actif = 1 WHERE id = ?")
            ->execute([$token, $id]);

        $protocole = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $dossier = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\');
        $lien_partage = "$protocole://$host$dossier/share.php?token=$token";

        $message = "Lien de partage généré : <a href='$lien_partage' target='_blank'>$lien_partage</a>";
        $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
            ->execute([$_SESSION['user_id'], 'PARTAGE_DOC', "Document #$id partagé"]);
    } catch (PDOException $e) {
        $message = "Erreur : " . $e->getMessage();
        $type_message = 'error';
    }
}

// Révoquer un partage
if (isset($_GET['revoquer_partage'])) {
    $id = (int)$_GET['revoquer_partage'];
    $pdo->prepare("UPDATE documents SET partage_actif = 0, token_partage = NULL WHERE id = ?")
        ->execute([$id]);
    $message = "Partage révoqué.";
    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'REVOQUE_PARTAGE', "Document #$id : partage révoqué"]);
}

// ==========================================
// ARCHIVAGE / RESTAURATION (Critères 18, 19)
// ==========================================
if (isset($_GET['archiver'])) {
    $id = (int)$_GET['archiver'];
    $pdo->prepare("UPDATE documents SET archive = 1 WHERE id = ?")->execute([$id]);
    $message = "Document archivé.";
    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'ARCHIVE_DOC', "Archivage du document #$id"]);
}
if (isset($_GET['restaurer'])) {
    $id = (int)$_GET['restaurer'];
    $pdo->prepare("UPDATE documents SET archive = 0 WHERE id = ?")->execute([$id]);
    $message = "Document restauré.";
    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'RESTAURE_DOC', "Restauration du document #$id"]);
}

// ==========================================
// SUPPRESSION
// ==========================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $stmt = $pdo->prepare("SELECT chemin_fichier FROM documents WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    if ($doc && file_exists($doc['chemin_fichier'])) {
        @unlink($doc['chemin_fichier']);
    }
    $pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$id]);
    $message = "Document supprimé définitivement.";
    $pdo->prepare("INSERT INTO journal_activites (utilisateur_id, action, details) VALUES (?, ?, ?)")
        ->execute([$_SESSION['user_id'], 'SUPPR_DOC', "Suppression du document #$id"]);
}

// ==========================================
// FILTRES ET LISTE (Critères 24, 25)
// ==========================================
$recherche = trim($_GET['recherche'] ?? '');
$filtre_archive = isset($_GET['archive']) ? (int)$_GET['archive'] : 0;
$tri = $_GET['tri'] ?? 'recent';

$sql = "SELECT d.*, u.nom AS uploadeur,
        (SELECT COUNT(*) FROM documents d2 WHERE d2.id_parent = d.id) AS nb_versions
        FROM documents d
        LEFT JOIN utilisateurs u ON d.televerse_par = u.id
        WHERE d.archive = :archive AND d.id_parent IS NULL"; // on affiche les originaux, leurs versions sont liées
$params = [':archive' => $filtre_archive];

if ($recherche !== '') {
    $sql .= " AND d.nom_fichier LIKE :rech";
    $params[':rech'] = "%$recherche%";
}

$order = match($tri) {
    'nom_asc'   => "d.nom_fichier ASC",
    'taille'    => "d.taille DESC",
    'ancien'    => "d.date_televersement ASC",
    default     => "d.date_televersement DESC",
};
$sql .= " ORDER BY $order";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll();

// Statistiques
$stats = [
    'total'    => (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE archive = 0")->fetchColumn(),
    'taille'   => (int)$pdo->query("SELECT COALESCE(SUM(taille), 0) FROM documents WHERE archive = 0")->fetchColumn(),
    'partages' => (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE partage_actif = 1 AND archive = 0")->fetchColumn(),
    'archives' => (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE archive = 1")->fetchColumn(),
];

// ==========================================
// HELPERS
// ==========================================
function icone_fichier(string $mime): string {
    if (str_contains($mime, 'pdf'))   return '📕';
    if (str_contains($mime, 'image')) return '🖼️';
    if (str_contains($mime, 'word') || str_contains($mime, 'document')) return '📘';
    if (str_contains($mime, 'excel') || str_contains($mime, 'sheet'))   return '📗';
    if (str_contains($mime, 'zip'))   return '🗜️';
    if (str_contains($mime, 'text'))  return '📄';
    return '📁';
}

function taille_lisible(int $octets): string {
    if ($octets < 1024) return $octets . ' o';
    if ($octets < 1024 * 1024) return round($octets / 1024, 1) . ' Ko';
    return round($octets / (1024 * 1024), 2) . ' Mo';
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars(param('theme', 'clair'), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion documentaire - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/admin.css">
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="admin-body">

<?php require __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="admin-main">

    <header class="admin-topbar">
        <div>
            <h1>📁 Gestion documentaire</h1>
            <p class="topbar-subtitle">Téléversez, versionnez, partagez et archivez vos documents</p>
        </div>
        <div class="topbar-actions">
            <div class="topbar-user">
                <span class="avatar"><?= strtoupper(substr($_SESSION['nom'], 0, 1)) ?></span>
                <span><?= htmlspecialchars($_SESSION['nom']) ?></span>
            </div>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert alert-<?= $type_message === 'success' ? 'success' : 'error' ?>">
            <?= $message /* déjà échappé/voulu pour les liens de partage */ ?>
        </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <section class="stats-grid">
        <div class="stat-card stat-blue">
            <div class="stat-icon">📄</div>
            <div class="stat-content">
                <p class="stat-label">Documents actifs</p>
                <h3 class="stat-value"><?= $stats['total'] ?></h3>
            </div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-icon">💾</div>
            <div class="stat-content">
                <p class="stat-label">Espace utilisé</p>
                <h3 class="stat-value"><?= taille_lisible($stats['taille']) ?></h3>
            </div>
        </div>
        <div class="stat-card stat-orange">
            <div class="stat-icon">🔗</div>
            <div class="stat-content">
                <p class="stat-label">Partages actifs</p>
                <h3 class="stat-value"><?= $stats['partages'] ?></h3>
            </div>
        </div>
        <div class="stat-card stat-red">
            <div class="stat-icon">📦</div>
            <div class="stat-content">
                <p class="stat-label">Archivés</p>
                <h3 class="stat-value"><?= $stats['archives'] ?></h3>
            </div>
        </div>
    </section>

    <!-- Téléversement (Critère 20) -->
    <section class="card">
        <div class="card-header">
            <h2>⬆️ Téléverser un document</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="documents.php" enctype="multipart/form-data" class="form-grid">
                <input type="hidden" name="action" value="televerser">

                <div class="form-group form-group-full">
                    <label for="fichier">Fichier (max 5 Mo) — PDF, images, Word, Excel, ZIP, TXT</label>
                    <input type="file" id="fichier" name="fichier" required accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx,.txt,.zip">
                </div>

                <div class="form-group form-group-full">
                    <button type="submit" class="btn-submit">⬆️ Téléverser</button>
                </div>
            </form>
            <p class="text-muted" style="margin-top:10px; font-size:0.85rem;">
                💡 Si un fichier du même nom existe déjà pour vous, une nouvelle version sera créée automatiquement.
            </p>
        </div>
    </section>

    <!-- Filtres -->
    <section class="card">
        <div class="card-header">
            <h2>🔍 Recherche & filtres</h2>
        </div>
        <div class="card-body">
            <form method="GET" action="documents.php" class="filters-bar">
                <input type="text" name="recherche" placeholder="Rechercher un document..."
                       value="<?= htmlspecialchars($recherche) ?>">

                <select name="archive">
                    <option value="0" <?= $filtre_archive === 0 ? 'selected' : '' ?>>Documents actifs</option>
                    <option value="1" <?= $filtre_archive === 1 ? 'selected' : '' ?>>Documents archivés</option>
                </select>

                <select name="tri">
                    <option value="recent"  <?= $tri === 'recent'  ? 'selected' : '' ?>>Plus récents</option>
                    <option value="ancien"  <?= $tri === 'ancien'  ? 'selected' : '' ?>>Plus anciens</option>
                    <option value="nom_asc" <?= $tri === 'nom_asc' ? 'selected' : '' ?>>Nom (A→Z)</option>
                    <option value="taille"  <?= $tri === 'taille'  ? 'selected' : '' ?>>Taille (décroissant)</option>
                </select>

                <button type="submit" class="btn-submit">Filtrer</button>
                <a href="documents.php" class="btn-secondary">Réinitialiser</a>
            </form>
        </div>
    </section>

    <!-- Liste -->
    <section class="card">
        <div class="card-header">
            <h2>📋 Documents (<?= count($documents) ?>)</h2>
        </div>
        <div class="card-body">
            <?php if (empty($documents)): ?>
                <p class="empty">Aucun document trouvé.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fichier</th>
                            <th>Type</th>
                            <th>Taille</th>
                            <th>Version</th>
                            <th>Téléversé par</th>
                            <th>Date</th>
                            <th>Partage</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $d): ?>
                            <tr>
                                <td>
                                    <span style="font-size:1.4rem;"><?= icone_fichier($d['type_mime']) ?></span>
                                    <strong><?= htmlspecialchars($d['nom_fichier']) ?></strong>
                                    <?php if ($d['nb_versions'] > 0): ?>
                                        <br><small class="text-muted"><?= $d['nb_versions'] ?> version(s) supplémentaire(s)</small>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= htmlspecialchars($d['type_mime']) ?></small></td>
                                <td><?= taille_lisible($d['taille']) ?></td>
                                <td><span class="badge badge-info">v<?= (int)$d['version'] ?></span></td>
                                <td><?= htmlspecialchars($d['uploadeur'] ?? '—') ?></td>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($d['date_televersement'])) ?></strong><br>
                                    <small class="text-muted"><?= date('H:i', strtotime($d['date_televersement'])) ?></small>
                                </td>
                                <td>
                                    <?php if ($d['partage_actif']): ?>
                                        <span class="badge badge-success">🔗 Actif</span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <a href="?telecharger=<?= (int)$d['id'] ?>" class="btn-icon" title="Télécharger">⬇️</a>

                                    <?php if ($filtre_archive === 1): ?>
                                        <a href="?restaurer=<?= (int)$d['id'] ?>" class="btn-icon" title="Restaurer">♻️</a>
                                    <?php else: ?>
                                        <?php if ($d['partage_actif']): ?>
                                            <a href="?revoquer_partage=<?= (int)$d['id'] ?>" class="btn-icon" title="Révoquer partage"
                                               onclick="return confirm('Révoquer le partage ?');">🔒</a>
                                        <?php else: ?>
                                            <a href="?partager=<?= (int)$d['id'] ?>" class="btn-icon" title="Générer un lien de partage">🔗</a>
                                        <?php endif; ?>
                                        <a href="?archiver=<?= (int)$d['id'] ?>" class="btn-icon" title="Archiver"
                                           onclick="return confirm('Archiver ce document ?');">📥</a>
                                    <?php endif; ?>

                                    <a href="?supprimer=<?= (int)$d['id'] ?>" class="btn-icon btn-danger" title="Supprimer"
                                       onclick="return confirm('Supprimer définitivement ce document ?');">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

</main>
</body>
</html>