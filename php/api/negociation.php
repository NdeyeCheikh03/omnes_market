<?php
/**
 * OMNES MARKETPLACE — API Négociation
 * Gestion des négociations (max 5 tours)
 *
 * Actions acheteur  : proposer
 * Actions vendeur   : accepter, refuser, contre_offre
 * Actions GET       : liste, vendeur_liste
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
        'liste'         => handleListe(),
        'vendeur_liste' => handleVendeurListe(),
        'admin_liste'   => handleAdminListe(),
        default         => jsonError('Action invalide.'),
    };
} else {
    $body   = getBody();
    $action = $body['action'] ?? '';
    match ($action) {
        'proposer'     => handleProposer($body),
        'accepter'     => handleRepondre($body, 'acceptee'),
        'refuser'      => handleRepondre($body, 'refusee'),
        'contre_offre' => handleContreOffre($body),
        default        => jsonError('Action invalide.'),
    };
}

/* ── PROPOSER (acheteur) ── */
function handleProposer(array $data): never {
    if ($_SESSION['user_role'] !== 'acheteur') jsonError('Rôle acheteur requis.', 403);

    $articleId   = (int)($data['article_id']   ?? 0);
    $prixPropose = (float)($data['prix_propose'] ?? 0);
    $message     = sanitize($data['message']    ?? '');
    $acheteurId  = (int)$_SESSION['user_id'];

    if (!$articleId || !validatePrice($prixPropose)) jsonError('Données invalides.');

    $db = db();

    // Vérifier l'article
    $stmtA = $db->prepare('SELECT * FROM Article WHERE article_id = ? AND type_nego = 1 AND statut = "disponible"');
    $stmtA->execute([$articleId]);
    $article = $stmtA->fetch();
    if (!$article) jsonError('Article non disponible pour négociation.', 404);

    // Chercher une négociation existante
    $stmtN = $db->prepare(
        'SELECT * FROM Negociation WHERE article_id = ? AND acheteur_id = ? AND statut = "en_cours"'
    );
    $stmtN->execute([$articleId, $acheteurId]);
    $nego = $stmtN->fetch();

    if ($nego) {
        // Vérifier le nombre de tours
        if ((int)$nego['nb_tours'] >= MAX_NEGO_TOURS) {
            jsonError('Nombre maximum de tours atteint (' . MAX_NEGO_TOURS . ').');
        }
        // Mettre à jour
        $db->prepare(
            'UPDATE Negociation
             SET prix_propose = ?, message = ?, nb_tours = nb_tours + 1, statut = "en_attente", updated_at = NOW()
             WHERE negoc_id = ?'
        )->execute([$prixPropose, $message, $nego['negoc_id']]);

        $tour = (int)$nego['nb_tours'] + 1;
        $negocId = $nego['negoc_id'];
    } else {
        // Nouvelle négociation
        $db->prepare(
            'INSERT INTO Negociation (article_id, acheteur_id, vendeur_id, prix_propose, message, statut, nb_tours)
             VALUES (?, ?, ?, ?, ?, "en_attente", 1)'
        )->execute([$articleId, $acheteurId, $article['vendeur_id'], $prixPropose, $message]);
        $tour    = 1;
        $negocId = (int)$db->lastInsertId();
    }

    // Notifier le vendeur
    $stmtV = $db->prepare('SELECT prenom, nom FROM Acheteur WHERE acheteur_id = ?');
    $stmtV->execute([$acheteurId]);
    $buyer = $stmtV->fetch();

    _addNegoNotif(
        $article['vendeur_id'],
        'nego',
        "Nouvelle offre de {$buyer['prenom']} {$buyer['nom']} sur « {$article['titre']} » : " . formatPrice($prixPropose),
        'vendeur'
    );

    jsonSuccess([
        'negoc_id' => $negocId,
        'tour'     => $tour,
        'restants' => MAX_NEGO_TOURS - $tour,
    ], 'Offre envoyée au vendeur !');
}

/* ── RÉPONDRE (vendeur) ── */
function handleRepondre(array $data, string $statut): never {
    if ($_SESSION['user_role'] !== 'vendeur') jsonError('Rôle vendeur requis.', 403);

    $negocId   = (int)($data['negoc_id'] ?? 0);
    $vendeurId = (int)$_SESSION['user_id'];

    if (!$negocId) jsonError('ID négociation requis.');

    $db = db();

    // Vérifier que c'est bien une négo de ce vendeur
    $stmt = $db->prepare(
        'SELECT n.*, a.titre FROM Negociation n
         JOIN Article a ON a.article_id = n.article_id
         WHERE n.negoc_id = ? AND n.vendeur_id = ?'
    );
    $stmt->execute([$negocId, $vendeurId]);
    $nego = $stmt->fetch();
    if (!$nego) jsonError('Négociation introuvable.', 404);

    $db->prepare('UPDATE Negociation SET statut = ?, updated_at = NOW() WHERE negoc_id = ?')
       ->execute([$statut, $negocId]);

    $msg = $statut === 'acceptee'
        ? "✅ Le vendeur a accepté votre offre de " . formatPrice($nego['prix_propose']) . " sur « {$nego['titre']} » !"
        : "❌ Votre offre sur « {$nego['titre']} » a été refusée.";

    // Si acceptée → ajouter au panier
    if ($statut === 'acceptee') {
        $stmtP = $db->prepare('SELECT panier_id FROM Panier WHERE acheteur_id = ? LIMIT 1');
        $stmtP->execute([$nego['acheteur_id']]);
        $panier = $stmtP->fetch();
        if ($panier) {
            $db->prepare('INSERT IGNORE INTO Contenir (panier_id, article_id, prix_final) VALUES (?,?,?)')
               ->execute([$panier['panier_id'], $nego['article_id'], $nego['prix_propose']]);
        }
        // Marquer l'article
        $db->prepare('UPDATE Article SET statut = "reserve" WHERE article_id = ?')
           ->execute([$nego['article_id']]);
    }

    _addNegoNotif($nego['acheteur_id'], 'nego', $msg);

    jsonSuccess(null, $statut === 'acceptee' ? 'Négociation acceptée.' : 'Négociation refusée.');
}

