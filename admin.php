<?php
require_once 'db.php';
$user = require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['pending', 'active', 'blocked'], true) ? $_POST['status'] : 'active';
        $association = trim($_POST['association'] ?? '');
        $password = trim($_POST['new_password'] ?? '');
        $association = $association === '' ? null : $association;

        if ($name !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                if ($password !== '') {
                    if (strlen($password) < 6) {
                        flash('error', 'Le nouveau mot de passe doit contenir au moins 6 caracteres.');
                        redirect('admin.php#users');
                    }
                    $stmt = db()->prepare("UPDATE users SET name = ?, email = ?, role = 'organizer', status = ?, association = ?, password_hash = ? WHERE id = ? AND role = 'organizer'");
                    $stmt->execute([$name, $email, $status, $association, password_hash($password, PASSWORD_DEFAULT), $userId]);
                    flash('success', 'Compte association mis a jour. Nouveau mot de passe : ' . $password);
                } else {
                    $stmt = db()->prepare("UPDATE users SET name = ?, email = ?, role = 'organizer', status = ?, association = ? WHERE id = ? AND role = 'organizer'");
                    $stmt->execute([$name, $email, $status, $association, $userId]);
                    flash('success', 'Compte association mis a jour.');
                }
            } catch (PDOException $e) {
                flash('error', 'Email deja utilise par un autre compte.');
            }
        } else {
            flash('error', 'Nom ou email invalide.');
        }
        redirect('admin.php#users');
    }

    if ($action === 'create_association') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $association = trim($_POST['association'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($name === '' || $association === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            flash('error', 'Complete le compte association avec un mot de passe de 6 caracteres minimum.');
            redirect('admin.php#users');
        }

        try {
            $stmt = db()->prepare("INSERT INTO users (name, email, password_hash, role, status, association) VALUES (?, ?, ?, 'organizer', 'active', ?)");
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $association]);
            flash('success', 'Association creee. Identifiants : ' . $email . ' / ' . $password);
        } catch (PDOException $e) {
            flash('error', 'Cet email existe deja.');
        }
        redirect('admin.php#users');
    }

    if ($action === 'update_event') {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        $association = trim($_POST['association'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['published', 'cancelled'], true) ? $_POST['status'] : 'published';
        $price = max(0, (float) str_replace(',', '.', $_POST['price'] ?? '0'));
        $capacity = max(1, (int) ($_POST['capacity'] ?? 1));

        if ($association !== '') {
            $stmt = db()->prepare('UPDATE events SET association = ?, status = ?, price = ?, capacity = ? WHERE id = ?');
            $stmt->execute([$association, $status, $price, $capacity, $eventId]);
            $promoted = promote_waitlist($eventId);
            $msg = 'Événement mis à jour.';
            if ($promoted > 0) {
                $msg .= ' ' . $promoted . ' personne' . ($promoted > 1 ? 's ont été promues' : ' a été promue') . ' depuis la liste d\'attente.';
            }
            flash('success', $msg);
        } else {
            flash('error', 'Association obligatoire pour un événement.');
        }
        redirect('admin.php#events');
    }
}

if (isset($_GET['approve'])) {
    $stmt = db()->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'organizer'");
    $stmt->execute([(int) $_GET['approve']]);
    flash('success', 'Organisateur valide.');
    redirect('admin.php#users');
}
if (isset($_GET['delete_event'])) {
    redirect('delete_event.php?id=' . (int) $_GET['delete_event']);
}

if (isset($_GET['ban']) || isset($_GET['unban'])) {
    $targetId = (int) ($_GET['ban'] ?? $_GET['unban'] ?? 0);
    $newStatus = isset($_GET['ban']) ? 'blocked' : 'active';
    if ($targetId && $targetId !== (int) $user['id']) {
        db()->prepare("UPDATE users SET status = ? WHERE id = ? AND role != 'admin'")->execute([$newStatus, $targetId]);
        flash('success', $newStatus === 'blocked' ? 'Utilisateur banni.' : 'Utilisateur debloque.');
    }
    redirect('admin.php#all-users');
}

