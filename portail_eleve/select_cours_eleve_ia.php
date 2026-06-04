<?php
// select_cours_eleve_ia.php -- endpoint JSON pour consigne IA prof
// action=cours  -> retourne les cours du prof connecté
// action=eleves -> retourne les élèves du cours sélectionné + id_acces si compte élève trouvé

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

audra_guard_prof_page($conn, [
    'as_json'             => true,
    'allow_blocked'       => true,
    'allow_portal_closed' => true,
    'allow_correction'    => true,
]);

$PROF = strtoupper(trim((string)($_SESSION['display'] ?? '')));
$nom = strtoupper(trim((string)($_SESSION['lastname'] ?? '')));

$profId = (isset($_SESSION['prof_code']) && $_SESSION['prof_code'] !== '')
    ? strtoupper(trim((string)$_SESSION['prof_code']))
    : '';

$action = trim((string)($_GET['action'] ?? 'cours'));

if ($nom === '' && $PROF === '' && $profId === '') {
    echo json_encode([
        'success' => false,
        'error'   => 'Professeur non identifié en session.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$profSearch = '%' . trim($nom !== '' ? $nom : $PROF) . '%';

if ($action === 'cours') {
    $sql = "
        SELECT DISTINCT TOP 200
            LTRIM(RTRIM(CAST([N° cours] AS nvarchar(40)))) AS id_cours,
            LTRIM(RTRIM(COALESCE([Elève], ''))) AS eleve
        FROM dbo._PROG_Analyse_Planning_ClientEleves
        WHERE UPPER(LTRIM(RTRIM([Formateur]))) LIKE UPPER(?)
          AND [Date de planning] >= DATEADD(MONTH, -6, CAST(GETDATE() AS date))
          AND [N° cours] IS NOT NULL
        ORDER BY id_cours DESC
    ";

    $stmt = sqlsrv_query($conn, $sql, [$profSearch]);

    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'error'   => 'Erreur SQL select_cours_eleve_ia action=cours',
            'details' => sqlsrv_errors()
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $coursMap = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $idCours = trim((string)($row['id_cours'] ?? ''));
        $eleve   = trim((string)($row['eleve'] ?? ''));

        if ($idCours === '') {
            continue;
        }

        if (!isset($coursMap[$idCours])) {
            $coursMap[$idCours] = [
                'id_cours' => $idCours,
                'eleves'   => []
            ];
        }

        if ($eleve !== '' && !in_array($eleve, $coursMap[$idCours]['eleves'], true)) {
            $coursMap[$idCours]['eleves'][] = $eleve;
        }
    }

    sqlsrv_free_stmt($stmt);

    $cours = array_values($coursMap);

    foreach ($cours as &$c) {
        $c['eleve'] = implode(' / ', $c['eleves']);

        if ($c['eleve'] === '') {
            $c['eleve'] = 'Élève à préciser';
        }

        unset($c['eleves']);
    }
    unset($c);

    echo json_encode([
        'success'   => true,
        'prof'      => $PROF,
        'prof_code' => $profId,
        'cours'     => $cours
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'eleves') {
    $idCours = trim((string)($_GET['id_cours'] ?? ''));

    if ($idCours === '') {
        echo json_encode([
            'success' => false,
            'error'   => 'Numéro de cours manquant.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "
        SELECT DISTINCT
            LTRIM(RTRIM(COALESCE(P.[Elève], ''))) AS nom_eleve,
            A.id AS id_acces
        FROM dbo._PROG_Analyse_Planning_ClientEleves P
        LEFT JOIN dbo.AudraWeb_Eleve_Acces A
            ON LTRIM(RTRIM(CAST(A.numero_cours AS nvarchar(40)))) = LTRIM(RTRIM(CAST(P.[N° cours] AS nvarchar(40))))
           AND UPPER(LTRIM(RTRIM(A.student_name))) = UPPER(LTRIM(RTRIM(P.[Elève])))
           AND A.is_active = 1
        WHERE UPPER(LTRIM(RTRIM(P.[Formateur]))) LIKE UPPER(?)
          AND LTRIM(RTRIM(CAST(P.[N° cours] AS nvarchar(40)))) = ?
          AND P.[Elève] IS NOT NULL
          AND LTRIM(RTRIM(P.[Elève])) <> ''
        ORDER BY nom_eleve ASC
    ";

    $stmt = sqlsrv_query($conn, $sql, [$profSearch, $idCours]);

    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'error'   => 'Erreur SQL select_cours_eleve_ia action=eleves',
            'details' => sqlsrv_errors()
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $elevesMap = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $nomEleve = trim((string)($row['nom_eleve'] ?? ''));
        $idAcces = isset($row['id_acces']) ? (int)$row['id_acces'] : 0;

        if ($nomEleve === '') {
            continue;
        }

        if (!isset($elevesMap[$nomEleve])) {
            $elevesMap[$nomEleve] = [
                'nom_eleve' => $nomEleve,
                'id_acces'  => $idAcces
            ];
        }

        if ((int)$elevesMap[$nomEleve]['id_acces'] === 0 && $idAcces > 0) {
            $elevesMap[$nomEleve]['id_acces'] = $idAcces;
        }
    }

    sqlsrv_free_stmt($stmt);

    $eleves = array_values($elevesMap);

    echo json_encode([
        'success'   => true,
        'prof'      => $PROF,
        'prof_code' => $profId,
        'id_cours'  => $idCours,
        'eleves'    => $eleves
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'success' => false,
    'error'   => 'Action inconnue.'
], JSON_UNESCAPED_UNICODE);
exit;