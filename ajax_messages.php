<?php
require_once 'config/init.php';

if (!isset($_SESSION['user_id'])) exit;

$conv_id = (int)($_GET['conv'] ?? 0);
$uid = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
$stmt->execute([$conv_id, $uid, $uid]);
if (!$stmt->fetch()) exit;

$stmt = $pdo->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY date_envoi ASC");
$stmt->execute([$conv_id]);

$result = [];
foreach ($stmt->fetchAll() as $m) {
    $result[] = [
        'contenu' => htmlspecialchars($m['contenu']),
        'heure' => date('H:i', strtotime($m['date_envoi'])),
        'moi' => ((int)$m['expediteur_id'] === $uid)
    ];
}
echo json_encode($result);