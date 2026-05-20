<?php
require_once 'db.php';
$viewer    = current_user();
$profileId = (int)($_GET['id'] ?? 0);

if (!$profileId) redirect('explore.php');
if ($viewer && (int)$viewer['id'] === $profileId) redirect('profil.php');

$stmt = db()->prepare("SELECT u.id, u.name, u.avatar, u.bio, u.role, u.association, u.is_private,
    (SELECT COUNT(*) FROM follows WHERE following_id = u.id AND status = 'accepted') AS followers_count,
    (SELECT COUNT(*) FROM follows WHERE follower_id  = u.id AND status = 'accepted') AS following_count,
    (SELECT COUNT(*) FROM user_posts WHERE user_id = u.id) AS posts_count
    FROM users u WHERE u.id = ? AND u.status = 'active'");
$stmt->execute([$profileId]);
$profile = $stmt->fetch();

if (!$profile) {
    flash('error', 'Profil introuvable.');
    redirect('explore.php');
}

// Statut de l'abonnement du viewer
$followStatus = null;
if ($viewer) {
    $fStmt = db()->prepare("SELECT status FROM follows WHERE follower_id = ? AND following_id = ?");
    $fStmt->execute([$viewer['id'], $profileId]);
    $follow       = $fStmt->fetch();
    $followStatus = $follow ? $follow['status'] : null;
}

$isFollowing    = ($followStatus === 'accepted');
$canSeeContent  = !$profile['is_private'] || $isFollowing || ($viewer && $viewer['role'] === 'admin');

// Publications
$posts = [];
if ($canSeeContent) {
    $stmt = db()->prepare("SELECT * FROM user_posts WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$profileId]);
    $posts = $stmt->fetchAll();
}

// Événements organisés (info publique visible par tous)
$events = [];
if (in_array($profile['role'], ['organizer', 'admin'])) {
    $stmt = db()->prepare("SELECT id, title, event_date, place, poster FROM events
        WHERE organizer_id = ? AND status = 'published' ORDER BY event_date DESC LIMIT 6");
    $stmt->execute([$profileId]);
    $events = $stmt->fetchAll();
}

$avatar    = $profile['avatar'] ? e($profile['avatar']) : 'images/default-avatar.svg';
$pageTitle = $profile['name'];
require 'header.php';
?>
<main class="shell">

    <!-- En-tête du profil -->
    <section class="panel">
        <div class="profile-head">
            <div class="avatar-form-wrap">
                <img class="profile-avatar" src="<?php echo $avatar; ?>" alt="" width="100" height="100">
            </div>
            <div>
                <p class="eyebrow">
                    <?php echo e($profile['role']); ?>
                    <?php echo $profile['association'] ? ' · ' . e($profile['association']) : ''; ?>
                    <?php if ($profile['is_private']) : ?>
                        <span style="margin-left:.4rem;font-size:.75rem;opacity:.7">🔒</span>
                    <?php endif; ?>
                </p>
                <h1><?php echo e($profile['name']); ?></h1>

                <div class="profile-stats">
                    <div class="profile-stat">
                        <strong><?php echo (int)$profile['posts_count']; ?></strong>
                        <span>Publications</span>
                    </div>
                    <div class="profile-stat">
                        <strong><?php echo (int)$profile['followers_count']; ?></strong>
                        <span>Abonnés</span>
                    </div>
                    <div class="profile-stat">
                        <strong><?php echo (int)$profile['following_count']; ?></strong>
                        <span>Abonnements</span>
                    </div>
                </div>

                <?php if ($canSeeContent && $profile['bio']) : ?>
                    <p class="profile-bio"><?php echo e($profile['bio']); ?></p>
                <?php elseif ($profile['is_private'] && !$isFollowing) : ?>
                    <p class="muted" style="margin-top:.75rem;font-size:.9rem">Ce compte est privé. Abonne-toi pour voir les publications.</p>
                <?php endif; ?>

                <!-- Bouton follow -->
                <?php if ($viewer) : ?>
                <div class="profile-actions" style="margin-top:1rem">
                    <form method="post" action="follow.php">
                        <input type="hidden" name="user_id" value="<?php echo $profileId; ?>">
                        <input type="hidden" name="back" value="user_profile.php?id=<?php echo $profileId; ?>">
                        <?php if ($followStatus === 'accepted') : ?>
                            <input type="hidden" name="action" value="unfollow">
                            <button class="btn ghost" type="submit">Se désabonner</button>
                        <?php elseif ($followStatus === 'pending') : ?>
                            <input type="hidden" name="action" value="unfollow">
                            <button class="btn ghost" type="submit">Demande envoyée · Annuler</button>
                        <?php else : ?>
                            <input type="hidden" name="action" value="follow">
                            <button class="btn primary" type="submit">
                                <?php echo $profile['is_private'] ? 'Demander à s\'abonner' : 'S\'abonner'; ?>
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
                <?php else : ?>
                <div class="profile-actions" style="margin-top:1rem">
                    <a class="btn primary" href="login.php">Se connecter pour s'abonner</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Publications -->
    <?php if ($canSeeContent && $posts) : ?>
    <section class="panel">
        <h2 style="margin-bottom:1rem">Publications (<?php echo count($posts); ?>)</h2>
        <div class="posts-grid">
            <?php foreach ($posts as $post) : ?>
            <div class="post-item">
                <img src="<?php echo e($post['photo_path']); ?>" alt="" loading="lazy">
                <?php if ($post['caption']) : ?>
                    <p class="post-caption"><?php echo e($post['caption']); ?></p>
                <?php endif; ?>
                <span class="muted" style="font-size:.72rem;padding:.25rem .75rem .5rem;display:block">
                    <?php echo date('d/m/Y', strtotime($post['created_at'])); ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php elseif ($profile['is_private'] && !$canSeeContent) : ?>
    <section class="panel" style="text-align:center;padding:3rem 1.5rem">
        <p style="font-size:2.5rem;margin-bottom:.75rem">🔒</p>
        <h2>Compte privé</h2>
        <p class="muted">Abonne-toi pour voir les publications de <?php echo e($profile['name']); ?>.</p>
    </section>
    <?php endif; ?>

    <!-- Événements organisés -->
    <?php if ($events) : ?>
    <section class="panel">
        <h2 style="margin-bottom:1rem">Événements organisés</h2>
        <div class="events-grid">
            <?php foreach ($events as $ev) : ?>
            <a class="event-card" href="event_detail.php?id=<?php echo (int)$ev['id']; ?>">
                <img src="<?php echo $ev['poster'] ? e($ev['poster']) : 'images/default-poster.svg'; ?>" alt="">
                <div class="event-body">
                    <h2><?php echo e($ev['title']); ?></h2>
                    <p><?php echo date('d/m/Y', strtotime($ev['event_date'])); ?> · <?php echo e($ev['place']); ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</main>
<?php require 'footer.php'; ?>
