<?php

/**
 * Email Functions for Quiz IA Pro
 * Handles email notifications and preferences
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Save or update email preferences for a user
 */
function quiz_ai_pro_save_email_preferences($email, $preferences = [])
{
    error_log('Quiz IA Pro Email Debug: Saving email preferences for: ' . $email);
    error_log('Quiz IA Pro Email Debug: Preferences data: ' . print_r($preferences, true));

    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_email_preferences';

    $email = sanitize_email($email);
    if (!is_email($email)) {
        error_log('Quiz IA Pro Email Debug: Invalid email format: ' . $email);
        return false;
    }

    $defaults = [
        'user_name' => '',
        'user_id' => 0,
        'receive_quiz_results' => true,
        'receive_new_quiz_alerts' => true,
        'quiz_id' => null,
        'preferences_json' => json_encode([])
    ];

    $preferences = wp_parse_args($preferences, $defaults);

    // Check if preferences already exist
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE user_email = %s AND (quiz_id = %d OR quiz_id IS NULL)",
        $email,
        $preferences['quiz_id'] ?? 0
    ));

    if ($existing) {
        // Update existing preferences
        $result = $wpdb->update(
            $table_name,
            [
                'user_name' => sanitize_text_field($preferences['user_name']),
                'user_id' => intval($preferences['user_id']),
                'receive_quiz_results' => intval($preferences['receive_quiz_results']),
                'receive_new_quiz_alerts' => intval($preferences['receive_new_quiz_alerts']),
                'preferences_json' => wp_json_encode($preferences),
                'updated_at' => current_time('mysql')
            ],
            [
                'user_email' => $email,
                'quiz_id' => $preferences['quiz_id']
            ]
        );
    } else {
        // Insert new preferences
        $result = $wpdb->insert(
            $table_name,
            [
                'user_email' => $email,
                'user_name' => sanitize_text_field($preferences['user_name']),
                'user_id' => intval($preferences['user_id']),
                'receive_quiz_results' => intval($preferences['receive_quiz_results']),
                'receive_new_quiz_alerts' => intval($preferences['receive_new_quiz_alerts']),
                'quiz_id' => $preferences['quiz_id'],
                'preferences_json' => wp_json_encode($preferences)
            ]
        );
    }

    $success = $result !== false;
    error_log('Quiz IA Pro Email Debug: Save result: ' . ($success ? 'SUCCESS' : 'FAILED'));
    if (!$success && $wpdb->last_error) {
        error_log('Quiz IA Pro Email Debug: Database error: ' . $wpdb->last_error);
    }

    return $success;
}

/**
 * Get email preferences for a user
 */
function quiz_ai_pro_get_email_preferences($email, $quiz_id = null)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_email_preferences';

    $email = sanitize_email($email);
    if (!is_email($email)) {
        return false;
    }

    $query = "SELECT * FROM {$table_name} WHERE user_email = %s";
    $params = [$email];

    if ($quiz_id !== null) {
        $query .= " AND quiz_id = %d";
        $params[] = intval($quiz_id);
    } else {
        $query .= " AND quiz_id IS NULL";
    }

    $query .= " ORDER BY created_at DESC LIMIT 1";

    $preferences = $wpdb->get_row($wpdb->prepare($query, $params));

    if ($preferences && $preferences->preferences_json) {
        $preferences->preferences_json = json_decode($preferences->preferences_json, true);
    }

    return $preferences;
}

/**
 * Send quiz result email to user
 */
function quiz_ai_pro_send_quiz_result_email($email, $quiz_data, $result_data)
{
    // Check if user wants to receive quiz result emails
    $preferences = quiz_ai_pro_get_email_preferences($email, $quiz_data->id);
    if ($preferences && !$preferences->receive_quiz_results) {
        return true; // User opted out, but return true to not show error
    }

    $user_name = $result_data['user_name'] ?? 'Utilisateur';
    $quiz_title = $quiz_data->title ?? 'Quiz';
    $score = $result_data['score'] ?? 0;
    $total = $result_data['total'] ?? 0;
    $percentage = $result_data['percentage'] ?? 0;

    $subject = sprintf('Résultats de votre quiz : %s', $quiz_title);

    $message = quiz_ai_pro_get_quiz_result_email_template([
        'user_name' => $user_name,
        'quiz_title' => $quiz_title,
        'score' => $score,
        'total' => $total,
        'percentage' => $percentage,
        'details' => $result_data['details'] ?? [],
        'course_recommendation' => $result_data['course_recommendation'] ?? null,
        'unsubscribe_link' => quiz_ai_pro_get_unsubscribe_link($email, 'quiz_results')
    ]);

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
    ];

    error_log('Quiz IA Pro Email Debug: About to send email');
    error_log('Quiz IA Pro Email Debug: To: ' . $email);
    error_log('Quiz IA Pro Email Debug: Subject: ' . $subject);
    error_log('Quiz IA Pro Email Debug: Headers: ' . print_r($headers, true));

    $mail_result = wp_mail($email, $subject, $message, $headers);

    error_log('Quiz IA Pro Email Debug: wp_mail result: ' . ($mail_result ? 'TRUE' : 'FALSE'));

    if (!$mail_result) {
        // Check for WordPress mail errors
        global $phpmailer;
        if (isset($phpmailer)) {
            error_log('Quiz IA Pro Email Debug: PHPMailer error: ' . $phpmailer->ErrorInfo);
        }
    }

    return $mail_result;
}

