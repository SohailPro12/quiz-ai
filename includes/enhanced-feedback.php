<?php

/**
 * Enhanced Feedback System for Quiz IA Pro
 * 
 * This file contains the enhanced feedback system that integrates
 * course sections and provides detailed feedback for both QCM and open questions.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate enhanced feedback for QCM questions
 * This replaces the basic correct/incorrect feedback with detailed course references
 */
function quiz_ai_pro_generate_qcm_enhanced_feedback($question_data, $is_correct, $user_answer, $correct_answer, $course_id)
{
    // Handle course_id if it's a JSON array (common format in quiz data)
    if (is_string($course_id) && (strpos($course_id, '[') === 0)) {
        $course_ids = json_decode($course_id, true);
        $course_id = is_array($course_ids) && !empty($course_ids) ? $course_ids[0] : 0;
    } elseif (is_array($course_id)) {
        $course_id = !empty($course_id) ? $course_id[0] : 0;
    }

    // Get real course information
    $course_slug = '';
    $course_title = '';

    if (!empty($course_id)) {
        $course_post = get_post($course_id);
        if ($course_post && $course_post->post_type === 'lp_course') {
            $course_slug = $course_post->post_name;
            $course_title = $course_post->post_title;
        }
    }

    // If no real course found, try to get from current quiz context
    if (empty($course_slug)) {
        global $wpdb;
        $quiz_table = $wpdb->prefix . 'quiz_ia_quizzes';
        $quiz = $wpdb->get_row("SELECT course_id FROM {$quiz_table} ORDER BY id DESC LIMIT 1");

        if ($quiz && !empty($quiz->course_id)) {
            // Handle JSON format course_id
            $quiz_course_id = $quiz->course_id;
            if (is_string($quiz_course_id) && (strpos($quiz_course_id, '[') === 0)) {
                $course_ids = json_decode($quiz_course_id, true);
                $quiz_course_id = is_array($course_ids) && !empty($course_ids) ? $course_ids[0] : 0;
            }

            $course_post = get_post($quiz_course_id);
            if ($course_post && $course_post->post_type === 'lp_course') {
                $course_slug = $course_post->post_name;
                $course_title = $course_post->post_title;
                $course_id = $quiz_course_id;
            }
        }
    }

    // Final fallback to Power BI course (ID 76)
    if (empty($course_slug)) {
        $course_slug = 'power-bi-exam-certification-preparation';
        $course_title = 'Power BI exam, certification preparation';
        $course_id = 76;
    }

    // Get real course sections from LearnPress
    $section_names = [];
    if (function_exists('quiz_ai_pro_get_course_sections') && !empty($course_id)) {
        $section_names = quiz_ai_pro_get_course_sections($course_id);
    }

    // If no real sections found, try to get lessons as sections
    if (empty($section_names) && !empty($course_id)) {
        global $wpdb;

        // Try to get lessons from this course
        $lessons = $wpdb->get_results($wpdb->prepare(
            "SELECT post_title FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key = '_lp_course' 
             AND pm.meta_value = %d 
             AND p.post_type = 'lp_lesson' 
             AND p.post_status = 'publish'
             ORDER BY menu_order ASC",
            $course_id
        ));

        foreach ($lessons as $lesson) {
            $section_names[] = $lesson->post_title;
        }
    }

    // Fallback sections based on course
    if (empty($section_names)) {
        if ($course_id == 34 || strpos($course_slug, 'dashboard') !== false) {
            // Sales dashboard course sections
            $section_names = [
                'Steps to Build the Project',
                'Define the Scope',
                'Key Questions to Answer',
                'Dashboard Design Principles',
                'Data Visualization Techniques',
                'Performance Metrics and KPIs'
            ];
        } else {
            // Power BI course sections
            $section_names = [
                'Preparation approach',
                'Preparation material',
                'Power BI Fundamentals',
                'Data Modeling',
                'Report Creation',
                'Dashboard Development'
            ];
        }
    }

    // Randomly select sections based on correctness
    shuffle($section_names);
    $num_sections = $is_correct ? 2 : 3;
    $selected_sections = array_slice($section_names, 0, min($num_sections, count($section_names)));

    // Build feedback structure (without course reference in individual questions)
    $feedback_data = [
        'is_correct' => $is_correct,
        'explanation' => $question_data['explanation'] ?? 'Explication non disponible.',
        'correct_answer' => $correct_answer,
        'user_answer' => $user_answer,
        'suggested_sections' => $selected_sections,
        'course_title' => $course_title,
        'course_url' => "https://innovation.ma/cours/{$course_slug}/",
        'course_slug' => $course_slug,
        'course_id' => $course_id
    ];

    return $feedback_data;
}

