<?php
/**
 * auth.php — Gestion authentification et sessions
 */
require_once __DIR__ . '/db.php';

function isLoggedIn(): bool {
    return isset($_SESSION['user_id'], $_SESSION['user_role']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'     => $_SESSION['user_id'],
        'role'   => $_SESSION['user_role'],
        'prenom' => $_SESSION['user_prenom'] ?? '',
        'nom'    => $_SESSION['user_nom']    ?? '',
        'email'  => $_SESSION['user_email'] ?? '',
    ];
}

function loginAcheteur(string $email, string $password): bool {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM Acheteur WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['mot_de_passe'])) {
        $_SESSION['user_id']     = $user['id_acheteur'];
        $_SESSION['user_role']   = 'acheteur';
        $_SESSION['user_prenom'] = $user['prenom'];
        $_SESSION['user_nom']    = $user['nom'];
        $_SESSION['user_email']  = $user['email'];
        return true;
    }
    return false;
}

function loginVendeur(string $email, string $password): bool {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM Vendeur WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['mot_de_passe'])) {
        $_SESSION['user_id']     = $user['id_vendeur'];
        $_SESSION['user_role']   = 'vendeur';
        $_SESSION['user_prenom'] = $user['prenom'];
        $_SESSION['user_nom']    = $user['nom'];
        $_SESSION['user_email']  = $user['email'];
        return true;
    }
    return false;
}

function loginAdmin(string $email, string $password): bool {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM Administrateur WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['mot_de_passe'])) {
        $_SESSION['user_id']     = $user['id_admin'];
        $_SESSION['user_role']   = 'admin';
        $_SESSION['user_prenom'] = $user['prenom'];
        $_SESSION['user_nom']    = $user['nom'];
        $_SESSION['user_email']  = $user['email'];
        return true;
    }
    return false;
}

function registerAcheteur(array $data): bool {
    $pdo  = getDB();
    $hash = password_hash($data['password'], PASSWORD_BCRYPT);
    try {
        $stmt = $pdo->prepare("INSERT INTO Acheteur (prenom, nom, email, mot_de_passe, telephone, adresse)
                                VALUES (?,?,?,?,?,?)");
        return $stmt->execute([
            $data['prenom'], $data['nom'], $data['email'],
            $hash, $data['telephone'] ?? null, $data['adresse'] ?? null
        ]);
    } catch (PDOException $e) { return false; }
}

function requireAuth(string $role = ''): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . SITE_URL . '/pages/auth/login.php');
        exit;
    }
    if ($role && $_SESSION['user_role'] !== $role) {
        header('Location: ' . SITE_URL . '/pages/home/index.php');
        exit;
    }
}

function countNotifications(int $userId, string $role): int {
    if ($role !== 'acheteur') return 0;
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Notification WHERE id_acheteur = ? AND lu = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function countPanier(int $userId, string $role): int {
    if ($role !== 'acheteur') return 0;
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM Contenir c
        JOIN Panier p ON c.id_panier = p.id_panier
        WHERE p.id_acheteur = ?
    ");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}
