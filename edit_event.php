<?php
require_once 'db.php';
$user = require_role(['organizer', 'admin']);

$id   = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM events WHERE id = ?');
$stmt->execute([$id]);
$event = $stmt->fetch();

// Vérification : l'événement doit exister et appartenir à l'utilisateur (ou admin)
if (!$event || ($user['role'] !== 'admin' && (int)$event['organizer_id'] !== (int)$user['id'])) {
    flash('error', 'Événement introuvable.');
    redirect('profil.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date  = str_replace('T', ' ', trim($_POST['event_date'] ?? ''));
    $place       = trim($_POST['place']       ?? '');
    $category    = $_POST['category'] ?? 'Autre';
    $association = trim($_POST['association'] ?? '');
    $capacity    = max(1, (int)($_POST['capacity'] ?? 1));
    $price       = max(0, (float)str_replace(',', '.', $_POST['price'] ?? '0'));
    $poster      = $event['poster'];

    // Nouveau poster
    if (!empty($_FILES['poster']['name']) && is_uploaded_file($_FILES['poster']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $dir = __DIR__ . '/uploads/';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $poster = 'uploads/' . uniqid('poster_') . '.' . $ext;
            move_uploaded_file($_FILES['poster']['tmp_name'], __DIR__ . '/' . $poster);
        }
    }

    if (!$title || !$description || !$place || !strtotime($event_date)) {
        $error = 'Tous les champs obligatoires doivent être remplis.';
    } else {
        $event_date = date('Y-m-d H:i:s', strtotime($event_date));
        $stmt = db()->prepare('UPDATE events SET title=?, description=?, event_date=?, place=?, category=?, association=?, capacity=?, price=?, poster=? WHERE id=?');
        $stmt->execute([$title, $description, $event_date, $place, $category, $association, $capacity, $price, $poster, $id]);
        $promoted = promote_waitlist($id);
        $msg = 'Événement mis à jour.';
        if ($promoted > 0) {
            $msg .= ' ' . $promoted . ' personne' . ($promoted > 1 ? 's ont été promues' : ' a été promue') . ' depuis la liste d\'attente.';
        }
        flash('success', $msg);
        redirect('event_detail.php?id=' . $id);
    }
}

$pageTitle = 'Modifier : ' . $event['title'];
require 'header.php';
?>

<main class="shell">
    <form class="panel form-wide" method="post" enctype="multipart/form-data">
        <p class="eyebrow">Espace organisateur</p>
        <h1>Modifier un événement</h1>

        <?php if ($error) : ?>
            <p class="form-error"><?php echo e($error); ?></p>
        <?php endif; ?>

        <label>
            Titre *
            <input type="text" name="title" value="<?php echo e($event['title']); ?>" required>
        </label>
        <label>
            Description *
            <textarea name="description" rows="5" required><?php echo e($event['description']); ?></textarea>
        </label>

        <div class="form-grid">
            <label>
                Date et heure *
                <input type="datetime-local" name="event_date"
                       value="<?php echo date('Y-m-d\TH:i', strtotime($event['event_date'])); ?>" required>
            </label>
            <label>
                Lieu *
                <input type="text" name="place" value="<?php echo e($event['place']); ?>" required>
            </label>
            <label>
                Catégorie
                <select name="category">
                    <?php foreach (['Soirée', 'Sport', 'Culture', 'Conférence', 'Autre'] as $cat) : ?>
                        <option <?php echo $event['category'] === $cat ? 'selected' : ''; ?>><?php echo e($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Association *
                <input type="text" name="association" value="<?php echo e($event['association']); ?>" required>
            </label>
            <label>
                Capacité maximale
                <input type="number" name="capacity" min="1" value="<?php echo (int)$event['capacity']; ?>" required>
            </label>
            <label>
                Prix du billet (€)
                <input type="number" name="price" min="0" step="0.01" value="<?php echo number_format((float)$event['price'], 2, '.', ''); ?>">
            </label>
            <label>
                Nouvelle affiche <small class="muted">(laisser vide pour garder l'actuelle)</small>
                <input type="file" name="poster" accept="image/*">
            </label>
        </div>

        <?php if ($event['poster']) : ?>
            <p class="muted" style="font-size:.82rem">Affiche actuelle : <?php echo e($event['poster']); ?></p>
        <?php endif; ?>

        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
            <button class="btn primary" type="submit">Enregistrer les modifications</button>
            <a class="btn ghost" href="event_detail.php?id=<?php echo (int)$event['id']; ?>">Annuler</a>
        </div>
    </form>
</main>

<?php require 'footer.php'; ?>
