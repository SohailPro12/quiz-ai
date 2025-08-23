<?php

/**
 * Helper Functions for Quiz IA Pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Format time duration in human readable format
 */
function quiz_ai_pro_format_duration($minutes)
{
    if ($minutes < 1) {
        return '< 1 min';
    } elseif ($minutes < 60) {
        return $minutes . ' min';
    } else {
        $hours = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;

        if ($remaining_minutes === 0) {
            return $hours . 'h';
        } else {
            return $hours . 'h ' . $remaining_minutes . 'min';
        }
    }
}

/**
 * Calculate percentage score
 */
function quiz_ai_pro_calculate_percentage($correct, $total)
{
    if ($total === 0) {
        return 0;
    }

    return round(($correct / $total) * 100, 1);
}

/**
 * Get performance level based on score
 */
function quiz_ai_pro_get_performance_level($percentage)
{
    if ($percentage >= 90) {
        return ['level' => 'excellent', 'label' => 'Excellent', 'color' => '#10b981'];
    } elseif ($percentage >= 80) {
        return ['level' => 'good', 'label' => 'Bien', 'color' => '#3b82f6'];
    } elseif ($percentage >= 60) {
        return ['level' => 'average', 'label' => 'Moyen', 'color' => '#f59e0b'];
    } else {
        return ['level' => 'needs_improvement', 'label' => 'À améliorer', 'color' => '#ef4444'];
    }
}

/**
 * Sanitize quiz data
 */
function quiz_ai_pro_sanitize_quiz_data($data)
{
    $sanitized = [];

    // Basic fields
    $sanitized['title'] = sanitize_text_field($data['title'] ?? '');
    $sanitized['description'] = sanitize_textarea_field($data['description'] ?? '');
    $sanitized['difficulty'] = sanitize_text_field($data['difficulty'] ?? 'moyen');
    $sanitized['time_limit'] = intval($data['time_limit'] ?? 0);
    $sanitized['pass_percentage'] = intval($data['pass_percentage'] ?? 60);
    $sanitized['max_attempts'] = intval($data['max_attempts'] ?? 3);
    $sanitized['show_results'] = (bool) ($data['show_results'] ?? true);
    $sanitized['randomize_questions'] = (bool) ($data['randomize_questions'] ?? false);
    $sanitized['status'] = sanitize_text_field($data['status'] ?? 'draft');

    // Questions
    if (isset($data['questions']) && is_array($data['questions'])) {
        $sanitized['questions'] = [];
        foreach ($data['questions'] as $question) {
            $sanitized['questions'][] = quiz_ai_pro_sanitize_question_data($question);
        }
    }

    return $sanitized;
}

/**
 * Sanitize question data
 */
function quiz_ai_pro_sanitize_question_data($question)
{
    $sanitized = [];

    $sanitized['type'] = sanitize_text_field($question['type'] ?? 'multiple_choice');
    $sanitized['question'] = sanitize_textarea_field($question['question'] ?? '');
    $sanitized['explanation'] = sanitize_textarea_field($question['explanation'] ?? '');
    $sanitized['points'] = intval($question['points'] ?? 1);

    // Type-specific fields
    switch ($sanitized['type']) {
        case 'multiple_choice':
            $sanitized['options'] = [];
            if (isset($question['options']) && is_array($question['options'])) {
                foreach ($question['options'] as $option) {
                    $sanitized['options'][] = sanitize_text_field($option);
                }
            }
            $sanitized['correct_answer'] = intval($question['correct_answer'] ?? 0);
            break;

        case 'true_false':
            $sanitized['correct_answer'] = (bool) ($question['correct_answer'] ?? false);
            break;

        case 'open_ended':
        case 'case_study':
            $sanitized['model_answer'] = sanitize_textarea_field($question['model_answer'] ?? '');
            break;
    }

    return $sanitized;
}

/**
 * Validate quiz data
 */
