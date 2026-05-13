<?php
// get_bilan_ia_eleve.php -- retourne l'historique IA d'un élève pour le prof connecté

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

$idAcces = (int)($_GET['id_acces'] ?? 0);

if ($idAcces <= 0) {
    echo json_encode([
        'success' => false,
        'error'   => 'Compte élève manquant.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$profSearch = '%' . trim($nom !== '' ? $nom : $PROF) . '%';

$sqlCheck = "
    SELECT TOP 1
        A.id,
        A.student_name,
        A.numero_cours
    FROM dbo.AudraWeb_Eleve_Acces A
    INNER JOIN dbo._PROG_Analyse_Planning_ClientEleves P
        ON LTRIM(RTRIM(CAST(A.numero_cours AS nvarchar(40)))) =
           LTRIM(RTRIM(CAST(P.[N° cours] AS nvarchar(40))))
       AND UPPER(LTRIM(RTRIM(A.student_name))) =
           UPPER(LTRIM(RTRIM(P.[Elève])))
    WHERE A.id = ?
      AND A.is_active = 1
      AND UPPER(LTRIM(RTRIM(P.[Formateur]))) LIKE UPPER(?)
";

$stmtCheck = sqlsrv_query($conn, $sqlCheck, [$idAcces, $profSearch]);

if (!$stmtCheck) {
    echo json_encode([
        'success' => false,
        'error'   => 'Erreur SQL lors de la vérification élève/prof.',
        'details' => sqlsrv_errors()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$eleveRow = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtCheck);

if (!$eleveRow) {
    echo json_encode([
        'success' => false,
        'error'   => 'Cet élève ne semble pas appartenir au professeur connecté.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Lecture d'historique IA
$sqlBilan = "
    SELECT TOP 20
        id,
        id_acces,
        numero_cours,
        eleve,
        theme,
        grammar,
        langue_etudiee,
        observation,
        points_forts,
        points_faibles,
        point_a_renforcer,
        exemple_a_retravailler,
        date_session
    FROM dbo.AudraWeb_Eleve_Sessions_IA
    WHERE id_acces = ?
    ORDER BY date_session DESC, id DESC
";

$stmtBilan = sqlsrv_query($conn, $sqlBilan, [$idAcces]);

if (!$stmtBilan) {
    echo json_encode([
        'success' => false,
        'error'   => 'Erreur SQL lors du chargement du bilan IA.',
        'details' => sqlsrv_errors()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$sessions = [];

while ($row = sqlsrv_fetch_array($stmtBilan, SQLSRV_FETCH_ASSOC)) {
    $dateSession = '';

    if (isset($row['date_session'])) {
        if ($row['date_session'] instanceof DateTimeInterface) {
            $dateSession = $row['date_session']->format('d/m/Y H:i');
        } else {
            $tmp = date_create((string)$row['date_session']);
            $dateSession = $tmp ? $tmp->format('d/m/Y H:i') : (string)$row['date_session'];
        }
    }

    $sessions[] = [
        'id'                     => (int)($row['id'] ?? 0),
        'id_acces'               => (int)($row['id_acces'] ?? 0),
        'numero_cours'           => (string)($row['numero_cours'] ?? ''),
        'eleve'                  => (string)($row['eleve'] ?? ''),
        'theme'                  => (string)($row['theme'] ?? ''),
        'grammar'                => (string)($row['grammar'] ?? ''),
        'langue_etudiee'         => (string)($row['langue_etudiee'] ?? ''),
        'observation'            => (string)($row['observation'] ?? ''),
        'points_forts'           => (string)($row['points_forts'] ?? ''),
        'points_faibles'         => (string)($row['points_faibles'] ?? ''),
        'point_a_renforcer'      => (string)($row['point_a_renforcer'] ?? ''),
        'exemple_a_retravailler' => (string)($row['exemple_a_retravailler'] ?? ''),
        'date_session'           => $dateSession
    ];
}

sqlsrv_free_stmt($stmtBilan);

echo json_encode([
    'success'   => true,
    'prof'      => $PROF,
    'prof_code' => $profId,
    'eleve'     => [
        'id_acces'     => (int)($eleveRow['id'] ?? 0),
        'student_name' => (string)($eleveRow['student_name'] ?? ''),
        'numero_cours' => (string)($eleveRow['numero_cours'] ?? '')
    ],
    'sessions'  => $sessions
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
