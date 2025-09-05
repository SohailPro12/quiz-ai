<?php

/**
 * Frontend functions for Quiz IA Pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include AI functions for scoring
require_once plugin_dir_path(__FILE__) . 'ai-functions.php';

/**
 * Security functions for Quiz IA Pro
 */
class QuizAIProSecurity
{

    /**
     * Check rate limit for actions
     */
    public static function check_rate_limit($action, $limit = 10, $window = 300)
    {
        error_log('Quiz IA Pro Debug: Checking rate limit for action: ' . $action);

        $user_id = get_current_user_id();
        $user_ip = self::get_user_ip();
        $cache_key = "quiz_ai_rate_limit_{$action}_{$user_id}_{$user_ip}";

        $attempts = get_transient($cache_key) ?: 0;

        error_log('Quiz IA Pro Debug: Current attempts: ' . $attempts . ' (limit: ' . $limit . ')');

        if ($attempts >= $limit) {
            self::log_security_event('rate_limit_exceeded', array(
                'action' => $action,
                'user_id' => $user_id,
                'ip' => $user_ip,
                'attempts' => $attempts
            ));

            wp_send_json_error(__('Rate limit exceeded. Please try again later.', 'quiz-ai-pro'));
            return false;
        }

        set_transient($cache_key, $attempts + 1, $window);
        error_log('Quiz IA Pro Debug: Rate limit check passed');
        return true;
    }

    /**
     * Log security events
     */
    public static function log_security_event($event, $data = array())
    {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event' => $event,
            'user_id' => get_current_user_id(),
            'ip' => self::get_user_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'data' => $data
        );

        error_log('QUIZ_AI_SECURITY: ' . wp_json_encode($log_entry));

        // Store in database for analysis
        global $wpdb;
        $security_table = $wpdb->prefix . 'quiz_ia_security_logs';

        // Create table if it doesn't exist
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$security_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            user_id int(11) DEFAULT 0,
            ip_address varchar(45) NOT NULL,
            event_data text,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY ip_address (ip_address),
            KEY created_at (created_at)
        )");

        $wpdb->insert(
            $security_table,
            array(
                'event_type' => sanitize_text_field($event),
                'user_id' => get_current_user_id(),
                'ip_address' => self::get_user_ip(),
                'event_data' => wp_json_encode($log_entry),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s', '%s')
        );
    }

    /**
     * Get user IP address
     */
    private static function get_user_ip()
    {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Validate and sanitize form data
     */
    public static function validate_quiz_submission_data($data)
    {
        $sanitized = array();

        // Quiz ID validation
        if (empty($data['quiz_id']) || !is_numeric($data['quiz_id'])) {
            return new WP_Error('invalid_quiz_id', __('Invalid quiz ID', 'quiz-ai-pro'));
        }
        $sanitized['quiz_id'] = absint($data['quiz_id']);

        // Answers validation
        if (empty($data['answers'])) {
            return new WP_Error('missing_answers', __('Answers are required', 'quiz-ai-pro'));
        }

        $answers = json_decode(stripslashes($data['answers']), true);
        if (!is_array($answers)) {
            return new WP_Error('invalid_answers', __('Invalid answers format', 'quiz-ai-pro'));
        }

        // Sanitize answers
        $sanitized_answers = array();
        foreach ($answers as $answer) {
            if (is_string($answer)) {
                // For text answers, sanitize but preserve content
                $sanitized_answers[] = wp_kses_post(trim($answer));
            } elseif (is_numeric($answer)) {
                // For multiple choice answers
                $sanitized_answers[] = intval($answer);
            } elseif (is_array($answer)) {
                // For fill-in-blank questions (arrays of text inputs)
                $sanitized_array_answer = array();
                foreach ($answer as $blank_answer) {
                    if (is_string($blank_answer)) {
                        $sanitized_array_answer[] = wp_kses_post(trim($blank_answer));
                    } else {
                        $sanitized_array_answer[] = '';
                    }
                }
                $sanitized_answers[] = $sanitized_array_answer;
            } else {
                $sanitized_answers[] = null;
            }
        }
        $sanitized['answers'] = $sanitized_answers;

        // User info validation (optional)
        if (!empty($data['user_info'])) {
            $user_info = json_decode(stripslashes($data['user_info']), true);
            if (is_array($user_info)) {
                $sanitized_user_info = array();

                if (!empty($user_info['name'])) {
                    $sanitized_user_info['name'] = sanitize_text_field($user_info['name']);
                }

                if (!empty($user_info['email'])) {
                    $email = sanitize_email($user_info['email']);
                    if (!is_email($email)) {
                        return new WP_Error('invalid_email', __('Invalid email format', 'quiz-ai-pro'));
                    }
                    $sanitized_user_info['email'] = $email;
                }

                if (!empty($user_info['phone'])) {
                    // Basic phone sanitization - remove all non-numeric and common phone chars
                    $phone = preg_replace('/[^0-9+\-() ]/', '', $user_info['phone']);
                    $sanitized_user_info['phone'] = sanitize_text_field($phone);
                }

                // Email preferences validation
                if (isset($user_info['email_quiz_results'])) {
                    $sanitized_user_info['email_quiz_results'] = (bool)$user_info['email_quiz_results'];
                }

                if (isset($user_info['email_new_quizzes'])) {
                    $sanitized_user_info['email_new_quizzes'] = (bool)$user_info['email_new_quizzes'];
                }

                $sanitized['user_info'] = $sanitized_user_info;
            }
        }

        // Quiz duration validation
        if (!empty($data['quiz_duration'])) {
            $sanitized['quiz_duration'] = absint($data['quiz_duration']);
        }

        return $sanitized;
    }

    /**
     * Enhanced nonce verification with action-specific checks
     */
    public static function verify_nonce_and_permission($action, $capability = null)
    {
        $nonce_action = "quiz_frontend_action";

        error_log('Quiz IA Pro Debug: Verifying nonce for action: ' . $action);
        error_log('Quiz IA Pro Debug: Expected nonce action: ' . $nonce_action);
        error_log('Quiz IA Pro Debug: Received nonce: ' . ($_POST['nonce'] ?? 'NOT_SET'));

        if (!wp_verify_nonce($_POST['nonce'] ?? '', $nonce_action)) {
            error_log('Quiz IA Pro Debug: Nonce verification FAILED');
            self::log_security_event('nonce_verification_failed', array(
                'action' => $action,
                'nonce' => $_POST['nonce'] ?? '',
                'expected_action' => $nonce_action
            ));
            wp_send_json_error(__('Security verification failed', 'quiz-ai-pro'));
            return false;
        }

        error_log('Quiz IA Pro Debug: Nonce verification PASSED');

        // For certain actions, check user capabilities
        if ($capability && !current_user_can($capability)) {
            self::log_security_event('insufficient_permissions', array(
                'action' => $action,
                'capability' => $capability,
                'user_id' => get_current_user_id()
            ));
            wp_send_json_error(__('Insufficient permissions', 'quiz-ai-pro'));
            return false;
        }

        return true;
    }
}

