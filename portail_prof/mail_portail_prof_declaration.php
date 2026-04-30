<?php
// ============================================================================
// mail_portail_prof_declaration.php
// --------------------------------------------------------------------------
// Envoi des mails liés à la déclaration mensuelle des professeurs :
//  1) Mail de confirmation au professeur
//  2) Mail de notification au bureau
// ============================================================================

declare(strict_types=1);

// --------------------------------------------------------------------------
// 1) Fonction utilitaire d'envoi (via PHPMailer + config OVH)
// --------------------------------------------------------------------------
/**
 * Envoie un mail en utilisant la configuration SMTP OVH
 *
 * @param string $to            Destinataire ou liste séparée par des virgules
 * @param string $subject       Sujet du mail
 * @param string $body          Corps du message (texte brut)
 * @param string $fromFallback  Adresse utilisée si la config ne fournit pas de user
 * @param string $cc            CC ou liste séparée par des virgules (optionnel)
 */
function audra_send_mail(
    string $to,
    string $subject,
    string $body,
    string $fromFallback = 'noreply@audralangues.fr',
    string $cc = '',
    string $bcc = ''
): bool
{
    // 1) Charger la config SMTP
    $configPath = 'C:/data/audra/config_mail.php';
    if (!file_exists($configPath)) {
        @file_put_contents(
            'C:/data/audra/logs/send_mail.log',
            date('c') . " CONFIG_MISSING (mail_portail_prof_declaration)\n",
            FILE_APPEND
        );
        return false;
    }

    $cfg = include $configPath;

    $SMTP_HOST     = $cfg['host']     ?? 'smtp.mail.ovh.net';
    $SMTP_PORT     = (int)($cfg['port'] ?? 587);
    $SMTP_USER     = $cfg['user']     ?? '';
    $SMTP_PASS     = $cfg['pass']     ?? '';
    $SMTP_FROMNAME = $cfg['fromname'] ?? 'AUDRA LANGUES';
    $SMTP_FROM     = $SMTP_USER !== '' ? $SMTP_USER : $fromFallback;

    // 2) Charger PHPMailer
    require_once __DIR__ . '/../../vendor/autoload.php';

    $ok = false;

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

        $mail->setFrom($SMTP_FROM, $SMTP_FROMNAME);

        // Destinataires (séparés par virgules)
        $destList = array_map('trim', explode(',', $to));
        foreach ($destList as $dest) {
            if ($dest !== '') {
                $mail->addAddress($dest);
            }
        }

                // CC (séparé par virgules)
        $cc = trim($cc);
        if ($cc !== '') {
            $ccList = array_map('trim', explode(',', $cc));
            foreach ($ccList as $c) {
                if ($c !== '') {
                    $mail->addCC($c);
                }
            }
        }

        // BCC (séparé par virgules)
        $bcc = trim($bcc);
        if ($bcc !== '') {
            $bccList = array_map('trim', explode(',', $bcc));
            foreach ($bccList as $b) {
                if ($b !== '') {
                    $mail->addBCC($b);
                }
            }
        }

// ✅ texte brut (retours à la ligne)
$mail->isHTML(false);

// ✅ Préfixe unique pour filtrer tous les mails portail
$subject = (string)$subject;
$prefix  = '[AudraWeb] ';
if (strpos($subject, $prefix) !== 0) {
    $subject = $prefix . $subject;
}

$mail->Subject = $subject;
$mail->Body    = $body;


        $mail->send();
        $ok = true;

        @file_put_contents(
            'C:/data/audra/logs/send_mail.log',
            date('c') . " SENT (mail_portail_prof_declaration) to={$to} cc={$cc} subject=\"{$subject}\"\n",
            FILE_APPEND
        );
    } catch (\Throwable $e) {
        @file_put_contents(
            'C:/data/audra/logs/send_mail.log',
            date('c') . " ERROR (mail_portail_prof_declaration): " . $e->getMessage() . "\n",
            FILE_APPEND
        );
        $ok = false;
    }

    return $ok;
}


