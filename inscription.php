<?php
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']        ?? '');
    $email       = trim($_POST['email']       ?? '');
    $password    = $_POST['password']    ?? '';
    $role        = ($_POST['role'] ?? 'participant') === 'organizer' ? 'organizer' : 'participant';
    $association = ($role === 'organizer') ? trim($_POST['association'] ?? '') : null;
    $status      = ($role === 'organizer') ? 'pending' : 'active';

    // Validation simple
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        $error = 'Remplis tous les champs avec un mot de passe d\'au moins 6 caractères.';
    } else {
        try {
            $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, role, status, association) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $status, $association]);

            if ($role === 'organizer') {
                flash('success', 'Compte organisateur envoyé à validation. Un admin doit l\'approuver.');
            } else {
                flash('success', 'Compte créé ! Tu peux maintenant te connecter.');
            }
            redirect('login.php');

        } catch (PDOException $e) {
            $error = 'Cet email est déjà utilisé.';
        }
    }
}

$pageTitle = 'Inscription';
require 'header.php';
?>

<main class="auth-layout">
    <form class="panel auth-card" method="post">
        <p class="eyebrow">Rejoindre OmnesEvent</p>
        <h1>Inscription</h1>

        <?php if ($error) : ?>
            <p class="form-error"><?php echo e($error); ?></p>
        <?php endif; ?>

        <label>
            Nom complet
            <input type="text" name="name" required autocomplete="name">
        </label>
        <label>
            Email Omnes
            <input type="email" name="email" required autocomplete="email">
        </label>
        <label>
            Mot de passe <small class="muted">(min. 6 caractères)</small>
            <input type="password" name="password" minlength="6" required autocomplete="new-password">
        </label>
        <label>
            Type de compte
            <select name="role" id="role-select">
                <option value="participant">Participant (étudiant)</option>
                <option value="organizer">Organisateur (association)</option>
            </select>
        </label>
        <label class="association-field">
            Nom de l'association
            <input type="text" name="association" placeholder="BDE, BDS, Junior Entreprise…">
        </label>

        <button class="btn primary" type="submit">Créer mon compte</button>

        <p class="muted">Déjà inscrit ? <a href="login.php">Se connecter</a></p>
    </form>
</main>

<?php require 'footer.php'; ?>
