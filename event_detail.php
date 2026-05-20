<?php
require_once 'db.php';

$id   = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT e.*, u.name AS organizer_name,
    (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'reserved') AS reserved_count
    FROM events e JOIN users u ON u.id = e.organizer_id WHERE e.id = ?");
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    $pageTitle = 'Événement introuvable';
    require 'header.php';
    echo '<main class="shell"><section class="panel"><h1>Événement introuvable</h1><p class="muted">Cet événement n\'existe pas.</p></section></main>';
    require 'footer.php';
    exit;
}

$user   = current_user();
$ticket = null;
$waitlistEntry = null;

if ($user) {
    $stmt = db()->prepare("SELECT * FROM tickets WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    $ticket = $stmt->fetch();

    if (!$ticket || $ticket['status'] === 'cancelled') {
        $stmt = db()->prepare("SELECT * FROM waitlist WHERE event_id = ? AND user_id = ?");
        $stmt->execute([$id, $user['id']]);
        $waitlistEntry = $stmt->fetch();
    }
}

// Position dans la file d'attente
$waitlistPos = 0;
if ($waitlistEntry && $waitlistEntry['status'] === 'waiting') {
    $stmt = db()->prepare("SELECT COUNT(*) FROM waitlist WHERE event_id = ? AND status = 'waiting' AND created_at <= ?");
    $stmt->execute([$id, $waitlistEntry['created_at']]);
    $waitlistPos = (int)$stmt->fetchColumn();
}

$remaining  = max(0, (int)$event['capacity'] - (int)$event['reserved_count']);
$canManage  = $user && ($user['role'] === 'admin' || (int)$user['id'] === (int)$event['organizer_id']);
$pageTitle  = $event['title'];
$headExtra  = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">';
require 'header.php';
?>

<main class="shell detail-layout">

    <!-- Détail de l'événement -->
    <section class="panel event-detail">
        <img class="detail-poster"
             src="<?php echo e($event['poster'] ?: 'images/default-poster.svg'); ?>"
             alt="Affiche <?php echo e($event['title']); ?>">

        <div>
            <p class="eyebrow"><?php echo e($event['category']); ?> · <?php echo e($event['association']); ?></p>
            <h1><?php echo e($event['title']); ?></h1>
            <p class="lead"><?php echo e($event['description']); ?></p>

            <dl class="info-list">
                <div><dt>Date</dt><dd><?php echo date('d/m/Y H:i', strtotime($event['event_date'])); ?></dd></div>
                <div><dt>Lieu</dt><dd><?php echo e($event['place']); ?></dd></div>
                <div><dt>Organisateur</dt><dd><?php echo e($event['organizer_name']); ?></dd></div>
                <div>
                    <dt>Places</dt>
                    <dd><?php echo $remaining; ?> / <?php echo (int)$event['capacity']; ?> restantes</dd>
                </div>
                <div>
                    <dt>Prix</dt>
                    <dd><?php echo (float)$event['price'] > 0 ? number_format((float)$event['price'], 2, ',', ' ') . ' €' : 'Gratuit'; ?></dd>
                </div>
            </dl>

            <div class="actions">
                <?php if (!$user) : ?>
                    <a class="btn primary" href="login.php">Se connecter pour réserver</a>

                <?php elseif ($ticket && $ticket['status'] === 'reserved') : ?>
                    <button class="btn primary" id="qr-open-btn">Mon QR Code</button>
                    <a class="btn ghost" href="cancel_ticket.php?id=<?php echo (int)$ticket['id']; ?>"
                       data-confirm="Annuler votre billet ?">Annuler mon billet</a>

                <?php elseif ($waitlistEntry) : ?>
                    <?php if ($waitlistEntry['status'] === 'promoted') : ?>
                        <span class="badge badge-warning">⚡ Une place s'est libérée !</span>
                        <?php if ((float)$event['price'] > 0) : ?>
                            <a class="btn primary" href="payment.php?id=<?php echo (int)$event['id']; ?>">Payer et confirmer</a>
                        <?php else : ?>
                            <a class="btn primary" href="reserve.php?id=<?php echo (int)$event['id']; ?>">Confirmer ma place</a>
                        <?php endif; ?>
                    <?php else : ?>
                        <span class="badge badge-info">File d'attente — position <?php echo $waitlistPos; ?></span>
                        <a class="btn ghost" href="leave_waitlist.php?id=<?php echo (int)$event['id']; ?>"
                           data-confirm="Quitter la file d'attente ?">Quitter</a>
                    <?php endif; ?>

                <?php elseif ($remaining > 0 && $event['status'] === 'published') : ?>
                    <?php if ((float)$event['price'] > 0) : ?>
                        <a class="btn primary" href="payment.php?id=<?php echo (int)$event['id']; ?>">Payer et réserver</a>
                    <?php else : ?>
                        <a class="btn primary" href="reserve.php?id=<?php echo (int)$event['id']; ?>">Réserver ma place</a>
                    <?php endif; ?>

                <?php elseif ($event['status'] === 'published') : ?>
                    <span class="badge danger">Complet</span>
                    <a class="btn ghost" href="reserve.php?id=<?php echo (int)$event['id']; ?>">Rejoindre la file d'attente</a>

                <?php else : ?>
                    <span class="badge danger">Événement annulé</span>
                <?php endif; ?>

                <?php if ($canManage) : ?>
                    <a class="btn ghost" href="edit_event.php?id=<?php echo (int)$event['id']; ?>">Modifier</a>
                    <a class="btn ghost" href="attendees.php?id=<?php echo (int)$event['id']; ?>">Voir les inscrits</a>
                    <a class="btn ghost" href="delete_event.php?id=<?php echo (int)$event['id']; ?>"
                       data-confirm="Supprimer cet événement ?">Supprimer</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Carte du lieu -->
    <section class="panel map-section">
        <h2>Localisation</h2>
        <p class="map-place-label"><?php echo e($event['place']); ?></p>
        <div id="event-map"></div>
    </section>

</main>

<!-- Modal QR Code -->
<?php if ($user && $ticket && $ticket['status'] === 'reserved') : ?>
<div id="qr-modal" class="qr-overlay" style="display:none" role="dialog">
    <div class="qr-dialog">
        <h2><?php echo e($event['title']); ?></h2>
        <p class="muted"><?php echo date('d/m/Y H:i', strtotime($event['event_date'])); ?> — <?php echo e($event['place']); ?></p>
        <div id="qr-code-canvas"></div>
        <p class="qr-code-text"><?php echo e($ticket['code']); ?></p>
        <p class="muted" style="font-size:.8rem">Présente ce QR code à l'entrée de l'événement</p>
        <button class="btn ghost" id="qr-close">Fermer</button>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous"></script>
<script>
// Génère le QR code à l'ouverture du modal
document.getElementById('qr-open-btn').addEventListener('click', function () {
    const canvas = document.getElementById('qr-code-canvas');
    if (canvas.childElementCount === 0) {
        new QRCode(canvas, {
            text: '<?php echo e($ticket['code']); ?>',
            width: 200, height: 200,
            colorDark: '#000', colorLight: '#fff',
            correctLevel: QRCode.CorrectLevel.H
        });
    }
});
</script>
<?php endif; ?>

<!-- Carte Leaflet -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function () {
    const place = <?php echo json_encode($event['place']); ?>;
    const mapEl = document.getElementById('event-map');
    if (!mapEl) return;

    // Géocodage avec Nominatim (OpenStreetMap)
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(place + ' France'), {
        headers: { 'Accept-Language': 'fr' }
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (!data.length) {
            mapEl.innerHTML = '<p class="muted map-place-label" style="padding:1.5rem;text-align:center">Carte non disponible.</p>';
            return;
        }
        const lat = parseFloat(data[0].lat);
        const lon = parseFloat(data[0].lon);
        const map = L.map('event-map', { scrollWheelZoom: false }).setView([lat, lon], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19
        }).addTo(map);

        const icon = L.divIcon({ className: 'map-marker', html: '<div class="map-pin"></div>', iconSize: [28, 28], iconAnchor: [14, 28] });
        L.marker([lat, lon], { icon }).addTo(map).bindPopup('<strong>' + place.replace(/</g, '&lt;') + '</strong>').openPopup();
    })
    .catch(function () {
        mapEl.innerHTML = '<p class="muted" style="padding:1.5rem;text-align:center">Impossible de charger la carte.</p>';
    });
})();
</script>

<?php require 'footer.php'; ?>
