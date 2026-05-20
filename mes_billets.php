<?php
require_once 'db.php';
$user = require_login();

// ── Notation d'un événement passé ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rate') {
    $eventId = (int)($_POST['event_id'] ?? 0);
    $rating  = (int)($_POST['rating']   ?? 0);
    $comment = trim($_POST['comment']   ?? '');

    if ($eventId && $rating >= 1 && $rating <= 5) {
        // Vérifie que l'utilisateur a bien assisté à cet événement
        $stmt = db()->prepare("SELECT id FROM tickets WHERE user_id = ? AND event_id = ? AND status != 'cancelled'");
        $stmt->execute([$user['id'], $eventId]);
        if ($stmt->fetch()) {
            // INSERT ou UPDATE si déjà noté
            $stmt = db()->prepare("INSERT INTO event_ratings (user_id, event_id, rating, comment)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)");
            $stmt->execute([$user['id'], $eventId, $rating, $comment]);
            flash('success', 'Note enregistrée !');
        }
    }
    redirect('mes_billets.php');
}

// ── Récupération des billets ──
$stmt = db()->prepare("SELECT t.*, e.title, e.event_date, e.place, e.category, e.price, e.poster
    FROM tickets t
    JOIN events e ON e.id = t.event_id
    WHERE t.user_id = ?
    ORDER BY e.event_date DESC");
$stmt->execute([$user['id']]);
$tickets = $stmt->fetchAll();

// ── Récupération de la liste d'attente ──
$stmt = db()->prepare("SELECT w.*, e.title, e.event_date, e.place, e.price,
    (SELECT COUNT(*) FROM waitlist w2
     WHERE w2.event_id = w.event_id AND w2.status = 'waiting' AND w2.created_at <= w.created_at) AS position
    FROM waitlist w
    JOIN events e ON e.id = w.event_id
    WHERE w.user_id = ?
    ORDER BY e.event_date ASC");
$stmt->execute([$user['id']]);
$waitlist = $stmt->fetchAll();

// ── Événements passés (pour la notation) ──
$stmt = db()->prepare("SELECT DISTINCT e.id, e.title, e.event_date, e.place, e.poster,
    r.rating AS ma_note, r.comment AS mon_commentaire
    FROM tickets t
    JOIN events e ON e.id = t.event_id
    LEFT JOIN event_ratings r ON r.event_id = e.id AND r.user_id = ?
    WHERE t.user_id = ? AND t.status != 'cancelled' AND e.event_date < NOW()
    ORDER BY e.event_date DESC");
$stmt->execute([$user['id'], $user['id']]);
$pastEvents = $stmt->fetchAll();

$pageTitle = 'Mes billets';
require 'header.php';
?>

<main class="shell">
    <section class="panel">
        <p class="eyebrow">Espace personnel</p>
        <h1 style="margin-bottom:1.5rem">Mes billets</h1>

        <?php if ($tickets) : ?>
            <div class="billets-section">
            <?php foreach ($tickets as $ticket) : ?>
                <div class="billet-card">
                    <div class="billet-info">
                        <h3><a href="event_detail.php?id=<?php echo (int)$ticket['event_id']; ?>"><?php echo e($ticket['title']); ?></a></h3>
                        <p>
                            <?php echo date('d/m/Y H:i', strtotime($ticket['event_date'])); ?>
                            · <?php echo e($ticket['place']); ?>
                            · <?php echo (float)$ticket['price'] > 0 ? number_format((float)$ticket['price'], 2, ',', ' ') . ' €' : 'Gratuit'; ?>
                        </p>
                    </div>
                    <div class="billet-actions">
                        <span class="status-<?php echo e($ticket['status']); ?>">
                            <?php
                            $labels = ['reserved' => 'Réservé', 'cancelled' => 'Annulé', 'present' => 'Présent'];
                            echo $labels[$ticket['status']] ?? e($ticket['status']);
                            ?>
                        </span>
                        <?php if ($ticket['status'] === 'reserved') : ?>
                            <button class="btn ghost btn-sm qr-trigger"
                                data-code="<?php echo e($ticket['code']); ?>"
                                data-title="<?php echo e($ticket['title']); ?>"
                                data-date="<?php echo date('d/m/Y H:i', strtotime($ticket['event_date'])); ?>"
                                data-place="<?php echo e($ticket['place']); ?>">
                                QR Code
                            </button>
                            <a class="btn ghost btn-sm"
                               href="cancel_ticket.php?id=<?php echo (int)$ticket['id']; ?>"
                               data-confirm="Annuler ce billet ?">Annuler</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="muted">Tu n'as pas encore de billet. <a href="index.php">Voir les événements →</a></p>
        <?php endif; ?>
    </section>

    <!-- Liste d'attente -->
    <?php if ($waitlist) : ?>
    <section class="panel">
        <h2 style="margin-bottom:1rem">File d'attente</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Événement</th>
                        <th>Date</th>
                        <th>Position</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($waitlist as $entry) : ?>
                    <tr>
                        <td><a href="event_detail.php?id=<?php echo (int)$entry['event_id']; ?>"><?php echo e($entry['title']); ?></a></td>
                        <td><?php echo date('d/m/Y', strtotime($entry['event_date'])); ?></td>
                        <td>
                            <?php if ($entry['status'] === 'promoted') : ?>
                                <span class="status-promoted">⚡ Place disponible !</span>
                            <?php else : ?>
                                Position #<?php echo (int)$entry['position']; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($entry['status'] === 'promoted') : ?>
                                <a class="btn primary btn-sm" href="<?php echo (float)$entry['price'] > 0 ? 'payment.php?id=' . (int)$entry['event_id'] : 'reserve.php?id=' . (int)$entry['event_id']; ?>">
                                    <?php echo (float)$entry['price'] > 0 ? 'Payer maintenant' : 'Confirmer'; ?>
                                </a>
                            <?php else : ?>
                                <a class="btn ghost btn-sm" href="leave_waitlist.php?id=<?php echo (int)$entry['event_id']; ?>"
                                   data-confirm="Quitter la file d'attente ?">Quitter</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <!-- Notation des soirées passées -->
    <?php if ($pastEvents) : ?>
    <section class="panel">
        <h2 style="margin-bottom:.5rem">Noter mes soirées</h2>
        <p class="muted" style="margin-bottom:1.25rem;font-size:.85rem">Note les soirées auxquelles tu as participé.</p>
        <div class="rating-grid">
        <?php foreach ($pastEvents as $ev) : ?>
            <div class="rating-card">
                <img class="rating-poster"
                     src="<?php echo $ev['poster'] ? e($ev['poster']) : 'images/default-poster.svg'; ?>"
                     alt="">
                <div class="rating-info">
                    <h3><a href="event_detail.php?id=<?php echo (int)$ev['id']; ?>"><?php echo e($ev['title']); ?></a></h3>
                    <p><?php echo date('d/m/Y', strtotime($ev['event_date'])); ?> · <?php echo e($ev['place']); ?></p>
                    <form method="post">
                        <input type="hidden" name="action"   value="rate">
                        <input type="hidden" name="event_id" value="<?php echo (int)$ev['id']; ?>">
                        <!-- Étoiles (radio buttons inversés pour le hover CSS) -->
                        <div class="star-select">
                            <?php for ($i = 5; $i >= 1; $i--) : ?>
                                <input type="radio" name="rating" id="star-<?php echo $ev['id']; ?>-<?php echo $i; ?>"
                                       value="<?php echo $i; ?>" <?php echo $ev['ma_note'] == $i ? 'checked' : ''; ?>>
                                <label for="star-<?php echo $ev['id']; ?>-<?php echo $i; ?>" title="<?php echo $i; ?> étoile<?php echo $i > 1 ? 's' : ''; ?>">★</label>
                            <?php endfor; ?>
                        </div>
                        <label style="margin-top:.5rem;font-weight:400;font-size:.82rem">
                            Commentaire (optionnel)
                            <input type="text" name="comment" value="<?php echo e($ev['mon_commentaire'] ?? ''); ?>" maxlength="300">
                        </label>
                        <button class="btn primary btn-sm" type="submit" style="margin-top:.5rem">
                            <?php echo $ev['ma_note'] ? 'Modifier ma note' : 'Envoyer'; ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</main>

<!-- Modal QR Code -->
<div id="qr-modal" class="qr-overlay" style="display:none" role="dialog">
    <div class="qr-dialog">
        <h2 id="qr-event-title"></h2>
        <p class="muted" id="qr-event-info"></p>
        <div id="qr-code-canvas"></div>
        <p class="qr-code-text" id="qr-code-display"></p>
        <p class="muted" style="font-size:.8rem">Présente ce QR code à l'entrée</p>
        <button class="btn ghost" id="qr-close">Fermer</button>
    </div>
</div>

<!-- Lib QR Code -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous"></script>

<?php require 'footer.php'; ?>
