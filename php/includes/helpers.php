<?php
/**
 * OMNES MARKETPLACE — Fonctions utilitaires
 */

require_once __DIR__ . '/config.php';

/* ==================== RÉPONSES JSON ==================== */

function jsonSuccess(mixed $data = null, string $message = 'OK', int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $code = 400, mixed $data = null): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ==================== VALIDATION ==================== */

function sanitize(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePrice(mixed $value): bool {
    return is_numeric($value) && (float)$value > 0;
}

function getBody(): array {
    // Lire le body brut (JSON envoyé par fetch avec Content-Type: application/json)
    $raw  = file_get_contents('php://input');
    $data = null;

    if (!empty($raw)) {
        $data = json_decode($raw, true);
    }

    // Si JSON valide → l'utiliser
    if (is_array($data) && json_last_error() === JSON_ERROR_NONE) {
        return $data;
    }

    // Sinon fallback sur $_POST (formulaire classique)
    if (!empty($_POST)) {
        return $_POST;
    }

    // Dernier recours : GET params
    return $_GET;
}

/* ==================== FORMATAGE ==================== */

function formatPrice(float $price): string {
    return number_format($price, 2, ',', ' ') . ' €';
}

function timeLeft(string $dateEnd): string {
    $diff = strtotime($dateEnd) - time();
    if ($diff <= 0) return 'Terminée';
    $days  = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $mins  = floor(($diff % 3600) / 60);
    if ($days > 0)  return "{$days}j {$hours}h";
    if ($hours > 0) return "{$hours}h {$mins}m";
    return "{$mins} min";
}

function generateRef(): string {
    return 'OMN-' . strtoupper(substr(md5(uniqid()), 0, 8));
}

function timeAgo(string $date): string {
    $diff = time() - strtotime($date);
    if ($diff < 60)     return 'À l\'instant';
    if ($diff < 3600)   return floor($diff/60) . ' min';
    if ($diff < 86400)  return floor($diff/3600) . 'h';
    if ($diff < 604800) return floor($diff/86400) . 'j';
    return date('d/m/Y', strtotime($date));
}

/* ==================== UPLOAD IMAGE ==================== */

function uploadImage(array $file, string $prefix = 'img'): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > MAX_FILE_SIZE)    return false;
    if (!in_array($file['type'], ALLOWED_TYPES)) return false;

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . '_' . uniqid() . '.' . strtolower($ext);
    $target   = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) return false;

    return UPLOAD_URL . $filename;
}

/* ==================== PAGINATION ==================== */

function paginate(int $total, int $page, int $perPage = ITEMS_PER_PAGE): array {
    $totalPages  = (int)ceil($total / $perPage);
    $currentPage = max(1, min($page, $totalPages));
    $offset      = ($currentPage - 1) * $perPage;

    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'has_prev'    => $currentPage > 1,
        'has_next'    => $currentPage < $totalPages,
    ];
}

/* ==================== NOTIFICATIONS ==================== */

function addNotification(int $acheteurId, string $type, string $message): void {
    try {
        db()->prepare(
            'INSERT INTO Notification (acheteur_id, type_notif, message) VALUES (?, ?, ?)'
        )->execute([$acheteurId, $type, $message]);
    } catch (PDOException) {
        // Silencieux
    }
}
