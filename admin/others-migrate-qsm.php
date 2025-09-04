<?php
if (!defined('ABSPATH')) exit;

function quiz_ai_migrate_qsm_quizzes_page()
{
    global $wpdb;
    echo '<div class="wrap" style="max-width:700px;margin:40px auto;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.08);padding:40px;">';
    echo '<h1 style="margin-bottom:24px;font-size:2rem;color:#0073aa;display:flex;align-items:center;gap:12px;">Migrate QSM Quizzes</h1>';

    // Show supported question types
    echo '<div style="background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:24px;border-left:4px solid #0073aa;">';
    echo '<h3 style="margin-top:0;color:#0073aa;">Supported Question Types</h3>';
    echo '<p style="margin-bottom:8px;"><strong>✅ Will be migrated:</strong></p>';
    echo '<ul style="margin-left:20px;margin-bottom:16px;">';
    echo '<li>Multiple Choice (QCM) → qcm</li>';
    echo '<li>Multiple Response → multiple-choice</li>';
    echo '<li>Short Answer/Number/Date → text</li>';
    echo '<li>Paragraph → essay</li>';
    echo '<li>Fill in the Blank → fill_blank</li>';
    echo '<li>Polar/True-False → true-false</li>';
    echo '<li><strong>📷 Question images</strong> (from featured images)</li>';
    echo '</ul>';
    echo '<p style="margin-bottom:8px;"><strong>⚠️ Will be skipped:</strong></p>';
    echo '<ul style="margin-left:20px;margin-bottom:0;">';
    echo '<li>Text/HTML Sections</li>';
    echo '<li>Opt-in, Captcha, File Upload</li>';
    echo '<li>Matching Pairs, Radio/Checkbox Grids (PRO features)</li>';
    echo '<li><strong>Questions with no answer options</strong> (when options are required)</li>';
    echo '</ul>';
    echo '</div>';

    echo '<form method="post" style="margin-bottom:24px;">';
    echo '<button type="submit" name="quiz_ai_migrate_qsm" class="button button-primary" style="height:40px;padding:0 24px;font-size:1rem;border-radius:6px;background:#0073aa;color:#fff;">Migrate QSM Quizzes</button>';
    echo '</form>';

    if (isset($_POST['quiz_ai_migrate_qsm'])) {
        // Fetch QSM quizzes
        $qsm_quizzes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mlw_quizzes");
        $migrated = 0;
        $questions_migrated = 0;
        $answers_migrated = 0;
        $quizzes_skipped = 0;
        $questions_skipped = 0;
        $images_migrated = 0;
        $auto_answers_created = 0;
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
                $quiz_questions_migrated = 0;

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

                        // Extract question image from question_settings
                        $featured_image = '';
                        if (is_array($settings)) {
                            // QSM can store image as attachment ID or direct URL
                            if (isset($settings['featureImageID']) && !empty($settings['featureImageID'])) {
                                // Get attachment URL from ID
                                $attachment_url = wp_get_attachment_url(intval($settings['featureImageID']));
                                if ($attachment_url) {
                                    $featured_image = $attachment_url;
                                }
                            } elseif (isset($settings['featureImageSrc']) && !empty($settings['featureImageSrc'])) {
                                // Direct URL
                                $featured_image = $settings['featureImageSrc'];
                            }
                        }
                        // Map QSM type to plugin equivalents, skip unsupported types
                        $raw_type = isset($question->question_type_new) ? $question->question_type_new : (isset($question->question_type) ? $question->question_type : '');
                        $type_map = array(
                            '0' => 'qcm', // Multiple Choice
                            '1' => 'qcm', // Multiple Choice (Horizontal)
                            '2' => 'qcm', // Drop Down
                            '3' => 'text', // Short Answer
                            '4' => 'multiple-choice', // Multiple Response
                            '5' => 'essay', // Paragraph
                            '6' => null, // Text/HTML Section (skip)
                            '7' => 'text', // Number
                            '8' => null, // Opt-in (skip)
                            '9' => null, // Captcha (skip)
                            '10' => 'multiple-choice', // Multiple Response (Horizontal)
                            '11' => null, // File Upload (skip)
                            '12' => 'text', // Date
                            '13' => 'true-false', // Polar (True/False)
                            '14' => 'fill_blank', // Fill In The Blank
                            '15' => null, // Matching Pairs (PRO, skip)
                            '16' => null, // Radio Grid (PRO, skip)
                            '17' => null, // Checkbox Grid (PRO, skip)
                            // String-based mappings for compatibility
                            'qcm' => 'qcm',
                            'multiple-choice' => 'multiple-choice',
                            'single-choice' => 'single-choice',
                            'fill_blank' => 'fill_blank',
                            'essay' => 'essay',
                            'text' => 'text',
                            'true-false' => 'true-false',
                            'true_false' => 'true-false', // Legacy support
                            'polar' => 'true-false', // QSM Polar questions are basically true/false
                        );
                        if (!isset($type_map[$raw_type]) || $type_map[$raw_type] === null) {
                            error_log('QSM MIGRATION: SKIP question_id=' . $question->question_id . ' | Unsupported type=' . print_r($raw_type, true));
                            $questions_skipped++;
                            continue; // Skip unsupported types
                        }
                        $question_type = $type_map[$raw_type];

                        // Validate that questions requiring options actually have them
                        $types_requiring_options = ['qcm', 'multiple-choice', 'single-choice', 'true-false'];
                        if (in_array($question_type, $types_requiring_options)) {
                            // Extract answers from answer_array to check if options exist
                            $answers = !empty($question->answer_array) ? @unserialize($question->answer_array) : [];
                            $valid_answers = 0;

                            if (is_array($answers)) {
                                foreach ($answers as $ans) {
                                    $answer_text = isset($ans[0]) ? trim($ans[0]) : '';
                                    if (!empty($answer_text)) {
                                        $valid_answers++;
                                    }
                                }
                            }

                            // Skip questions that should have options but don't
                            if ($valid_answers === 0) {
                                error_log('QSM MIGRATION: SKIP question_id=' . $question->question_id . ' | Type requires options but has none | type=' . $question_type);
                                $questions_skipped++;
                                continue;
                            }

                            // For true-false, ensure we have exactly 2 options or auto-create them
                            if ($question_type === 'true-false' && $valid_answers < 2) {
                                error_log('QSM MIGRATION: AUTO-CREATE true-false options for question_id=' . $question->question_id);
                                // We'll handle this in the answer migration section
                            }
                        }

                        $correct_answer = isset($question->correct_answer) ? $question->correct_answer : '';
                        $explanation = isset($question->explanation) ? $question->explanation : '';
                        error_log('QSM MIGRATION: FINAL question_id=' . $question->question_id . ' | question_text=' . print_r($question_text, true));

                        // Log image migration
                        if (!empty($featured_image)) {
                            error_log('QSM MIGRATION: IMAGE found for question_id=' . $question->question_id . ' | image_url=' . $featured_image);
                            $images_migrated++;
                        }

                        $wpdb->insert($wpdb->prefix . 'quiz_ia_questions', [
                            'quiz_id' => $new_quiz_id,
                            'question_text' => wp_kses_post($question_text),
                            'question_type' => $question_type,
                            'correct_answer' => $correct_answer,
                            'explanation' => wp_kses_post($explanation),
                            'featured_image' => $featured_image,
                            'created_at' => current_time('mysql'),
                        ]);
                        $new_question_id = $wpdb->insert_id;
                        $questions_migrated++;
                        $quiz_questions_migrated++;

                        // Extract answers from answer_array
                        $answers = !empty($question->answer_array) ? @unserialize($question->answer_array) : [];
                        $answers_added = 0;

                        if (is_array($answers)) {
                            foreach ($answers as $ans) {
                                // QSM answer array: [0] => answer text, [2] => is_correct
                                $answer_text = isset($ans[0]) ? trim($ans[0]) : '';
                                $is_correct = isset($ans[2]) ? intval($ans[2]) : 0;

                                if (!empty($answer_text)) {
                                    error_log('QSM MIGRATION: INSERT ANSWER | question_id=' . $new_question_id . ' | answer_text=' . print_r($answer_text, true) . ' | is_correct=' . print_r($is_correct, true));
                                    $wpdb->insert($wpdb->prefix . 'quiz_ia_answers', [
                                        'question_id' => $new_question_id,
                                        'answer_text' => $answer_text,
                                        'is_correct' => $is_correct,
                                        'created_at' => current_time('mysql'),
                                    ]);
                                    $answers_migrated++;
                                    $answers_added++;
                                }
                            }
                        }

                        // Auto-create True/False options if needed
                        if ($question_type === 'true-false' && $answers_added < 2) {
                            $existing_correct = ($answers_added > 0) ? 0 : 1; // Default to True if no answers exist

                            // Add "Vrai" if not exists
                            $wpdb->insert($wpdb->prefix . 'quiz_ia_answers', [
                                'question_id' => $new_question_id,
                                'answer_text' => 'Vrai',
                                'is_correct' => $existing_correct,
                                'created_at' => current_time('mysql'),
                            ]);
                            $answers_migrated++;
                            $auto_answers_created++;

                            // Add "Faux" if not exists  
                            $wpdb->insert($wpdb->prefix . 'quiz_ia_answers', [
                                'question_id' => $new_question_id,
                                'answer_text' => 'Faux',
                                'is_correct' => $existing_correct ? 0 : 1,
                                'created_at' => current_time('mysql'),
                            ]);
                            $answers_migrated++;
                            $auto_answers_created++;

                            error_log('QSM MIGRATION: AUTO-CREATED true-false options for question_id=' . $new_question_id);
                        }
                    }
                }

                // Check if quiz has any questions, if not delete it
                if ($quiz_questions_migrated === 0) {
                    // Delete the quiz since it has no valid questions
                    $wpdb->delete($wpdb->prefix . 'quiz_ia_quizzes', ['id' => $new_quiz_id], ['%d']);
                    $quizzes_skipped++;
                    error_log('QSM MIGRATION: DELETED quiz (0 questions) | original_quiz_id=' . $quiz->quiz_id . ' | title=' . $title);
                } else {
                    $migrated++;
                    error_log('QSM MIGRATION: MIGRATED quiz | original_quiz_id=' . $quiz->quiz_id . ' | new_quiz_id=' . $new_quiz_id . ' | questions=' . $quiz_questions_migrated);
                }
            }
            echo '<div class="notice notice-success" style="margin-top:24px;"><p><strong>Migration completed!</strong><br/>
            ✅ Successfully migrated: <strong>' . $migrated . '</strong> quizzes<br/>
            ✅ Questions migrated: <strong>' . $questions_migrated . '</strong><br/>
            ✅ Answers migrated: <strong>' . $answers_migrated . '</strong><br/>
            ' . ($images_migrated > 0 ? '✅ Question images migrated: <strong>' . $images_migrated . '</strong><br/>' : '') . '
            ' . ($auto_answers_created > 0 ? '🔧 Auto-created answers (true/false): <strong>' . $auto_answers_created . '</strong><br/>' : '') . '
            ' . ($questions_skipped > 0 ? '⚠️ Questions skipped (unsupported types or no options): <strong>' . $questions_skipped . '</strong><br/>' : '') . '
            ' . ($quizzes_skipped > 0 ? '⚠️ Quizzes skipped (0 valid questions): <strong>' . $quizzes_skipped . '</strong><br/>' : '') . '
            </p></div>';
        } else {
            echo '<div class="notice notice-warning" style="margin-top:24px;"><p>No QSM quizzes found to migrate.</p></div>';
        }
    }
    echo '</div>';
}
