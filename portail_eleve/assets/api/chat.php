<?php
// assets/api/chat.php - la configuration de l'API

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../CVT/_db.php';
$pdo = pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Contexte élève depuis la session
$contexte = $_SESSION['contexte'] ?? [];

// TO DO: clé API à sécuriser côté serveur par le service IT
$apiKey = 'key'; 

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

$action  = trim((string)($data['action'] ?? ''));
$message = trim((string)($data['message'] ?? ''));
$theme   = trim((string)($data['theme'] ?? ''));
$grammar = trim((string)($data['grammar'] ?? ''));
$history = $data['history'] ?? [];

if ($action !== 'end_session' && $message === '') {
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

if ($action === 'end_session') {
    $historyText = '';
    $langueEtudiee = $_SESSION['langue_etudiee'] ?? 'English';
	$historyJson = is_array($history)
		? json_encode($history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
		: null;

    if (is_array($history)) {
        foreach ($history as $entry) {
            $sender = trim((string)($entry['sender'] ?? ''));
            $text   = trim((string)($entry['text'] ?? ''));

            if ($text !== '') {
                $historyText .= strtoupper($sender) . ': ' . $text . "\n";
            }
        }
    }

    $analysisInstruction = <<<TEXT
You are an {$langueEtudiee} teacher.

Based on this student's {$langueEtudiee} practice session, produce a useful pedagogical report for the real teacher.

Return:
1. one clear observation in French
2. the student's strengths in French
3. the student's weaknesses in French
4. one priority point to improve in French
5. one short example based on an actual mistake or weakness observed during the session, with a corrected version in the target language

Important language rules:
- The pedagogical comments must be written in French.
- The example must be linked to a real mistake, hesitation, weak point, or difficulty observed in the session.
- If no clear mistake was made, provide one useful model sentence related to the chosen theme or grammar.
- Any example sentence, correction example, model sentence, or mini-dialogue must be written in the target language: {$langueEtudiee}.
- Do not write examples in French unless the target language is French.

Rules:
- be pedagogical
- be kind
- be concrete
- be useful for the real teacher
- mention the chosen theme or grammar if relevant
- do not exaggerate the student's level
- do not write anything else
- output only valid JSON with exactly these keys:
observation
points_forts
points_faibles
point_a_renforcer
exemple_a_retravailler
TEXT;

	$analysisPayload = [
		'model' => 'gpt-4o-mini',
		'instructions' => $analysisInstruction,
		'input' => $analysisInput,
		'text' => [
			'format' => [
				'type' => 'json_schema',
				'name' => 'session_observation',
				'schema' => [
					'type' => 'object',
					'properties' => [
						'observation' => [
							'type' => 'string'
						],
						'points_forts' => [
							'type' => 'string'
						],
						'points_faibles' => [
							'type' => 'string'
						],
						'point_a_renforcer' => [
							'type' => 'string'
						],
						'exemple_a_retravailler' => [
							'type' => 'string'
						]
					],
					'required' => [
						'observation',
						'points_forts',
						'points_faibles',
						'point_a_renforcer',
						'exemple_a_retravailler'
						],
					'additionalProperties' => false
				],
				'strict' => true
			]
		]
	];

    $ch = curl_init('https://api.openai.com/v1/responses');

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($analysisPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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

    $analysisText = '';

    if (!empty($responseData['output_text']) && is_string($responseData['output_text'])) {
        $analysisText = trim($responseData['output_text']);
    }

    if ($analysisText === '' && !empty($responseData['output']) && is_array($responseData['output'])) {
        foreach ($responseData['output'] as $item) {
            if (!empty($item['content']) && is_array($item['content'])) {
                foreach ($item['content'] as $contentPart) {
                    if (
                        isset($contentPart['type'], $contentPart['text']) &&
                        $contentPart['type'] === 'output_text'
                    ) {
                        $analysisText .= $contentPart['text'];
                    }
                }
            }
        }
        $analysisText = trim($analysisText);
    }

    if ($analysisText === '') {
        echo json_encode([
            'error' => 'Empty analysis returned by OpenAI.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $analysisJson = json_decode($analysisText, true);

	if (!is_array($analysisJson)) {
		echo json_encode([
			'error' => 'Invalid analysis JSON.',
			'raw' => $analysisText
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}

    $observation = trim((string)($analysisJson['observation'] ?? ''));
	$pointsForts = trim((string)($analysisJson['points_forts'] ?? ''));
	$pointsFaibles = trim((string)($analysisJson['points_faibles'] ?? ''));
    $pointARenforcer = trim((string)($analysisJson['point_a_renforcer'] ?? ''));
	$exempleARetravailler = trim((string)($analysisJson['exemple_a_retravailler'] ?? ''));

	if ($observation === ''){
		$observation = 'Aucune observation générée.';
	}
	
	if ($pointsForts === '') {
		$pointsForts = 'Aucun point fort spécifique identifié.';
	}
	
	if ($pointsFaibles === '') {
		$pointsFaibles = 'Aucun point faible spécifique identifié.';
	}
	
	if ($pointARenforcer === '') {
		$pointARenforcer = 'Aucun point prioritaire identifié.';
	}
	
	if ($exempleARetravailler === '') {
		$exempleARetravailler = 'Aucun exemple spécifique généré.';
	}
	
	$progressionNoteDetaillee = 
		"Observation : " . $observation . "\n\n" .
		"Points forts : " . $pointsForts . "\n\n" .
		"Points faibles : " . $pointsFaibles . "\n\n" .
		"Exemple à retravailler : " . $exempleARetravailler;

    $idAcces = (int)($_SESSION['id_acces'] ?? 0);

    if ($idAcces > 0) {
        $sqlCheckSuivi = "
            SELECT TOP 1 id
            FROM dbo.AudraWeb_Eleve_Suivi_IA
            WHERE id_acces = ?
        ";
        $stCheckSuivi = $pdo->prepare($sqlCheckSuivi);
        $stCheckSuivi->execute([$idAcces]);
        $existingSuivi = $stCheckSuivi->fetch(PDO::FETCH_ASSOC);

        if ($existingSuivi) {
            $sqlUpdateSuivi = "
                UPDATE dbo.AudraWeb_Eleve_Suivi_IA
                SET
                    last_theme = COALESCE(?, last_theme),
                    last_grammar = COALESCE(?, last_grammar),
                    last_session_date = GETDATE(),
                    point_a_renforcer = ?,
                    progression_note = ?
                WHERE id_acces = ?
            ";
            $stUpdateSuivi = $pdo->prepare($sqlUpdateSuivi);
            $stUpdateSuivi->execute([
                $theme !== 'No specific topic chosen yet' ? $theme : null,
                $grammar !== 'No specific grammar focus chosen' ? $grammar : null,
                $pointARenforcer !== '' ? $pointARenforcer : null,
				$progressionNoteDetaillee !== '' ? $progressionNoteDetaillee : null,
                $idAcces
            ]);
        } else {
            $sqlInsertSuivi = "
                INSERT INTO dbo.AudraWeb_Eleve_Suivi_IA (
                    id_acces,
                    last_theme,
                    last_grammar,
                    last_session_date,
                    point_a_renforcer,
                    progression_note
                )
                VALUES (?, ?, ?, GETDATE(), ?, ?)
            ";
            $stInsertSuivi = $pdo->prepare($sqlInsertSuivi);
            $stInsertSuivi->execute([
                $idAcces,
                $theme !== 'No specific topic chosen yet' ? $theme : null,
                $grammar !== 'No specific grammar focus chosen' ? $grammar : null,
                $pointARenforcer !== '' ? $pointARenforcer : null,
				$progressionNoteDetaillee !== '' ? $progressionNoteDetaillee : null,
            ]);
        }
		
		$numeroCoursSession = trim((string)($_SESSION['course_number'] ?? ''));
		$studentNameSession = trim((string)($_SESSION['student_name'] ?? ''));
		
		$sqlInsertSession = "
			INSERT INTO dbo.AudraWeb_Eleve_Sessions_IA (
				id_acces,
				numero_cours,
				eleve,
				theme,
				grammar,
				langue_etudiee,
				observation,
				points_forts,
				points_faibles,
				point_a_renforcer,
				exemple_a_retravailler,
				session_history,
				date_session
			)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())
		";
		
		$stInsertSession = $pdo->prepare($sqlInsertSession);
		$stInsertSession->execute([
			$idAcces,
			$numeroCoursSession !== '' ? $numeroCoursSession : null,
			$studentNameSession !== '' ? $studentNameSession : null,
			$theme !== 'No specific topic chosen yet' ? $theme : null,
			$grammar !== 'No specific grammar focus chosen' ? $grammar : null,
			$langueEtudiee !== '' ? $langueEtudiee : null,
			$observation,
			$pointsForts,
			$pointsFaibles,
			$pointARenforcer,
			$exempleARetravailler,
			$historyJson
		]);
    }

    echo json_encode([
        'success' => true,
        'observation' => $observation,
		'points_forts' => $pointsForts,
		'points_faibles' => $pointsFaibles,
        'point_a_renforcer' => $pointARenforcer,
		'exemple_a_retravailler' => $exempleARetravailler
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Ambil data dari session.php
$langueEtudiee = $_SESSION['langue_etudiee'] ?? 'English';
$niveauActuel = $_SESSION['niveau_actuel'] ?? 'A préciser';
$objectif = $_SESSION['objectif'] ?? 'Improve professional communication';
$contexte = $_SESSION['contexte'] ?? [];
$contexteText = is_array($contexte) ? implode(', ', $contexte) : (string)$contexte;

// Progression data
$pointARenforcer = $_SESSION['point_a_renforcer'] ?? null;
$lastTheme = $_SESSION['last_theme'] ?? null;
$lastGrammar = $_SESSION['last_grammar'] ?? null;

// Build level-appropriate instructions
$levelInstruction = '';
switch ($niveauActuel) {
    case 'A1':
        $levelInstruction = 'Student is a complete beginner. Use very short sentences, basic vocabulary, speak slowly. Correct major errors gently. Do not use complex grammar.';
        break;
    case 'A1+':
        $levelInstruction = 'Student is beginner but has some basics. Use short sentences. Speak slightly slower than normal. Focus on common phrases and present tense.';
        break;
    case 'A2':
        $levelInstruction = 'Student is elementary. Use simple sentences. Speak at normal speed but clearly. Introduce past tense gradually.';
        break;
    case 'A2+':
        $levelInstruction = 'Student is lower intermediate. Use normal speech. Introduce future tense. Correct errors naturally.';
        break;
    case 'B1':
        $levelInstruction = 'Student is intermediate. Speak normally. Use a mix of tenses. Encourage longer answers. Correct errors but don\'t interrupt flow.';
        break;
    case 'B1+':
        $levelInstruction = 'Student is upper intermediate. Speak naturally. Introduce nuanced vocabulary and idiomatic expressions. Correct errors after they finish speaking.';
        break;
    case 'B2':
    case 'C1':
    case 'C2':
        $levelInstruction = 'Student is advanced. Speak naturally at normal speed. Use complex structures. Correct only repeated or significant errors.';
        break;
    default:
        $levelInstruction = 'Student level unknown. Adjust difficulty based on their responses. Start with intermediate level.';
}

// Build progression instruction
$progressionInstruction = '';
if ($pointARenforcer && trim($pointARenforcer) !== '') {
    $progressionInstruction = "\n\nIMPORTANT - AREAS TO REINFORCE:\nThe student needs to work on: {$pointARenforcer}\n\nFocus your questions and corrections on this area.\n";
}

if ($lastTheme && $lastTheme !== 'Aucun') {
    $progressionInstruction .= "\nLast session theme: {$lastTheme}\n";
}

if ($lastGrammar && $lastGrammar !== 'Aucun') {
    $progressionInstruction .= "\nLast session grammar point: {$lastGrammar}\n";
}

// Build language instruction
$languageInstruction = "You are a welcoming, calm, and encouraging {$langueEtudiee} teacher.";

// Consigne IA donnée par le professeur pour cet élève
$consigneIA = null;
$consigneProf = null;
$consigneDate = null;

$idAcces = (int)($_SESSION['id_acces'] ?? 0);

if ($idAcces > 0) {
    $sqlConsigne = "
        SELECT TOP 1
            consigne_ia,
            prof,
            date_creation
        FROM dbo.AudraWeb_Eleve_Consigne
        WHERE id_acces = ?
          AND is_active = 1
        ORDER BY date_creation DESC
    ";

    $stConsigne = $pdo->prepare($sqlConsigne);
    $stConsigne->execute([$idAcces]);
    $rowConsigne = $stConsigne->fetch(PDO::FETCH_ASSOC);

    if ($rowConsigne) {
        $consigneIA = trim((string)($rowConsigne['consigne_ia'] ?? ''));
        $consigneProf = trim((string)($rowConsigne['prof'] ?? ''));
        $consigneDate = $rowConsigne['date_creation'] ?? null;
    }
}

$consigneInstruction = '';

if ($consigneIA !== null && trim($consigneIA) !== '') {
    $consigneInstruction = <<<TEXT

IMPORTANT - TEACHER'S PERSONAL INSTRUCTION:
The real teacher has given this specific instruction for this student:
{$consigneIA}

You must follow this instruction during the conversation.
Do not mention to the student that this instruction comes from a database.
Use it naturally to guide your questions, corrections, examples and practice.
TEXT;
}

// Instructions système
$systemInstruction = <<<TEXT
{$languageInstruction}

{$levelInstruction}

The student's learning objective is: {$objectif}.

The student's professional context is: {$contexteText}.
This should guide the conversation and the choice of vocabulary and topics.

The student has chosen to practise the following theme: {$theme}.
The student has chosen to focus on the following grammar point: {$grammar}.

{$progressionInstruction}

{$consigneInstruction}

Your role is to help students practise spoken {$langueEtudiee} through natural conversation.

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

$idAcces = (int)($_SESSION['id_acces'] ?? 0);

if ($idAcces > 0) {
    $lastThemeValue = $theme !== 'No specific topic chosen yet' ? $theme : null;
    $lastGrammarValue = $grammar !== 'No specific grammar focus chosen' ? $grammar : null;

    $sqlCheckSuivi = "
        SELECT TOP 1 id
        FROM dbo.AudraWeb_Eleve_Suivi_IA
        WHERE id_acces = ?
    ";
    $stCheckSuivi = $pdo->prepare($sqlCheckSuivi);
    $stCheckSuivi->execute([$idAcces]);
    $existingSuivi = $stCheckSuivi->fetch(PDO::FETCH_ASSOC);

    if ($existingSuivi) {
        $sqlUpdateSuivi = "
            UPDATE dbo.AudraWeb_Eleve_Suivi_IA
            SET
                last_theme = COALESCE(?, last_theme),
                last_grammar = COALESCE(?, last_grammar),
                last_session_date = GETDATE()
            WHERE id_acces = ?
        ";
        $stUpdateSuivi = $pdo->prepare($sqlUpdateSuivi);
        $stUpdateSuivi->execute([
            $lastThemeValue,
            $lastGrammarValue,
            $idAcces
        ]);
    } else {
        $sqlInsertSuivi = "
            INSERT INTO dbo.AudraWeb_Eleve_Suivi_IA (
                id_acces,
                last_theme,
                last_grammar,
                last_session_date
            )
            VALUES (?, ?, ?, GETDATE())
        ";
        $stInsertSuivi = $pdo->prepare($sqlInsertSuivi);
        $stInsertSuivi->execute([
            $idAcces,
            $lastThemeValue,
            $lastGrammarValue
        ]);
    }
}


// Réponse vers le frontend
echo json_encode([
    'reply' => $reply,
    'response_id' => $responseData['id'] ?? null
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;