function quiz_ai_pro_validate_quiz_data($data)
{
    $errors = [];

    // Required fields
    if (empty($data['title'])) {
        $errors[] = 'Le titre du quiz est requis';
    }

    if (!isset($data['questions']) || empty($data['questions'])) {
        $errors[] = 'Au moins une question est requise';
    }

    // Validate questions
    if (isset($data['questions']) && is_array($data['questions'])) {
        foreach ($data['questions'] as $index => $question) {
            $question_errors = quiz_ai_pro_validate_question_data($question, $index + 1);
            $errors = array_merge($errors, $question_errors);
        }
    }

    // Validate numeric fields
    if (isset($data['time_limit']) && $data['time_limit'] < 0) {
        $errors[] = 'La limite de temps ne peut pas être négative';
    }

    if (isset($data['pass_percentage']) && ($data['pass_percentage'] < 0 || $data['pass_percentage'] > 100)) {
        $errors[] = 'Le pourcentage de réussite doit être entre 0 et 100';
    }

    if (isset($data['max_attempts']) && $data['max_attempts'] < 1) {
        $errors[] = 'Le nombre maximum de tentatives doit être d\'au moins 1';
    }

    return $errors;
}

/**
 * Validate question data
 */
function quiz_ai_pro_validate_question_data($question, $question_number)
{
    $errors = [];
    $prefix = "Question $question_number: ";

    // Required fields
    if (empty($question['question'])) {
        $errors[] = $prefix . 'Le texte de la question est requis';
    }

    if (empty($question['type'])) {
        $errors[] = $prefix . 'Le type de question est requis';
    }

    // Type-specific validation
    switch ($question['type'] ?? '') {
        case 'multiple_choice':
            if (!isset($question['options']) || !is_array($question['options']) || count($question['options']) < 2) {
                $errors[] = $prefix . 'Au moins 2 options sont requises pour les QCM';
            }

            if (!isset($question['correct_answer']) || $question['correct_answer'] < 0) {
                $errors[] = $prefix . 'Une réponse correcte doit être sélectionnée';
            }

            if (isset($question['options']) && is_array($question['options'])) {
                $correct_index = intval($question['correct_answer'] ?? 0);
                if ($correct_index >= count($question['options'])) {
                    $errors[] = $prefix . 'L\'index de la réponse correcte est invalide';
                }
            }
            break;

        case 'true_false':
            if (!isset($question['correct_answer'])) {
                $errors[] = $prefix . 'La réponse correcte (vrai/faux) doit être définie';
            }
            break;

        case 'open_ended':
        case 'case_study':
            // Ces types de questions peuvent ne pas avoir de réponse modèle obligatoire
            break;
    }

    return $errors;
}

/**
 * Generate unique slug
 */
function quiz_ai_pro_generate_slug($title, $table = 'quiz_ai_pro_quizzes')
{
    global $wpdb;

    $slug = sanitize_title($title);
    $original_slug = $slug;
    $counter = 1;

    do {
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}{$table} WHERE slug = %s",
            $slug
        ));

        if (!$existing) {
            break;
        }

        $slug = $original_slug . '-' . $counter;
        $counter++;
    } while ($counter < 100); // Safety limit

    return $slug;
}

/**
 * Send email notification
 */
function quiz_ai_pro_send_email($to, $subject, $message, $headers = [])
{
    // Default headers
    $default_headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_option('quiz_ai_pro_from_name', get_bloginfo('name')) . ' <' . get_option('quiz_ai_pro_from_email', get_option('admin_email')) . '>'
    ];

    $headers = array_merge($default_headers, $headers);

    // Email template
    $email_template = quiz_ai_pro_get_email_template();
    $formatted_message = str_replace('[CONTENT]', $message, $email_template);

    return wp_mail($to, $subject, $formatted_message, $headers);
}

/**
 * Get email template
 */
function quiz_ai_pro_get_email_template()
{
    $site_name = get_bloginfo('name');
    $site_url = home_url();

    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Quiz IA Pro</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
            .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
            .content { padding: 30px 20px; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .button { display: inline-block; background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Quiz IA Pro</h1>
            <p>' . esc_html($site_name) . '</p>
        </div>
        <div class="content">
            [CONTENT]
        </div>
        <div class="footer">
            <p>Cet email a été envoyé automatiquement par Quiz IA Pro</p>
            <p><a href="' . esc_url($site_url) . '">' . esc_html($site_name) . '</a></p>
        </div>
    </body>
    </html>';
}

/**
 * Log activity
 */
function quiz_ai_pro_log_activity($type, $message, $data = [])
{
    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix . 'quiz_ai_pro_activities',
        [
            'type' => $type,
            'message' => $message,
            'data' => json_encode($data),
            'user_id' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ],
        ['%s', '%s', '%s', '%d', '%s']
    );
}

/**
 * Clean expired data
 */
function quiz_ai_pro_cleanup_expired_data()
{
    global $wpdb;

    // Clean old activities (older than 30 days)
    $wpdb->query("
        DELETE FROM {$wpdb->prefix}quiz_ai_pro_activities 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

    // Clean old attempt data if configured
    $retention_days = get_option('quiz_ai_pro_data_retention_days', 0);
    if ($retention_days > 0) {
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}quiz_ai_pro_attempts 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $retention_days));
    }
}

