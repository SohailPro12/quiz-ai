<?php

/**
 * LearnPress Integration for Quiz IA Pro
 * 
 * This file handles integration between Quiz IA Pro and LearnPress,
 * creating corresponding LearnPress quizzes for each Quiz IA Pro quiz.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Link questions to LearnPress quiz using the quiz_questions table
 */
function quiz_ai_pro_link_questions_to_quiz($lp_quiz_id, $question_ids)
{
    global $wpdb;

    error_log("Quiz IA Pro: Linking " . count($question_ids) . " questions to LearnPress quiz ID {$lp_quiz_id}");

    foreach ($question_ids as $order => $question_id) {
        $result = $wpdb->insert(
            $wpdb->prefix . 'learnpress_quiz_questions',
            array(
                'quiz_id'        => $lp_quiz_id,
                'question_id'    => $question_id,
                'question_order' => $order + 1
            ),
            array('%d', '%d', '%d')
        );

        if ($result === false) {
            error_log("Quiz IA Pro: Failed to link question ID {$question_id} to quiz ID {$lp_quiz_id}");
        } else {
            error_log("Quiz IA Pro: Successfully linked question ID {$question_id} to quiz ID {$lp_quiz_id} with order " . ($order + 1));
        }
    }
}

/**
 * Create LearnPress question answers (options) for multiple choice questions
 */
