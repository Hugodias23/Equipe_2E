<?php
require_once 'db.php';
$user = require_role(['organizer', 'admin']);
$eventId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM events WHERE id = ?');
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event || ($user['role'] !== 'admin' && (int) $event['organizer_id'] !== (int) $user['id'])) {
    flash('error', 'Suppression impossible.');
    redirect('profil.php');
}

$stmt = db()->prepare('DELETE FROM events WHERE id = ?');
$stmt->execute([$eventId]);
flash('success', 'Événement supprimé.');
redirect($user['role'] === 'admin' ? 'admin.php' : 'profil.php');