/**
 * Generate enhanced feedback for open questions with course context
 */
function quiz_ai_pro_generate_open_enhanced_feedback($question_text, $user_answer, $expected_answer, $explanation, $course_info, $ai_score, $is_correct)
{
    $course_id = $course_info ? $course_info['course_id'] : 0;
    $course_slug = $course_info ? $course_info['slug'] : '';
    $course_title = $course_info ? $course_info['title'] : '';

    // Handle course_id if it's a JSON array
    if (is_string($course_id) && (strpos($course_id, '[') === 0)) {
        $course_ids = json_decode($course_id, true);
        $course_id = is_array($course_ids) && !empty($course_ids) ? $course_ids[0] : 0;
    } elseif (is_array($course_id)) {
        $course_id = !empty($course_id) ? $course_id[0] : 0;
    }

    // If no course info provided, try to get real course data
    if (empty($course_slug) || empty($course_title)) {
        global $wpdb;

        // Try to get from most recent quiz
        $quiz_table = $wpdb->prefix . 'quiz_ia_quizzes';
        $quiz = $wpdb->get_row("SELECT course_id FROM {$quiz_table} ORDER BY id DESC LIMIT 1");

        if ($quiz && !empty($quiz->course_id)) {
            // Handle JSON format course_id
            $quiz_course_id = $quiz->course_id;
            if (is_string($quiz_course_id) && (strpos($quiz_course_id, '[') === 0)) {
                $course_ids = json_decode($quiz_course_id, true);
                $quiz_course_id = is_array($course_ids) && !empty($course_ids) ? $course_ids[0] : 0;
            }

            $course_post = get_post($quiz_course_id);
            if ($course_post && $course_post->post_type === 'lp_course') {
                $course_slug = $course_post->post_name;
                $course_title = $course_post->post_title;
                $course_id = $quiz_course_id;
            }
        }
    }

    // Final fallback to real Power BI course (ID 76)
    if (empty($course_slug)) {
        $course_slug = 'power-bi-exam-certification-preparation';
        $course_title = 'Power BI exam, certification preparation';
        $course_id = 76;
    }

    // Get real course sections
    $section_names = [];
    if (function_exists('quiz_ai_pro_get_course_sections') && !empty($course_id)) {
        $section_names = quiz_ai_pro_get_course_sections($course_id);
    }

    // If no real sections found, try to get lessons
    if (empty($section_names) && !empty($course_id)) {
        global $wpdb;

        $lessons = $wpdb->get_results($wpdb->prepare(
            "SELECT post_title FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key = '_lp_course' 
             AND pm.meta_value = %d 
             AND p.post_type = 'lp_lesson' 
             AND p.post_status = 'publish'
             ORDER BY menu_order ASC",
            $course_id
        ));

        foreach ($lessons as $lesson) {
            $section_names[] = $lesson->post_title;
        }
    }

    // Fallback sections based on the specific course
    if (empty($section_names)) {
        if ($course_id == 34 || strpos($course_slug, 'dashboard') !== false) {
            // Sales dashboard course sections
            $section_names = [
                'Steps to Build the Project',
                'Define the Scope',
                'Key Questions to Answer',
                'Dashboard Design Principles',
                'Data Visualization Techniques',
                'Performance Metrics and KPIs'
            ];
        } else {
            // Power BI course sections
            $section_names = [
                'Preparation approach',
                'Preparation material',
                'Power BI Fundamentals',
                'Data Modeling',
                'Report Creation',
                'Dashboard Development'
            ];
        }
    }

    // Select sections based on performance
    shuffle($section_names);
    $num_sections = ($ai_score >= 70) ? 2 : 3;
    $selected_sections = array_slice($section_names, 0, min($num_sections, count($section_names)));

    return [
        'is_correct' => $is_correct,
        'ai_score' => $ai_score,
        'suggested_sections' => $selected_sections,
        'course_title' => $course_title,
        'course_url' => "https://innovation.ma/cours/{$course_slug}/",
        'course_slug' => $course_slug,
        'course_id' => $course_id
    ];
}

