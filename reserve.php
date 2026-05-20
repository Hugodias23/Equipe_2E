<?php
require_once 'db.php';
$user = require_role(['participant', 'organizer', 'admin']);
$eventId = (int)($_GET['id'] ?? 0);

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT capacity, status, price,
        (SELECT COUNT(*) FROM tickets WHERE event_id = events.id AND status = 'reserved') AS reserved_count
        FROM events WHERE id = ? FOR UPDATE");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();

    if (!$event || $event['status'] !== 'published') {
        throw new RuntimeException('Événement indisponible.');
    }
    if ((float)$event['price'] > 0) {
        $pdo->rollBack();
        redirect('payment.php?id=' . $eventId);
    }

    $wStmt = $pdo->prepare("SELECT id, status FROM waitlist WHERE event_id = ? AND user_id = ?");
    $wStmt->execute([$eventId, $user['id']]);
    $waitlistEntry = $wStmt->fetch();
    $isPromoted = $waitlistEntry && $waitlistEntry['status'] === 'promoted';

    if ((int)$event['reserved_count'] >= (int)$event['capacity'] && !$isPromoted) {
        $wInsert = $pdo->prepare("INSERT IGNORE INTO waitlist (event_id, user_id) VALUES (?, ?)");
        $wInsert->execute([$eventId, $user['id']]);
        $pdo->commit();
        flash('success', 'Événement complet. Tu as été ajouté à la liste d\'attente.');
        redirect('event_detail.php?id=' . $eventId);
    }

    $code = strtoupper(bin2hex(random_bytes(4))) . '-' . $eventId . '-' . $user['id'];
    $stmt = $pdo->prepare('INSERT INTO tickets (event_id, user_id, code, payment_status) VALUES (?, ?, ?, ?)');
    $stmt->execute([$eventId, $user['id'], $code, 'free']);

    if ($waitlistEntry) {
        $pdo->prepare("DELETE FROM waitlist WHERE id = ?")->execute([$waitlistEntry['id']]);
    }

    $pdo->commit();
    flash('success', 'Réservation confirmée. Ton billet est dans ton profil.');
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('error', $e instanceof PDOException ? 'Tu as déjà un billet pour cet événement.' : $e->getMessage());
}
redirect('event_detail.php?id=' . $eventId);
