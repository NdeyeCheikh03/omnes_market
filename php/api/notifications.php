<?php
/**
 * OMNES MARKETPLACE — API Notifications
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
    $action = $_GET['action'] ?? 'liste';
    match ($action) {
        'liste' => handleListe(),
        default => jsonError('Action invalide.'),
    };
} else {
    $body   = getBody();
    $action = $body['action'] ?? '';
    match ($action) {
        'marquer_lu'       => handleMarquerLu($body),
        'marquer_tous_lu'  => handleMarquerTousLu(),
        default            => jsonError('Action invalide.'),
    };
}

function handleListe(): never {
    $userId = (int)$_SESSION['user_id'];
    $role   = $_SESSION['user_role'];

    $col = $role === 'vendeur' ? 'vendeur_id' : 'acheteur_id';

    $stmt = db()->prepare(
        "SELECT * FROM Notification WHERE {$col} = ? ORDER BY date_creation DESC LIMIT 50"
    );
    $stmt->execute([$userId]);
    $notifs = $stmt->fetchAll();

    $unread = count(array_filter($notifs, fn($n) => !$n['lu']));
    jsonSuccess(['notifications' => $notifs, 'unread' => $unread]);
}

function handleMarquerLu(array $data): never {
    $notifId = (int)($data['notif_id'] ?? 0);
    if (!$notifId) jsonError('ID notif requis.');
    db()->prepare('UPDATE Notification SET lu = 1 WHERE notif_id = ?')->execute([$notifId]);
    jsonSuccess(null, 'Notification marquée comme lue.');
}

function handleMarquerTousLu(): never {
    $userId = (int)$_SESSION['user_id'];
    $role   = $_SESSION['user_role'];
    $col    = $role === 'vendeur' ? 'vendeur_id' : 'acheteur_id';
    db()->prepare("UPDATE Notification SET lu = 1 WHERE {$col} = ?")->execute([$userId]);
    jsonSuccess(null, 'Toutes les notifications marquées comme lues.');
}