/**
 * Render categories with their quizzes
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function quiz_ai_pro_render_categories_with_quizzes($atts = array())
{
    global $wpdb;

    // Default attributes
    $defaults = array(
        'columns' => '3',
        'show_empty' => 'false',
        'order' => 'name',
        'orderby' => 'ASC'
    );
    $atts = array_merge($defaults, $atts);

    $output = '';
    // Add search bar section
    $output .= '<div class="quiz-categories-search-row">';
    $output .= '<form class="quiz-categories-search-form" onsubmit="return false;">';
    $output .= '<input type="text" class="quiz-categories-search-input" placeholder="Rechercher..." />';
    $output .= '<button type="submit" class="quiz-categories-search-btn"><span class="dashicons dashicons-search"></span></button>';
    $output .= '</form>';
    $output .= '</div>';

    try {
        // Get LearnPress categories - always include all categories for now
        $categories = get_terms(array(
            'taxonomy' => 'course_category',
            'hide_empty' => false, // Always show all categories to avoid count issues
            'orderby' => $atts['order'],
            'order' => $atts['orderby']
        ));

        if (empty($categories) || is_wp_error($categories)) {
            return '<div class="quiz-categories-notice">Aucune catégorie trouvée.</div>';
        }

        // Start output: only category cards, no quizzes yet
        $output .= '<div class="quiz-categories-container">';
        $output .= '<div class="quiz-categories-grid columns-' . esc_attr($atts['columns']) . '">';

        foreach ($categories as $category) {
            // Get quiz count for this category
            $quizzes = quiz_ai_pro_get_quizzes_by_category($category->term_id);
            $quiz_count = count($quizzes);
            if ($quiz_count === 0 && $atts['show_empty'] === 'false') {
                continue;
            }
            $output .= '<div class="quiz-category-card">';
            $output .= '<div class="category-card-flex">';
            $output .= '<div class="category-card-main">';
            $output .= '<h3 class="category-title">' . esc_html($category->name) . '</h3>';
            if (!empty($category->description)) {
                $output .= '<p class="category-description">' . esc_html($category->description) . '</p>';
            }
            $output .= '</div>';
            $output .= '<div class="category-card-count">';
            $output .= '<span class="quiz-count-number">' . $quiz_count . '</span>';
            $output .= '<span class="quiz-count-label">quiz' . ($quiz_count > 1 ? 's' : '') . '</span>';
            $output .= '</div>';
            $output .= '</div>'; // .category-card-flex
            $output .= '<div class="category-card-btn-row">';
            $output .= '<button class="voir-les-quiz-btn" data-category-id="' . esc_attr($category->term_id) . '">Voir les quiz</button>';
            $output .= '</div>';
            $output .= '<div class="category-quiz-list" id="category-quiz-list-' . esc_attr($category->term_id) . '" style="display:none;"></div>';
            $output .= '</div>'; // .quiz-category-card
        }

        $output .= '</div>'; // .quiz-categories-grid
        $output .= '</div>'; // .quiz-categories-container

    } catch (Exception $e) {
        error_log('Quiz IA Pro Frontend Error: ' . $e->getMessage());
        $output = '<div class="quiz-categories-error">Une erreur est survenue lors du chargement des quiz.</div>';
    }

    return $output;
}

/**
 * Get quizzes by category ID - Enhanced Security Version
 * 
 * @param int $category_id Category ID
 * @return array Array of quiz objects
 */
function quiz_ai_pro_get_quizzes_by_category($category_id)
{
    global $wpdb;

    // Input validation
    $category_id = absint($category_id);
    if (!$category_id) {
        return array();
    }

    $table_name = $wpdb->prefix . 'quiz_ia_quizzes';

    try {
        // Start database transaction
        $wpdb->query('START TRANSACTION');

        // Use secure prepared statements for category search
        // The category_id is stored as a string like '[7, 9]', so we need to search within this format
        $quiz_results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, category_id, description, quiz_type, total_questions, status, course_id, created_at
             FROM {$table_name} 
             WHERE (
                 category_id LIKE %s OR
                 category_id LIKE %s OR
                 category_id LIKE %s OR
                 category_id LIKE %s OR
                 category_id LIKE %s OR
                 category_id LIKE %s
             )
             AND status IN ('published', 'completed')
             ORDER BY created_at DESC",
            '[' . $category_id . ']',           // Exact match [7]
            '[' . $category_id . ',%',          // Start of array [7,...]
            '[' . $category_id . ', %',         // Start of array with space [7, ...]
            '%,' . $category_id . ']',          // End of array [...,7]
            '%, ' . $category_id . ']',         // End of array with space [..., 7]
            '%,' . $category_id . ',%'          // Middle of array [...,7,...]
        ));

        if (empty($quiz_results)) {
            $wpdb->query('COMMIT');
            return array();
        }

        // Remove duplicates securely
        $unique_results = quiz_ai_pro_remove_duplicate_quizzes($quiz_results);

        if (empty($unique_results)) {
            $wpdb->query('COMMIT');
            return array();
        }

        // Get detailed quiz information securely
        $detailed_quizzes = quiz_ai_pro_get_detailed_quiz_info($unique_results);

        // Commit transaction
        $wpdb->query('COMMIT');

        return $detailed_quizzes;
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        QuizAIProSecurity::log_security_event('quiz_category_fetch_error', array(
            'category_id' => $category_id,
            'error' => $e->getMessage()
        ));
        error_log('Quiz IA Pro Category Fetch Error: ' . $e->getMessage());
        return array();
    }
}

/**
 * Remove duplicate quizzes securely
 */
function quiz_ai_pro_remove_duplicate_quizzes($quiz_results)
{
    $unique_results = array();
    $seen_ids = array();

    foreach ($quiz_results as $result) {
        $quiz_id = absint($result->id);
        if (!in_array($quiz_id, $seen_ids)) {
            $unique_results[] = $result;
            $seen_ids[] = $quiz_id;
        }
    }

    return $unique_results;
}

/**
 * Get detailed quiz information securely
 */
