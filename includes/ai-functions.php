<?php

/**
 * AI Functions for Quiz IA Pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate quiz questions using AI
 */
function quiz_ai_pro_generate_quiz_with_ai($content, $params = [])
{
    $provider = get_option('quiz_ai_pro_ai_provider', 'gemini');

    switch ($provider) {
        case 'gemini':
            return quiz_ai_pro_generate_with_gemini($content, $params);
        default:
            return new WP_Error('no_provider', 'Aucun fournisseur IA configuré');
    }
}

/**
 * Generate quiz using Google Gemini
 */
function quiz_ai_pro_generate_with_gemini($content, $params = [])
{
    $api_key = get_option('quiz_ai_gemini_api_key');

    if (empty($api_key)) {
        return new WP_Error('no_api_key', 'Clé API Gemini non configurée');
    }

    $defaults = [
        'num_questions' => 5,
        'difficulty' => 'moyen',
        'question_types' => ['multiple_choice', 'true_false'],
        'language' => 'fr'
    ];

    $params = wp_parse_args($params, $defaults);

    // Prepare the prompt
    $prompt = quiz_ai_pro_build_prompt($content, $params);

    // === DEBUG: Full prompt and content analysis ===
   /*  error_log("=== QUIZ AI DEBUG: FULL PROMPT ANALYSIS ===");
    error_log("Content object received:");
    error_log("- Title: " . (isset($content->title) ? $content->title : 'NOT SET'));
    error_log("- Description: " . (isset($content->description) ? $content->description : 'NOT SET'));
    error_log("- Content length: " . (isset($content->content) ? strlen($content->content) : 'NOT SET'));
    error_log("- Level: " . (isset($content->level) ? $content->level : 'NOT SET'));
    error_log("Parameters received:");
    error_log("- num_questions: " . $params['num_questions']);
    error_log("- difficulty: " . $params['difficulty']);
    error_log("- question_types: " . implode(', ', $params['question_types']));
    error_log("FULL PROMPT SENT TO AI:");
    error_log($prompt);
    error_log("=== END QUIZ AI DEBUG ==="); */

    // API endpoint
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $api_key;

    $body = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 2048
        ]
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => wp_json_encode($body),
        'timeout' => 60
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        return new WP_Error('api_error', 'Erreur API Gemini: ' . $response_code);
    }

    $data = json_decode($response_body, true);

    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        return new WP_Error('invalid_response', 'Réponse invalide de l\'API Gemini');
    }

    $generated_text = $data['candidates'][0]['content']['parts'][0]['text'];

    // Parse the generated questions
    return quiz_ai_pro_parse_generated_questions($generated_text);
}

/**
 * Build AI prompt for quiz generation
 */
