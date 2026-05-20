<?php
require_once 'db.php';

// Construction de la requête avec filtres GET
$where  = ["e.status = 'published'"];
$params = [];

if (!empty($_GET['q'])) {
    $where[]  = '(e.title LIKE ? OR e.description LIKE ? OR e.place LIKE ?)';
    $term     = '%' . $_GET['q'] . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}
if (!empty($_GET['category'])) {
    $where[]  = 'e.category = ?';
    $params[] = $_GET['category'];
}
if (!empty($_GET['association'])) {
    $where[]  = 'e.association LIKE ?';
    $params[] = '%' . $_GET['association'] . '%';
}
if (!empty($_GET['date'])) {
    $where[]  = 'DATE(e.event_date) = ?';
    $params[] = $_GET['date'];
}

$sql  = "SELECT e.*, u.name AS organizer_name,
    (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'reserved') AS reserved_count
    FROM events e
    JOIN users u ON u.id = e.organizer_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY e.event_date ASC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Réponse AJAX pour les filtres dynamiques
if (isset($_GET['ajax'])) {
    foreach ($events as $event) {
        $remaining = max(0, (int)$event['capacity'] - (int)$event['reserved_count']);
        $poster    = $event['poster'] ?: 'images/default-poster.svg';
        ?>
        <article class="event-card">
            <a href="event_detail.php?id=<?php echo (int)$event['id']; ?>" class="poster-link">
                <img src="<?php echo e($poster); ?>" alt="Affiche <?php echo e($event['title']); ?>">
            </a>
            <div class="event-body">
                <div class="card-meta">
                    <span><?php echo e($event['category']); ?></span>
                    <span><?php echo date('d/m H:i', strtotime($event['event_date'])); ?></span>
                </div>
                <h2><a href="event_detail.php?id=<?php echo (int)$event['id']; ?>"><?php echo e($event['title']); ?></a></h2>
                <p><?php echo e(mb_strimwidth($event['description'], 0, 115, '...')); ?></p>
                <div class="capacity">
                    <span style="width:<?php echo min(100, ((int)$event['reserved_count'] / max(1, (int)$event['capacity'])) * 100); ?>%"></span>
                </div>
                <div class="card-footer">
                    <strong><?php echo $remaining; ?> place<?php echo $remaining > 1 ? 's' : ''; ?></strong>
                    <span><?php echo (float)$event['price'] > 0 ? number_format((float)$event['price'], 2, ',', ' ') . ' €' : 'Gratuit'; ?> · <?php echo e($event['association']); ?></span>
                </div>
            </div>
        </article>
        <?php
    }
    if (!$events) {
        echo '<p class="muted" style="grid-column:1/-1;text-align:center;padding:3rem">Aucun événement trouvé.</p>';
    }
    exit;
}

$pageTitle = 'Événements';
require 'header.php';
?>

<main>
    <!-- Hero -->
    <section class="hero shell">
        <div class="hero-copy">
            <p class="eyebrow">Plateforme étudiante Omnes</p>
            <h1>Tous les événements du campus, au même endroit.</h1>
            <p>Réserve tes billets, suis tes inscriptions et aide les associations à remplir leurs événements.</p>
            <div class="hero-actions">
                <a class="btn primary" href="#events">Voir le programme</a>
                <?php if (!$user) : ?>
                    <a class="btn ghost" href="inscription.php">Créer un compte</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Filtres de recherche -->
    <section class="shell search-panel">
        <form method="get" class="filters">
            <label>
                Recherche
                <input type="search" name="q" value="<?php echo e($_GET['q'] ?? ''); ?>" placeholder="Titre, lieu, mot-clé…">
            </label>
            <label>
                Catégorie
                <select name="category">
                    <option value="">Toutes</option>
                    <?php foreach (['Soirée', 'Sport', 'Culture', 'Conférence', 'Autre'] as $cat) : ?>
                        <option value="<?php echo e($cat); ?>" <?php echo (($_GET['category'] ?? '') === $cat) ? 'selected' : ''; ?>>
                            <?php echo e($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Association
                <input type="text" name="association" value="<?php echo e($_GET['association'] ?? ''); ?>" placeholder="BDE, BDS…">
            </label>
            <label>
                Date
                <input type="date" name="date" value="<?php echo e($_GET['date'] ?? ''); ?>">
            </label>
            <button class="btn primary" type="submit">Filtrer</button>
        </form>
    </section>

    <!-- Grille des événements -->
    <section id="events" class="shell event-grid" aria-live="polite">
        <?php foreach ($events as $event) :
            $remaining = max(0, (int)$event['capacity'] - (int)$event['reserved_count']);
            $poster    = $event['poster'] ?: 'images/default-poster.svg';
        ?>
        <article class="event-card">
            <a href="event_detail.php?id=<?php echo (int)$event['id']; ?>" class="poster-link">
                <img src="<?php echo e($poster); ?>" alt="Affiche <?php echo e($event['title']); ?>">
            </a>
            <div class="event-body">
                <div class="card-meta">
                    <span><?php echo e($event['category']); ?></span>
                    <span><?php echo date('d/m H:i', strtotime($event['event_date'])); ?></span>
                </div>
                <h2><a href="event_detail.php?id=<?php echo (int)$event['id']; ?>"><?php echo e($event['title']); ?></a></h2>
                <p><?php echo e(mb_strimwidth($event['description'], 0, 115, '...')); ?></p>
                <div class="capacity">
                    <span style="width:<?php echo min(100, ((int)$event['reserved_count'] / max(1, (int)$event['capacity'])) * 100); ?>%"></span>
                </div>
                <div class="card-footer">
                    <strong><?php echo $remaining; ?> place<?php echo $remaining > 1 ? 's' : ''; ?></strong>
                    <span><?php echo (float)$event['price'] > 0 ? number_format((float)$event['price'], 2, ',', ' ') . ' €' : 'Gratuit'; ?> · <?php echo e($event['association']); ?></span>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
        <?php if (!$events) : ?>
            <div class="empty" style="grid-column:1/-1">
                <h2>Aucun événement trouvé</h2>
                <p>Modifie les filtres ou reviens plus tard.</p>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require 'footer.php'; ?>