function quiz_ai_pro_get_detailed_quiz_info($quiz_results)
{
    global $wpdb;

    $quiz_ids = array_map(function ($quiz) {
        return absint($quiz->id);
    }, $quiz_results);

    // Create safe placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($quiz_ids), '%d'));

    // Get full quiz data with question counts securely
    $detailed_query = $wpdb->prepare(
        "SELECT 
            q.*,
            GROUP_CONCAT(DISTINCT c.post_title) as course_names,
            (SELECT COUNT(*) FROM {$wpdb->prefix}quiz_ia_questions WHERE quiz_id = q.id) as actual_question_count
         FROM {$wpdb->prefix}quiz_ia_quizzes q
         LEFT JOIN {$wpdb->posts} c ON (
             FIND_IN_SET(c.ID, REPLACE(REPLACE(REPLACE(q.course_id, '[', ''), ']', ''), '\"', '')) > 0
         )
         WHERE q.id IN ({$placeholders})
         GROUP BY q.id
         ORDER BY q.created_at DESC",
        ...$quiz_ids
    );

    $final_results = $wpdb->get_results($detailed_query);

    // Process and sanitize course names for display
    foreach ($final_results as &$quiz) {
        // Sanitize quiz data
        $quiz->id = absint($quiz->id);
        $quiz->title = wp_kses_post($quiz->title);
        $quiz->description = wp_kses_post($quiz->description);
        $quiz->difficulty = 'mixed'; // Default since difficulty column doesn't exist
        $quiz->quiz_type = sanitize_text_field($quiz->quiz_type);
        $quiz->total_questions = absint($quiz->total_questions);
        $quiz->actual_question_count = absint($quiz->actual_question_count);

        // Process course names securely
        if (!empty($quiz->course_names)) {
            $course_names_array = array_map('sanitize_text_field', explode(',', $quiz->course_names));
            $quiz->course_names = wp_json_encode($course_names_array);
        }
    }

    return $final_results;
}

// AJAX: Get quizzes by category and return HTML for frontend display
function quiz_ai_pro_ajax_get_quizzes_by_category()
{
    // Rate limiting
    if (!QuizAIProSecurity::check_rate_limit('get_quizzes', 20, 60)) {
        return;
    }

    // Enhanced security verification
    if (!QuizAIProSecurity::verify_nonce_and_permission('get_quizzes_by_category')) {
        return;
    }

    // Input validation and sanitization
    if (!isset($_POST['category_id'])) {
        QuizAIProSecurity::log_security_event('missing_category_id', $_POST);
        wp_send_json_error(['message' => 'Invalid request - missing category ID']);
        return;
    }

    $category_id = absint($_POST['category_id']);
    if (!$category_id) {
        QuizAIProSecurity::log_security_event('invalid_category_id', array(
            'provided_id' => $_POST['category_id']
        ));
        wp_send_json_error(['message' => 'Invalid category ID']);
        return;
    }

    error_log('Quiz IA Pro: AJAX get_quizzes_by_category called for category_id=' . $category_id);

    try {
        $quizzes = quiz_ai_pro_get_quizzes_by_category($category_id);
        error_log('Quiz IA Pro: Number of quizzes found=' . count($quizzes));

        if (empty($quizzes)) {
            wp_send_json_success(['html' => '<div class="no-quizzes">' . esc_html__('Aucun quiz disponible dans cette catégorie.', 'quiz-ai-pro') . '</div>']);
            return;
        }

        $html = quiz_ai_pro_render_quiz_list_html($quizzes);
        wp_send_json_success(['html' => $html]);
    } catch (Exception $e) {
        QuizAIProSecurity::log_security_event('quiz_fetch_error', array(
            'category_id' => $category_id,
            'error' => $e->getMessage()
        ));
        error_log('Quiz IA Pro Error: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Error loading quizzes']);
    }
}

/**
 * Secure HTML rendering for quiz list
 */
function quiz_ai_pro_render_quiz_list_html($quizzes)
{
    $html = '';
    $html .= '<div class="quiz-list-grid">';

    foreach ($quizzes as $quiz) {
        // Sanitize all output - use quiz_type as difficulty since difficulty column doesn't exist
        $difficulty = !empty($quiz->quiz_type) ? sanitize_text_field($quiz->quiz_type) : 'mixed';
        $question_count = !empty($quiz->actual_question_count) ? absint($quiz->actual_question_count) : absint($quiz->total_questions);

        $html .= '<div class="quiz-list-card">';
        $html .= '<h4 class="quiz-title">' . esc_html($quiz->title) . '</h4>';
        $html .= '<span class="quiz-difficulty ' . esc_attr($difficulty) . '">' . esc_html(ucfirst($difficulty)) . '</span>';

        if (!empty($quiz->description)) {
            $html .= '<p class="quiz-description">' . esc_html(wp_trim_words($quiz->description, 30)) . '</p>';
        }

        $html .= '<div class="quiz-meta">';
        $html .= '<span class="question-count">' . absint($question_count) . ' ' . esc_html__('questions', 'quiz-ai-pro') . '</span>';

        if (!empty($quiz->quiz_type) && strtolower(sanitize_text_field($quiz->quiz_type)) !== strtolower($difficulty)) {
            $html .= '<span class="quiz-type">' . esc_html($quiz->quiz_type) . '</span>';
        }
        $html .= '</div>';

        if (!empty($quiz->course_names)) {
            $course_names = json_decode($quiz->course_names, true);
            if (is_array($course_names) && !empty($course_names)) {
                $html .= '<div class="quiz-courses">';
                $html .= '<span class="courses-label">' . esc_html__('Cours:', 'quiz-ai-pro') . '</span>';
                foreach ($course_names as $course_name) {
                    $html .= '<span class="course-tag">' . esc_html($course_name) . '</span>';
                }
                $html .= '</div>';
            }
        }

        $html .= '<div class="quiz-actions">';
        $html .= '<button class="btn-take-quiz" data-quiz-id="' . absint($quiz->id) . '">' . esc_html__('Passer le Quiz', 'quiz-ai-pro') . '</button>';
        $html .= '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}

/**
 * Get quiz details for AJAX requests
 */
function quiz_ai_pro_get_quiz_details()
{
    // Add debugging
    error_log('Quiz IA Pro Debug: get_quiz_details function called');
    error_log('Quiz IA Pro Debug: POST data: ' . print_r($_POST, true));

    // Rate limiting for quiz details requests
    if (!QuizAIProSecurity::check_rate_limit('get_quiz_details', 30, 60)) {
        error_log('Quiz IA Pro Debug: Rate limit exceeded');
        return;
    }

    // Enhanced security verification
    if (!QuizAIProSecurity::verify_nonce_and_permission('get_quiz_details')) {
        error_log('Quiz IA Pro Debug: Nonce verification failed');
        return;
    }

    error_log('Quiz IA Pro Debug: Security checks passed');

    // Input validation
    if (!isset($_POST['quiz_id'])) {
        QuizAIProSecurity::log_security_event('missing_quiz_id', $_POST);
        wp_send_json_error('Missing quiz ID');
        return;
    }

    $quiz_id = absint($_POST['quiz_id']);
    if (!$quiz_id) {
        QuizAIProSecurity::log_security_event('invalid_quiz_id', array(
            'provided_id' => $_POST['quiz_id']
        ));
        wp_send_json_error('Invalid quiz ID');
        return;
    }

    error_log('Quiz IA Pro: Requested quiz_id=' . $quiz_id);

    global $wpdb;

    try {
        // Use database transaction for data integrity
        $wpdb->query('START TRANSACTION');

        $table_name = $wpdb->prefix . 'quiz_ia_quizzes';

        // Secure quiz query with proper escaping
        $quiz_query = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d AND status IN ('completed', 'published')",
            $quiz_id
        );
        $quiz = $wpdb->get_row($quiz_query);

        if (!$quiz) {
            $wpdb->query('ROLLBACK');
            QuizAIProSecurity::log_security_event('quiz_not_found', array(
                'quiz_id' => $quiz_id
            ));
            wp_send_json_error('Quiz not found');
            return;
        }

        // Get questions from separate questions table
        $questions_table = $wpdb->prefix . 'quiz_ia_questions';
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$questions_table} WHERE quiz_id = %d ORDER BY sort_order ASC",
            $quiz_id
        ));

        if (empty($questions)) {
            $wpdb->query('ROLLBACK');
            QuizAIProSecurity::log_security_event('no_questions_found', array(
                'quiz_id' => $quiz_id
            ));
            wp_send_json_error('No questions found for this quiz');
            return;
        }

        // Format questions for frontend display (fetch answers from answers table)
        $formatted_questions = quiz_ai_pro_format_questions_securely($questions);

        // Parse quiz settings from JSON securely
        $quiz_settings = quiz_ai_pro_parse_quiz_settings_securely($quiz);

        // Commit transaction
        $wpdb->query('COMMIT');

        // Log successful access
        QuizAIProSecurity::log_security_event('quiz_details_accessed', array(
            'quiz_id' => $quiz_id,
            'quiz_title' => $quiz->title
        ));

        error_log('Quiz IA Pro: Returning quiz details for quiz_id=' . $quiz_id);
        wp_send_json_success(array(
            'quiz' => quiz_ai_pro_sanitize_quiz_output($quiz),
            'questions' => $formatted_questions,
            'settings' => $quiz_settings
        ));
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        QuizAIProSecurity::log_security_event('quiz_details_error', array(
            'quiz_id' => $quiz_id,
            'error' => $e->getMessage()
        ));
        error_log('Quiz IA Pro Error: ' . $e->getMessage());
        wp_send_json_error('Error loading quiz details');
    }
}

