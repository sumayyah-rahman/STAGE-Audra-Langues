<?php 
// select_cours_eleve_ia.php -- endpoint JSON pour consigne IA prof

declare(strict_types=1);

header('Content-Type: application/json');

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

$action = trim((string)($_GET['action'] ?? 'cours'));

if ($nom === '' && $PROF === '' && $profId === '') {
	echo json_encode([
	'success' => false,
	'error' => 'Professeur non identifié en session.'
	], JSON_UNESCAPED_UNICODE);
	exit;
}

$profSearch = '%' . trim($nom !== '' ? $nom : $PROF) . '%';

if ($action === 'cours') {
	$sql = "
		SELECT DISTINCT TOP 200
			LTRIM(RTRIM(CAST([N° cours] AS nvarchar(40)))) AS id_cours,
			LTRIM(RTRIM(CAST([Elève] AS nvarchar(255)))) AS eleve
		FROM [_PROG_Analyse_Planning_ClientEleves]
		WHERE UPPER(LTRIM(RTRIM([Formateur]))) LIKE UPPER(?)
		  AND [Date de planning] >= DATEADD(MONTH, -6, CAST(GETDATE() AS date))
		  AND [N° cours] IS NOT NULL
		ORDER BY [N° cours] DESC
		";
		
	$params = [$profSearch];
	$stmt = sqlsrv_query($conn, $sql, $params);

	if (!$stmt) {
		echo json_encode([
			'success' => false,
			'error'   => 'Erreur SQL select_cours_salles',
			'details' => sqlsrv_errors()
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}

	$coursMap = [];

	while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
		$idCours = (int)($row['id_cours'] ?? 0);
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
		unset($c['eleves']);
	}
	unset($c);

	echo json_encode([
		'success' => true,
		'prof'    => $PROF,
		'prof_code' => $profId,
		'cours'   => $cours
	], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

if ($action === 'eleves') {
	$idCours = trim((string)($_GET['id_cours'] ?? ''));
	
	if ($idCours === '') {
		echo json_encode([
			'success' => false,
			'error' => 'Numéro de cours manquant.'
		], JSON_UNESCAPED_UNICODE);
		exit;
	}
	
	$sql = "
		SELECT DISTINCT TOP 200
			LTRIM(RTRIM(CAST([Elève] AS nvarchar(255)))) AS nom_eleve
		FROM [_PROG_Analyse_Planning_ClientEleves]
		WHERE UPPER(LTRIM(RTRIM([Formateur]))) LIKE UPPER(?)
		  AND LTRIM(RTRIM(CAST([N° cours] AS nvarchar(40)))) = ?
		  AND [Elève] IS NOT NULL
		  AND [N° cours] IS NOT NULL
		  AND LTRIM(RTRIM([Elève])) <> ''
		ORDER BY nom_eleve DESC
		";
		
	$params = [$profSearch, $idCours];
	$stmt = sqlsrv_query($conn, $sql, $params);

	if (!$stmt) {
		echo json_encode([
			'success' => false,
			'error'   => 'Erreur SQL select_cours_salles action=eleves',
			'details' => sqlsrv_errors()
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}

	$eleves = [];

	while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
		$nomEleve   = trim((string)($row['nom_eleve'] ?? ''));

		if ($nomEleve !== '') {
			$eleves[] = [
				'nom_eleve' => $nomEleve
			];
		}
	}
	
	sqlsrv_free_stmt($stmt);
	

	echo json_encode([
		'success' => true,
		'prof'    => $PROF,
		'prof_code' => $profId,
		'id_cours'   => $idCours,
		'eleves' => $eleves
	], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);	
	exit;
}

echo json_encode ([
	'success' => false,
	'error' => 'Action inconnue.'
	], JSON_UNESCAPED_UNICODE);
	exit;