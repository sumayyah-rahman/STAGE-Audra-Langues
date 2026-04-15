<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode([
        'error' => 'Invalid JSON payload.'
    ]);
    exit;
}

$message = trim((string)($data['message'] ?? ''));

if ($message === '') {
    echo json_encode([
        'error' => 'Empty message.'
    ]);
    exit;
}

echo json_encode([
    'reply' => "Test backend OK. You said: " . $message
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);