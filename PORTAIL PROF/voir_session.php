<?php
// voir_session.php — Affichage brut de la session (à réserver au DEV)

declare(strict_types=1);
session_start();

header('Content-Type: text/plain; charset=utf-8');

echo "=== CONTENU DE \$_SESSION ===\n\n";
print_r($_SESSION);
