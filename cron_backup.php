<?php
// cron_backup.php
// À exécuter via cron : 0 2 * * * php /var/www/html/Boutique/cron_backup.php
require_once __DIR__ . '/config/database.php';

$dossier = __DIR__ . '/backups/';
if (!is_dir($dossier)) mkdir($dossier, 0775, true);

$nom = 'auto_backup_' . date('Y-m-d_H-i-s') . '.sql';
$chemin = $dossier . $nom;

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$sql = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
foreach ($tables as $t) {
    $c = $pdo->query("SHOW CREATE TABLE `$t`")->fetch();
    $sql .= "DROP TABLE IF EXISTS `$t`;\n" . $c['Create Table'] . ";\n";
    $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), array_values($r));
        $cols = '`' . implode('`,`', array_keys($r)) . '`';
        $sql .= "INSERT INTO `$t` ($cols) VALUES (" . implode(',', $vals) . ");\n";
    }
    $sql .= "\n";
}
$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
file_put_contents($chemin, $sql);

// Garder seulement les 7 dernières sauvegardes auto
$fichiers = glob($dossier . 'auto_backup_*.sql');
usort($fichiers, fn($a, $b) => filemtime($b) - filemtime($a));
foreach (array_slice($fichiers, 7) as $f) unlink($f);

echo "Sauvegarde créée : $nom\n";