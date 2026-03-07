<?php
/**
 * OMNES MARKETPLACE — API Commandes
 * POST { action:'passer', adresse, prenom, nom, livraison }
 * GET  ?action=liste           → commandes de l'acheteur
 * GET  ?action=admin_liste     → toutes commandes (admin)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if (empty($_SESSION['user_id'])) {
    jsonError('Connexion requise.', 401);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'liste';
    match ($action) {
        'liste'       => handleListeAcheteur(),
        'admin_liste' => handleListeAdmin(),
        default       => jsonError('Action invalide.'),
    };
} else {
    $body   = getBody();
    $action = $body['action'] ?? '';
    match ($action) {
        'passer' => handlePasser($body),
        default  => jsonError('Action invalide.'),
    };
}

/* ── PASSER UNE COMMANDE ── */
function handlePasser(array $data): never {
    if ($_SESSION['user_role'] !== 'acheteur') jsonError('Rôle acheteur requis.', 403);

    $acheteurId = (int)$_SESSION['user_id'];
    $adresse    = sanitize($data['adresse']   ?? '');
    $prenom     = sanitize($data['prenom']    ?? '');
    $nom        = sanitize($data['nom']       ?? '');
    $livraison  = sanitize($data['livraison'] ?? 'standard');

    if (!$adresse || !$prenom || !$nom) {
        jsonError('Adresse et nom de livraison requis.');
    }

    $db = db();

    // Récupérer le panier
    $stmtP = $db->prepare('SELECT panier_id FROM Panier WHERE acheteur_id = ? LIMIT 1');
    $stmtP->execute([$acheteurId]);
    $panier = $stmtP->fetch();
    if (!$panier) jsonError('Panier introuvable.');

    $panierId = (int)$panier['panier_id'];

    // Récupérer les articles du panier
    $stmtC = $db->prepare(
        'SELECT c.article_id, c.prix_final FROM Contenir c WHERE c.panier_id = ?'
    );
    $stmtC->execute([$panierId]);
    $items = $stmtC->fetchAll();

    if (!$items) jsonError('Votre panier est vide.');

    $total = array_sum(array_column($items, 'prix_final'));

    // Frais de livraison
    $fraisLivraison = match ($livraison) {
        'express' => 5.90,
        default   => 0.0,
    };

    // Remise si ≥ 100€
    $remise = $total >= REMISE_MIN ? $total * (REMISE_PCT / 100) : 0;
    $totalFinal = $total - $remise + $fraisLivraison;

    // Générer une référence unique
    $reference = generateRef();

    // Créer la commande
    $db->prepare(
        'INSERT INTO Commande (acheteur_id, reference, montant, adresse_livraison,
                               mode_livraison, statut)
         VALUES (?, ?, ?, ?, ?, "confirmee")'
    )->execute([$acheteurId, $reference, $totalFinal, "$prenom $nom, $adresse", $livraison]);

    $commandeId = (int)$db->lastInsertId();

    // Associer les articles à la commande et les marquer vendus
    foreach ($items as $item) {
        $db->prepare(
            'INSERT INTO Commander (commande_id, article_id, prix_achat) VALUES (?, ?, ?)'
        )->execute([$commandeId, $item['article_id'], $item['prix_final']]);

        $db->prepare('UPDATE Article SET statut = "vendu" WHERE article_id = ?')
           ->execute([$item['article_id']]);
        
        // Marquer les négociations liées comme terminées
        $db->prepare('UPDATE Negociation SET statut = "terminee" WHERE article_id = ?')
           ->execute([$item['article_id']]);
    }

    // Vider le panier
    $db->prepare('DELETE FROM Contenir WHERE panier_id = ?')->execute([$panierId]);

    // Notification de confirmation
    try {
        $db->prepare(
            'INSERT INTO Notification (acheteur_id, type_notif, message) VALUES (?, "commande", ?)'
        )->execute([$acheteurId, "✅ Commande $reference confirmée ! Total : " . formatPrice($totalFinal) . ". Livraison estimée sous " . ($livraison === 'express' ? '24h' : '3-5 jours') . "."]);
    } catch (PDOException) {}

    jsonSuccess([
        'commande_id' => $commandeId,
        'reference'   => $reference,
        'total'       => $totalFinal,
    ], 'Commande passée avec succès !', 201);
}

/* ── LISTE acheteur ── */
function handleListeAcheteur(): never {
    if ($_SESSION['user_role'] !== 'acheteur') jsonError('Rôle acheteur requis.', 403);

    $stmt = db()->prepare(
        'SELECT cm.commande_id, cm.reference, cm.montant, cm.statut, cm.date_commande,
                a.titre
         FROM Commande cm
         LEFT JOIN Commander c ON c.commande_id = cm.commande_id
         LEFT JOIN Article a   ON a.article_id  = c.article_id
         WHERE cm.acheteur_id = ?
         ORDER BY cm.date_commande DESC'
    );
    $stmt->execute([(int)$_SESSION['user_id']]);
    jsonSuccess(['commandes' => $stmt->fetchAll()]);
}

/* ── LISTE admin ── */
function handleListeAdmin(): never {
    if ($_SESSION['user_role'] !== 'admin') jsonError('Accès admin requis.', 403);

    $stmt = db()->prepare(
        'SELECT cm.commande_id, cm.reference, cm.montant, cm.statut, cm.date_commande,
                ac.prenom AS acheteur_prenom, ac.nom AS acheteur_nom
         FROM Commande cm
         JOIN Acheteur ac ON ac.acheteur_id = cm.acheteur_id
         ORDER BY cm.date_commande DESC
         LIMIT 50'
    );
    $stmt->execute();
    jsonSuccess(['commandes' => $stmt->fetchAll()]);
}