$allUsers = db()->query("SELECT u.id, u.name, u.email, u.role, u.status, u.avatar, u.association,
    (SELECT COUNT(*) FROM tickets t WHERE t.user_id = u.id AND t.status != 'cancelled') AS ticket_count
    FROM users u WHERE u.role != 'admin' ORDER BY u.role, u.name")->fetchAll();

$stats = [
    'organizers' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'organizer'")->fetchColumn(),
    'active_organizers' => (int) db()->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'events' => (int) db()->query('SELECT COUNT(*) FROM events')->fetchColumn(),
    'tickets' => (int) db()->query("SELECT COUNT(*) FROM tickets WHERE status = 'reserved'")->fetchColumn(),
];

$users = db()->query("SELECT u.*,
    (SELECT COUNT(*) FROM events e WHERE e.organizer_id = u.id) AS event_count
    FROM users u WHERE u.role = 'organizer' ORDER BY u.association, u.id")->fetchAll();

$events = db()->query("SELECT e.*, u.id AS organizer_user_id, u.name AS organizer_name, u.email AS organizer_email,
    (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'reserved') AS reserved_count
    FROM events e JOIN users u ON u.id = e.organizer_id ORDER BY e.event_date DESC")->fetchAll();

$pageTitle = 'Dashboard admin';
require 'header.php';
?>
<main class="shell dashboard admin-dashboard">
  <section class="panel profile-head">
    <div>
      <p class="eyebrow">Super admin</p>
      <h1>Dashboard du site</h1>
      <p class="muted">Gestion des associations, evenements et reservations du site.</p>
    </div>
    <a class="btn primary" href="create_event.php">Créer un événement</a>
  </section>

  <section class="admin-stats">
    <article class="panel stat-card"><span>Comptes association</span><strong><?php echo $stats['organizers']; ?></strong></article>
    <article class="panel stat-card"><span>Utilisateurs inscrits</span><strong><?php echo $stats['active_organizers']; ?></strong></article>
    <article class="panel stat-card"><span>Evenements</span><strong><?php echo $stats['events']; ?></strong></article>
    <article class="panel stat-card"><span>Billets reserves</span><strong><?php echo $stats['tickets']; ?></strong></article>
  </section>

  <section id="users" class="panel">
    <h2>Comptes association et identifiants</h2>
    <form class="admin-create-form" method="post">
      <input type="hidden" name="action" value="create_association">
      <label>Nom du gerant<input type="text" name="name" placeholder="Responsable BDE" required></label>
      <label>Email de connexion<input type="email" name="email" placeholder="bde@omnes.fr" required></label>
      <label>Association<input type="text" name="association" placeholder="BDE" required></label>
      <label>Mot de passe<input type="password" name="password" minlength="6" placeholder="ex: bde2026" required></label>
      <button class="btn primary" type="submit">Creer l'asso</button>
    </form>
    <p class="muted password-note">Les mots de passe deja enregistres sont proteges par hash. Le super admin peut les reinitialiser ici, puis donner le nouveau mot de passe a l'association.</p>
    <div class="admin-records">
      <?php foreach ($users as $account) : ?>
        <form class="admin-record" method="post">
          <input type="hidden" name="action" value="update_user">
          <input type="hidden" name="user_id" value="<?php echo (int) $account['id']; ?>">
          <div class="record-id">#<?php echo (int) $account['id']; ?></div>
          <label>Nom<input type="text" name="name" value="<?php echo e($account['name']); ?>" required></label>
          <label>Email<input type="email" name="email" value="<?php echo e($account['email']); ?>" required></label>
          <label>Statut
            <select name="status">
              <?php foreach (['active' => 'Actif', 'pending' => 'En attente', 'blocked' => 'Bloque'] as $value => $label) : ?>
                <option value="<?php echo e($value); ?>" <?php echo $account['status'] === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Association<input type="text" name="association" value="<?php echo e($account['association']); ?>" placeholder="BDE, BDS..."></label>
          <label>Nouveau mot de passe<input type="password" name="new_password" minlength="6" placeholder="laisser vide"></label>
          <div class="record-meta">
            <strong><?php echo (int) $account['event_count']; ?></strong>
            <span>event(s)</span>
          </div>
          <button class="btn primary" type="submit">Sauver</button>
          <?php if ($account['role'] === 'organizer' && $account['status'] === 'pending') : ?>
            <a class="btn ghost" href="admin.php?approve=<?php echo (int) $account['id']; ?>">Valider</a>
          <?php endif; ?>
        </form>
      <?php endforeach; ?>
    </div>
  </section>

  <section id="events" class="panel">
    <h2>Evenements et association creatrice</h2>
    <?php foreach ($events as $event) : ?>
      <form id="event-form-<?php echo (int) $event['id']; ?>" method="post"></form>
    <?php endforeach; ?>
    <div class="table-wrap">
      <table class="admin-events-table">
        <thead>
          <tr>
            <th>ID event</th>
            <th>Evenement</th>
            <th>Ajoute par</th>
            <th>Association</th>
            <th>Date</th>
            <th>Prix</th>
            <th>Capacite</th>
            <th>Statut</th>
            <th>Inscrits</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $event) : ?>
            <?php $eventFormId = 'event-form-' . (int) $event['id']; ?>
            <tr>
                <td>
                  <input form="<?php echo e($eventFormId); ?>" type="hidden" name="action" value="update_event">
                  <input form="<?php echo e($eventFormId); ?>" type="hidden" name="event_id" value="<?php echo (int) $event['id']; ?>">
                  #<?php echo (int) $event['id']; ?>
                </td>
                <td><strong><a href="event_detail.php?id=<?php echo (int)$event['id']; ?>"><?php echo e($event['title']); ?></a></strong></td>
                <td>
                  #<?php echo (int) $event['organizer_user_id']; ?> - <?php echo e($event['organizer_name']); ?><br>
                  <span class="muted"><?php echo e($event['organizer_email']); ?></span>
                </td>
                <td><input form="<?php echo e($eventFormId); ?>" type="text" name="association" value="<?php echo e($event['association']); ?>" required></td>
                <td><?php echo date('d/m/Y H:i', strtotime($event['event_date'])); ?></td>
                <td><input form="<?php echo e($eventFormId); ?>" type="number" name="price" min="0" step="0.01" value="<?php echo e(number_format((float) $event['price'], 2, '.', '')); ?>"></td>
                <td><input form="<?php echo e($eventFormId); ?>" type="number" name="capacity" min="1" value="<?php echo (int) $event['capacity']; ?>"></td>
                <td>
                  <select form="<?php echo e($eventFormId); ?>" name="status">
                    <option value="published" <?php echo $event['status'] === 'published' ? 'selected' : ''; ?>>Publie</option>
                    <option value="cancelled" <?php echo $event['status'] === 'cancelled' ? 'selected' : ''; ?>>Annule</option>
                  </select>
                </td>
                <td><?php echo (int) $event['reserved_count']; ?> / <?php echo (int) $event['capacity']; ?></td>
                <td class="table-actions">
                  <button form="<?php echo e($eventFormId); ?>" class="btn primary" type="submit">Sauver</button>
                  <a class="btn ghost" href="edit_event.php?id=<?php echo (int)$event['id']; ?>">Modifier</a>
                  <a class="btn ghost" href="attendees.php?id=<?php echo (int) $event['id']; ?>">Inscrits</a>
                  <a class="btn ghost" href="admin.php?delete_event=<?php echo (int) $event['id']; ?>" data-confirm="Supprimer cet evenement ?">Supprimer</a>
                </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$events) : ?><tr><td colspan="10">Aucun evenement.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section id="all-users" class="panel">
    <h2>Tous les utilisateurs</h2>
    <p class="muted" style="margin-bottom:1rem;font-size:.85rem"><?php echo count($allUsers); ?> comptes (hors admins)</p>
    <div class="table-wrap">
      <table class="admin-events-table" style="min-width:680px">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Email</th>
            <th>Role</th>
            <th>Statut</th>
            <th>Billets</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="all-users-tbody">
          <?php foreach ($allUsers as $u) : ?>
            <tr id="user-row-<?php echo (int) $u['id']; ?>">
              <td>#<?php echo (int) $u['id']; ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:.6rem">
                  <?php if ($u['avatar']) : ?>
                    <img src="<?php echo e($u['avatar']); ?>" alt="" width="32" height="32" style="border-radius:50%;object-fit:cover;flex-shrink:0">
                  <?php else : ?>
                    <img src="images/default-avatar.svg" alt="" width="32" height="32" style="border-radius:50%;flex-shrink:0">
                  <?php endif; ?>
                  <div>
                    <strong><?php echo e($u['name']); ?></strong>
                    <?php if ($u['association']) : ?><br><span class="muted" style="font-size:.78rem"><?php echo e($u['association']); ?></span><?php endif; ?>
                  </div>
                </div>
              </td>
              <td><span class="muted" style="font-size:.85rem"><?php echo e($u['email']); ?></span></td>
              <td><?php echo e($u['role']); ?></td>
              <td>
                <span class="ban-status-badge <?php echo $u['status'] === 'blocked' ? 'badge-banned' : 'badge-active'; ?>"
                      id="status-badge-<?php echo (int) $u['id']; ?>">
                  <?php echo $u['status'] === 'blocked' ? 'Banni' : ucfirst($u['status']); ?>
                </span>
              </td>
              <td><?php echo (int) $u['ticket_count']; ?></td>
              <td class="table-actions">
                <?php if ($u['status'] === 'blocked') : ?>
                    <a class="btn primary btn-sm" href="admin.php?unban=<?php echo (int)$u['id']; ?>">Débannir</a>
                <?php else : ?>
                    <a class="btn btn-sm btn-danger" href="admin.php?ban=<?php echo (int)$u['id']; ?>"
                       data-confirm="Bannir cet utilisateur ?">Bannir</a>
                <?php endif; ?>
                <a class="btn ghost btn-sm" href="profil.php">Voir</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$allUsers) : ?><tr><td colspan="7">Aucun utilisateur.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php require 'footer.php'; ?>
