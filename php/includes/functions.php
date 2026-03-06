<?php
/**
 * functions.php — Fonctions métier
 */
require_once __DIR__ . '/db.php';

function getArticles(array $filters = [], int $limit = 12): array {
    $pdo    = getDB();
    $where  = ["a.statut = 'disponible'"];
    $params = [];

    if (!empty($filters['q'])) {
        $where[]  = "(a.nom_article LIKE ? OR a.description LIKE ?)";
        $params[] = '%' . $filters['q'] . '%';
        $params[] = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['categorie'])) {
        $where[]  = "a.categorie = ?";
        $params[] = $filters['categorie'];
    }
    if (!empty($filters['type_vente'])) {
        $where[]  = "a.type_vente = ?";
        $params[] = $filters['type_vente'];
    }
    if (!empty($filters['prix_max'])) {
        $where[]  = "a.prix_depart <= ?";
        $params[] = $filters['prix_max'];
    }

    $orderMap = [
        'recent'     => 'a.date_publication DESC',
        'prix_asc'   => 'a.prix_depart ASC',
        'prix_desc'  => 'a.prix_depart DESC',
        'fin_proche' => 'a.date_fin_enchere ASC',
    ];
    $order = $orderMap[$filters['ordre'] ?? 'recent'] ?? 'a.date_publication DESC';

    $sql  = "SELECT a.*, v.prenom AS prenom_vendeur, v.nom AS nom_vendeur
             FROM Article a JOIN Vendeur v ON a.id_vendeur = v.id_vendeur
             WHERE " . implode(' AND ', $where) . "
             ORDER BY $order
             LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getArticlesPagines(array $filters, int $page, int $perPage): array {
    $pdo    = getDB();
    $where  = ["a.statut = 'disponible'"];
    $params = [];

    if (!empty($filters['q'])) {
        $where[]  = "(a.nom_article LIKE ? OR a.description LIKE ?)";
        $params[] = '%' . $filters['q'] . '%';
        $params[] = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['categorie'])) { $where[] = "a.categorie = ?";    $params[] = $filters['categorie']; }
    if (!empty($filters['type_vente'])){ $where[] = "a.type_vente = ?";   $params[] = $filters['type_vente']; }
    if (!empty($filters['prix_max']))  { $where[] = "a.prix_depart <= ?"; $params[] = $filters['prix_max']; }

    $orderMap = ['recent'=>'a.date_publication DESC','prix_asc'=>'a.prix_depart ASC','prix_desc'=>'a.prix_depart DESC','fin_proche'=>'a.date_fin_enchere ASC'];
    $order = $orderMap[$filters['ordre'] ?? 'recent'] ?? 'a.date_publication DESC';
    $whereSQL = implode(' AND ', $where);

    // Total
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM Article a WHERE $whereSQL");
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    // Articles paginés
    $offset   = ($page - 1) * $perPage;
    $stmt     = $pdo->prepare("SELECT a.*, v.prenom AS prenom_vendeur, v.nom AS nom_vendeur
                                FROM Article a JOIN Vendeur v ON a.id_vendeur = v.id_vendeur
                                WHERE $whereSQL ORDER BY $order LIMIT ? OFFSET ?");
    $stmt->execute([...$params, $perPage, $offset]);

    return ['articles' => $stmt->fetchAll(), 'total' => $total];
}

function getArticle(int $id): ?array {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT a.*, v.prenom AS prenom_vendeur, v.nom AS nom_vendeur, v.email AS email_vendeur
                            FROM Article a JOIN Vendeur v ON a.id_vendeur = v.id_vendeur
                            WHERE a.id_article = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getEncheresActives(int $limit = 6): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT a.*, e.id_enchere, e.date_fin_enchere,
               COALESCE(MAX(oe.montant_offre), a.prix_depart) AS prix_actuel,
               COUNT(oe.id_enchere) AS nb_encheres
        FROM Article a
        JOIN Enchere e ON a.id_article = e.id_article
        LEFT JOIN Asso17 oe ON e.id_enchere = oe.id_enchere
        WHERE a.statut = 'disponible' AND e.date_fin_enchere > NOW()
        GROUP BY a.id_article, e.id_enchere
        ORDER BY e.date_fin_enchere ASC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function countArticles(): int {
    $stmt = getDB()->query("SELECT COUNT(*) FROM Article WHERE statut = 'disponible'");
    return (int)$stmt->fetchColumn();
}
function countVendeurs(): int {
    $stmt = getDB()->query("SELECT COUNT(*) FROM Vendeur");
    return (int)$stmt->fetchColumn();
}
function countEncheresActives(): int {
    $stmt = getDB()->query("SELECT COUNT(*) FROM Enchere WHERE date_fin_enchere > NOW()");
    return (int)$stmt->fetchColumn();
}
function countMembres(): int {
    $stmt = getDB()->query("SELECT COUNT(*) FROM Acheteur");
    return (int)$stmt->fetchColumn();
}

function getCountsByCategorie(): array {
    $pdo  = getDB();
    $stmt = $pdo->query("SELECT categorie, COUNT(*) AS n FROM Article WHERE statut='disponible' GROUP BY categorie");
    $rows = $stmt->fetchAll();
    $out  = ['' => countArticles()];
    foreach ($rows as $r) $out[$r['categorie']] = $r['n'];
    return $out;
}
