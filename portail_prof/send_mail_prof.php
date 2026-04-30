<?php
// send_mail_prof.php — envoi des mails (bureau + confirmation prof)

declare(strict_types=1);

@file_put_contents('C:/data/audra/logs/send_mail.log', date('c') . " CALLED (send_mail_prof)\n", FILE_APPEND);

session_start();

// Autoload (PHPMailer) : fichier dans modules/portail_prof → vendor est à la racine du projet
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Charger config SMTP hors webroot
$configPath = 'C:/data/audra/config_mail.php';
if (!file_exists($configPath)) {
    @file_put_contents('C:/data/audra/logs/send_mail.log', date('c') . " CONFIG_MISSING\n", FILE_APPEND | LOCK_EX);
    exit("Erreur serveur : configuration mail introuvable.");
}
$cfg = include $configPath;

$SMTP_HOST     = $cfg['host']     ?? 'smtp.mail.ovh.net';
$SMTP_PORT     = (int)($cfg['port'] ?? 587);
$SMTP_USER     = $cfg['user']     ?? '';
$SMTP_PASS     = $cfg['pass']     ?? '';
$SMTP_FROMNAME = $cfg['fromname'] ?? 'AUDRA LANGUES';
$SMTP_FROM     = $SMTP_USER;

// ✅ Préfixe unique dans l'objet
$AUDRA_SUBJECT_PREFIX = '[AudraWeb] ';

function audra_prefix_subject(string $subject, string $prefix): string {
    if (strpos($subject, $prefix) !== 0) {
        return $prefix . $subject;
    }
    return $subject;
}

// Infos prof (sessions)
$profEmail     = $_SESSION['email']     ?? '';
$profFirstname = $_SESSION['firstname'] ?? '';
$profLastname  = $_SESSION['lastname']  ?? '';
$profDisplay   = $_SESSION['display']   ?? '';
$declaredHours = $_SESSION['declared_hours'] ?? 'Non précisé';
$uploadedFiles = $_SESSION['uploaded_files'] ?? [];

// Mode correction ?
$modeCorrection = !empty($_SESSION['mode_correction']) && $_SESSION['mode_correction'];

$annee  = (int)($_SESSION['annee'] ?? date('Y'));
$moisN  = (int)($_SESSION['mois']  ?? date('n'));

// Mois FR robuste
$moisNoms = [
    "Janvier","Février","Mars","Avril","Mai","Juin",
    "Juillet","Août","Septembre","Octobre","Novembre","Décembre"
];
$monthLabel = ($moisNoms[$moisN - 1] ?? (string)$moisN) . ' ' . $annee;

// Date/heure d'envoi (format demandé)
$dateEnvoi  = date('d/m/Y');
$heureEnvoi = date('H:i');
$dateSaisie = $dateEnvoi . ' ' . $heureEnvoi;

// Destinataires internes (bureau)
$internalTo = [
    'direction@audralangues.fr',
    'info@audralangues.fr',
];
$internalCC = [
    'contact@audralangues.fr',
];

// ✅ On met ces infos en session pour la page de confirmation (affichage)
$_SESSION['last_decl_sent_at'] = $dateSaisie;
$_SESSION['last_decl_sent_to'] = implode(', ', $internalTo);
$_SESSION['last_decl_sent_cc'] = implode(', ', $internalCC);

