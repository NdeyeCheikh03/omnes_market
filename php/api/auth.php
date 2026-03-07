<?php
// auth.php - gestion de l'authentification
// actions : login, logout, register, me, delete_user, liste_acheteurs

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$body   = getBody();
$action = $body['action'] ?? ($_GET['action'] ?? '');

if ($action === 'login')            handleLogin($body);
elseif ($action === 'logout')       handleLogout();
elseif ($action === 'register')     handleRegister($body);
elseif ($action === 'me')           handleMe();
elseif ($action === 'delete_user')  handleDeleteUser($body);
elseif ($action === 'liste_acheteurs') handleListeAcheteurs();
else jsonError('Action invalide.', 400);


// connexion
function handleLogin($data) {
    $email    = sanitize($data['email']    ?? '');
    $password = $data['password'] ?? '';
    $role     = sanitize($data['role']     ?? 'acheteur');

    if (!$email || !$password) jsonError('Email et mot de passe requis.');
    if (!validateEmail($email)) jsonError('Adresse email invalide.');

    $tables = [
        'acheteur' => ['table' => 'Acheteur',      'id' => 'acheteur_id', 'redirect' => 'index.html'],
        'vendeur'  => ['table' => 'Vendeur',        'id' => 'vendeur_id',  'redirect' => 'vendor-dashboard.html'],
        'admin'    => ['table' => 'Administrateur', 'id' => 'admin_id',    'redirect' => 'admin-dashboard.html'],
    ];

    if (!isset($tables[$role])) jsonError('Rôle invalide.');

    $cfg   = $tables[$role];
    $table = $cfg['table'];

    $stmt = db()->prepare("SELECT * FROM {$table} WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['mot_de_passe'])) {
        jsonError('Identifiants incorrects.', 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id']    = $user[$cfg['id']];
    $_SESSION['user_role']  = $role;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name']  = ($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '');

    jsonSuccess([
        'id'       => $user[$cfg['id']],
        'role'     => $role,
        'name'     => trim($_SESSION['user_name']),
        'email'    => $email,
        'redirect' => $cfg['redirect'],
    ], 'Connexion réussie.');
}

// déconnexion
function handleLogout() {
    session_destroy();
    jsonSuccess(null, 'Déconnecté.');
}

// inscription
function handleRegister($data) {
    $prenom    = sanitize($data['prenom']    ?? '');
    $nom       = sanitize($data['nom']       ?? '');
    $email     = sanitize($data['email']     ?? '');
    $password  = $data['password']  ?? '';
    $telephone = sanitize($data['telephone'] ?? '');
    $adresse   = sanitize($data['adresse']   ?? '');
    $pseudo    = sanitize($data['pseudo']    ?? '');
    $role      = sanitize($data['role']      ?? 'acheteur');

    if (!$prenom || !$nom)      jsonError('Prénom et nom requis.');
    if (!validateEmail($email)) jsonError('Adresse email invalide.');
    if (strlen($password) < 8)  jsonError('Mot de passe trop court (min 8 caractères).');

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    if ($role === 'vendeur') {
        $stmt = db()->prepare('SELECT vendeur_id FROM Vendeur WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) jsonError('Cette adresse email est déjà utilisée (vendeur).');

        if (!$pseudo) $pseudo = $prenom . ' ' . $nom;

        db()->prepare(
            'INSERT INTO Vendeur (prenom, nom, email, mot_de_passe, telephone, pseudo, description)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$prenom, $nom, $email, $hash, $telephone, $pseudo, '']);

        $newId = (int)db()->lastInsertId();
        jsonSuccess(['id' => $newId, 'role' => 'vendeur'], 'Compte vendeur créé avec succès.', 201);

    } else {
        $stmt = db()->prepare('SELECT acheteur_id FROM Acheteur WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) jsonError('Cette adresse email est déjà utilisée.');

        db()->prepare(
            'INSERT INTO Acheteur (prenom, nom, email, mot_de_passe, telephone, adresse_livraison)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$prenom, $nom, $email, $hash, $telephone, $adresse]);

        $newId = (int)db()->lastInsertId();
        jsonSuccess(['id' => $newId, 'role' => 'acheteur'], 'Compte créé avec succès.', 201);
    }
}

// infos utilisateur connecté
function handleMe() {
    if (empty($_SESSION['user_id'])) {
        jsonError('Non connecté.', 401);
    }
    jsonSuccess([
        'id'    => $_SESSION['user_id'],
        'role'  => $_SESSION['user_role'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
    ]);
}

// supprimer un utilisateur (admin uniquement)
function handleDeleteUser($data) {
    if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        jsonError('Accès administrateur requis.', 403);
    }

    $userId = (int)($data['user_id'] ?? 0);
    $role   = sanitize($data['role'] ?? '');

    if (!$userId) jsonError('ID utilisateur requis.');
    if (!in_array($role, ['vendeur', 'acheteur'])) jsonError('Rôle invalide.');

    $db = db();

    if ($role === 'vendeur') {
        $stmt = $db->prepare('SELECT vendeur_id FROM Vendeur WHERE vendeur_id = ?');
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) jsonError('Vendeur introuvable.', 404);
        $db->prepare('DELETE FROM Vendeur WHERE vendeur_id = ?')->execute([$userId]);
    } else {
        $stmt = $db->prepare('SELECT acheteur_id FROM Acheteur WHERE acheteur_id = ?');
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) jsonError('Acheteur introuvable.', 404);
        $db->prepare('DELETE FROM Acheteur WHERE acheteur_id = ?')->execute([$userId]);
    }

    jsonSuccess(null, 'Utilisateur supprimé.');
}

// liste des acheteurs (admin uniquement)
function handleListeAcheteurs() {
    if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        jsonError('Accès administrateur requis.', 403);
    }

    $stmt = db()->prepare('SELECT acheteur_id, prenom, nom, email, telephone, date_inscription FROM Acheteur ORDER BY prenom');
    $stmt->execute();
    jsonSuccess(['acheteurs' => $stmt->fetchAll()]);
}