/**
 * Securely format questions for frontend
 */
function quiz_ai_pro_format_questions_securely($questions)
{
    global $wpdb;
    $formatted_questions = array();
    $answers_table = $wpdb->prefix . 'quiz_ia_answers';

    foreach ($questions as $question) {
        // Fetch all answers for this question, ordered by sort_order
        $answers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$answers_table} WHERE question_id = %d ORDER BY sort_order ASC",
            $question->id
        ));

        $options = array();
        $correct_answer_index = null;

        foreach ($answers as $idx => $answer) {
            // Sanitize answer text
            $options[] = wp_kses_post($answer->answer_text);
            if ($answer->is_correct) {
                $correct_answer_index = $idx;
            }
        }

        $formatted_questions[] = array(
            'question' => wp_kses_post($question->question_text),
            'type' => sanitize_text_field($question->question_type ?: 'qcm'),
            'options' => $options,
            'correct_answer' => $correct_answer_index !== null ? $correct_answer_index : 0,
            'explanation' => wp_kses_post($question->explanation ?: ''),
            'image' => esc_url($question->featured_image ?: '')
        );
    }

    return $formatted_questions;
}

/**
 * Securely parse quiz settings
 */
function quiz_ai_pro_parse_quiz_settings_securely($quiz)
{
    $settings = array();

    if (!empty($quiz->settings)) {
        $decoded_settings = json_decode($quiz->settings, true);
        if (is_array($decoded_settings)) {
            $settings = $decoded_settings;
        }
    }

    // Set default values with type casting for security
    $quiz_settings = array_merge(array(
        'show_contact_form' => false,
        'show_page_number' => true,
        'show_question_images' => false,
        'show_question_images_results' => false,
        'show_progress_bar' => true,
        'require_login' => false,
        'disable_first_page' => false,
        'enable_comments' => false,
        'time_limit' => absint($quiz->time_limit ?? 0),
        'questions_per_page' => absint($quiz->questions_per_page ?? 1)
    ), $settings);

    // Sanitize boolean settings
    $boolean_fields = array(
        'show_contact_form',
        'show_page_number',
        'show_question_images',
        'show_question_images_results',
        'show_progress_bar',
        'require_login',
        'disable_first_page',
        'enable_comments'
    );

    foreach ($boolean_fields as $field) {
        $quiz_settings[$field] = !empty($quiz_settings[$field]) &&
            filter_var($quiz_settings[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;
    }

    // Sanitize numeric settings
    $quiz_settings['time_limit'] = absint($quiz_settings['time_limit']);
    $quiz_settings['questions_per_page'] = max(1, absint($quiz_settings['questions_per_page']));

    return $quiz_settings;
}

/**
 * Sanitize quiz output for frontend
 */
function quiz_ai_pro_sanitize_quiz_output($quiz)
{
    return (object) array(
        'id' => absint($quiz->id),
        'title' => esc_html($quiz->title),
        'description' => wp_kses_post($quiz->description),
        'difficulty' => 'mixed', // Default since difficulty column doesn't exist
        'total_questions' => absint($quiz->total_questions),
        'time_limit' => absint($quiz->time_limit),
        'questions_per_page' => absint($quiz->questions_per_page),
        'quiz_type' => sanitize_text_field($quiz->quiz_type),
        'status' => sanitize_text_field($quiz->status),
        'featured_image' => esc_url($quiz->featured_image)
    );
}

/**
 * Handle quiz answer submission (AJAX) - Enhanced Security Version
 */
function quiz_ai_pro_submit_quiz_answers()
{
    // Rate limiting for quiz submissions
    if (!QuizAIProSecurity::check_rate_limit('submit_quiz', 5, 300)) {
        return;
    }

    // Enhanced security verification
    if (!QuizAIProSecurity::verify_nonce_and_permission('submit_quiz_answers')) {
        return;
    }

    // Validate and sanitize input data
    $validated_data = QuizAIProSecurity::validate_quiz_submission_data($_POST);
    if (is_wp_error($validated_data)) {
        QuizAIProSecurity::log_security_event('invalid_submission_data', array(
            'error' => $validated_data->get_error_message(),
            'post_data' => $_POST
        ));
        wp_send_json_error($validated_data->get_error_message());
        return;
    }

    $quiz_id = $validated_data['quiz_id'];
    $user_answers = $validated_data['answers'];
    $user_info = $validated_data['user_info'] ?? null;
    $quiz_duration = $validated_data['quiz_duration'] ?? 0;

    global $wpdb;

    try {
        // Start database transaction for data integrity
        $wpdb->query('START TRANSACTION');

        // Verify quiz exists and is available
        $quiz = quiz_ai_pro_verify_quiz_availability($quiz_id);
        if (!$quiz) {
            throw new Exception('Quiz not available');
        }

        // Get questions securely
        $questions = quiz_ai_pro_get_quiz_questions_securely($quiz_id);
        if (empty($questions)) {
            throw new Exception('No questions found');
        }

        // Validate answer count matches question count
        if (count($user_answers) !== count($questions)) {
            throw new Exception('Answer count mismatch');
        }

        // Process answers and calculate score
        $scoring_result = quiz_ai_pro_process_answers_securely($questions, $user_answers);

        // Save attempt to database securely
        $attempt_id = quiz_ai_pro_save_attempt_securely(
            $quiz_id,
            $scoring_result,
            $user_info,
            $quiz_duration
        );

        if (!$attempt_id) {
            throw new Exception('Failed to save quiz attempt');
        }

        // Commit transaction
        $wpdb->query('COMMIT');

        // Log successful submission
        QuizAIProSecurity::log_security_event('quiz_submitted_successfully', array(
            'quiz_id' => $quiz_id,
            'attempt_id' => $attempt_id,
            'score' => $scoring_result['score'],
            'total' => $scoring_result['total']
        ));

        // Get quiz data for course recommendation
        $quiz_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}quiz_ia_quizzes WHERE id = %d",
            $quiz_id
        ));

        // Generate overall course recommendation
        $course_recommendation = null;
        if ($quiz_data && function_exists('quiz_ai_pro_generate_overall_course_recommendation')) {
            $course_recommendation = quiz_ai_pro_generate_overall_course_recommendation(
                $quiz_data,
                $scoring_result['score'],
                $scoring_result['total']
            );
        }

        // Save email preferences and send result email if requested
        if ($user_info && isset($user_info['email']) && is_email($user_info['email'])) {
            error_log('Quiz IA Pro Debug: Processing email preferences for user: ' . $user_info['email']);
            error_log('Quiz IA Pro Debug: User info data: ' . print_r($user_info, true));

            // Save email preferences
            $email_preferences = [
                'user_name' => $user_info['name'] ?? '',
                'user_id' => get_current_user_id(),
                'receive_quiz_results' => isset($user_info['email_quiz_results']) ? (bool)$user_info['email_quiz_results'] : true,
                'receive_new_quiz_alerts' => isset($user_info['email_new_quizzes']) ? (bool)$user_info['email_new_quizzes'] : true,
                'quiz_id' => $quiz_id
            ];

            error_log('Quiz IA Pro Debug: Email preferences to save: ' . print_r($email_preferences, true));

            $saved = quiz_ai_pro_save_email_preferences($user_info['email'], $email_preferences);
            error_log('Quiz IA Pro Debug: Email preferences saved: ' . ($saved ? 'YES' : 'NO'));

            // Send result email if user opted in
            if ($email_preferences['receive_quiz_results']) {
                $result_data = [
                    'user_name' => $user_info['name'] ?? 'Utilisateur',
                    'score' => $scoring_result['score'],
                    'total' => $scoring_result['total'],
                    'percentage' => $scoring_result['percentage'],
                    'details' => $scoring_result['details'],
                    'course_recommendation' => $course_recommendation
                ];

                error_log('Quiz IA Pro Debug: Attempting to send result email to: ' . $user_info['email']);
                $email_sent = quiz_ai_pro_send_quiz_result_email($user_info['email'], $quiz_data, $result_data);
                error_log('Quiz IA Pro Debug: Result email sent: ' . ($email_sent ? 'YES' : 'NO'));
            } else {
                error_log('Quiz IA Pro Debug: User opted out of result emails');
            }
        } else {
            error_log('Quiz IA Pro Debug: No user_info found or invalid email');
            if ($user_info) {
                error_log('Quiz IA Pro Debug: User info exists but email check failed: ' . print_r($user_info, true));
            } else {
                error_log('Quiz IA Pro Debug: No user_info data at all');
            }
        }

        wp_send_json_success(array(
            'score' => $scoring_result['score'],
            'total' => $scoring_result['total'],
            'details' => $scoring_result['details'],
            'attempt_id' => $attempt_id,
            'percentage' => $scoring_result['percentage'],
            'course_recommendation' => $course_recommendation
        ));
    } catch (Exception $e) {
        // Rollback transaction on error
        $wpdb->query('ROLLBACK');

        QuizAIProSecurity::log_security_event('quiz_submission_error', array(
            'quiz_id' => $quiz_id,
            'error' => $e->getMessage(),
            'user_id' => get_current_user_id()
        ));

        error_log('Quiz IA Pro Submission Error: ' . $e->getMessage());
        wp_send_json_error('Quiz submission failed: ' . $e->getMessage());
    }
}

