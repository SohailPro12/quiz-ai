<?php
if (!defined('ABSPATH')) exit;

function quiz_ai_migrate_qsm_quizzes_page()
{
    global $wpdb;
    echo '<div class="wrap" style="max-width:700px;margin:40px auto;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.08);padding:40px;">';
    echo '<h1 style="margin-bottom:24px;font-size:2rem;color:#0073aa;display:flex;align-items:center;gap:12px;">Migrate QSM Quizzes</h1>';
    echo '<form method="post" style="margin-bottom:24px;">';
    echo '<button type="submit" name="quiz_ai_migrate_qsm" class="button button-primary" style="height:40px;padding:0 24px;font-size:1rem;border-radius:6px;background:#0073aa;color:#fff;">Migrate QSM Quizzes</button>';
    echo '</form>';

    if (isset($_POST['quiz_ai_migrate_qsm'])) {
        // Fetch QSM quizzes
        $qsm_quizzes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mlw_quizzes");
        $migrated = 0;
        $questions_migrated = 0;
        $answers_migrated = 0;
        if ($qsm_quizzes) {
            foreach ($qsm_quizzes as $quiz) {
                $title = $quiz->quiz_name;
                $description = isset($quiz->quiz_description) ? $quiz->quiz_description : '';
                // Map standard parameters from QSM to Quiz AI
                $quiz_params = [
                    'title' => $title,
                    'description' => $description,
                    'created_at' => current_time('mysql'),
                    'status' => 'draft',
                    'time_limit' => isset($quiz->timer_limit) ? intval($quiz->timer_limit) : 0,
                    'questions_per_page' => isset($quiz->pagination) ? intval($quiz->pagination) : 0,
                    'total_questions' => isset($quiz->question_from_total) ? intval($quiz->question_from_total) : 0,
                    'views' => isset($quiz->quiz_views) ? intval($quiz->quiz_views) : 0,
                    'participants' => isset($quiz->quiz_taken) ? intval($quiz->quiz_taken) : 0,
                    'form_type' => isset($quiz->quiz_system) ? $quiz->quiz_system : 'quiz',
                    'questions_per_page' => isset($quiz->pagination) ? intval($quiz->pagination) : 0,
                ];
                // Store extra settings as JSON
                $extra_settings = [
                    'show_contact_form' => !empty($quiz->loggedin_user_contact) ? boolval($quiz->loggedin_user_contact) : false,
                    'show_page_number' => !empty($quiz->question_numbering) ? boolval($quiz->question_numbering) : false,
                    'show_question_images' => false, // QSM does not have direct mapping
                    'show_progress_bar' => !empty($quiz->pagination) ? true : false,
                    'require_login' => !empty($quiz->require_log_in) ? true : false,
                    'disable_first_page' => !empty($quiz->disable_first_page) ? true : false,
                    'enable_comments' => !empty($quiz->comment_section) ? true : false,
                    'show_score' => !empty($quiz->show_score) ? true : false,
                    'send_user_email' => !empty($quiz->send_user_email) ? true : false,
                    'send_admin_email' => !empty($quiz->send_admin_email) ? true : false,
                    'limit_total_entries' => !empty($quiz->limit_total_entries) ? true : false,
                    'total_user_tries' => isset($quiz->total_user_tries) ? intval($quiz->total_user_tries) : 0,
                    'theme_selected' => isset($quiz->theme_selected) ? $quiz->theme_selected : '',
                    'quiz_style' => isset($quiz->quiz_stye) ? $quiz->quiz_stye : '',
                ];
                $quiz_params['settings'] = wp_json_encode($extra_settings);
                $wpdb->insert($wpdb->prefix . 'quiz_ia_quizzes', $quiz_params);
                $new_quiz_id = $wpdb->insert_id;

                // Fetch QSM questions for this quiz
                $qsm_questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mlw_questions WHERE quiz_id = %d", $quiz->quiz_id));
                if ($qsm_questions) {
                    foreach ($qsm_questions as $question) {
                        // Extract question text from question_settings
                        $settings = !empty($question->question_settings) ? @unserialize($question->question_settings) : [];
                        $question_text = '';
                        if (is_array($settings) && isset($settings['question_title'])) {
                            $question_text = $settings['question_title'];
                        }
                        // Fallback to question_name if needed
                        if (empty($question_text) && !empty($question->question_name)) {
                            $question_text = $question->question_name;
                        }
                        // Map QSM type to plugin equivalents, skip unsupported types
                        $raw_type = isset($question->question_type_new) ? $question->question_type_new : (isset($question->question_type) ? $question->question_type : '');
                        $type_map = array(
                            '0' => 'qcm', // Multiple Choice
                            '1' => 'qcm', // Multiple Choice (Horizontal)
                            '2' => 'qcm', // Drop Down
                            '3' => 'text', // Short Answer
                            '4' => 'multi_choice', // Multiple Response
                            '5' => 'essay', // Paragraph
                            '6' => null, // Text/HTML Section (skip)
                            '7' => 'text', // Number
                            '8' => null, // Opt-in (skip)
                            '9' => null, // Captcha (skip)
                            '10' => 'multi_choice', // Multiple Response (Horizontal)
                            '11' => null, // File Upload (skip)
                            '12' => 'text', // Date
                            '13' => null, // Polar (skip)
                            '14' => 'fill_blank', // Fill In The Blank
                            '15' => null, // Matching Pairs (PRO, skip)
                            '16' => null, // Radio Grid (PRO, skip)
                            '17' => null, // Checkbox Grid (PRO, skip)
                            'qcm' => 'qcm',
                            'multi_choice' => 'multi_choice',
                            'fill_blank' => 'fill_blank',
                            'essay' => 'essay',
                            'text' => 'text',
                            'true_false' => 'true_false',
                        );
                        if (!isset($type_map[$raw_type]) || $type_map[$raw_type] === null) {
                            error_log('QSM MIGRATION: SKIP question_id=' . $question->question_id . ' | Unsupported type=' . print_r($raw_type, true));
                            continue; // Skip unsupported types
                        }
                        $question_type = $type_map[$raw_type];
                        $correct_answer = isset($question->correct_answer) ? $question->correct_answer : '';
                        $explanation = isset($question->explanation) ? $question->explanation : '';
                        error_log('QSM MIGRATION: FINAL question_id=' . $question->question_id . ' | question_text=' . print_r($question_text, true));
                        $wpdb->insert($wpdb->prefix . 'quiz_ia_questions', [
                            'quiz_id' => $new_quiz_id,
                            'question_text' => $question_text,
                            'question_type' => $question_type,
                            'correct_answer' => $correct_answer,
                            'explanation' => $explanation,
                            'created_at' => current_time('mysql'),
                        ]);
                        $new_question_id = $wpdb->insert_id;
                        $questions_migrated++;

                        // Extract answers from answer_array
                        $answers = !empty($question->answer_array) ? @unserialize($question->answer_array) : [];
                        if (is_array($answers)) {
                            foreach ($answers as $ans) {
                                // QSM answer array: [0] => answer text, [2] => is_correct
                                $answer_text = isset($ans[0]) ? $ans[0] : '';
                                $is_correct = isset($ans[2]) ? intval($ans[2]) : 0;
                                error_log('QSM MIGRATION: INSERT ANSWER | question_id=' . $new_question_id . ' | answer_text=' . print_r($answer_text, true) . ' | is_correct=' . print_r($is_correct, true));
                                if (!empty($answer_text)) {
                                    $wpdb->insert($wpdb->prefix . 'quiz_ia_answers', [
                                        'question_id' => $new_question_id,
                                        'answer_text' => $answer_text,
                                        'is_correct' => $is_correct,
                                        'created_at' => current_time('mysql'),
                                    ]);
                                    $answers_migrated++;
                                }
                            }
                        }
                    }
                }
                $migrated++;
            }
            echo '<div class="notice notice-success" style="margin-top:24px;"><p>Migrated <strong>' . $migrated . '</strong> QSM quizzes, <strong>' . $questions_migrated . '</strong> questions, and <strong>' . $answers_migrated . '</strong> answers to Quiz AI format.</p></div>';
        } else {
            echo '<div class="notice notice-warning" style="margin-top:24px;"><p>No QSM quizzes found to migrate.</p></div>';
        }
    }
    echo '</div>';
}
