<?php
// process_reset_password_eleve.php — traitement du nouveau mot de passe

declare(strict_types=1);

require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page(); // TO DO: adapter à un bootstrap élève dédié si besoin
require_once __DIR__ . '/../CVT/_db.php';

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

$pdo = pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

$token = trim((string)($_POST['token'] ?? ''));
$password = trim((string)($_POST['password'] ?? ''));
$passwordConfirmation = trim((string)($_POST['password_confirmation'] ?? ''));

if ($token === '') {
    $error = 'Token manquant.';
} else {
    $tokenHash = hash('sha256', $token);

    $sql = "
        SELECT TOP 1
            id,
            reset_token_hash,
            reset_token_expires_at
        FROM dbo.AudraWeb_Eleve_Acces
        WHERE reset_token_hash = ?
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$tokenHash]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error = 'Token introuvable.';
    } elseif (
        empty($user['reset_token_expires_at']) ||
        strtotime((string)$user['reset_token_expires_at']) <= time()
    ) {
        $error = 'Le lien de réinitialisation a expiré.';
    } elseif (strlen($password) < 12) {
        $error = 'Le mot de passe doit contenir au moins 12 caractères.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Le mot de passe doit contenir au moins une majuscule.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Le mot de passe doit contenir au moins une minuscule.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Le mot de passe doit contenir au moins un chiffre.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Le mot de passe doit contenir au moins un caractère spécial.';
    } elseif ($password !== $passwordConfirmation) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $sqlUpdate = "
            UPDATE dbo.AudraWeb_Eleve_Acces
            SET
                password_hash = ?,
                reset_token_hash = NULL,
                reset_token_expires_at = NULL
            WHERE id = ?
        ";
        $stUpdate = $pdo->prepare($sqlUpdate);
        $stUpdate->execute([
            $passwordHash,
            (int)$user['id']
        ]);

        $success = 'Mot de passe mis à jour. Vous pouvez maintenant vous connecter.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Portail Élève — Réinitialisation terminée</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./assets/css/portail_eleve.css?v=6">
</head>
<body>
    <div class="login-container">
        <img src="./assets/photos/audralangues-1.png" alt="Logo Audra Langues">
        <h1>Réinitialisation du mot de passe</h1>

        <?php if ($error !== ''): ?>
            <p style="color:#b91c1c; font-weight:600; margin-bottom:12px;">
                <?= h($error) ?>
            </p>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <p style="color:#065f46; font-weight:600; margin-bottom:12px;">
                <?= h($success) ?>
            </p>
        <?php endif; ?>

        <div class="login-links">
            <a href="portail_eleve.php">Retour à la connexion</a>
        </div>
    </div>
</body>
</html>