/**
 * Verify quiz availability securely
 */
function quiz_ai_pro_verify_quiz_availability($quiz_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_quizzes';

    $quiz = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d AND status IN ('completed', 'published')",
        $quiz_id
    ));

    return $quiz;
}

/**
 * Get quiz questions securely
 */
function quiz_ai_pro_get_quiz_questions_securely($quiz_id)
{
    global $wpdb;
    $questions_table = $wpdb->prefix . 'quiz_ia_questions';

    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$questions_table} WHERE quiz_id = %d ORDER BY sort_order ASC",
        $quiz_id
    ));

    return $questions;
}

/**
 * Process answers and calculate score securely
 */
function quiz_ai_pro_process_answers_securely($questions, $user_answers)
{
    global $wpdb;
    $score = 0;
    $details = array();
    $answers_table = $wpdb->prefix . 'quiz_ia_answers';

    foreach ($questions as $i => $question) {
        // Initialize all variables at the start of each loop
        $ai_suggested_sections = [];
        $ai_course_reference = '';
        $enhanced_feedback = null;
        $enhanced_feedback_html = '';
        $expected_answers = null;

        // Get answers for this question securely
        $answers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$answers_table} WHERE question_id = %d ORDER BY sort_order ASC",
            $question->id
        ));

        $options = array();
        $correct_answer_index = null;

        foreach ($answers as $idx => $answer) {
            $options[] = wp_kses_post($answer->answer_text);
            if ($answer->is_correct) {
                $correct_answer_index = $idx;
            }
        }

        $user_answer = isset($user_answers[$i]) ? $user_answers[$i] : null;
        $is_correct = false;
        $user_answer_text = '';
        $ai_feedback = null;
        $ai_score = null;

        // Handle different question types securely
        if ($question->question_type === 'open' || $question->question_type === 'text' || $question->question_type === 'essay') {
            // For open questions, use AI-powered scoring
            $user_answer_text = is_string($user_answer) ? wp_kses_post(trim($user_answer)) : '';

            if (empty($user_answer_text)) {
                $is_correct = false;
                $ai_feedback = __('Aucune réponse fournie', 'quiz-ai-pro');
                $ai_score = 0;
            } else {
                // Get expected answer from the first correct answer in options
                $expected_answer = '';
                if (!empty($options) && $correct_answer_index !== null && isset($options[$correct_answer_index])) {
                    $expected_answer = $options[$correct_answer_index];
                }

                // For text/essay questions without predefined answers, use question context for evaluation
                if (empty($expected_answer) && ($question->question_type === 'text' || $question->question_type === 'essay')) {
                    $expected_answer = 'Cette question sera évaluée selon la pertinence, la précision et la complétude de la réponse par rapport au sujet abordé.';
                }

                // Use enhanced feedback system for better AI evaluation
                if (function_exists('quiz_ai_pro_score_open_question')) {
                    // Use proper AI scoring first
                    // Get course information
                    $course_info = null;
                    if (isset($question->course_reference) && !empty($question->course_reference)) {
                        // Prepare course info from the question's course reference
                        $course_info = array(
                            'title' => get_the_title($quiz->ID) ?: 'Cours',
                            'slug' => $question->course_reference,
                        );
                    }

                    $ai_evaluation = quiz_ai_pro_score_open_question(
                        wp_kses_post($question->question_text),
                        $expected_answer,
                        $user_answer_text,
                        wp_kses_post($question->explanation ?: ''),
                        $course_info
                    );

                    $is_correct = $ai_evaluation['is_correct'];
                    $ai_feedback = sanitize_text_field($ai_evaluation['feedback']);
                    $ai_score = absint($ai_evaluation['percentage']);
                    $ai_course_reference = isset($ai_evaluation['course_reference']) ? sanitize_text_field($ai_evaluation['course_reference']) : '';
                    $ai_suggested_sections = isset($ai_evaluation['suggested_sections']) ? $ai_evaluation['suggested_sections'] : [];

                    // If AI didn't provide sections, use our new section selection function
                    if (empty($ai_suggested_sections) && function_exists('quiz_ai_pro_get_course_sections') && function_exists('quiz_ai_pro_select_relevant_sections')) {
                        $quiz_table = $wpdb->prefix . 'quiz_ia_quizzes';
                        $quiz = $wpdb->get_row($wpdb->prepare(
                            "SELECT course_id FROM {$quiz_table} WHERE id = %d",
                            $question->quiz_id
                        ));

                        $course_id = $quiz ? $quiz->course_id : 0;
                        if ($course_id) {
                            $available_sections = quiz_ai_pro_get_course_sections($course_id);
                            error_log('Quiz IA Pro Debug: Retrieved sections for course ' . $course_id . ': ' . print_r($available_sections, true));

                            if (!empty($available_sections)) {
                                $ai_suggested_sections = quiz_ai_pro_select_relevant_sections(
                                    $question->question_text,
                                    $question->explanation ?? '',
                                    $available_sections,
                                    $is_correct,
                                    3
                                );
                                error_log('Quiz IA Pro Debug: AI selected sections: ' . print_r($ai_suggested_sections, true));
                            }
                        }
                    }

                    // Also try to generate enhanced feedback
                    if (function_exists('quiz_ai_get_enhanced_feedback')) {
                        $enhanced_feedback = quiz_ai_get_enhanced_feedback(
                            $question->quiz_id,
                            $question->id,
                            $user_answer_text,
                            $expected_answer,
                            $question->question_text,
                            $question->question_type
                        );
                        error_log('Enhanced feedback generated: ' . print_r($enhanced_feedback, true));
                    }
                } elseif (function_exists('quiz_ai_get_enhanced_feedback')) {
                    $enhanced_feedback = quiz_ai_get_enhanced_feedback(
                        $question->quiz_id,
                        $question->id,
                        $user_answer_text,
                        $expected_answer,
                        $question->question_text,
                        $question->question_type
                    );

                    // Debug log
                    error_log('Enhanced feedback generated: ' . print_r($enhanced_feedback, true));

                    // Use enhanced AI feedback if available
                    $ai_feedback = $enhanced_feedback['ai_feedback'] ?? __('Évaluation IA temporairement indisponible', 'quiz-ai-pro');

                    // Simple scoring for open questions based on content quality
                    $word_count = str_word_count($user_answer_text);
                    if ($word_count >= 10) {
                        $is_correct = true;
                        $ai_score = 85; // Good detailed answer
                    } elseif ($word_count >= 5) {
                        $is_correct = true;
                        $ai_score = 70; // Acceptable answer
                    } else {
                        $is_correct = false;
                        $ai_score = 30; // Too short answer
                    }
                } elseif (function_exists('quiz_ai_pro_score_open_question')) {
                    // Fallback to existing AI scoring
                    // Get course information
                    $course_info = null;
                    if (isset($question->course_reference) && !empty($question->course_reference)) {
                        // Prepare course info from the question's course reference
                        $course_info = array(
                            'title' => get_the_title($quiz->ID) ?: 'Cours',
                            'slug' => $question->course_reference,
                        );
                    }

                    $ai_evaluation = quiz_ai_pro_score_open_question(
                        wp_kses_post($question->question_text),
                        $expected_answer,
                        $user_answer_text,
                        wp_kses_post($question->explanation ?: ''),
                        $course_info
                    );

                    $is_correct = $ai_evaluation['is_correct'];
                    $ai_feedback = sanitize_text_field($ai_evaluation['feedback']);
                    $ai_score = absint($ai_evaluation['percentage']);
                    $ai_course_reference = isset($ai_evaluation['course_reference']) ? sanitize_text_field($ai_evaluation['course_reference']) : '';
                    $ai_suggested_sections = isset($ai_evaluation['suggested_sections']) ? $ai_evaluation['suggested_sections'] : [];
                } else {
                    // Fallback if AI scoring is not available
                    $is_correct = !empty($user_answer_text);
                    $ai_feedback = __('Réponse enregistrée', 'quiz-ai-pro');
                    $ai_score = $is_correct ? 100 : 0;
                }
            }
        } elseif ($question->question_type === 'fill_blank' || $question->question_type === 'text_a_completer') {
            // For fill-in-the-blank questions
            $user_answers_array = is_array($user_answer) ? $user_answer : (empty($user_answer) ? [] : [$user_answer]);
            $expected_answers = array();

            // Get expected answers from the options
            foreach ($options as $expected_answer) {
                if (!empty(trim($expected_answer))) {
                    $expected_answers[] = trim($expected_answer);
                }
            }

            // Debug logging for fill-in-blank
            error_log('Fill-in-blank Debug - Question: ' . $question->question_text);
            error_log('Fill-in-blank Debug - User answers: ' . print_r($user_answers_array, true));
            error_log('Fill-in-blank Debug - Expected answers: ' . print_r($expected_answers, true));

            // Check if user provided answers match expected answers
            $correct_count = 0;
            $total_blanks = count($expected_answers);

            for ($blank_idx = 0; $blank_idx < $total_blanks; $blank_idx++) {
                $user_blank_answer = isset($user_answers_array[$blank_idx]) ? trim($user_answers_array[$blank_idx]) : '';
                $expected_blank_answer = isset($expected_answers[$blank_idx]) ? trim($expected_answers[$blank_idx]) : '';

                // Case-insensitive comparison
                if (strcasecmp($user_blank_answer, $expected_blank_answer) === 0) {
                    $correct_count++;
                }
            }

            $is_correct = ($correct_count === $total_blanks);
            $user_answer_text = is_array($user_answers_array) ? implode(', ', $user_answers_array) : '';

            // Calculate partial score for fill-in-the-blank
            $partial_score = $total_blanks > 0 ? round(($correct_count / $total_blanks) * 100) : 0;
            $ai_score = $partial_score;

            // Use question's actual explanation
            $ai_feedback = !empty($question->explanation) ? $question->explanation : sprintf(
                __('%d sur %d réponses correctes (%d%%)', 'quiz-ai-pro'),
                $correct_count,
                $total_blanks,
                $partial_score
            );

            // Get course sections and let AI select relevant ones
            $quiz_table = $wpdb->prefix . 'quiz_ia_quizzes';
            $quiz = $wpdb->get_row($wpdb->prepare(
                "SELECT course_id FROM {$quiz_table} WHERE id = %d",
                $question->quiz_id
            ));

            $course_id = $quiz ? $quiz->course_id : 0;
            $ai_suggested_sections = [];

            if ($course_id && function_exists('quiz_ai_pro_get_course_sections') && function_exists('quiz_ai_pro_select_relevant_sections')) {
                $available_sections = quiz_ai_pro_get_course_sections($course_id);
                error_log('Quiz IA Pro Debug: Retrieved sections for course ' . $course_id . ': ' . print_r($available_sections, true));

                if (!empty($available_sections)) {
                    $ai_suggested_sections = quiz_ai_pro_select_relevant_sections(
                        $question->question_text,
                        $question->explanation ?? '',
                        $available_sections,
                        $is_correct,
                        3
                    );
                    error_log('Quiz IA Pro Debug: AI selected sections: ' . print_r($ai_suggested_sections, true));
                }
            }
        } else {
            // For multiple choice questions
            $user_index = is_numeric($user_answer) ? absint($user_answer) : null;
            $user_answer_text = ($user_index !== null && isset($options[$user_index])) ? $options[$user_index] : '';
            $is_correct = ($user_index === $correct_answer_index);

            // Get correct answer text
            $correct_answer_text = ($correct_answer_index !== null && isset($options[$correct_answer_index]))
                ? $options[$correct_answer_index]
                : 'Réponse correcte non disponible';

            // Use question's actual explanation
            $ai_feedback = !empty($question->explanation) ? $question->explanation : ($is_correct ? 'Correct' : 'Incorrect');
            $ai_score = $is_correct ? 100 : 0;

            // Get course sections and let AI select relevant ones
            $quiz_table = $wpdb->prefix . 'quiz_ia_quizzes';
            $quiz = $wpdb->get_row($wpdb->prepare(
                "SELECT course_id FROM {$quiz_table} WHERE id = %d",
                $question->quiz_id
            ));

            $course_id = $quiz ? $quiz->course_id : 0;
            $ai_suggested_sections = [];

            if ($course_id && function_exists('quiz_ai_pro_get_course_sections') && function_exists('quiz_ai_pro_select_relevant_sections')) {
                $available_sections = quiz_ai_pro_get_course_sections($course_id);
                error_log('Quiz IA Pro Debug: Retrieved sections for course ' . $course_id . ': ' . print_r($available_sections, true));

                if (!empty($available_sections)) {
                    $ai_suggested_sections = quiz_ai_pro_select_relevant_sections(
                        $question->question_text,
                        $question->explanation ?? '',
                        $available_sections,
                        $is_correct,
                        $is_correct ? 2 : 3  // Fewer sections if correct, more if incorrect
                    );
                    error_log('Quiz IA Pro Debug: AI selected sections: ' . print_r($ai_suggested_sections, true));
                }
            }
        }

        $details[] = array(
            'question' => wp_kses_post($question->question_text),
            'type' => sanitize_text_field($question->question_type ?: 'qcm'),
            'user_answer' => ($question->question_type === 'fill_blank' || $question->question_type === 'text_a_completer')
                ? (isset($user_answers_array) ? $user_answers_array : [])  // Always use array for fill-in-blank
                : wp_kses_post($user_answer_text), // String for other types
            'correct_answer' => ($question->question_type === 'fill_blank' || $question->question_type === 'text_a_completer')
                ? (isset($expected_answers) ? $expected_answers : [])  // Array for fill-in-the-blank
                : (($question->question_type === 'text' || $question->question_type === 'essay' || $question->question_type === 'open')
                    ? ($ai_feedback ? $ai_feedback : __('Évaluée par IA', 'quiz-ai-pro'))  // For text/essay/open, show AI feedback instead
                    : (($correct_answer_index !== null && isset($options[$correct_answer_index])) ?
                        wp_kses_post($options[$correct_answer_index]) : __('Réponse libre', 'quiz-ai-pro'))),
            'expected_answers' => ($question->question_type === 'fill_blank' || $question->question_type === 'text_a_completer')
                ? (isset($expected_answers) ? $expected_answers : [])  // For frontend display
                : null,
            'is_correct' => $is_correct,  // Make sure this matches the frontend check
            'correct' => $is_correct,
            'ai_feedback' => $ai_feedback,
            'ai_score' => $ai_score,
            'ai_course_reference' => $ai_course_reference,
            'ai_suggested_sections' => $ai_suggested_sections,
            'explanation' => wp_kses_post($question->explanation ?? ''),
            'course_slug' => 'power-bi-exam-certification-preparation', // Default course slug
            'enhanced_feedback' => $enhanced_feedback,
            'enhanced_feedback_html' => $enhanced_feedback_html
        );

        if ($is_correct) {
            $score++;
        }
    }

    $percentage = count($questions) > 0 ? round(($score / count($questions)) * 100, 2) : 0;

    return array(
        'score' => $score,
        'total' => count($questions),
        'details' => $details,
        'percentage' => $percentage
    );
}

