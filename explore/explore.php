<?php
require_once 'db.php';
$viewer = current_user();
$q      = trim($_GET['q'] ?? '');
$isAjax = isset($_GET['ajax']);

$params = [];
$sql = "SELECT u.id, u.name, u.avatar, u.role, u.association, u.is_private,
    (SELECT COUNT(*) FROM follows f WHERE f.following_id = u.id AND f.status = 'accepted') AS followers_count
    FROM users u WHERE u.status = 'active'";

if ($q !== '') {
    $sql .= " AND (u.name LIKE ? OR u.association LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$sql .= " ORDER BY u.name ASC LIMIT 60";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$followMap = [];
if ($viewer && $users) {
    $ids   = array_column($users, 'id');
    $ph    = implode(',', array_fill(0, count($ids), '?'));
    $fStmt = db()->prepare("SELECT following_id, status FROM follows WHERE follower_id = ? AND following_id IN ($ph)");
    $fStmt->execute(array_merge([$viewer['id']], $ids));
    foreach ($fStmt->fetchAll() as $f) {
        $followMap[$f['following_id']] = $f['status'];
    }
}

// ── Mode AJAX : renvoie uniquement le HTML de la grille ──
if ($isAjax) {
    if ($users) : ?>
    <div class="explore-grid">
        <?php foreach ($users as $u) : ?>
        <?php $fs = $followMap[$u['id']] ?? null; ?>
        <div class="explore-card">
            <a href="user_profile.php?id=<?php echo (int)$u['id']; ?>">
                <img class="explore-avatar"
                     src="<?php echo $u['avatar'] ? e($u['avatar']) : 'images/default-avatar.svg'; ?>"
                     alt="" width="72" height="72">
            </a>
            <a href="user_profile.php?id=<?php echo (int)$u['id']; ?>" class="explore-name">
                <?php echo e($u['name']); ?>
            </a>
            <?php if ($u['association']) : ?>
                <span class="muted" style="font-size:.78rem"><?php echo e($u['association']); ?></span>
            <?php endif; ?>
            <span class="muted" style="font-size:.75rem">
                <?php echo (int)$u['followers_count']; ?> abonné<?php echo $u['followers_count'] != 1 ? 's' : ''; ?>
            </span>
            <?php if ($u['is_private']) : ?>
                <span class="badge-private">🔒 Privé</span>
            <?php endif; ?>
            <?php if ($viewer && (int)$viewer['id'] !== (int)$u['id']) : ?>
            <form method="post" action="follow.php" style="width:100%">
                <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                <input type="hidden" name="back" value="explore.php">
                <?php if ($fs === 'accepted') : ?>
                    <input type="hidden" name="action" value="unfollow">
                    <button class="btn ghost btn-sm" style="width:100%" type="submit">Abonné ✓</button>
                <?php elseif ($fs === 'pending') : ?>
                    <input type="hidden" name="action" value="unfollow">
                    <button class="btn ghost btn-sm" style="width:100%" type="submit">Demande envoyée…</button>
                <?php else : ?>
                    <input type="hidden" name="action" value="follow">
                    <button class="btn primary btn-sm" style="width:100%" type="submit">S'abonner</button>
                <?php endif; ?>
            </form>
            <?php elseif (!$viewer) : ?>
                <a class="btn ghost btn-sm" style="width:100%;text-align:center" href="login.php">Connexion</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php elseif ($q !== '') : ?>
        <p class="muted" style="margin-top:1.5rem">Aucun profil trouvé pour "<?php echo e($q); ?>".</p>
    <?php else : ?>
        <p class="muted" style="margin-top:1.5rem">Aucun utilisateur inscrit pour l'instant.</p>
    <?php endif;
    exit;
}

// ── Mode normal : page complète ──
$pageTitle = 'Explorer';
require 'header.php';
?>
<main class="shell">
    <section class="panel">
        <p class="eyebrow">Communauté</p>
        <h1>Explorer les profils</h1>

        <form class="explore-search" method="get" action="explore.php" id="explore-form">
            <input type="search" name="q" id="explore-input" value="<?php echo e($q); ?>"
                   placeholder="Rechercher un nom, une association…" autocomplete="off">
            <button class="btn primary" type="submit">Chercher</button>
        </form>

        <div id="explore-results">
        <?php if ($users) : ?>
        <div class="explore-grid">
            <?php foreach ($users as $u) : ?>
            <?php $fs = $followMap[$u['id']] ?? null; ?>
            <div class="explore-card">
                <a href="user_profile.php?id=<?php echo (int)$u['id']; ?>">
                    <img class="explore-avatar"
                         src="<?php echo $u['avatar'] ? e($u['avatar']) : 'images/default-avatar.svg'; ?>"
                         alt="" width="72" height="72">
                </a>
                <a href="user_profile.php?id=<?php echo (int)$u['id']; ?>" class="explore-name">
                    <?php echo e($u['name']); ?>
                </a>
                <?php if ($u['association']) : ?>
                    <span class="muted" style="font-size:.78rem"><?php echo e($u['association']); ?></span>
                <?php endif; ?>
                <span class="muted" style="font-size:.75rem">
                    <?php echo (int)$u['followers_count']; ?> abonné<?php echo $u['followers_count'] != 1 ? 's' : ''; ?>
                </span>
                <?php if ($u['is_private']) : ?>
                    <span class="badge-private">🔒 Privé</span>
                <?php endif; ?>
                <?php if ($viewer && (int)$viewer['id'] !== (int)$u['id']) : ?>
                <form method="post" action="follow.php" style="width:100%">
                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                    <input type="hidden" name="back" value="explore.php<?php echo $q ? '?q=' . urlencode($q) : ''; ?>">
                    <?php if ($fs === 'accepted') : ?>
                        <input type="hidden" name="action" value="unfollow">
                        <button class="btn ghost btn-sm" style="width:100%" type="submit">Abonné ✓</button>
                    <?php elseif ($fs === 'pending') : ?>
                        <input type="hidden" name="action" value="unfollow">
                        <button class="btn ghost btn-sm" style="width:100%" type="submit">Demande envoyée…</button>
                    <?php else : ?>
                        <input type="hidden" name="action" value="follow">
                        <button class="btn primary btn-sm" style="width:100%" type="submit">S'abonner</button>
                    <?php endif; ?>
                </form>
                <?php elseif (!$viewer) : ?>
                    <a class="btn ghost btn-sm" style="width:100%;text-align:center" href="login.php">Connexion</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else : ?>
            <p class="muted" style="margin-top:1.5rem">Aucun utilisateur inscrit pour l'instant.</p>
        <?php endif; ?>
        </div>
    </section>
</main>

<script>
(function () {
    var input   = document.getElementById('explore-input');
    var results = document.getElementById('explore-results');
    var timer;

    if (!input || !results) return;

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var q = input.value.trim();
            fetch('explore.php?ajax=1&q=' + encodeURIComponent(q))
                .then(function (r) { return r.text(); })
                .then(function (html) { results.innerHTML = html; })
                .catch(function () {});
        }, 300);
    });
})();
</script>

<?php require 'footer.php'; ?>
