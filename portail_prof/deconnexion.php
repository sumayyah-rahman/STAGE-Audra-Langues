<?php
// deconnexion.php — Déconnexion propre (sans envoi de mails)

declare(strict_types=1);

@file_put_contents('C:/data/audra/logs/send_mail.log', date('c') . " CALLED (deconnexion)\n", FILE_APPEND);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Vide les variables de session
$_SESSION = [];
session_unset();

// Supprime le cookie de session (si utilisé)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Détruit la session
session_destroy();

// Redirection
header('Location: portail_prof.php');
exit;
