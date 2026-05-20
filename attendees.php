<?php
require_once 'db.php';
$user = require_role(['organizer', 'admin']);
$eventId = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM events WHERE id = ?');
$stmt->execute([$eventId]);
$event = $stmt->fetch();
if (!$event || ($user['role'] !== 'admin' && (int)$event['organizer_id'] !== (int)$user['id'])) {
    flash('error', 'Accès impossible.');
    redirect('profil.php');
}

if (isset($_GET['present'])) {
    $stmt = db()->prepare("UPDATE tickets SET status = 'present' WHERE id = ? AND event_id = ?");
    $stmt->execute([(int)$_GET['present'], $eventId]);
    flash('success', 'Présence validée.');
    redirect('attendees.php?id=' . $eventId);
}

if (isset($_GET['give'])) {
    $waitlistId = (int)$_GET['give'];
    $stmt = db()->prepare("SELECT * FROM waitlist WHERE id = ? AND event_id = ?");
    $stmt->execute([$waitlistId, $eventId]);
    $w = $stmt->fetch();
    if ($w) {
        $code = strtoupper(bin2hex(random_bytes(4))) . '-' . $eventId . '-' . $w['user_id'];
        // Si un billet annulé existe déjà, on le réactive
        $existing = db()->prepare("SELECT id FROM tickets WHERE event_id = ? AND user_id = ?");
        $existing->execute([$eventId, $w['user_id']]);
        if ($existing->fetch()) {
            db()->prepare("UPDATE tickets SET status = 'reserved', code = ?, payment_status = 'free' WHERE event_id = ? AND user_id = ?")
                ->execute([$code, $eventId, $w['user_id']]);
        } else {
            db()->prepare("INSERT INTO tickets (event_id, user_id, code, payment_status) VALUES (?, ?, ?, 'free')")
                ->execute([$eventId, $w['user_id'], $code]);
        }
        db()->prepare("DELETE FROM waitlist WHERE id = ?")->execute([$waitlistId]);
        flash('success', 'Billet attribué manuellement.');
    }
    redirect('attendees.php?id=' . $eventId);
}

$stmt = db()->prepare("SELECT t.*, u.name, u.email FROM tickets t JOIN users u ON u.id = t.user_id WHERE t.event_id = ? ORDER BY t.created_at");
$stmt->execute([$eventId]);
$attendees = $stmt->fetchAll();

$wStmt = db()->prepare("SELECT w.*, u.name, u.email FROM waitlist w JOIN users u ON u.id = w.user_id WHERE w.event_id = ? ORDER BY w.created_at");
$wStmt->execute([$eventId]);
$waitlisted = $wStmt->fetchAll();

