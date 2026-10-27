<?php
// config/panier.php

/**
 * Ajoute un produit au panier.
 */
function panier_ajouter(int $produit_id, int $quantite = 1): void {
    if (!isset($_SESSION['panier'])) {
        $_SESSION['panier'] = [];
    }
    $_SESSION['panier'][$produit_id] = ($_SESSION['panier'][$produit_id] ?? 0) + $quantite;
}

/**
 * Retire un produit du panier.
 */
function panier_retirer(int $produit_id): void {
    unset($_SESSION['panier'][$produit_id]);
}

/**
 * Vide le panier.
 */
function panier_vider(): void {
    $_SESSION['panier'] = [];
}

/**
 * Nombre d'articles dans le panier.
 */
function panier_nombre(): int {
    return array_sum($_SESSION['panier'] ?? []);
}

/**
 * Récupère les articles du panier avec les infos produit.
 * Retourne ['articles' => [...], 'total' => float, 'erreurs' => [...]]
 */
function panier_contenu(PDO $pdo): array {
    $panier = $_SESSION['panier'] ?? [];
    $articles = [];
    $total = 0;
    $erreurs = [];

    if (empty($panier)) {
        return ['articles' => [], 'total' => 0, 'erreurs' => []];
    }

    $ids = array_keys($panier);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT id, nom, prix, stock, archive
        FROM produits
        WHERE id IN ($placeholders)
    ");
    $stmt->execute($ids);
    $produits = $stmt->fetchAll();

    foreach ($produits as $p) {
        $qte = $panier[$p['id']];
        $sous_total = $p['prix'] * $qte;
        $total += $sous_total;

        if ($p['archive']) {
            $erreurs[] = "« {$p['nom']} » n'est plus disponible.";
            continue;
        }
        if ($qte > $p['stock']) {
            $erreurs[] = "« {$p['nom']} » : stock insuffisant (demandé : $qte, disponible : {$p['stock']}).";
        }

        $articles[] = [
            'id' => $p['id'],
            'nom' => $p['nom'],
            'prix' => $p['prix'],
            'quantite' => $qte,
            'sous_total' => $sous_total,
            'stock' => $p['stock'],
            'archive' => $p['archive'],
        ];
    }

    return ['articles' => $articles, 'total' => $total, 'erreurs' => $erreurs];
}
?>