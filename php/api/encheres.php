<?php
/**
 * OMNES MARKETPLACE — API Enchères
 * POST { action:'encherir', enchere_id, montant_max }  → acheteur
 * POST { action:'cloture', enchere_id }                → admin
 * GET  ?action=mes_encheres                            → acheteur
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if (empty($_SESSION['user_id'])) jsonError('Connexion requise.', 401);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    match ($action) {
        'mes_encheres' => handleMesEncheres(),
        default        => jsonError('Action invalide.'),
    };
} else {
    $body   = getBody();
    $action = $body['action'] ?? '';
    match ($action) {
        'encherir' => handleEncherir($body),
        'cloture'  => handleCloture($body),
        default    => jsonError('Action invalide.'),
    };
}

/* ── MES ENCHÈRES (acheteur) ── */
function handleMesEncheres(): never {
    if ($_SESSION['user_role'] !== 'acheteur') jsonError('Rôle acheteur requis.', 403);
    $stmt = db()->prepare(
        'SELECT ao.montant_offre, ao.est_gagnant, a.article_id, a.titre, a.image_url, e.date_fin, e.statut AS enchere_statut
         FROM Asso17 ao
         JOIN Enchere e ON e.enchere_id = ao.enchere_id
         JOIN Article a ON a.article_id = e.article_id
         WHERE ao.acheteur_id = ?
         ORDER BY ao.offre_id DESC'
    );
    $stmt->execute([(int)$_SESSION['user_id']]);
    jsonSuccess(['encheres' => $stmt->fetchAll()]);
}

/* ── PLACER UNE OFFRE (acheteur) ── */
function handleEncherir(array $data): never {
    if ($_SESSION['user_role'] !== 'acheteur') jsonError('Connexion acheteur requise.', 403);

    $enchere_id  = (int)($data['enchere_id']  ?? 0);
    $montant_max = (float)($data['montant_max'] ?? 0);
    $acheteur_id = (int)$_SESSION['user_id'];

    if (!$enchere_id || $montant_max <= 0) jsonError('Données invalides.');

    $db = db();

    $stmt = $db->prepare(
        'SELECT e.*, a.titre FROM Enchere e
         JOIN Article a ON a.article_id = e.article_id
         WHERE e.enchere_id = ? AND e.statut = "en_cours"'
    );
    $stmt->execute([$enchere_id]);
    $enchere = $stmt->fetch();

    if (!$enchere) jsonError('Enchère introuvable ou terminée.', 404);
    if (strtotime($enchere['date_fin']) < time()) jsonError('L\'enchère est terminée.');

    $stmt2 = $db->prepare('SELECT MAX(montant_offre) AS max_offre, acheteur_id FROM Asso17 WHERE enchere_id = ? ORDER BY montant_offre DESC LIMIT 1');
    $stmt2->execute([$enchere_id]);
    $row         = $stmt2->fetch();
    $prix_actuel = (float)($row['max_offre'] ?? $enchere['prix_depart']);
    $leader_id   = $row['acheteur_id'] ?? null;

    if ($montant_max <= $prix_actuel) jsonError('Votre offre doit dépasser ' . formatPrice($prix_actuel) . '.');

    if ($leader_id && $leader_id != $acheteur_id) {
        $stmtL = $db->prepare('SELECT MAX(montant_offre) AS max_leader FROM Asso17 WHERE enchere_id = ? AND acheteur_id = ?');
        $stmtL->execute([$enchere_id, $leader_id]);
        $max_leader = (float)$stmtL->fetchColumn();

        if ($montant_max > $max_leader) {
            $prix_final = min($montant_max, $max_leader + 1);
        } else {
            $db->prepare('INSERT INTO Asso17 (enchere_id, acheteur_id, montant_offre) VALUES (?,?,?)')->execute([$enchere_id, $acheteur_id, $montant_max]);
            jsonSuccess(['prix_actuel' => min($max_leader, $montant_max + 1), 'gagnant' => false], 'Offre enregistrée mais dépassée par l\'enchérisseur précédent.');
        }
    } else {
        $prix_final = $montant_max;
    }

    $db->prepare('INSERT INTO Asso17 (enchere_id, acheteur_id, montant_offre) VALUES (?,?,?)')->execute([$enchere_id, $acheteur_id, $montant_max]);

    if ($leader_id && $leader_id != $acheteur_id) {
        addNotification($leader_id, 'enchere', "Vous avez été dépassé sur l'enchère « {$enchere['titre']} ». Enchère actuelle : " . formatPrice($prix_final));
    }

    jsonSuccess(['prix_actuel' => $prix_final, 'gagnant' => true], 'Offre enregistrée ! Vous êtes en tête.');
}

/* ── CLÔTURER (admin) ── */
function handleCloture(array $data): never {
    if ($_SESSION['user_role'] !== 'admin') jsonError('Accès réservé aux administrateurs.', 403);

    $enchere_id = (int)($data['enchere_id'] ?? 0);
    if (!$enchere_id) jsonError('ID enchère requis.');

    $db = db();

    $stmt = $db->prepare(
        'SELECT ao.acheteur_id, ao.montant_offre, a.article_id, a.titre
         FROM Asso17 ao
         JOIN Enchere e ON e.enchere_id = ao.enchere_id
         JOIN Article a ON a.article_id = e.article_id
         WHERE ao.enchere_id = ?
         ORDER BY ao.montant_offre DESC LIMIT 1'
    );
    $stmt->execute([$enchere_id]);
    $gagnant = $stmt->fetch();

    $db->prepare('UPDATE Enchere SET statut = "terminee" WHERE enchere_id = ?')->execute([$enchere_id]);

    if (!$gagnant) jsonSuccess(null, 'Enchère clôturée. Aucune offre.');

    $db->prepare('UPDATE Asso17 SET est_gagnant = 1 WHERE enchere_id = ? AND acheteur_id = ? ORDER BY montant_offre DESC LIMIT 1')->execute([$enchere_id, $gagnant['acheteur_id']]);
    $db->prepare('UPDATE Article SET statut = "vendu" WHERE article_id = ?')->execute([$gagnant['article_id']]);

    $stmtP = $db->prepare('SELECT panier_id FROM Panier WHERE acheteur_id = ? LIMIT 1');
    $stmtP->execute([$gagnant['acheteur_id']]);
    $panier = $stmtP->fetch();
    if ($panier) {
        $db->prepare('INSERT IGNORE INTO Contenir (panier_id, article_id, prix_final) VALUES (?,?,?)')->execute([$panier['panier_id'], $gagnant['article_id'], $gagnant['montant_offre']]);
    }

    addNotification($gagnant['acheteur_id'], 'enchere', "🏆 Vous avez remporté l'enchère « {$gagnant['titre']} » pour " . formatPrice($gagnant['montant_offre']) . " !");

    jsonSuccess(['gagnant_id' => $gagnant['acheteur_id'], 'prix_final' => $gagnant['montant_offre']], 'Enchère clôturée.');
}