// --------------------------------------------------------------------------
// 2) Mail au PROF : confirmation de déclaration
// --------------------------------------------------------------------------
function audra_mail_prof_confirmation_declaration(
    string $profEmail,
    string $profName,
    string $moisTexte,
    bool $modeCorrection = false,
    string $declaredHours = '',
    int $nbFiles = 0
): bool
{
    $profEmail = trim($profEmail);
    if ($profEmail === '') return false;

    $name = trim($profName);
    if ($name === '') $name = 'Professeur';

    // Prénom uniquement (1er mot)
    $parts = preg_split('/\s+/', $name);
    $prenom = $parts && !empty($parts[0]) ? $parts[0] : $name;

    $typeTxt  = $modeCorrection ? 'correction' : 'déclaration';
    $labelHum = $modeCorrection ? 'Correction' : 'Déclaration';

    // Infos “envoyée le … à …”
    $dateEnvoi  = date('d/m/Y');
    $heureEnvoi = date('H:i');

    // Destinataires internes (info prof)
    $toLabel = 'direction@audralangues.fr, info@audralangues.fr';
    $ccLabel = 'contact@audralangues.fr';

    // Heures (si vide -> formulation neutre)
    $dh = trim((string)$declaredHours);
    if ($dh === '' || stripos($dh, 'non') !== false) {
        $hoursTxt = 'des heures';
    } else {
        $hoursTxt = $dh . ' heure' . ((is_numeric($dh) && (float)$dh > 1) ? 's' : '');
    }

    // Fichiers (pluriel)
    if ($nbFiles <= 0) {
        $filesTxt = 'des fichiers';
    } elseif ($nbFiles === 1) {
        $filesTxt = '1 fichier';
    } else {
        $filesTxt = $nbFiles . ' fichiers';
    }

    $subject = "[AudraWeb] Accusé de réception — " . ($modeCorrection ? 'CORRECTION' : 'DECLARATION') . " — {$moisTexte}";

    // ✅ Ton texte (tutoiement + infos)
    $body  = "Bonjour {$prenom},\n\n";
    $body .= "Nous te remercions pour ta {$labelHum} de {$moisTexte}.\n";
    $body .= "Elle a été envoyée le {$dateEnvoi} à {$heureEnvoi} et pour information tu as déclaré {$hoursTxt} et tu as joint {$filesTxt}.\n\n";
    $body .= "Nous allons vérifier les heures déclarées et les documents reçus afin de procéder au règlement dans les meilleurs délais.\n\n";
    $body .= "⚠️ Si tu penses avoir fait une erreur, ou avoir oublié des informations ou des documents, ne refait pas une nouvelle {$typeTxt}. ";
    $body .= "Contacte directement le bureau au 04.93.87.23.11 ou par mail : ";
    $body .= "info@audralangues.fr (Julia), direction@audralangues.fr (Elfie), contact@audralangues.fr (Yulia).\n";

    // ✅ CC au mail prof
    return audra_send_mail($profEmail, $subject, $body, 'noreply@audralangues.fr');
}



// --------------------------------------------------------------------------
// 3) Mail au BUREAU : notification (déclaration / correction)
// --------------------------------------------------------------------------
function audra_mail_bureau_notification_declaration(string $nomProf, string $codeProf, string $moisTexte, bool $isCorrection = false): bool
{
$destBureau = 'direction@audralangues.fr';
$ccBureau   = '';
$bccBureau  = '';

    $label   = $isCorrection ? 'Correction reçue' : 'Déclaration reçue';
    $subject = '[AudraWeb] Portail prof – ' . $label . ' : ' . $nomProf . ' – ' . $moisTexte;

    if ($isCorrection) {
        $body  = "Le professeur {$nomProf} (code : {$codeProf}) vient de renvoyer sa déclaration (CORRECTION) et/ou de nouveaux justificatifs pour le mois de {$moisTexte}.\n\n";
        $body .= "Merci de vous connecter au portail admin afin de re-contrôler le dossier (heures / facture / feuilles de présence / pièces jointes).\n\n";
    } else {
        $body  = "Le professeur {$nomProf} (code : {$codeProf}) vient de déposer sa déclaration et ses justificatifs pour le mois de {$moisTexte}.\n\n";
        $body .= "Merci de vous connecter au portail admin afin de contrôler cette déclaration et de traiter les documents dans les meilleurs délais.\n\n";
    }

    $body .= "Portail admin : http://audra.langues.pro.dns-orange.fr/admin\n\n";
	@file_put_contents('C:/data/audra/logs/send_mail.log', date('c') . " | ADMIN_URI (mail_portail_prof_declaration) http://audra.langues.pro.dns-orange.fr/admin\n", FILE_APPEND);
    $body .= "Ceci est un message automatique du portail AudraWeb.\n";

    return audra_send_mail($destBureau, $subject, $body, 'noreply@audralangues.fr', $ccBureau, $bccBureau);
}
