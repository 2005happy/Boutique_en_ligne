<?php
// config/settings.php

/**
 * Récupère un paramètre depuis la base.
 * Retourne $defaut si le paramètre n'existe pas.
 */
function get_parametre(PDO $pdo, string $cle, $defaut = null) {
    static $cache = [];
    if (isset($cache[$cle])) {
        return $cache[$cle];
    }
    try {
        $stmt = $pdo->prepare("SELECT valeur FROM parametres WHERE cle = ?");
        $stmt->execute([$cle]);
        $val = $stmt->fetchColumn();
        $cache[$cle] = ($val !== false) ? $val : $defaut;
        return $cache[$cle];
    } catch (PDOException $e) {
        return $defaut;
    }
}

/**
 * Récupère tous les paramètres d'un coup.
 */
function get_tous_parametres(PDO $pdo): array {
    try {
        $rows = $pdo->query("SELECT cle, valeur FROM parametres")->fetchAll();
        $result = [];
        foreach ($rows as $r) {
            $result[$r['cle']] = $r['valeur'];
        }
        return $result;
    } catch (PDOException $e) {
        return [];
    }
}
?>