function quiz_ai_pro_build_prompt($content, $params)
{
    $difficulty_map = [
        'facile' => 'facile (questions directes sur les concepts de base)',
        'moyen' => 'moyen (questions de compréhension et d\'application)',
        'difficile' => 'difficile (questions d\'analyse et de synthèse)'
    ];

    $types_map = [
        'multiple_choice' => 'Questions à choix multiples (QCM)',
        'true_false' => 'Questions Vrai/Faux',
        'open_ended' => 'Questions ouvertes',
        'case_study' => 'Études de cas pratiques'
    ];

    $selected_types = array_intersect_key($types_map, array_flip($params['question_types']));

    $prompt = "Créez un quiz de {$params['num_questions']} questions basé sur le contenu suivant.\n\n";
    $prompt .= "**CONTENU SOURCE:**\n";
    $prompt .= "Titre: " . $content->title . "\n";
    if (!empty($content->description)) {
        $prompt .= "Description: " . $content->description . "\n";
    }
    $prompt .= "Contenu: " . wp_strip_all_tags($content->content) . "\n\n";

    $prompt .= "**PARAMÈTRES DU QUIZ:**\n";
    $prompt .= "- Nombre de questions: {$params['num_questions']}\n";
    $prompt .= "- Niveau de difficulté: " . $difficulty_map[$params['difficulty']] . "\n";
    $prompt .= "- Types de questions: " . implode(', ', $selected_types) . "\n";
    $prompt .= "- Niveau du public: " . ucfirst($content->level) . "\n\n";

    if (!empty($params['target_audience'])) {
        $prompt .= "- Public cible: " . $params['target_audience'] . "\n\n";
    }

    $prompt .= "**FORMAT DE RÉPONSE REQUIS:**\n";
    $prompt .= "Répondez uniquement au format JSON suivant (sans markdown ou formatage supplémentaire):\n\n";
    $prompt .= "{\n";
    $prompt .= '  "quiz_title": "Titre du quiz",';
    $prompt .= "\n";
    $prompt .= '  "questions": [';
    $prompt .= "\n";
    $prompt .= "    {\n";
    $prompt .= '      "type": "multiple_choice|true_false|open_ended|case_study",';
    $prompt .= "\n";
    $prompt .= '      "question": "Texte de la question",';
    $prompt .= "\n";
    $prompt .= '      "options": ["Option A", "Option B", "Option C", "Option D"],';
    $prompt .= "\n";
    $prompt .= '      "correct_answer": "Réponse correcte ou index (0,1,2,3)",';
    $prompt .= "\n";
    $prompt .= '      "explanation": "Explication de la réponse"';
    $prompt .= "\n";
    $prompt .= "    }\n";
    $prompt .= "  ]\n";
    $prompt .= "}\n\n";

    $prompt .= "**INSTRUCTIONS IMPORTANTES:**\n";
    $prompt .= "1. Créez des questions pertinentes et directement liées au contenu\n";
    $prompt .= "2. Variez les types de questions selon les paramètres\n";
    $prompt .= "3. Pour les QCM, incluez 4 options avec une seule bonne réponse\n";
    $prompt .= "4. Pour Vrai/Faux, utilisez 'true' ou 'false' comme correct_answer\n";
    $prompt .= "5. Incluez une explication claire pour chaque question\n";
    $prompt .= "6. Assurez-vous que les questions testent la compréhension du contenu\n";
    $prompt .= "7. Répondez uniquement en JSON valide, sans texte supplémentaire\n";

    return $prompt;
}

/**
 * Parse AI-generated questions from response
 */
function quiz_ai_pro_parse_generated_questions($generated_text)
{
    // Clean the response - remove markdown formatting
    $cleaned_text = trim($generated_text);
    $cleaned_text = preg_replace('/```json\s*/', '', $cleaned_text);
    $cleaned_text = preg_replace('/```\s*$/', '', $cleaned_text);
    $cleaned_text = trim($cleaned_text);

    // Try to decode JSON
    $data = json_decode($cleaned_text, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('parse_error', 'Impossible de parser la réponse IA: ' . json_last_error_msg());
    }

    if (!isset($data['questions']) || !is_array($data['questions'])) {
        return new WP_Error('invalid_format', 'Format de réponse invalide - questions manquantes');
    }

    $questions = [];

    foreach ($data['questions'] as $index => $q) {
        // Validate required fields
        if (!isset($q['type']) || !isset($q['question'])) {
            continue;
        }

        $question = [
            'type' => sanitize_text_field($q['type']),
            'question' => sanitize_textarea_field($q['question']),
            'explanation' => isset($q['explanation']) ? sanitize_textarea_field($q['explanation']) : '',
            'points' => 1
        ];

        // Handle different question types
        switch ($q['type']) {
            case 'multiple_choice':
                if (isset($q['options']) && is_array($q['options'])) {
                    $question['options'] = array_map('sanitize_text_field', $q['options']);
                    $question['correct_answer'] = isset($q['correct_answer']) ?
                        intval($q['correct_answer']) : 0;
                }
                break;

            case 'true_false':
                $question['correct_answer'] = isset($q['correct_answer']) ?
                    ($q['correct_answer'] === 'true' || $q['correct_answer'] === true) : false;
                break;

            case 'open_ended':
            case 'case_study':
                $question['model_answer'] = isset($q['correct_answer']) ?
                    sanitize_textarea_field($q['correct_answer']) : '';
                break;
        }

        $questions[] = $question;
    }

    if (empty($questions)) {
        return new WP_Error('no_questions', 'Aucune question valide générée');
    }

    return [
        'title' => isset($data['quiz_title']) ? sanitize_text_field($data['quiz_title']) : 'Quiz Généré par IA',
        'questions' => $questions,
        'ai_generated' => true
    ];
}

/**
 * Generate personalized feedback for student
 */