/**
 * Get quiz result email template
 */
function quiz_ai_pro_get_quiz_result_email_template($data)
{
    $template = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . esc_html($data['quiz_title']) . ' - Résultats</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .result-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #0073aa; }
            .score { font-size: 24px; font-weight: bold; color: #0073aa; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .unsubscribe { margin-top: 20px; }
            .unsubscribe a { color: #666; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Résultats de votre Quiz</h1>
            </div>
            <div class="content">
                <p>Bonjour ' . esc_html($data['user_name']) . ',</p>
                
                <p>Félicitations ! Vous avez terminé le quiz <strong>' . esc_html($data['quiz_title']) . '</strong>.</p>
                
                <div class="result-box">
                    <h3>Vos Résultats :</h3>
                    <p class="score">' . esc_html($data['score']) . '/' . esc_html($data['total']) . ' (' . esc_html($data['percentage']) . '%)</p>
                </div>';

    if (!empty($data['details']) && is_array($data['details'])) {
        $template .= '<div class="result-box"><h3>Détails par Question :</h3><ul>';
        foreach ($data['details'] as $index => $detail) {
            if (is_array($detail)) {
                $status = (isset($detail['is_correct']) && $detail['is_correct']) || (isset($detail['correct']) && $detail['correct']) ? '✅' : '❌';
                $question_number = $index + 1;
                $points = ((isset($detail['is_correct']) && $detail['is_correct']) || (isset($detail['correct']) && $detail['correct'])) ? 1 : 0;
                $max_points = 1;
                $template .= '<li>' . $status . ' Question ' . esc_html($question_number) . ': ' . esc_html($points) . '/' . esc_html($max_points) . ' points</li>';
            }
        }
        $template .= '</ul></div>';
    }

    if (!empty($data['course_recommendation'])) {
        $course_recommendation_text = '';
        if (is_array($data['course_recommendation'])) {
            // If it's an array, properly format it
            $formatted_recommendation = '';
            foreach ($data['course_recommendation'] as $item) {
                if (is_string($item)) {
                    $formatted_recommendation .= '<p>' . esc_html($item) . '</p>';
                }
            }
            $course_recommendation_text = $formatted_recommendation;
        } else {
            $course_recommendation_text = wp_kses_post($data['course_recommendation']);
        }

        $template .= '
                <div class="result-box">
                    <h3>Recommandation de Cours :</h3>
                    ' . $course_recommendation_text . '
                </div>';
    }

    // Add retake suggestion for low scores
    if (isset($data['percentage']) && $data['percentage'] < 70) {
        $template .= '
                <div class="result-box">
                    <h3>💡 Conseil :</h3>
                    <p>Votre score est inférieur à 70%. Nous vous recommandons de revoir les concepts et de refaire le quiz pour améliorer vos connaissances.</p>
                    <p style="text-align: center; margin-top: 15px;">
                        <a href="https://innovation.ma/categories-and-quizzes/" style="display: inline-block; background: #0073aa; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px;">Refaire le Quiz</a>
                    </p>
                </div>';
    }

    $template .= '
                <p>Merci d\'avoir participé à notre quiz !</p>
            </div>
            <div class="footer">
                <p>' . esc_html(get_bloginfo('name')) . '</p>
                <div class="unsubscribe">
                    <a href="' . esc_url($data['unsubscribe_link']) . '">Se désabonner des emails de résultats</a>
                </div>
            </div>
        </div>
    </body>
    </html>';

    return $template;
}

/**
 * Send new quiz alert email to subscribers
 */
function quiz_ai_pro_send_new_quiz_alert($quiz_data)
{
    error_log('Quiz IA Pro: New quiz alert triggered for quiz: ' . print_r($quiz_data, true));

    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_email_preferences';

    // Get all users who want new quiz alerts
    $subscribers = $wpdb->get_results(
        "SELECT DISTINCT user_email, user_name FROM {$table_name} WHERE receive_new_quiz_alerts = 1"
    );

    error_log('Quiz IA Pro: Found ' . count($subscribers) . ' subscribers for new quiz alerts');

    if (empty($subscribers)) {
        error_log('Quiz IA Pro: No subscribers found for new quiz alerts');
        return true; // No subscribers, but not an error
    }

    $quiz_title = $quiz_data['title'] ?? 'Nouveau Quiz';
    $quiz_description = $quiz_data['description'] ?? '';
    $quiz_url = 'https://innovation.ma/categories-and-quizzes/';

    $subject = 'Nouveau Quiz Disponible : ' . $quiz_title;

    foreach ($subscribers as $subscriber) {
        error_log('Quiz IA Pro: Sending new quiz alert to: ' . $subscriber->user_email);

        $message = quiz_ai_pro_get_new_quiz_email_template([
            'user_name' => $subscriber->user_name ?: 'Utilisateur',
            'quiz_title' => $quiz_title,
            'quiz_description' => $quiz_description,
            'quiz_url' => $quiz_url,
            'unsubscribe_link' => quiz_ai_pro_get_unsubscribe_link($subscriber->user_email, 'new_quiz_alerts')
        ]);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];

        $result = wp_mail($subscriber->user_email, $subject, $message, $headers);
        error_log('Quiz IA Pro: New quiz email sent to ' . $subscriber->user_email . ': ' . ($result ? 'SUCCESS' : 'FAILED'));
    }

    return true;
}

/**
 * Get new quiz alert email template
 */
function quiz_ai_pro_get_new_quiz_email_template($data)
{
    $template = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Nouveau Quiz Disponible</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .quiz-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #0073aa; }
            .cta-button { display: inline-block; background: #ffffffff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 15px 0; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .unsubscribe { margin-top: 20px; }
            .unsubscribe a { color: #666; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🎯 Nouveau Quiz Disponible !</h1>
            </div>
            <div class="content">
                <p>Bonjour ' . esc_html($data['user_name']) . ',</p>
                
                <p>Un nouveau quiz vient d\'être publié et nous pensons qu\'il vous intéressera !</p>
                
                <div class="quiz-box">
                    <h3>' . esc_html($data['quiz_title']) . '</h3>';

    if (!empty($data['quiz_description'])) {
        $template .= '<p>' . wp_kses_post($data['quiz_description']) . '</p>';
    }

    $template .= '
                </div>
                
                <p style="text-align: center;">
                    <a href="' . esc_url($data['quiz_url']) . '" class="cta-button">Commencer le Quiz</a>
                </p>
                
                <p>Bonne chance et amusez-vous bien !</p>
            </div>
            <div class="footer">
                <p>' . esc_html(get_bloginfo('name')) . '</p>
                <div class="unsubscribe">
                    <a href="' . esc_url($data['unsubscribe_link']) . '">Se désabonner des alertes de nouveaux quiz</a>
                </div>
            </div>
        </div>
    </body>
    </html>';

    return $template;
}

/**
 * Generate unsubscribe link
 */
function quiz_ai_pro_get_unsubscribe_link($email, $type = 'all')
{
    $token = wp_hash($email . $type . 'quiz_ia_unsubscribe');
    // Update: use the new unsubscribe page slug
    return home_url('/desinscription-quiz/?email=' . urlencode($email) . '&type=' . $type . '&token=' . $token);
}

/**
 * Handle unsubscribe requests
 */
function quiz_ai_pro_handle_unsubscribe()
{
    if (!isset($_GET['email']) || !isset($_GET['type']) || !isset($_GET['token'])) {
        return false;
    }

    $email = sanitize_email($_GET['email']);
    $type = sanitize_text_field($_GET['type']);
    $token = sanitize_text_field($_GET['token']);

    // Verify token
    $expected_token = wp_hash($email . $type . 'quiz_ia_unsubscribe');
    if ($token !== $expected_token) {
        return false;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_email_preferences';

    $update_data = [];
    if ($type === 'quiz_results') {
        $update_data['receive_quiz_results'] = 0;
    } elseif ($type === 'new_quiz_alerts') {
        $update_data['receive_new_quiz_alerts'] = 0;
    } elseif ($type === 'all') {
        $update_data = [
            'receive_quiz_results' => 0,
            'receive_new_quiz_alerts' => 0
        ];
    }

    if (empty($update_data)) {
        return false;
    }

    $result = $wpdb->update(
        $table_name,
        $update_data,
        ['user_email' => $email]
    );

    return $result !== false;
}

/**
 * Get email statistics
 */
function quiz_ai_pro_get_email_stats()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_email_preferences';

    return [
        'total_subscribers' => $wpdb->get_var("SELECT COUNT(DISTINCT user_email) FROM {$table_name}"),
        'quiz_result_subscribers' => $wpdb->get_var("SELECT COUNT(DISTINCT user_email) FROM {$table_name} WHERE receive_quiz_results = 1"),
        'new_quiz_subscribers' => $wpdb->get_var("SELECT COUNT(DISTINCT user_email) FROM {$table_name} WHERE receive_new_quiz_alerts = 1")
    ];
}

/**
 * Clean up old email preferences (optional maintenance function)
 */
function quiz_ai_pro_cleanup_email_preferences($days = 365)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_email_preferences';

    return $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table_name} WHERE updated_at < DATE_SUB(NOW(), INTERVAL %d DAY) AND receive_quiz_results = 0 AND receive_new_quiz_alerts = 0",
        intval($days)
    ));
}
