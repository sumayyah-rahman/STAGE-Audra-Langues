<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/security/firewall.php';
require_once __DIR__ . '/../../../base_url.php';
require_once __DIR__ . '/../../CVT/_db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['admin'])) {
    header('Location: /admin_login.php');
    exit;
}

$pdo = pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function frDate(?string $sqlDate): string {
    if (!$sqlDate) return '';
    $ts = strtotime($sqlDate);
    if (!$ts) return (string)$sqlDate;
    return date('d/m/Y', $ts);
}

function frFloat($v, int $decimals = 2): string {
    if ($v === null || $v === '') return '';
    return number_format((float)$v, $decimals, ',', ' ');
}

function normalizeSpaces(?string $s): string {
    $s = trim((string)$s);
    $s = preg_replace('~\s+~u', ' ', $s);
    return trim((string)$s);
}

function buildProfessionalActivity(?string $intituleStage, ?string $fonctions, ?string $client): string {
    $intituleStage = mb_strtolower(normalizeSpaces($intituleStage), 'UTF-8');
    $fonctions     = mb_strtolower(normalizeSpaces($fonctions), 'UTF-8');
    $client        = normalizeSpaces($client);

    // 1) priorité aux fonctions précises
    if ($fonctions !== '') {
        if (str_contains($fonctions, 'notaire')) {
            return 'Notariat / juridique';
        }
        if (str_contains($fonctions, 'médecin') || str_contains($fonctions, 'medical') || str_contains($fonctions, 'médical')) {
            return 'Santé / médical';
        }
        if (str_contains($fonctions, 'architect')) {
            return 'Architecture / bâtiment';
        }
        if (str_contains($fonctions, 'comptable')) {
            return 'Comptabilité / gestion';
        }
		if (str_contains($fonctions, 'assistant')) {
		return 'Assistanat / fonctions support';
		}
    }

    // 2) sinon on regarde l’intitulé du stage
    if ($intituleStage !== '') {
        if (str_contains($intituleStage, 'notariat') || str_contains($intituleStage, 'juridique')) {
            return 'Notariat / juridique';
        }
        if (str_contains($intituleStage, 'médical') || str_contains($intituleStage, 'medical')) {
            return 'Santé / médical';
        }
        if (str_contains($intituleStage, 'restauration') || str_contains($intituleStage, 'oenologie') || str_contains($intituleStage, 'œnologie')) {
            return 'Restauration / hôtellerie';
        }
        if (str_contains($intituleStage, 'hotellerie') || str_contains($intituleStage, 'hôtellerie')) {
            return 'Hôtellerie';
        }
        if (str_contains($intituleStage, 'architecture') || str_contains($intituleStage, 'bâtiment') || str_contains($intituleStage, 'batiment')) {
            return 'Architecture / bâtiment';
        }
        if (str_contains($intituleStage, 'nettoyage') || str_contains($intituleStage, 'proprete') || str_contains($intituleStage, 'propreté')) {
            return 'Propreté / services';
        }
        if (str_contains($intituleStage, 'évènementiel') || str_contains($intituleStage, 'evenementiel')) {
            return 'Évènementiel / communication';
        }
        if (str_contains($intituleStage, 'accueil')) {
            return 'Accueil / relation client';
        }
    }

    // 3) sinon on regarde le client
    $clientLower = mb_strtolower($client, 'UTF-8');
    if ($clientLower !== '') {
        if (str_contains($clientLower, 'notaire') || str_contains($clientLower, 'etude notariale') || str_contains($clientLower, 'étude notariale')) {
            return 'Notariat / juridique';
        }
        if (str_contains($clientLower, 'architect')) {
            return 'Architecture / bâtiment';
        }
        if (str_contains($clientLower, 'expertise comptable') || str_contains($clientLower, 'comptable')) {
            return 'Comptabilité / gestion';
        }
        if (str_contains($clientLower, 'onet') || str_contains($clientLower, 'proprete') || str_contains($clientLower, 'propreté')) {
            return 'Propreté / services';
        }
        if (str_contains($clientLower, 'restaurant') || str_contains($clientLower, 'libanais') || str_contains($clientLower, 'chalet suisse')) {
            return 'Restauration / hôtellerie';
        }
    }

    // 4) repli
    return 'À préciser par l’admin';
}

function mapTrainingLocation(?string $rawLieu): array {
    $raw = strtoupper(trim((string)$rawLieu));

    $display = $raw;
    $modalite = '';
    $precision = '';

    switch ($raw) {
        case 'IN':
            $display   = 'Audra Langues';
            $modalite  = 'Présentiel';
            $precision = 'Dans les locaux Audra Langues';
            break;

        case 'OUT':
            $display   = 'Entreprise';
            $modalite  = 'Présentiel';
            $precision = 'Dans les locaux de l’entreprise';
            break;

        case 'SITE CLIENT':
            $display   = 'Site client';
            $modalite  = 'Présentiel';
            $precision = 'Sur site client';
            break;

        case 'VOTRE ENTREPRISE':
            $display   = 'Votre entreprise';
            $modalite  = 'Présentiel';
            $precision = 'Dans les locaux de l’entreprise';
            break;

        case 'TELE-TRAVAIL SKYPE-TELLEPHONE':
        case 'TELE TRAVAIL SKYPE-TELEPHONE':
        case 'TELE-TRAVAIL SKYPE-TELEPHONE':
            $display   = 'À distance / téléphone';
            $modalite  = 'À distance';
            $precision = 'Cours à distance par téléphone';
            break;

        case 'A DISTANCE VIA TEAMS':
            $display   = 'À distance via Teams';
            $modalite  = 'Visioconférence';
            $precision = 'Cours en visioconférence via Teams';
            break;

        case 'VISIOCONFERENCE':
            $display   = 'Visioconférence';
            $modalite  = 'Visioconférence';
            $precision = 'Cours en visioconférence';
            break;

        case 'AUDRA LANGUES':
            $display   = 'Audra Langues';
            $modalite  = 'Présentiel';
            $precision = 'Dans les locaux Audra Langues';
            break;

        case 'OBJECTIF LANGUES':
            $display   = 'Objectif Langues';
            $modalite  = 'Présentiel';
            $precision = 'Lieu partenaire';
            break;

        default:
            $display   = trim((string)$rawLieu);
            $modalite  = '';
            $precision = '';
            break;
    }

    return [
        'display'   => $display,
        'modalite'  => $modalite,
        'precision' => $precision,
        'raw'       => (string)$rawLieu,
    ];
}

