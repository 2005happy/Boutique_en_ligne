<?php
// includes/auth_vendeur.php
// ⚠️ À inclure dans les pages du dossier vandeur/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Timeout 30 min (Critère 9)
$timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?expired=1");
    exit;
}
$_SESSION['last_activity'] = time();

// ⚠️ Autoriser admin ET vendeur
if (!in_array($_SESSION['role'], ['admin', 'vendeur'], true)) {
    header("Location: ../index.php?acces_refuse=1");
    exit;
}

// Vérifier que le vendeur est bien validé
if ($_SESSION['role'] === 'vendeur') {
    $stmt = $pdo->prepare("SELECT statut FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $statut = $stmt->fetchColumn();
    if ($statut !== 'actif') {
        header("Location: ../index.php?compte_non_valide=1");
        exit;
    }
}
?>