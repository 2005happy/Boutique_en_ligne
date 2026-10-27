<?php
// config/init.php
// ⚠️ À inclure EN TOUT PREMIER sur chaque page publique.

// 1) Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2) Base de données
require_once __DIR__ . '/database.php';

// 3) Helper de paramètres
require_once __DIR__ . '/settings.php';

// 4) Charger tous les paramètres une seule fois
$GLOBALS['parametres'] = get_tous_parametres($pdo);

// 5) Fonction helper pour accès rapide
function param(string $cle, $defaut = null) {
    return $GLOBALS['parametres'][$cle] ?? $defaut;
}

// 6) Vérification du mode maintenance (Critère 44)
$maintenance = param('maintenance', '0');
$role = $_SESSION['role'] ?? null;

// Pages autorisées pendant la maintenance (pour ne pas bloquer l'admin et les pages système)
$page_actuelle = basename($_SERVER['PHP_SELF']);
$pages_autorisees = ['login.php', 'logout.php', 'maintenance.php'];

if ($maintenance === '1'
    && $role !== 'admin'
    && !in_array($page_actuelle, $pages_autorisees, true)) {
    header("Location: " . (str_starts_with($_SERVER['PHP_SELF'], '/Boutique/admin/') ? '../maintenance.php' : 'maintenance.php'));
    exit;
}

// 7) Vérification des inscriptions ouvertes (Critère 44)
//    Utilisé dans register.php : if (!param_bool('inscription_ouverte', true)) { ... }

function param_bool(string $cle, bool $defaut = false): bool {
    $val = param($cle, $defaut ? '1' : '0');
    return $val === '1' || $val === 'true' || $val === 1;
}
?>