/**
 * Render enhanced QCM feedback HTML
 */
function quiz_ai_pro_render_qcm_feedback($feedback_data)
{
    if (empty($feedback_data)) {
        return '';
    }

    $is_correct = $feedback_data['is_correct'];
    $status_class = $is_correct ? 'correct' : 'incorrect';
    $status_icon = $is_correct ? '✅' : '❌';
    $status_text = $is_correct ? 'Correct' : 'Incorrect';

    $html = '<div class="enhanced-qcm-feedback ' . $status_class . '">';

    // Status header
    $html .= '<div class="feedback-status">' . $status_icon . ' ' . $status_text . '</div>';

    // Show correct answer if incorrect
    if (!$is_correct) {
        $html .= '<div class="correct-answer-info">';
        $html .= '<strong>Bonne réponse:</strong> ' . esc_html($feedback_data['correct_answer']);
        $html .= '</div>';
    }

    // Explanation
    if (!empty($feedback_data['explanation'])) {
        $html .= '<div class="explanation">';
        $html .= '<strong>Explication:</strong> ' . esc_html($feedback_data['explanation']);
        $html .= '</div>';
    }

    // Suggested sections
    if (!empty($feedback_data['suggested_sections'])) {
        $html .= '<div class="suggested-sections">';
        if ($is_correct) {
            $html .= '<p><strong>Pour approfondir vos connaissances, consultez ces sections :</strong></p>';
        } else {
            $html .= '<p><strong>Vous devriez réviser davantage ces sections :</strong></p>';
        }

        $html .= '<ul>';
        foreach ($feedback_data['suggested_sections'] as $section) {
            $html .= '<li>' . esc_html($section) . '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Render enhanced open question feedback HTML
 */
function quiz_ai_pro_render_open_feedback($ai_feedback, $enhanced_data)
{
    if (empty($enhanced_data)) {
        return '<div class="ai-feedback">' . esc_html($ai_feedback) . '</div>';
    }

    $ai_score = $enhanced_data['ai_score'];
    $performance_class = '';
    if ($ai_score >= 80) {
        $performance_class = 'excellent';
    } elseif ($ai_score >= 60) {
        $performance_class = 'good';
    } else {
        $performance_class = 'needs-improvement';
    }

    $html = '<div class="enhanced-open-feedback ' . $performance_class . '">';

    // AI Feedback
    $html .= '<div class="ai-evaluation">';
    $html .= '<h4>Évaluation IA</h4>';
    $html .= '<div class="ai-feedback-content">' . esc_html($ai_feedback) . '</div>';
    $html .= '<div class="ai-score">Score: ' . $ai_score . '%</div>';
    $html .= '</div>';

    // Suggested sections
    if (!empty($enhanced_data['suggested_sections'])) {
        $html .= '<div class="suggested-sections">';
        if ($ai_score >= 70) {
            $html .= '<p><strong>Pour approfondir vos connaissances, consultez ces sections :</strong></p>';
        } else {
            $html .= '<p><strong>Vous devriez réviser davantage ces sections :</strong></p>';
        }

        $html .= '<ul>';
        foreach ($enhanced_data['suggested_sections'] as $section) {
            $html .= '<li>' . esc_html($section) . '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Update the quiz result processing to include enhanced feedback
 */
function quiz_ai_pro_process_enhanced_feedback($question, $user_answer, $options, $correct_answer_index, $quiz, $i)
{
    $question_type = $question->question_type ?: 'qcm';
    $is_correct = false;
    $user_answer_text = '';
    $ai_feedback = null;
    $ai_score = null;
    $enhanced_feedback = null;
    $ai_course_reference = '';
    $ai_suggested_sections = [];

    // Get course ID from quiz - handle JSON array format
    $course_id = $quiz->course_id ?? 0;

    // Handle JSON format course_id from quiz data
    if (is_string($course_id) && (strpos($course_id, '[') === 0)) {
        $course_ids = json_decode($course_id, true);
        $course_id = is_array($course_ids) && !empty($course_ids) ? $course_ids[0] : 0;
    } elseif (is_array($course_id)) {
        $course_id = !empty($course_id) ? $course_id[0] : 0;
    }

    if ($question_type === 'open' || $question_type === 'text') {
        // Handle open questions (existing AI scoring)
        $user_answer_text = is_string($user_answer) ? wp_kses_post(trim($user_answer)) : '';

        if (empty($user_answer_text)) {
            $is_correct = false;
            $ai_feedback = __('Aucune réponse fournie', 'quiz-ai-pro');
            $ai_score = 0;
        } else {
            // Get expected answer
            $expected_answer = '';
            if (!empty($options) && $correct_answer_index !== null && isset($options[$correct_answer_index])) {
                $expected_answer = $options[$correct_answer_index];
            }

            // Prepare course info for AI
            $course_info = null;
            if ($course_id) {
                $course_post = get_post($course_id);
                if ($course_post) {
                    $course_info = array(
                        'title' => $course_post->post_title,
                        'slug' => $course_post->post_name,
                        'course_id' => $course_id
                    );
                }
            }

            // Use AI to score the open question
            if (function_exists('quiz_ai_pro_score_open_question')) {
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

                // Generate enhanced feedback for open questions
                $enhanced_feedback = quiz_ai_pro_generate_open_enhanced_feedback(
                    wp_kses_post($question->question_text),
                    $user_answer_text,
                    $expected_answer,
                    wp_kses_post($question->explanation ?: ''),
                    $course_info,
                    $ai_score,
                    $is_correct
                );
            } else {
                $is_correct = !empty($user_answer_text);
                $ai_feedback = __('Réponse enregistrée', 'quiz-ai-pro');
                $ai_score = $is_correct ? 100 : 0;
            }
        }
    } else {
        // Handle QCM questions with enhanced feedback
        $user_index = is_numeric($user_answer) ? absint($user_answer) : null;
        $user_answer_text = ($user_index !== null && isset($options[$user_index])) ? $options[$user_index] : '';
        $is_correct = ($user_index === $correct_answer_index);

        // Get correct answer text
        $correct_answer_text = ($correct_answer_index !== null && isset($options[$correct_answer_index]))
            ? $options[$correct_answer_index]
            : 'Réponse correcte non disponible';

        // Generate enhanced feedback for QCM
        $question_data = [
            'explanation' => $question->explanation ?? ''
        ];

        $enhanced_feedback = quiz_ai_pro_generate_qcm_enhanced_feedback(
            $question_data,
            $is_correct,
            $user_answer_text,
            $correct_answer_text,
            $course_id
        );

        // Set AI feedback fields for consistency
        $ai_feedback = $enhanced_feedback['explanation'];
        $ai_score = $is_correct ? 100 : 0;
        $ai_course_reference = $enhanced_feedback['course_reference'];
        $ai_suggested_sections = $enhanced_feedback['suggested_sections'];
    }

    return [
        'question' => wp_kses_post($question->question_text),
        'type' => sanitize_text_field($question_type),
        'user_answer' => wp_kses_post($user_answer_text),
        'correct_answer' => ($correct_answer_index !== null && isset($options[$correct_answer_index])) ?
            wp_kses_post($options[$correct_answer_index]) : __('Réponse libre', 'quiz-ai-pro'),
        'correct' => $is_correct,
        'ai_feedback' => $ai_feedback,
        'ai_score' => $ai_score,
        'ai_course_reference' => $ai_course_reference,
        'ai_suggested_sections' => $ai_suggested_sections,
        'enhanced_feedback' => $enhanced_feedback,
        'explanation' => $question->explanation ?? ''
    ];
}

/**
 * Generate overall course recommendation for quiz results
 * This appears once at the end of the quiz, not after each question
 */
function quiz_ai_pro_generate_overall_course_recommendation($quiz, $overall_score, $total_questions)
{
    // Get course ID from quiz - handle JSON array format
    $course_id = $quiz->course_id ?? 0;

    // Handle JSON format course_id from quiz data
    if (is_string($course_id) && (strpos($course_id, '[') === 0)) {
        $course_ids = json_decode($course_id, true);
        $course_id = is_array($course_ids) && !empty($course_ids) ? $course_ids[0] : 0;
    } elseif (is_array($course_id)) {
        $course_id = !empty($course_id) ? $course_id[0] : 0;
    }

    // Get real course information
    $course_slug = '';
    $course_title = '';

    if (!empty($course_id)) {
        $course_post = get_post($course_id);
        if ($course_post && $course_post->post_type === 'lp_course') {
            $course_slug = $course_post->post_name;
            $course_title = $course_post->post_title;
        }
    }

    // Final fallback to Power BI course (ID 76)
    if (empty($course_slug)) {
        $course_slug = 'power-bi-exam-certification-preparation';
        $course_title = 'Power BI exam, certification preparation';
        $course_id = 76;
    }

    // Calculate percentage
    $percentage = ($total_questions > 0) ? round(($overall_score / $total_questions) * 100) : 0;

    // Generate recommendation based on performance
    $recommendation_text = '';
    if ($percentage >= 80) {
        $recommendation_text = "Pour approfondir vos connaissances sur ce sujet, consultez le cours";
    } elseif ($percentage >= 60) {
        $recommendation_text = "Pour consolider vos acquis, nous vous recommandons de consulter le cours";
    } else {
        $recommendation_text = "Pour mieux comprendre ces concepts, nous recommandons fortement de réviser le cours";
    }

    return [
        'course_title' => $course_title,
        'course_url' => "https://innovation.ma/cours/{$course_slug}/",
        'course_slug' => $course_slug,
        'course_id' => $course_id,
        'recommendation_text' => $recommendation_text,
        'percentage' => $percentage
    ];
}

/**
 * Render overall course recommendation HTML
 * This appears once at the end of quiz results
 */
function quiz_ai_pro_render_overall_course_recommendation($recommendation_data)
{
    if (empty($recommendation_data) || empty($recommendation_data['course_title'])) {
        return '';
    }

    $html = '<div class="quiz-overall-course-recommendation">';
    $html .= '<div class="course-recommendation-content">';

    // Course recommendation text with clickable link
    $html .= '<p>' . esc_html($recommendation_data['recommendation_text']) . ' ';
    $html .= '<a href="' . esc_url($recommendation_data['course_url']) . '" target="_blank" rel="noopener">';
    $html .= "'" . esc_html($recommendation_data['course_title']) . "'";
   // $html .= '</a> : ' . esc_url($recommendation_data['course_url']) . '</p>';

    // Clickable button
    $html .= '<p><a href="' . esc_url($recommendation_data['course_url']) . '" target="_blank" rel="noopener" class="course-access-button">🔗 Accéder au cours</a></p>';

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}
