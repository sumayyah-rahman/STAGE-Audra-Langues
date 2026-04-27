<?php
// session_eleve.php — session commune pour les pages élève

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['student_logged'])) {
    header('Location: portail_eleve.php');
    exit;
}

// Connexion BDD
require_once __DIR__ . '/../CVT/_db.php';

$pdo = pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Aides
function mapTypeFormation(?string $rawLieu): string
{
    $raw = strtoupper(trim((string)$rawLieu));

    return match ($raw) {
        'IN', 'OUT', 'SITE CLIENT', 'VOTRE ENTREPRISE', 'AUDRA LANGUES', 'OBJECTIF LANGUES'
            => 'Présentiel',

        'A DISTANCE VIA TEAMS', 'VISIOCONFERENCE'
            => 'Visioconférence',

        'TELE-TRAVAIL SKYPE-TELEPHONE',
        'TELE TRAVAIL SKYPE-TELEPHONE',
        'TELE-TRAVAIL SKYPE TELEPHONE'
            => 'À distance',

        default => 'À préciser',
    };
}

function buildContexteFormation(?string $client, ?string $intituleCours, ?string $objectif): array
{
    $clientTxt   = mb_strtolower(trim((string)$client), 'UTF-8');
    $coursTxt    = mb_strtolower(trim((string)$intituleCours), 'UTF-8');
    $objectifTxt = mb_strtolower(trim((string)$objectif), 'UTF-8');

    $fullText = $clientTxt . ' ' . $coursTxt . ' ' . $objectifTxt;
    $items = [];

    // Domaines / secteurs
    if (
        str_contains($fullText, 'hotel') ||
        str_contains($fullText, 'hôtel') ||
        str_contains($fullText, 'mercure') ||
        str_contains($fullText, 'restaurant') ||
        str_contains($fullText, 'restauration') ||
        str_contains($fullText, 'tourisme')
    ) {
        $items[] = 'Hôtellerie / restauration / tourisme';
    }

    if (
        str_contains($fullText, 'médical') ||
        str_contains($fullText, 'medical') ||
        str_contains($fullText, 'médecin') ||
        str_contains($fullText, 'santé') ||
        str_contains($fullText, 'sante') ||
        str_contains($fullText, 'clinique') ||
        str_contains($fullText, 'pharmacie')
    ) {
        $items[] = 'Santé / médical';
    }

    if (
        str_contains($fullText, 'notaire') ||
        str_contains($fullText, 'notariat') ||
        str_contains($fullText, 'juridique') ||
        str_contains($fullText, 'avocat') ||
        str_contains($fullText, 'droit')
    ) {
        $items[] = 'Juridique / notariat';
    }

    if (
        str_contains($fullText, 'comptable') ||
        str_contains($fullText, 'comptabilité') ||
        str_contains($fullText, 'comptabilite') ||
        str_contains($fullText, 'gestion') ||
        str_contains($fullText, 'finance') ||
        str_contains($fullText, 'banque') ||
        str_contains($fullText, 'assurance')
    ) {
        $items[] = 'Comptabilité / gestion / finance';
    }

    if (
        str_contains($fullText, 'architect') ||
        str_contains($fullText, 'bâtiment') ||
        str_contains($fullText, 'batiment') ||
        str_contains($fullText, 'chantier') ||
        str_contains($fullText, 'immobilier') ||
        str_contains($fullText, 'construction')
    ) {
        $items[] = 'Architecture / bâtiment / immobilier';
    }

    if (
        str_contains($fullText, 'informatique') ||
        str_contains($fullText, 'numérique') ||
        str_contains($fullText, 'numerique') ||
        str_contains($fullText, 'digital') ||
        str_contains($fullText, 'développeur') ||
        str_contains($fullText, 'developpeur') ||
        str_contains($fullText, 'développement') ||
        str_contains($fullText, 'developpement') ||
        str_contains($fullText, 'logiciel') ||
        str_contains($fullText, 'software') ||
        str_contains($fullText, 'support') ||
        str_contains($fullText, 'helpdesk') ||
        str_contains($fullText, 'réseau') ||
        str_contains($fullText, 'reseau') ||
        str_contains($fullText, 'data') ||
        str_contains($fullText, 'it ')
    ) {
        $items[] = 'Informatique / numérique';
    }

    if (
        str_contains($fullText, 'commerce') ||
        str_contains($fullText, 'vente') ||
        str_contains($fullText, 'vendeur') ||
        str_contains($fullText, 'boutique') ||
        str_contains($fullText, 'prêt-à-porter') ||
        str_contains($fullText, 'pret-a-porter') ||
        str_contains($fullText, 'pret a porter')
    ) {
        $items[] = 'Commerce / vente';
    }

    if (
        str_contains($fullText, 'assistant') ||
        str_contains($fullText, 'administratif') ||
        str_contains($fullText, 'secrétariat') ||
        str_contains($fullText, 'secretariat') ||
        str_contains($fullText, 'bureau')
    ) {
        $items[] = 'Assistanat / administratif';
    }

    if (
        str_contains($fullText, 'formation') ||
        str_contains($fullText, 'éducation') ||
        str_contains($fullText, 'education') ||
        str_contains($fullText, 'enseignement') ||
        str_contains($fullText, 'école') ||
        str_contains($fullText, 'ecole')
    ) {
        $items[] = 'Éducation / formation';
    }

    if (
        str_contains($fullText, 'industrie') ||
        str_contains($fullText, 'technique') ||
        str_contains($fullText, 'maintenance') ||
        str_contains($fullText, 'production') ||
        str_contains($fullText, 'usine')
    ) {
        $items[] = 'Industrie / technique';
    }

    if (
        str_contains($fullText, 'transport') ||
        str_contains($fullText, 'logistique') ||
        str_contains($fullText, 'livraison') ||
        str_contains($fullText, 'stock')
    ) {
        $items[] = 'Transport / logistique';
    }

    if (
        str_contains($fullText, 'propreté') ||
        str_contains($fullText, 'proprete') ||
        str_contains($fullText, 'nettoyage') ||
        str_contains($fullText, 'services')
    ) {
        $items[] = 'Propreté / services';
    }

    if (
        str_contains($fullText, 'évènementiel') ||
        str_contains($fullText, 'evenementiel') ||
        str_contains($fullText, 'communication') ||
        str_contains($fullText, 'marketing')
    ) {
        $items[] = 'Communication / évènementiel';
    }

    if (
        str_contains($fullText, 'petite enfance') ||
        str_contains($fullText, 'social') ||
        str_contains($fullText, 'enfant') ||
        str_contains($fullText, 'crèche') ||
        str_contains($fullText, 'creche')
    ) {
        $items[] = 'Social / petite enfance';
    }

    if (
        str_contains($fullText, 'beauté') ||
        str_contains($fullText, 'beaute') ||
        str_contains($fullText, 'bien-être') ||
        str_contains($fullText, 'bien etre') ||
        str_contains($fullText, 'esthétique') ||
        str_contains($fullText, 'esthetique')
    ) {
        $items[] = 'Beauté / bien-être';
    }

    // Orientations de communication
    if (
        str_contains($fullText, 'client') ||
        str_contains($fullText, 'accueil') ||
        str_contains($fullText, 'relation')
    ) {
        $items[] = 'Accueil / relation client';
    }

    if (
        str_contains($fullText, 'téléphone') ||
        str_contains($fullText, 'telephone') ||
        str_contains($fullText, 'appel')
    ) {
        $items[] = 'Téléphone / communication orale';
    }

    if (
        str_contains($fullText, 'mail') ||
        str_contains($fullText, 'email') ||
        str_contains($fullText, 'courriel')
    ) {
        $items[] = 'Communication écrite professionnelle';
    }

    if (
        str_contains($fullText, 'professionnel') ||
        str_contains($fullText, 'entreprise') ||
        str_contains($fullText, 'bureau') ||
        str_contains($fullText, 'travail')
    ) {
        $items[] = 'Communication professionnelle';
    }

    // Orientation langue / cours
    if (str_contains($coursTxt, 'ita') || str_contains($coursTxt, 'italien')) {
        $items[] = 'Italien professionnel';
    }

    if (
        str_contains($coursTxt, 'eng') ||
        str_contains($coursTxt, 'anglais')
    ) {
        $items[] = 'Anglais professionnel';
    }

    if (
        str_contains($coursTxt, 'esp') ||
        str_contains($coursTxt, 'espagnol')
    ) {
        $items[] = 'Espagnol professionnel';
    }

    if (
        str_contains($coursTxt, 'fr') ||
        str_contains($coursTxt, 'français') ||
        str_contains($coursTxt, 'francais')
    ) {
        $items[] = 'Français professionnel';
    }


    // Fallback
    if (empty($items)) {
        if (trim((string)$objectif) !== '') {
            $items[] = trim((string)$objectif);
        } elseif (trim((string)$client) !== '') {
            $items[] = trim((string)$client);
        } elseif (trim((string)$intituleCours) !== '') {
            $items[] = trim((string)$intituleCours);
        } else {
            $items[] = 'À préciser';
        }
    }

    return array_values(array_unique($items));
}

