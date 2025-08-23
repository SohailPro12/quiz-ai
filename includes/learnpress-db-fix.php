<?php

/**
 * LearnPress Database Fix for Quiz IA Pro
 * 
 * This file fixes database issues for LearnPress integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fix LearnPress database issues
 */
function quiz_ai_pro_fix_learnpress_database()
{
    global $wpdb;

    
    // 1. Add learnpress_quiz_id column if it doesn't exist
    $quizzes_table = $wpdb->prefix . 'quiz_ia_quizzes';

    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM `{$quizzes_table}` LIKE %s",
        'learnpress_quiz_id'
    ));

    if (empty($column_exists)) {
        $sql = "ALTER TABLE `{$quizzes_table}` ADD COLUMN `learnpress_quiz_id` bigint(20) unsigned DEFAULT NULL";
        $result = $wpdb->query($sql);

        if ($result !== false) {
            error_log('Quiz IA Pro: Successfully added learnpress_quiz_id column');
        } else {
            error_log('Quiz IA Pro: Failed to add learnpress_quiz_id column: ' . $wpdb->last_error);
        }
    } else {
    }

    // 2. Check questions table structure
    $questions_table = $wpdb->prefix . 'quiz_ia_questions';
    $questions_columns = $wpdb->get_results("SHOW COLUMNS FROM `{$questions_table}`");

    $has_question_order = false;
    foreach ($questions_columns as $column) {
        if ($column->Field === 'question_order') {
            $has_question_order = true;
            break;
        }
    }

   

    // 3. Clean up broken LearnPress answers with empty titles
    if (isset($wpdb->learnpress_question_answers)) {
        $cleanup_result = $wpdb->query(
            "DELETE FROM {$wpdb->learnpress_question_answers} 
             WHERE title IS NULL OR title = '' OR TRIM(title) = ''"
        );

     
    }

    // 4. Fix duration type issues for existing LearnPress quizzes created by Quiz IA Pro
    // First, let's see what we actually have in the database
    $existing_duration_types = $wpdb->get_results(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
         WHERE meta_key = '_lp_duration_type' 
         AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'lp_quiz')"
    );

   
    $existing_durations = $wpdb->get_results(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
         WHERE meta_key = '_lp_duration' 
         AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'lp_quiz')"
    );

    

    $fix_duration_result = $wpdb->query(
        "UPDATE {$wpdb->postmeta} 
         SET meta_value = 'minute' 
         WHERE meta_key = '_lp_duration_type' 
         AND (meta_value IS NULL OR meta_value = '' OR meta_value = '0')"
    );

   

    // 5. Add missing duration type entries for quizzes that don't have it at all
    $missing_duration_types = $wpdb->query(
        "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
         SELECT p.ID, '_lp_duration_type', 'minute'
         FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_lp_duration_type'
         WHERE p.post_type = 'lp_quiz' 
         AND pm.meta_id IS NULL"
    );


    // 6. Fix duration format issue - LearnPress expects "X unit" format, not just numbers
   $problematic_durations = $wpdb->get_results(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
         WHERE meta_key = '_lp_duration' 
         AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'lp_quiz')
         AND (
            meta_value IS NULL OR 
            meta_value = '' OR 
            meta_value = '0' OR
            (meta_value REGEXP '^[0-9]+$' AND meta_value NOT LIKE '% minute' AND meta_value NOT LIKE '% hour' AND meta_value NOT LIKE '% day')
         )"
    );

   

    // Fix durations that are just numbers (add "minute" unit)
    $fix_numeric_durations = $wpdb->query(
        "UPDATE {$wpdb->postmeta} 
         SET meta_value = CONCAT(meta_value, ' minute')
         WHERE meta_key = '_lp_duration' 
         AND meta_value REGEXP '^[0-9]+$'
         AND meta_value != '0'
         AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'lp_quiz')"
    );

   

    // Fix empty/null/zero durations
    $fix_empty_durations = $wpdb->query(
        "UPDATE {$wpdb->postmeta} 
         SET meta_value = '10 minute' 
         WHERE meta_key = '_lp_duration' 
         AND (meta_value IS NULL OR meta_value = '' OR meta_value = '0')
         AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'lp_quiz')"
    );

   

    // Additional check - look for any duration entries that might have unexpected values
    $all_durations = $wpdb->get_results(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
         WHERE meta_key = '_lp_duration' 
         AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'lp_quiz')"
    );

   

    // 7. CRITICAL: Force fix quiz ID 379 specifically (and any other problematic quizzes)
    $problematic_quiz_ids = [379]; // Add more IDs if needed
    foreach ($problematic_quiz_ids as $quiz_id) {
        // First, check if this quiz actually exists
        $quiz_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE ID = %d",
            $quiz_id
        ));

        if (!$quiz_exists) {
            continue;
        }

     
        // Delete any existing problematic duration entries
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ('_lp_duration', '_lp_duration_type')",
            $quiz_id
        ));

        // Insert fresh duration entries
        $wpdb->insert(
            $wpdb->postmeta,
            array(
                'post_id' => $quiz_id,
                'meta_key' => '_lp_duration_type',
                'meta_value' => 'minute'
            ),
            array('%d', '%s', '%s')
        );

        $wpdb->insert(
            $wpdb->postmeta,
            array(
                'post_id' => $quiz_id,
                'meta_key' => '_lp_duration',
                'meta_value' => '10'
            ),
            array('%d', '%s', '%s')
        );

        error_log("Quiz IA Pro: Force-inserted duration metadata for quiz ID {$quiz_id}");

        // Clear all caches for this quiz
        wp_cache_delete($quiz_id, 'post_meta');
        wp_cache_delete($quiz_id, 'posts');
        clean_post_cache($quiz_id);

        // Verify the fix worked
        $verify_duration_type = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_lp_duration_type'",
            $quiz_id
        ));

        $verify_duration = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_lp_duration'",
            $quiz_id
        ));

    }

    // 8. Get only LearnPress quizzes that were created by Quiz IA Pro
    $quiz_ia_learnpress_quizzes = $wpdb->get_col(
        "SELECT learnpress_quiz_id FROM {$quizzes_table} 
         WHERE learnpress_quiz_id IS NOT NULL 
         AND learnpress_quiz_id > 0"
    );

    if (empty($quiz_ia_learnpress_quizzes)) {
        return true;
    }

    // Filter to only include quizzes that still exist in WordPress
    $existing_lp_quizzes = array();
    foreach ($quiz_ia_learnpress_quizzes as $quiz_id) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'lp_quiz'",
            $quiz_id
        ));

        if ($exists) {
            $existing_lp_quizzes[] = $quiz_id;
        } else {
            // Clean up the reference in Quiz IA Pro table
            $wpdb->update(
                $quizzes_table,
                array('learnpress_quiz_id' => null),
                array('learnpress_quiz_id' => $quiz_id),
                array('%d'),
                array('%d')
            );
        }
    }

   
    $all_lp_quizzes = $existing_lp_quizzes;

    foreach ($all_lp_quizzes as $quiz_id) {
        // Check if duration_type is missing or invalid
        $duration_type = get_post_meta($quiz_id, '_lp_duration_type', true);
        if (empty($duration_type) || !in_array($duration_type, ['minute', 'hour', 'day', 'week'])) {
            update_post_meta($quiz_id, '_lp_duration_type', 'minute');
        }

        // Check if duration is missing, zero, or just a number (needs unit)
        $duration = get_post_meta($quiz_id, '_lp_duration', true);
        if (empty($duration) || $duration == '0') {
            update_post_meta($quiz_id, '_lp_duration', '10 minute');
        } elseif (preg_match('/^[0-9]+$/', $duration)) {
            // Duration is just a number, add the unit
            update_post_meta($quiz_id, '_lp_duration', $duration . ' minute');
        }

        // CRITICAL: Simulate LearnPress's save process to ensure proper format
        // This mimics what happens when you click "save" in LearnPress
        if (class_exists('LP_Quiz') && function_exists('wp_update_post')) {
            $quiz_post = get_post($quiz_id);
            if ($quiz_post) {
                // Trigger WordPress post update hooks that LearnPress uses
                $updated_post = array(
                    'ID' => $quiz_id,
                    'post_title' => $quiz_post->post_title,
                    'post_content' => $quiz_post->post_content,
                    'post_status' => $quiz_post->post_status
                );

                // This will trigger LearnPress's save hooks which fix the duration format
                $result = wp_update_post($updated_post, false);
                
            }
        }
    }

    // 9. Additional safety: Force refresh all quiz meta after the save process
    foreach ($all_lp_quizzes as $quiz_id) {
        // Clear any cached meta data
        wp_cache_delete($quiz_id, 'post_meta');

        // Ensure the meta is properly set one final time
        $final_duration_type = get_post_meta($quiz_id, '_lp_duration_type', true);
        $final_duration = get_post_meta($quiz_id, '_lp_duration', true);

        if (empty($final_duration_type)) {
            update_post_meta($quiz_id, '_lp_duration_type', 'minute');
            error_log("Quiz IA Pro: Final fix - added duration type for quiz ID {$quiz_id}");
        }

        if (empty($final_duration)) {
            update_post_meta($quiz_id, '_lp_duration', '10 minute');
            error_log("Quiz IA Pro: Final fix - added duration value for quiz ID {$quiz_id}");
        } elseif (preg_match('/^[0-9]+$/', $final_duration)) {
            // Final check: ensure duration has unit
            update_post_meta($quiz_id, '_lp_duration', $final_duration . ' minute');
            error_log("Quiz IA Pro: Final fix - added unit to duration for quiz ID {$quiz_id}");
        }
    }

    return true;
}