function quiz_ai_pro_create_learnpress_quiz($quiz_ia_id, $quiz_data)
{
    // Check if LearnPress is active
    if (!class_exists('LearnPress')) {
        error_log('Quiz IA Pro: LearnPress not found, skipping LearnPress quiz creation');
        return false;
    }

    try {
        // Prepare LearnPress quiz post data
        $quiz_title = sanitize_text_field($quiz_data['title']);
        $quiz_description = sanitize_textarea_field($quiz_data['description'] ?? '');
        $quiz_code = sanitize_text_field($quiz_data['quiz_code']);

        // Create the LearnPress quiz post
        $lp_quiz_data = array(
            'post_title'    => $quiz_title,
            'post_content'  => $quiz_description, // This is the quiz description - main content
            'post_excerpt'  => wp_trim_words($quiz_description, 30), // Short excerpt
            'post_status'   => 'publish',
            'post_type'     => 'lp_quiz',
            'post_author'   => get_current_user_id(),
            'meta_input'    => array(
                '_lp_duration'              => isset($quiz_data['time_limit']) && is_numeric($quiz_data['time_limit']) ? intval($quiz_data['time_limit']) : 0, // Time in minutes, default to 0
                '_lp_duration_type'         => 'minute', // Specify duration type
                '_lp_passing_grade'         => 80, // Default passing grade
                '_lp_negative_marking'      => 'no', // No negative marking by default
                '_lp_instant_check'         => 'no', // No instant check by default
                '_lp_retake_count'          => -1, // Unlimited retakes
                '_lp_show_result'          => 'yes', // Show results
                '_lp_show_correct_review'  => 'yes', // Show correct answers in review
                '_lp_show_check_answer'    => 'no', // Don't show check answer during quiz
                '_quiz_ia_pro_id'          => $quiz_ia_id, // Link back to Quiz IA Pro quiz
                '_quiz_ia_pro_code'        => $quiz_code, // Store Quiz IA Pro code
                '_quiz_ia_pro_sync'        => 'yes', // Mark as synced with Quiz IA Pro
                '_lp_quiz_description'     => $quiz_description, // Additional description field
                '_lp_content'              => $quiz_description, // LearnPress content field
                'lp_quiz_description'      => $quiz_description, // Without underscore prefix
                'quiz_description'         => $quiz_description  // Generic description field
            )
        );

        error_log("Quiz IA Pro: Creating LearnPress quiz with title: '{$quiz_title}' and description: '" . substr($quiz_description, 0, 100) . "...'");

        // Insert the LearnPress quiz post
        $lp_quiz_id = wp_insert_post($lp_quiz_data);

        if (is_wp_error($lp_quiz_id)) {
            error_log('Quiz IA Pro: Failed to create LearnPress quiz: ' . $lp_quiz_id->get_error_message());
            return false;
        }

        // Additional safety check: Ensure duration fields are properly set
        $duration_value = isset($quiz_data['time_limit']) && is_numeric($quiz_data['time_limit']) ? intval($quiz_data['time_limit']) : 10;
        update_post_meta($lp_quiz_id, '_lp_duration', $duration_value);
        update_post_meta($lp_quiz_id, '_lp_duration_type', 'minute');

        error_log("Quiz IA Pro: Set quiz duration to {$duration_value} minutes for quiz ID {$lp_quiz_id}");

        // Create quiz questions in LearnPress format
        if (isset($quiz_data['questions']) && is_array($quiz_data['questions'])) {
            $original_question_count = count($quiz_data['questions']);
            $lp_question_ids = quiz_ai_pro_create_learnpress_questions($quiz_data['questions'], $lp_quiz_id);

            if (!empty($lp_question_ids)) {
                $synced_question_count = count($lp_question_ids);

                if ($synced_question_count < $original_question_count) {
                    $filtered_count = $original_question_count - $synced_question_count;
                    error_log("Quiz IA Pro: LearnPress sync completed. {$synced_question_count} questions synchronized, {$filtered_count} questions filtered out (unsupported types)");

                    // Store a notice for the admin interface
                    update_post_meta($lp_quiz_id, '_quiz_ia_pro_sync_notice', "Synchronized {$synced_question_count} out of {$original_question_count} questions. {$filtered_count} questions were filtered out as they are not supported by LearnPress.");
                } else {
                    error_log("Quiz IA Pro: LearnPress sync completed successfully. All {$synced_question_count} questions synchronized");
                }

                // CRITICAL: Link questions to quiz in LearnPress database
                quiz_ai_pro_link_questions_to_quiz($lp_quiz_id, $lp_question_ids);

                // Add questions to the quiz meta - LearnPress format
                update_post_meta($lp_quiz_id, '_lp_quiz_questions', $lp_question_ids);
                update_post_meta($lp_quiz_id, '_lp_questions', $lp_question_ids); // Alternative meta key

                // Set the total questions count
                $total_questions = count($lp_question_ids);
                update_post_meta($lp_quiz_id, '_lp_quiz_question_count', $total_questions);
                update_post_meta($lp_quiz_id, '_question_count', $total_questions); // Alternative meta key

                // Calculate total points
                $total_points = $total_questions; // 1 point per question
                update_post_meta($lp_quiz_id, '_lp_quiz_total_points', $total_points);

                error_log("Quiz IA Pro: Linked " . count($lp_question_ids) . " questions to LearnPress quiz ID {$lp_quiz_id}");

                // Clear LearnPress cache for this quiz
                if (class_exists('LP_Object_Cache')) {
                    LP_Object_Cache::flush('learn-press/quizzes');
                    LP_Object_Cache::flush('learn-press/quiz-' . $lp_quiz_id);
                }

                // Clear WordPress post cache
                clean_post_cache($lp_quiz_id);
            }
        } else {
            // Set default values if no questions
            update_post_meta($lp_quiz_id, '_lp_quiz_questions', array());
            update_post_meta($lp_quiz_id, '_lp_questions', array());
            update_post_meta($lp_quiz_id, '_lp_quiz_question_count', 0);
            update_post_meta($lp_quiz_id, '_question_count', 0);
            update_post_meta($lp_quiz_id, '_lp_quiz_total_points', 0);
        }

        // Update Quiz IA Pro quiz with LearnPress quiz ID
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'quiz_ia_quizzes',
            array('learnpress_quiz_id' => $lp_quiz_id),
            array('id' => $quiz_ia_id),
            array('%d'),
            array('%d')
        );

        error_log("Quiz IA Pro: Successfully created LearnPress quiz ID {$lp_quiz_id} for Quiz IA Pro quiz ID {$quiz_ia_id}");

        return $lp_quiz_id;
    } catch (Exception $e) {
        error_log('Quiz IA Pro: Error creating LearnPress quiz: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create LearnPress questions from Quiz IA Pro questions
 */
function quiz_ai_pro_create_learnpress_questions($questions, $lp_quiz_id)
{
    $lp_question_ids = array();

    // Define supported question types for LearnPress integration - include all variations
    $supported_types = array(
        'qcm',
        'single_choice',
        'single-choice',
        'multiple-choice',
        'multi_choice',
        'multiple_choice',
        'true-false',
        'true_false',
        'true_or_false',
        'fill_blank',
        'fill_in_blanks',
        'fill_in_the_blank',
        'text_a_completer',
        'text',
        'essay'
    );

    error_log("Quiz IA Pro: Creating " . count($questions) . " questions for LearnPress quiz ID {$lp_quiz_id}");

    // Filter questions to only include supported types
    $filtered_questions = array();
    foreach ($questions as $index => $question) {
        $question_type = sanitize_text_field($question['type'] ?? 'qcm');

        if (in_array($question_type, $supported_types)) {
            $filtered_questions[] = $question;
            error_log("Quiz IA Pro: Question " . ($index + 1) . " type '{$question_type}' is supported, including in LearnPress sync");
        } else {
            error_log("Quiz IA Pro: Question " . ($index + 1) . " type '{$question_type}' is not supported by LearnPress, skipping");
        }
    }

    if (empty($filtered_questions)) {
        error_log("Quiz IA Pro: No supported questions found for LearnPress synchronization");
        return array();
    }

    error_log("Quiz IA Pro: " . count($filtered_questions) . " out of " . count($questions) . " questions are supported for LearnPress");

    foreach ($filtered_questions as $index => $question) {
        $question_text = sanitize_textarea_field($question['question'] ?? '');
        $question_type = sanitize_text_field($question['type'] ?? 'qcm');
        $explanation = sanitize_textarea_field($question['explanation'] ?? '');

        error_log("Quiz IA Pro: Processing supported question " . ($index + 1) . " - Type: {$question_type}");

        // Map Quiz IA Pro question types to LearnPress question types
        $lp_question_type = 'single_choice'; // Default
        switch ($question_type) {
            case 'qcm':
            case 'single_choice':
            case 'single-choice':
                $lp_question_type = 'single_choice';
                break;
            case 'multiple-choice':
            case 'multi_choice':
            case 'multiple_choice':
                $lp_question_type = 'multi_choice';
                break;
            case 'true-false':
            case 'true_false':
            case 'true_or_false':
                $lp_question_type = 'true_or_false';
                break;
            case 'fill_blank':
            case 'fill_in_blanks':
            case 'text_a_completer':
            case 'fill_in_the_blank':
                $lp_question_type = 'fill_in_blanks';
                break;
            case 'text':
            case 'essay':
                $lp_question_type = 'single_choice'; // LearnPress doesn't have text type, use single choice
                break;
        }

        // Create LearnPress question post
        $lp_question_data = array(
            'post_title'    => 'Question ' . ($index + 1) . ' - ' . wp_trim_words($question_text, 8, '...'),
            'post_content'  => $question_text,
            'post_status'   => 'publish',
            'post_type'     => 'lp_question',
            'post_author'   => get_current_user_id(),
            'meta_input'    => array(
                '_lp_type'              => $lp_question_type,
                '_lp_mark'              => 1, // 1 point per question
                '_lp_explanation'       => $explanation,
                '_quiz_ia_pro_question' => 'yes', // Mark as Quiz IA Pro question
                '_lp_quiz_id'           => $lp_quiz_id
            )
        );

        $lp_question_id = wp_insert_post($lp_question_data);

        if (!is_wp_error($lp_question_id)) {
            $lp_question_ids[] = $lp_question_id;
            error_log("Quiz IA Pro: Created LearnPress question ID {$lp_question_id}");

            // Handle question options based on type
            if ($question_type === 'qcm' || $question_type === 'single_choice' || $question_type === 'single-choice') {
                // Single choice questions
                error_log("Quiz IA Pro: Full question data for single choice: " . json_encode($question));

                if (isset($question['options'])) {
                    error_log("Quiz IA Pro: Question options found: " . json_encode($question['options']));

                    if (is_array($question['options']) && count($question['options']) > 0) {
                        error_log("Quiz IA Pro: Creating options for single choice question ID {$lp_question_id} - Options: " . json_encode($question['options']));
                        quiz_ai_pro_create_learnpress_question_answers($lp_question_id, $question['options'], $question['correct_answer'] ?? 0, 'single_choice');
                    } else {
                        error_log("Quiz IA Pro: Question options is not a valid array for question ID {$lp_question_id}");
                    }
                } else {
                    error_log("Quiz IA Pro: No 'options' key found in question data for question ID {$lp_question_id}");
                }
            } elseif ($question_type === 'multi_choice' || $question_type === 'multiple-choice' || $question_type === 'multiple_choice') {
                // Multi choice questions
                error_log("Quiz IA Pro: Processing multi choice question ID {$lp_question_id}");
                error_log("Quiz IA Pro: Full question data for multi choice: " . json_encode($question));

                if (isset($question['options'])) {
                    if (is_array($question['options']) && count($question['options']) > 0) {
                        error_log("Quiz IA Pro: Creating options for multi choice question ID {$lp_question_id}");
                        // For multi choice, correct_answer might be an array
                        $correct_answers = $question['correct_answer'] ?? array();
                        quiz_ai_pro_create_learnpress_question_answers($lp_question_id, $question['options'], $correct_answers, 'multi_choice');
                    } else {
                        error_log("Quiz IA Pro: Question options is not a valid array for multi choice question ID {$lp_question_id}");
                    }
                } else {
                    error_log("Quiz IA Pro: No 'options' key found in multi choice question data for question ID {$lp_question_id}");
                }
            } elseif ($question_type === 'true_false' || $question_type === 'true-false' || $question_type === 'true_or_false') {
                // True/False questions - create True and False options
                error_log("Quiz IA Pro: Processing true/false question ID {$lp_question_id}");
                error_log("Quiz IA Pro: Full question data for true/false: " . json_encode($question));

                $true_false_options = array('Vrai', 'Faux'); // French true/false
                $correct_answer = 0; // Default to True

                // Determine correct answer
                if (isset($question['correct_answer'])) {
                    if (is_string($question['correct_answer'])) {
                        $correct_answer = (strtolower($question['correct_answer']) === 'true' ||
                            strtolower($question['correct_answer']) === 'vrai') ? 0 : 1;
                    } elseif (is_numeric($question['correct_answer'])) {
                        $correct_answer = intval($question['correct_answer']);
                    }
                }

                error_log("Quiz IA Pro: True/False correct answer determined as: " . $correct_answer);
                quiz_ai_pro_create_learnpress_question_answers($lp_question_id, $true_false_options, $correct_answer, 'true_or_false');
            } elseif (
                $question_type === 'fill_in_the_blank' || $question_type === 'fill_blank' ||
                $question_type === 'text_a_completer' || $question_type === 'fill_in_blanks'
            ) {
                // Fill in the blank questions
                error_log("Quiz IA Pro: Processing fill-in-blank question ID {$lp_question_id}");
                error_log("Quiz IA Pro: Full question data for fill-in-blank: " . json_encode($question));

                // Store the question content with blank markers
                $question_content = $question_text;

                // If there are blanks answers, create them
                if (isset($question['options']) && is_array($question['options'])) {
                    // Convert options to blanks format for LearnPress
                    error_log("Quiz IA Pro: Found 'options' array for fill-in-blank: " . json_encode($question['options']));
                    quiz_ai_pro_create_fill_in_blank_answers($lp_question_id, $question['options'], $question_content);
                } elseif (isset($question['blanks_answers']) && is_array($question['blanks_answers'])) {
                    error_log("Quiz IA Pro: Found 'blanks_answers' array for fill-in-blank: " . json_encode($question['blanks_answers']));
                    quiz_ai_pro_create_fill_in_blank_answers($lp_question_id, $question['blanks_answers'], $question_content);
                } else {
                    // Create default blank
                    error_log("Quiz IA Pro: No specific answers found, creating default blank for fill-in-blank question");
                    quiz_ai_pro_create_fill_in_blank_answers($lp_question_id, array('_blank_'), $question_content);
                }
            } elseif ($question_type === 'text' || $question_type === 'essay') {
                // Text/Essay questions - convert to single choice with text answer
                error_log("Quiz IA Pro: Processing text/essay question ID {$lp_question_id}");

                $text_options = array('Réponse libre');
                if (isset($question['options']) && is_array($question['options'])) {
                    $text_options = $question['options'];
                } elseif (isset($question['answer']) && !empty($question['answer'])) {
                    $text_options = array($question['answer']);
                }

                quiz_ai_pro_create_learnpress_question_answers($lp_question_id, $text_options, 0, 'single_choice');
            }

            // Clear any existing caches for this question
            clean_post_cache($lp_question_id);
        } else {
            error_log("Quiz IA Pro: Failed to create LearnPress question: " . $lp_question_id->get_error_message());
        }
    }

    error_log("Quiz IA Pro: Successfully created " . count($lp_question_ids) . " LearnPress questions");
    return $lp_question_ids;
}

/**
 * Create answers for LearnPress questions using native LearnPress method
 */
function quiz_ai_pro_create_learnpress_question_answers($question_id, $options, $correct_index, $question_type = 'single_choice')
{
    global $wpdb;

    error_log("Quiz IA Pro: Creating answers for question ID {$question_id} with " . count($options) . " options, correct index: " . json_encode($correct_index) . ", type: {$question_type}");

    // Clear existing answers for this question
    $table_meta = $wpdb->learnpress_question_answermeta;
    $table_main = $wpdb->learnpress_question_answers;

    $query = $wpdb->prepare(
        "DELETE FROM t1, t2
         USING {$table_main} AS t1 
         INNER JOIN {$table_meta} AS t2 ON t1.question_answer_id = t2.learnpress_question_answer_id
         WHERE t1.question_id = %d",
        $question_id
    );
    $wpdb->query($query);
    error_log("Quiz IA Pro: Cleared existing answers for question ID {$question_id}");

    if (empty($options)) {
        error_log("Quiz IA Pro: No options provided for question {$question_id}");
        return;
    }

    // Create answers using direct database insertion (LearnPress native method)
    foreach ($options as $index => $option_text) {
        $order = $index + 1;

        // Determine if this answer is correct
        $is_correct = false;
        if ($question_type === 'multi_choice') {
            // For multiple choice, correct_index might be an array
            if (is_array($correct_index)) {
                $is_correct = in_array($index, $correct_index);
            } else {
                $is_correct = ($index == $correct_index);
            }
        } else {
            // For single choice and true/false
            $is_correct = ($index == $correct_index);
        }

        // Clean and validate option text
        $clean_option = trim($option_text);
        if (empty($clean_option)) {
            error_log("Quiz IA Pro: Skipping empty option at index {$index} for question {$question_id}");
            continue;
        }

        error_log("Quiz IA Pro: Processing option {$order}: '{$clean_option}' - Correct: " . ($is_correct ? 'yes' : 'no'));

        // Generate unique value hash like LearnPress does
        $value_hash = md5($clean_option . $question_id . time() . rand());

        // Insert answer in learnpress_question_answers table with all required fields
        $insert_query = $wpdb->prepare(
            "INSERT INTO {$table_main} (`question_id`, `title`, `value`, `order`, `is_true`) VALUES (%d, %s, %s, %d, %s)",
            $question_id,
            $clean_option,
            $value_hash,
            $order,
            $is_correct ? 'yes' : ''
        );

        $result = $wpdb->query($insert_query);

        if ($result) {
            $answer_id = $wpdb->insert_id;
            error_log("Quiz IA Pro: Created answer ID {$answer_id} for question {$question_id} - Text: '{$clean_option}' - Correct: " . ($is_correct ? 'yes' : 'no') . " - Hash: {$value_hash}");
        } else {
            error_log("Quiz IA Pro: Failed to create answer for question {$question_id}: " . $wpdb->last_error);
        }
    }

    error_log("Quiz IA Pro: Successfully created " . count($options) . " answers for question ID {$question_id}");
}

/**
 * Create fill-in-blank answers for LearnPress questions
 */
function quiz_ai_pro_create_fill_in_blank_answers($question_id, $blanks_answers, $question_content)
{
    global $wpdb;

    error_log("Quiz IA Pro: Creating fill-in-blank answers for question ID {$question_id}");
    error_log("Quiz IA Pro: Blank answers: " . json_encode($blanks_answers));

    // Clear existing answers
    $table_main = $wpdb->learnpress_question_answers;
    $wpdb->delete($table_main, array('question_id' => $question_id));

    // Create fill-in-blank entries
    foreach ($blanks_answers as $index => $blank_answer) {
        $order = $index + 1;
        $blank_id = wp_generate_uuid4();

        // Create the blank content with LearnPress format
        $blank_content = '[fib fill="' . esc_attr($blank_answer) . '" id="' . $blank_id . '"]';

        // Insert blank answer
        $insert_result = $wpdb->insert(
            $table_main,
            array(
                'question_id' => $question_id,
                'title' => $blank_answer,
                'value' => '', // Empty for fill-in-blanks
                'order' => $order,
                'is_true' => '' // Not used for fill-in-blanks
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );

        if ($insert_result) {
            $answer_id = $wpdb->insert_id;
            error_log("Quiz IA Pro: Created fill-in-blank answer ID {$answer_id} for question {$question_id} - Content: '{$blank_answer}'");

            // Store additional meta for the blank
            update_post_meta($question_id, "_lp_answer_{$index}", $blank_answer);
        } else {
            error_log("Quiz IA Pro: Failed to create fill-in-blank answer for question {$question_id}: " . $wpdb->last_error);
        }
    }

    // Store the blank answers in post meta
    update_post_meta($question_id, '_lp_answers', $blanks_answers);
    update_post_meta($question_id, '_lp_fill_in_blanks', 'yes');

    error_log("Quiz IA Pro: Successfully created " . count($blanks_answers) . " fill-in-blank answers for question ID {$question_id}");
}

/**
 * Add LearnPress quiz ID column to Quiz IA Pro quizzes table
 */
function quiz_ai_pro_add_learnpress_quiz_id_column()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'quiz_ia_quizzes';

    // Check if column already exists
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
        'learnpress_quiz_id'
    ));

    if (empty($column_exists)) {
        $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `learnpress_quiz_id` bigint(20) unsigned DEFAULT NULL";
        $result = $wpdb->query($sql);

        if ($result !== false) {
            error_log('Quiz IA Pro: Successfully added learnpress_quiz_id column');
            return true;
        } else {
            error_log('Quiz IA Pro: Failed to add learnpress_quiz_id column: ' . $wpdb->last_error);
            return false;
        }
    }

    return true; // Column already exists
}

