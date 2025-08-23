<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Quiz Generator AJAX Handler
 */
class QuizGeneratorAjax
{

    public function __construct()
    {
        add_action('wp_ajax_generate_quiz', [$this, 'handle_generate_quiz']);
        add_action('wp_ajax_nopriv_generate_quiz', [$this, 'handle_generate_quiz']);

        add_action('wp_ajax_generate_practical_exercise', [$this, 'handle_generate_practical_exercise']);
        add_action('wp_ajax_nopriv_generate_practical_exercise', [$this, 'handle_generate_practical_exercise']);

        add_action('wp_ajax_get_courses', [$this, 'handle_get_courses']);
        add_action('wp_ajax_nopriv_get_courses', [$this, 'handle_get_courses']);

        add_action('wp_ajax_get_categories', [$this, 'handle_get_categories']);
        add_action('wp_ajax_nopriv_get_categories', [$this, 'handle_get_categories']);

        add_action('wp_ajax_save_quiz_draft', [$this, 'handle_save_quiz_draft']);
        add_action('wp_ajax_nopriv_save_quiz_draft', [$this, 'handle_save_quiz_draft']);

        add_action('wp_ajax_load_quiz_draft', [$this, 'handle_load_quiz_draft']);
        add_action('wp_ajax_nopriv_load_quiz_draft', [$this, 'handle_load_quiz_draft']);

        add_action('wp_ajax_save_gemini_api_key', [$this, 'handle_save_gemini_api_key']);
        add_action('wp_ajax_get_gemini_api_key', [$this, 'handle_get_gemini_api_key']);

        // Quiz editing actions
        add_action('wp_ajax_quiz_ai_pro_save_question', [$this, 'handle_save_question']);
        add_action('wp_ajax_quiz_ai_pro_save_quiz_settings', [$this, 'handle_save_quiz_settings']);
        add_action('wp_ajax_quiz_ai_pro_save_all_changes', [$this, 'handle_save_all_changes']);
        add_action('wp_ajax_quiz_ai_pro_delete_question', [$this, 'handle_delete_question']);
        add_action('wp_ajax_quiz_ai_pro_duplicate_question', [$this, 'handle_duplicate_question']);
        add_action('wp_ajax_quiz_ai_pro_reorder_questions', [$this, 'handle_reorder_questions']);
        add_action('wp_ajax_quiz_ai_pro_add_question', [$this, 'handle_add_question']);
        add_action('wp_ajax_quiz_ai_pro_import_questions', [$this, 'handle_import_questions']);

        // Quiz preview actions
        add_action('wp_ajax_quiz_ai_pro_preview_quiz', [$this, 'handle_preview_quiz']);
        add_action('wp_ajax_quiz_test_ajax', [$this, 'handle_test_ajax']);

        // Quiz status actions
        add_action('wp_ajax_quiz_ai_pro_publish_quiz', [$this, 'handle_publish_quiz']);
        add_action('wp_ajax_quiz_ai_pro_unpublish_quiz', [$this, 'handle_unpublish_quiz']);

        // Question type conversion
        add_action('wp_ajax_quiz_ai_pro_change_question_type', [$this, 'handle_change_question_type']);

        // LearnPress integration
        add_action('wp_ajax_quiz_ai_pro_create_learnpress_quiz', [$this, 'handle_create_learnpress_quiz']);
        add_action('wp_ajax_quiz_ai_pro_cleanup_orphaned_learnpress_syncs', [$this, 'handle_cleanup_orphaned_learnpress_syncs']);

        // Quiz comments actions
        add_action('wp_ajax_submit_quiz_comment', [$this, 'handle_submit_quiz_comment']);
        add_action('wp_ajax_nopriv_submit_quiz_comment', [$this, 'handle_submit_quiz_comment']);
        add_action('wp_ajax_get_quiz_comments', [$this, 'handle_get_quiz_comments']);
        add_action('wp_ajax_nopriv_get_quiz_comments', [$this, 'handle_get_quiz_comments']);

        // Image upload actions
        add_action('wp_ajax_quiz_ai_pro_upload_image', [$this, 'handle_upload_image']);

        // Quiz settings AJAX handlers
        add_action('wp_ajax_quiz_ai_pro_update_quiz_settings', [$this, 'handle_update_quiz_settings']);
        add_action('wp_ajax_quiz_ai_pro_add_category', [$this, 'handle_add_category']);
        add_action('wp_ajax_quiz_ai_pro_remove_category', [$this, 'handle_remove_category']);

        // Debug action
        add_action('wp_ajax_quiz_ai_debug_tables', [$this, 'handle_debug_quiz_tables']);
        add_action('wp_ajax_debug_quiz_tables', [$this, 'handle_debug_quiz_tables']); // Alternative action name
        add_action('wp_ajax_quiz_ai_fix_category_column', [$this, 'handle_fix_category_id_column']);
        add_action('wp_ajax_quiz_ai_populate_course_chunks', [$this, 'handle_populate_course_chunks']);
        add_action('wp_ajax_quiz_ai_force_update_tables', [$this, 'handle_force_update_tables']);

        // Stats and filtering actions
        add_action('wp_ajax_quiz_ai_filter_stats', [$this, 'handle_filter_stats']);
        add_action('wp_ajax_quiz_ai_export_stats', [$this, 'handle_export_stats']);
        add_action('wp_ajax_quiz_ai_filter_quizzes', [$this, 'handle_filter_quizzes']);
        add_action('wp_ajax_quiz_ai_bulk_quiz_action', [$this, 'handle_bulk_quiz_action']);
        add_action('wp_ajax_quiz_ai_pro_get_result_details', [$this, 'handle_get_result_details']);
        add_action('wp_ajax_quiz_ai_pro_get_performance_data', [$this, 'handle_get_performance_data']);
        add_action('wp_ajax_quiz_ai_refresh_nonces', [$this, 'handle_refresh_nonces']);

        // Frontend quiz contact form action (others are in frontend-functions.php)
        add_action('wp_ajax_submit_quiz_contact_form', [$this, 'handle_submit_quiz_contact_form']);
        add_action('wp_ajax_nopriv_submit_quiz_contact_form', [$this, 'handle_submit_quiz_contact_form']);
    }

