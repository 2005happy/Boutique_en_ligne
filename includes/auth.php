<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1) Vérification de la connexion (Critère 42)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// 2) Déconnexion automatique après 30 min d'inactivité (Critère 9)
$timeout = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?expired=1");
    exit;
}
$_SESSION['last_activity'] = time(); // mise à jour

// 3) Vérification du rôle admin (Critères 2, 3)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?acces_refuse=1");
    exit;
}
?>