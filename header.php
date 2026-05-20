<?php
// En-tête commun à toutes les pages
$user       = current_user();
$pageTitle  = $pageTitle ?? 'OmnesEvent';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> – OmnesEvent</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=6">
    <?php if (!empty($headExtra)) echo $headExtra; ?>
</head>
<body>

<header class="topbar">
    <a class="brand" href="index.php" aria-label="Accueil OmnesEvent">
        <img class="brand-logo" src="images/omnesevent-logo.webp" alt="OmnesEvent" width="68" height="68">
        <span>
            <strong>OmnesEvent</strong>
            <small>Vie associative Omnes</small>
        </span>
    </a>

    <button class="menu-toggle" id="menu-toggle" aria-expanded="false" aria-controls="main-nav">☰</button>

    <nav id="main-nav" class="nav">
        <a href="index.php">Événements</a>
        <a href="calendar.php">Calendrier</a>
        <a href="explore.php">Explorer</a>
        <?php if ($user && in_array($user['role'], ['organizer', 'admin'])) : ?>
            <a href="create_event.php">Créer</a>
        <?php endif; ?>
        <?php if ($user) : ?>
            <a href="profil.php">
                <?php if ($user['avatar']) : ?>
                    <img src="<?php echo e($user['avatar']); ?>" class="nav-avatar" alt="" width="28" height="28">
                <?php endif; ?>
                Mon profil
            </a>
            <a href="mes_billets.php">Mes billets</a>
            <?php if ($user['role'] === 'admin') : ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <a class="nav-pill" href="logout.php">Déconnexion</a>
        <?php else : ?>
            <a href="login.php">Connexion</a>
            <a class="nav-pill" href="inscription.php">Inscription</a>
        <?php endif; ?>
    </nav>
</header>

<?php foreach (get_flashes() as $flash) : ?>
    <div class="flash flash-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
<?php endforeach; ?>