$pageTitle = 'Inscrits — ' . $event['title'];
require 'header.php';
?>
<main class="shell">
    <section class="panel">
        <p class="eyebrow">Tableau de bord</p>
        <h1>Inscrits — <?php echo e($event['title']); ?></h1>

        <div class="scanner-zone">
            <h2>Scanner un billet</h2>
            <p class="muted">Pointez la caméra vers le QR code du participant pour valider sa présence.</p>
            <div class="scanner-actions">
                <button class="btn primary" id="start-scan">Démarrer le scanner</button>
                <button class="btn ghost is-hidden" id="stop-scan">Arrêter</button>
            </div>
            <div id="scanner-container" class="is-hidden">
                <div id="qr-reader"></div>
            </div>
            <div id="scan-result" class="scan-result is-hidden"></div>
        </div>

        <div class="table-wrap" style="margin-top:1.5rem">
            <table>
                <thead>
                    <tr><th>Nom</th><th>Email</th><th>Code billet</th><th>Statut</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php foreach ($attendees as $attendee) : ?>
                    <tr>
                        <td><?php echo e($attendee['name']); ?></td>
                        <td><?php echo e($attendee['email']); ?></td>
                        <td class="ticket-code-cell"><?php echo e($attendee['code']); ?></td>
                        <td>
                            <?php if ($attendee['status'] === 'present') : ?>
                                <span class="status-present">Présent</span>
                            <?php elseif ($attendee['status'] === 'cancelled') : ?>
                                <span class="status-cancelled">Annulé</span>
                            <?php else : ?>
                                <span class="status-reserved">Réservé</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($attendee['status'] === 'reserved') : ?>
                                <a href="attendees.php?id=<?php echo $eventId; ?>&present=<?php echo (int)$attendee['id']; ?>">Valider</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$attendees) : ?>
                    <tr><td colspan="5" class="muted">Aucun inscrit.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($waitlisted) : ?>
    <section class="panel">
        <h2>File d'attente (<?php echo count($waitlisted); ?>)</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Nom</th><th>Email</th><th>Statut</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($waitlisted as $i => $w) : ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo e($w['name']); ?></td>
                        <td><?php echo e($w['email']); ?></td>
                        <td><?php echo $w['status'] === 'promoted' ? '<span class="status-promoted">Notifiée</span>' : 'En attente'; ?></td>
                        <td>
                            <?php if ($w['status'] === 'waiting') : ?>
                                <a class="btn primary btn-sm"
                                   href="attendees.php?id=<?php echo $eventId; ?>&give=<?php echo (int)$w['id']; ?>"
                                   data-confirm="Attribuer un billet à <?php echo e($w['name']); ?> ?">
                                    Donner un billet
                                </a>
                            <?php else : ?>
                                <span class="muted" style="font-size:.8rem">En attente de paiement</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
</main>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
(function () {
    const eventId   = <?php echo (int)$eventId; ?>;
    const startBtn  = document.getElementById('start-scan');
    const stopBtn   = document.getElementById('stop-scan');
    const container = document.getElementById('scanner-container');
    const resultDiv = document.getElementById('scan-result');
    let html5QrCode = null;
    let scanning    = false;

    function showResult(ok, msg) {
        resultDiv.classList.remove('is-hidden');
        resultDiv.className = 'scan-result ' + (ok ? 'scan-ok' : 'scan-err');
        resultDiv.textContent = msg;
        if (ok) setTimeout(() => location.reload(), 1800);
    }

    async function onScanSuccess(code) {
        if (!scanning) return;
        try { await html5QrCode.pause(true); } catch(e) {}
        try {
            const fd = new FormData();
            fd.append('code', code);
            fd.append('event_id', eventId);
            const res  = await fetch('scan_ticket.php', { method: 'POST', body: fd });
            const data = await res.json();
            showResult(data.ok, data.msg);
            if (!data.ok) setTimeout(async () => { try { await html5QrCode.resume(); } catch(e) {} }, 2000);
        } catch(e) {
            showResult(false, 'Erreur réseau.');
            setTimeout(async () => { try { await html5QrCode.resume(); } catch(e) {} }, 2000);
        }
    }

    startBtn.addEventListener('click', async () => {
        container.classList.remove('is-hidden');
        startBtn.style.display = 'none';
        stopBtn.classList.remove('is-hidden');
        resultDiv.classList.add('is-hidden');
        scanning = true;
        html5QrCode = new Html5Qrcode('qr-reader');
        try {
            await html5QrCode.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 250, height: 250 } }, onScanSuccess);
        } catch(e) {
            try {
                await html5QrCode.start({ facingMode: 'user' }, { fps: 10, qrbox: { width: 250, height: 250 } }, onScanSuccess);
            } catch(e2) {
                showResult(false, 'Impossible d\'accéder à la caméra.');
                container.classList.add('is-hidden');
                startBtn.style.display = '';
                stopBtn.classList.add('is-hidden');
                scanning = false;
            }
        }
    });

    stopBtn.addEventListener('click', async () => {
        scanning = false;
        if (html5QrCode) { try { await html5QrCode.stop(); } catch(e) {} html5QrCode = null; }
        container.classList.add('is-hidden');
        startBtn.style.display = '';
        stopBtn.classList.add('is-hidden');
    });
})();
</script>
<?php require 'footer.php'; ?>
