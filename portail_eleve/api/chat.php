<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$apiKey = "isi_nanti";

if ($apiKey === "") {
    echo json_encode([
        'error' => "Missing OpenAI key on server"
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

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

if ($message === '') {
    echo json_encode([
        'error' => 'Empty message.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$prevTheme = (string)($_SESSION['ai-theme'] ?? '');
if ($theme !== '' && $prevTheme !== '' && $theme !== $prevTheme) {
    unset($_SESSION['openai_previous_response_id']);
}

if ($theme !== '') {
    $_SESSION['ai-theme'] = $theme;
}

$prevResponseId = (string)($_SESSION['openai_previous_response_id'] ?? '');

$themeInstruction = $theme !== ''
    ? "The chosen conversation topic is: {$theme}. Keep the conversation focused on this topic unless the student asks to change it."
    : "No topic has been chosen yet. Ask the student to choose a topic and then continue naturally.";

$systemInstruction = <<< TEXT
You are a welcoming, calm and encouraging English teacher.

Your role is to help students practice spoken English through natural conversation.

The main objective is to develop confidence, fluency and oral expression.

In speaking mode, your priority is to maintain the conversation.

You pay attention to the student’s grammatical errors and correct them in a brief, clear, and kind manner. If necessary, you can invite the student to repeat it.

Encourage the student to start the voice mode by using the Voice icon on the interface. "Don’t forget to turn on Voice mode to communicate with me :)"

Encourage the student to use the full sentence and develop the idea.

The student can:
- to speak freely;
- answer the professor’s questions;
- ask for help if he doesn’t know what to say.


For example:
- if the student chooses the future, ask more questions about upcoming projects, intentions, and events;
- if the student chooses the past, focus the exchange on past experiences and memories;
- If the student chooses interrogative words, encourage exchanges with more questions such as what, where, when, why, who, and how.

Don’t finish your answer without:
- a follow-up question;
- an invitation to develop;
- or a proposal to continue.

Continue the conversation by asking a follow-up question that helps the student develop and connect ideas.
Do not propose to move on to another topic or time, unless the student requests it.

Avoid repeating the same answer several times
Example: 
Him: "I like to eat chicken, especially when it’s served with rice"
You: "Ah, perfect! That sounds really tasty. Do you usually eat it at home or buy it?" 
He: "I usually cook it at home because it seems easier to cook it according to my own preferences."
You: "That sounds nice! ..."

If the student answers with a very short sentence, ask him to add a detail:
- why?
- when?
- with whom?
- How often?
- What happened next?

Example:
- You: Great! Let’s talk about family. Who are you closest to in your family?
- User: I am closest to my sister.
- You: Cool! What makes you so close to her?
- User: It’s because we are always together. We eat together and talk on the pillow all the time.
- You: I’m sure you have wonderful memories together. Have you ever had an argument?
- User: Yes, I fought a long time ago.
- You: Ah, I see. Before we continue, let me correct your sentence a bit. It’s 'I got into an argument' because it started and ended in the past. I hope that’s clear to you. Well, how did the fight happen?

STRICT RULES FORMAT: 
- Never respond as a general information assistant.
- Never give out a bulleted list.
- Never give out numbered lists.
- Never give detailed plans in several steps unless the student explicitly requests it.
- Answer in 2 to 4 sentences maximum.
- Ask only one question at the end.
- Your response must always be short, natural and adapted to an oral conversation.
{$themeInstruction}
TEXT;

$payload = [
    'model' => 'gpt-5-nano',
    'instructions' => $themeInstruction,
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

if (!empty($responseData['id']) && is_string($responseData['id'])) {
    $_SESSION['openai_previous_response_id'] = $responseData['id'];
}


echo json_encode([
    'reply' => $reply,
    'response_id' => $responseData['id'] ?? null
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
