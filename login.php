<?php
require_once 'db.php';

// Déjà connecté → redirection
if (current_user()) redirect('profil.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $account = $stmt->fetch();

    if ($account && password_verify($password, $account['password_hash'])) {
        login_user($account);
        redirect('profil.php');
    }

    $error = 'Email ou mot de passe incorrect.';
}

$pageTitle = 'Connexion';
require 'header.php';
?>

<main class="auth-layout">
    <form class="panel auth-card" method="post">
        <p class="eyebrow">Bon retour</p>
        <h1>Connexion</h1>

        <?php if ($error) : ?>
            <p class="form-error"><?php echo e($error); ?></p>
        <?php endif; ?>

        <label>
            Email
            <input type="email" name="email" required autocomplete="email">
        </label>
        <label>
            Mot de passe
            <input type="password" name="password" required autocomplete="current-password">
        </label>

        <button class="btn primary" type="submit">Se connecter</button>

        <p class="muted">
            Pas encore de compte ? <a href="inscription.php">S'inscrire</a>
        </p>
        <p class="muted" style="font-size:.75rem">
            Démo : admin@omnes.fr / admin123 · bde@omnes.fr / orga123 · etu@omnes.fr / etu123
        </p>
    </form>
</main>

<?php require 'footer.php'; ?>