/**
 * Save quiz attempt securely
 */
function quiz_ai_pro_save_attempt_securely($quiz_id, $scoring_result, $user_info, $quiz_duration)
{
    global $wpdb;
    $current_user = wp_get_current_user();

    // Use contact form data if available, otherwise fall back to user data
    if ($user_info && is_array($user_info)) {
        $user_email = sanitize_email($user_info['email']) ?: 'anonymous@example.com';
        $user_name = sanitize_text_field($user_info['name']) ?: 'Anonymous';
    } else {
        $user_email = $current_user->user_email ?: 'anonymous@example.com';
        $user_name = $current_user->display_name ?: 'Anonymous';
    }

    $user_id = $current_user->ID ?: 0;

    // Prepare attempt data securely
    $results_table = $wpdb->prefix . 'quiz_ia_results';
    $attempt_data = array(
        'quiz_id' => absint($quiz_id),
        'user_email' => $user_email,
        'user_name' => $user_name,
        'user_id' => $user_id,
        'score' => absint($scoring_result['score']),
        'total_questions' => absint($scoring_result['total']),
        'correct_answers' => absint($scoring_result['score']),
        'time_taken' => absint($quiz_duration),
        'percentage' => floatval($scoring_result['percentage']),
        'status' => 'completed',
        'answers_data' => wp_json_encode($scoring_result['details']),
        'started_at' => current_time('mysql'),
        'completed_at' => current_time('mysql')
    );

    // Store additional user info securely if provided
    if ($user_info && is_array($user_info) && !empty($user_info['phone'])) {
        $contact_info = array(
            'name' => sanitize_text_field($user_info['name']),
            'email' => sanitize_email($user_info['email']),
            'phone' => sanitize_text_field($user_info['phone'])
        );
        $scoring_result['details']['contact_info'] = $contact_info;
        $attempt_data['answers_data'] = wp_json_encode($scoring_result['details']);
    }

    // Insert with proper formatting
    $insert_result = $wpdb->insert(
        $results_table,
        $attempt_data,
        array('%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s')
    );

    if ($insert_result === false) {
        error_log('Quiz IA Pro: Database insert failed: ' . $wpdb->last_error);
        return false;
    }

    return $wpdb->insert_id;
}