function audra_log(string $msg): void {
    $log_path = 'C:/data/audra/logs/send_mail.log';
    @file_put_contents($log_path, date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Nom prof lisible
$profFull = trim(($profFirstname ?? '') . ' ' . ($profLastname ?? ''));
if ($profFull === '') {
    $profFull = trim((string)$profDisplay);
}

// ---------- 1) Mail interne (bureau) ----------
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = $SMTP_USER;
    $mail->Password   = $SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    $mail->Encoding   = 'base64';
    $mail->SMTPDebug  = 0;

    $mail->setFrom($SMTP_FROM, $SMTP_FROMNAME);

    foreach ($internalTo as $to) {
        $mail->addAddress($to, 'AUDRA LANGUES');
    }
    foreach ($internalCC as $cc) {
        $mail->addCC($cc);
    }

    if ($modeCorrection) {
        $mail->Subject = "Correction Heures-web / Prof {$profFull} — {$monthLabel}";
        $body  = "Le prof : {$profFull} a apporté une CORRECTION pour ses heures du mois de {$monthLabel}\n";
    } else {
        $mail->Subject = "Déclaration Heures-web / Prof {$profFull} — {$monthLabel}";
        $body  = "Le prof : {$profFull} vient de saisir ses heures du mois de {$monthLabel}\n";
    }

    // ✅ Préfixe unique (anti-doublon)
    $mail->Subject = audra_prefix_subject((string)$mail->Subject, $AUDRA_SUBJECT_PREFIX);

    $body .= "Date et horaire de saisie : {$dateSaisie}\n";
    $body .= "Nbre heures : {$declaredHours}\n";
    $body .= "Fichiers envoyés :\n";

    if (!empty($uploadedFiles) && is_array($uploadedFiles)) {
        foreach ($uploadedFiles as $f) {
            $name = is_array($f) ? ($f['name'] ?? basename((string)($f['path'] ?? ''))) : basename((string)$f);
            $body .= "- {$name}\n";
        }
    } else {
        $body .= "- Aucun fichier listé\n";
        audra_log("NO_FILES for {$profEmail} (bureau mail)");
    }

    $body .= "\nMerci de vous connecter à votre espace « Admin » pour contrôler cette saisie.\n";
    $body .= "Message venant du serveur Audra Langues\n";

    $mail->isHTML(false);
    $mail->Body = $body;

    $mail->send();
    audra_log("NOTIFY_SENT (send_mail_prof) to=" . implode(',', $internalTo) . " cc=" . implode(',', $internalCC) . " (prof={$profEmail})");
} catch (Exception $e) {
    $errInfo = isset($mail) ? ($mail->ErrorInfo ?? '') : '';
    audra_log("NOTIFY_ERROR (send_mail_prof): " . ($errInfo !== '' ? $errInfo : $e->getMessage()));
}

// ---------- 2) Mail de confirmation au prof ----------
try {
    $mail2 = new PHPMailer(true);
    $mail2->isSMTP();
    $mail2->Host       = $SMTP_HOST;
    $mail2->SMTPAuth   = true;
    $mail2->Username   = $SMTP_USER;
    $mail2->Password   = $SMTP_PASS;
    $mail2->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail2->Port       = $SMTP_PORT;
    $mail2->CharSet    = 'UTF-8';
    $mail2->Encoding   = 'base64';
    $mail2->SMTPDebug  = 0;

    $mail2->setFrom($SMTP_FROM, $SMTP_FROMNAME);

    if (!empty($profEmail)) {
        $mail2->addAddress($profEmail, $profFull);
    } else {
        audra_log("CONFIRM_SKIP (send_mail_prof) PROF_EMAIL_EMPTY");
        echo "OK";
        exit;
    }

    if ($modeCorrection) {
        $mail2->Subject = "Accusé de réception — Correction — {$monthLabel}";
        $typeTxt = "correction";
    } else {
        $mail2->Subject = "Accusé de réception — Déclaration — {$monthLabel}";
        $typeTxt = "déclaration";
    }

    // ✅ Préfixe unique (anti-doublon)
    $mail2->Subject = audra_prefix_subject((string)$mail2->Subject, $AUDRA_SUBJECT_PREFIX);

    $text  = "Bonjour {$profFull},\n\n";
    $text .= "Nous avons bien reçu votre {$typeTxt} et vos documents pour {$monthLabel}.\n";
    $text .= "Elle a été envoyée le {$dateEnvoi} à {$heureEnvoi}\n";
    $text .= "à : " . implode(', ', $internalTo) . " + copie à : " . implode(', ', $internalCC) . ".\n\n";
    $text .= "Nous allons la traiter dans les meilleurs délais.\n\n";
    $text .= "⚠️ Si vous constatez une erreur, ou si vous pensez avoir oublié des informations ou des documents,\n";
    $text .= "ne refaites pas une nouvelle {$typeTxt}. Contactez directement le bureau au 04.93.87.23.11 ou par mail :\n";
    $text .= "- info@audralangues.fr (Julia)\n";
    $text .= "- direction@audralangues.fr (Elfie)\n\n";

    // (Option utile) rappel des fichiers envoyés
    $text .= "Fichiers reçus :\n";
    if (!empty($uploadedFiles) && is_array($uploadedFiles)) {
        foreach ($uploadedFiles as $f) {
            $name = is_array($f) ? ($f['name'] ?? basename((string)($f['path'] ?? ''))) : basename((string)$f);
            $text .= "- {$name}\n";
        }
    } else {
        $text .= "- Aucun fichier listé\n";
        audra_log("NO_FILES for {$profEmail} (confirmation mail)");
    }

    $text .= "\nBien cordialement,\n";
    $text .= "L'équipe Audra Langues\n";

    $mail2->isHTML(false);
    $mail2->Body = $text;

    $mail2->send();
    audra_log("CONFIRM_SENT (send_mail_prof) to {$profEmail}");
} catch (Exception $e) {
    $errInfo2 = isset($mail2) ? ($mail2->ErrorInfo ?? '') : '';
    audra_log("CONFIRM_ERROR (send_mail_prof): " . ($errInfo2 !== '' ? $errInfo2 : $e->getMessage()));
}

// Pas de déconnexion ici : ça reste un script “envoi de mails”
echo "OK";
