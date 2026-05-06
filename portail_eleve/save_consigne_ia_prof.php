<?php
// save_consigne_ia_prof.php -- enregistrement d'une consigne IA prof pour un élève précis

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../app/security/firewall.php';
$config = audra_bootstrap_prof_page();

require_once $config['base_path'] . '/app/config/db_config.php';
$conn = db();

if (!$conn) {
    echo json_encode([
        'success' => false,
        'error'   => 'Connexion SQL impossible'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

audra_guard_prof_page($conn, ['as_json' => true]);

$PROF = strtoupper(trim((string)($_SESSION['display'] ?? '')));
$nom = strtoupper(trim((string)($_SESSION['lastname'] ?? '')));

$profId = (isset($_SESSION['prof_code']) && $_SESSION['prof_code'] !== '')
    ? strtoupper(trim((string)$_SESSION['prof_code']))
    : '';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'error'   => 'Payload JSON invalide.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$idAcces     = (int)($data['id_acces'] ?? 0);
$numeroCours = trim((string)($data['numero_cours'] ?? ''));
$eleve        = trim((string)($data['eleve'] ?? ''));
$consigne     = trim((string)($data['consigne_ia'] ?? ''));

if ($idAcces <= 0) {
    echo json_encode([
        'success' => false,
        'error'   => 'Compte élève introuvable. Impossible d’enregistrer une consigne personnalisée.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($numeroCours === '') {
    echo json_encode([
        'success' => false,
        'error'   => 'Numéro de cours manquant.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($eleve === '') {
    echo json_encode([
        'success' => false,
        'error'   => 'Élève manquant.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($consigne === '') {
    echo json_encode([
        'success' => false,
        'error'   => 'Consigne manquante.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($consigne, 'UTF-8') > 4000) {
    echo json_encode([
        'success' => false,
        'error'   => 'La consigne est trop longue. Merci de la raccourcir.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/*
|--------------------------------------------------------------------------
| 1. Vérifier que le compte élève existe bien
|--------------------------------------------------------------------------
*/

$sqlCheckAcces = "
    SELECT TOP 1
        id,
        student_name,
        numero_cours,
        is_active
    FROM dbo.AudraWeb_Eleve_Acces
    WHERE id = ?
      AND is_active = 1
";

$stmtCheckAcces = sqlsrv_query($conn, $sqlCheckAcces, [$idAcces]);

if (!$stmtCheckAcces) {
    echo json_encode([
        'success' => false,
        'error'   => 'Erreur SQL lors de la vérification du compte élève.',
        'details' => sqlsrv_errors()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$accesRow = sqlsrv_fetch_array($stmtCheckAcces, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtCheckAcces);

if (!$accesRow) {
    echo json_encode([
        'success' => false,
        'error'   => 'Compte élève actif introuvable.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$numeroCoursAcces = trim((string)($accesRow['numero_cours'] ?? ''));

if ($numeroCoursAcces !== $numeroCours) {
    echo json_encode([
        'success' => false,
        'error'   => 'Le compte élève ne correspond pas au numéro de cours sélectionné.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/*
|--------------------------------------------------------------------------
| 2. Vérifier que le cours appartient bien au prof connecté
|--------------------------------------------------------------------------
*/

$profSearch = '%' . trim($nom !== '' ? $nom : $PROF) . '%';

$sqlCheckCours = "
    SELECT TOP 1
        LTRIM(RTRIM(CAST([N° cours] AS nvarchar(40)))) AS id_cours
    FROM dbo._PROG_Analyse_Planning_ClientEleves
    WHERE UPPER(LTRIM(RTRIM([Formateur]))) LIKE UPPER(?)
      AND LTRIM(RTRIM(CAST([N° cours] AS nvarchar(40)))) = ?
";

$stmtCheckCours = sqlsrv_query($conn, $sqlCheckCours, [$profSearch, $numeroCours]);

if (!$stmtCheckCours) {
    echo json_encode([
        'success' => false,
        'error'   => 'Erreur SQL lors de la vérification du cours.',
        'details' => sqlsrv_errors()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$coursRow = sqlsrv_fetch_array($stmtCheckCours, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtCheckCours);

if (!$coursRow) {
    echo json_encode([
        'success' => false,
        'error'   => 'Ce cours ne semble pas appartenir au professeur connecté.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/*
|--------------------------------------------------------------------------
| 3. Désactiver les anciennes consignes actives de cet élève
|--------------------------------------------------------------------------
| On garde l'historique, mais seule la dernière consigne reste active.
|--------------------------------------------------------------------------
*/

$sqlDisableOld = "
    UPDATE dbo.AudraWeb_Eleve_Consigne
    SET is_active = 0
    WHERE id_acces = ?
      AND is_active = 1
";

$stmtDisable = sqlsrv_query($conn, $sqlDisableOld, [$idAcces]);

if (!$stmtDisable) {
    echo json_encode([
        'success' => false,
        'error'   => 'Erreur SQL lors de la désactivation des anciennes consignes.',
        'details' => sqlsrv_errors()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

sqlsrv_free_stmt($stmtDisable);

/*
|--------------------------------------------------------------------------
| 4. Insérer la nouvelle consigne personnalisée
|--------------------------------------------------------------------------
*/

$sqlInsert = "
    INSERT INTO dbo.AudraWeb_Eleve_Consigne (
        id_acces,
        numero_cours,
        eleve,
        consigne_ia,
        prof,
        prof_code,
        date_creation,
        is_active
    )
    VALUES (?, ?, ?, ?, ?, ?, GETDATE(), 1)
";

$paramsInsert = [
    $idAcces,
    $numeroCours,
    $eleve,
    $consigne,
    $PROF,
    $profId
];

$stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);

if (!$stmtInsert) {
    echo json_encode([
        'success' => false,
        'error'   => 'Erreur SQL lors de l’enregistrement de la consigne.',
        'details' => sqlsrv_errors()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

sqlsrv_free_stmt($stmtInsert);

echo json_encode([
    'success'      => true,
    'message'      => 'Consigne IA enregistrée.',
    'id_acces'     => $idAcces,
    'numero_cours' => $numeroCours,
    'eleve'        => $eleve,
    'prof'         => $PROF,
    'prof_code'    => $profId
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;