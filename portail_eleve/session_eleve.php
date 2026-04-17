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

$studentName   = $_SESSION['student_name']   ?? 'Sumayyah MAR';
$teacherName   = $_SESSION['teacher_name']   ?? 'Munirah MAR';
$numeroCours   = $_SESSION['course_number']  ?? '12345';
$langueEtudiee = $_SESSION['langue_etudiee'] ?? 'English';
$niveauActuel  = $_SESSION['niveau_actuel']  ?? 'B2';
$niveauVise    = $_SESSION['niveau_vise']    ?? 'C1';
$objectifs     = $_SESSION['objectifs']      ?? 'langue professionnelle';
$contexte      = $_SESSION['contexte']       ?? ['médical', 'nourriture', 'avis'];
$typeFormation = $_SESSION['type_formation'] ?? 'Présentiel';

if (!is_array($contexte)) {
    $contexte = [$contexte];
}