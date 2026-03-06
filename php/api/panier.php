<?php
/**
 * OMNES MARKETPLACE — API Panier
 * GET  ?action=get        → contenu du panier
 * POST { action:'add', article_id, prix? }   → ajouter
 * POST { action:'remove', article_id }        → retirer
 * POST { action:'clear' }                     → vider
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'acheteur') {
    jsonError('Connexion acheteur requise.', 401);
}

$acheteurId = (int)$_SESSION['user_id'];
$method     = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handleGet($acheteurId);
} else {
    $body   = getBody();
    $action = $body['action'] ?? '';
    match ($action) {
        'add'    => handleAdd($acheteurId, $body),
        'remove' => handleRemove($acheteurId, $body),
        'clear'  => handleClear($acheteurId),
        default  => jsonError('Action invalide.'),
    };
}

/* ── GET : contenu ── */
function handleGet(int $acheteurId): never {
    $db = db();

    // Obtenir ou créer le panier
    $panierId = getPanierId($db, $acheteurId);

    $stmt = $db->prepare(
        'SELECT c.article_id, c.prix_final,
                a.titre, a.image_url, a.type_immediat, a.type_enchere, a.type_nego,
                CASE WHEN a.type_enchere=1 THEN "enchere"
                     WHEN a.type_nego=1    THEN "nego"
                     ELSE "immediat" END AS type,
                v.prenom AS v_prenom, v.nom AS v_nom
         FROM Contenir c
         JOIN Article a ON a.article_id = c.article_id
         JOIN Vendeur v ON v.vendeur_id = a.vendeur_id
         WHERE c.panier_id = ?
         ORDER BY c.article_id DESC'
    );
    $stmt->execute([$panierId]);
    $items = $stmt->fetchAll();

    $total = array_sum(array_column($items, 'prix_final'));

    jsonSuccess([
        'items' => $items,
        'total' => $total,
        'count' => count($items),
    ]);
}

/* ── ADD ── */
function handleAdd(int $acheteurId, array $data): never {
    $articleId = (int)($data['article_id'] ?? 0);
    if (!$articleId) jsonError('ID article requis.');

    $db = db();

    // Vérifier que l'article est disponible
    $stmt = $db->prepare(
        'SELECT article_id, prix_immediat, prix_depart_nego, type_immediat, type_nego
         FROM Article WHERE article_id = ? AND statut = "disponible"'
    );
    $stmt->execute([$articleId]);
    $article = $stmt->fetch();
    if (!$article) jsonError('Article non disponible.', 404);

    // Déterminer le prix
    $prix = (float)($data['prix'] ?? 0);
    if ($prix <= 0) {
        $prix = (float)($article['prix_immediat'] ?? $article['prix_depart_nego'] ?? 0);
    }

    $panierId = getPanierId($db, $acheteurId);

    // Vérifier si déjà dans le panier
    $check = $db->prepare('SELECT 1 FROM Contenir WHERE panier_id = ? AND article_id = ?');
    $check->execute([$panierId, $articleId]);
    if ($check->fetch()) jsonError('Article déjà dans le panier.');

    $db->prepare(
        'INSERT INTO Contenir (panier_id, article_id, prix_final) VALUES (?, ?, ?)'
    )->execute([$panierId, $articleId, $prix]);

    // Compter le panier
    $count = (int)$db->prepare('SELECT COUNT(*) FROM Contenir WHERE panier_id = ?')
                     ->execute([$panierId]) ? 0 : 0;
    $countStmt = $db->prepare('SELECT COUNT(*) FROM Contenir WHERE panier_id = ?');
    $countStmt->execute([$panierId]);
    $count = (int)$countStmt->fetchColumn();

    jsonSuccess(['count' => $count], 'Article ajouté au panier.', 201);
}

/* ── REMOVE ── */
function handleRemove(int $acheteurId, array $data): never {
    $articleId = (int)($data['article_id'] ?? 0);
    if (!$articleId) jsonError('ID article requis.');

    $db       = db();
    $panierId = getPanierId($db, $acheteurId);

    $db->prepare('DELETE FROM Contenir WHERE panier_id = ? AND article_id = ?')
       ->execute([$panierId, $articleId]);

    $countStmt = $db->prepare('SELECT COUNT(*) FROM Contenir WHERE panier_id = ?');
    $countStmt->execute([$panierId]);
    $count = (int)$countStmt->fetchColumn();

    // Recalculer total
    $totStmt = $db->prepare('SELECT COALESCE(SUM(prix_final),0) FROM Contenir WHERE panier_id = ?');
    $totStmt->execute([$panierId]);
    $total = (float)$totStmt->fetchColumn();

    jsonSuccess(['count' => $count, 'total' => $total], 'Article retiré du panier.');
}

/* ── CLEAR ── */
function handleClear(int $acheteurId): never {
    $db       = db();
    $panierId = getPanierId($db, $acheteurId);
    $db->prepare('DELETE FROM Contenir WHERE panier_id = ?')->execute([$panierId]);
    jsonSuccess(['count' => 0, 'total' => 0], 'Panier vidé.');
}

/* ── Helper : obtenir ou créer l'ID panier ── */
function getPanierId(PDO $db, int $acheteurId): int {
    $stmt = $db->prepare('SELECT panier_id FROM Panier WHERE acheteur_id = ? LIMIT 1');
    $stmt->execute([$acheteurId]);
    $row = $stmt->fetch();

    if ($row) return (int)$row['panier_id'];

    // Créer un panier
    $db->prepare('INSERT INTO Panier (acheteur_id) VALUES (?)')->execute([$acheteurId]);
    return (int)$db->lastInsertId();
}
