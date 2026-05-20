<?php
// Connexion BDD et fonctions communes du site

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Configuration automatique :
// - sur WAMP/local : base locale
// - sur InfinityFree : base en ligne
$hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = strpos($hostName, 'localhost') !== false || strpos($hostName, '127.0.0.1') !== false;

if ($isLocal) {
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3308');
    define('DB_NAME', 'omnesevent');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    define('DB_HOST', 'sql110.infinityfree.com');
    define('DB_PORT', '3306');
    define('DB_NAME', 'if0_41964317_db_omnesevent');
    define('DB_USER', 'if0_41964317');
    define('DB_PASS', 'vHArvOkpGq3I');
}

/*
    Pour forcer une config a la main :
    - Local WAMP : garde le bloc avec 127.0.0.1 / root.
    - Hebergement : garde le bloc avec sql110.infinityfree.com.
    La detection automatique evite normalement de modifier ce fichier a chaque upload.
*/

function db() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            die('Erreur connexion BDD : ' . e($e->getMessage()));
        }

        // Petites migrations. Si InfinityFree refuse une requete, on ignore
        // pour eviter une erreur 500 et laisser le site s'afficher.
        foreach ([
            "CREATE TABLE IF NOT EXISTS follows (
                id INT AUTO_INCREMENT PRIMARY KEY,
                follower_id INT NOT NULL,
                following_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_follow (follower_id, following_id)
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS user_posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                photo_path VARCHAR(500) NOT NULL,
                caption VARCHAR(500) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",

            "CREATE TABLE IF NOT EXISTS personal_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                event_date DATE NOT NULL,
                event_time TIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",

            "ALTER TABLE users ADD COLUMN is_private TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE follows ADD COLUMN status ENUM('pending','accepted') NOT NULL DEFAULT 'accepted'",
        ] as $sql) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                // Deja existant ou refuse par l'hebergeur : pas bloquant.
            }
        }
    }

    return $pdo;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function current_user() {
    if (empty($_SESSION['user_id'])) return null;

    $stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION = [];
        session_destroy();
        return null;
    }

    return $user;
}

function login_user($user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
}

function logout_user() {
    $_SESSION = [];
    session_destroy();
}

function require_login() {
    $user = current_user();
    if (!$user) redirect('login.php');
    return $user;
}

function require_role($roles) {
    $user = require_login();

    if (!in_array($user['role'], $roles)) {
        http_response_code(403);
        require 'header.php';
        echo '<main class="shell"><section class="panel"><h1>Acces refuse</h1><p class="muted">Tu n\'as pas les droits pour acceder a cette page.</p></section></main>';
        require 'footer.php';
        exit;
    }

    return $user;
}

function promote_waitlist(int $eventId): int {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT price, capacity,
        (SELECT COUNT(*) FROM tickets WHERE event_id = ? AND status = 'reserved') AS reserved_count
        FROM events WHERE id = ?");
    $stmt->execute([$eventId, $eventId]);
    $event = $stmt->fetch();

    if (!$event) return 0;

    $freeSpots = max(0, (int)$event['capacity'] - (int)$event['reserved_count']);
    if ($freeSpots <= 0) return 0;

    $wStmt = $pdo->prepare("SELECT * FROM waitlist WHERE event_id = ? AND status = 'waiting'
        ORDER BY created_at ASC LIMIT ?");
    $wStmt->execute([$eventId, $freeSpots]);
    $toPromote = $wStmt->fetchAll();

    foreach ($toPromote as $w) {
        if ((float)$event['price'] <= 0) {
            $code = strtoupper(bin2hex(random_bytes(4))) . '-' . $eventId . '-' . $w['user_id'];
            $existing = $pdo->prepare("SELECT id FROM tickets WHERE event_id = ? AND user_id = ?");
            $existing->execute([$eventId, $w['user_id']]);

            if ($existing->fetch()) {
                $pdo->prepare("UPDATE tickets SET status = 'reserved', code = ?, payment_status = 'free' WHERE event_id = ? AND user_id = ?")
                    ->execute([$code, $eventId, $w['user_id']]);
            } else {
                $pdo->prepare("INSERT INTO tickets (event_id, user_id, code, payment_status) VALUES (?, ?, ?, 'free')")
                    ->execute([$eventId, $w['user_id'], $code]);
            }

            $pdo->prepare("DELETE FROM waitlist WHERE id = ?")->execute([$w['id']]);
        } else {
            $pdo->prepare("UPDATE waitlist SET status = 'promoted' WHERE id = ?")->execute([$w['id']]);
        }
    }

    return count($toPromote);
}

function flash($type, $message) {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes() {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}
