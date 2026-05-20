<?php
require_once 'db.php';
$user = require_login();

$action   = $_POST['action']  ?? '';
$targetId = (int)($_POST['user_id'] ?? 0);
$back     = $_POST['back']    ?? 'explore.php';

if (!$targetId || $targetId === (int)$user['id']) {
    redirect($back);
}

$pdo = db();

if ($action === 'follow') {
    $stmt = $pdo->prepare("SELECT is_private FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();
    if ($target) {
        $status = $target['is_private'] ? 'pending' : 'accepted';
        $pdo->prepare("INSERT IGNORE INTO follows (follower_id, following_id, status) VALUES (?, ?, ?)")
            ->execute([$user['id'], $targetId, $status]);
        flash('success', $status === 'pending' ? 'Demande d\'abonnement envoyée.' : 'Abonné !');
    }
}
elseif ($action === 'unfollow') {
    $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?")
        ->execute([$user['id'], $targetId]);
    flash('success', 'Désabonné.');
}
elseif ($action === 'approve') {
    $pdo->prepare("UPDATE follows SET status = 'accepted' WHERE follower_id = ? AND following_id = ? AND status = 'pending'")
        ->execute([$targetId, $user['id']]);
    flash('success', 'Demande acceptée.');
}
elseif ($action === 'reject') {
    $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ? AND status = 'pending'")
        ->execute([$targetId, $user['id']]);
    flash('success', 'Demande refusée.');
}

redirect($back);