/* ── CONTRE-OFFRE (vendeur) ── */
function handleContreOffre(array $data): never {
    if ($_SESSION['user_role'] !== 'vendeur') jsonError('Rôle vendeur requis.', 403);

    $negocId     = (int)($data['negoc_id']     ?? 0);
    $contreOffre = (float)($data['contre_offre'] ?? 0);
    $vendeurId   = (int)$_SESSION['user_id'];

    if (!$negocId || !validatePrice($contreOffre)) jsonError('Données invalides.');

    $db   = db();
    $stmt = $db->prepare(
        'SELECT n.*, a.titre FROM Negociation n
         JOIN Article a ON a.article_id = n.article_id
         WHERE n.negoc_id = ? AND n.vendeur_id = ?'
    );
    $stmt->execute([$negocId, $vendeurId]);
    $nego = $stmt->fetch();
    if (!$nego) jsonError('Négociation introuvable.', 404);

    if ((int)$nego['nb_tours'] >= MAX_NEGO_TOURS) {
        jsonError('Nombre maximum de tours atteint.');
    }

    $db->prepare(
        'UPDATE Negociation
         SET prix_propose = ?, statut = "en_cours", nb_tours = nb_tours + 1, updated_at = NOW()
         WHERE negoc_id = ?'
    )->execute([$contreOffre, $negocId]);

    _addNegoNotif(
        $nego['acheteur_id'],
        'nego',
        "Le vendeur vous propose " . formatPrice($contreOffre) . " pour « {$nego['titre']} ». Tour " . ((int)$nego['nb_tours']+1) . "/" . MAX_NEGO_TOURS
    );

    jsonSuccess(['tour' => (int)$nego['nb_tours'] + 1], 'Contre-offre envoyée !');
}

/* ── LISTE acheteur ── */
function handleListe(): never {
    if ($_SESSION['user_role'] !== 'acheteur') jsonError('Rôle acheteur requis.', 403);

    $stmt = db()->prepare(
        'SELECT n.*, a.titre, a.article_id FROM Negociation n
         JOIN Article a ON a.article_id = n.article_id
         WHERE n.acheteur_id = ?
         ORDER BY n.updated_at DESC'
    );
    $stmt->execute([(int)$_SESSION['user_id']]);
    jsonSuccess(['negociations' => $stmt->fetchAll()]);
}

/* ── LISTE vendeur ── */
function handleVendeurListe(): never {
    if ($_SESSION['user_role'] !== 'vendeur') jsonError('Rôle vendeur requis.', 403);

    $stmt = db()->prepare(
        'SELECT n.*, a.titre, a.article_id,
                ac.prenom, ac.nom
         FROM Negociation n
         JOIN Article a  ON a.article_id  = n.article_id
         JOIN Acheteur ac ON ac.acheteur_id = n.acheteur_id
         WHERE n.vendeur_id = ? AND n.statut = "en_attente"
         ORDER BY n.updated_at DESC'
    );
    $stmt->execute([(int)$_SESSION['user_id']]);
    jsonSuccess(['negociations' => $stmt->fetchAll()]);
}


/* ── LISTE admin (toutes négociations) ── */
function handleAdminListe(): never {
    if ($_SESSION['user_role'] !== 'admin') jsonError('Accès admin requis.', 403);

    $stmt = db()->prepare(
        'SELECT n.*, a.titre, a.article_id,
                ac.prenom AS acheteur_prenom, ac.nom AS acheteur_nom,
                v.prenom AS vendeur_prenom, v.nom AS vendeur_nom
         FROM Negociation n
         JOIN Article a   ON a.article_id   = n.article_id
         JOIN Acheteur ac ON ac.acheteur_id  = n.acheteur_id
         JOIN Vendeur v   ON v.vendeur_id    = n.vendeur_id
         ORDER BY n.updated_at DESC
         LIMIT 100'
    );
    $stmt->execute();
    jsonSuccess(['negociations' => $stmt->fetchAll()]);
}

/* ── Helper notification avec rôle ── */
function _addNegoNotif(int $userId, string $type, string $message, string $role = 'acheteur'): void {
    try {
        if ($role === 'vendeur') {
            db()->prepare(
                'INSERT INTO Notification (vendeur_id, type_notif, message) VALUES (?, ?, ?)'
            )->execute([$userId, $type, $message]);
        } else {
            db()->prepare(
                'INSERT INTO Notification (acheteur_id, type_notif, message) VALUES (?, ?, ?)'
            )->execute([$userId, $type, $message]);
        }
    } catch (PDOException) { /* silencieux */ }
}
