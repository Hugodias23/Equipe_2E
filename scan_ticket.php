<?php
require_once 'db.php';
$user = require_role(['organizer', 'admin']);

header('Content-Type: application/json');

$code    = trim($_POST['code']     ?? '');
$eventId = (int) ($_POST['event_id'] ?? 0);

if (!$code || !$eventId) {
    echo json_encode(['ok' => false, 'msg' => 'Donnees manquantes.']);
    exit;
}

$pdo = db();

// Organizer must own the event
if ($user['role'] !== 'admin') {
    $evStmt = $pdo->prepare("SELECT organizer_id FROM events WHERE id = ?");
    $evStmt->execute([$eventId]);
    $ev = $evStmt->fetch();
    if (!$ev || (int) $ev['organizer_id'] !== (int) $user['id']) {
        echo json_encode(['ok' => false, 'msg' => 'Acces refuse.']);
        exit;
    }
}

$stmt = $pdo->prepare("SELECT t.*, u.name FROM tickets t JOIN users u ON u.id = t.user_id WHERE t.code = ? AND t.event_id = ?");
$stmt->execute([$code, $eventId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    echo json_encode(['ok' => false, 'msg' => 'Billet introuvable pour cet evenement.']);
    exit;
}
if ($ticket['status'] === 'present') {
    echo json_encode(['ok' => false, 'msg' => 'Deja valide — ' . htmlspecialchars($ticket['name'], ENT_QUOTES, 'UTF-8') . '.']);
    exit;
}
if ($ticket['status'] === 'cancelled') {
    echo json_encode(['ok' => false, 'msg' => 'Billet annule.']);
    exit;
}

$pdo->prepare("UPDATE tickets SET status = 'present' WHERE id = ?")->execute([$ticket['id']]);
echo json_encode(['ok' => true, 'msg' => 'Bienvenue ' . htmlspecialchars($ticket['name'], ENT_QUOTES, 'UTF-8') . ' !']);
