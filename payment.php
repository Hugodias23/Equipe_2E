<?php
require_once 'db.php';
$user = require_role(['participant', 'organizer', 'admin']);
$eventId = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare("SELECT e.*, u.name AS organizer_name,
    (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'reserved') AS reserved_count
    FROM events e JOIN users u ON u.id = e.organizer_id WHERE e.id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event || $event['status'] !== 'published') {
    flash('error', 'Événement indisponible.');
    redirect('index.php');
}
if ((float)$event['price'] <= 0) {
    redirect('reserve.php?id=' . $eventId);
}

$remaining = max(0, (int)$event['capacity'] - (int)$event['reserved_count']);

$wStmt = db()->prepare("SELECT id, status FROM waitlist WHERE event_id = ? AND user_id = ?");
$wStmt->execute([$eventId, $user['id']]);
$waitlistEntry = $wStmt->fetch();
$isPromoted = $waitlistEntry && $waitlistEntry['status'] === 'promoted';

if ($remaining <= 0 && !$isPromoted && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $wInsert = db()->prepare("INSERT IGNORE INTO waitlist (event_id, user_id) VALUES (?, ?)");
    $wInsert->execute([$eventId, $user['id']]);
    flash('success', 'Événement complet. Tu as été ajouté à la liste d\'attente.');
    redirect('event_detail.php?id=' . $eventId);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cardName   = trim($_POST['card_name']   ?? '');
    $cardNumber = preg_replace('/\D+/', '', $_POST['card_number'] ?? '');
    $expiry     = trim($_POST['expiry']      ?? '');
    $cvc        = preg_replace('/\D+/', '', $_POST['cvc'] ?? '');

    if ($cardName === '' || strlen($cardNumber) < 12 || !preg_match('/^\d{2}\/\d{2}$/', $expiry) || strlen($cvc) < 3) {
        $error = 'Vérifiez les informations de paiement.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT capacity, status, price,
                (SELECT COUNT(*) FROM tickets WHERE event_id = events.id AND status = 'reserved') AS reserved_count
                FROM events WHERE id = ? FOR UPDATE");
            $stmt->execute([$eventId]);
            $lockedEvent = $stmt->fetch();

            if (!$lockedEvent || $lockedEvent['status'] !== 'published') {
                throw new RuntimeException('Événement indisponible.');
            }
            $wCheck = $pdo->prepare("SELECT id, status FROM waitlist WHERE event_id = ? AND user_id = ?");
            $wCheck->execute([$eventId, $user['id']]);
            $wEntry = $wCheck->fetch();
            $promoted = $wEntry && $wEntry['status'] === 'promoted';
            if ((int)$lockedEvent['reserved_count'] >= (int)$lockedEvent['capacity'] && !$promoted) {
                throw new RuntimeException('Événement complet.');
            }

            $reference = 'PAY-' . strtoupper(bin2hex(random_bytes(5)));
            $code      = strtoupper(bin2hex(random_bytes(4))) . '-' . $eventId . '-' . $user['id'];
            $stmt = $pdo->prepare('INSERT INTO tickets (event_id, user_id, code, payment_status, payment_reference) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$eventId, $user['id'], $code, 'paid', $reference]);
            $ticketId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO payments (event_id, user_id, ticket_id, amount, reference, status) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$eventId, $user['id'], $ticketId, $lockedEvent['price'], $reference, 'paid']);

            if (!empty($wEntry)) {
                $pdo->prepare("DELETE FROM waitlist WHERE id = ?")->execute([$wEntry['id']]);
            }

            $pdo->commit();
            flash('success', 'Paiement accepté. Ton billet est réservé.');
            redirect('event_detail.php?id=' . $eventId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = $e instanceof PDOException ? 'Tu as déjà un billet pour cet événement.' : $e->getMessage();
        }
    }
}

$pageTitle = 'Paiement';
require 'header.php';
?>
<main class="shell payment-layout">
    <section class="panel payment-summary">
        <p class="eyebrow">Paiement sécurisé (démo)</p>
        <h1><?php echo e($event['title']); ?></h1>
        <p class="lead"><?php echo e($event['place']); ?> — <?php echo date('d/m/Y H:i', strtotime($event['event_date'])); ?></p>
        <dl class="info-list">
            <div><dt>Prix</dt><dd><?php echo number_format((float)$event['price'], 2, ',', ' '); ?> €</dd></div>
            <div><dt>Places restantes</dt><dd><?php echo $remaining; ?></dd></div>
            <div><dt>Association</dt><dd><?php echo e($event['association']); ?></dd></div>
            <div><dt>Organisateur</dt><dd><?php echo e($event['organizer_name']); ?></dd></div>
        </dl>
    </section>
    <form class="panel payment-card" method="post">
        <h2>Carte bancaire</h2>
        <?php if ($error) : ?><p class="form-error"><?php echo e($error); ?></p><?php endif; ?>
        <label>Nom sur la carte<input type="text" name="card_name" required autocomplete="cc-name"></label>
        <label>Numéro de carte<input type="text" name="card_number" inputmode="numeric" minlength="12" maxlength="19" placeholder="4242 4242 4242 4242" required autocomplete="cc-number"></label>
        <div class="form-grid">
            <label>Expiration<input type="text" name="expiry" placeholder="MM/AA" pattern="\d{2}/\d{2}" required autocomplete="cc-exp"></label>
            <label>CVC<input type="text" name="cvc" inputmode="numeric" minlength="3" maxlength="4" required autocomplete="cc-csc"></label>
        </div>
        <button class="btn primary" type="submit">Payer <?php echo number_format((float)$event['price'], 2, ',', ' '); ?> €</button>
        <p class="muted">Mode démo : aucune carte réelle n'est débitée.</p>
    </form>
</main>
<?php require 'footer.php'; ?>
