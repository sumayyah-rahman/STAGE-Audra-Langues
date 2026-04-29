<?php
// send_password_reset_eleve.php — envoi du lien de réinitialisation élève

declare(strict_types=1);

require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page(); // TO DO: adapter à un bootstrap élève dédié si besoin
require_once __DIR__ . '/../../base_url.php';
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

$message = "Si un compte actif correspond à cette adresse email, un lien de réinitialisation a été envoyé.";

$email = trim((string)($_POST['email'] ?? ''));

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $sql = "
        SELECT TOP 1
            id,
            email,
            login,
            student_name,
            is_active
        FROM dbo.AudraWeb_Eleve_Acces
        WHERE email = ?
          AND is_active = 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $token = bin2hex(random_bytes(16));
        $tokenHash = hash('sha256', $token);
        $expiry = date('Y-m-d H:i:s', time() + 60 * 30);

		$sqlUpdate = "
			UPDATE dbo.AudraWeb_Eleve_Acces
			SET
				reset_token_hash = ?,
				reset_token_expires_at = DATEADD(MINUTE, 30, GETDATE())
			WHERE id = ?
		";
		$stUpdate = $pdo->prepare($sqlUpdate);
		$stUpdate->execute([
			$tokenHash,
			(int)$user['id']
		]);

        // Envoi mail via PHPMailer + config SMTP existante
        require dirname(__DIR__, 2) . '/vendor/autoload.php';

        $configPath = 'C:/data/audra/config_mail.php';
        if (file_exists($configPath)) {
            $cfg = include $configPath;

            $SMTP_HOST     = $cfg['host']     ?? 'smtp.mail.ovh.net';
            $SMTP_PORT     = (int)($cfg['port'] ?? 587);
            $SMTP_USER     = $cfg['user']     ?? '';
            $SMTP_PASS     = $cfg['pass']     ?? '';
            $SMTP_FROMNAME = $cfg['fromname'] ?? 'AUDRA LANGUES';
            $SMTP_FROM     = $SMTP_USER;

			$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
			$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
			$basePath = '/audra_portail_prod';

			$resetLink = sprintf(
				'%s://%s%s/modules/portail_eleve/reset_password_eleve.php?token=%s',
				$scheme,
				$host,
				$basePath,
				urlencode($token)
			);

            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = $SMTP_USER;
                $mail->Password   = $SMTP_PASS;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $SMTP_PORT;
                $mail->CharSet    = 'UTF-8';
                $mail->Encoding   = 'base64';
                $mail->SMTPDebug  = 0;
                $mail->isHTML(true);

                $mail->setFrom($SMTP_FROM, $SMTP_FROMNAME);
                $mail->addAddress((string)$user['email'], (string)($user['student_name'] ?? ''));

                $mail->Subject = '[AudraWeb] Réinitialisation du mot de passe — Portail Élève';
                $mail->Body = "
                    <p>Bonjour,</p>
                    <p>Une demande de réinitialisation de votre mot de passe a été effectuée pour le portail élève.</p>
                    <p><a href=\"{$resetLink}\">Cliquez ici pour réinitialiser votre mot de passe</a></p>
                    <p>Ce lien expirera dans 30 minutes.</p>
                    <p>Si vous n’êtes pas à l’origine de cette demande, vous pouvez ignorer ce message.</p>
                    <p>Bien cordialement,<br>AUDRA LANGUES</p>
                ";

                $mail->send();
            } catch (Throwable $e) {
                // On garde un message neutre côté utilisateur
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Portail Élève — Demande envoyée</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./assets/css/portail_eleve.css?v=6">
</head>
<body>
    <div class="login-container">
        <img src="./assets/photos/audralangues-1.png" alt="Logo Audra Langues">
        <h1>Mot de passe oublié</h1>

        <p><?= h($message) ?></p>

        <div class="login-links">
            <a href="portail_eleve.php">Retour à la connexion</a>
        </div>
    </div>
</body>
</html>