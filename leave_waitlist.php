<?php
require_once 'db.php';
$user = require_login();
$eventId = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare("DELETE FROM waitlist WHERE event_id = ? AND user_id = ?");
$stmt->execute([$eventId, $user['id']]);

flash('success', 'Tu as été retiré de la liste d\'attente.');
redirect($eventId ? 'event_detail.php?id=' . $eventId : 'index.php');