// Add AJAX action to manually fix database
add_action('wp_ajax_quiz_ai_fix_learnpress_db', function () {
    // Security checks
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    $result = quiz_ai_pro_fix_learnpress_database();

    if ($result) {
        wp_send_json_success('Database issues fixed successfully');
    } else {
        wp_send_json_error('Failed to fix database issues');
    }
});

// Add AJAX action to fix all LearnPress quizzes by simulating save
add_action('wp_ajax_quiz_ai_fix_all_learnpress_quizzes', function () {
    // Security checks
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    global $wpdb;

    try {
        // Get only Quiz IA Pro LearnPress quizzes
        $quizzes_table = $wpdb->prefix . 'quiz_ia_quizzes';
        $quiz_ia_learnpress_quizzes = $wpdb->get_col(
            "SELECT learnpress_quiz_id FROM {$quizzes_table} 
             WHERE learnpress_quiz_id IS NOT NULL 
             AND learnpress_quiz_id > 0"
        );

        if (empty($quiz_ia_learnpress_quizzes)) {
            wp_send_json_success("No Quiz IA Pro LearnPress quizzes found");
            return;
        }

        $fixed_count = 0;

        foreach ($quiz_ia_learnpress_quizzes as $quiz_id) {
            // Check if quiz still exists
            $quiz_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'lp_quiz'",
                $quiz_id
            ));

            if (!$quiz_exists) {
                // Clean up the reference
                $wpdb->update(
                    $quizzes_table,
                    array('learnpress_quiz_id' => null),
                    array('learnpress_quiz_id' => $quiz_id),
                    array('%d'),
                    array('%d')
                );
                continue;
            }

            $quiz_post = get_post($quiz_id);
            if ($quiz_post) {
                // Simulate the save process that fixes the duration
                $updated_post = array(
                    'ID' => $quiz_id,
                    'post_title' => $quiz_post->post_title,
                    'post_content' => $quiz_post->post_content,
                    'post_status' => $quiz_post->post_status
                );

                $result = wp_update_post($updated_post, false);
                if (!is_wp_error($result)) {
                    $fixed_count++;

                    // Ensure duration meta is set with proper format
                    if (empty(get_post_meta($quiz_id, '_lp_duration_type', true))) {
                        update_post_meta($quiz_id, '_lp_duration_type', 'minute');
                    }
                    $current_duration = get_post_meta($quiz_id, '_lp_duration', true);
                    if (empty($current_duration)) {
                        update_post_meta($quiz_id, '_lp_duration', '10 minute');
                    } elseif (preg_match('/^[0-9]+$/', $current_duration)) {
                        update_post_meta($quiz_id, '_lp_duration', $current_duration . ' minute');
                    }
                }
            }
        }

        wp_send_json_success("Successfully processed {$fixed_count} Quiz IA Pro LearnPress quizzes");
    } catch (Exception $e) {
        wp_send_json_error('Error fixing quizzes: ' . $e->getMessage());
    }
});

// Run the fix immediately when this file is loaded
quiz_ai_pro_fix_learnpress_database();
