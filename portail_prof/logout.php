<?php
// logout.php — Déconnexion propre du portail prof

declare(strict_types=1);
session_start();

session_unset();
session_destroy();

header('Location: portail_prof.php');
exit;
