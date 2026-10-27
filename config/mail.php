<?php
// config/mail.php

/**
 * Envoie un email (ou affiche le lien en mode dev).
 * En production : configurez SMTP via PHPMailer.
 */
function envoyer_email_reinitialisation($email, $nom, $lien) {
    $nom_boutique = param('nom_boutique', 'Ma Boutique');
    $sujet = "Réinitialisation de votre mot de passe — $nom_boutique";
    $message = "Bonjour $nom,\n\n";
    $message .= "Vous avez demandé la réinitialisation de votre mot de passe.\n";
    $message .= "Cliquez sur ce lien pour définir un nouveau mot de passe (valable 1 heure) :\n\n";
    $message .= $lien . "\n\n";
    $message .= "Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.\n\n";
    $message .= "— L'équipe $nom_boutique";

    $headers = "From: no-reply@maboutique.local\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // Tentative d'envoi réel
    $envoye = @mail($email, $sujet, $message, $headers);

    // En local, mail() échoue souvent → on retourne false pour afficher le lien
    return $envoye;
}
?>