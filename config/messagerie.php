<?php
// config/messagerie.php

/**
 * Récupère ou crée une conversation entre deux utilisateurs.
 */
function get_ou_creer_conversation(PDO $pdo, int $user_a, int $user_b): int {
    // Toujours ordonner les IDs pour garantir l'unicité
    $min = min($user_a, $user_b);
    $max = max($user_a, $user_b);

    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE user1_id = ? AND user2_id = ?");
    $stmt->execute([$min, $max]);
    $id = $stmt->fetchColumn();

    if ($id) return (int)$id;

    $pdo->prepare("INSERT INTO conversations (user1_id, user2_id) VALUES (?, ?)")
        ->execute([$min, $max]);
    return (int)$pdo->lastInsertId();
}

/**
 * Envoie un message.
 */
function envoyer_message(PDO $pdo, int $conv_id, int $expediteur_id, string $contenu): int {
    $contenu = trim($contenu);
    if ($contenu === '') return 0;

    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, expediteur_id, contenu) VALUES (?, ?, ?)");
    $stmt->execute([$conv_id, $expediteur_id, $contenu]);
    $message_id = (int)$pdo->lastInsertId();

    // Mettre à jour la date de la conversation
    $pdo->prepare("UPDATE conversations SET date_maj = NOW() WHERE id = ?")->execute([$conv_id]);

    return $message_id;
}

/**
 * Nombre de messages non lus pour un utilisateur.
 */
function nb_messages_non_lus(PDO $pdo, int $user_id): int {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM messages m
        INNER JOIN conversations c ON m.conversation_id = c.id
        WHERE (c.user1_id = ? OR c.user2_id = ?)
          AND m.expediteur_id != ?
          AND m.lu = 0
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    return (int)$stmt->fetchColumn();
}

/**
 * Nombre de notifications non lues.
 */
function nb_notifications_non_lues(PDO $pdo, int $user_id): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND lu = 0");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

/**
 * Crée une notification.
 */
function notifier(PDO $pdo, int $user_id, string $type, string $message, ?string $lien = null): void {
    $pdo->prepare("INSERT INTO notifications (utilisateur_id, type, message, lien) VALUES (?, ?, ?, ?)")
        ->execute([$user_id, $type, $message, $lien]);
}
?>