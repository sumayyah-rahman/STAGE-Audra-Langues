<?php
// save_consigne_ia_prof.php -- enregistrement d'une consigne IA prof pour un élève

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

$numeroCours = trim((string)($data['numero_cours'] ?? ''));
$eleve        = trim((string)($data['eleve'] ?? ''));
$consigne     = trim((string)($data['consigne_ia'] ?? ''));

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

// Ni apa eh, nanti tengok balik

$nom = strtoupper(trim((string)($_SESSION['lastname'] ?? '')));
$profSearch = '%' . trim($nom !== '' ? $nom : $PROF) . '%';

$sqlCheck = "
    SELECT TOP 1
        LTRIM(RTRIM(CAST([N° cours] AS nvarchar(40)))) AS id_cours
    FROM dbo._PROG_Analyse_Planning_ClientEleves
    WHERE UPPER(LTRIM(RTRIM([Formateur]))) LIKE UPPER(?)
      AND LTRIM(RTRIM(CAST([N° cours] AS nvarchar(40)))) = ?
";

$stmtCheck = sqlsrv_query($conn, $sqlCheck, [$profSearch, $numeroCours]);

if (!$stmtCheck) {
    echo json_encode([
        'success' => false,
        'error'   => 'Erreur SQL lors de la vérification du cours.',
        'details' => sqlsrv_errors()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$checkRow = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtCheck);

if (!$checkRow) {
    echo json_encode([
        'success' => false,
        'error'   => 'Ce cours ne semble pas appartenir au professeur connecté.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// On garde l'historique

$sqlDisableOld = "
    UPDATE dbo.AudraWeb_Eleve_Consigne
    SET is_active = 0
    WHERE numero_cours = ?
      AND eleve = ?
      AND is_active = 1
";

$stmtDisable = sqlsrv_query($conn, $sqlDisableOld, [$numeroCours, $eleve]);

if (!$stmtDisable) {
    echo json_encode([
        'success' => false,
        'error'   => 'Erreur SQL lors de la désactivation des anciennes consignes.',
        'details' => sqlsrv_errors()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

sqlsrv_free_stmt($stmtDisable);

// Insertion de la nouvelle consigne


$sqlInsert = "
    INSERT INTO dbo.AudraWeb_Eleve_Consigne (
        numero_cours,
        eleve,
        consigne_ia,
        prof,
        prof_code,
        date_creation,
        is_active
    )
    VALUES (?, ?, ?, ?, ?, GETDATE(), 1)
";

$paramsInsert = [
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
    'numero_cours' => $numeroCours,
    'eleve'        => $eleve,
    'prof'         => $PROF,
    'prof_code'    => $profId
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;