/**
 * Get user's previous attempts for a quiz - Enhanced Security Version
 */
function quiz_ai_pro_get_user_attempts()
{
    // Rate limiting for attempts requests
    if (!QuizAIProSecurity::check_rate_limit('get_user_attempts', 15, 60)) {
        return;
    }

    // Enhanced security verification
    if (!QuizAIProSecurity::verify_nonce_and_permission('get_user_attempts')) {
        return;
    }

    // Input validation
    if (!isset($_POST['quiz_id'])) {
        QuizAIProSecurity::log_security_event('missing_quiz_id_for_attempts', $_POST);
        wp_send_json_error('Missing quiz ID');
        return;
    }

    $quiz_id = absint($_POST['quiz_id']);
    if (!$quiz_id) {
        QuizAIProSecurity::log_security_event('invalid_quiz_id_for_attempts', array(
            'provided_id' => $_POST['quiz_id']
        ));
        wp_send_json_error('Invalid quiz ID');
        return;
    }

    global $wpdb;
    $current_user = wp_get_current_user();
    $results_table = $wpdb->prefix . 'quiz_ia_results';

    try {
        // Start database transaction
        $wpdb->query('START TRANSACTION');

        // Get user's attempts for this quiz securely
        if ($current_user->ID) {
            // Logged in user
            $attempts = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$results_table} WHERE quiz_id = %d AND user_id = %d ORDER BY completed_at DESC LIMIT 5",
                $quiz_id,
                $current_user->ID
            ));
        } else {
            // Anonymous user - use email if provided and validated
            $user_email = '';
            if (isset($_POST['user_email'])) {
                $user_email = sanitize_email($_POST['user_email']);
                if (!is_email($user_email)) {
                    throw new Exception('Invalid email format');
                }
            }

            if (!empty($user_email)) {
                $attempts = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$results_table} WHERE quiz_id = %d AND user_email = %s ORDER BY completed_at DESC LIMIT 5",
                    $quiz_id,
                    $user_email
                ));
            } else {
                $attempts = array();
            }
        }

        // Commit transaction
        $wpdb->query('COMMIT');

        if (empty($attempts)) {
            wp_send_json_success(array(
                'has_attempts' => false,
                'attempts' => array()
            ));
            return;
        }

        // Format attempts for display securely
        $formatted_attempts = quiz_ai_pro_format_attempts_securely($attempts);

        // Log successful access
        QuizAIProSecurity::log_security_event('user_attempts_accessed', array(
            'quiz_id' => $quiz_id,
            'attempts_count' => count($attempts),
            'user_id' => $current_user->ID
        ));

        wp_send_json_success(array(
            'has_attempts' => true,
            'attempts' => $formatted_attempts
        ));
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        QuizAIProSecurity::log_security_event('user_attempts_error', array(
            'quiz_id' => $quiz_id,
            'error' => $e->getMessage(),
            'user_id' => get_current_user_id()
        ));
        error_log('Quiz IA Pro Attempts Error: ' . $e->getMessage());
        wp_send_json_error('Error loading user attempts');
    }
}

