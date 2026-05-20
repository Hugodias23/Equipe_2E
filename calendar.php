<?php
require_once 'db.php';
$viewer = current_user();

$stmt = db()->query("SELECT id, title, description, event_date, place, category, association, price
    FROM events WHERE status = 'published' ORDER BY event_date ASC");
$events = $stmt->fetchAll();
$calendarEvents = array_map(static function (array $event): array {
    return [
        'id'          => (int)$event['id'],
        'title'       => $event['title'],
        'description' => mb_strimwidth($event['description'], 0, 95, '…'),
        'date'        => date('Y-m-d', strtotime($event['event_date'])),
        'time'        => date('H:i', strtotime($event['event_date'])),
        'place'       => $event['place'],
        'category'    => $event['category'],
        'association' => $event['association'],
        'price'       => (float)$event['price'],
    ];
}, $events);

$focusedEventId = (int)($_GET['event'] ?? 0);
$focusedDate    = null;
foreach ($calendarEvents as $calendarEvent) {
    if ($focusedEventId && $calendarEvent['id'] === $focusedEventId) {
        $focusedDate = $calendarEvent['date'];
        break;
    }
}

$personalEvents = [];
if ($viewer) {
    $pStmt = db()->prepare("SELECT id, title,
        DATE_FORMAT(event_date, '%Y-%m-%d') AS date,
        IF(event_time IS NULL, '', TIME_FORMAT(event_time, '%H:%i')) AS time
        FROM personal_events WHERE user_id = ? ORDER BY event_date ASC");
    $pStmt->execute([$viewer['id']]);
    $personalEvents = $pStmt->fetchAll();
}

$pageTitle = 'Calendrier';
require 'header.php';
?>
<main class="shell calendar-page">
    <section class="panel calendar-toolbar">
        <div>
            <p class="eyebrow">Programme interactif</p>
            <h1>Calendrier des événements</h1>
        </div>
        <div class="calendar-actions">
            <button class="btn ghost" type="button" data-calendar-prev>Précédent</button>
            <strong data-calendar-title></strong>
            <button class="btn ghost" type="button" data-calendar-next>Suivant</button>
        </div>
    </section>

    <section class="calendar-layout">
        <div class="panel calendar-grid" data-calendar-grid></div>
        <aside class="panel calendar-side">
            <h2 data-selected-date>Aujourd'hui</h2>
            <div class="calendar-event-list" data-calendar-list></div>
            <?php if ($viewer) : ?>
            <form class="personal-event-form" id="personal-event-form">
                <p class="eyebrow" style="margin-bottom:.4rem;font-size:.7rem">Ajouter un événement perso</p>
                <input type="text" id="personal-title" placeholder="Mon anniversaire…" maxlength="200" autocomplete="off" required>
                <input type="time" id="personal-time">
                <button class="btn primary btn-sm" type="submit">Ajouter</button>
            </form>
            <?php endif; ?>
        </aside>
    </section>
</main>
<script>
window.calendarEvents       = <?php echo json_encode($calendarEvents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.personalEvents       = <?php echo json_encode(array_values($personalEvents), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.isLoggedIn           = <?php echo $viewer ? 'true' : 'false'; ?>;
window.initialCalendarDate  = <?php echo json_encode($focusedDate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<?php require 'footer.php'; ?>