function quiz_ai_pro_generate_feedback($attempt, $quiz, $student)
{
    $provider = get_option('quiz_ai_pro_ai_provider', 'gemini');

    $prompt = quiz_ai_pro_build_feedback_prompt($attempt, $quiz, $student);

    switch ($provider) {
        case 'gemini':
            return quiz_ai_pro_generate_feedback_gemini($prompt);
        default:
            return quiz_ai_pro_generate_basic_feedback($attempt);
    }
}

/**
 * Build feedback prompt
 */
function quiz_ai_pro_build_feedback_prompt($attempt, $quiz, $student)
{
    $answers = json_decode($attempt->answers, true);
    $questions = json_decode($quiz->questions, true);

    $prompt = "Générez un feedback personnalisé pour cet étudiant:\n\n";
    $prompt .= "**INFORMATIONS ÉTUDIANT:**\n";
    $prompt .= "- Nom: " . trim($student->first_name . ' ' . $student->last_name) . "\n";
    $prompt .= "- Niveau: " . ucfirst($student->level) . "\n";
    $prompt .= "- Quiz précédents: " . $student->total_quizzes_taken . "\n";
    $prompt .= "- Score moyen: " . $student->average_score . "%\n\n";

    $prompt .= "**PERFORMANCE SUR CE QUIZ:**\n";
    $prompt .= "- Quiz: " . $quiz->title . "\n";
    $prompt .= "- Score: " . $attempt->percentage . "%\n";
    $prompt .= "- Temps: " . $attempt->time_taken . " minutes\n\n";

    $prompt .= "**ANALYSE DES RÉPONSES:**\n";
    foreach ($questions as $index => $question) {
        $user_answer = isset($answers[$index]) ? $answers[$index] : 'Non répondu';
        $is_correct = quiz_ai_pro_check_answer_correctness($question, $user_answer);

        $prompt .= "Question " . ($index + 1) . ": " . ($is_correct ? "✓ Correct" : "✗ Incorrect") . "\n";
        $prompt .= "Sujet: " . substr($question['question'], 0, 50) . "...\n";
    }

    $prompt .= "\n**CONSIGNES:**\n";
    $prompt .= "1. Donnez un feedback encourageant et constructif\n";
    $prompt .= "2. Identifiez les points forts et axes d'amélioration\n";
    $prompt .= "3. Proposez des recommandations d'apprentissage\n";
    $prompt .= "4. Adaptez le ton au niveau de l'étudiant\n";
    $prompt .= "5. Limitez-vous à 200 mots maximum\n\n";

    return $prompt;
}

/**
 * Generate basic feedback without AI
 */
function quiz_ai_pro_generate_basic_feedback($attempt)
{
    $score = $attempt->percentage;

    if ($score >= 80) {
        return "Excellent travail ! Vous maîtrisez bien ce sujet. Continuez ainsi !";
    } elseif ($score >= 60) {
        return "Bon travail ! Vous avez une bonne compréhension du sujet. Quelques révisions vous aideront à perfectionner vos connaissances.";
    } else {
        return "Il y a des points à améliorer. Je vous recommande de réviser le contenu et de refaire le quiz pour consolider vos connaissances.";
    }
}

/**
 * Check if an answer is correct
 */
function quiz_ai_pro_check_answer_correctness($question, $user_answer)
{
    switch ($question['type']) {
        case 'multiple_choice':
            return isset($question['correct_answer']) &&
                intval($user_answer) === intval($question['correct_answer']);

        case 'true_false':
            return isset($question['correct_answer']) &&
                (bool) $user_answer === (bool) $question['correct_answer'];

        default:
            // For open-ended questions, we can't automatically check
            return false;
    }
}

/**
 * Test AI connection
 */
function quiz_ai_pro_test_ai_connection()
{
    $provider = get_option('quiz_ai_pro_ai_provider', 'gemini');

    // Create a simple test content
    $test_content = (object) [
        'title' => 'Test Content',
        'description' => 'Test description',
        'content' => 'WordPress est un système de gestion de contenu (CMS) très populaire.',
        'level' => 'debutant'
    ];

    $test_params = [
        'num_questions' => 1,
        'difficulty' => 'facile',
        'question_types' => ['multiple_choice']
    ];

    $result = quiz_ai_pro_generate_quiz_with_ai($test_content, $test_params);

    if (is_wp_error($result)) {
        return $result;
    }

    return ['status' => 'success', 'message' => 'Connexion IA fonctionnelle'];
}

