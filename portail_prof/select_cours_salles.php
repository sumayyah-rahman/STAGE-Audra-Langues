<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../app/security/firewall.php';
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

audra_guard_prof_page($conn);

$PROF = strtoupper(trim((string)($_SESSION['display'] ?? '')));
$nom = strtoupper(trim((string)($_SESSION['lastname'] ?? '')));
$profId = (isset($_SESSION['prof_code']) && $_SESSION['prof_code'] !== '')
    ? strtoupper(trim((string)$_SESSION['prof_code']))
    : '';

$sql = "
SELECT DISTINCT TOP 200
    [N° cours] AS id_cours,
    [Elève] AS eleve
FROM [_PROG_Analyse_Planning_ClientEleves]
WHERE UPPER(LTRIM(RTRIM([Formateur]))) LIKE UPPER(?)
  AND [Date de planning] >= DATEADD(MONTH, -6, CAST(GETDATE() AS date))
ORDER BY [N° cours] DESC
";

$params = ['%' . trim($nom) . '%'];
$stmt = sqlsrv_query($conn, $sql, $params);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'error'   => 'Erreur SQL select_cours_salles',
        'details' => sqlsrv_errors()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'error'   => 'Erreur SQL select_cours_salles'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$coursMap = [];

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $idCours = (int)($row['id_cours'] ?? 0);
    $eleve   = trim((string)($row['eleve'] ?? ''));

    if ($idCours <= 0) {
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

$cours = array_values($coursMap);

foreach ($cours as &$c) {
    $c['eleve'] = implode(' / ', $c['eleves']);
    unset($c['eleves']);
}
unset($c);

sqlsrv_free_stmt($stmt);

echo json_encode([
    'success' => true,
    'prof'    => $PROF,
    'prof_code' => $profId,
    'cours'   => $cours
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