/**
 * Format attempts data securely for frontend display
 */
function quiz_ai_pro_format_attempts_securely($attempts)
{
    $formatted_attempts = array();

    foreach ($attempts as $attempt) {
        $answers_data = json_decode($attempt->answers_data, true);

        // Sanitize attempt data
        $formatted_attempt = array(
            'id' => absint($attempt->id),
            'score' => absint($attempt->score),
            'total' => absint($attempt->total_questions),
            'percentage' => floatval($attempt->percentage),
            'completed_at' => sanitize_text_field($attempt->completed_at),
            'time_taken' => absint($attempt->time_taken),
            'details' => array()
        );

        // Sanitize answers data if available
        if (is_array($answers_data)) {
            $sanitized_details = array();
            foreach ($answers_data as $key => $detail) {
                if ($key === 'contact_info') {
                    // Skip contact info for privacy
                    continue;
                }

                if (is_array($detail)) {
                    $sanitized_detail = array();
                    foreach ($detail as $detail_key => $detail_value) {
                        // Handle different types of detail values
                        if (is_array($detail_value)) {
                            // For arrays (like fill-in-the-blank answers), sanitize each element
                            $sanitized_detail[sanitize_key($detail_key)] = array_map('sanitize_text_field', $detail_value);
                        } elseif (is_string($detail_value)) {
                            // For strings, use wp_kses_post
                            $sanitized_detail[sanitize_key($detail_key)] = wp_kses_post($detail_value);
                        } else {
                            // For other types (numbers, booleans), convert to string first
                            $sanitized_detail[sanitize_key($detail_key)] = sanitize_text_field(strval($detail_value));
                        }
                    }
                    $sanitized_details[] = $sanitized_detail;
                }
            }
            $formatted_attempt['details'] = $sanitized_details;
        }

        $formatted_attempts[] = $formatted_attempt;
    }

    return $formatted_attempts;
}

// Register ALL AJAX handlers
add_action('wp_ajax_get_quizzes_by_category', 'quiz_ai_pro_ajax_get_quizzes_by_category');
add_action('wp_ajax_nopriv_get_quizzes_by_category', 'quiz_ai_pro_ajax_get_quizzes_by_category');
add_action('wp_ajax_get_quiz_details', 'quiz_ai_pro_get_quiz_details');
add_action('wp_ajax_nopriv_get_quiz_details', 'quiz_ai_pro_get_quiz_details');
add_action('wp_ajax_submit_quiz_answers', 'quiz_ai_pro_submit_quiz_answers');
add_action('wp_ajax_nopriv_submit_quiz_answers', 'quiz_ai_pro_submit_quiz_answers');
add_action('wp_ajax_get_user_attempts', 'quiz_ai_pro_get_user_attempts');
add_action('wp_ajax_nopriv_get_user_attempts', 'quiz_ai_pro_get_user_attempts');