/**
 * Si la référence commence par D => devis
 * sinon si chiffres => cours
 */
function detectRefType(string $ref): string {
    $ref = strtoupper(trim($ref));
    if ($ref === '') return '';
    if ($ref[0] === 'D') return 'devis';
    if (ctype_digit($ref)) return 'cours';
    return '';
}

/**
 * À partir d'un devis, retrouver le cours principal :
 * - première ligne du devis avec ItemId renseigné
 */
function findCourseFromDevis(PDO $pdo, string $devis): ?string {
    $sql = "
        SELECT TOP (1)
            CAST(sdl.ItemId AS nvarchar(40)) AS num_cours
        FROM dbo.SaleDocument sd
        INNER JOIN dbo.SaleDocumentLine sdl
            ON sdl.DocumentId = sd.Id
        WHERE sd.DocumentNumber = :devis
          AND sdl.ItemId IS NOT NULL
        ORDER BY sdl.LineOrder ASC, sdl.Id ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':devis' => $devis]);
    $v = $st->fetchColumn();
    return $v ? (string)$v : null;
}

/**
 * Fiche programme complète pour 1 cours
 */
function fetchProgrammeSheet(PDO $pdo, string $numCours): ?array {
    $sql = "
    ;WITH Cours AS (
        SELECT
            i.Id AS num_cours,
            i.Caption AS intitule_interne_cours,
            i.xx_Action,
            i.xx_Certification_visee,
            i.xx_Debut,
            i.xx_Fin,
            i.xx_Lieu,
            i.xx_Liste_des_objectifs_vises_en_fin_de_formation,
            i.xx_Methode_pedagogique,
            i.xx_Niveau AS niveau_item,
            i.xx_Nombre_de_stagiaires,
            i.xx_numero_dossier,
            i.xx_Objectif AS objectif_item,
            i.xx_Taux_horaire
        FROM dbo.Item i
        WHERE i.Id = ?
    ),
    VueCours AS (
        SELECT TOP (1)
            p.[N° cours]            AS num_cours,
            p.[Intitule du cours]   AS intitule_vue,
            p.[Elève]               AS eleve_vue,
            p.[Client]              AS client_vue,
            p.[Formateur]           AS formateur_vue,
            p.[Matière]             AS matiere_vue,
            p.[Certification visée] AS certification_vue,
            p.[Objectif]            AS objectif_vue,
            p.[Niveau]              AS niveau_vue
        FROM dbo._PROG_Analyse_Planning_ClientEleves p
        WHERE p.[N° cours] = ?
    ),
    Devis AS (
        SELECT TOP (1)
            sd.Id                   AS sale_doc_id,
            sd.DocumentNumber       AS numero_devis,
            sd.DocumentDate         AS date_devis,
            sd.Reference            AS intitule_stage,
            sd.CustomerId           AS customer_id,
            sd.CustomerName         AS client_devis,
            sd.xx_Numero_de_dossier AS numero_dossier_devis,
            CAST(sdl.Quantity AS float) AS duree_contrat
        FROM dbo.SaleDocumentLine sdl
        INNER JOIN dbo.SaleDocument sd
            ON sd.Id = sdl.DocumentId
        WHERE sdl.ItemId = ?
          AND sd.DocumentNumber LIKE 'D%'
        ORDER BY sd.DocumentDate DESC, sd.Id DESC, sdl.Id ASC
    ),
    Client AS (
        SELECT
            c.Id,
            c.Siren,
            c.NAF
        FROM dbo.Customer c
        INNER JOIN Devis d
            ON d.customer_id = c.Id
    ),
    Eleves AS (
        SELECT DISTINCT
            ec.xx_sysParentId AS num_cours,
            c.ContactFields_Name,
            c.ContactFields_FirstName,
            c.ContactFields_Function,
            c.ContactFields_Department,
            c.ContactFields_Email
        FROM dbo.xx_Eleves_du_cours ec
        INNER JOIN dbo.Contact c
            ON c.Id = ec.xx_Eleves
        WHERE ec.xx_sysParentId = ?
    )
    SELECT
        c.num_cours                                          AS num_cours,
        d.numero_devis                                       AS numero_devis,
        d.date_devis                                         AS date_devis,
        d.intitule_stage                                     AS intitule_stage,
        COALESCE(vc.client_vue, d.client_devis)              AS client,
        cl.Siren                                             AS siren_client,
        cl.NAF                                               AS naf_client,
        COALESCE(vc.matiere_vue, '')                         AS matiere,
        COALESCE(vc.niveau_vue, c.niveau_item)               AS niveau,
        COALESCE(vc.objectif_vue, c.objectif_item)           AS objectif_general,
        COALESCE(vc.certification_vue, c.xx_Certification_visee) AS certification_visee,
        c.xx_Debut                                           AS date_debut,
        c.xx_Fin                                             AS date_fin,
        d.duree_contrat                                      AS duree_contrat,
        c.xx_Lieu                                            AS lieu,
        c.xx_Methode_pedagogique                             AS methode_pedagogique,
        c.xx_Liste_des_objectifs_vises_en_fin_de_formation   AS objectifs_vises_fin_formation,
        c.xx_Nombre_de_stagiaires                            AS nombre_de_stagiaires,
        COALESCE(d.numero_dossier_devis, c.xx_numero_dossier) AS numero_dossier,
        vc.formateur_vue                                     AS formateur,
        c.intitule_interne_cours                             AS intitule_interne_cours,

        STUFF((
            SELECT ' | ' + LTRIM(RTRIM(
                COALESCE(e2.ContactFields_FirstName, '') +
                CASE WHEN e2.ContactFields_FirstName IS NOT NULL AND e2.ContactFields_Name IS NOT NULL THEN ' ' ELSE '' END +
                COALESCE(e2.ContactFields_Name, '')
            ))
            FROM Eleves e2
            WHERE e2.num_cours = c.num_cours
            ORDER BY e2.ContactFields_Name, e2.ContactFields_FirstName
            FOR XML PATH(''), TYPE
        ).value('.', 'nvarchar(max)'), 1, 3, '')            AS eleves,

        STUFF((
            SELECT DISTINCT ' | ' + LTRIM(RTRIM(COALESCE(e2.ContactFields_Function, '')))
            FROM Eleves e2
            WHERE e2.num_cours = c.num_cours
              AND COALESCE(e2.ContactFields_Function, '') <> ''
            FOR XML PATH(''), TYPE
        ).value('.', 'nvarchar(max)'), 1, 3, '')            AS fonctions,

        STUFF((
            SELECT DISTINCT ' | ' + LTRIM(RTRIM(COALESCE(e2.ContactFields_Department, '')))
            FROM Eleves e2
            WHERE e2.num_cours = c.num_cours
              AND COALESCE(e2.ContactFields_Department, '') <> ''
            FOR XML PATH(''), TYPE
        ).value('.', 'nvarchar(max)'), 1, 3, '')            AS services,

        STUFF((
            SELECT DISTINCT ' | ' + LTRIM(RTRIM(COALESCE(e2.ContactFields_Email, '')))
            FROM Eleves e2
            WHERE e2.num_cours = c.num_cours
              AND COALESCE(e2.ContactFields_Email, '') <> ''
            FOR XML PATH(''), TYPE
        ).value('.', 'nvarchar(max)'), 1, 3, '')            AS emails

    FROM Cours c
    LEFT JOIN VueCours vc
        ON vc.num_cours = c.num_cours
    LEFT JOIN Devis d
        ON 1 = 1
    LEFT JOIN Client cl
        ON 1 = 1
    ";

    $st = $pdo->prepare($sql);
    $st->execute([$numCours, $numCours, $numCours, $numCours]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

// ----------------------------------------------------------
// Entrée utilisateur
// ----------------------------------------------------------
$ref       = trim((string)($_GET['ref'] ?? ''));
$refType   = '';
$numCours  = '';
$error     = '';
$sheet     = null;

if ($ref !== '') {
    $refType = detectRefType($ref);

    if ($refType === 'devis') {
        $numCours = (string)(findCourseFromDevis($pdo, strtoupper($ref)) ?? '');
        if ($numCours === '') {
            $error = "Aucun cours principal n’a été trouvé pour le devis " . h(strtoupper($ref)) . ".";
        }
    } elseif ($refType === 'cours') {
        $numCours = $ref;
    } else {
        $error = "Référence invalide. Saisis un N° de cours (ex : 19734) ou un N° de devis (ex : D004370).";
    }

    if ($error === '' && $numCours !== '') {
        $sheet = fetchProgrammeSheet($pdo, $numCours);
        if (!$sheet) {
            $error = "Aucune fiche programme n’a été trouvée pour le cours " . h($numCours) . ".";
        }
    }
}

$adminEmail = (string)($_SESSION['admin_email'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Programmes ChatGPT – Admin Audra</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{
            font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
            background:#f3f4f6;
            margin:0;
            color:#111827;
        }
        .wrap{
            max-width:1280px;
            margin:32px auto;
            padding:0 20px 40px;
        }
        .card{
            background:#fff;
            border:1px solid #d1d5db;
            border-radius:14px;
            padding:22px 24px;
            box-shadow:0 4px 14px rgba(0,0,0,.05);
            margin-bottom:20px;
        }
        h1{
            margin:0 0 8px;
            font-size:30px;
        }
        h2{
            margin:0 0 12px;
            font-size:22px;
        }
        .muted{
            color:#6b7280;
            font-size:14px;
        }
        .toplinks{
            margin-top:14px;
            display:flex;
            gap:16px;
            flex-wrap:wrap;
        }
        a{
            color:#2563eb;
            text-decoration:none;
            font-weight:600;
        }
        a:hover{ text-decoration:underline; }
        .ok{
            margin-top:18px;
            padding:12px 14px;
            border-radius:10px;
            background:#ecfdf5;
            border:1px solid #a7f3d0;
            color:#065f46;
            font-weight:600;
        }
        .err{
            padding:12px 14px;
            border-radius:10px;
            background:#fef2f2;
            border:1px solid #fecaca;
            color:#991b1b;
            font-weight:600;
        }
        form.search{
            display:flex;
            gap:12px;
            flex-wrap:wrap;
            align-items:end;
        }
        label{
            display:block;
            margin-bottom:6px;
            font-weight:700;
        }
        input[type="text"], textarea{
            width:100%;
            box-sizing:border-box;
            padding:10px 12px;
            border:1px solid #cbd5e1;
            border-radius:10px;
            font-size:15px;
        }
        input[type="text"]{
            max-width:420px;
        }
        textarea{
            min-height:90px;
            resize:vertical;
        }
        button{
            padding:11px 16px;
            border:0;
            border-radius:10px;
            background:#2563eb;
            color:#fff;
            font-weight:700;
            cursor:pointer;
        }
        button:hover{
            background:#1d4ed8;
        }
        .btn-secondary{
            background:#0f766e;
        }
        .btn-secondary:hover{
            background:#115e59;
        }
        .grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:16px 24px;
        }
        @media (max-width: 980px){
            .grid{
                grid-template-columns:1fr;
            }
        }
        .line{
            margin:8px 0;
            line-height:1.5;
        }
        .field{
            background:#f8fafc;
            border:1px solid #e2e8f0;
            border-radius:10px;
            padding:10px 12px;
        }
        .field b{
            display:block;
            margin-bottom:4px;
            color:#1f2937;
        }
        .copy-wrap{
            display:flex;
            gap:12px;
            flex-wrap:wrap;
            align-items:center;
            margin-top:12px;
        }
        .note{
            font-size:13px;
            color:#475569;
        }
        #bloc_chatgpt{
            min-height:480px;
            font-family:Consolas, "Courier New", monospace;
            white-space:pre-wrap;
        }
    </style>
</head>
<body>
<div class="wrap">

    <div class="card">
        <h1>Programmes ChatGPT — Admin Audra</h1>
        <div class="muted">Prépare une fiche synthèse à partir d’un N° de cours ou d’un N° de devis, puis copie le bloc final dans ChatGPT.</div>

        <div class="toplinks">
            <a href="/portail_admin.php">&larr; Retour au Portail Admin</a>
            <a href="/modules/QUALIOPI/index.php">📘 Module Qualiopi</a>
        </div>

        <div class="ok">
            Accès admin autorisé<?= $adminEmail !== '' ? ' pour : ' . h($adminEmail) : '' ?>.
        </div>
    </div>

    <div class="card">
        <h2>1. Charger un dossier</h2>

        <?php if ($error !== ''): ?>
            <div class="err" style="margin-bottom:14px;"><?= h($error) ?></div>
        <?php endif; ?>

        <form class="search" method="get" action="">
            <div>
                <label for="ref">N° de cours ou N° de devis</label>
                <input type="text" name="ref" id="ref" placeholder="Ex. 19734 ou D004370" value="<?= h($ref) ?>">
            </div>
            <div>
                <button type="submit">Charger la fiche</button>
            </div>
        </form>

        <div class="note" style="margin-top:12px;">
            La page détecte automatiquement si la référence saisie correspond à un devis ou à un cours.
        </div>
    </div>

    <?php if ($sheet): ?>
        <?php
    $activiteProAuto = buildProfessionalActivity(
        (string)($sheet['intitule_stage'] ?? ''),
        (string)($sheet['fonctions'] ?? ''),
        (string)($sheet['client'] ?? '')
    );

    $lieuInfo = mapTrainingLocation((string)($sheet['lieu'] ?? ''));
?>

        <div class="card">
            <h2>2. Fiche programme EBP</h2>
            <div class="grid">
                <div class="field"><b>N° cours</b><?= h((string)$sheet['num_cours']) ?></div>
                <div class="field"><b>N° devis</b><?= h((string)$sheet['numero_devis']) ?></div>

                <div class="field"><b>Date devis</b><?= h(frDate((string)$sheet['date_devis'])) ?></div>
                <div class="field"><b>Intitulé du stage</b><?= h((string)$sheet['intitule_stage']) ?></div>

                <div class="field"><b>Client</b><?= h((string)$sheet['client']) ?></div>
                <div class="field"><b>SIREN client</b><?= h((string)$sheet['siren_client']) ?></div>

                <div class="field"><b>Code NAF</b><?= h((string)$sheet['naf_client']) ?></div>
                <div class="field"><b>Langue / matière</b><?= h((string)$sheet['matiere']) ?></div>

                <div class="field"><b>Niveau d’entrée</b><?= h((string)$sheet['niveau']) ?></div>
                <div class="field"><b>Objectif général</b><?= h((string)$sheet['objectif_general']) ?></div>

                <div class="field"><b>Certification visée</b><?= h((string)$sheet['certification_visee']) ?></div>
                <div class="field">
    <b>Lieu</b><?= h((string)$lieuInfo['display']) ?>
    <?php if (!empty($lieuInfo['raw']) && $lieuInfo['raw'] !== $lieuInfo['display']): ?>
        <div class="note" style="margin-top:6px;">Valeur EBP : <?= h((string)$lieuInfo['raw']) ?></div>
    <?php endif; ?>
</div>

<div class="field">
    <b>Modalité</b><?= h((string)$lieuInfo['modalite']) ?>
    <?php if (!empty($lieuInfo['precision'])): ?>
        <div class="note" style="margin-top:6px;"><?= h((string)$lieuInfo['precision']) ?></div>
    <?php endif; ?>
</div>

                <div class="field"><b>Date début</b><?= h(frDate((string)$sheet['date_debut'])) ?></div>
                <div class="field"><b>Date fin</b><?= h(frDate((string)$sheet['date_fin'])) ?></div>

                <div class="field"><b>Durée contrat (h)</b><?= h(frFloat($sheet['duree_contrat'], 2)) ?></div>
                <div class="field"><b>Nombre de stagiaires</b><?= h((string)$sheet['nombre_de_stagiaires']) ?></div>

                <div class="field"><b>N° dossier</b><?= h((string)$sheet['numero_dossier']) ?></div>
                <div class="field"><b>Formateur</b><?= h((string)$sheet['formateur']) ?></div>

                <div class="field"><b>Stagiaire(s)</b><?= h((string)$sheet['eleves']) ?></div>
                <div class="field"><b>Fonction(s)</b><?= h((string)$sheet['fonctions']) ?></div>

                <div class="field"><b>Service(s)</b><?= h((string)$sheet['services']) ?></div>
                <div class="field"><b>Email(s)</b><?= h((string)$sheet['emails']) ?></div>

                <div class="field"><b>Méthode pédagogique</b><?= h((string)$sheet['methode_pedagogique']) ?></div>
                <div class="field"><b>Intitulé interne du cours</b><?= h((string)$sheet['intitule_interne_cours']) ?></div>

                <div class="field" style="grid-column:1 / -1;">
                    <b>Objectifs visés fin de formation</b>
                    <?= h((string)$sheet['objectifs_vises_fin_formation']) ?>
                </div>
            </div>
        </div>

                
       <div class="card" id="bloc_complements_admin">
			<h2>3. Compléments admin</h2>

    <div class="note" style="margin-bottom:14px; padding:10px 12px; background:#fff7ed; border:1px solid #fdba74; border-radius:10px; color:#9a3412;">
        Si la fonction est vide ou trop vague, merci de renseigner <strong>Activité professionnelle / branche</strong>, <strong>Poste / fonction du stagiaire</strong> et <strong>Mots-clés métier</strong>.
    </div>

    <div class="grid">
                <div>
                    <label for="activite_professionnelle">Activité professionnelle / branche</label>
                    <textarea id="activite_professionnelle" placeholder="Ex. notariat, cabinet comptable, restauration, hôtellerie, propreté, architecture..."><?= h($activiteProAuto) ?></textarea>
                </div>
                <div>
                    <label for="poste_fonction_stagiaire">Poste / fonction du stagiaire</label>
                    <textarea id="poste_fonction_stagiaire" placeholder="Ex. comptable, vendeur, technicien de maintenance, demandeur d’emploi visant un poste en secrétariat..."></textarea>
                </div>
                <div>
                    <label for="mots_cles_metier">Mots-clés métier</label>
                    <textarea id="mots_cles_metier" placeholder="Ex. accueil client, téléphone, mails, devis, rendez-vous, vocabulaire juridique..."></textarea>
                </div>
                <div>
                    <label for="competences_cibles">Compétences à cibler</label>
                    <textarea id="competences_cibles" placeholder="Ex. oral professionnel, compréhension écrite, rédaction d’emails, vocabulaire métier..."></textarea>
                </div>
                <div>
                    <label for="remarques_admin">Remarques admin</label>
                    <textarea id="remarques_admin" placeholder="Ex. programme sobre, professionnel, pas trop technique, insister sur l’accueil, viser 3 pages max..."></textarea>
                </div>
            </div>
        </div>

        <div class="card" id="bloc_sortie_chatgpt">
		<h2 id="titre_bloc_sortie">4. Bloc Programme Audra à copier dans ChatGPT</h2>

            <textarea id="bloc_chatgpt" readonly></textarea>

            <div class="copy-wrap">
			<button type="button" id="btn_generer">Préparer le bloc</button>
			<button type="button" class="btn-secondary" id="btn_copier">Copier le bloc</button>
<span class="note">Vérifie le contenu, puis clique sur “Copier le programme Audra”.</span>
            </div>
        </div>

        <script>
        (function(){
            const fiche = <?= json_encode([
                'num_cours'                    => (string)($sheet['num_cours'] ?? ''),
                'numero_devis'                 => (string)($sheet['numero_devis'] ?? ''),
                'date_devis'                   => frDate((string)($sheet['date_devis'] ?? '')),
                'intitule_stage'               => (string)($sheet['intitule_stage'] ?? ''),
                'client'                       => (string)($sheet['client'] ?? ''),
				'activite_pro_auto'            => (string)$activiteProAuto,
                'siren_client'                 => (string)($sheet['siren_client'] ?? ''),
                'naf_client'                   => (string)($sheet['naf_client'] ?? ''),
                'matiere'                      => (string)($sheet['matiere'] ?? ''),
                'niveau'                       => (string)($sheet['niveau'] ?? ''),
                'objectif_general'             => (string)($sheet['objectif_general'] ?? ''),
                'certification_visee'          => (string)($sheet['certification_visee'] ?? ''),
                'date_debut'                   => frDate((string)($sheet['date_debut'] ?? '')),
                'date_fin'                     => frDate((string)($sheet['date_fin'] ?? '')),
                'duree_contrat'                => frFloat($sheet['duree_contrat'], 2),
                'lieu'                         => (string)($sheet['lieu'] ?? ''),
                'lieu_affiche'                 => (string)($lieuInfo['display'] ?? ''),
                'modalite'                     => (string)($lieuInfo['modalite'] ?? ''),
                'precision_lieu'               => (string)($lieuInfo['precision'] ?? ''),
                'methode_pedagogique'          => (string)($sheet['methode_pedagogique'] ?? ''),
                'objectifs_vises_fin_formation'=> (string)($sheet['objectifs_vises_fin_formation'] ?? ''),
                'nombre_de_stagiaires'         => (string)($sheet['nombre_de_stagiaires'] ?? ''),
                'numero_dossier'               => (string)($sheet['numero_dossier'] ?? ''),
                'formateur'                    => (string)($sheet['formateur'] ?? ''),
                'intitule_interne_cours'       => (string)($sheet['intitule_interne_cours'] ?? ''),
                'eleves'                       => (string)($sheet['eleves'] ?? ''),
                'fonctions'                    => (string)($sheet['fonctions'] ?? ''),
                'services'                     => (string)($sheet['services'] ?? ''),
                'emails'                       => (string)($sheet['emails'] ?? ''),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

            function guessKeywords(activity, intituleStage, fonctions, client) {
                const txt = [
                    activity || '',
                    intituleStage || '',
                    fonctions || '',
                    client || ''
                ].join(' ').toLowerCase();

                if (txt.includes('notariat') || txt.includes('notaire') || txt.includes('juridique')) {
                    return 'accueil client, téléphone, mails, rendez-vous, vocabulaire juridique, échanges professionnels, actes, relation client';
                }
                if (txt.includes('médical') || txt.includes('medical') || txt.includes('médecin')) {
                    return 'accueil patient, prise de rendez-vous, vocabulaire médical courant, échanges professionnels, compréhension orale, téléphone';
                }
                if (txt.includes('architecture') || txt.includes('architect') || txt.includes('bâtiment') || txt.includes('batiment')) {
                    return 'présentation de projet, vocabulaire technique, échanges clients, plans, chantier, mails professionnels, réunions';
                }
                if (txt.includes('restauration') || txt.includes('restaurant') || txt.includes('hôtellerie') || txt.includes('hotellerie')) {
                    return 'accueil client, prise de commande, service, téléphone, réservation, vocabulaire restauration, échanges professionnels';
                }
                if (txt.includes('nettoyage') || txt.includes('propreté') || txt.includes('proprete') || txt.includes('onet')) {
                    return 'consignes, sécurité, matériel, site client, échanges simples, compréhension d’instructions, vocabulaire professionnel';
                }
                if (txt.includes('comptabilité') || txt.includes('comptable') || txt.includes('expertise comptable') || txt.includes('gestion')) {
                    return 'mails professionnels, accueil client, téléphone, vocabulaire comptable, documents, échanges administratifs';
                }
                if (txt.includes('évènementiel') || txt.includes('evenementiel') || txt.includes('communication')) {
                    return 'accueil client, organisation, téléphone, mails, coordination, présentation, échanges professionnels';
                }
                if (txt.includes('commerce') || txt.includes('vente') || txt.includes('boutique') || txt.includes('prêt-à-porter') || txt.includes('pret a porter')) {
                    return 'accueil client, conseil, argumentation, téléphone, mails, vocabulaire commercial, relation client';
                }
                if (txt.includes('accueil')) {
                    return 'accueil client, téléphone, mails, prise de messages, informations pratiques, échanges professionnels';
                }

                return '';
            }

            const $act   = document.getElementById('activite_professionnelle');
const $poste = document.getElementById('poste_fonction_stagiaire');
const $mots  = document.getElementById('mots_cles_metier');
const $comp  = document.getElementById('competences_cibles');
const $rem   = document.getElementById('remarques_admin');
const $bloc  = document.getElementById('bloc_chatgpt');
const $btnG  = document.getElementById('btn_generer');
const $btnC  = document.getElementById('btn_copier');

const $blocComplementsAdmin = document.getElementById('bloc_complements_admin');
const $blocSortieChatgpt = document.getElementById('bloc_sortie_chatgpt');
const $titreBlocSortie = document.getElementById('titre_bloc_sortie');

function val(id){ return (id.value || '').trim(); }


function updateSortieUi() {
    if ($titreBlocSortie) {
        $titreBlocSortie.textContent = '4. Bloc Programme Audra à copier dans ChatGPT';
    }

    if ($btnG) {
        $btnG.textContent = 'Préparer le programme Audra';
    }

    if ($btnC) {
        $btnC.textContent = 'Copier le programme Audra';
    }
}

function updateSortieUi() {
    if ($titreBlocSortie) {
        $titreBlocSortie.textContent = '4. Bloc Programme Audra à copier dans ChatGPT';
    }

    if ($btnG) {
        $btnG.textContent = 'Préparer le programme Audra';
    }

    if ($btnC) {
        $btnC.textContent = 'Copier le programme Audra';
    }
}

updateSortieUi();

function buildText() {
    return [
`INSTRUCTION DE RÉDACTION

Tu rédiges uniquement le programme final.
Tu ne reproduis pas les consignes.
Tu ne mentionnes ni la fiche source, ni les instructions, ni ton raisonnement.
Tu ne poses aucune question.
Tu n’ajoutes aucun commentaire final.
Tu ne proposes pas d’autre version.
Tu ne termines pas par une phrase du type « je peux aussi… ».
Tu livres directement une version quasi définitive, prête à être copiée dans Word.

RÈGLES DE RÉDACTION

STYLE AUDRA ATTENDU

- Le style attendu est celui d’un programme Audra Langues :
  - ton administratif, pédagogique, sobre et professionnel ;
  - structure stable et normée ;
  - rédaction directement exploitable dans Word ;
  - rubriques homogènes et proches du programme type Audra ;
  - pas de commentaire final, pas de relance, pas de proposition alternative.
- Le document final doit ressembler à un vrai programme de stage Audra et non à une note explicative.
- Si la fiche source mentionne plusieurs stagiaires, rédiger tout le programme au pluriel.
- Si la fiche source mentionne un seul stagiaire, rédiger tout le programme au singulier.
- Ne jamais mélanger singulier et pluriel dans un même programme.
- Ne pas utiliser de markdown parasite dans le résultat final : pas de **, pas de ##, pas de puces de titre en markdown.
- Produire un texte propre, directement exploitable dans Word, avec une mise en forme simple et propre.
- Ne pas afficher d’intitulés ou de symboles techniques inutiles.
- Les rubriques génériques doivent être rédigées dans un style stable, propre et réutilisable.
- Les parties variables doivent être contextualisées avec finesse à partir de la fiche source.
- Respecter strictement la structure du programme type Audra.
- Conserver des intitulés de rubriques proches de ceux du programme type Audra.
- Le programme doit rester professionnel, crédible, sobre et conforme à la formation professionnelle.
- Ne jamais prendre une orientation loisirs, même si certaines motivations réelles du stagiaire peuvent être plus personnelles.
- Utiliser en priorité : l’intitulé du stage, la fonction du stagiaire, l’activité professionnelle / branche, l’objectif général, le niveau de départ et la durée contractuelle.
- Si certaines informations sont absentes ou imprécises, rester prudent et ne pas inventer de détails excessifs.
- Ne pas citer le nom du formateur dans le texte, sauf nécessité exceptionnelle.
- Dans la rubrique « MODALITÉS TECHNIQUES, PÉDAGOGIQUES ET D’ENCADREMENT », ne pas centrer le texte sur le nom du formateur ; décrire plutôt le rôle du formateur, l’accompagnement, l’adaptation au stagiaire, la progression, les mises en situation, l’immersion, le feedback, le suivi individualisé et la contextualisation métier.
- Dans la rubrique « OBJECTIFS PÉDAGOGIQUES DE LA FORMATION », formuler les objectifs sous la forme : « À l’issue de la formation, le stagiaire sera capable de : »
- Rédiger ensuite une liste de 8 à 10 objectifs pédagogiques maximum.
- Ces objectifs doivent être rédigés comme des capacités observables en situation professionnelle, sous forme d’actes de parole contextualisés.
- Éviter les formulations trop générales du type « enrichir son vocabulaire », « gagner en aisance », « maîtriser la grammaire » si elles ne sont pas reliées à une situation professionnelle concrète.
- Privilégier des formulations comme : accueillir, renseigner, demander, reformuler, expliquer, confirmer, rédiger, répondre, présenter, comprendre, traiter, transmettre, relancer.
- Adapter ces objectifs au métier, au niveau de départ et à la durée de formation.
- Le niveau à atteindre, les objectifs pédagogiques, les contenus, les thèmes grammaticaux, les difficultés, les types d’exercices et la progression doivent être déduits de manière réaliste à partir :
  - du niveau de départ,
  - de la durée contractuelle,
  - du contexte professionnel,
  - de la certification visée éventuelle.
- Si le champ « Objectifs visés fin de formation » est vide, ne pas bloquer dessus.
- Dans ce cas, déduire les objectifs pédagogiques, le parcours de formation et la progression à partir du profil professionnel du stagiaire, de son métier, du niveau de départ, du niveau visé, de la durée, de la langue, de la certification éventuelle et des compétences à cibler.
- Ne pas surestimer la progression possible.
- Le niveau à atteindre doit rester crédible, prudent et cohérent avec le niveau de départ, la durée du stage, la langue concernée et l’intensité réelle de la progression possible.
- En matière de certification, ne rien inventer : si le champ « Certification visée » est renseigné dans la fiche source, l’utiliser ; s’il est vide, considérer qu’il n’y a pas de certification de fin de formation.
- Pour certaines langues réputées plus difficiles pour un public francophone, notamment le russe, le chinois, le japonais et l’arabe, adopter une progression plus prudente à niveau d’entrée et volume horaire équivalents.
- Un stagiaire de niveau A2 sur 20 heures ne doit pas avoir le même programme ni le même niveau de sortie qu’un stagiaire de niveau A2 sur 80 heures.
- Le programme doit être rédigé dans un style administratif et pédagogique propre, proche des documents Audra.
- Longueur cible : environ 2 à 3 pages Word maximum.
- Dans la rubrique « PUBLIC VISÉ », rédiger une phrase sobre, claire et fluide, sans surcharge inutile.
- Si le niveau de départ est déjà élevé (par exemple C1 ou plus), éviter les formulations artificielles de type « C1+ » sauf nécessité évidente ; privilégier des formulations comme « consolidation du niveau », « renforcement des compétences », « développement de l’aisance professionnelle » ou toute formulation équivalente plus prudente et crédible.
- Lorsque le métier est spécialisé, contextualiser le programme avec précision, mais sans entrer dans un niveau de technicité excessif ou trop clinique ; rester dans le champ d’une formation linguistique professionnelle.
- Dans la rubrique « MODALITÉS DE FINANCEMENT », employer une formulation administrative simple, fluide et professionnelle, cohérente avec un document Audra.

STRUCTURE ATTENDUE

Le programme doit comprendre au minimum les rubriques suivantes :

PROGRAMME DE STAGE : [Nom du stagiaire ou des stagiaires]

Nature de l’action de formation (art. L6313-1 du Code du travail) :
Action de formation

INTITULÉ DE LA FORMATION

PUBLIC VISÉ

PRÉ-REQUIS

TEST DE POSITIONNEMENT SUR LE CECRL AVANT LA FORMATION

NIVEAU DE DÉPART

NIVEAU À ATTEINDRE

DURÉE en Heures

DATE DE DÉMARRAGE

DATE DE FIN

OBJECTIF OPÉRATIONNEL

OBJECTIFS PÉDAGOGIQUES DE LA FORMATION
(8 à 10 objectifs maximum, rédigés en actes de parole contextualisés au domaine professionnel du client)

PARCOURS DE FORMATION

MODALITÉS TECHNIQUES, PÉDAGOGIQUES ET D’ENCADREMENT

MODALITÉS D’ÉVALUATION EN COURS DE FORMATION

MODALITÉS DE CERTIFICATION
(si aucune certification n’est explicitement renseignée, rester prudent et le signaler proprement sans alourdir)

MODALITÉS DE FINANCEMENT

FICHE SOURCE

N° cours : ${fiche.num_cours}
N° devis : ${fiche.numero_devis}
Date devis : ${fiche.date_devis}

Intitulé du stage : ${fiche.intitule_stage}
Intitulé interne du cours : ${fiche.intitule_interne_cours}

Client : ${fiche.client}
SIREN client : ${fiche.siren_client}
Code NAF : ${fiche.naf_client}

Stagiaire(s) : ${fiche.eleves}
Fonction(s) : ${fiche.fonctions}
Service(s) : ${fiche.services}
Email(s) : ${fiche.emails}

Activité professionnelle / branche : ${val($act)}
Poste / fonction du stagiaire : ${val($poste)}
Mots-clés métier : ${val($mots)}
Compétences à cibler : ${val($comp)}
Remarques admin : ${val($rem)}

Langue / matière : ${fiche.matiere}
Niveau de départ : ${fiche.niveau}
Objectif général : ${fiche.objectif_general}
Certification visée : ${fiche.certification_visee}

Date début : ${fiche.date_debut}
Date fin : ${fiche.date_fin}
Durée contrat (h) : ${fiche.duree_contrat}
Lieu : ${fiche.lieu_affiche}
Modalité : ${fiche.modalite}
Précision lieu : ${fiche.precision_lieu}
Nombre de stagiaires : ${fiche.nombre_de_stagiaires}

Méthode pédagogique : ${fiche.methode_pedagogique}
Objectifs visés fin de formation : ${fiche.objectifs_vises_fin_formation}`
    ].join("\n");
}

function saveLocal() {
                const key = 'AUDRA_PROGRAMMES_CHATGPT_' + fiche.num_cours;
                const payload = {
				activite: val($act),
				poste: val($poste),
				mots: val($mots),
				competences: val($comp),
				remarques: val($rem)
};
                localStorage.setItem(key, JSON.stringify(payload));
            }

            function loadLocal() {
    const key = 'AUDRA_PROGRAMMES_CHATGPT_' + fiche.num_cours;
    try {
        const raw = localStorage.getItem(key);
        if (raw) {
            const data = JSON.parse(raw);
            if (data.activite && !$act.value.trim()) $act.value = data.activite;
			if (data.poste) $poste.value = data.poste;
			if (data.mots) $mots.value = data.mots;
			if (data.competences) $comp.value = data.competences;
			if (data.remarques) $rem.value = data.remarques;
        }
    } catch (e) {}

    if (!$mots.value.trim()) {
        $mots.value = guessKeywords(
            $act.value,
            fiche.intitule_stage,
            fiche.fonctions,
            fiche.client
        );
    }
}


            loadLocal();
            $bloc.value = buildText();

            [$act, $poste, $mots, $comp, $rem].forEach(el => {
                el.addEventListener('input', () => {
                    saveLocal();
                });
            });

            $btnG.addEventListener('click', () => {
                $bloc.value = buildText();
                saveLocal();
            });

            $btnC.addEventListener('click', async () => {
                $bloc.value = buildText();
                saveLocal();
                try {
                    await navigator.clipboard.writeText($bloc.value);
                    alert('Le bloc a été copié dans le presse-papiers.');
                } catch (e) {
                    $bloc.select();
                    document.execCommand('copy');
                    alert('Le bloc a été copié dans le presse-papiers.');
                }
            });
        })();
        </script>
    <?php endif; ?>

</div>
</body>
</html>