// Données session venant de l’accès portail
$numeroCoursSession = trim((string)($_SESSION['course_number'] ?? ''));
$studentNameSession = trim((string)($_SESSION['student_name'] ?? ''));

// Requête EBP
$sql = "
    WITH BaseCours AS (
        SELECT TOP 1
            i.Id                                   AS numero_cours,
            LTRIM(RTRIM(i.Caption))                AS intitule_cours,
            LTRIM(RTRIM(i.xx_Objectif))            AS objectif,
            LTRIM(RTRIM(i.xx_Niveau))              AS niveau_item,
            LTRIM(RTRIM(i.xx_Certification_visee)) AS certification_visee,
            LTRIM(RTRIM(i.xx_Lieu))                AS lieu_formation
        FROM dbo.Item i
        WHERE CAST(i.Id AS nvarchar(40)) = ?
    ),
    VuePlanning AS (
        SELECT TOP 1
            LTRIM(RTRIM(p.[N° cours]))           AS numero_cours,
            LTRIM(RTRIM(p.[Elève]))              AS eleve,
            LTRIM(RTRIM(p.[Formateur]))          AS professeur,
            LTRIM(RTRIM(p.[Matière]))            AS langue_etudiee,
            LTRIM(RTRIM(p.[Niveau]))             AS niveau_vue,
            LTRIM(RTRIM(p.[Objectif]))           AS objectif_vue,
            LTRIM(RTRIM(p.[Client]))             AS client,
            LTRIM(RTRIM(p.[Intitule du cours]))  AS intitule_vue
        FROM dbo._PROG_Analyse_Planning_ClientEleves p
        WHERE LTRIM(RTRIM(p.[N° cours])) = ?
          AND (
                ? = ''
                OR LTRIM(RTRIM(p.[Elève])) = ?
              )
    )
    SELECT
        CAST(b.numero_cours AS nvarchar(40))                    AS numero_cours,
		COALESCE(v.eleve, '')                                    AS eleve,
		COALESCE(v.professeur, '')                               AS professeur,
		COALESCE(v.langue_etudiee, '')                           AS langue_etudiee,
		COALESCE(v.niveau_vue, b.niveau_item, '')                AS niveau_actuel,
		COALESCE(v.objectif_vue, b.objectif, '')                 AS objectif,
		COALESCE(v.client, '')                                   AS client,
		COALESCE(v.intitule_vue, b.intitule_cours, '')           AS intitule_cours,
		COALESCE(b.certification_visee, '')                      AS certification_visee,
		COALESCE(b.lieu_formation, '')                           AS lieu_formation
    FROM BaseCours b
    LEFT JOIN VuePlanning v
        ON v.numero_cours = CAST(b.numero_cours AS nvarchar(40))
