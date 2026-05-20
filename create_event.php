<?php
require_once 'db.php';
$user = require_role(['organizer', 'admin']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date  = str_replace('T', ' ', trim($_POST['event_date'] ?? ''));
    $place       = trim($_POST['place']       ?? '');
    $category    = $_POST['category'] ?? 'Autre';
    $association = trim($_POST['association'] ?? ($user['association'] ?? 'Omnes'));
    $capacity    = max(1, (int)($_POST['capacity'] ?? 50));
    $price       = max(0, (float)str_replace(',', '.', $_POST['price'] ?? '0'));
    $poster      = null;

    // Upload de l'affiche
    if (!empty($_FILES['poster']['name']) && is_uploaded_file($_FILES['poster']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $dir = __DIR__ . '/uploads/';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $poster = 'uploads/' . uniqid('poster_') . '.' . $ext;
            move_uploaded_file($_FILES['poster']['tmp_name'], __DIR__ . '/' . $poster);
        }
    }

    // Validation
    if (!$title || !$description || !$place || !strtotime($event_date)) {
        $error = 'Tous les champs obligatoires doivent être remplis.';
    } else {
        $event_date = date('Y-m-d H:i:s', strtotime($event_date));
        $stmt = db()->prepare('INSERT INTO events (organizer_id, title, description, event_date, place, category, association, capacity, price, poster) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $title, $description, $event_date, $place, $category, $association, $capacity, $price, $poster]);
        $newId = (int)db()->lastInsertId();
        flash('success', 'Événement publié !');
        redirect('event_detail.php?id=' . $newId);
    }
}

$pageTitle = 'Créer un événement';
require 'header.php';
?>

<main class="shell">
    <form class="panel form-wide" method="post" enctype="multipart/form-data">
        <p class="eyebrow">Espace organisateur</p>
        <h1>Créer un événement</h1>

        <?php if ($error) : ?>
            <p class="form-error"><?php echo e($error); ?></p>
        <?php endif; ?>

        <label>
            Titre *
            <input type="text" name="title" required>
        </label>
        <label>
            Description *
            <textarea name="description" rows="5" required></textarea>
        </label>

        <div class="form-grid">
            <label>
                Date et heure *
                <input type="datetime-local" name="event_date" min="<?php echo date('Y-m-d\TH:i'); ?>" required>
            </label>
            <label>
                Lieu *
                <input type="text" name="place" placeholder="Salle des fêtes, Agora…" required>
            </label>
            <label>
                Catégorie
                <select name="category">
                    <?php foreach (['Soirée', 'Sport', 'Culture', 'Conférence', 'Autre'] as $cat) : ?>
                        <option><?php echo e($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Association *
                <input type="text" name="association" value="<?php echo e($user['association'] ?? 'Omnes'); ?>" required>
            </label>
            <label>
                Capacité maximale
                <input type="number" name="capacity" min="1" value="50" required>
            </label>
            <label>
                Prix du billet (€) <small class="muted">0 = gratuit</small>
                <input type="number" name="price" min="0" step="0.01" value="0">
            </label>
            <label>
                Affiche (image)
                <input type="file" name="poster" accept="image/*">
            </label>
        </div>

        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
            <button class="btn primary" type="submit">Publier l'événement</button>
            <a class="btn ghost" href="profil.php">Annuler</a>
        </div>
    </form>
</main>

<?php require 'footer.php'; ?>