/**
 * Hook to create LearnPress quiz when Quiz IA Pro quiz is saved
 */
// Automatic LearnPress integration disabled - sync only when manually requested
// add_action('quiz_ai_pro_quiz_created', 'quiz_ai_pro_create_learnpress_quiz_hook', 10, 2);

function quiz_ai_pro_create_learnpress_quiz_hook($quiz_id, $quiz_data)
{
    // Create LearnPress quiz for all quizzes, regardless of status
    quiz_ai_pro_create_learnpress_quiz($quiz_id, $quiz_data);
}

/**
 * Get LearnPress quiz ID for a Quiz IA Pro quiz
 */
function quiz_ai_pro_get_learnpress_quiz_id($quiz_ia_id)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'quiz_ia_quizzes';

    return $wpdb->get_var($wpdb->prepare(
        "SELECT learnpress_quiz_id FROM `{$table_name}` WHERE id = %d",
        $quiz_ia_id
    ));
}

/**
 * Sync quiz updates between Quiz IA Pro and LearnPress
 */
function quiz_ai_pro_sync_learnpress_quiz($quiz_ia_id, $updated_data)
{
    $lp_quiz_id = quiz_ai_pro_get_learnpress_quiz_id($quiz_ia_id);

    if (!$lp_quiz_id) {
        return false;
    }

    // Update LearnPress quiz post
    $update_data = array(
        'ID' => $lp_quiz_id
    );

    if (isset($updated_data['title'])) {
        $update_data['post_title'] = sanitize_text_field($updated_data['title']);
    }

    if (isset($updated_data['description'])) {
        $update_data['post_content'] = sanitize_textarea_field($updated_data['description']);
    }

    if (count($update_data) > 1) { // More than just ID
        wp_update_post($update_data);
    }

    // Update meta fields
    if (isset($updated_data['time_limit'])) {
        update_post_meta($lp_quiz_id, '_lp_duration', intval($updated_data['time_limit']));
    }

    return true;
}