";

$st = $pdo->prepare($sql);
$st->execute([
    $numeroCoursSession,
    $numeroCoursSession,
    $studentNameSession,
    $studentNameSession
]);
$row = $st->fetch(PDO::FETCH_ASSOC);

// Fallback
if (!$row) {
    $studentName        = $_SESSION['student_name'] ?? 'À préciser';
    $teacherName        = $_SESSION['teacher_name'] ?? 'À préciser';
    $numeroCours        = $_SESSION['course_number'] ?? 'À préciser';
    $langueEtudiee      = $_SESSION['langue_etudiee'] ?? 'À préciser';
    $niveauActuel       = $_SESSION['niveau_actuel'] ?? 'À préciser';
    $certificationVisee = $_SESSION['certification_visee'] ?? 'À préciser';
    $objectif           = $_SESSION['objectif'] ?? 'À préciser';
    $typeFormation      = $_SESSION['type_formation'] ?? 'À préciser';
    $contexte           = $_SESSION['contexte'] ?? ['À préciser'];
} else {
    $studentName = trim((string)($row['eleve'] ?? ''));
    if ($studentName === '') {
        $studentName = $studentNameSession !== '' ? $studentNameSession : 'À préciser';
    }

    $teacherName = trim((string)($row['professeur'] ?? ''));
    if ($teacherName === '') {
        $teacherName = (string)($_SESSION['teacher_name'] ?? 'À préciser');
    }

    $langueEtudiee = trim((string)($row['langue_etudiee'] ?? ''));
    if ($langueEtudiee === '') {
        $langueEtudiee = (string)($_SESSION['langue_etudiee'] ?? 'À préciser');
    }

    $numeroCours = trim((string)($row['numero_cours'] ?? ''));
    if ($numeroCours === '') {
        $numeroCours = $numeroCoursSession !== '' ? $numeroCoursSession : 'À préciser';
    }

    $niveauActuel = trim((string)($row['niveau_actuel'] ?? ''));
    if ($niveauActuel === '') {
        $niveauActuel = (string)($_SESSION['niveau_actuel'] ?? 'À préciser');
    }

    $certificationVisee = trim((string)($row['certification_visee'] ?? ''));
    if ($certificationVisee === '') {
        $certificationVisee = (string)($_SESSION['certification_visee'] ?? 'À préciser');
    }

    $objectif = trim((string)($row['objectif'] ?? ''));
    if ($objectif === '') {
        $objectif = (string)($_SESSION['objectif'] ?? 'À préciser');
    }

    $typeFormation = mapTypeFormation((string)($row['lieu_formation'] ?? ''));
    if ($typeFormation === 'À préciser') {
        $typeFormation = (string)($_SESSION['type_formation'] ?? 'À préciser');
    }

    $contexte = buildContexteFormation(
        (string)($row['client'] ?? ''),
        (string)($row['intitule_cours'] ?? ''),
        (string)($row['objectif'] ?? '')
    );

    $_SESSION['student_name']        = $studentName;
    $_SESSION['teacher_name']        = $teacherName;
    $_SESSION['course_number']       = $numeroCours;
    $_SESSION['langue_etudiee']      = $langueEtudiee;
    $_SESSION['niveau_actuel']       = $niveauActuel;
    $_SESSION['certification_visee'] = $certificationVisee;
    $_SESSION['objectif']            = $objectif;
    $_SESSION['type_formation']      = $typeFormation;
    $_SESSION['contexte']            = $contexte;
}

