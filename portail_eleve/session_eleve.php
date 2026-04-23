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

    $items = [];
    $fullText = $clientTxt . ' ' . $coursTxt . ' ' . $objectifTxt;

    if (
        str_contains($fullText, 'hotel') ||
        str_contains($fullText, 'hôtel') ||
        str_contains($fullText, 'mercure') ||
        str_contains($fullText, 'restaurant') ||
        str_contains($fullText, 'restauration')
    ) {
        $items[] = 'Hôtellerie / restauration';
    }

    if (
        str_contains($fullText, 'médical') ||
        str_contains($fullText, 'medical') ||
        str_contains($fullText, 'médecin') ||
        str_contains($fullText, 'santé')
    ) {
        $items[] = 'Santé / médical';
    }

    if (
        str_contains($fullText, 'notaire') ||
        str_contains($fullText, 'notariat') ||
        str_contains($fullText, 'juridique')
    ) {
        $items[] = 'Juridique / notariat';
    }

    if (
        str_contains($fullText, 'comptable') ||
        str_contains($fullText, 'comptabilité') ||
        str_contains($fullText, 'comptabilite') ||
        str_contains($fullText, 'gestion')
    ) {
        $items[] = 'Comptabilité / gestion';
    }

    if (
        str_contains($fullText, 'architect') ||
        str_contains($fullText, 'bâtiment') ||
        str_contains($fullText, 'batiment')
    ) {
        $items[] = 'Architecture / bâtiment';
    }

    if (
        str_contains($fullText, 'vente') ||
        str_contains($fullText, 'commerce') ||
        str_contains($fullText, 'client') ||
        str_contains($fullText, 'accueil')
    ) {
        $items[] = 'Accueil / relation client';
    }

    if (
        str_contains($fullText, 'professionnel') ||
        str_contains($fullText, 'entreprise') ||
        str_contains($fullText, 'client')
    ) {
        $items[] = 'Communication professionnelle';
    }

    if (str_contains($coursTxt, 'ita')) {
        $items[] = 'Italien professionnel';
    }

    if (str_contains($coursTxt, 'eng') || str_contains($coursTxt, 'anglais')) {
        $items[] = 'Anglais professionnel';
    }

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
			LTRIM(RTRIM(p.[N° cours]))            AS numero_cours,
			LTRIM(RTRIM(p.[Elève]))               AS eleve,
			LTRIM(RTRIM(p.[Formateur]))           AS professeur,
			LTRIM(RTRIM(p.[Matière]))             AS langue_etudiee,
			LTRIM(RTRIM(p.[Niveau]))              AS niveau_vue,
			LTRIM(RTRIM(p.[Objectif]))            AS objectif_vue,
			LTRIM(RTRIM(p.[Client]))              AS client,
			LTRIM(RTRIM(p.[Intitule du cours]))   AS intitule_vue
		FROM dbo._PROG_Analyse_Planning_ClientEleves p
		WHERE LTRIM(RTRIM(p.[N° cours])) = ?
		  AND (
				? = ''
				OR UPPER(
					REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(p.[Elève])), '  ', ' '), '  ', ' '), '  ', ' ')
				) LIKE '%' + UPPER(
					REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(?)), '  ', ' '), '  ', ' '), '  ', ' ')
				) + '%'
			  )
	)
    SELECT
        CAST(b.numero_cours AS nvarchar(40))                    AS numero_cours,
        COALESCE(v.eleve, 'À préciser')                         AS eleve,
        COALESCE(v.professeur, 'À préciser')                    AS professeur,
        COALESCE(v.langue_etudiee, 'À préciser')                AS langue_etudiee,
        COALESCE(v.niveau_vue, b.niveau_item, 'À préciser')     AS niveau_actuel,
        COALESCE(v.objectif_vue, b.objectif, 'À préciser')      AS objectif,
        COALESCE(v.client, 'À préciser')                        AS client,
        COALESCE(v.intitule_vue, b.intitule_cours, 'À préciser') AS intitule_cours,
        COALESCE(b.certification_visee, 'À préciser')           AS certification_visee,
        COALESCE(b.lieu_formation, 'À préciser')                AS lieu_formation
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
    $studentName        = (string)($row['eleve'] ?? 'À préciser');
    $teacherName        = (string)($row['professeur'] ?? 'À préciser');
    $numeroCours        = (string)($row['numero_cours'] ?? $numeroCoursSession);
    $langueEtudiee      = (string)($row['langue_etudiee'] ?? 'À préciser');
    $niveauActuel       = (string)($row['niveau_actuel'] ?? 'À préciser');
    $certificationVisee = (string)($row['certification_visee'] ?? 'À préciser');
    $objectif           = (string)($row['objectif'] ?? 'À préciser');
    $typeFormation      = mapTypeFormation((string)($row['lieu_formation'] ?? ''));

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
$lastTheme       = $_SESSION['last_theme'] ?? 'Aucun';
$lastGrammar     = $_SESSION['last_grammar'] ?? 'Aucun';
$lastSessionDate = $_SESSION['last_session_date'] ?? 'Aucune';

if (!is_array($contexte)) {
    $contexte = [$contexte];
}