/**
 * Initialize LearnPress integration
 */
function quiz_ai_pro_init_learnpress_integration()
{
    // Add the column when plugin is activated or updated (in case of upgrades)
    add_action('admin_init', 'quiz_ai_pro_add_learnpress_quiz_id_column', 5);

    // Hook to detect when LearnPress quiz is deleted
    add_action('before_delete_post', 'quiz_ai_pro_handle_learnpress_quiz_deletion');

    // Force add the column immediately if it doesn't exist
    quiz_ai_pro_add_learnpress_quiz_id_column();
}

/**
 * Handle LearnPress quiz deletion - update Quiz IA Pro sync status
 */
function quiz_ai_pro_handle_learnpress_quiz_deletion($post_id)
{
    $post = get_post($post_id);

    // Only handle LearnPress quiz deletions
    if (!$post || $post->post_type !== 'lp_quiz') {
        return;
    }

    global $wpdb;

    // Check if this LearnPress quiz is linked to a Quiz IA Pro quiz
    $quiz_ia_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}quiz_ia_quizzes WHERE learnpress_quiz_id = %d",
        $post_id
    ));

    if ($quiz_ia_id) {
        error_log("Quiz IA Pro: LearnPress quiz ID {$post_id} is being deleted, clearing sync status for Quiz IA Pro quiz ID {$quiz_ia_id}");

        // Clear the learnpress_quiz_id from Quiz IA Pro quiz
        $wpdb->update(
            $wpdb->prefix . 'quiz_ia_quizzes',
            array('learnpress_quiz_id' => null),
            array('id' => $quiz_ia_id),
            array('%s'),
            array('%d')
        );

        error_log("Quiz IA Pro: Successfully cleared sync status for Quiz IA Pro quiz ID {$quiz_ia_id}");
    }
}

