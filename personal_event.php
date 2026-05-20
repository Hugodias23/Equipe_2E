<?php
require_once 'db.php';
$user = require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $title = trim($_POST['title'] ?? '');
    $date  = $_POST['date'] ?? '';
    $time  = trim($_POST['time'] ?? '');

    if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['error' => 'Titre et date requis']);
        exit;
    }

    $timeVal = ($time !== '' && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) ? substr($time, 0, 5) : null;

    $stmt = db()->prepare("INSERT INTO personal_events (user_id, title, event_date, event_time) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user['id'], $title, $date, $timeVal]);

    echo json_encode([
        'id'    => (int) db()->lastInsertId(),
        'title' => $title,
        'date'  => $date,
        'time'  => $timeVal ?? '',
    ]);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['error' => 'ID invalide']);
        exit;
    }
    db()->prepare("DELETE FROM personal_events WHERE id = ? AND user_id = ?")->execute([$id, $user['id']]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Action inconnue']);