/**
 * AI-powered scoring for open questions using Gemini with enhanced course references
 * 
 * @param string $question The original question
 * @param string $expected_answer The expected/model answer (if available)
 * @param string $user_answer The student's answer
 * @param string $context Additional context about the topic
 * @param array $course_info Course information for references
 * @return array Scoring result with score, feedback, and course references
 */
function quiz_ai_pro_score_open_question($question, $expected_answer, $user_answer, $context = '', $course_info = null)
{
    $api_key = get_option('quiz_ai_gemini_api_key');

    if (empty($api_key)) {
        // Fallback to simple scoring if no API key
        return [
            'score' => !empty(trim($user_answer)) ? 0.7 : 0,
            'percentage' => !empty(trim($user_answer)) ? 70 : 0,
            'feedback' => 'Évaluation automatique - API IA non disponible',
            'key_points_covered' => [],
            'missing_points' => [],
            'suggested_sections' => [],
            'course_reference' => '',
            'is_correct' => !empty(trim($user_answer))
        ];
    }

    // Prepare course information for AI context
    $course_context = '';
    $course_url = '';
    $available_sections = '';

    if ($course_info && is_array($course_info)) {
        $course_title = $course_info['title'] ?? '';
        $course_slug = $course_info['slug'] ?? '';
        $course_id = $course_info['course_id'] ?? 0;
        $course_url = $course_slug ? "https://innovation.ma/cours/{$course_slug}/" : '';

        // Get course sections
        $sections = [];
        if ($course_id && function_exists('quiz_ai_pro_get_course_sections')) {
            $sections = quiz_ai_pro_get_course_sections($course_id);
        }

        if (!empty($sections)) {
            $available_sections = "\n\nSECTIONS DISPONIBLES DANS LE COURS:
" . implode("\n- ", array_map(function ($section) {
                return "\"$section\"";
            }, $sections));
        }

        $course_context = "\n\nINFORMATIONS DU COURS:
Titre du cours: {$course_title}
URL du cours: {$course_url}
Contexte: Ce cours fait partie de la plateforme Innovation.ma{$available_sections}

IMPORTANT: 
1. Dans ton feedback, tu DOIS inclure une référence au cours avec l'URL fournie
2. Tu DOIS recommander 2-3 sections spécifiques du cours à partir de la liste des sections disponibles
3. Adapte tes recommandations selon la qualité de la réponse de l'étudiant";
    }

    // Build the enhanced evaluation prompt
    $prompt = "Tu es un enseignant expert qui évalue des réponses d'étudiants à des questions ouvertes.

QUESTION: {$question}

RÉPONSE MODÈLE/ATTENDUE: {$expected_answer}

RÉPONSE DE L'ÉTUDIANT: {$user_answer}

CONTEXTE ADDITIONNEL: {$context}{$course_context}

INSTRUCTIONS D'ÉVALUATION:
1. Évalue la réponse de l'étudiant sur une échelle de 0 à 10
2. Fournis un feedback constructif en français qui:
   - Est COURT (maximum 2-3 phrases)
   - Paraît NATUREL et humain (pas généré par IA)
   - Explique brièvement pourquoi c'est correct/incorrect
   - INCLUT OBLIGATOIREMENT une référence au cours avec l'URL (si disponible)

3. Recommande EXACTEMENT 2-3 sections du cours (utilise UNIQUEMENT les noms exacts de la liste fournie)

IMPORTANT: Garde le feedback très court et naturel. Évite les formulations robotiques.