    /**
     * Handle quiz generation with AI
     */
    public function handle_generate_quiz()
    {
        error_log('[QUIZ_AI] === DÉBUT GÉNÉRATION QUIZ ===');

        // Initialize debug information array as global
        global $debug_info;
        $debug_info = [
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id()
        ];

        try {
            // Verify nonce - accept both quiz generator and admin nonces
            $nonce_valid = wp_verify_nonce($_POST['nonce'], 'quiz_generator_action') ||
                wp_verify_nonce($_POST['nonce'], 'quiz_ai_admin_nonce') ||
                wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_nonce');

            if (!$nonce_valid) {
                error_log('[QUIZ_AI] ERREUR: Échec vérification nonce - reçu: ' . ($_POST['nonce'] ?? 'NULL'));
                wp_die('Security check failed');
            }
            error_log('[QUIZ_AI] ✓ Nonce vérifié avec succès');

            // Check permissions
            if (!current_user_can('manage_options')) {
                error_log('[QUIZ_AI] ERREUR: Permissions insuffisantes pour utilisateur ID: ' . get_current_user_id());
                wp_die('Insufficient permissions');
            }
            error_log('[QUIZ_AI] ✓ Permissions validées pour utilisateur ID: ' . get_current_user_id());

            // Check if form_data exists
            if (!isset($_POST['form_data'])) {
                error_log('[QUIZ_AI] ERREUR: form_data manquant dans $_POST');
                wp_send_json_error('No form data received');
                return;
            }

            $form_data = $_POST['form_data'];
            error_log('[QUIZ_AI] Données formulaire reçues: ' . json_encode($form_data));

            // Validate required fields
            if (empty($form_data['quiz_name']) || empty($form_data['quiz_type'])) {
                error_log('[QUIZ_AI] ERREUR: Champs requis manquants - quiz_name: ' . (!empty($form_data['quiz_name']) ? 'OK' : 'MANQUANT') . ', quiz_type: ' . (!empty($form_data['quiz_type']) ? 'OK' : 'MANQUANT'));
                wp_send_json_error('Required fields missing');
                return;
            }
            error_log('[QUIZ_AI] ✓ Champs requis validés');

            // Get selected courses (handle both single course_id and multiple course_ids)
            $selected_courses = [];
            if (!empty($form_data['course_ids'])) {
                $selected_courses = array_map('intval', $form_data['course_ids']);
            } elseif (!empty($form_data['course_id'])) {
                $selected_courses = [intval($form_data['course_id'])];
            }

            // Get selected categories (handle both single category_id and multiple category_ids)
            $selected_categories = [];
            if (!empty($form_data['category_ids'])) {
                $selected_categories = array_map('intval', $form_data['category_ids']);
            } elseif (!empty($form_data['category_id'])) {
                $selected_categories = [intval($form_data['category_id'])];
            }

            // Debug information for course and category selection
            $debug_info['course_selection'] = [
                'form_course_id' => $form_data['course_id'] ?? 'NOT SET',
                'form_course_ids' => $form_data['course_ids'] ?? 'NOT SET',
                'selected_courses' => $selected_courses,
                'form_category_id' => $form_data['category_id'] ?? 'NOT SET',
                'form_category_ids' => $form_data['category_ids'] ?? 'NOT SET',
                'selected_categories' => $selected_categories
            ];

            error_log('[QUIZ_AI] Form data received for selection: ' . json_encode([
                'course_id' => $form_data['course_id'] ?? 'NOT SET',
                'course_ids' => $form_data['course_ids'] ?? 'NOT SET',
                'category_id' => $form_data['category_id'] ?? 'NOT SET',
                'category_ids' => $form_data['category_ids'] ?? 'NOT SET'
            ]));
            error_log('[QUIZ_AI] Cours sélectionnés: ' . json_encode($selected_courses));
            error_log('[QUIZ_AI] Catégories sélectionnées: ' . json_encode($selected_categories));

            // Ensure at least one course or category is selected
            // Also check if the form data has any indication of selection
            $has_course_selection = !empty($selected_courses) ||
                !empty($form_data['course_id']) ||
                !empty($form_data['course_ids']);
            $has_category_selection = !empty($selected_categories) ||
                !empty($form_data['category_id']) ||
                !empty($form_data['category_ids']);

            error_log('[QUIZ_AI] Validation check - Has course selection: ' . ($has_course_selection ? 'YES' : 'NO'));
            error_log('[QUIZ_AI] Validation check - Has category selection: ' . ($has_category_selection ? 'YES' : 'NO'));

            if (!$has_course_selection && !$has_category_selection) {
                error_log('[QUIZ_AI] ERREUR: Aucun cours ni catégorie sélectionné');
                wp_send_json_error('Veuillez sélectionner au moins un cours ou une catégorie');
                return;
            }

            // Generate unique quiz code
            $quiz_code = $this->generate_quiz_code();
            error_log('[QUIZ_AI] Code quiz généré: ' . $quiz_code);

            // Debug: Log the form data to see what settings are being received
            error_log('[QUIZ_AI] Form data received: ' . json_encode($form_data));
            error_log('[QUIZ_AI] Time limit from form: ' . ($form_data['time_limit'] ?? 'NOT SET'));
            error_log('[QUIZ_AI] Questions per page from form: ' . ($form_data['questions_per_page'] ?? 'NOT SET'));

            // Prepare settings array with all the settings from the form
            $settings_array = array_merge(
                isset($form_data['settings']) && is_array($form_data['settings']) ? $form_data['settings'] : [],
                [
                    'selected_courses' => $selected_courses,
                    'selected_categories' => $selected_categories,

                    // Display options - convert to boolean values
                    'show_contact_form' => isset($form_data['show_contact_form']) ? ($form_data['show_contact_form'] === '1' || $form_data['show_contact_form'] === true) : false,
                    'show_page_number' => isset($form_data['show_page_number']) ? ($form_data['show_page_number'] === '1' || $form_data['show_page_number'] === true) : false,
                    'show_question_images_results' => isset($form_data['show_question_images_results']) ? ($form_data['show_question_images_results'] === '1' || $form_data['show_question_images_results'] === true) : false,
                    'show_progress_bar' => isset($form_data['show_progress_bar']) ? ($form_data['show_progress_bar'] === '1' || $form_data['show_progress_bar'] === true) : false,

                    // Advanced settings - convert to boolean values
                    'require_login' => isset($form_data['require_login']) ? ($form_data['require_login'] === '1' || $form_data['require_login'] === true) : false,
                    'disable_first_page' => isset($form_data['disable_first_page']) ? ($form_data['disable_first_page'] === '1' || $form_data['disable_first_page'] === true) : false,
                    'enable_comments' => isset($form_data['enable_comments']) ? ($form_data['enable_comments'] === '1' || $form_data['enable_comments'] === true) : false,

                    // Quiz configuration
                    'difficulty_level' => isset($form_data['difficulty_level']) ? $form_data['difficulty_level'] : 'beginner',
                    'language' => isset($form_data['language']) ? $form_data['language'] : 'fr',

                    // Featured image settings
                    'featured_image_type' => isset($form_data['featured_image_type']) ? $form_data['featured_image_type'] : 'none',
                    'featured_image_url' => isset($form_data['featured_image_url']) ? $form_data['featured_image_url'] : '',
                    'featured_image_id' => isset($form_data['featured_image_id']) ? $form_data['featured_image_id'] : '',

                    // Numeric settings
                    'time_limit' => intval($form_data['time_limit'] ?? 0),
                    'questions_per_page' => intval($form_data['questions_per_page'] ?? 1),
                    'num_questions' => intval($form_data['num_questions'] ?? 10),

                    // Additional instructions
                    'additional_instructions' => isset($form_data['additional_instructions']) ? $form_data['additional_instructions'] : ''
                ]
            );

            $settings_json = json_encode($settings_array);
            error_log('[QUIZ_AI] Settings JSON to be saved: ' . $settings_json);

            // Prepare quiz data with new structure
            $quiz_data = array(
                'title' => sanitize_text_field($form_data['quiz_name']),
                'course_id' => !empty($selected_courses) ? wp_json_encode(array_map('intval', $selected_courses)) : null, // Store all selected courses as JSON
                'category_id' => !empty($selected_categories) ? wp_json_encode(array_map('intval', $selected_categories)) : null, // Store all selected categories as JSON
                'quiz_type' => sanitize_text_field($form_data['quiz_type']),
                'form_type' => sanitize_text_field($form_data['form_type']),
                'grading_system' => sanitize_text_field($form_data['grading_system']),
                'time_limit' => intval($form_data['time_limit']),
                'questions_per_page' => intval($form_data['questions_per_page']),
                'total_questions' => intval($form_data['num_questions']),
                'settings' => $settings_json,
                'ai_provider' => 'gemini', // Always use Gemini
                'ai_generated' => 1, // Mark as AI-generated
                'ai_instructions' => sanitize_textarea_field($form_data['additional_instructions']),
                'quiz_code' => $quiz_code,
                'status' => 'draft', // Save as draft for review
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            );

            error_log('[QUIZ_AI] Données quiz préparées: ' . json_encode($quiz_data));

            // Save quiz to database
            global $wpdb;
            $quizzes_table = $wpdb->prefix . 'quiz_ia_quizzes';
            error_log('[QUIZ_AI] Table quiz: ' . $quizzes_table);

            // Define the format for each field to ensure proper database insertion
            $format = array(
                '%s', // title
                '%s', // course_id (JSON)
                '%s', // category_id (JSON)
                '%s', // quiz_type
                '%s', // form_type
                '%s', // grading_system
                '%d', // time_limit
                '%d', // questions_per_page
                '%d', // total_questions
                '%s', // settings (JSON)
                '%s', // ai_provider
                '%d', // ai_generated
                '%s', // ai_instructions
                '%s', // quiz_code
                '%s', // status
                '%d', // created_by
                '%s', // created_at
                '%s'  // updated_at
            );

            $result = $wpdb->insert($quizzes_table, $quiz_data, $format);
            error_log('[QUIZ_AI] Résultat insertion quiz: ' . ($result !== false ? 'SUCCÈS' : 'ÉCHEC'));

            if ($result === false) {
                error_log('[QUIZ_AI] ERREUR DÉTAILLÉE insertion quiz: ' . $wpdb->last_error);
                error_log('[QUIZ_AI] Requête SQL: ' . $wpdb->last_query);

                // Debug info for browser console
                $debug_info = array(
                    'error' => 'Database insertion failed',
                    'last_error' => $wpdb->last_error,
                    'last_query' => $wpdb->last_query,
                    'table' => $quizzes_table,
                    'data' => $quiz_data
                );

                wp_send_json_error(array(
                    'message' => 'Failed to save quiz',
                    'debug' => $debug_info
                ));
            }

            $quiz_id = $wpdb->insert_id;
            error_log('[QUIZ_AI] ✓ Quiz sauvegardé avec ID: ' . $quiz_id);

            // Verify quiz was actually saved
            if (!$quiz_id || $quiz_id <= 0) {
                error_log('[QUIZ_AI] ERREUR: ID de quiz invalide après insertion');
                wp_send_json_error(array(
                    'message' => 'Failed to save quiz - invalid ID',
                    'debug' => array(
                        'quiz_id' => $quiz_id,
                        'insert_result' => $result,
                        'last_error' => $wpdb->last_error
                    )
                ));
            }

            // Double-check quiz exists in database
            $quiz_check = $wpdb->get_var($wpdb->prepare("SELECT id FROM $quizzes_table WHERE id = %d", $quiz_id));
            if (!$quiz_check) {
                error_log('[QUIZ_AI] ERREUR: Quiz non trouvé dans la base après insertion');
                wp_send_json_error(array(
                    'message' => 'Failed to save quiz - not found in database',
                    'debug' => array(
                        'quiz_id' => $quiz_id,
                        'table' => $quizzes_table,
                        'check_query' => $wpdb->last_query,
                        'check_error' => $wpdb->last_error
                    )
                ));
            }
            error_log('[QUIZ_AI] ✓ Quiz confirmé dans la base de données');

            // Generate questions with AI using RAG
            error_log('[QUIZ_AI] === DÉBUT GÉNÉRATION QUESTIONS AI ===');
            $questions = $this->generate_questions_with_ai_rag($quiz_id, $form_data, $selected_courses, $selected_categories);
            error_log('[QUIZ_AI] Questions générées: ' . (is_array($questions) ? count($questions) . ' questions' : 'ERREUR - ' . $questions));

            // Check if we got an error message instead of questions
            if (!is_array($questions)) {
                error_log('[QUIZ_AI] ERREUR: Gemini API a retourné une erreur - ' . $questions);
                // Rollback - delete the quiz if question generation failed
                $wpdb->delete($quizzes_table, array('id' => $quiz_id));
                wp_send_json_error(array(
                    'message' => $questions, // This will be the error message from Gemini API
                    'debug' => array(
                        'error_type' => 'gemini_api_error',
                        'error_message' => $questions,
                        'quiz_deleted' => true
                    )
                ));
            }

            if (empty($questions)) {
                error_log('[QUIZ_AI] ERREUR: Aucune question générée - suppression du quiz');
                // Rollback - delete the quiz if question generation failed
                $wpdb->delete($quizzes_table, array('id' => $quiz_id));
                wp_send_json_error(array(
                    'message' => 'Aucune question n\'a pu être générée. Veuillez vérifier votre contenu et réessayer.',
                    'debug' => array(
                        'error_type' => 'no_questions_generated',
                        'questions_count' => 0,
                        'quiz_deleted' => true
                    )
                ));
            }

            // Save questions to database
            error_log('[QUIZ_AI] === DÉBUT SAUVEGARDE QUESTIONS ===');
            $save_result = $this->save_questions($quiz_id, $questions);
            error_log('[QUIZ_AI] Résultat sauvegarde questions: ' . ($save_result ? 'SUCCÈS' : 'ÉCHEC'));

            if (!$save_result) {
                error_log('[QUIZ_AI] ERREUR: Échec sauvegarde questions - suppression du quiz');
                // Rollback - delete the quiz if saving questions failed
                $delete_result = $wpdb->delete($quizzes_table, array('id' => $quiz_id));
                error_log('[QUIZ_AI] Résultat suppression quiz (rollback): ' . ($delete_result !== false ? 'SUCCÈS' : 'ÉCHEC'));
                wp_send_json_error(array(
                    'message' => 'Failed to save quiz questions',
                    'debug' => array(
                        'save_result' => $save_result,
                        'questions_count' => count($questions),
                        'delete_result' => $delete_result
                    )
                ));
            }

            error_log('[QUIZ_AI] === GÉNÉRATION QUIZ TERMINÉE AVEC SUCCÈS ===');

            // Trigger LearnPress integration hook
            if (function_exists('do_action')) {
                $complete_quiz_data = array_merge($quiz_data, array(
                    'questions' => $questions,
                    'id' => $quiz_id
                ));
                do_action('quiz_ai_pro_quiz_created', $quiz_id, $complete_quiz_data);
            }

            // Final success response
            error_log('[QUIZ_AI] === GÉNÉRATION TERMINÉE AVEC SUCCÈS ===');
            error_log('[QUIZ_AI] Quiz ID final: ' . $quiz_id);
            error_log('[QUIZ_AI] Code quiz final: ' . $quiz_code);
            error_log('[QUIZ_AI] Questions sauvegardées: ' . count($questions));
            error_log('[QUIZ_AI] Envoi de la réponse de succès...');

            wp_send_json_success(array(
                'quiz_id' => $quiz_id,
                'quiz_code' => $quiz_code,
                'message' => '🎉 Quiz généré avec succès! ' . count($questions) . ' questions ont été créées.',
                'show_notification' => true,
                'stay_on_page' => true, // Don't redirect automatically
                'edit_url' => admin_url('admin.php?page=quiz-ai-pro-edit&quiz_id=' . $quiz_id),
                'list_url' => admin_url('admin.php?page=quiz-ai-pro-list'),
                'questions_count' => count($questions),
                'debug_info' => $debug_info
            ));
        } catch (Exception $e) {
            error_log('[QUIZ_AI] ERREUR EXCEPTION: ' . $e->getMessage());
            error_log('[QUIZ_AI] Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('An unexpected error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Handle practical exercise generation with AI
     */
    public function handle_generate_practical_exercise()
    {
        error_log('[EXERCISE_AI] === DÉBUT GÉNÉRATION EXERCICE PRATIQUE ===');

        try {
            // Verify nonce
            $nonce_valid = wp_verify_nonce($_POST['exercise_generator_nonce'], 'exercise_generator_action');

            if (!$nonce_valid) {
                error_log('[EXERCISE_AI] ERREUR: Échec vérification nonce');
                wp_send_json_error('Security check failed');
                return;
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                error_log('[EXERCISE_AI] ERREUR: Permissions insuffisantes');
                wp_send_json_error('Insufficient permissions');
                return;
            }

            // Get form data
            $exercise_title = sanitize_text_field($_POST['exercise_title'] ?? '');
            $exercise_description = sanitize_textarea_field($_POST['exercise_description'] ?? '');
            $exercise_sections = intval($_POST['exercise_sections'] ?? 5);
            $exercise_complexity = sanitize_text_field($_POST['exercise_complexity'] ?? 'intermediate');
            $exercise_type = sanitize_text_field($_POST['exercise_type'] ?? 'project');
            $exercise_tools = sanitize_text_field($_POST['exercise_tools'] ?? '');
            $selected_courses = json_decode(stripslashes($_POST['selected_courses'] ?? '[]'), true);
            $selected_categories = json_decode(stripslashes($_POST['selected_categories'] ?? '[]'), true);

            // Validate required fields
            if (empty($exercise_title)) {
                wp_send_json_error('Le titre de l\'exercice est requis');
                return;
            }

            error_log('[EXERCISE_AI] Configuration: ' . json_encode([
                'title' => $exercise_title,
                'sections' => $exercise_sections,
                'complexity' => $exercise_complexity,
                'type' => $exercise_type,
                'courses' => count($selected_courses),
                'categories' => count($selected_categories)
            ]));

            // Generate practical exercise content
            $exercise_content = $this->generate_exercise_content(
                $exercise_title,
                $exercise_description,
                $exercise_sections,
                $exercise_complexity,
                $exercise_type,
                $exercise_tools,
                $selected_courses,
                $selected_categories
            );

            if (!$exercise_content) {
                wp_send_json_error('Erreur lors de la génération du contenu de l\'exercice');
                return;
            }

            // Add selected courses to exercise content for resources section
            $exercise_content['selected_courses'] = $selected_courses;

            // Create LearnPress course
            $course_id = $this->create_learnpress_course($exercise_content, $selected_courses, $selected_categories);

            if (!$course_id) {
                wp_send_json_error('Erreur lors de la création du cours LearnPress');
                return;
            }

            error_log('[EXERCISE_AI] ✓ Exercice pratique créé avec succès - Cours ID: ' . $course_id);

            wp_send_json_success([
                'message' => 'Exercice pratique généré avec succès!',
                'course_id' => $course_id,
                'redirect_url' => admin_url('edit.php?post_type=lp_course')
            ]);
        } catch (Exception $e) {
            error_log('[EXERCISE_AI] ERREUR EXCEPTION: ' . $e->getMessage());
            wp_send_json_error('Une erreur inattendue s\'est produite: ' . $e->getMessage());
        }
    }

    /**
     * Generate practical exercise content using AI
     */
    private function generate_exercise_content($title, $description, $sections, $complexity, $type, $tools, $courses, $categories)
    {
        // Get course content for context
        $course_content = $this->get_course_content_for_exercise($courses, $categories);

        // Build AI prompt
        $prompt = $this->build_exercise_ai_prompt($title, $description, $sections, $complexity, $type, $tools, $course_content);

        // Call AI service
        $ai_response = $this->call_ai_for_exercise($prompt);

        if (!$ai_response) {
            return false;
        }

        $parsed_content = $this->parse_exercise_response($ai_response);

        // Use the user's title instead of AI-generated title
        if ($parsed_content && !empty($title)) {
            $parsed_content['original_title'] = $parsed_content['title']; // Keep AI title for reference
            $parsed_content['title'] = $title; // Use user's title
        }

        return $parsed_content;
    }

    /**
     * Get course content for exercise context
     */
    private function get_course_content_for_exercise($course_ids, $category_ids)
    {
        $content = [];

        // Get content from selected courses
        if (!empty($course_ids)) {
            foreach ($course_ids as $course_id) {
                $course = get_post($course_id);
                if ($course && $course->post_type === 'lp_course') {
                    $content[] = [
                        'title' => $course->post_title,
                        'content' => $course->post_content,
                        'type' => 'course'
                    ];

                    // Get course lessons
                    $curriculum = get_post_meta($course_id, '_lp_curriculum', true);
                    if ($curriculum) {
                        foreach ($curriculum as $item) {
                            if ($item['type'] === 'lp_lesson') {
                                $lesson = get_post($item['id']);
                                if ($lesson) {
                                    $content[] = [
                                        'title' => $lesson->post_title,
                                        'content' => $lesson->post_content,
                                        'type' => 'lesson'
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Build AI prompt for exercise generation
     */
    private function build_exercise_ai_prompt($title, $description, $sections, $complexity, $type, $tools, $course_content)
    {
        $complexity_map = [
            'beginner' => 'débutant',
            'intermediate' => 'intermédiaire',
            'advanced' => 'avancé',
            'expert' => 'expert'
        ];

        $type_map = [
            'dashboard' => 'création de tableau de bord',
            'analysis' => 'analyse de données',
            'visualization' => 'visualisation de données',
            'project' => 'projet complet',
            'case_study' => 'étude de cas',
            'hands_on' => 'pratique dirigée'
        ];

        $content_summary = '';
        if (!empty($course_content)) {
            $content_summary = "Contenu des cours de référence :\n";
            foreach (array_slice($course_content, 0, 5) as $content) {
                $content_summary .= "- {$content['title']}: " . substr(strip_tags($content['content']), 0, 200) . "...\n";
            }
        }

        return "Générez un exercice pratique détaillé avec les spécifications suivantes :

TITRE: {$title}
DESCRIPTION: {$description}
NOMBRE D'ÉTAPES: {$sections}
NIVEAU: " . ($complexity_map[$complexity] ?? $complexity) . "
TYPE: " . ($type_map[$type] ?? $type) . "
OUTILS: {$tools}

{$content_summary}

Créez un exercice pratique structuré avec :
1. Une introduction claire expliquant l'objectif
2. {$sections} étapes détaillées avec instructions précises
3. Des exemples concrets et des captures d'écran suggérées
4. Des conseils et bonnes pratiques
5. Des points de validation pour chaque étape
6. Une conclusion avec les apprentissages clés

Format de réponse JSON :
{
    \"title\": \"Titre de l'exercice\",
    \"section_name\": \"Nom de la section (ex: 'Mise en pratique', 'Exercices dirigés', 'Étapes de création')\",
    \"section_description\": \"Description courte de la section pour expliquer son contenu\",
    \"introduction\": \"Introduction détaillée\",
    \"steps\": [
        {
            \"title\": \"Titre de l'étape\",
            \"content\": \"Contenu détaillé\",
            \"instructions\": [\"Instruction 1\", \"Instruction 2\"],
            \"tips\": [\"Conseil 1\", \"Conseil 2\"],
            \"validation\": \"Point de validation\"
        }
    ],
    \"conclusion\": \"Conclusion avec apprentissages\",
    \"resources\": [\"Ressource 1\", \"Ressource 2\"]
}";
    }

    /**
     * Call AI service for exercise generation
     */
    private function call_ai_for_exercise($prompt)
    {
        $api_key = get_option('quiz_ai_gemini_api_key');
        if (!$api_key) {
            error_log('[EXERCISE_AI] ERREUR: Clé API Gemini manquante');
            return false;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;

        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topP' => 0.95,
                'topK' => 40,
                'maxOutputTokens' => 8192
            ]
        ];

        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
            'timeout' => 60
        ];

        error_log('[EXERCISE_AI] Request args: ' . json_encode($args));
        error_log('[EXERCISE_AI] Data being sent: ' . json_encode($data));
        // For browser debugging (if running in AJAX context)
        if (isset($_POST['debug']) && $_POST['debug']) {
            echo "<script>console.log('[EXERCISE_AI] Request args:', " . json_encode($args) . ");</script>";
            echo "<script>console.log('[EXERCISE_AI] Data being sent:', " . json_encode($data) . ");</script>";
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('[EXERCISE_AI] Erreur API: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (!isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            error_log('[EXERCISE_AI] Réponse API invalide: ' . $body);
            return false;
        }

        return $decoded['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * Parse AI response for exercise content
     */
    private function parse_exercise_response($response)
    {
        // Try to extract JSON from response
        $json_start = strpos($response, '{');
        $json_end = strrpos($response, '}');

        if ($json_start !== false && $json_end !== false) {
            $json_string = substr($response, $json_start, $json_end - $json_start + 1);
            $parsed = json_decode($json_string, true);

            if ($parsed && isset($parsed['title']) && isset($parsed['steps'])) {
                return $parsed;
            }
        }

        // Fallback: create structured content from text
        return [
            'title' => 'Exercice Pratique Généré',
            'introduction' => 'Exercice pratique généré par IA.',
            'steps' => [
                [
                    'title' => 'Étape 1',
                    'content' => substr($response, 0, 1000),
                    'instructions' => ['Suivez les instructions ci-dessus'],
                    'tips' => ['Prenez votre temps'],
                    'validation' => 'Vérifiez votre travail'
                ]
            ],
            'conclusion' => 'Exercice terminé avec succès.',
            'resources' => []
        ];
    }

    /**
     * Create LearnPress course from exercise content
     */
    private function create_learnpress_course($exercise_content, $selected_courses = [], $selected_categories = [])
    {
        error_log('[EXERCISE_AI] Creating LearnPress course...');
        error_log('[EXERCISE_AI] Course title: ' . $exercise_content['title']);
        error_log('[EXERCISE_AI] Selected courses: ' . json_encode($selected_courses));
        error_log('[EXERCISE_AI] Selected categories: ' . json_encode($selected_categories));

        // Create course post
        $course_data = [
            'post_title' => $exercise_content['title'],
            'post_content' => $this->format_course_introduction($exercise_content),
            'post_status' => 'publish', // Change to publish instead of draft
            'post_type' => 'lp_course',
            'post_author' => get_current_user_id()
        ];

        $course_id = wp_insert_post($course_data);

        if (!$course_id || is_wp_error($course_id)) {
            error_log('[EXERCISE_AI] Failed to create course post');
            return false;
        }

        error_log('[EXERCISE_AI] Course created with ID: ' . $course_id);

        // Assign categories to the course (try both LearnPress taxonomy and course_category)
        if (!empty($selected_categories)) {
            $category_ids = array_map('intval', $selected_categories);

            // Try LearnPress course category taxonomy
            $result1 = wp_set_object_terms($course_id, $category_ids, 'course_category');

            // Also try the general category taxonomy
            $result2 = wp_set_object_terms($course_id, $category_ids, 'category');

            // And LearnPress specific taxonomy
            $result3 = wp_set_object_terms($course_id, $category_ids, 'course-category');

            error_log('[EXERCISE_AI] Category assignment results:');
            error_log('[EXERCISE_AI] - course_category: ' . (is_wp_error($result1) ? $result1->get_error_message() : 'success'));
            error_log('[EXERCISE_AI] - category: ' . (is_wp_error($result2) ? $result2->get_error_message() : 'success'));
            error_log('[EXERCISE_AI] - course-category: ' . (is_wp_error($result3) ? $result3->get_error_message() : 'success'));
        }

        // Create lessons for each step using proper LearnPress structure
        $curriculum = [];
        $lesson_ids = [];

        if (isset($exercise_content['steps']) && is_array($exercise_content['steps'])) {
            // Generate section name and description from exercise content
            $section_name = isset($exercise_content['section_name']) ?
                $exercise_content['section_name'] :
                'Étapes - ' . substr($exercise_content['title'], 0, 50);

            $section_description = isset($exercise_content['section_description']) ?
                $exercise_content['section_description'] :
                $exercise_content['description'];

            // Create a section in the LearnPress sections table
            global $wpdb;
            $sections_table = $wpdb->prefix . 'learnpress_sections';

            $section_result = $wpdb->insert(
                $sections_table,
                [
                    'section_name' => $section_name,
                    'section_course_id' => $course_id,
                    'section_order' => 1,
                    'section_description' => $section_description
                ],
                ['%s', '%d', '%d', '%s']
            );

            $section_id = $wpdb->insert_id;
            error_log('[EXERCISE_AI] Created section in LearnPress table with ID: ' . $section_id . ', Name: ' . $section_name);

            // Create lessons and add them to the section
            foreach ($exercise_content['steps'] as $index => $step) {
                $lesson_data = [
                    'post_title' => $step['title'],
                    'post_content' => $this->format_step_content($step),
                    'post_status' => 'publish',
                    'post_type' => 'lp_lesson',
                    'post_author' => get_current_user_id(),
                    'post_parent' => $course_id
                ];

                $lesson_id = wp_insert_post($lesson_data);

                if ($lesson_id && !is_wp_error($lesson_id)) {
                    // Set lesson meta data
                    update_post_meta($lesson_id, '_lp_course', $course_id);
                    update_post_meta($lesson_id, '_lp_duration', '10');
                    update_post_meta($lesson_id, '_lp_preview', 'no');

                    $lesson_ids[] = $lesson_id;

                    // Add lesson to section in LearnPress section items table
                    $section_items_table = $wpdb->prefix . 'learnpress_section_items';
                    $wpdb->insert(
                        $section_items_table,
                        [
                            'section_id' => $section_id,
                            'item_id' => $lesson_id,
                            'item_type' => 'lp_lesson',
                            'item_order' => $index + 1
                        ],
                        ['%d', '%d', '%s', '%d']
                    );

                    error_log('[EXERCISE_AI] Created lesson ' . ($index + 1) . ' with ID: ' . $lesson_id . ' and added to section');
                }
            }

            // Create curriculum structure for meta storage
            if ($section_id && !empty($lesson_ids)) {
                $section_items = [];
                foreach ($lesson_ids as $index => $lesson_id) {
                    $section_items[] = [
                        'id' => intval($lesson_id),
                        'type' => 'lp_lesson'
                    ];
                }

                $curriculum[] = [
                    'id' => $section_id,
                    'type' => 'section',
                    'title' => $section_name,
                    'items' => $section_items
                ];
            }
        }

        // Save curriculum meta data
        if (!empty($curriculum)) {
            update_post_meta($course_id, '_lp_curriculum', $curriculum);
            update_post_meta($course_id, '_lp_lessons', count($lesson_ids));
            update_post_meta($course_id, '_lp_sections', 1);

            error_log('[EXERCISE_AI] Saved curriculum with 1 section and ' . count($lesson_ids) . ' lessons');
            error_log('[EXERCISE_AI] Final curriculum structure: ' . json_encode($curriculum));
        } else {
            error_log('[EXERCISE_AI] No curriculum to save - no lessons created');
        }

        // Set course metadata for LearnPress compatibility
        update_post_meta($course_id, '_quiz_ai_exercise_type', 'practical');
        update_post_meta($course_id, '_quiz_ai_exercise_data', $exercise_content);

        // Essential LearnPress course meta data
        update_post_meta($course_id, '_lp_duration', '0');
        update_post_meta($course_id, '_lp_max_students', '0');
        update_post_meta($course_id, '_lp_price', '');
        update_post_meta($course_id, '_lp_sale_price', '');
        update_post_meta($course_id, '_lp_featured', 'no');
        update_post_meta($course_id, '_lp_course_result', 'evaluate_lesson');
        update_post_meta($course_id, '_lp_passing_condition', '80');
        update_post_meta($course_id, '_lp_course_author', get_current_user_id());
        update_post_meta($course_id, '_lp_students', '0');
        update_post_meta($course_id, '_lp_level', 'beginner');
        update_post_meta($course_id, '_lp_external_link_buy_course', '');
        update_post_meta($course_id, '_lp_external_link', '');
        update_post_meta($course_id, '_lp_course_repurchase', 'no');

        // Set course status to published
        wp_update_post([
            'ID' => $course_id,
            'post_status' => 'publish'
        ]);

        error_log('[EXERCISE_AI] Course creation completed successfully');
        return $course_id;
    }

    /**
     * Format step content for lesson
     */
    private function format_step_content($step)
    {
        $content = "<div class='exercise-step-content' style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>";

        // Main content with styling
        $content .= "<div class='step-description' style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #007cba;'>";
        $content .= "<div style='font-size: 16px;'>" . $step['content'] . "</div>";
        $content .= "</div>";

        // Instructions section with icon and styling
        if (!empty($step['instructions'])) {
            $content .= "<div style='background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px;'>";
            $content .= "<h4 style='color: #007cba; margin: 0 0 15px 0; display: flex; align-items: center;'>";
            $content .= "<span style='background: #007cba; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; margin-right: 10px;'>📋</span>";
            $content .= "Instructions :</h4>";
            $content .= "<ul style='list-style: none; padding: 0; margin: 0;'>";
            foreach ($step['instructions'] as $index => $instruction) {
                $content .= "<li style='padding: 8px 0; border-bottom: 1px solid #eee; display: flex; align-items: flex-start;'>";
                $content .= "<span style='background: #007cba; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px; margin-right: 10px; flex-shrink: 0;'>" . ($index + 1) . "</span>";
                $content .= "<span>" . $instruction . "</span>";
                $content .= "</li>";
            }
            $content .= "</ul>";
            $content .= "</div>";
        }

        // Tips section with lightbulb icon
        if (!empty($step['tips'])) {
            $content .= "<div style='background: #fffbf0; border: 1px solid #f0c33c; border-radius: 8px; padding: 20px; margin-bottom: 20px;'>";
            $content .= "<h4 style='color: #f0c33c; margin: 0 0 15px 0; display: flex; align-items: center;'>";
            $content .= "<span style='background: #f0c33c; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; margin-right: 10px;'>💡</span>";
            $content .= "Conseils :</h4>";
            $content .= "<ul style='list-style: none; padding: 0; margin: 0;'>";
            foreach ($step['tips'] as $tip) {
                $content .= "<li style='padding: 8px 0; border-bottom: 1px solid #f5d982; display: flex; align-items: flex-start;'>";
                $content .= "<span style='color: #f0c33c; margin-right: 10px; flex-shrink: 0;'>💡</span>";
                $content .= "<span>" . $tip . "</span>";
                $content .= "</li>";
            }
            $content .= "</ul>";
            $content .= "</div>";
        }

        // Validation section with checkmark
        if (!empty($step['validation'])) {
            $content .= "<div style='background: #f0f8f0; border: 1px solid #28a745; border-radius: 8px; padding: 20px; margin-bottom: 20px;'>";
            $content .= "<div style='display: flex; align-items: flex-start;'>";
            $content .= "<span style='background: #28a745; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; margin-right: 10px; flex-shrink: 0;'>✓</span>";
            $content .= "<div>";
            $content .= "<strong style='color: #28a745;'>Point de validation :</strong><br>";
            $content .= "<span style='color: #155724;'>" . $step['validation'] . "</span>";
            $content .= "</div>";
            $content .= "</div>";
            $content .= "</div>";
        }

        // Add placeholder for image/screenshot
        $content .= "<div style='background: #f8f9fa; border: 2px dashed #ddd; border-radius: 8px; padding: 40px; text-align: center; margin: 20px 0; color: #666;'>";
        $content .= "<div style='font-size: 48px; margin-bottom: 10px;'>📷</div>";
        $content .= "<div style='font-size: 14px;'>Emplacement pour capture d'écran / image d'exemple</div>";
        $content .= "<div style='font-size: 12px; margin-top: 5px;'>Ajoutez ici une image illustrant cette étape</div>";
        $content .= "</div>";

        $content .= "</div>";

        return $content;
    }

    /**
     * Format course introduction with styling
     */
    private function format_course_introduction($exercise_content)
    {
        $content = "<div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>";

        // Introduction section
        $content .= "<div style='background: linear-gradient(135deg, #007cba 0%, #005a9c 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; text-align: center;'>";
        $content .= "<h2 style='color: white; margin: 0 0 15px 0; font-size: 28px;'>🎯 " . $exercise_content['title'] . "</h2>";
        if (isset($exercise_content['description'])) {
            $content .= "<p style='font-size: 18px; margin: 0; opacity: 0.9;'>" . $exercise_content['description'] . "</p>";
        }
        $content .= "</div>";

        // Introduction content
        if (isset($exercise_content['introduction'])) {
            $content .= "<div style='background: #f8f9fa; padding: 25px; border-radius: 10px; border-left: 5px solid #007cba; margin-bottom: 25px;'>";
            $content .= "<h3 style='color: #007cba; margin: 0 0 15px 0; display: flex; align-items: center;'>";
            $content .= "<span style='background: #007cba; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; margin-right: 12px;'>📚</span>";
            $content .= "Introduction</h3>";
            $content .= "<div style='font-size: 16px;'>" . $exercise_content['introduction'] . "</div>";
            $content .= "</div>";
        }

        // Resources section with clickable links
        $content .= "<div style='background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 10px; padding: 25px; margin-bottom: 25px;'>";
        $content .= "<h3 style='color: #856404; margin: 0 0 15px 0; display: flex; align-items: center;'>";
        $content .= "<span style='background: #ffc107; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; margin-right: 12px;'>📁</span>";
        $content .= "Ressources utiles</h3>";
        $content .= "<ul style='list-style: none; padding: 0; margin: 0;'>";

        // Add course links from selected courses during exercise creation
        if (isset($exercise_content['selected_courses']) && is_array($exercise_content['selected_courses'])) {
            foreach ($exercise_content['selected_courses'] as $course_id) {
                $course_post = get_post($course_id);
                if ($course_post) {
                    $course_slug = $course_post->post_name;
                    $course_title = $course_post->post_title;
                    $course_url = "https://innovation.ma/cours/" . $course_slug;

                    $content .= "<li style='padding: 10px 0; border-bottom: 1px solid #ffeaa7; display: flex; align-items: center;'>";
                    $content .= "<span style='color: #ffc107; margin-right: 10px;'>🎓</span>";
                    $content .= "<a href='" . esc_url($course_url) . "' target='_blank' style='color: #856404; text-decoration: none; font-weight: 500;'>";
                    $content .= $course_title . " <span style='font-size: 12px; opacity: 0.7;'>(Cours de référence)</span>";
                    $content .= "</a>";
                    $content .= "</li>";
                }
            }
        }

        // Add AI-generated resources as clickable links if they look like URLs
        if (isset($exercise_content['resources']) && is_array($exercise_content['resources'])) {
            foreach ($exercise_content['resources'] as $resource) {
                $content .= "<li style='padding: 8px 0; border-bottom: 1px solid #ffeaa7; display: flex; align-items: center;'>";
                $content .= "<span style='color: #ffc107; margin-right: 10px;'>📄</span>";

                // Check if the resource looks like a URL
                if (filter_var($resource, FILTER_VALIDATE_URL)) {
                    $content .= "<a href='" . esc_url($resource) . "' target='_blank' style='color: #856404; text-decoration: none;'>" . $resource . "</a>";
                } elseif (preg_match('/^https?:\/\//', $resource)) {
                    // If it starts with http but filter_var failed, still make it clickable
                    $content .= "<a href='" . esc_url($resource) . "' target='_blank' style='color: #856404; text-decoration: none;'>" . $resource . "</a>";
                } else {
                    // If it's not a URL, display as text
                    $content .= "<span style='color: #856404;'>" . $resource . "</span>";
                }
                $content .= "</li>";
            }
        }
        $content .= "</ul>";
        $content .= "</div>";



        $content .= "</div>";

        return $content;
    }


    /**
     * Handle save draft
     */
    public function handle_save_draft()
    {
        // Verify nonce - accept multiple nonce types
        $nonce_valid = wp_verify_nonce($_POST['nonce'], 'quiz_generator_action') ||
            wp_verify_nonce($_POST['nonce'], 'quiz_ai_admin_nonce') ||
            wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_nonce');

        if (!$nonce_valid) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $form_data = $_POST['form_data'];

        if (empty($form_data['quiz_name'])) {
            wp_send_json_error('Quiz name is required');
        }

        // Generate unique quiz code if not exists
        $quiz_code = !empty($form_data['quiz_code']) ? $form_data['quiz_code'] : $this->generate_quiz_code();

        // Prepare draft data
        $quiz_data = array(
            'title' => sanitize_text_field($form_data['quiz_name']),
            'course_id' => !empty($form_data['course_id']) ? intval($form_data['course_id']) : null,
            'category_id' => !empty($form_data['category_id']) ? intval($form_data['category_id']) : null,
            'quiz_type' => sanitize_text_field($form_data['quiz_type']),
            'form_type' => sanitize_text_field($form_data['form_type']),
            'grading_system' => sanitize_text_field($form_data['grading_system']),
            'time_limit' => intval($form_data['time_limit']),
            'questions_per_page' => intval($form_data['questions_per_page']),
            'total_questions' => intval($form_data['num_questions']),
            'settings' => json_encode($form_data['settings']),
            'ai_provider' => 'gemini', // Always use Gemini
            'ai_instructions' => sanitize_textarea_field($form_data['additional_instructions']),
            'quiz_code' => $quiz_code,
            'status' => 'draft',
            'created_by' => get_current_user_id(),
            'updated_at' => current_time('mysql')
        );

        global $wpdb;
        $quizzes_table = $wpdb->prefix . 'quiz_ia_quizzes';

        if (!empty($form_data['quiz_id'])) {
            // Update existing draft
            $result = $wpdb->update(
                $quizzes_table,
                $quiz_data,
                array('id' => intval($form_data['quiz_id']))
            );
        } else {
            // Create new draft
            $quiz_data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($quizzes_table, $quiz_data);
        }

        if ($result === false) {
            wp_send_json_error('Failed to save draft');
        }

        $quiz_id = !empty($form_data['quiz_id']) ? $form_data['quiz_id'] : $wpdb->insert_id;

        wp_send_json_success(array(
            'quiz_id' => $quiz_id,
            'quiz_code' => $quiz_code,
            'message' => 'Draft saved successfully'
        ));
    }

    /**
     * Get courses for dropdown
     */
    public function handle_get_courses()
    {
        global $wpdb;

        // Use LearnPress courses table
        $learnpress_courses_table = $wpdb->prefix . 'learnpress_courses';

        // Check if LearnPress courses table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$learnpress_courses_table'");

        if (!$table_exists) {
            // No LearnPress courses available
            $courses = array();
        } else {
            // Get courses from LearnPress
            $courses = $wpdb->get_results(
                "SELECT ID as id, post_title as title, post_content as description 
                 FROM $learnpress_courses_table 
                 WHERE post_status = 'publish' 
                 ORDER BY post_title ASC"
            );

            // Clean up the description (remove HTML tags and limit length)
            foreach ($courses as $course) {
                $course->description = wp_trim_words(wp_strip_all_tags($course->description), 20);
            }
        }

        wp_send_json_success($courses);
    }

    /**
     * Get categories for dropdown
     */
    public function handle_get_categories()
    {
        global $wpdb;

        // Use WordPress native taxonomy tables with course_category taxonomy
        $categories = $wpdb->get_results(
            "SELECT t.term_id as id, t.name, tt.description 
             FROM {$wpdb->terms} t 
             INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
             WHERE tt.taxonomy = 'course_category' 
             ORDER BY t.name ASC"
        );

        wp_send_json_success($categories);
    }

    /**
     * Save Gemini API key
     */
    public function handle_save_gemini_api_key()
    {
        // Verify nonce - accept multiple nonce types
        $nonce_valid = wp_verify_nonce($_POST['nonce'], 'quiz_generator_action') ||
            wp_verify_nonce($_POST['nonce'], 'quiz_ai_admin_nonce') ||
            wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_nonce');

        if (!$nonce_valid) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $api_key = sanitize_text_field($_POST['api_key']);

        if (empty($api_key)) {
            wp_send_json_error('API key is required');
        }

        // Save API key
        update_option('quiz_ai_gemini_api_key', $api_key);

        wp_send_json_success(array(
            'message' => 'API key saved successfully'
        ));
    }

    /**
     * Get Gemini API key (masked)
     */
    public function handle_get_gemini_api_key()
    {
        // Verify nonce - accept multiple nonce types
        $nonce_valid = wp_verify_nonce($_POST['nonce'], 'quiz_generator_action') ||
            wp_verify_nonce($_POST['nonce'], 'quiz_ai_admin_nonce') ||
            wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_nonce');

        if (!$nonce_valid) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $api_key = get_option('quiz_ai_gemini_api_key', '');

        // Mask the API key for security (show only first 8 and last 4 characters)
        $masked_key = '';
        if (!empty($api_key)) {
            $key_length = strlen($api_key);
            if ($key_length > 12) {
                $masked_key = substr($api_key, 0, 8) . str_repeat('*', $key_length - 12) . substr($api_key, -4);
            } else {
                $masked_key = str_repeat('*', $key_length);
            }
        }

        wp_send_json_success(array(
            'api_key' => $masked_key,
            'has_key' => !empty($api_key)
        ));
    }

    /**
     * Generate unique quiz code
     */
    private function generate_quiz_code()
    {
        global $wpdb;
        $quizzes_table = $wpdb->prefix . 'quiz_ia_quizzes';

        do {
            $code = strtoupper(wp_generate_password(8, false, false));
            $existing = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM $quizzes_table WHERE quiz_code = %s", $code)
            );
        } while ($existing > 0);

        return $code;
    }

    /**
     * Generate questions with AI using RAG
     */
    private function generate_questions_with_ai_rag($quiz_id, $form_data, $course_ids, $category_ids = [])
    {
        global $debug_info;

        error_log('[QUIZ_AI] === DÉBUT GÉNÉRATION QUESTIONS AI RAG ===');
        error_log('[QUIZ_AI] Quiz ID: ' . $quiz_id);
        error_log('[QUIZ_AI] Cours IDs: ' . json_encode($course_ids));
        error_log('[QUIZ_AI] Catégories IDs: ' . json_encode($category_ids));

        $num_questions = intval($form_data['num_questions']);
        $quiz_type = $form_data['quiz_type'];
        $difficulty = $form_data['difficulty_level'];
        $language = $form_data['language'];
        $quiz_topic = !empty($form_data['quiz_topic']) ? $form_data['quiz_topic'] : '';

        error_log('[QUIZ_AI] Paramètres AI:');
        error_log('[QUIZ_AI] - Provider: Gemini (fixe)');
        error_log('[QUIZ_AI] - Nombre questions: ' . $num_questions);
        error_log('[QUIZ_AI] - Type quiz: ' . $quiz_type);
        error_log('[QUIZ_AI] - Difficulté: ' . $difficulty);
        error_log('[QUIZ_AI] - Langue: ' . $language);
        error_log('[QUIZ_AI] - Sujet: ' . $quiz_topic);

        // Debug information for AI parameters
        if (!isset($debug_info)) $debug_info = [];
        $debug_info['ai_parameters'] = [
            'provider' => 'Gemini (fixed)',
            'num_questions' => $num_questions,
            'quiz_type' => $quiz_type,
            'difficulty' => $difficulty,
            'language' => $language,
            'topic' => $quiz_topic,
            'course_ids' => $course_ids,
            'category_ids' => $category_ids
        ];

        // Get relevant content using RAG
        error_log('[QUIZ_AI] === RÉCUPÉRATION CONTENU RAG ===');

        $rag_content = $this->get_rag_content_for_ai($course_ids, $category_ids, $quiz_topic, $form_data);

        // Debug information for RAG content
        if (!isset($debug_info)) $debug_info = [];
        $debug_info['rag_content'] = [
            'course_ids' => $course_ids,
            'quiz_topic' => $quiz_topic,
            'content_length' => strlen($rag_content),
            'content_preview' => substr($rag_content, 0, 500),
            'full_content' => $rag_content
        ];

        error_log('[QUIZ_AI] Contenu RAG récupéré - Longueur: ' . strlen($rag_content));

        // Prepare AI prompt with RAG content
        error_log('[QUIZ_AI] === CONSTRUCTION PROMPT AI ===');
        $prompt = $this->build_ai_prompt_with_rag($rag_content, $quiz_type, $num_questions, $difficulty, $language, $form_data['additional_instructions']);

        // Debug information for AI prompt
        if (!isset($debug_info)) $debug_info = [];
        $debug_info['ai_prompt'] = [
            'prompt_length' => strlen($prompt),
            'prompt_preview' => substr($prompt, 0, 1000),
            'full_prompt' => $prompt
        ];

        error_log('[QUIZ_AI] Prompt construit - Longueur: ' . strlen($prompt));

        // Call Gemini API
        error_log('[QUIZ_AI] === APPEL GEMINI API ===');
        $result = $this->call_gemini_api($prompt);

        // Debug information for API result
        if (!isset($debug_info)) $debug_info = [];
        $debug_info['api_result'] = [
            'success' => is_array($result),
            'result_type' => gettype($result),
            'questions_count' => is_array($result) ? count($result) : 0,
            'error_message' => is_array($result) ? null : $result,
            'first_question_preview' => is_array($result) && !empty($result) ? json_encode($result[0]) : null
        ];

        error_log('[QUIZ_AI] Résultat Gemini API: ' . (is_array($result) ? 'SUCCÈS (' . count($result) . ' questions)' : 'ÉCHEC - ' . $result));

        return $result;
    }

    /**
     * Get RAG content for AI generation
     */
    private function get_rag_content_for_ai($course_ids, $category_ids = [], $quiz_topic = '', $form_data = [])
    {
        global $debug_info;

        error_log('[QUIZ_AI] === RÉCUPÉRATION CONTENU RAG ===');
        error_log('[QUIZ_AI] Cours IDs: ' . json_encode($course_ids));
        error_log('[QUIZ_AI] Catégories IDs: ' . json_encode($category_ids));
        error_log('[QUIZ_AI] Sujet quiz: ' . $quiz_topic);

        // Initialize RAG debug section
        if (!isset($debug_info['rag_function'])) {
            $debug_info['rag_function'] = [];
        }

        $debug_info['rag_function']['start'] = [
            'course_ids' => $course_ids,
            'category_ids' => $category_ids,
            'quiz_topic' => $quiz_topic,
            'form_data_keys' => array_keys($form_data)
        ];

        // If no courses or categories selected, use fallback
        if (empty($course_ids) && empty($category_ids)) {
            error_log('[QUIZ_AI] Aucun cours ni catégorie sélectionné - utilisation contexte général');
            $debug_info['rag_function']['no_selection'] = true;
            $fallback_content = $this->get_general_context_for_ai($form_data);
            error_log('[QUIZ_AI] Contenu fallback récupéré - Longueur: ' . strlen($fallback_content));
            $debug_info['rag_function']['fallback_content_length'] = strlen($fallback_content);
            return $fallback_content;
        }

        $formatted_content = "";

        // Process selected courses if any
        if (!empty($course_ids)) {
            error_log('[QUIZ_AI] Traitement des cours sélectionnés...');

            // Check if RAG function exists
            if (!function_exists('quiz_ai_pro_format_content_for_ai')) {
                error_log('[QUIZ_AI] ERREUR: Fonction quiz_ai_pro_format_content_for_ai non trouvée!');
                $debug_info['rag_function']['function_missing'] = true;
                return "Erreur: Fonctions RAG non disponibles.";
            }

            // Check if tables exist
            global $wpdb;
            $chunks_table = $wpdb->prefix . 'quiz_ia_course_chunks';
            $chunks_exists = $wpdb->get_var("SHOW TABLES LIKE '$chunks_table'");
            $learnpress_exists = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'lp_course'");

            error_log('[QUIZ_AI] Vérification tables:');
            error_log('[QUIZ_AI] - Table chunks (' . $chunks_table . '): ' . ($chunks_exists ? 'EXISTE' : 'N\'EXISTE PAS'));
            error_log('[QUIZ_AI] - LearnPress courses: ' . $learnpress_exists . ' cours trouvés');

            $debug_info['rag_function']['database_tables'] = [
                'chunks_table' => $chunks_table,
                'chunks_exists' => (bool)$chunks_exists,
                'learnpress_courses_count' => (int)$learnpress_exists
            ];

            if (!$chunks_exists || !$learnpress_exists) {
                error_log('[QUIZ_AI] ERREUR: Tables RAG manquantes!');
                $debug_info['rag_function']['tables_missing'] = true;
                return "Erreur: Tables de base de données manquantes.";
            }

            // Use RAG to get relevant course content
            $course_content = quiz_ai_pro_format_content_for_ai($course_ids, $quiz_topic, 10);
            error_log('[QUIZ_AI] Contenu formaté RAG récupéré - Longueur: ' . strlen($course_content));

            $debug_info['rag_function']['course_content'] = [
                'length' => strlen($course_content),
                'preview' => substr($course_content, 0, 200)
            ];

            $formatted_content .= $course_content;
        }

        // Process selected categories if any
        if (!empty($category_ids)) {
            error_log('[QUIZ_AI] Traitement des catégories sélectionnées...');
            $category_content = $this->get_multiple_categories_context_for_ai($category_ids);

            if (!empty($category_content)) {
                $formatted_content .= "\n" . $category_content;
                error_log('[QUIZ_AI] Contexte catégories ajouté - Longueur: ' . strlen($category_content));
                $debug_info['rag_function']['categories_content'] = [
                    'length' => strlen($category_content),
                    'preview' => substr($category_content, 0, 200)
                ];
            }
        }

        // Add quiz-specific context
        if (!empty($quiz_topic)) {
            error_log('[QUIZ_AI] Ajout sujet spécifique: ' . $quiz_topic);
            $formatted_content .= "\n=== SUJET SPÉCIFIQUE DU QUIZ ===\n";
            $formatted_content .= "🎯 Sujet principal: {$quiz_topic}\n\n";
            $debug_info['rag_function']['topic_added'] = $quiz_topic;
        }

        error_log('[QUIZ_AI] Contenu RAG final - Longueur totale: ' . strlen($formatted_content));
        $debug_info['rag_function']['final_content'] = [
            'total_length' => strlen($formatted_content),
            'preview' => substr($formatted_content, 0, 300)
        ];

        return $formatted_content;
    }

    /**
     * Get category context as fallback
     */
    private function get_category_context_for_ai($form_data)
    {
        if (empty($form_data['category_id'])) {
            // Provide better fallback content instead of generic context
            $context = "=== CONTENU ÉDUCATIF GÉNÉRAL ===\n\n";
            $context .= "📚 **Thèmes d'apprentissage courants :**\n";
            $context .= "- Sciences et Mathématiques : équations, physique, chimie, biologie\n";
            $context .= "- Langues : grammaire, vocabulaire, littérature, expression\n";
            $context .= "- Histoire et Géographie : événements historiques, pays, capitales\n";
            $context .= "- Informatique : programmation, bases de données, réseaux\n";
            $context .= "- Arts et Culture : musique, peinture, cinéma, théâtre\n\n";
            $context .= "🎯 **Instructions pour le quiz :**\n";
            $context .= "Créez des questions éducatives variées sur des sujets d'apprentissage généraux.\n";
            $context .= "Évitez les questions meta sur la création de quiz ou les paramètres techniques.\n";
            $context .= "Concentrez-vous sur des connaissances pratiques et utiles.\n\n";
            return $context;
        }

        $category = quiz_ai_pro_get_category_by_id($form_data['category_id']);
        if (!$category) {
            // Use the improved fallback even when category not found
            return $this->get_category_context_for_ai([]);
        }

        $context = "=== CONTEXTE CATÉGORIE ===\n\n";
        $context .= "📂 **Catégorie: {$category->name}**\n";
        $context .= "📝 Description: {$category->description}\n\n";

        // Get some courses from this category for additional context
        global $wpdb;
        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT p.post_title as title, p.post_content as description 
             FROM {$wpdb->prefix}posts p
             INNER JOIN {$wpdb->prefix}term_relationships tr ON p.ID = tr.object_id
             INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             WHERE p.post_type = 'lp_course' 
             AND p.post_status = 'publish'
             AND tt.term_id = %d
             LIMIT 3",
            $form_data['category_id']
        ));

        if (!empty($courses)) {
            $context .= "📚 **Cours associés:**\n";
            foreach ($courses as $course) {
                $context .= "- {$course->title}: {$course->description}\n";
            }
        } else {
            // Add some context even if no courses are found
            $context .= "📚 **Sujet d'étude:** Créez des questions pertinentes pour la catégorie {$category->name}\n";
            $context .= "Basez-vous sur des connaissances standards de ce domaine d'étude.\n";
        }

        return $context;
    }

    /**
     * Get context for multiple categories
     */
    private function get_multiple_categories_context_for_ai($category_ids)
    {
        if (empty($category_ids)) {
            return "";
        }

        error_log('[QUIZ_AI] Récupération contexte pour ' . count($category_ids) . ' catégories');

        $content = "\n=== CONTEXTE CATÉGORIES ===\n\n";

        foreach ($category_ids as $category_id) {
            $category = quiz_ai_pro_get_category_by_id($category_id);
            if (!$category) {
                error_log('[QUIZ_AI] Catégorie non trouvée pour ID: ' . $category_id);
                continue;
            }

            $content .= "📂 **Catégorie: {$category->name}**\n";
            $content .= "📝 Description: {$category->description}\n";

            // Get some courses from this category for additional context
            global $wpdb;
            $courses = $wpdb->get_results($wpdb->prepare(
                "SELECT p.post_title as title, p.post_content as description 
                 FROM {$wpdb->prefix}posts p
                 INNER JOIN {$wpdb->prefix}term_relationships tr ON p.ID = tr.object_id
                 INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 WHERE p.post_type = 'lp_course' 
                 AND p.post_status = 'publish'
                 AND tt.term_id = %d
                 LIMIT 2",
                $category_id
            ));

            if (!empty($courses)) {
                $content .= "📚 **Cours associés:**\n";
                foreach ($courses as $course) {
                    $description = wp_trim_words(strip_tags($course->description), 15);
                    $content .= "- {$course->title}: {$description}\n";
                }
            }

            $content .= "\n";
            error_log('[QUIZ_AI] ✓ Contexte ajouté pour catégorie: ' . $category->name);
        }

        return $content;
    }

    /**
     * Get general context when no specific selection
     */
    private function get_general_context_for_ai($form_data)
    {
        $context = "=== CONTENU ÉDUCATIF GÉNÉRAL ===\n\n";
        $context .= "📚 **Thèmes d'apprentissage courants :**\n";
        $context .= "- Sciences et Mathématiques : équations, physique, chimie, biologie\n";
        $context .= "- Langues : grammaire, vocabulaire, littérature, expression\n";
        $context .= "- Histoire et Géographie : événements historiques, pays, capitales\n";
        $context .= "- Informatique : programmation, bases de données, réseaux\n";
        $context .= "- Arts et Culture : musique, peinture, cinéma, théâtre\n\n";
        $context .= "🎯 **Instructions pour le quiz :**\n";
        $context .= "Créez des questions éducatives variées sur des sujets d'apprentissage généraux.\n";
        $context .= "Évitez les questions meta sur la création de quiz ou les paramètres techniques.\n";
        $context .= "Concentrez-vous sur des connaissances pratiques et utiles.\n\n";

        return $context;
    }

    /**
     * Build AI prompt with RAG content
     */
    private function build_ai_prompt_with_rag($rag_content, $quiz_type, $num_questions, $difficulty, $language, $additional_instructions)
    {
        // === DEBUG: AI Prompt Building ===
        error_log("=== AI PROMPT DEBUG ===");
        error_log("RAG content length received: " . strlen($rag_content));
        error_log("Quiz type: " . $quiz_type);
        error_log("Number of questions: " . $num_questions);
        error_log("Difficulty: " . $difficulty);
        error_log("Language: " . $language);
        error_log("Additional instructions: " . $additional_instructions);
        error_log("RAG content preview: " . substr($rag_content, 0, 200) . "...");

        $prompt = "Tu es un expert en création de quiz éducatifs. ";
        $prompt .= "Génère un quiz de {$num_questions} questions de type '{$quiz_type}' ";
        $prompt .= "avec un niveau de difficulté '{$difficulty}' en {$language}.\n\n";

        $prompt .= $rag_content . "\n\n";

        $prompt .= "INSTRUCTIONS SPÉCIFIQUES:\n";
        $prompt .= "- Base-toi UNIQUEMENT sur le contenu fourni ci-dessus\n";
        $prompt .= "- Adapte le niveau de difficulté aux étudiants '{$difficulty}'\n";
        $prompt .= "- Assure-toi que les questions testent la compréhension du contenu\n";
        $prompt .= "- Varie les aspects couverts du contenu\n";
        $prompt .= "- Pour les QCM: 4 options de réponse avec une seule bonne réponse\n";
        $prompt .= "- Pour les questions ouvertes: attends des réponses de 2-3 phrases\n";
        $prompt .= "- Pour les questions à compléter (fill_blank): utilise {réponse} dans le texte de la question pour créer des espaces à compléter\n\n";

        $prompt .= "INTERDICTIONS IMPORTANTES:\n";
        $prompt .= "- NE génère PAS de questions sur les paramètres du quiz (nombre de questions, type de quiz, etc.)\n";
        $prompt .= "- NE génère PAS de questions sur la création de quiz ou les instructions techniques\n";
        $prompt .= "- NE génère PAS de questions meta sur le processus de génération\n";
        $prompt .= "- Concentre-toi UNIQUEMENT sur le contenu éducatif fourni\n\n";

        if (!empty($additional_instructions)) {
            $prompt .= "INSTRUCTIONS SUPPLÉMENTAIRES:\n{$additional_instructions}\n\n";
        }

        $prompt .= "Réponds UNIQUEMENT avec un JSON valide dans ce format exact (sans balises markdown ```json):\n";
        $prompt .= "{\n";
        $prompt .= '  "questions": [' . "\n";
        $prompt .= '    {' . "\n";
        $prompt .= '      "question": "Question 1?",' . "\n";
        $prompt .= '      "type": "qcm",' . "\n";
        $prompt .= '      "options": ["Option A", "Option B", "Option C", "Option D"],' . "\n";
        $prompt .= '      "correct_answer": "Option A",' . "\n";
        $prompt .= '      "explanation": "Explication de la réponse"' . "\n";
        $prompt .= '    }' . "\n";
        $prompt .= '  ]' . "\n";
        $prompt .= "}";

        error_log("FULL PROMPT SENT TO AI:");
        error_log($prompt);
        error_log("=== END AI PROMPT DEBUG ===");

        return $prompt;
    }

    /**
     * Build AI prompt
     */
    private function build_ai_prompt($context, $quiz_type, $num_questions, $difficulty, $language, $additional_instructions)
    {
        $language_map = array(
            'fr' => 'français',
            'en' => 'anglais',
            'es' => 'espagnol',
            'de' => 'allemand'
        );

        $lang = isset($language_map[$language]) ? $language_map[$language] : 'français';

        $prompt = "Générez un quiz de {$num_questions} questions en {$lang} sur le sujet suivant:\n\n";
        $prompt .= "{$context}\n\n";
        $prompt .= "Type de questions: {$quiz_type}\n";
        $prompt .= "Niveau de difficulté: {$difficulty}\n\n";

        if (!empty($additional_instructions)) {
            $prompt .= "Instructions supplémentaires: {$additional_instructions}\n\n";
        }

        $prompt .= "Format de réponse attendu (JSON):\n";
        $prompt .= "{\n";
        $prompt .= '  "questions": [' . "\n";
        $prompt .= '    {' . "\n";
        $prompt .= '      "question": "Texte de la question",' . "\n";
        $prompt .= '      "type": "qcm|open|true_false|fill_blank",' . "\n";
        $prompt .= '      "answers": [' . "\n";
        $prompt .= '        {"text": "Réponse 1", "correct": true},' . "\n";
        $prompt .= '        {"text": "Réponse 2", "correct": false}' . "\n";
        $prompt .= '      ],' . "\n";
        $prompt .= '      "explanation": "Explication de la réponse correcte",' . "\n";
        $prompt .= '      "points": 1' . "\n";
        $prompt .= '    }' . "\n";
        $prompt .= '  ]' . "\n";
        $prompt .= "}\n\n";

        $prompt .= "EXEMPLE pour type fill_blank:\n";
        $prompt .= "{\n";
        $prompt .= '  "questions": [' . "\n";
        $prompt .= '    {' . "\n";
        $prompt .= '      "question": "La fonction {SUM} permet de {additionner} les valeurs dans Excel.",' . "\n";
        $prompt .= '      "type": "fill_blank",' . "\n";
        $prompt .= '      "answers": [' . "\n";
        $prompt .= '        {"text": "SUM", "correct": true},' . "\n";
        $prompt .= '        {"text": "additionner", "correct": true}' . "\n";
        $prompt .= '      ],' . "\n";
        $prompt .= '      "explanation": "SUM est la fonction d\'addition dans Excel",' . "\n";
        $prompt .= '      "points": 1' . "\n";
        $prompt .= '    }' . "\n";
        $prompt .= '  ]' . "\n";
        $prompt .= "}\n\n";
        $prompt .= "Assurez-vous que les questions soient variées, pertinentes et bien formulées.";

        return $prompt;
    }

    /**
     * Call Gemini API
     */
    private function call_gemini_api($prompt)
    {
        error_log('[QUIZ_AI] === APPEL GEMINI API ===');
        error_log('[QUIZ_AI] Longueur du prompt: ' . strlen($prompt));
        error_log('[QUIZ_AI] Début du prompt: ' . substr($prompt, 0, 200) . '...');

        // Get API key from WordPress options
        $api_key = get_option('quiz_ai_gemini_api_key');

        if (empty($api_key)) {
            error_log('[QUIZ_AI] ERREUR: Clé API Gemini non configurée');
            error_log('[QUIZ_AI] Pour configurer la clé API, allez dans le tableau de bord Quiz IA Pro');
            // Return error instead of sample questions
            error_log('[QUIZ_AI] Retour d\'erreur - clé API manquante');
            return "ERREUR: Clé API Gemini non configurée. Veuillez configurer votre clé API dans les paramètres.";
        }

        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;

        // Prepare request data for Gemini API
        $request_data = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => $prompt
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'topP' => 0.95,
                'topK' => 40,
                'maxOutputTokens' => 8192
            )
        );

        error_log('[QUIZ_AI] URL API: ' . $api_url);
        error_log('[QUIZ_AI] Données de requête: ' . json_encode($request_data));

        // Make HTTP request
        $response = wp_remote_post($api_url, array(
            'method' => 'POST',
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_data)
        ));

        if (is_wp_error($response)) {
            error_log('[QUIZ_AI] ERREUR HTTP: ' . $response->get_error_message());
            // Return error instead of sample questions
            return "ERREUR: Impossible de contacter l'API Gemini. " . $response->get_error_message();
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log('[QUIZ_AI] Code de réponse HTTP: ' . $response_code);
        error_log('[QUIZ_AI] Corps de réponse: ' . substr($response_body, 0, 500) . '...');

        if ($response_code !== 200) {
            error_log('[QUIZ_AI] ERREUR API: Code ' . $response_code . ' - ' . $response_body);
            // Return error instead of sample questions
            return "ERREUR: L'API Gemini a retourné une erreur (Code: " . $response_code . "). Vérifiez votre clé API.";
        }

        $data = json_decode($response_body, true);

        if (!$data || !isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            error_log('[QUIZ_AI] ERREUR: Format de réponse invalide');
            error_log('[QUIZ_AI] Réponse complète: ' . $response_body);
            // Return error instead of sample questions
            return "ERREUR: Format de réponse invalide de l'API Gemini. La réponse ne contient pas le texte attendu.";
        }

        $generated_text = $data['candidates'][0]['content']['parts'][0]['text'];
        error_log('[QUIZ_AI] Texte généré: ' . substr($generated_text, 0, 300) . '...');

        // Clean the generated text to remove markdown code blocks if present
        $cleaned_text = $this->clean_gemini_response($generated_text);

        // Parse JSON response from Gemini
        $questions_data = json_decode($cleaned_text, true);

        if (!$questions_data || !isset($questions_data['questions'])) {
            error_log('[QUIZ_AI] ERREUR: Format JSON invalide dans la réponse Gemini');
            error_log('[QUIZ_AI] Texte généré complet: ' . $generated_text);
            error_log('[QUIZ_AI] Texte nettoyé: ' . $cleaned_text);
            // Return error instead of sample questions
            return "ERREUR: L'API Gemini n'a pas retourné un format JSON valide. Texte reçu: " . substr($cleaned_text, 0, 200) . "...";
        }

        // Convert Gemini format to our internal format
        $questions = array();

        foreach ($questions_data['questions'] as $index => $q) {
            $formatted_question = array(
                'question' => $q['question'],
                'type' => $q['type'],
                'answers' => array(),
                'explanation' => $q['explanation'] ?? '',
                'points' => 1
            );

            // Handle different question types
            if ($q['type'] === 'qcm' && isset($q['options'])) {
                foreach ($q['options'] as $option) {
                    $formatted_question['answers'][] = array(
                        'text' => $option,
                        'correct' => ($option === $q['correct_answer'])
                    );
                }
            } else {
                // For open questions or other types
                $formatted_question['answers'][] = array(
                    'text' => $q['correct_answer'] ?? '',
                    'correct' => true
                );
            }

            $questions[] = $formatted_question;
        }

        error_log('[QUIZ_AI] Questions formatées: ' . count($questions));
        error_log('[QUIZ_AI] === FIN APPEL GEMINI API ===');

        return $questions;
    }

    /**
     * Clean Gemini response to remove markdown code blocks
     */
    private function clean_gemini_response($text)
    {
        // Remove markdown code block markers
        $cleaned = preg_replace('/^```json\s*/i', '', $text);
        $cleaned = preg_replace('/\s*```\s*$/', '', $cleaned);

        // Remove any other markdown code block markers
        $cleaned = preg_replace('/^```[a-zA-Z]*\s*/i', '', $cleaned);

        // Trim whitespace
        $cleaned = trim($cleaned);

        return $cleaned;
    }

    /**
     * Save questions to database
     */
    private function save_questions($quiz_id, $questions)
    {
        /*    error_log('[QUIZ_AI] === DÉBUT SAUVEGARDE QUESTIONS ===');
        error_log('[QUIZ_AI] Quiz ID: ' . $quiz_id);
        error_log('[QUIZ_AI] Nombre de questions à sauvegarder: ' . count($questions));
 */
        global $wpdb;
        $questions_table = $wpdb->prefix . 'quiz_ia_questions';
        $answers_table = $wpdb->prefix . 'quiz_ia_answers';

        /*  error_log('[QUIZ_AI] Tables utilisées:');
        error_log('[QUIZ_AI] - Questions: ' . $questions_table);
        error_log('[QUIZ_AI] - Réponses: ' . $answers_table);
 */
        $saved_questions = 0;
        $saved_answers = 0;

        foreach ($questions as $index => $question_data) {
            /*    error_log('[QUIZ_AI] --- Sauvegarde question ' . ($index + 1) . ' ---');
            error_log('[QUIZ_AI] Question: ' . substr($question_data['question'], 0, 100) . '...');
            error_log('[QUIZ_AI] Type: ' . $question_data['type']);
 */
            // Save question
            $question = array(
                'quiz_id' => $quiz_id,
                'question_text' => $question_data['question'],
                'question_type' => $question_data['type'],
                'explanation' => $question_data['explanation'],
                'points' => $question_data['points'],
                'sort_order' => $index + 1,
                'created_at' => current_time('mysql')
            );

            $question_result = $wpdb->insert($questions_table, $question);
            // error_log('[QUIZ_AI] Résultat insertion question: ' . ($question_result !== false ? 'SUCCÈS' : 'ÉCHEC'));

            if ($question_result === false) {
                error_log('[QUIZ_AI] ERREUR insertion question: ' . $wpdb->last_error);
                error_log('[QUIZ_AI] Requête SQL question: ' . $wpdb->last_query);
                continue;
            }

            $question_id = $wpdb->insert_id;
            //  error_log('[QUIZ_AI] ✓ Question sauvegardée avec ID: ' . $question_id);
            $saved_questions++;

            // Save answers
            // error_log('[QUIZ_AI] Sauvegarde des réponses pour question ID: ' . $question_id);
            // error_log('[QUIZ_AI] Nombre de réponses: ' . count($question_data['answers']));

            foreach ($question_data['answers'] as $answer_index => $answer_data) {
                $answer = array(
                    'question_id' => $question_id,
                    'answer_text' => $answer_data['text'],
                    'is_correct' => $answer_data['correct'] ? 1 : 0,
                    'sort_order' => $answer_index + 1,
                    'created_at' => current_time('mysql')
                );

                $answer_result = $wpdb->insert($answers_table, $answer);
                // error_log('[QUIZ_AI] Réponse ' . ($answer_index + 1) . ': ' . ($answer_result !== false ? 'SUCCÈS' : 'ÉCHEC') . ' - Correcte: ' . ($answer_data['correct'] ? 'OUI' : 'NON'));

                if ($answer_result === false) {
                    error_log('[QUIZ_AI] ERREUR insertion réponse: ' . $wpdb->last_error);
                } else {
                    $saved_answers++;
                }
            }
        }

        /*  error_log('[QUIZ_AI] === RÉSUMÉ SAUVEGARDE ===');
        error_log('[QUIZ_AI] Questions sauvegardées: ' . $saved_questions . '/' . count($questions));
        error_log('[QUIZ_AI] Réponses sauvegardées: ' . $saved_answers);
        error_log('[QUIZ_AI] === FIN SAUVEGARDE QUESTIONS ===');
 */
        return $saved_questions > 0;
    }

    /**
     * Debug quiz tables and configuration
     */
    public function handle_debug_quiz_tables()
    {
        global $wpdb;

        $debug_info = array();

        // Check tables
        $tables = array(
            'quiz_ia_quizzes',
            'quiz_ia_questions',
            'quiz_ia_answers',
            'quiz_ia_results',
            'quiz_ia_course_chunks',
            'quiz_ia_comments'
        );

        $missing_tables = array();

        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            $table_exists = !empty($exists);

            $debug_info['tables'][$table] = array(
                'full_name' => $full_table_name,
                'exists' => $table_exists
            );

            if (!$table_exists) {
                $missing_tables[] = $table;
            }
        }

        // If there are missing tables, try to create them
        if (!empty($missing_tables)) {
            $debug_info['creating_missing_tables'] = $missing_tables;

            // Call the table creation function
            $creation_result = quiz_ai_pro_create_all_tables();
            $debug_info['table_creation_result'] = $creation_result;

            // Re-check tables after creation attempt
            foreach ($missing_tables as $table) {
                $full_table_name = $wpdb->prefix . $table;
                $exists_after = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
                $debug_info['tables'][$table]['exists_after_creation'] = !empty($exists_after);
            }
        }

        // Check API key
        $api_key = get_option('quiz_ai_gemini_api_key');
        $debug_info['api_key'] = array(
            'configured' => !empty($api_key),
            'length' => strlen($api_key)
        );

        // Check WordPress constants
        $debug_info['wordpress'] = array(
            'WP_DEBUG' => defined('WP_DEBUG') ? WP_DEBUG : false,
            'WP_DEBUG_LOG' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
            'current_user_id' => get_current_user_id(),
            'can_manage_options' => current_user_can('manage_options')
        );

        wp_send_json_success($debug_info);
    }

    /**
     * Fix category_id column
     */
    public function handle_fix_category_id_column()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $result = quiz_ai_pro_fix_category_id_column();

        if ($result) {
            wp_send_json_success(array(
                'message' => 'Category ID column fixed successfully',
                'fixed' => true
            ));
        } else {
            wp_send_json_error('Failed to fix category ID column');
        }
    }

    /**
     * Populate course chunks for RAG
     */
    public function handle_populate_course_chunks()
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        error_log('[QUIZ_AI CHUNKS] === STARTING COURSE CHUNKS POPULATION ===');
        error_log('[QUIZ_AI CHUNKS] Checking user permissions...');

        global $wpdb;

        // Clear existing chunks first
        $chunks_table = $wpdb->prefix . 'quiz_ia_course_chunks';
        error_log('[QUIZ_AI CHUNKS] Clearing existing chunks from table: ' . $chunks_table);
        $wpdb->query("TRUNCATE TABLE $chunks_table");
        error_log('[QUIZ_AI CHUNKS] Existing chunks cleared');

        // Process all courses for RAG
        error_log('[QUIZ_AI CHUNKS] Starting RAG processing for all courses...');
        $processed_count = quiz_ai_pro_process_all_courses_for_rag();
        error_log('[QUIZ_AI CHUNKS] Processed ' . $processed_count . ' courses');

        if ($processed_count > 0) {
            // Get total chunks created
            $total_chunks = $wpdb->get_var("SELECT COUNT(*) FROM $chunks_table");
            error_log('[QUIZ_AI CHUNKS] Total chunks created: ' . $total_chunks);
            error_log('[QUIZ_AI CHUNKS] === COURSE CHUNKS POPULATION COMPLETED SUCCESSFULLY ===');

            wp_send_json_success([
                'message' => "Successfully processed $processed_count courses and created $total_chunks content chunks for AI generation",
                'courses_processed' => $processed_count,
                'chunks_created' => $total_chunks
            ]);
        } else {
            error_log('[QUIZ_AI CHUNKS] No courses found to process or processing failed');
            wp_send_json_error('No courses found to process or processing failed');
        }
    }

    /**
     * Handle saving individual question data
     */
    public function handle_save_question()
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_save_question')) {
                wp_send_json_error('Security check failed');
                return;
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            $quiz_id = intval($_POST['quiz_id']);
            $question_index = intval($_POST['question_index']);
            $question_data = $_POST['question_data'];

            if (!$quiz_id || !isset($question_data)) {
                wp_send_json_error('Missing required data');
                return;
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'quiz_ai_pro_quizzes';

            // Get current quiz
            $quiz = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $quiz_id
            ));

            if (!$quiz) {
                wp_send_json_error('Quiz not found');
                return;
            }

            // Decode existing questions
            $questions = json_decode($quiz->questions, true);
            if (!is_array($questions)) {
                wp_send_json_error('Invalid quiz format');
                return;
            }

            // Update the specific question
            if (isset($questions[$question_index])) {
                $questions[$question_index] = array_merge($questions[$question_index], $question_data);

                // Update the quiz in database
                $updated = $wpdb->update(
                    $table_name,
                    ['questions' => json_encode($questions)],
                    ['id' => $quiz_id],
                    ['%s'],
                    ['%d']
                );

                if ($updated !== false) {
                    wp_send_json_success('Question saved successfully');
                } else {
                    wp_send_json_error('Failed to save question');
                }
            } else {
                wp_send_json_error('Question not found');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle saving quiz settings
     */
    public function handle_save_quiz_settings()
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_save_settings')) {
                wp_send_json_error('Security check failed');
                return;
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            $quiz_id = intval($_POST['quiz_id']);
            $settings = $_POST['settings'];

            if (!$quiz_id || !isset($settings)) {
                wp_send_json_error('Missing required data');
                return;
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'quiz_ia_quizzes';

            // Separate direct quiz fields from settings
            $quiz_fields = [];
            $settings_fields = [];

            // Debug: Log the received settings
            error_log('[QUIZ_AI] Received settings for quiz ID ' . $quiz_id . ': ' . print_r($settings, true));

            // Fields that should be saved directly in the quiz table 
            $direct_fields = [
                'title',
                'course_id',
                'category_id',
                'description',
                'quiz_type',
                'form_type',
                'grading_system',
                'questions_per_page',
                'time_limit',
                'featured_image'
            ];

            foreach ($settings as $key => $value) {
                if (in_array($key, $direct_fields)) {
                    // Convert empty strings to null for integer fields
                    if (in_array($key, ['course_id', 'category_id', 'questions_per_page', 'time_limit']) && $value === '') {
                        $quiz_fields[$key] = null;
                    } else {
                        $quiz_fields[$key] = $value;
                    }
                } else {
                    // These go into the settings JSON field
                    // Ensure boolean values are properly typed
                    if (is_string($value) && ($value === 'true' || $value === 'false')) {
                        $settings_fields[$key] = ($value === 'true');
                    } else {
                        $settings_fields[$key] = $value;
                    }
                }
            }

            // Debug: Log the settings that will be saved to JSON
            error_log('[QUIZ_AI] Settings fields to save as JSON: ' . print_r($settings_fields, true));

            // Add settings JSON and updated timestamp
            $quiz_fields['settings'] = json_encode($settings_fields);
            $quiz_fields['updated_at'] = current_time('mysql');

            // Prepare format array
            $format = [];
            foreach ($quiz_fields as $key => $value) {
                if (in_array($key, ['course_id', 'category_id', 'questions_per_page', 'time_limit'])) {
                    $format[] = is_null($value) ? null : '%d';
                } else {
                    $format[] = '%s';
                }
            }

            // Update quiz with all fields
            $updated = $wpdb->update(
                $table_name,
                $quiz_fields,
                ['id' => $quiz_id],
                $format,
                ['%d']
            );

            if ($updated !== false) {
                wp_send_json_success('Settings saved successfully');
            } else {
                // Check if it's because no changes were made
                $last_error = $wpdb->last_error;
                if (empty($last_error)) {
                    wp_send_json_success('Settings saved successfully (no changes)');
                } else {
                    error_log('Quiz settings update error: ' . $last_error);
                    wp_send_json_error('Database error: ' . $last_error);
                }
            }
        } catch (Exception $e) {
            error_log('Quiz settings save exception: ' . $e->getMessage());
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle deleting a question
     */
    public function handle_delete_question()
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_delete_question')) {
                wp_send_json_error('Security check failed');
                return;
            }

            // Check permissions
            if (!current_user_can('edit_posts')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            $quiz_id = intval($_POST['quiz_id']);
            $question_id = intval($_POST['question_id']);

            if (!$quiz_id || !$question_id) {
                wp_send_json_error('Missing required data');
                return;
            }

            global $wpdb;
            $questions_table = $wpdb->prefix . 'quiz_ia_questions';
            $answers_table = $wpdb->prefix . 'quiz_ia_answers';

            error_log('[QUIZ_AI] Deleting question - Question ID: ' . $question_id . ', Quiz ID: ' . $quiz_id);

            // Verify question exists and belongs to the quiz
            $question_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $questions_table WHERE id = %d AND quiz_id = %d",
                $question_id,
                $quiz_id
            ));

            if (!$question_exists) {
                error_log('[QUIZ_AI] Question not found or does not belong to quiz');
                wp_send_json_error('Question not found or does not belong to this quiz');
                return;
            }

            // Delete answers first (foreign key constraint)
            $answers_deleted = $wpdb->delete($answers_table, ['question_id' => $question_id], ['%d']);
            error_log('[QUIZ_AI] Deleted ' . $answers_deleted . ' answers for question ' . $question_id);

            // Delete question
            $deleted = $wpdb->delete($questions_table, ['id' => $question_id, 'quiz_id' => $quiz_id], ['%d', '%d']);
            error_log('[QUIZ_AI] Question deletion result: ' . ($deleted ? 'SUCCESS' : 'FAILED'));

            if ($deleted) {
                wp_send_json_success('Question deleted successfully');
            } else {
                error_log('[QUIZ_AI] Failed to delete question - SQL Error: ' . $wpdb->last_error);
                wp_send_json_error('Failed to delete question: ' . $wpdb->last_error);
            }
        } catch (Exception $e) {
            error_log('[QUIZ_AI] Exception in delete_question: ' . $e->getMessage());
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle duplicating a question
     */
    public function handle_duplicate_question()
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_duplicate_question')) {
                wp_send_json_error('Security check failed');
                return;
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            $quiz_id = intval($_POST['quiz_id']);
            $question_id = intval($_POST['question_id']);

            if (!$quiz_id || !$question_id) {
                wp_send_json_error('Missing required data');
                return;
            }

            global $wpdb;
            $questions_table = $wpdb->prefix . 'quiz_ia_questions';
            $answers_table = $wpdb->prefix . 'quiz_ia_answers';

            // Get original question
            $original_question = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $questions_table WHERE id = %d AND quiz_id = %d",
                $question_id,
                $quiz_id
            ));

            if (!$original_question) {
                wp_send_json_error('Question not found');
                return;
            }

            // Get max sort order
            $max_sort = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(sort_order) FROM $questions_table WHERE quiz_id = %d",
                $quiz_id
            ));

            // Create duplicate question
            $new_question_data = [
                'quiz_id' => $quiz_id,
                'question_text' => $original_question->question_text . ' (Copie)',
                'question_type' => $original_question->question_type,
                'explanation' => $original_question->explanation,
                'points' => $original_question->points,
                'sort_order' => ($max_sort + 1),
                'created_at' => current_time('mysql')
            ];

            $question_result = $wpdb->insert($questions_table, $new_question_data);

            if ($question_result) {
                $new_question_id = $wpdb->insert_id;

                // Duplicate answers
                $original_answers = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $answers_table WHERE question_id = %d ORDER BY sort_order",
                    $question_id
                ));

                foreach ($original_answers as $answer) {
                    $new_answer_data = [
                        'question_id' => $new_question_id,
                        'answer_text' => $answer->answer_text,
                        'is_correct' => $answer->is_correct,
                        'sort_order' => $answer->sort_order,
                        'created_at' => current_time('mysql')
                    ];
                    $wpdb->insert($answers_table, $new_answer_data);
                }

                wp_send_json_success(['message' => 'Question duplicated successfully', 'new_question_id' => $new_question_id]);
            } else {
                wp_send_json_error('Failed to duplicate question');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle reordering questions
     */
    public function handle_reorder_questions()
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_reorder_questions')) {
                wp_send_json_error('Security check failed');
                return;
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            $quiz_id = intval($_POST['quiz_id']);
            $question_order = $_POST['question_order']; // Array of question IDs in new order

            if (!$quiz_id || !is_array($question_order)) {
                wp_send_json_error('Missing required data');
                return;
            }

            global $wpdb;
            $questions_table = $wpdb->prefix . 'quiz_ia_questions';

            // Update sort order for each question
            foreach ($question_order as $index => $question_id) {
                $wpdb->update(
                    $questions_table,
                    ['sort_order' => $index + 1],
                    ['id' => intval($question_id), 'quiz_id' => $quiz_id],
                    ['%d'],
                    ['%d', '%d']
                );
            }

            wp_send_json_success('Questions reordered successfully');
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle adding a new question
     */
    public function handle_add_question()
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_add_question')) {
                wp_send_json_error('Security check failed');
                return;
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            $quiz_id = intval($_POST['quiz_id']);
            $question_type = sanitize_text_field($_POST['question_type'] ?? 'qcm');

            if (!$quiz_id) {
                wp_send_json_error('Missing quiz ID');
                return;
            }

            global $wpdb;
            $questions_table = $wpdb->prefix . 'quiz_ia_questions';
            $answers_table = $wpdb->prefix . 'quiz_ia_answers';

            // Get max sort order
            $max_sort = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(sort_order) FROM $questions_table WHERE quiz_id = %d",
                $quiz_id
            ));

            // Create new question
            $new_question_data = [
                'quiz_id' => $quiz_id,
                'question_text' => 'Nouvelle question',
                'question_type' => $question_type,
                'explanation' => '',
                'points' => 1,
                'sort_order' => ($max_sort + 1),
                'created_at' => current_time('mysql')
            ];

            $question_result = $wpdb->insert($questions_table, $new_question_data);

            if ($question_result) {
                $new_question_id = $wpdb->insert_id;

                // Add default answers based on question type
                if ($question_type === 'qcm') {
                    $default_answers = [
                        ['text' => 'Option A', 'correct' => true],
                        ['text' => 'Option B', 'correct' => false],
                        ['text' => 'Option C', 'correct' => false],
                        ['text' => 'Option D', 'correct' => false]
                    ];

                    foreach ($default_answers as $index => $answer) {
                        $answer_data = [
                            'question_id' => $new_question_id,
                            'answer_text' => $answer['text'],
                            'is_correct' => $answer['correct'] ? 1 : 0,
                            'sort_order' => $index + 1,
                            'created_at' => current_time('mysql')
                        ];
                        $wpdb->insert($answers_table, $answer_data);
                    }
                } elseif ($question_type === 'true_false') {
                    $true_false_answers = [
                        ['text' => 'Vrai', 'correct' => true],
                        ['text' => 'Faux', 'correct' => false]
                    ];

                    foreach ($true_false_answers as $index => $answer) {
                        $answer_data = [
                            'question_id' => $new_question_id,
                            'answer_text' => $answer['text'],
                            'is_correct' => $answer['correct'] ? 1 : 0,
                            'sort_order' => $index + 1,
                            'created_at' => current_time('mysql')
                        ];
                        $wpdb->insert($answers_table, $answer_data);
                    }
                }

                wp_send_json_success(['message' => 'Question added successfully', 'new_question_id' => $new_question_id]);
            } else {
                wp_send_json_error('Failed to add question');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle importing questions
     */
    public function handle_import_questions()
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_import_questions')) {
                wp_send_json_error('Security check failed');
                return;
            }

            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            $quiz_id = intval($_POST['quiz_id']);
            $import_data = $_POST['import_data']; // JSON or CSV data

            if (!$quiz_id || empty($import_data)) {
                wp_send_json_error('Missing required data');
                return;
            }

            // For now, return a placeholder response
            // This would need to be implemented based on the import format
            wp_send_json_success('Import functionality will be implemented based on requirements');
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle quiz preview AJAX request
     */
    public function handle_preview_quiz()
    {
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Verify nonce first
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_preview')) {
            error_log('Nonce verification failed. Nonce: ' . ($_POST['nonce'] ?? 'not set'));
            wp_send_json_error('Nonce verification failed');
            return;
        }

        global $wpdb;

        $quiz_id = intval($_POST['quiz_id']);

        if (!$quiz_id) {
            wp_send_json_error('ID du quiz manquant');
            return;
        }

        // Check if quiz exists
        $quiz = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}quiz_ia_quizzes WHERE id = %d",
            $quiz_id
        ));

        if (!$quiz) {
            wp_send_json_error('Quiz non trouvé');
            return;
        }

        try {
            // Generate a realistic end-user quiz preview
            $questions = [];
            if ($quiz->questions) {
                $questions = json_decode($quiz->questions, true);
            } else {
                // Try to get from separate questions table
                $question_results = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}quiz_ia_questions WHERE quiz_id = %d ORDER BY sort_order ASC",
                    $quiz->id
                ));

                foreach ($question_results as $q) {
                    // Get answers for this question
                    $answers = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}quiz_ia_answers WHERE question_id = %d ORDER BY sort_order ASC",
                        $q->id
                    ));

                    $answer_options = [];
                    foreach ($answers as $answer) {
                        $answer_options[] = [
                            'text' => $answer->answer_text,
                            'correct' => $answer->is_correct
                        ];
                    }

                    $questions[] = [
                        'question' => $q->question_text,
                        'type' => $q->question_type,
                        'answers' => $answer_options
                    ];
                }
            }

            $settings = json_decode($quiz->settings, true) ?: [];

            // Extract quiz settings with defaults
            $questions_per_page = isset($settings['questions_per_page']) && $settings['questions_per_page'] > 0 ? intval($settings['questions_per_page']) : 1;
            $time_limit = isset($settings['time_limit']) && $settings['time_limit'] > 0 ? intval($settings['time_limit']) : 0;
            $show_results_immediately = isset($settings['show_results_immediately']) ? $settings['show_results_immediately'] : true;
            $allow_review = isset($settings['allow_review']) ? $settings['allow_review'] : true;
            $randomize_questions = isset($settings['randomize_questions']) ? $settings['randomize_questions'] : false;
            $randomize_answers = isset($settings['randomize_answers']) ? $settings['randomize_answers'] : false;

            // Generate full end-user preview with realistic styling
            $preview_html = '
            <div class="quiz-container-preview" style="max-width: 800px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; background: #f8f9fa; padding: 20px;">
                
                <!-- Quiz Header -->
                <div class="quiz-header" style="background: white; border-radius: 12px; padding: 40px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center;">
                    <h1 style="color: #2c3e50; margin: 0 0 20px 0; font-size: 32px; font-weight: 700;">' . esc_html($quiz->title) . '</h1>';

            if (!empty($quiz->description)) {
                $preview_html .= '<p style="color: #6c757d; font-size: 18px; line-height: 1.6; margin: 0 0 30px 0;">' . esc_html($quiz->description) . '</p>';
            }

            $preview_html .= '
                    <div class="quiz-info-badges" style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap; margin-bottom: 30px;">
                        <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 16px; border-radius: 25px; font-size: 14px; font-weight: 500;">📚 ' . esc_html(ucfirst($quiz->quiz_type ?? 'Quiz')) . '</span>
                        <span style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 8px 16px; border-radius: 25px; font-size: 14px; font-weight: 500;">❓ ' . count($questions) . ' Questions</span>';

            if ($time_limit > 0) {
                $preview_html .= '<span style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 8px 16px; border-radius: 25px; font-size: 14px; font-weight: 500;">⏱️ ' . $time_limit . ' min</span>';
            }

            if ($questions_per_page > 1) {
                $preview_html .= '<span style="background: linear-gradient(135deg, #ff9a56 0%, #ff6b6b 100%); color: white; padding: 8px 16px; border-radius: 25px; font-size: 14px; font-weight: 500;">📄 ' . $questions_per_page . ' questions/page</span>';
            }

            $preview_html .= '
                    </div>';

            // Add timer if time limit is set
            if ($time_limit > 0) {
                $preview_html .= '
                    <div class="quiz-timer" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin-bottom: 20px; text-align: center; display: none;">
                        <div style="color: #856404; font-weight: 600; font-size: 18px;">
                            ⏰ Temps restant: <span class="timer-display" style="color: #d63031; font-family: monospace; font-size: 20px;">' . $time_limit . ':00</span>
                        </div>
                        <div style="background: #ffeaa7; height: 4px; border-radius: 2px; margin-top: 10px;">
                            <div class="timer-progress" style="background: #fdcb6e; height: 100%; border-radius: 2px; width: 100%; transition: width 1s linear;"></div>
                        </div>
                    </div>';
            }

            $preview_html .= '
                    <!-- Progress Bar -->
                    <div class="progress-container" style="background: #e9ecef; border-radius: 10px; height: 8px; margin-bottom: 20px; overflow: hidden;">
                        <div class="progress-bar" style="background: linear-gradient(90deg, #00c6ff 0%, #0072ff 100%); height: 100%; width: 0%; border-radius: 10px; transition: width 0.3s ease;"></div>
                    </div>
                    
                    <button class="start-quiz-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 15px 40px; border-radius: 30px; font-size: 18px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);" onmouseover="this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'0 6px 20px rgba(102, 126, 234, 0.6)\';" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 4px 15px rgba(102, 126, 234, 0.4)\';" onclick="
                        console.log(\'Starting quiz preview with settings\');
                        let currentQuestionIndex = 0;
                        let currentPage = 0;
                        const totalQuestions = ' . count($questions) . ';
                        const questionsPerPage = ' . $questions_per_page . ';
                        const timeLimit = ' . $time_limit . ';
                        const totalPages = Math.ceil(totalQuestions / questionsPerPage);
                        let timeRemaining = timeLimit * 60; // Convert to seconds
                        let timerInterval = null;
                        
                        // Hide header and show questions
                        document.querySelector(\'.quiz-header\').style.display = \'none\';
                        document.querySelector(\'.quiz-questions-container\').style.display = \'block\';
                        
                        // Start timer if time limit is set
                        if (timeLimit > 0) {
                            document.querySelector(\'.quiz-timer\').style.display = \'block\';
                            startTimer();
                        }
                        
                        function startTimer() {
                            timerInterval = setInterval(() => {
                                timeRemaining--;
                                updateTimerDisplay();
                                
                                if (timeRemaining <= 0) {
                                    clearInterval(timerInterval);
                                    alert(\'Temps écoulé ! Le quiz va se terminer automatiquement.\');
                                    finishQuiz();
                                }
                            }, 1000);
                        }
                        
                        function updateTimerDisplay() {
                            const minutes = Math.floor(timeRemaining / 60);
                            const seconds = timeRemaining % 60;
                            const display = minutes + \':\' + (seconds < 10 ? \'0\' : \'\') + seconds;
                            document.querySelector(\'.timer-display\').textContent = display;
                            
                            // Update timer progress bar
                            const totalTime = timeLimit * 60;
                            const progress = (timeRemaining / totalTime) * 100;
                            document.querySelector(\'.timer-progress\').style.width = progress + \'%\';
                            
                            // Change color when time is running out
                            if (timeRemaining < 60) {
                                document.querySelector(\'.timer-display\').style.color = \'#d63031\';
                                document.querySelector(\'.timer-progress\').style.background = \'#d63031\';
                            } else if (timeRemaining < 300) {
                                document.querySelector(\'.timer-display\').style.color = \'#e17055\';
                                document.querySelector(\'.timer-progress\').style.background = \'#e17055\';
                            }
                        }
                        
                        function showPage(pageIndex) {
                            const questions = document.querySelectorAll(\'.question-card\');
                            questions.forEach(q => q.style.display = \'none\');
                            
                            const startIndex = pageIndex * questionsPerPage;
                            const endIndex = Math.min(startIndex + questionsPerPage, totalQuestions);
                            
                            for (let i = startIndex; i < endIndex; i++) {
                                if (questions[i]) {
                                    questions[i].style.display = \'block\';
                                }
                            }
                            
                            // Update page counter
                            document.querySelector(\'.current-question\').textContent = \'Page \' + (pageIndex + 1) + \' sur \' + totalPages;
                            
                            // Update progress bar
                            const progress = ((pageIndex + 1) / totalPages) * 100;
                            document.querySelector(\'.progress-bar\').style.width = progress + \'%\';
                        }
                        
                        function showQuestion(index) {
                            if (questionsPerPage === 1) {
                                // Single question mode
                                const questions = document.querySelectorAll(\'.question-card\');
                                questions.forEach(q => q.style.display = \'none\');
                                if (questions[index]) {
                                    questions[index].style.display = \'block\';
                                    document.querySelector(\'.current-question\').textContent = index + 1;
                                    const progress = ((index + 1) / totalQuestions) * 100;
                                    document.querySelector(\'.progress-bar\').style.width = progress + \'%\';
                                }
                            } else {
                                // Multi-question per page mode
                                const pageIndex = Math.floor(index / questionsPerPage);
                                currentPage = pageIndex;
                                showPage(pageIndex);
                            }
                        }
                        
                        // Enable all inputs
                        document.querySelectorAll(\'.question-card input, .question-card textarea\').forEach(input => {
                            input.disabled = false;
                        });
                        
                        // Setup navigation buttons
                        document.querySelectorAll(\'.prev-btn\').forEach(btn => {
                            btn.disabled = false;
                            btn.onclick = () => {
                                if (questionsPerPage === 1) {
                                    if (currentQuestionIndex > 0) {
                                        currentQuestionIndex--;
                                        showQuestion(currentQuestionIndex);
                                    }
                                } else {
                                    if (currentPage > 0) {
                                        currentPage--;
                                        showPage(currentPage);
                                    }
                                }
                            };
                        });
                        
                        document.querySelectorAll(\'.next-btn\').forEach(btn => {
                            btn.disabled = false;
                            btn.onclick = () => {
                                if (questionsPerPage === 1) {
                                    if (currentQuestionIndex < totalQuestions - 1) {
                                        currentQuestionIndex++;
                                        showQuestion(currentQuestionIndex);
                                    }
                                } else {
                                    if (currentPage < totalPages - 1) {
                                        currentPage++;
                                        showPage(currentPage);
                                    }
                                }
                            };
                        });
                        
                        document.querySelectorAll(\'.submit-btn\').forEach(btn => {
                            btn.disabled = false;
                            btn.onclick = () => {
                                finishQuiz();
                            };
                        });
                        
                        function finishQuiz() {
                            if (timerInterval) {
                                clearInterval(timerInterval);
                            }
                            
                            document.querySelector(\'.quiz-questions-container\').style.display = \'none\';
                            if (document.querySelector(\'.quiz-timer\')) {
                                document.querySelector(\'.quiz-timer\').style.display = \'none\';
                            }
                            
                            const resultsDiv = document.querySelector(\'.quiz-results-preview\');
                            resultsDiv.style.display = \'block\';
                            const randomScore = Math.floor(Math.random() * totalQuestions) + 1;
                            resultsDiv.querySelector(\'strong\').textContent = randomScore + \' / \' + totalQuestions;
                            resultsDiv.querySelector(\'button\').onclick = () => location.reload();
                        }
                        
                        // Start the quiz
                        if (questionsPerPage === 1) {
                            showQuestion(0);
                        } else {
                            showPage(0);
                        }
                        console.log(\'Quiz preview started with \' + questionsPerPage + \' questions per page\');
                    ">🚀 Commencer le Quiz</button>
                </div>

                <!-- Quiz Questions Container -->
                <div class="quiz-questions-container" style="display: none;">
                    <div class="question-counter" style="text-align: center; margin-bottom: 20px;">
                        <span style="background: white; color: #6c757d; padding: 10px 20px; border-radius: 25px; font-weight: 600; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                            <span class="current-question">1</span> sur ' . count($questions) . '
                        </span>
                    </div>';

            // Generate all questions
            foreach ($questions as $index => $question) {
                $question_text = $question['question'] ?? $question['question_text'] ?? 'Question sans titre';
                $question_type = $question['type'] ?? $question['question_type'] ?? 'qcm';
                $answers = $question['answers'] ?? [];

                $preview_html .= '
                    <div class="question-card" data-question="' . ($index + 1) . '" style="background: white; border-radius: 16px; padding: 35px; margin-bottom: 25px; box-shadow: 0 4px 25px rgba(0,0,0,0.08); border: 2px solid #f8f9fa; transition: all 0.3s ease; ' . ($index > 0 ? 'display: none;' : '') . '">
                        
                        <div class="question-header" style="margin-bottom: 25px;">
                            <h2 style="color: #2c3e50; font-size: 24px; font-weight: 600; margin: 0; line-height: 1.4;">' . esc_html($question_text) . '</h2>
                        </div>

                        <div class="question-answers" style="margin-bottom: 30px;">';

                if ($question_type === 'qcm' && !empty($answers)) {
                    foreach ($answers as $answer_index => $answer) {
                        $answer_text = is_array($answer) ? ($answer['text'] ?? '') : $answer;
                        if (!empty($answer_text)) {
                            $preview_html .= '
                                <label class="answer-option" style="display: block; background: #f8f9fa; margin: 12px 0; padding: 18px 24px; border-radius: 12px; cursor: pointer; transition: all 0.3s ease; border: 2px solid transparent; position: relative; overflow: hidden;" 
                                       onmouseover="this.style.background=\'#e9ecef\'; this.style.transform=\'translateX(5px)\';" 
                                       onmouseout="this.style.background=\'#f8f9fa\'; this.style.transform=\'translateX(0)\';">
                                    <input type="radio" name="question_' . ($index + 1) . '" value="' . $answer_index . '" style="margin-right: 15px; transform: scale(1.2);" disabled>
                                    <span style="font-size: 16px; line-height: 1.5; color: #495057;">' . esc_html($answer_text) . '</span>
                                </label>';
                        }
                    }
                } elseif ($question_type === 'text') {
                    $preview_html .= '
                        <textarea placeholder="Écrivez votre réponse ici..." style="width: 100%; min-height: 120px; padding: 18px; border: 2px solid #e9ecef; border-radius: 12px; font-size: 16px; font-family: inherit; resize: vertical; transition: border-color 0.3s ease;" disabled></textarea>';
                } elseif ($question_type === 'true_false') {
                    $preview_html .= '
                        <label class="answer-option" style="display: block; background: #f8f9fa; margin: 12px 0; padding: 18px 24px; border-radius: 12px; cursor: pointer; transition: all 0.3s ease;">
                            <input type="radio" name="question_' . ($index + 1) . '" value="true" style="margin-right: 15px; transform: scale(1.2);" disabled>
                            <span style="font-size: 16px;">✅ Vrai</span>
                        </label>
                        <label class="answer-option" style="display: block; background: #f8f9fa; margin: 12px 0; padding: 18px 24px; border-radius: 12px; cursor: pointer; transition: all 0.3s ease;">
                            <input type="radio" name="question_' . ($index + 1) . '" value="false" style="margin-right: 15px; transform: scale(1.2);" disabled>
                            <span style="font-size: 16px;">❌ Faux</span>
                        </label>';
                }

                $preview_html .= '
                        </div>

                        <div class="question-navigation" style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px;">';

                // For pagination mode, show navigation on every question but let JavaScript handle the logic
                // For single question mode, show based on question index
                if ($questions_per_page > 1 || $index > 0) {
                    $preview_html .= '<button class="prev-btn" style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;" disabled>← Précédent</button>';
                } else {
                    $preview_html .= '<div></div>';
                }

                if ($questions_per_page > 1 || $index < count($questions) - 1) {
                    $preview_html .= '<button class="next-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;" disabled>Suivant →</button>';
                } else {
                    $preview_html .= '<button class="submit-btn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 12px 30px; border-radius: 8px; cursor: pointer; font-weight: 600;" disabled>🎯 Terminer le Quiz</button>';
                }

                $preview_html .= '
                        </div>
                    </div>';
            }

            $preview_html .= '
                </div>

                <!-- Results Preview -->
                <div class="quiz-results-preview" style="display: none; background: white; border-radius: 16px; padding: 40px; text-align: center; box-shadow: 0 4px 25px rgba(0,0,0,0.08);">
                    <div class="success-icon" style="font-size: 64px; margin-bottom: 20px;">🎉</div>
                    <h2 style="color: #28a745; margin: 0 0 15px 0; font-size: 28px;">Quiz Terminé !</h2>
                    <p style="color: #6c757d; font-size: 18px; margin-bottom: 30px;">Votre score : <strong style="color: #2c3e50;">-- / ' . count($questions) . '</strong></p>
                    <button style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 15px 30px; border-radius: 8px; font-weight: 600; cursor: pointer;" disabled>📊 Voir les Résultats Détaillés</button>
                </div>

                <!-- Preview Notice -->
                <div class="preview-notice" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); border-radius: 12px; padding: 20px; margin-top: 30px; text-align: center; border-left: 4px solid #f39c12;">
                    <p style="margin: 0; color: #8b4513; font-weight: 600; font-size: 16px;">
                        👁️ <strong>Mode Aperçu</strong> - Ceci montre comment le quiz apparaîtra aux utilisateurs. Cliquez sur "Commencer le Quiz" pour voir le déroulement interactif !
                    </p>
                </div>
            </div>';

            // Add CSS styles
            $preview_html .= '
            <style>
                .quiz-container-preview * {
                    box-sizing: border-box;
                }
                
                .answer-option:hover input[type="radio"] {
                    transform: scale(1.3);
                }
                
                .question-card {
                    animation: slideInUp 0.5s ease-out;
                }
                
                @keyframes slideInUp {
                    from {
                        opacity: 0;
                        transform: translateY(30px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                @media (max-width: 768px) {
                    .quiz-container-preview {
                        padding: 10px !important;
                    }
                    
                    .quiz-header {
                        padding: 25px 20px !important;
                    }
                    
                    .question-card {
                        padding: 25px 20px !important;
                    }
                    
                    .quiz-info-badges {
                        flex-direction: column;
                        align-items: center;
                    }
                }
            </style>';

            wp_send_json_success([
                'html' => $preview_html,
                'title' => $quiz->title
            ]);
        } catch (Exception $e) {
            error_log('Preview error: ' . $e->getMessage());
            wp_send_json_error('Erreur: ' . $e->getMessage());
        }
    }

    /**
     * Handle test AJAX request
     */
    public function handle_test_ajax()
    {
        wp_send_json_success('AJAX is working!');
    }

    /**
     * Handle publish quiz action
     */
    public function handle_publish_quiz()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        $quiz_id = intval($_POST['quiz_id']);
        if (!$quiz_id) {
            wp_send_json_error('Invalid quiz ID');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'quiz_ia_quizzes';

        $result = $wpdb->update(
            $table_name,
            ['status' => 'published'],
            ['id' => $quiz_id],
            ['%s'],
            ['%d']
        );

        if ($result !== false) {
            // Get quiz data for email notification
            $quiz_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $quiz_id
            ), ARRAY_A);

            if ($quiz_data) {
                // Trigger email notification hook
                do_action('quiz_ia_pro_quiz_published', $quiz_data);
                error_log('Quiz IA Pro: Triggered email notification for published quiz ID: ' . $quiz_id);
            }

            wp_send_json_success('Quiz publié avec succès');
        } else {
            wp_send_json_error('Erreur lors de la publication du quiz');
        }
    }

    /**
     * Handle unpublish quiz action
     */
    public function handle_unpublish_quiz()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        $quiz_id = intval($_POST['quiz_id']);
        if (!$quiz_id) {
            wp_send_json_error('Invalid quiz ID');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'quiz_ia_quizzes';

        $result = $wpdb->update(
            $table_name,
            ['status' => 'draft'],
            ['id' => $quiz_id],
            ['%s'],
            ['%d']
        );

        if ($result !== false) {
            wp_send_json_success('Quiz dépublié avec succès');
        } else {
            wp_send_json_error('Erreur lors de la dépublication du quiz');
        }
    }

    /**
     * Handle question type change
     */
    public function handle_change_question_type()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_change_question_type')) {
            wp_send_json_error('Security check failed');
        }

        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        $question_id = intval($_POST['question_id']);
        $new_type = sanitize_text_field($_POST['new_type']);
        $old_type = sanitize_text_field($_POST['old_type']);

        if (!$question_id || !$new_type) {
            wp_send_json_error('Invalid parameters');
        }

        global $wpdb;
        $questions_table = $wpdb->prefix . 'quiz_ia_questions';
        $answers_table = $wpdb->prefix . 'quiz_ia_answers';

        error_log("Quiz IA Pro: Changing question type from '$old_type' to '$new_type' for question ID $question_id");

        try {
            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Update question type
            $result = $wpdb->update(
                $questions_table,
                ['question_type' => $new_type],
                ['id' => $question_id],
                ['%s'],
                ['%d']
            );

            if ($result === false) {
                throw new Exception('Failed to update question type');
            }

            // Handle answer conversion based on question types
            $new_answers = $this->convert_question_answers($question_id, $old_type, $new_type);

            $wpdb->query('COMMIT');

            error_log("Quiz IA Pro: Successfully changed question type to '$new_type'");

            wp_send_json_success([
                'message' => "Type de question changé avec succès",
                'new_type' => $new_type,
                'answers' => $new_answers
            ]);
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log("Quiz IA Pro: Error changing question type: " . $e->getMessage());
            wp_send_json_error('Erreur lors du changement de type: ' . $e->getMessage());
        }
    }

    /**
     * Convert question answers when changing question type
     */
    private function convert_question_answers($question_id, $old_type, $new_type)
    {
        global $wpdb;
        $answers_table = $wpdb->prefix . 'quiz_ia_answers';

        // Get existing answers
        $existing_answers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $answers_table WHERE question_id = %d ORDER BY answer_order ASC",
            $question_id
        ));

        error_log("Quiz IA Pro: Converting $old_type to $new_type - found " . count($existing_answers) . " existing answers");

        $new_answers = [];

        switch ($new_type) {
            case 'true-false':
                // Clear existing answers and create True/False
                $wpdb->delete($answers_table, ['question_id' => $question_id], ['%d']);

                $true_false_answers = [
                    ['text' => 'Vrai', 'is_correct' => false],
                    ['text' => 'Faux', 'is_correct' => true] // Default to False being correct
                ];

                // If we had existing answers, try to preserve correctness
                if (!empty($existing_answers)) {
                    $had_correct = false;
                    foreach ($existing_answers as $answer) {
                        if ($answer->is_correct) {
                            $had_correct = true;
                            break;
                        }
                    }
                    if (!$had_correct) {
                        // If no correct answer was set, default to True
                        $true_false_answers[0]['is_correct'] = true;
                        $true_false_answers[1]['is_correct'] = false;
                    }
                }

                foreach ($true_false_answers as $index => $answer_data) {
                    $insert_result = $wpdb->insert(
                        $answers_table,
                        [
                            'question_id' => $question_id,
                            'answer_text' => $answer_data['text'],
                            'is_correct' => $answer_data['is_correct'] ? 1 : 0,
                            'answer_order' => $index + 1
                        ],
                        ['%d', '%s', '%d', '%d']
                    );

                    if ($insert_result) {
                        $new_answers[] = [
                            'id' => $wpdb->insert_id,
                            'answer_text' => $answer_data['text'],
                            'is_correct' => $answer_data['is_correct']
                        ];
                    }
                }
                break;

            case 'qcm':
            case 'multiple-choice':
            case 'single-choice':
                // Keep existing answers but adjust correctness based on type
                if (empty($existing_answers)) {
                    // Create default answers if none exist
                    $default_answers = ['Réponse A', 'Réponse B'];
                    foreach ($default_answers as $index => $answer_text) {
                        $insert_result = $wpdb->insert(
                            $answers_table,
                            [
                                'question_id' => $question_id,
                                'answer_text' => $answer_text,
                                'is_correct' => 0,
                                'answer_order' => $index + 1
                            ],
                            ['%d', '%s', '%d', '%d']
                        );

                        if ($insert_result) {
                            $new_answers[] = [
                                'id' => $wpdb->insert_id,
                                'answer_text' => $answer_text,
                                'is_correct' => false
                            ];
                        }
                    }
                } else {
                    // For single choice, ensure only one answer is correct
                    if ($new_type === 'single-choice') {
                        $correct_count = 0;
                        foreach ($existing_answers as $answer) {
                            if ($answer->is_correct) {
                                $correct_count++;
                            }
                        }

                        // If more than one correct answer, keep only the first one
                        if ($correct_count > 1) {
                            $first_correct_found = false;
                            foreach ($existing_answers as $answer) {
                                if ($answer->is_correct && !$first_correct_found) {
                                    $first_correct_found = true;
                                    continue; // Keep this one correct
                                } elseif ($answer->is_correct && $first_correct_found) {
                                    // Make this one incorrect
                                    $wpdb->update(
                                        $answers_table,
                                        ['is_correct' => 0],
                                        ['id' => $answer->id],
                                        ['%d'],
                                        ['%d']
                                    );
                                }
                            }
                        }
                    }

                    // Return existing answers (potentially modified)
                    foreach ($existing_answers as $answer) {
                        $new_answers[] = [
                            'id' => $answer->id,
                            'answer_text' => $answer->answer_text,
                            'is_correct' => ($new_type === 'single-choice' && $answer->is_correct) ? true : (bool)$answer->is_correct
                        ];
                    }
                }
                break;

            case 'fill_blank':
            case 'text_a_completer':
                // Convert existing answers to fill-in-the-blank format
                if (empty($existing_answers)) {
                    // Create a default blank answer
                    $insert_result = $wpdb->insert(
                        $answers_table,
                        [
                            'question_id' => $question_id,
                            'answer_text' => '',
                            'is_correct' => 1,
                            'answer_order' => 1
                        ],
                        ['%d', '%s', '%d', '%d']
                    );

                    if ($insert_result) {
                        $new_answers[] = [
                            'id' => $wpdb->insert_id,
                            'answer_text' => '',
                            'is_correct' => true
                        ];
                    }
                } else {
                    // Keep existing answers as potential blanks
                    foreach ($existing_answers as $answer) {
                        $new_answers[] = [
                            'id' => $answer->id,
                            'answer_text' => $answer->answer_text,
                            'is_correct' => true // All blanks are "correct"
                        ];
                    }
                }
                break;

            case 'text':
            case 'essay':
                // Convert to free text - keep first answer as acceptable answer
                if (!empty($existing_answers)) {
                    $first_answer = $existing_answers[0];

                    // Update all others to be acceptable answers
                    foreach ($existing_answers as $answer) {
                        $wpdb->update(
                            $answers_table,
                            ['is_correct' => 1],
                            ['id' => $answer->id],
                            ['%d'],
                            ['%d']
                        );

                        $new_answers[] = [
                            'id' => $answer->id,
                            'answer_text' => $answer->answer_text,
                            'is_correct' => true
                        ];
                    }
                } else {
                    // Create default acceptable answer
                    $insert_result = $wpdb->insert(
                        $answers_table,
                        [
                            'question_id' => $question_id,
                            'answer_text' => '',
                            'is_correct' => 1,
                            'answer_order' => 1
                        ],
                        ['%d', '%s', '%d', '%d']
                    );

                    if ($insert_result) {
                        $new_answers[] = [
                            'id' => $wpdb->insert_id,
                            'answer_text' => '',
                            'is_correct' => true
                        ];
                    }
                }
                break;

            default:
                // For unknown types, keep existing answers as-is
                foreach ($existing_answers as $answer) {
                    $new_answers[] = [
                        'id' => $answer->id,
                        'answer_text' => $answer->answer_text,
                        'is_correct' => (bool)$answer->is_correct
                    ];
                }
                break;
        }

        error_log("Quiz IA Pro: Created " . count($new_answers) . " answers for new type '$new_type'");
        return $new_answers;
    }

    /**
     * Handle save all changes
     */
    public function handle_save_all_changes()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_save_all')) {
            wp_send_json_error('Nonce verification failed');
            return;
        }

        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $quiz_data = json_decode(stripslashes($_POST['quiz_data']), true);
        if (!$quiz_data || !isset($quiz_data['quiz_id'])) {
            wp_send_json_error('Invalid quiz data');
            return;
        }

        global $wpdb;
        $quiz_id = intval($quiz_data['quiz_id']);

        try {
            $wpdb->query('START TRANSACTION');

            // Update quiz information
            $quiz_update_data = [
                'title' => sanitize_text_field($quiz_data['title']),
                'description' => sanitize_textarea_field($quiz_data['description']),
                'updated_at' => current_time('mysql')
            ];

            // Update featured image if provided
            if (isset($quiz_data['featured_image'])) {
                $quiz_update_data['featured_image'] = sanitize_url($quiz_data['featured_image']);
            }

            // Update time_limit and questions_per_page if provided
            if (isset($quiz_data['time_limit'])) {
                $quiz_update_data['time_limit'] = intval($quiz_data['time_limit']);
            }
            if (isset($quiz_data['questions_per_page'])) {
                $quiz_update_data['questions_per_page'] = intval($quiz_data['questions_per_page']);
            }

            $quiz_update_result = $wpdb->update(
                $wpdb->prefix . 'quiz_ia_quizzes',
                $quiz_update_data,
                ['id' => $quiz_id],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );

            if ($quiz_update_result === false) {
                throw new Exception('Failed to update quiz information');
            }

            error_log("Quiz AI Pro: Updated quiz info for quiz ID: $quiz_id");

            // Update questions
            if (isset($quiz_data['questions']) && is_array($quiz_data['questions'])) {
                foreach ($quiz_data['questions'] as $question_data) {
                    $question_id = intval($question_data['id']);
                    $is_new_question = (isset($question_data['is_new']) && $question_data['is_new'] === true) || $question_id === 0;

                    // Prepare question update data
                    $question_update_data = [
                        'question_text' => sanitize_text_field($question_data['question_text']),
                        'explanation' => sanitize_textarea_field($question_data['explanation'] ?? ''),
                        'points' => intval($question_data['points'] ?? 1),
                        'sort_order' => intval($question_data['question_order'] ?? 1)
                    ];

                    // Add required fields for new questions
                    if ($is_new_question) {
                        $question_update_data['quiz_id'] = $quiz_id;
                        $question_update_data['question_type'] = sanitize_text_field($question_data['question_type'] ?? 'qcm');
                        $question_update_data['created_at'] = current_time('mysql');
                    }

                    // Update featured image if provided
                    if (isset($question_data['featured_image'])) {
                        $question_update_data['featured_image'] = sanitize_url($question_data['featured_image']);
                    }

                    if ($is_new_question) {
                        // Insert new question
                        $question_result = $wpdb->insert(
                            $wpdb->prefix . 'quiz_ia_questions',
                            $question_update_data,
                            ['%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s']
                        );

                        if ($question_result === false) {
                            throw new Exception("Failed to insert new question");
                        }

                        $question_id = $wpdb->insert_id;
                        error_log("Quiz AI Pro: Inserted new question ID: $question_id");
                    } else {
                        // Update existing question
                        $question_update_result = $wpdb->update(
                            $wpdb->prefix . 'quiz_ia_questions',
                            $question_update_data,
                            ['id' => $question_id],
                            ['%s', '%s', '%d', '%d', '%s'],
                            ['%d']
                        );

                        if ($question_update_result === false) {
                            throw new Exception("Failed to update question ID: $question_id");
                        }

                        error_log("Quiz AI Pro: Updated question ID: $question_id");
                    }

                    // Update answers
                    if (isset($question_data['answers']) && is_array($question_data['answers'])) {
                        // Get current answers from database
                        $current_answer_ids = $wpdb->get_col($wpdb->prepare(
                            "SELECT id FROM {$wpdb->prefix}quiz_ia_answers WHERE question_id = %d",
                            $question_id
                        ));

                        $submitted_answer_ids = [];

                        foreach ($question_data['answers'] as $answer_data) {
                            $answer_id = intval($answer_data['id']);
                            $is_new_answer = (isset($answer_data['is_new']) && $answer_data['is_new'] === true) || $answer_id === 0;

                            if ($is_new_answer) {
                                // Handle new answers by inserting them
                                $new_answer_result = $wpdb->insert(
                                    $wpdb->prefix . 'quiz_ia_answers',
                                    [
                                        'question_id' => $question_id,
                                        'answer_text' => sanitize_text_field($answer_data['answer_text']),
                                        'is_correct' => intval($answer_data['is_correct']),
                                        'sort_order' => intval($answer_data['sort_order']),
                                        'created_at' => current_time('mysql')
                                    ],
                                    ['%d', '%s', '%d', '%d', '%s']
                                );

                                if ($new_answer_result === false) {
                                    error_log("Quiz AI Pro: Warning - Failed to insert new answer for question ID: $question_id");
                                } else {
                                    error_log("Quiz AI Pro: Inserted new answer ID: " . $wpdb->insert_id . " for question ID: $question_id");
                                }
                                continue;
                            }

                            // Track submitted answer IDs
                            $submitted_answer_ids[] = $answer_id;

                            // Update existing answers
                            $answer_update_result = $wpdb->update(
                                $wpdb->prefix . 'quiz_ia_answers',
                                [
                                    'answer_text' => sanitize_text_field($answer_data['answer_text']),
                                    'is_correct' => intval($answer_data['is_correct']),
                                    'sort_order' => intval($answer_data['sort_order'])
                                ],
                                ['id' => $answer_id],
                                ['%s', '%d', '%d'],
                                ['%d']
                            );

                            if ($answer_update_result === false) {
                                error_log("Quiz AI Pro: Warning - Failed to update answer ID: $answer_id");
                            } else {
                                error_log("Quiz AI Pro: Updated answer ID: $answer_id");
                            }
                        }

                        // Delete answers that were removed from the frontend
                        $answers_to_delete = array_diff($current_answer_ids, $submitted_answer_ids);
                        if (!empty($answers_to_delete)) {
                            $answer_ids_placeholder = implode(',', array_fill(0, count($answers_to_delete), '%d'));
                            $delete_result = $wpdb->query($wpdb->prepare(
                                "DELETE FROM {$wpdb->prefix}quiz_ia_answers WHERE id IN ($answer_ids_placeholder)",
                                ...$answers_to_delete
                            ));

                            if ($delete_result !== false) {
                                error_log("Quiz AI Pro: Deleted " . count($answers_to_delete) . " answers for question ID: $question_id");
                            } else {
                                error_log("Quiz AI Pro: Warning - Failed to delete answers for question ID: $question_id");
                            }
                        }
                    }
                }
            }
            $wpdb->query('COMMIT');

            error_log("Quiz AI Pro: Successfully saved all changes for quiz ID: $quiz_id");
            wp_send_json_success('Toutes les modifications ont été sauvegardées avec succès');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log("Quiz AI Pro: Error saving all changes - " . $e->getMessage());
            wp_send_json_error('Erreur lors de la sauvegarde: ' . $e->getMessage());
        }
    }

    /**
     * Handle image upload using WordPress Media Library
     */
    public function handle_upload_image()
    {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_nonce') || !current_user_can('upload_files')) {
            wp_send_json_error('Permission refusée');
        }

        // Check if file was uploaded
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Aucun fichier téléchargé ou erreur de téléchargement');
        }

        // Validate file type and size
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($_FILES['file']['type'], $allowed_types)) {
            wp_send_json_error('Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WebP.');
        }

        if ($_FILES['file']['size'] > 5 * 1024 * 1024) { // 5MB limit
            wp_send_json_error('Fichier trop volumineux. Taille maximale: 5MB');
        }

        // Handle the upload using WordPress functions
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        $upload_overrides = array(
            'test_form' => false,
            'unique_filename_callback' => function ($dir, $name, $ext) {
                return 'quiz-' . uniqid() . $ext;
            }
        );

        $uploaded_file = wp_handle_upload($_FILES['file'], $upload_overrides);

        if (isset($uploaded_file['error'])) {
            wp_send_json_error($uploaded_file['error']);
        }

        // Create attachment post
        $attachment = array(
            'post_mime_type' => $uploaded_file['type'],
            'post_title' => sanitize_file_name(pathinfo($uploaded_file['file'], PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file']);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error('Erreur lors de la création de l\'attachment');
        }

        // Generate metadata and thumbnails
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        wp_send_json_success(array(
            'url' => $uploaded_file['url'],
            'attachment_id' => $attachment_id,
            'file' => $uploaded_file['file']
        ));
    }

    /**
     * Handle updating quiz settings (time_limit, questions_per_page)
     */
    public function handle_update_quiz_settings()
    {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_nonce') || !current_user_can('edit_posts')) {
            wp_send_json_error('Permission refusée');
        }

        $quiz_id = intval($_POST['quiz_id']);
        $setting_name = sanitize_text_field($_POST['setting_name']);
        $setting_value = sanitize_text_field($_POST['setting_value']);

        if (!$quiz_id || !$setting_name) {
            wp_send_json_error('Données manquantes');
        }

        // Validate setting name and value
        $allowed_settings = ['time_limit', 'questions_per_page'];
        if (!in_array($setting_name, $allowed_settings)) {
            wp_send_json_error('Paramètre non autorisé');
        }

        // Validate value
        $value = intval($setting_value);
        if ($setting_name === 'time_limit' && $value < 0) {
            wp_send_json_error('La limite de temps ne peut pas être négative');
        }
        if ($setting_name === 'questions_per_page' && $value < 1) {
            wp_send_json_error('Le nombre de questions par page doit être d\'au moins 1');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'quiz_ia_quizzes';

        $updated = $wpdb->update(
            $table_name,
            [$setting_name => $value, 'updated_at' => current_time('mysql')],
            ['id' => $quiz_id],
            ['%d', '%s'],
            ['%d']
        );

        if ($updated !== false) {
            wp_send_json_success([
                'message' => 'Paramètre mis à jour avec succès',
                'setting_name' => $setting_name,
                'setting_value' => $value
            ]);
        } else {
            wp_send_json_error('Erreur lors de la mise à jour');
        }
    }

    /**
     * Handle adding a category to quiz
     */
    public function handle_add_category()
    {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_nonce') || !current_user_can('edit_posts')) {
            wp_send_json_error('Permission refusée');
        }

        $quiz_id = intval($_POST['quiz_id']);
        $category_id = intval($_POST['category_id']);

        if (!$quiz_id || !$category_id) {
            wp_send_json_error('Données manquantes');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'quiz_ia_quizzes';

        // Get current categories
        $quiz = $wpdb->get_row($wpdb->prepare(
            "SELECT category_id FROM $table_name WHERE id = %d",
            $quiz_id
        ));

        if (!$quiz) {
            wp_send_json_error('Quiz non trouvé');
        }

        $current_categories = $quiz->category_id ? json_decode($quiz->category_id, true) : [];

        // Check if category is already added
        if (in_array($category_id, $current_categories)) {
            wp_send_json_error('Cette catégorie est déjà associée au quiz');
        }

        // Add new category
        $current_categories[] = $category_id;

        $updated = $wpdb->update(
            $table_name,
            ['category_id' => wp_json_encode($current_categories), 'updated_at' => current_time('mysql')],
            ['id' => $quiz_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated !== false) {
            // Get category name for response
            $category = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM {$wpdb->terms} WHERE term_id = %d",
                $category_id
            ));

            wp_send_json_success([
                'message' => 'Catégorie ajoutée avec succès',
                'category_id' => $category_id,
                'category_name' => $category ? $category->name : 'Catégorie inconnue'
            ]);
        } else {
            wp_send_json_error('Erreur lors de l\'ajout de la catégorie');
        }
    }

    /**
     * Handle removing a category from quiz
     */
    public function handle_remove_category()
    {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_nonce') || !current_user_can('edit_posts')) {
            wp_send_json_error('Permission refusée');
        }

        $quiz_id = intval($_POST['quiz_id']);
        $category_id = intval($_POST['category_id']);

        if (!$quiz_id || !$category_id) {
            wp_send_json_error('Données manquantes');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'quiz_ia_quizzes';

        // Get current categories
        $quiz = $wpdb->get_row($wpdb->prepare(
            "SELECT category_id FROM $table_name WHERE id = %d",
            $quiz_id
        ));

        if (!$quiz) {
            wp_send_json_error('Quiz non trouvé');
        }

        $current_categories = $quiz->category_id ? json_decode($quiz->category_id, true) : [];

        // Remove category
        $updated_categories = array_diff($current_categories, [$category_id]);

        $updated = $wpdb->update(
            $table_name,
            ['category_id' => wp_json_encode(array_values($updated_categories)), 'updated_at' => current_time('mysql')],
            ['id' => $quiz_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated !== false) {
            wp_send_json_success([
                'message' => 'Catégorie supprimée avec succès',
                'category_id' => $category_id
            ]);
        } else {
            wp_send_json_error('Erreur lors de la suppression de la catégorie');
        }
    }

    /**
     * Handle quiz comment submission
     */
    public function handle_submit_quiz_comment()
    {
        try {
            // Rate limiting for comment submissions
            if (!QuizAIProSecurity::check_rate_limit('submit_comment', 3, 300)) {
                return;
            }

            // Security checks
            if (!QuizAIProSecurity::verify_nonce_and_permission('submit_quiz_comment')) {
                return;
            }

            // Validate required data
            $required_fields = ['quiz_id', 'comment_text'];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    wp_send_json_error("Champ manquant: $field");
                    return;
                }
            }

            // Sanitize and validate inputs
            $quiz_id = absint($_POST['quiz_id']);
            $comment_text = sanitize_textarea_field($_POST['comment_text']);
            $rating = isset($_POST['rating']) ? absint($_POST['rating']) : null;

            // Validate comment length
            if (strlen($comment_text) < 10) {
                wp_send_json_error('Le commentaire doit contenir au moins 10 caractères.');
                return;
            }

            if (strlen($comment_text) > 500) {
                wp_send_json_error('Le commentaire ne peut pas dépasser 500 caractères.');
                return;
            }

            // Validate rating if provided
            if ($rating !== null && ($rating < 1 || $rating > 5)) {
                wp_send_json_error('La note doit être entre 1 et 5.');
                return;
            }

            // Check if quiz exists
            global $wpdb;
            $quiz_table = $wpdb->prefix . 'quiz_ia_quizzes';
            $quiz_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$quiz_table} WHERE id = %d AND status = 'published'",
                $quiz_id
            ));

            if (!$quiz_exists) {
                wp_send_json_error('Quiz non trouvé.');
                return;
            }

            // Get user information
            $user_id = get_current_user_id();
            $user_name = '';
            $user_email = '';

            if ($user_id > 0) {
                // Logged in user
                $user_info = get_userdata($user_id);
                $user_name = $user_info->display_name;
                $user_email = $user_info->user_email;
            } else {
                // Anonymous user - require name and email
                if (empty($_POST['user_name']) || empty($_POST['user_email'])) {
                    wp_send_json_error('Nom et email requis pour les utilisateurs anonymes.');
                    return;
                }
                $user_name = sanitize_text_field($_POST['user_name']);
                $user_email = sanitize_email($_POST['user_email']);

                if (!is_email($user_email)) {
                    wp_send_json_error('Adresse email invalide.');
                    return;
                }
            }

            // Insert comment
            $comments_table = $wpdb->prefix . 'quiz_ia_comments';
            $result = $wpdb->insert(
                $comments_table,
                [
                    'quiz_id' => $quiz_id,
                    'user_id' => $user_id,
                    'user_name' => $user_name,
                    'user_email' => $user_email,
                    'comment_text' => $comment_text,
                    'rating' => $rating,
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'is_approved' => 1, // Auto-approve for now
                    'created_at' => current_time('mysql')
                ],
                [
                    '%d', // quiz_id
                    '%d', // user_id
                    '%s', // user_name
                    '%s', // user_email
                    '%s', // comment_text
                    '%d', // rating
                    '%s', // ip_address
                    '%s', // user_agent
                    '%d', // is_approved
                    '%s'  // created_at
                ]
            );

            if ($result === false) {
                QuizAIProSecurity::log_security_event('comment_submission_error', [
                    'quiz_id' => $quiz_id,
                    'error' => $wpdb->last_error
                ]);
                wp_send_json_error('Erreur lors de l\'enregistrement du commentaire.');
                return;
            }

            // Log successful comment submission
            QuizAIProSecurity::log_security_event('comment_submitted_successfully', [
                'quiz_id' => $quiz_id,
                'comment_id' => $wpdb->insert_id,
                'user_id' => $user_id
            ]);

            wp_send_json_success([
                'message' => 'Commentaire enregistré avec succès!',
                'comment_id' => $wpdb->insert_id
            ]);
        } catch (Exception $e) {
            QuizAIProSecurity::log_security_event('comment_submission_error', [
                'error' => $e->getMessage()
            ]);
            wp_send_json_error('Erreur lors de l\'enregistrement du commentaire.');
        }
    }

    /**
     * Handle getting quiz comments
     */
    public function handle_get_quiz_comments()
    {
        try {
            // Rate limiting
            if (!QuizAIProSecurity::check_rate_limit('get_comments', 20, 60)) {
                return;
            }

            // Security checks
            if (!QuizAIProSecurity::verify_nonce_and_permission($_POST['nonce'], 'quiz_ia_frontend_nonce')) {
                return;
            }

            $quiz_id = absint($_POST['quiz_id']);

            if (!$quiz_id) {
                wp_send_json_error('ID de quiz manquant.');
                return;
            }

            global $wpdb;
            $comments_table = $wpdb->prefix . 'quiz_ia_comments';

            // Get approved comments for the quiz
            $comments = $wpdb->get_results($wpdb->prepare(
                "SELECT id, user_name, comment_text, rating, created_at 
                 FROM {$comments_table} 
                 WHERE quiz_id = %d AND is_approved = 1 
                 ORDER BY created_at DESC 
                 LIMIT 50",
                $quiz_id
            ));

            if ($comments === false) {
                wp_send_json_error('Erreur lors du chargement des commentaires.');
                return;
            }

            // Format comments for display
            $formatted_comments = [];
            foreach ($comments as $comment) {
                $formatted_comments[] = [
                    'id' => $comment->id,
                    'user_name' => esc_html($comment->user_name),
                    'comment_text' => esc_html($comment->comment_text),
                    'rating' => $comment->rating,
                    'created_at' => date('d/m/Y H:i', strtotime($comment->created_at))
                ];
            }

            wp_send_json_success([
                'comments' => $formatted_comments,
                'count' => count($formatted_comments)
            ]);
        } catch (Exception $e) {
            QuizAIProSecurity::log_security_event('get_comments_error', [
                'error' => $e->getMessage()
            ]);
            wp_send_json_error('Erreur lors du chargement des commentaires.');
        }
    }

    /**
     * Handle forced table updates
     */
    public function handle_force_update_tables()
    {
        try {
            // Security check - only admins can do this
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            // Force update tables
            $result = quiz_ai_pro_force_update_tables();

            wp_send_json_success([
                'message' => 'Table update completed',
                'details' => $result
            ]);
        } catch (Exception $e) {
            wp_send_json_error('Error updating tables: ' . $e->getMessage());
        }
    }

    /**
     * Handle stats filtering
     */
    public function handle_filter_stats()
    {
        try {
            // Security check
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            check_ajax_referer('quiz_ai_admin_nonce', 'nonce');

            $period = sanitize_text_field($_POST['period'] ?? '30days');
            $quiz_id = intval($_POST['quiz_id'] ?? 0);
            $search = sanitize_text_field($_POST['search'] ?? '');
            $page = intval($_POST['page'] ?? 1);
            $per_page = 20;
            $offset = ($page - 1) * $per_page;

            // Get stats data
            $stats = quiz_ai_pro_get_stats_data($period, $quiz_id);
            $results = quiz_ai_pro_get_detailed_results($period, $quiz_id, $search, $per_page, $offset);
            $total_results = quiz_ai_pro_count_detailed_results($period, $quiz_id, $search);
            $total_pages = ceil($total_results / $per_page);

            wp_send_json_success([
                'stats' => $stats,
                'results' => $results,
                'total_results' => $total_results,
                'total_pages' => $total_pages,
                'current_page' => $page
            ]);
        } catch (Exception $e) {
            wp_send_json_error('Error filtering stats: ' . $e->getMessage());
        }
    }

    /**
     * Handle stats export
     */
    public function handle_export_stats()
    {
        try {
            // Security check
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            check_ajax_referer('quiz_ai_admin_nonce', 'nonce');

            $period = sanitize_text_field($_POST['period'] ?? '30days');
            $quiz_id = intval($_POST['quiz_id'] ?? 0);
            $format = sanitize_text_field($_POST['format'] ?? 'csv');

            // Generate export data
            $export_data = quiz_ai_pro_export_stats_data($period, $quiz_id, $format);

            wp_send_json_success([
                'download_url' => $export_data['url'],
                'filename' => $export_data['filename']
            ]);
        } catch (Exception $e) {
            wp_send_json_error('Error exporting stats: ' . $e->getMessage());
        }
    }

    /**
     * Handle quiz list filtering
     */
    public function handle_filter_quizzes()
    {
        try {
            // Security check
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            // Temporarily disable nonce check for debugging
            // check_ajax_referer('quiz_ai_admin_nonce', 'nonce');

            $filters = [
                'status' => sanitize_text_field($_POST['status'] ?? ''),
                'date' => sanitize_text_field($_POST['date'] ?? ''),
                'category' => intval($_POST['category'] ?? 0),
                'search' => sanitize_text_field($_POST['search'] ?? ''),
                'sort_by' => sanitize_text_field($_POST['sort_by'] ?? 'created_at'),
                'sort_order' => sanitize_text_field($_POST['sort_order'] ?? 'desc')
            ];

            $page = intval($_POST['page'] ?? 1);
            $per_page = 20;

            $results = quiz_ai_pro_get_filtered_quizzes($filters, $page, $per_page);

            wp_send_json_success([
                'quizzes' => $results['quizzes'],
                'total' => $results['total'],
                'total_pages' => $results['total_pages'],
                'current_page' => $page,
                'html' => quiz_ai_render_quiz_table($results['quizzes'])
            ]);
        } catch (Exception $e) {
            wp_send_json_error('Error filtering quizzes: ' . $e->getMessage());
        }
    }

    /**
     * Handle bulk quiz actions
     */
    public function handle_bulk_quiz_action()
    {
        try {
            error_log('Quiz IA Pro: Bulk action handler called');
            error_log('POST data: ' . print_r($_POST, true));

            // Security check
            if (!current_user_can('manage_options')) {
                error_log('Quiz IA Pro: Insufficient permissions');
                wp_send_json_error('Insufficient permissions');
                return;
            }

            // Verify nonce - try both possible nonces
            $nonce_verified = false;
            if (isset($_POST['nonce'])) {
                error_log('Quiz IA Pro: Received nonce: ' . $_POST['nonce']);
                error_log('Quiz IA Pro: Trying to verify against quiz_ai_admin_nonce');
                if (wp_verify_nonce($_POST['nonce'], 'quiz_ai_admin_nonce')) {
                    $nonce_verified = true;
                    error_log('Quiz IA Pro: Nonce verified with quiz_ai_admin_nonce');
                } else {
                    error_log('Quiz IA Pro: Failed to verify with quiz_ai_admin_nonce');
                    error_log('Quiz IA Pro: Trying to verify against quiz_ai_pro_nonce');
                    if (wp_verify_nonce($_POST['nonce'], 'quiz_ai_pro_nonce')) {
                        $nonce_verified = true;
                        error_log('Quiz IA Pro: Nonce verified with quiz_ai_pro_nonce');
                    } else {
                        error_log('Quiz IA Pro: Failed to verify with quiz_ai_pro_nonce');
                        // Let's also try with no nonce action for debugging
                        $current_nonce = wp_create_nonce('quiz_ai_admin_nonce');
                        error_log('Quiz IA Pro: Current expected nonce for quiz_ai_admin_nonce: ' . $current_nonce);
                        $current_nonce_pro = wp_create_nonce('quiz_ai_pro_nonce');
                        error_log('Quiz IA Pro: Current expected nonce for quiz_ai_pro_nonce: ' . $current_nonce_pro);
                    }
                }
            } else {
                error_log('Quiz IA Pro: No nonce provided in POST data');
            }

            if (!$nonce_verified) {
                error_log('Quiz IA Pro: Nonce verification failed');
                wp_send_json_error('Security check failed');
                return;
            }

            $action = sanitize_text_field($_POST['action_type'] ?? '');
            $quiz_ids = array_map('intval', $_POST['quiz_ids'] ?? []);

            error_log('Quiz IA Pro: Action=' . $action . ', Quiz IDs=' . implode(',', $quiz_ids));

            if (empty($quiz_ids)) {
                error_log('Quiz IA Pro: No quizzes selected');
                wp_send_json_error('No quizzes selected');
                return;
            }

            $results = quiz_ai_pro_bulk_quiz_action($action, $quiz_ids);

            error_log('Quiz IA Pro: Bulk action results: ' . print_r($results, true));

            wp_send_json_success([
                'message' => $results['message'],
                'affected' => $results['affected']
            ]);
        } catch (Exception $e) {
            error_log('Quiz IA Pro: Exception in bulk action: ' . $e->getMessage());
            wp_send_json_error('Error executing bulk action: ' . $e->getMessage());
        }
    }

    /**
     * Handle manual LearnPress quiz creation
     */
    public function handle_create_learnpress_quiz()
    {
        try {
            // Security checks
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }

            check_ajax_referer('quiz_ai_pro_nonce', 'nonce');

            $quiz_id = intval($_POST['quiz_id'] ?? 0);

            if (!$quiz_id) {
                wp_send_json_error('Quiz ID is required');
                return;
            }

            // Check if LearnPress is active
            if (!class_exists('LearnPress')) {
                wp_send_json_error('LearnPress plugin is not active');
                return;
            }

            // Get quiz data
            global $wpdb;
            $quiz_table = $wpdb->prefix . 'quiz_ia_quizzes';
            $questions_table = $wpdb->prefix . 'quiz_ia_questions';

            $quiz = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$quiz_table} WHERE id = %d",
                $quiz_id
            ));

            if (!$quiz) {
                wp_send_json_error('Quiz not found');
                return;
            }

            // Allow re-syncing: check if already synced and update existing or create new
            $is_resync = !empty($quiz->learnpress_quiz_id);
            if ($is_resync) {
                error_log("Quiz IA Pro: Re-syncing existing LearnPress quiz ID: " . $quiz->learnpress_quiz_id);

                // Check if LearnPress quiz still exists
                $existing_post = get_post($quiz->learnpress_quiz_id);
                if ($existing_post && $existing_post->post_type === 'lp_quiz') {
                    // Delete existing LearnPress quiz and all its questions/answers
                    error_log("Quiz IA Pro: Deleting existing LearnPress quiz and related data for re-sync");

                    // Get all questions linked to this quiz
                    $existing_questions = $wpdb->get_col($wpdb->prepare(
                        "SELECT question_id FROM {$wpdb->prefix}learnpress_quiz_questions WHERE quiz_id = %d",
                        $quiz->learnpress_quiz_id
                    ));

                    // Delete all question answers first
                    foreach ($existing_questions as $question_id) {
                        $wpdb->query($wpdb->prepare(
                            "DELETE FROM {$wpdb->prefix}learnpress_question_answers WHERE question_id = %d",
                            $question_id
                        ));
                        // Delete the question post
                        wp_delete_post($question_id, true);
                    }

                    // Delete quiz-question links
                    $wpdb->query($wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}learnpress_quiz_questions WHERE quiz_id = %d",
                        $quiz->learnpress_quiz_id
                    ));

                    // Delete the quiz post
                    wp_delete_post($quiz->learnpress_quiz_id, true);
                } else {
                    error_log("Quiz IA Pro: LearnPress quiz ID {$quiz->learnpress_quiz_id} no longer exists, will create new one");
                }

                // Clear the learnpress_quiz_id to allow fresh creation
                $wpdb->update(
                    $wpdb->prefix . 'quiz_ia_quizzes',
                    array('learnpress_quiz_id' => null),
                    array('id' => $quiz_id),
                    array('%s'),
                    array('%d')
                );
            }

            // Get quiz questions
            $questions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$questions_table} WHERE quiz_id = %d ORDER BY id ASC",
                $quiz_id
            ));

            // Also get the answers table
            $answers_table = $wpdb->prefix . 'quiz_ia_answers';

            // Prepare quiz data for LearnPress creation
            $quiz_data = array(
                'title' => $quiz->title,
                'description' => $quiz->description,
                'quiz_code' => $quiz->quiz_code,
                'time_limit' => $quiz->time_limit,
                'questions' => array()
            );

            // Convert questions to the expected format
            foreach ($questions as $question) {
                // Get question answers from the answers table
                $answers = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$answers_table} WHERE question_id = %d ORDER BY sort_order ASC",
                    $question->id
                ));

                // Convert answers to options array format
                $options = array();
                $correct_answer_index = 0;

                foreach ($answers as $index => $answer) {
                    $options[] = $answer->answer_text;
                    if ($answer->is_correct) {
                        $correct_answer_index = $index;
                    }
                }

                error_log("AJAX Handler - Question data structure:");
                error_log("Question ID: " . $question->id);
                error_log("Question Type: " . $question->question_type);
                error_log("Fetched Answers: " . count($answers));
                error_log("Options array: " . json_encode($options));
                error_log("Correct answer index: " . $correct_answer_index);

                $quiz_data['questions'][] = array(
                    'question' => $question->question_text,
                    'type' => $question->question_type,
                    'options' => $options,
                    'correct_answer' => $correct_answer_index,
                    'explanation' => $question->explanation
                );
            }

            // Create LearnPress quiz
            $lp_quiz_id = quiz_ai_pro_create_learnpress_quiz($quiz_id, $quiz_data);

            if (!$lp_quiz_id) {
                wp_send_json_error('Failed to create LearnPress quiz');
                return;
            }

            // Success response
            $message = $is_resync ? 'Quiz re-synchronisé avec succès dans LearnPress!' : 'Quiz créé avec succès dans LearnPress!';
            wp_send_json_success(array(
                'message' => $message,
                'learnpress_quiz_id' => $lp_quiz_id,
                'edit_url' => admin_url('post.php?post=' . $lp_quiz_id . '&action=edit'),
                'is_resync' => $is_resync
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error creating LearnPress quiz: ' . $e->getMessage());
        }
    }

    /**
     * Handle cleanup of orphaned LearnPress sync statuses
     */
    public function handle_cleanup_orphaned_learnpress_syncs()
    {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'quiz_ia_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        try {
            // Run the cleanup function
            quiz_ai_pro_cleanup_orphaned_syncs();

            wp_send_json_success('Orphaned sync statuses cleaned up successfully');
        } catch (Exception $e) {
            wp_send_json_error('Error cleaning up orphaned syncs: ' . $e->getMessage());
        }
    }

    /**
     * Handle getting performance data for charts via AJAX
     */
    public function handle_get_performance_data()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['security'], 'quiz_ai_pro_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        global $wpdb;

        try {
            // Get performance data for the last 8 weeks
            $end_date = current_time('mysql');
            $start_date = date('Y-m-d H:i:s', strtotime('-8 weeks', strtotime($end_date)));

            $performance_data = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    DATE_FORMAT(completed_at, '%%Y-%%u') as week_key,
                    DATE_FORMAT(completed_at, '%%d/%%m') as week_label,
                    WEEK(completed_at) as week_number,
                    YEAR(completed_at) as year,
                    AVG(percentage) as avg_success_rate,
                    COUNT(*) as participant_count,
                    COUNT(DISTINCT user_email) as unique_participants
                FROM {$wpdb->prefix}quiz_ia_results 
                WHERE completed_at BETWEEN %s AND %s
                GROUP BY week_key, week_number, year
                ORDER BY year DESC, week_number DESC
                LIMIT 8
            ", $start_date, $end_date));

            // Organize data for Chart.js
            $labels = array();
            $success_rates = array();
            $participant_counts = array();

            // Reverse to show chronological order
            $performance_data = array_reverse($performance_data);

            foreach ($performance_data as $week) {
                $labels[] = 'Sem ' . $week->week_number . ' (' . $week->week_label . ')';
                $success_rates[] = round(floatval($week->avg_success_rate), 1);
                $participant_counts[] = intval($week->participant_count);
            }

            // If no data, provide sample data
            if (empty($labels)) {
                $current_week = date('W');
                for ($i = 7; $i >= 0; $i--) {
                    $week_num = $current_week - $i;
                    if ($week_num < 1) $week_num += 52;
                    $labels[] = 'Semaine ' . $week_num;
                    $success_rates[] = 0;
                    $participant_counts[] = 0;
                }
            }

            $response_data = array(
                'labels' => $labels,
                'success_rates' => $success_rates,
                'participant_counts' => $participant_counts,
                'total_data_points' => count($labels)
            );

            wp_send_json_success($response_data);
        } catch (Exception $e) {
            error_log('Quiz IA Pro - Error getting performance data: ' . $e->getMessage());
            wp_send_json_error('Database error occurred');
        }
    }

    /**
     * Handle getting result details via AJAX
     */
    public function handle_get_result_details()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['security'], 'quiz_ai_pro_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $result_id = intval($_POST['result_id']);

        if (!$result_id) {
            wp_send_json_error('Invalid result ID');
            return;
        }

        global $wpdb;

        try {
            // Get the main result data
            $result = $wpdb->get_row($wpdb->prepare("
                SELECT r.*, q.title as quiz_title, q.description
                FROM {$wpdb->prefix}quiz_ia_results r
                LEFT JOIN {$wpdb->prefix}quiz_ia_quizzes q ON r.quiz_id = q.id
                WHERE r.id = %d
            ", $result_id));

            if (!$result) {
                wp_send_json_error('Result not found');
                return;
            }

            // Get detailed question answers
            $questions_details = array();

            // Check both possible column names for answers data
            $answers_json = $result->answers_data ?? $result->user_answers_json ?? '';

            if (!empty($answers_json)) {
                $answers_data = json_decode($answers_json, true);

                // The answers_data already contains all the question details we need
                if (is_array($answers_data)) {
                    foreach ($answers_data as $index => $answer_item) {
                        $question_detail = array(
                            'question_text' => $answer_item['question'] ?? 'Question sans texte',
                            'user_answer' => '',
                            'correct_answer' => '',
                            'is_correct' => $answer_item['is_correct'] ?? false,
                            'explanation' => $answer_item['explanation'] ?? ''
                        );

                        // Handle different question types
                        if ($answer_item['type'] === 'fill_blank') {
                            // For fill-in-blank questions
                            if (isset($answer_item['user_answer']) && is_array($answer_item['user_answer'])) {
                                $question_detail['user_answer'] = implode(', ', $answer_item['user_answer']);
                            } else {
                                $question_detail['user_answer'] = $answer_item['user_answer'] ?? 'Aucune réponse';
                            }

                            if (isset($answer_item['correct_answer']) && is_array($answer_item['correct_answer'])) {
                                $question_detail['correct_answer'] = implode(', ', $answer_item['correct_answer']);
                            } else {
                                $question_detail['correct_answer'] = $answer_item['correct_answer'] ?? '';
                            }
                        } else {
                            // For multiple choice and open questions
                            $question_detail['user_answer'] = $answer_item['user_answer'] ?? 'Aucune réponse';
                            $question_detail['correct_answer'] = $answer_item['correct_answer'] ?? '';
                        }

                        $questions_details[] = $question_detail;
                    }
                }
            }

            // Format time taken
            $time_taken_formatted = '';
            if (!empty($result->time_taken)) {
                $seconds = intval($result->time_taken);
                if ($seconds >= 3600) {
                    $hours = floor($seconds / 3600);
                    $minutes = floor(($seconds % 3600) / 60);
                    $secs = $seconds % 60;
                    $time_taken_formatted = sprintf('%dh %02dm %02ds', $hours, $minutes, $secs);
                } elseif ($seconds >= 60) {
                    $minutes = floor($seconds / 60);
                    $secs = $seconds % 60;
                    $time_taken_formatted = sprintf('%dm %02ds', $minutes, $secs);
                } else {
                    $time_taken_formatted = $seconds . 's';
                }
            }

            $response_data = array(
                'id' => $result->id,
                'quiz_title' => $result->quiz_title,
                'percentage' => round(floatval($result->percentage), 1),
                'correct_answers' => intval($result->correct_answers),
                'total_questions' => intval($result->total_questions),
                'time_taken' => $result->time_taken,
                'time_taken_formatted' => $time_taken_formatted,
                'completed_at' => $result->completed_at,
                'attempt_number' => isset($result->attempt_number) ? intval($result->attempt_number) : 1,
                'questions_details' => $questions_details
            );

            wp_send_json_success($response_data);
        } catch (Exception $e) {
            error_log('Quiz IA Pro - Error getting result details: ' . $e->getMessage());
            wp_send_json_error('Database error occurred');
        }
    }

    /**
     * Handle refreshing nonces
     */
    public function handle_refresh_nonces()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        wp_send_json_success([
            'quiz_ai_admin_nonce' => wp_create_nonce('quiz_ai_admin_nonce'),
            'quiz_ai_pro_nonce' => wp_create_nonce('quiz_ai_pro_nonce')
        ]);
    }

    /**
     * Handle quiz contact form submission (end of quiz)
     */
    public function handle_submit_quiz_contact_form()
    {
        // Use the same security verification as other frontend functions
        if (!QuizAIProSecurity::verify_nonce_and_permission('submit_quiz_contact_form')) {
            return;
        }

        try {
            $quiz_id = intval($_POST['quiz_id'] ?? 0);
            $receive_results = intval($_POST['receive_results'] ?? 0);
            $receive_alerts = intval($_POST['receive_alerts'] ?? 0);
            $user_name = sanitize_text_field($_POST['user_name'] ?? '');
            $user_email = sanitize_email($_POST['user_email'] ?? '');

            // Get user ID
            $user_id = get_current_user_id();

            // Validation
            if (!$quiz_id) {
                wp_send_json_error('ID du quiz manquant.');
                return;
            }

            if (!$user_id) {
                // For non-logged-in users, validate name and email
                if (empty($user_name) || empty($user_email)) {
                    wp_send_json_error('Nom et email requis pour les utilisateurs non connectés.');
                    return;
                }

                if (!is_email($user_email)) {
                    wp_send_json_error('Adresse email invalide.');
                    return;
                }
            } else {
                // For logged-in users, get their email
                $user = wp_get_current_user();
                $user_email = $user->user_email;
                $user_name = $user->display_name ?: $user->user_login;
            }

            // Save email preferences
            if (function_exists('quiz_ai_pro_save_email_preferences')) {
                $saved = quiz_ai_pro_save_email_preferences(
                    $user_email,
                    $user_name,
                    $receive_results,
                    $receive_alerts,
                    $quiz_id
                );

                if ($saved) {
                    // Send quiz results if requested and quiz exists
                    if ($receive_results && function_exists('quiz_ai_pro_send_quiz_result_email')) {
                        // Try to get recent quiz result for this user/email
                        global $wpdb;

                        $result = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}quiz_ia_results 
                             WHERE quiz_id = %d AND (user_id = %d OR user_email = %s)
                             ORDER BY completed_at DESC LIMIT 1",
                            $quiz_id,
                            $user_id,
                            $user_email
                        ));

                        if ($result) {
                            quiz_ai_pro_send_quiz_result_email($result, [
                                'name' => $user_name,
                                'email' => $user_email
                            ]);
                        }
                    }

                    wp_send_json_success('Préférences enregistrées avec succès!');
                } else {
                    wp_send_json_error('Erreur lors de l\'enregistrement des préférences.');
                }
            } else {
                wp_send_json_error('Fonction de sauvegarde des préférences non disponible.');
            }
        } catch (Exception $e) {
            error_log('Quiz IA Pro - Error in contact form submission: ' . $e->getMessage());
            wp_send_json_error('Erreur lors du traitement de la demande.');
        }
    }
}

// Initialize the AJAX handler
new QuizGeneratorAjax();
// Initialize AJAX handlers
new QuizGeneratorAjax();
