<?php
header("Content-Type: application/json; charset=utf-8");

// Connexion SQL
$serverName = "192.168.10.10,1433";
$connectionOptions = [
    "Database" => "AUDRA LANGUES_0895452f-b7c1-4c00-a316-c6a6d0ea4bf4",
    "Uid" => "sa",
    "PWD" => "0ncle!P1ksou",
    "Encrypt" => 0,
    "TrustServerCertificate" => 1
];

$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    echo json_encode(["error" => "Connexion SQL échouée", "details" => sqlsrv_errors()]);
    exit;
}

$sql = "SELECT Id, Contact_Email, Contact_Name, Contact_FirstName
        FROM Colleague
        WHERE Contact_Email IS NOT NULL AND Contact_Email <> ''";

$stmt = sqlsrv_query($conn, $sql);

if (!$stmt) {
    echo json_encode(["error" => "Erreur SQL", "details" => sqlsrv_errors()]);
    sqlsrv_close($conn);
    exit;
}

$utilisateurs = [];

function to_utf8($val) {
    return mb_convert_encoding($val, 'UTF-8', 'auto');
}

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $email  = to_utf8(strtolower(trim($row["Contact_Email"])));
    $nom    = to_utf8(trim($row["Contact_Name"]));
    $prenom = to_utf8(trim($row["Contact_FirstName"]));
    $id     = strtoupper(trim($row["Id"])); // ⚡ le vrai mot de passe attendu

    $utilisateurs[] = [
        "login"  => $email,
        "mdp"    => $id,
        "nom"    => $nom,
        "prenom" => $prenom
    ];
}

sqlsrv_close($conn);

// Génération JSON sûre
$json = json_encode($utilisateurs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    echo "❌ Erreur lors du json_encode : " . json_last_error_msg();
    exit;
}

echo $json;