/**
 * Export quiz to JSON
 */
function quiz_ai_pro_export_quiz($quiz_id)
{
    global $wpdb;

    $quiz = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}quiz_ai_pro_quizzes WHERE id = %d",
        $quiz_id
    ));

    if (!$quiz) {
        return false;
    }

    // Get related content from LearnPress if course_id exists
    $content = null;
    if ($quiz->course_id) {
        $content = $wpdb->get_row($wpdb->prepare(
            "SELECT ID, post_title as title, post_content as description, post_name as slug 
             FROM {$wpdb->prefix}posts 
             WHERE ID = %d AND post_type = 'lp_course'",
            $quiz->course_id
        ));
    }

    $export_data = [
        'quiz' => $quiz,
        'content' => $content,
        'export_date' => current_time('mysql'),
        'version' => '1.0'
    ];

    return json_encode($export_data, JSON_PRETTY_PRINT);
}

/**
 * Import quiz from JSON
 */
function quiz_ai_pro_import_quiz($json_data)
{
    $data = json_decode($json_data, true);

    if (!$data || !isset($data['quiz'])) {
        return new WP_Error('invalid_data', 'Données d\'import invalides');
    }

    global $wpdb;

    // Start transaction
    $wpdb->query('START TRANSACTION');

    try {
        $quiz = $data['quiz'];
        $content = $data['content'] ?? null;

        $content_id = null;

        // Import content if exists (create as LearnPress course)
        if ($content) {
            $course_post = array(
                'post_title'   => $content['title'],
                'post_content' => $content['description'] ?? '',
                'post_status'  => 'publish',
                'post_type'    => 'lp_course',
                'post_name'    => quiz_ai_pro_generate_slug($content['title'])
            );

            $content_id = wp_insert_post($course_post);

            if (!$content_id || is_wp_error($content_id)) {
                throw new Exception('Failed to create LearnPress course');
            }
        }

        // Import quiz
        unset($quiz['id']); // Remove ID to create new
        $quiz['course_id'] = $content_id;
        $quiz['slug'] = quiz_ai_pro_generate_slug($quiz['title']);
        $quiz['created_at'] = current_time('mysql');

        // Create format array for all fields to ensure proper database insertion
        $format = array();
        foreach ($quiz as $key => $value) {
            if (in_array($key, ['id', 'course_id', 'category_id', 'time_limit', 'questions_per_page', 'total_questions', 'ai_generated', 'views', 'participants', 'created_by'])) {
                $format[] = '%d'; // Integer fields
            } else {
                $format[] = '%s'; // String/text fields (including JSON)
            }
        }

        $wpdb->insert($wpdb->prefix . 'quiz_ia_quizzes', $quiz, $format);
        $quiz_id = $wpdb->insert_id;

        $wpdb->query('COMMIT');

        return $quiz_id;
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('import_failed', 'Échec de l\'import: ' . $e->getMessage());
    }
}

/**
 * Get system info for support
 */
function quiz_ai_pro_get_system_info()
{
    global $wpdb;

    return [
        'wordpress_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'mysql_version' => $wpdb->db_version(),
        'plugin_version' => QUIZ_AI_PRO_VERSION,
        'active_theme' => get_option('stylesheet'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
    ];
}

/**
 * Add admin notice
 */
function quiz_ai_pro_add_admin_notice($message, $type = 'success')
{
    $notices = get_transient('quiz_ai_pro_admin_notices') ?: [];
    $notices[] = ['message' => $message, 'type' => $type];
    set_transient('quiz_ai_pro_admin_notices', $notices, 60);
}

/**
 * Show admin notices
 */
function quiz_ai_pro_show_admin_notices()
{
    $notices = get_transient('quiz_ai_pro_admin_notices') ?: [];

    foreach ($notices as $notice) {
        $class = $notice['type'] === 'error' ? 'notice-error' : ($notice['type'] === 'warning' ? 'notice-warning' : 'notice-success');
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
    }

    delete_transient('quiz_ai_pro_admin_notices');
}