RÉPONDS STRICTEMENT dans ce format JSON (sans markdown):
{
    \"score\": [nombre entre 0 et 10],
    \"percentage\": [pourcentage entre 0 et 100],
    \"feedback\": \"[feedback court et naturel en français - MAX 2-3 phrases]\",
    \"key_points_covered\": [\"point1\", \"point2\"],
    \"missing_points\": [\"point_manqué1\", \"point_manqué2\"],
    \"suggested_sections\": [\"nom exact section 1\", \"nom exact section 2\"],
    \"course_reference\": \"[référence complète au cours avec URL]\",
    \"is_correct\": [true si score >= 7, false sinon]
}";

    // API endpoint (same as quiz generation)
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $api_key;

    $body = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.3, // Lower temperature for more consistent scoring
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 1024
        ]
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body' => wp_json_encode($body),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        error_log('Quiz IA Pro: AI scoring error - ' . $response->get_error_message());
        // Fallback scoring
        return [
            'score' => !empty(trim($user_answer)) ? 0.7 : 0,
            'percentage' => !empty(trim($user_answer)) ? 70 : 0,
            'feedback' => 'Erreur lors de l\'évaluation IA - Score automatique appliqué',
            'key_points_covered' => [],
            'missing_points' => [],
            'suggested_sections' => [],
            'course_reference' => '',
            'is_correct' => !empty(trim($user_answer))
        ];
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        error_log('Quiz IA Pro: AI scoring API error - Code: ' . $response_code);
        error_log('Quiz IA Pro: API Response: ' . $response_body);
        // Fallback scoring
        return [
            'score' => !empty(trim($user_answer)) ? 0.7 : 0,
            'percentage' => !empty(trim($user_answer)) ? 70 : 0,
            'feedback' => 'Erreur API lors de l\'évaluation - Score automatique appliqué',
            'key_points_covered' => [],
            'missing_points' => [],
            'suggested_sections' => [],
            'course_reference' => '',
            'is_correct' => !empty(trim($user_answer))
        ];
    }

    $data = json_decode($response_body, true);

    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        error_log('Quiz IA Pro: Invalid AI scoring response structure');
        error_log('Quiz IA Pro: Response data: ' . print_r($data, true));
        return [
            'score' => !empty(trim($user_answer)) ? 0.7 : 0,
            'percentage' => !empty(trim($user_answer)) ? 70 : 0,
            'feedback' => 'Réponse IA invalide - Score automatique appliqué',
            'key_points_covered' => [],
            'missing_points' => [],
            'suggested_sections' => [],
            'course_reference' => '',
            'is_correct' => !empty(trim($user_answer))
        ];
    }

    $ai_response = $data['candidates'][0]['content']['parts'][0]['text'];

    // Clean the response - remove markdown formatting (same as quiz generation)
    $cleaned_response = trim($ai_response);
    $cleaned_response = preg_replace('/```json\s*/', '', $cleaned_response);
    $cleaned_response = preg_replace('/```\s*$/', '', $cleaned_response);
    $cleaned_response = trim($cleaned_response);

    // Try to parse JSON response
    $evaluation = json_decode($cleaned_response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Quiz IA Pro: AI scoring JSON parse error - ' . $cleaned_response);
        error_log('Quiz IA Pro: JSON error: ' . json_last_error_msg());
        return [
            'score' => !empty(trim($user_answer)) ? 0.7 : 0,
            'percentage' => !empty(trim($user_answer)) ? 70 : 0,
            'feedback' => 'Erreur de format dans l\'évaluation IA',
            'key_points_covered' => [],
            'missing_points' => [],
            'suggested_sections' => [],
            'course_reference' => '',
            'is_correct' => !empty(trim($user_answer))
        ];
    }

    // Validate and sanitize the AI response
    $score = isset($evaluation['score']) ? floatval($evaluation['score']) : 0;
    $percentage = isset($evaluation['percentage']) ? intval($evaluation['percentage']) : ($score * 10);
    $feedback = isset($evaluation['feedback']) ? sanitize_text_field($evaluation['feedback']) : 'Évaluation IA complétée';
    $is_correct = isset($evaluation['is_correct']) ? (bool)$evaluation['is_correct'] : ($score >= 7);

    // Ensure score is within bounds
    $score = max(0, min(10, $score));
    $percentage = max(0, min(100, $percentage));

    error_log('Quiz IA Pro: AI scoring successful - Score: ' . $score . ', Percentage: ' . $percentage);

    return [
        'score' => $score,
        'percentage' => $percentage,
        'feedback' => $feedback,
        'key_points_covered' => $evaluation['key_points_covered'] ?? [],
        'missing_points' => $evaluation['missing_points'] ?? [],
        'suggested_sections' => $evaluation['suggested_sections'] ?? [],
        'course_reference' => $evaluation['course_reference'] ?? '',
        'is_correct' => $is_correct
    ];
}