/**
 * Check and clean up orphaned sync statuses
 * This function runs periodically to clean up quizzes marked as synced but LearnPress quiz no longer exists
 */
function quiz_ai_pro_cleanup_orphaned_syncs()
{
    global $wpdb;

    // Get all Quiz IA Pro quizzes that are marked as synced
    $synced_quizzes = $wpdb->get_results(
        "SELECT id, learnpress_quiz_id FROM {$wpdb->prefix}quiz_ia_quizzes 
         WHERE learnpress_quiz_id IS NOT NULL AND learnpress_quiz_id != 0"
    );

    foreach ($synced_quizzes as $quiz) {
        // Check if the LearnPress quiz still exists
        $lp_quiz_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'lp_quiz' AND post_status != 'trash'",
            $quiz->learnpress_quiz_id
        ));

        if (!$lp_quiz_exists) {
            error_log("Quiz IA Pro: Cleaning up orphaned sync for Quiz IA Pro quiz ID {$quiz->id}, LearnPress quiz ID {$quiz->learnpress_quiz_id} no longer exists");

            // Clear the sync status
            $wpdb->update(
                $wpdb->prefix . 'quiz_ia_quizzes',
                array('learnpress_quiz_id' => null),
                array('id' => $quiz->id),
                array('%s'),
                array('%d')
            );
        }
    }
}

// Hook into plugin initialization
add_action('init', 'quiz_ai_pro_init_learnpress_integration');

// Hook to run cleanup when admin area is accessed
add_action('admin_init', 'quiz_ai_pro_cleanup_orphaned_syncs');

// Include the database fix
require_once plugin_dir_path(__FILE__) . 'learnpress-db-fix.php';
