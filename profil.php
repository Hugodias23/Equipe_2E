<?php
require_once 'db.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_bio') {
        $bio = substr(trim($_POST['bio'] ?? ''), 0, 300);
        db()->prepare('UPDATE users SET bio = ? WHERE id = ?')->execute([$bio, $user['id']]);
        flash('success', 'Bio mise à jour.');
        redirect('profil.php');
    }

    if ($action === 'update_avatar' && isset($_FILES['avatar'])) {
        $file = $_FILES['avatar'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file['error'] === 0 && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $dir      = __DIR__ . '/uploads/avatars/';
            $filename = 'uploads/avatars/avatar_' . $user['id'] . '_' . time() . '.' . $ext;
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            if (move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $filename)) {
                $stmt = db()->prepare('SELECT avatar FROM users WHERE id = ?');
                $stmt->execute([$user['id']]);
                $old = $stmt->fetchColumn();
                if ($old && file_exists(__DIR__ . '/' . $old)) unlink(__DIR__ . '/' . $old);
                db()->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$filename, $user['id']]);
                flash('success', 'Photo de profil mise à jour.');
            }
        } else {
            flash('error', 'Fichier invalide (jpg, png, webp acceptés).');
        }
        redirect('profil.php');
    }

    if ($action === 'toggle_privacy') {
        $stmt = db()->prepare('SELECT is_private FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $cur = (int)$stmt->fetchColumn();
        db()->prepare('UPDATE users SET is_private = ? WHERE id = ?')->execute([$cur ? 0 : 1, $user['id']]);
        flash('success', $cur ? 'Compte mis en public.' : 'Compte mis en privé.');
        redirect('profil.php');
    }

    if ($action === 'upload_post' && isset($_FILES['photo'])) {
        $file    = $_FILES['photo'];
        $caption = trim($_POST['caption'] ?? '');
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file['error'] === 0 && in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $dir  = __DIR__ . '/uploads/posts/';
            $path = 'uploads/posts/' . uniqid('post_') . '.' . $ext;
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            if (move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $path)) {
                db()->prepare("INSERT INTO user_posts (user_id, photo_path, caption) VALUES (?, ?, ?)")
                    ->execute([$user['id'], $path, $caption ?: null]);
                flash('success', 'Photo publiée !');
            }
        } else {
            flash('error', 'Fichier invalide (jpg, png, webp, gif acceptés).');
        }
        redirect('profil.php');
    }

    if ($action === 'delete_post') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $stmt   = db()->prepare("SELECT photo_path FROM user_posts WHERE id = ? AND user_id = ?");
        $stmt->execute([$postId, $user['id']]);
        $post   = $stmt->fetch();
        if ($post) {
            if (file_exists(__DIR__ . '/' . $post['photo_path'])) unlink(__DIR__ . '/' . $post['photo_path']);
            db()->prepare("DELETE FROM user_posts WHERE id = ?")->execute([$postId]);
            flash('success', 'Publication supprimée.');
        }
        redirect('profil.php');
    }
}

// Recharge les données à jour
$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$user = $stmt->fetch();

// Statistiques
$stmt = db()->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ? AND status = 'reserved'");
$stmt->execute([$user['id']]);
$nbBillets = (int)$stmt->fetchColumn();

$stmt = db()->prepare("SELECT COUNT(DISTINCT event_id) FROM tickets WHERE user_id = ? AND status != 'cancelled'");
$stmt->execute([$user['id']]);
$nbSoirees = (int)$stmt->fetchColumn();

$stmt = db()->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ? AND status = 'accepted'");
$stmt->execute([$user['id']]);
$nbFollowers = (int)$stmt->fetchColumn();

$stmt = db()->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ? AND status = 'accepted'");
$stmt->execute([$user['id']]);
$nbFollowing = (int)$stmt->fetchColumn();