/**
 * Use AI to select the most relevant course sections for a specific question
 * @param string $question_text The question text
 * @param string $explanation The question explanation
 * @param array $available_sections Array of available course section names
 * @param bool $is_correct Whether the user's answer was correct
 * @param int $max_sections Maximum number of sections to return (default 3)
 * @return array Selected course sections
 */
function quiz_ai_pro_select_relevant_sections($question_text, $explanation, $available_sections, $is_correct = true, $max_sections = 3)
{
    error_log('Quiz IA Pro Debug: Starting section selection - Question: ' . substr($question_text, 0, 100) . '...');
    error_log('Quiz IA Pro Debug: Available sections count: ' . count($available_sections));
    error_log('Quiz IA Pro Debug: Available sections: ' . print_r($available_sections, true));
    
    $api_key = get_option('quiz_ai_gemini_api_key');

    if (empty($api_key) || empty($available_sections)) {
        // Fallback: return first few sections if AI is not available
        $fallback_sections = array_slice($available_sections, 0, min($max_sections, count($available_sections)));
        error_log('Quiz IA Pro Debug: Using fallback sections: ' . print_r($fallback_sections, true));
        return $fallback_sections;
    }

    // Build prompt for AI to select relevant sections
    $sections_list = implode("\n- ", $available_sections);
    $performance_context = $is_correct ? "l'étudiant a bien répondu" : "l'étudiant a besoin de réviser davantage";

    $prompt = "Tu es un assistant pédagogique expert. Sélectionne les {$max_sections} sections de cours les plus pertinentes pour cette question, sachant que {$performance_context}.

QUESTION: {$question_text}

EXPLICATION: {$explanation}

SECTIONS DISPONIBLES:
- {$sections_list}

INSTRUCTIONS:
1. Sélectionne exactement {$max_sections} sections (ou moins si moins disponibles)
2. Choisis les sections les plus directement liées au contenu de la question
3. Si l'étudiant a mal répondu, privilégie les sections qui l'aideront à mieux comprendre
4. Si l'étudiant a bien répondu, suggère des sections pour approfondir ses connaissances

Réponds UNIQUEMENT par un tableau JSON des noms des sections sélectionnées:
[\"Section 1\", \"Section 2\", \"Section 3\"]";

    try {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $api_key;
        $headers = [
            'Content-Type: application/json',
        ];

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 200,
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
           // error_log('Quiz IA Pro Debug: AI API Response: ' . print_r($result, true));

            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                $ai_response = trim($result['candidates'][0]['content']['parts'][0]['text']);
              //  error_log('Quiz IA Pro Debug: AI Response Text: ' . $ai_response);

                // Try to extract JSON array from response
                if (preg_match('/\[(.*?)\]/', $ai_response, $matches)) {
                    $selected = json_decode('[' . $matches[1] . ']', true);
                    //error_log('Quiz IA Pro Debug: Extracted JSON: ' . print_r($selected, true));

                    if (is_array($selected)) {
                        // Validate that selected sections exist in available sections
                        $valid_sections = [];
                        foreach ($selected as $section) {
                            if (in_array($section, $available_sections)) {
                                $valid_sections[] = $section;
                            } else {
                                error_log('Quiz IA Pro Debug: Invalid section (not in available): ' . $section);
                            }
                        }

                        if (!empty($valid_sections)) {
                            $final_sections = array_slice($valid_sections, 0, $max_sections);
                            error_log('Quiz IA Pro Debug: Final selected sections: ' . print_r($final_sections, true));
                            return $final_sections;
                        }
                    }
                } else {
                    error_log('Quiz IA Pro Debug: Could not extract JSON array from AI response');
                }
            }
        } else {
            error_log('Quiz IA Pro Debug: AI API Error - HTTP Code: ' . $httpCode . ', Response: ' . $response);
        }

        error_log('Quiz IA Pro: AI section selection failed, using fallback');
    } catch (Exception $e) {
        error_log('Quiz IA Pro: Error in AI section selection: ' . $e->getMessage());
    }

    // Fallback: return first few sections
    $fallback_sections = array_slice($available_sections, 0, min($max_sections, count($available_sections)));
    error_log('Quiz IA Pro Debug: Using fallback sections: ' . print_r($fallback_sections, true));
    return $fallback_sections;
}
