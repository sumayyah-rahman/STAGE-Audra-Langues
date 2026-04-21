<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Contexte élève depuis la session
$contexte = $_SESSION['contexte'] ?? [];

// TODO: clé API à sécuriser côté serveur par le service IT
$apiKey = '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';

if ($apiKey === '') {
    echo json_encode([
        'error' => 'Missing OpenAI key on server.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Lecture du payload JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode([
        'error' => 'Invalid JSON payload.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$message = trim((string)($data['message'] ?? ''));
$theme   = trim((string)($data['theme'] ?? ''));
$grammar = trim((string)($data['grammar'] ?? ''));

if ($message === '') {
    echo json_encode([
        'error' => 'Empty message.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Reset conversation si le thème change
$prevTheme = (string)($_SESSION['ai-theme'] ?? '');
if ($theme !== '' && $prevTheme !== '' && $theme !== $prevTheme) {
    unset($_SESSION['openai_previous_response_id']);
}

if ($theme !== '') {
    $_SESSION['ai-theme'] = $theme;
}

$prevResponseId = (string)($_SESSION['openai_previous_response_id'] ?? '');

// Valeurs sûres par défaut
$contextText = 'No specific professional context provided';
if (!empty($contexte)) {
    $contextText = is_array($contexte) ? implode(', ', $contexte) : (string)$contexte;
}

if ($theme === '') {
    $theme = 'No specific topic chosen yet';
}

if ($grammar === '') {
    $grammar = 'No specific grammar focus chosen';
}

// Instructions système
$systemInstruction = <<<TEXT
You are a welcoming, calm, and encouraging English teacher.

Your role is to help students practise spoken English through natural conversation.

The main objective is to develop confidence, fluency, and oral expression.

In speaking mode, your priority is to maintain the conversation.

You pay attention to the student’s grammatical errors and correct them in a brief, clear, and kind manner only when necessary. If useful, you may invite the student to repeat the corrected sentence.

Encourage the student to use full sentences and to develop their ideas.

The student may choose between:
- conversation practice
- grammar practice
- both

If the student chooses conversation practice:
- focus mainly on speaking naturally about the chosen topic.

If the student chooses grammar practice:
- focus mainly on helping the student practise the chosen grammar point through short spoken interaction.

If the student chooses both:
- keep the conversation on the chosen topic while lightly guiding the student toward the chosen grammar point.

If the student has not yet clearly specified a topic or a grammar point, ask one short clarifying question before continuing.

Do not finish your answer without one short follow-up question or one short invitation to continue.

Continue the conversation by asking a follow-up question that helps the student develop and connect ideas.

Do not propose changing the topic unless the student asks.

Avoid repeating the same response too often.

If the student gives a very short answer, ask them to add one detail, for example:
- why?
- when?
- with whom?
- how often?
- what happened next?

STRICT FORMAT RULES:
- Never respond as a general information assistant.
- Never give bullet points.
- Never give numbered lists.
- Never give long explanations unless the student explicitly asks for one.
- Never give detailed plans in several steps unless the student explicitly asks for them.
- Answer in 2 to 4 sentences maximum.
- Ask only one question at the end.
- Keep every reply short, natural, and adapted to oral conversation.

If the student mentions a food, a place, a hobby, or any other topic, stay in conversation mode.
Do not provide recipes, long factual explanations, or detailed informational content unless the student explicitly asks for them.

If the student chooses grammar practice, keep the conversation centred on the chosen grammar point.
Encourage the student to use it naturally in their answers.
Ask questions that make the student use this grammar point.
Correct the student briefly if needed, but keep the exchange conversational, short, and natural.
Do not turn the exchange into a long grammar lesson.

The chosen conversation topic is: {$theme}.
The student's professional context is: {$contextText}.
The grammar focus for this conversation is: {$grammar}.

Keep the conversation coherent with the chosen topic, the student’s professional context, and the grammar focus when relevant.

If the student describes what they want to practise in an unnatural or unclear way, first reformulate it into a natural conversation topic or communicative situation, then begin the practice.
Do not repeat the student's wording mechanically if it sounds unnatural.
Start the exercise in a natural and realistic way.

When the student gives a topic such as "I want to practise..." or "I want to talk about...", interpret the intention and reformulate it naturally before continuing.
TEXT;

// Payload Responses API
$payload = [
    'model' => 'gpt-4o-mini',
    'instructions' => $systemInstruction,
    'input' => [
        [
            'role' => 'user',
            'content' => $message
        ]
    ]
];

if ($prevResponseId !== '') {
    $payload['previous_response_id'] = $prevResponseId;
}

// Appel OpenAI
$ch = curl_init('https://api.openai.com/v1/responses');

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT => 60
]);

$responseBody = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

if ($responseBody === false || $curlError !== '') {
    echo json_encode([
        'error' => 'Network error while contacting OpenAI.',
        'details' => $curlError
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$responseData = json_decode($responseBody, true);

if (!is_array($responseData)) {
    echo json_encode([
        'error' => 'Invalid JSON returned by OpenAI.',
        'raw' => $responseBody
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($httpCode >= 400) {
    echo json_encode([
        'error' => 'OpenAI API error.',
        'status' => $httpCode,
        'details' => $responseData
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Extraction de la réponse texte
$reply = '';

if (!empty($responseData['output_text']) && is_string($responseData['output_text'])) {
    $reply = trim($responseData['output_text']);
}

if ($reply === '' && !empty($responseData['output']) && is_array($responseData['output'])) {
    foreach ($responseData['output'] as $item) {
        if (!empty($item['content']) && is_array($item['content'])) {
            foreach ($item['content'] as $contentPart) {
                if (
                    isset($contentPart['type'], $contentPart['text']) &&
                    $contentPart['type'] === 'output_text'
                ) {
                    $reply .= $contentPart['text'];
                }
            }
        }
    }
    $reply = trim($reply);
}

if ($reply === '') {
    $reply = "Sorry, I couldn't generate a reply.";
}

// Sauvegarde de l'état conversationnel
if (!empty($responseData['id']) && is_string($responseData['id'])) {
    $_SESSION['openai_previous_response_id'] = $responseData['id'];
}

$_SESSION['last_theme'] = $theme !== 'No specific topic chosen yet' ? $theme : 'Aucun';
$_SESSION['last_grammar'] = $grammar !== 'No specific grammar focus chosen' ? $grammar : 'Aucun';
$_SESSION['last_session_date'] = date('d/m/Y');

// Réponse vers le frontend
echo json_encode([
    'reply' => $reply,
    'response_id' => $responseData['id'] ?? null
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
