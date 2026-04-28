<?php
// mdp_oublie_eleve.php — demande de réinitialisation du mot de passe élève

declare(strict_types=1);

require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page(); // TODO: adapter à un bootstrap élève dédié si besoin

if (($config['env'] ?? 'DEV') === 'DEV') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!empty($_SESSION['student_logged']) && $_SESSION['student_logged'] === true) {
    header('Location: dashboard_eleve.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Portail Élève — Mot de passe oublié</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./assets/css/portail_eleve.css?v=6">
</head>
<body>
    <div class="login-container">
        <img src="./assets/photos/audralangues-1.png" alt="Logo Audra Langues">
        <h1>Mot de passe oublié</h1>

        <form method="post" action="send_password_reset_eleve.php">
            <label for="email">Adresse mail</label>
            <input type="email" name="email" id="email" required>

            <button type="submit">Envoyer le lien</button>
        </form>

        <div class="login-links">
            <a href="portail_eleve.php">Retour à la connexion</a>
        </div>
    </div>
</body>
</html>