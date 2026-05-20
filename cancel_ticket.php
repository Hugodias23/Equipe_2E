<?php
require_once 'db.php';
$user = require_login();
$ticketId = (int)($_GET['id'] ?? 0);

$pdo = db();
$eventId = 0;

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ? FOR UPDATE');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();

    if (!$ticket || !($user['role'] === 'admin' || (int)$ticket['user_id'] === (int)$user['id'])) {
        $pdo->rollBack();
        flash('error', 'Action impossible.');
        redirect('profil.php');
    }

    $eventId = (int)$ticket['event_id'];

    $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = ?")->execute([$ticketId]);

    $evStmt = $pdo->prepare("SELECT price FROM events WHERE id = ?");
    $evStmt->execute([$eventId]);
    $event = $evStmt->fetch();

    $wStmt = $pdo->prepare("SELECT * FROM waitlist WHERE event_id = ? AND status = 'waiting' ORDER BY created_at ASC LIMIT 1 FOR UPDATE");
    $wStmt->execute([$eventId]);
    $nextUser = $wStmt->fetch();

    if ($nextUser) {
        if ((float)($event['price'] ?? 0) <= 0) {
            $code = strtoupper(bin2hex(random_bytes(4))) . '-' . $eventId . '-' . $nextUser['user_id'];
            $pdo->prepare('INSERT INTO tickets (event_id, user_id, code, payment_status) VALUES (?, ?, ?, ?)')->execute([$eventId, $nextUser['user_id'], $code, 'free']);
            $pdo->prepare("DELETE FROM waitlist WHERE id = ?")->execute([$nextUser['id']]);
        } else {
            $pdo->prepare("UPDATE waitlist SET status = 'promoted' WHERE id = ?")->execute([$nextUser['id']]);
        }
    }

    $pdo->commit();
    flash('success', 'Billet annulé.');
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('error', 'Erreur lors de l\'annulation.');
}

redirect($eventId ? 'event_detail.php?id=' . $eventId : 'profil.php');