// Données portail / IA
$idAcces = (int)($_SESSION['id_acces'] ?? 0);

if ($idAcces > 0) {
    $sqlSuivi = "
        SELECT TOP 1
            last_theme,
            last_grammar,
            last_session_date
        FROM dbo.AudraWeb_Eleve_Suivi_IA
        WHERE id_acces = ?
    ";
    $stSuivi = $pdo->prepare($sqlSuivi);
    $stSuivi->execute([$idAcces]);
    $rowSuivi = $stSuivi->fetch(PDO::FETCH_ASSOC);

    if ($rowSuivi) {
        $lastTheme = trim((string)($rowSuivi['last_theme'] ?? ''));
        if ($lastTheme === '') {
            $lastTheme = 'Aucun';
        }

        $lastGrammar = trim((string)($rowSuivi['last_grammar'] ?? ''));
        if ($lastGrammar === '') {
            $lastGrammar = 'Aucun';
        }

        $lastSessionDate = 'Aucune';
        if (!empty($rowSuivi['last_session_date'])) {
            $ts = strtotime((string)$rowSuivi['last_session_date']);
            if ($ts) {
                $lastSessionDate = date('d/m/Y', $ts);
            }
        }

        $_SESSION['last_theme'] = $lastTheme;
        $_SESSION['last_grammar'] = $lastGrammar;
        $_SESSION['last_session_date'] = $lastSessionDate;
    } else {
        $lastTheme = $_SESSION['last_theme'] ?? 'Aucun';
        $lastGrammar = $_SESSION['last_grammar'] ?? 'Aucun';
        $lastSessionDate = $_SESSION['last_session_date'] ?? 'Aucune';
    }
} else {
    $lastTheme = $_SESSION['last_theme'] ?? 'Aucun';
    $lastGrammar = $_SESSION['last_grammar'] ?? 'Aucun';
    $lastSessionDate = $_SESSION['last_session_date'] ?? 'Aucune';
}

if (!is_array($contexte)) {
    $contexte = [$contexte];
}
