<?php
/**
 * OMNES MARKETPLACE — API Articles
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$method = $_SERVER['REQUEST_METHOD'];

match ($method) {
    'GET'    => handleGet(),
    'POST'   => handleCreate(),
    'DELETE' => handleDelete(),
    default  => jsonError('Méthode non supportée.', 405),
};

function handleGet(): never {
    $db = db();

    if (!empty($_GET['stats'])) {
        jsonSuccess([
            'total_articles'   => (int)$db->query('SELECT COUNT(*) FROM Article WHERE statut="disponible"')->fetchColumn(),
            'total_vendeurs'   => (int)$db->query('SELECT COUNT(*) FROM Vendeur')->fetchColumn(),
            'total_membres'    => (int)$db->query('SELECT COUNT(*) FROM Acheteur')->fetchColumn(),
            'encheres_actives' => (int)$db->query('SELECT COUNT(*) FROM Enchere WHERE statut="en_cours"')->fetchColumn(),
            'total_ventes'     => (int)$db->query('SELECT COUNT(*) FROM Article WHERE statut="vendu"')->fetchColumn(),
        ]);
    }

    if (!empty($_GET['vendeurs'])) {
        $stmt = $db->query('SELECT vendeur_id, prenom, nom FROM Vendeur ORDER BY prenom');
        jsonSuccess(['vendeurs' => $stmt->fetchAll()]);
    }

    if (!empty($_GET['id'])) {
        $id   = (int)$_GET['id'];
        $stmt = $db->prepare(
            'SELECT a.*, v.prenom AS v_prenom, v.nom AS v_nom, v.email AS v_email,
                    e.enchere_id, e.date_fin, e.prix_depart,
                    (SELECT MAX(ao.montant_offre) FROM Asso17 ao WHERE ao.enchere_id = e.enchere_id) AS prix_actuel,
                    (SELECT COUNT(*) FROM Asso17 ao WHERE ao.enchere_id = e.enchere_id) AS nb_offres
             FROM Article a
             JOIN Vendeur v ON a.vendeur_id = v.vendeur_id
             LEFT JOIN Enchere e ON e.article_id = a.article_id
             WHERE a.article_id = ?'
        );
        $stmt->execute([$id]);
        $article = $stmt->fetch();
        if (!$article) jsonError('Article introuvable.', 404);

        if (!empty($_SESSION['user_id']) && $_SESSION['user_role'] === 'acheteur') {
            $sn = $db->prepare('SELECT * FROM Negociation WHERE article_id=? AND acheteur_id=? AND statut="en_attente" LIMIT 1');
            $sn->execute([$id, $_SESSION['user_id']]);
            $article['nego_active'] = $sn->fetch();
        }
        jsonSuccess($article);
    }

    // ── Liste avec filtres ──
    $where  = ['a.statut = "disponible"'];
    $params = [];

    // Filtre type : supporte ?type=enchere ET ?type[]=enchere&type[]=nego
    $types = [];
    if (!empty($_GET['type'])) {
        $raw = $_GET['type'];
        $types = is_array($raw) ? $raw : [$raw];
    }
    if (!empty($types)) {
        $typeClauses = [];
        foreach ($types as $t) {
            match (trim($t)) {
                'immediat' => ($typeClauses[] = 'a.type_immediat = 1'),
                'enchere'  => ($typeClauses[] = 'a.type_enchere = 1'),
                'nego'     => ($typeClauses[] = 'a.type_nego = 1'),
                default    => null,
            };
        }
        if ($typeClauses) $where[] = '(' . implode(' OR ', $typeClauses) . ')';
    }

    // Filtre catégorie : supporte ENUM (regular/haut_de_gamme/rare) ET sous_categorie (electronique/mode/…)
    if (!empty($_GET['categorie'])) {
        $cat = $_GET['categorie'];
        $enumVals = ['regular', 'haut_de_gamme', 'rare'];
        if (in_array($cat, $enumVals)) {
            $where[]  = 'a.categorie = ?';
            $params[] = $cat;
        } else {
            // c'est une sous-catégorie (electronique, mode, livres…)
            $where[]  = 'a.sous_categorie = ?';
            $params[] = $cat;
        }
    }

    if (!empty($_GET['sous_categorie'])) {
        $where[]  = 'a.sous_categorie = ?';
        $params[] = $_GET['sous_categorie'];
    }

    if (!empty($_GET['vendeur_id'])) {
        $where[]  = 'a.vendeur_id = ?';
        $params[] = (int)$_GET['vendeur_id'];
    }

    // Filtre statut (pour admin/vendeur)
    if (!empty($_GET['statut'])) {
        $where    = array_filter($where, fn($w) => strpos($w, 'statut') === false);
        $where[]  = 'a.statut = ?';
        $params[] = $_GET['statut'];
    }

    if (!empty($_GET['prix_min'])) {
        $where[]  = 'COALESCE(a.prix_immediat, a.prix_depart_enchere, a.prix_depart_nego) >= ?';
        $params[] = (float)$_GET['prix_min'];
    }

    if (!empty($_GET['prix_max'])) {
        $where[]  = 'COALESCE(a.prix_immediat, a.prix_depart_enchere, a.prix_depart_nego) <= ?';
        $params[] = (float)$_GET['prix_max'];
    }

    if (!empty($_GET['q'])) {
        $where[]  = '(a.titre LIKE ? OR a.description LIKE ?)';
        $q        = '%' . $_GET['q'] . '%';
        $params[] = $q;
        $params[] = $q;
    }

    if (!empty($_GET['etat'])) {
        $where[]  = 'a.etat = ?';
        $params[] = $_GET['etat'];
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    $order = match ($_GET['sort'] ?? 'recent') {
        'prix_asc'  => 'COALESCE(a.prix_immediat,a.prix_depart_enchere,a.prix_depart_nego) ASC',
        'prix_desc' => 'COALESCE(a.prix_immediat,a.prix_depart_enchere,a.prix_depart_nego) DESC',
        'enchere'   => 'a.type_enchere DESC, a.date_creation DESC',
        default     => 'a.date_creation DESC',
    };

    $countStmt = $db->prepare("SELECT COUNT(*) FROM Article a $whereSQL");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $page    = max(1, (int)($_GET['page'] ?? 1));
    $pag     = paginate($total, $page);
    $params2 = array_merge($params, [$pag['per_page'], $pag['offset']]);

    $stmt = $db->prepare(
        "SELECT a.*, v.prenom AS v_prenom, v.nom AS v_nom,
                e.date_fin,
                (SELECT MAX(ao.montant_offre) FROM Asso17 ao WHERE ao.enchere_id = e.enchere_id) AS prix_actuel
         FROM Article a
         JOIN Vendeur v ON a.vendeur_id = v.vendeur_id
         LEFT JOIN Enchere e ON e.article_id = a.article_id
         $whereSQL
         ORDER BY $order
         LIMIT ? OFFSET ?"
    );
    $stmt->execute($params2);

    jsonSuccess(['articles' => $stmt->fetchAll(), 'pagination' => $pag]);
}

function handleCreate(): never {
    if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'vendeur') {
        jsonError('Authentification vendeur requise.', 401);
    }

    $data      = $_POST;
    $vendeurId = (int)$_SESSION['user_id'];

    $titre       = sanitize($data['titre']          ?? '');
    $description = sanitize($data['description']    ?? '');
    $categorie   = sanitize($data['categorie']      ?? 'regular');
    $etat        = sanitize($data['etat']           ?? 'bon');
    $sous_cat    = sanitize($data['sous_categorie'] ?? 'autre');

    $typeImmediat = !empty($data['type_immediat']) ? 1 : 0;
    $typeEnchere  = !empty($data['type_enchere'])  ? 1 : 0;
    $typeNego     = !empty($data['type_nego'])     ? 1 : 0;

    if (!$titre || !$description) jsonError('Titre et description requis.');
    if (!$typeImmediat && !$typeEnchere && !$typeNego) jsonError('Choisissez au moins un type de vente.');
    if ($typeEnchere && $typeNego)     jsonError('Enchères et négociation ne peuvent pas être combinées.');
    if ($typeEnchere && $typeImmediat) jsonError('Un article en enchère ne peut pas être en achat immédiat simultanément.');

    $prixImmediat = $typeImmediat ? (float)($data['prix_immediat'] ?? 0) : null;
    $prixEnchere  = $typeEnchere  ? (float)($data['prix_enchere']  ?? 0) : null;
    $dateFin      = $typeEnchere  ? ($data['date_fin'] ?? null) : null;
    $prixNego     = $typeNego     ? (float)($data['prix_nego']    ?? 0) : null;

    if ($typeImmediat && !validatePrice($prixImmediat)) jsonError('Prix immédiat invalide.');
    if ($typeEnchere  && !validatePrice($prixEnchere))  jsonError('Prix de départ enchère invalide.');
    if ($typeEnchere  && !$dateFin)                     jsonError('Date de fin enchère requise.');
    if ($typeNego     && !validatePrice($prixNego))     jsonError('Prix de départ négociation invalide.');

    $imageUrl = null;
    if (!empty($_FILES['photo']['name'])) {
        $imageUrl = uploadImage($_FILES['photo'], 'art');
    }

    $db = db();
    $db->prepare(
        'INSERT INTO Article
         (vendeur_id, titre, description, categorie, sous_categorie, etat,
          type_immediat, type_enchere, type_nego,
          prix_immediat, prix_depart_enchere, prix_depart_nego,
          image_url, statut)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,"disponible")'
    )->execute([
        $vendeurId, $titre, $description, $categorie, $sous_cat, $etat,
        $typeImmediat, $typeEnchere, $typeNego,
        $prixImmediat, $prixEnchere, $prixNego,
        $imageUrl,
    ]);

    $articleId = (int)$db->lastInsertId();

    if ($typeEnchere) {
        $db->prepare('INSERT INTO Enchere (article_id, prix_depart, date_fin, statut) VALUES (?,?,?,"en_cours")')
           ->execute([$articleId, $prixEnchere, $dateFin]);
    }

    jsonSuccess(['article_id' => $articleId], 'Article publié avec succès.', 201);
}

function handleDelete(): never {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonError('ID article requis.');
    if (empty($_SESSION['user_id'])) jsonError('Authentification requise.', 401);

    $db   = db();
    $stmt = $db->prepare('SELECT vendeur_id FROM Article WHERE article_id = ?');
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    if (!$article) jsonError('Article introuvable.', 404);

    if ($_SESSION['user_role'] === 'vendeur' && $article['vendeur_id'] != $_SESSION['user_id']) jsonError('Accès refusé.', 403);
    if (!in_array($_SESSION['user_role'], ['vendeur','admin'])) jsonError('Accès refusé.', 403);

    $db->prepare('DELETE FROM Article WHERE article_id = ?')->execute([$id]);
    jsonSuccess(null, 'Article supprimé.');
}
