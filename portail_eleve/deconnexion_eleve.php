<?php
// deconnexion_eleve.php — déconnexion de l'élève

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Supprime toutes les variables de session
$_SESSION = [];

// Supprime le cookie de session si nécessaire
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Détruit la session
session_destroy();

// Redirection vers la page de connexion élève
header('Location: portail_eleve.php');
exit;