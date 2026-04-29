<?php
// reset_password_eleve.php — formulaire de réinitialisation

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
$token = trim((string)($_GET['token'] ?? ''));

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
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Portail Élève — Nouveau mot de passe</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./assets/css/portail_eleve.css?v=6">
</head>
<body>
    <div class="login-container">
        <img src="./assets/photos/audralangues-1.png" alt="Logo Audra Langues">
        <h1>Nouveau mot de passe</h1>

        <?php if ($error !== ''): ?>
            <p style="color:#b91c1c; font-weight:600; margin-bottom:12px;">
                <?= h($error) ?>
            </p>

            <div class="login-links">
                <a href="portail_eleve.php">Retour à la connexion</a>
            </div>
        <?php else: ?>
            <form method="post" action="process_reset_password_eleve.php">
                <input type="hidden" name="token" value="<?= h($token) ?>">

                <label for="password">Nouveau mot de passe</label>
				<div class="password-wrapper">
					<input type="password" id="password" name="password" required>
					<span class="toggle-eye" onclick="togglePassword()">👁</span>
				</div>

                <label for="password_confirmation">Confirmation du mot de passe</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required>

                <button type="submit">Enregistrer</button>
            </form>
			


            <div class="login-links">
                <a href="portail_eleve.php">Retour à la connexion</a>
            </div>
        <?php endif; ?>
    </div>
	<script src="./assets/js/portail_eleve.js"></script>
</body>
</html>