// Demandes d'abonnement en attente
$pendingRequests = [];
if ($user['is_private']) {
    $stmt = db()->prepare("SELECT f.follower_id, u.name, u.avatar
        FROM follows f JOIN users u ON u.id = f.follower_id
        WHERE f.following_id = ? AND f.status = 'pending'
        ORDER BY f.created_at DESC");
    $stmt->execute([$user['id']]);
    $pendingRequests = $stmt->fetchAll();
}

// Billets réservés (événements à venir)
$stmt = db()->prepare("SELECT t.id AS ticket_id, t.code, t.status AS ticket_status,
    e.id AS event_id, e.title, e.event_date, e.place, e.poster, e.price
    FROM tickets t JOIN events e ON e.id = t.event_id
    WHERE t.user_id = ? AND t.status = 'reserved'
    ORDER BY e.event_date ASC");
$stmt->execute([$user['id']]);
$billets = $stmt->fetchAll();

// Publications
$stmt = db()->prepare("SELECT * FROM user_posts WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$posts = $stmt->fetchAll();

// Événements organisés
$events = [];
if (in_array($user['role'], ['organizer', 'admin'])) {
    if ($user['role'] === 'admin') {
        $stmt = db()->query("SELECT e.*,
            (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'reserved') AS reserved_count
            FROM events e ORDER BY e.event_date DESC");
    } else {
        $stmt = db()->prepare("SELECT e.*,
            (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'reserved') AS reserved_count
            FROM events e WHERE e.organizer_id = ? ORDER BY e.event_date DESC");
        $stmt->execute([$user['id']]);
    }
    $events = $stmt->fetchAll();
}

$avatar    = $user['avatar'] ? e($user['avatar']) : 'images/default-avatar.svg';
$pageTitle = 'Mon profil';
require 'header.php';
?>

<main class="shell dashboard">

    <!-- En-tête du profil -->
    <section class="panel">
        <div class="profile-head">
            <div class="avatar-form-wrap">
                <img class="profile-avatar" src="<?php echo $avatar; ?>" alt="Mon avatar" id="profile-avatar-img" width="100" height="100">
                <label class="change-avatar" for="avatar-input" title="Changer la photo">✎</label>
                <form id="avatar-upload-form" method="post" enctype="multipart/form-data" style="display:none">
                    <input type="hidden" name="action" value="update_avatar">
                    <input type="file" id="avatar-input" name="avatar" accept="image/jpeg,image/png,image/webp">
                </form>
            </div>

            <div>
                <p class="eyebrow">
                    <?php echo e($user['role']); ?>
                    <?php echo $user['association'] ? ' · ' . e($user['association']) : ''; ?>
                    <?php if ($user['is_private']) : ?>
                        <span style="margin-left:.5rem;opacity:.8">🔒 Privé</span>
                    <?php endif; ?>
                </p>
                <h1><?php echo e($user['name']); ?></h1>

                <div class="profile-stats">
                    <div class="profile-stat"><strong><?php echo count($posts); ?></strong><span>Publications</span></div>
                    <div class="profile-stat"><strong><?php echo $nbFollowers; ?></strong><span>Abonnés</span></div>
                    <div class="profile-stat"><strong><?php echo $nbFollowing; ?></strong><span>Abonnements</span></div>
                    <div class="profile-stat"><strong><?php echo $nbSoirees; ?></strong><span>Soirées</span></div>
                    <div class="profile-stat"><strong><?php echo $nbBillets; ?></strong><span>Billets actifs</span></div>
                </div>

                <p class="profile-bio <?php echo $user['bio'] ? '' : 'empty'; ?>" id="bio-display">
                    <?php echo $user['bio'] ? e($user['bio']) : 'Clique sur "Modifier" pour ajouter une bio.'; ?>
                </p>

                <form id="bio-form" method="post" class="bio-form" style="display:none">
                    <input type="hidden" name="action" value="update_bio">
                    <label>
                        Ta bio <small class="muted">(max 300 caractères)</small>
                        <textarea name="bio" rows="3" maxlength="300"><?php echo e($user['bio'] ?? ''); ?></textarea>
                    </label>
                    <div class="btn-row">
                        <button class="btn primary btn-sm" type="submit">Enregistrer</button>
                        <button class="btn ghost btn-sm" type="button" id="bio-cancel-btn">Annuler</button>
                    </div>
                </form>

                <div class="profile-actions">
                    <button class="btn ghost btn-sm" id="bio-edit-btn">Modifier la bio</button>
                    <a class="btn ghost btn-sm" href="mes_billets.php">Mes billets</a>
                    <a class="btn ghost btn-sm" href="explore.php">Explorer</a>
                    <?php if (in_array($user['role'], ['organizer', 'admin'])) : ?>
                        <a class="btn primary btn-sm" href="create_event.php">Nouvel événement</a>
                    <?php endif; ?>
                    <!-- Toggle compte privé -->
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="toggle_privacy">
                        <button class="btn ghost btn-sm" type="submit">
                            <?php echo $user['is_private'] ? '🔓 Rendre public' : '🔒 Rendre privé'; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Demandes d'abonnement en attente -->
    <?php if ($pendingRequests) : ?>
    <section class="panel">
        <h2 style="margin-bottom:1rem">
            Demandes d'abonnement
            <span class="badge-count"><?php echo count($pendingRequests); ?></span>
        </h2>
        <div class="follow-requests-list">
            <?php foreach ($pendingRequests as $req) : ?>
            <div class="follow-request-item">
                <img src="<?php echo $req['avatar'] ? e($req['avatar']) : 'images/default-avatar.svg'; ?>"
                     alt="" width="44" height="44" style="border-radius:50%;object-fit:cover;flex-shrink:0">
                <a href="user_profile.php?id=<?php echo (int)$req['follower_id']; ?>" style="font-weight:600;flex:1">
                    <?php echo e($req['name']); ?>
                </a>
                <div class="follow-request-actions">
                    <form method="post" action="follow.php" style="display:inline">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="user_id" value="<?php echo (int)$req['follower_id']; ?>">
                        <input type="hidden" name="back" value="profil.php">
                        <button class="btn primary btn-sm" type="submit">Accepter</button>
                    </form>
                    <form method="post" action="follow.php" style="display:inline">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="user_id" value="<?php echo (int)$req['follower_id']; ?>">
                        <input type="hidden" name="back" value="profil.php">
                        <button class="btn ghost btn-sm" type="submit">Refuser</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Billets réservés -->
    <?php if ($billets) : ?>
    <section class="panel">
        <h2 style="margin-bottom:1rem">
            Mes billets
            <span class="badge-count"><?php echo count($billets); ?></span>
        </h2>
        <div class="billets-section">
            <?php foreach ($billets as $b) : ?>
            <div class="billet-card">
                <div class="billet-info">
                    <h3><a href="event_detail.php?id=<?php echo (int)$b['event_id']; ?>"><?php echo e($b['title']); ?></a></h3>
                    <p>
                        <?php echo date('d/m/Y H:i', strtotime($b['event_date'])); ?>
                        · <?php echo e($b['place']); ?>
                        · <?php echo (float)$b['price'] > 0 ? number_format((float)$b['price'], 2, ',', ' ') . ' €' : 'Gratuit'; ?>
                    </p>
                </div>
                <div class="billet-actions">
                    <span class="status-reserved">Réservé</span>
                    <a class="btn ghost btn-sm" href="mes_billets.php">Voir le QR code</a>
                    <a class="btn ghost btn-sm"
                       href="cancel_ticket.php?id=<?php echo (int)$b['ticket_id']; ?>"
                       data-confirm="Annuler ce billet ?">Annuler</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Nouvelle publication -->
    <section class="panel">
        <h2 style="margin-bottom:.75rem">Nouvelle publication</h2>
        <form class="post-upload-form" method="post" enctype="multipart/form-data" action="profil.php">
            <input type="hidden" name="action" value="upload_post">
            <label>
                Photo *
                <input type="file" name="photo" accept="image/*" required>
            </label>
            <label>
                Légende <small class="muted">(optionnel)</small>
                <input type="text" name="caption" maxlength="500" placeholder="Décris ta photo…">
            </label>
            <button class="btn primary btn-sm" type="submit">Publier</button>
        </form>
    </section>

    <!-- Mes publications -->
    <?php if ($posts) : ?>
    <section class="panel">
        <h2 style="margin-bottom:1rem">Mes publications (<?php echo count($posts); ?>)</h2>
        <div class="posts-grid">
            <?php foreach ($posts as $post) : ?>
            <div class="post-item">
                <img src="<?php echo e($post['photo_path']); ?>" alt="" loading="lazy">
                <?php if ($post['caption']) : ?>
                    <p class="post-caption"><?php echo e($post['caption']); ?></p>
                <?php endif; ?>
                <div class="post-meta">
                    <span class="muted"><?php echo date('d/m/Y', strtotime($post['created_at'])); ?></span>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="delete_post">
                        <input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>">
                        <button class="btn ghost btn-sm" type="submit"
                                data-confirm="Supprimer cette publication ?">Supprimer</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Événements organisés -->
    <?php if ($events) : ?>
    <section class="panel">
        <h2 style="margin-bottom:1rem">Mes événements (<?php echo count($events); ?>)</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Titre</th><th>Date</th><th>Inscrits</th><th>Statut</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($events as $event) : ?>
                    <tr>
                        <td><a href="event_detail.php?id=<?php echo (int)$event['id']; ?>"><?php echo e($event['title']); ?></a></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($event['event_date'])); ?></td>
                        <td><?php echo (int)$event['reserved_count']; ?> / <?php echo (int)$event['capacity']; ?></td>
                        <td>
                            <?php if ($event['status'] === 'published') : ?>
                                <span class="status-reserved">Publié</span>
                            <?php else : ?>
                                <span class="status-cancelled">Annulé</span>
                            <?php endif; ?>
                        </td>
                        <td class="table-actions">
                            <a class="btn ghost btn-sm" href="edit_event.php?id=<?php echo (int)$event['id']; ?>">Modifier</a>
                            <a class="btn ghost btn-sm" href="attendees.php?id=<?php echo (int)$event['id']; ?>">Inscrits</a>
                            <a class="btn ghost btn-sm" href="delete_event.php?id=<?php echo (int)$event['id']; ?>"
                               data-confirm="Supprimer cet événement ?">Supprimer</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

</main>
<?php require 'footer.php'; ?>
