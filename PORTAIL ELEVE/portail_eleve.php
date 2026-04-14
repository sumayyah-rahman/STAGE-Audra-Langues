<?php
// portail_eleve.php — page de connexion de l'élève

declare(strict_types=1);

require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page(); // adapter à un autre bootstrap s'il en existe un 

if (($config['env'] ?? 'DEV') === 'DEV') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
}

// Ici : logique élève
// ex. vérifier si l’élève est connecté
// sinon redirection vers login élève
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Portail Elève — Connexion</title>
        <link rel="stylesheet" href="./assets/css/portail_eleve.css">
        <meta name="viewport" content="width=device-width, initial-scale=1">    
    </head>
    <body>
        <div class="login-container">
            <img src="./assets/photos/audralangues-1.png" alt="Logo Audra Langues">
            <h1>Connexion</h1>
            <form id="loginForm">
                <label for="login">Adresse email</label>
                <input type="text" id="login" name="login" required>
                <label for="password">Mot de passe</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required>
                    <span class="toggle-eye" onclick="togglePassword()">👁</span>
                </div>
                <button type="submit">Se connecter</button>
            </form>
        </div>
        <script src="./assets/js/script.js"></script>
</body>
</html>
