<?php

/**
 * Database Functions for Quiz IA Pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/* ===========================
   DATABASE CREATION & MANAGEMENT
   =========================== */

/**
 * Fix category_id column to allow NULL values
 */
function quiz_ai_pro_fix_category_id_column()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_quizzes';

    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    if (!$table_exists) {
        return false;
    }

    // Alter the column to allow NULL
    $result = $wpdb->query("ALTER TABLE $table_name MODIFY COLUMN category_id mediumint(9) DEFAULT NULL");

    if ($result === false) {
        error_log('Quiz IA Pro: Failed to alter category_id column: ' . $wpdb->last_error);
        return false;
    }

    error_log('Quiz IA Pro: Successfully altered category_id column to allow NULL');
    return true;
}

/**
 * Create all plugin tables dynamically
 */
function quiz_ai_pro_create_all_tables()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Get all table creation functions
    $tables = [
        'quiz_ia_quizzes' => quiz_ai_pro_get_quizzes_table_sql(),
        'quiz_ia_questions' => quiz_ai_pro_get_questions_table_sql(),
        'quiz_ia_answers' => quiz_ai_pro_get_answers_table_sql(),
        'quiz_ia_results' => quiz_ai_pro_get_results_table_sql(),
        'quiz_ia_course_chunks' => quiz_ai_pro_get_course_chunks_table_sql(),
        'quiz_ia_email_preferences' => quiz_ai_pro_get_email_preferences_table_sql(),
        'quiz_ia_comments' => quiz_ai_pro_get_quiz_comments_table_sql()
    ];

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $created_tables = [];
    $failed_tables = [];

    foreach ($tables as $table_name => $sql) {
        $result = dbDelta($sql);

        // Check if table was created successfully
        $full_table_name = $wpdb->prefix . $table_name;
        if ($wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name) {
            $created_tables[] = $table_name;
        } else {
            $failed_tables[] = $table_name;
        }
    }

    // Log results
    if (!empty($created_tables)) {
        error_log('Quiz IA Pro: Tables created successfully: ' . implode(', ', $created_tables));
    }

    if (!empty($failed_tables)) {
        error_log('Quiz IA Pro: Failed to create tables: ' . implode(', ', $failed_tables));
    }

    // Fix category_id column to allow NULL values
    quiz_ai_pro_fix_category_id_column();

    // Ensure comments table is created
    quiz_ai_pro_ensure_comments_table();

    // Upgrade course chunks table if needed
    quiz_ai_pro_upgrade_course_chunks_table();

    // Upgrade quizzes table for multi-select support
    quiz_ai_pro_upgrade_quizzes_table_for_multiselect();

    // Process existing courses for RAG after table creation
    quiz_ai_pro_process_all_courses_for_rag();

    return [
        'created' => $created_tables,
        'failed' => $failed_tables,
        'success' => empty($failed_tables)
    ];
}

/**
 * Create all plugin tables dynamically (safe version for activation)
 */
function quiz_ai_pro_create_all_tables_safe()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Get all table creation functions
    $tables = [
        'quiz_ia_quizzes' => quiz_ai_pro_get_quizzes_table_sql(),
        'quiz_ia_questions' => quiz_ai_pro_get_questions_table_sql(),
        'quiz_ia_answers' => quiz_ai_pro_get_answers_table_sql(),
        'quiz_ia_results' => quiz_ai_pro_get_results_table_sql(),
        'quiz_ia_course_chunks' => quiz_ai_pro_get_course_chunks_table_sql(),
        'quiz_ia_comments' => quiz_ai_pro_get_quiz_comments_table_sql()
    ];

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $created_tables = [];
    $failed_tables = [];

    foreach ($tables as $table_name => $sql) {
        try {
            $result = dbDelta($sql);

            // Check if table was created successfully
            $full_table_name = $wpdb->prefix . $table_name;
            if ($wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name) {
                $created_tables[] = $table_name;
            } else {
                $failed_tables[] = $table_name;
            }
        } catch (Exception $e) {
            error_log('Quiz IA Pro: Error creating table ' . $table_name . ': ' . $e->getMessage());
            $failed_tables[] = $table_name;
        }
    }

    // Log results
    if (!empty($created_tables)) {
        error_log('Quiz IA Pro: Tables created successfully: ' . implode(', ', $created_tables));
    }

    if (!empty($failed_tables)) {
        error_log('Quiz IA Pro: Failed to create tables: ' . implode(', ', $failed_tables));
    }

    // Safe operations that won't fail
    try {
        // Fix category_id column to allow NULL values
        quiz_ai_pro_fix_category_id_column();

        // Upgrade course chunks table if needed
        quiz_ai_pro_upgrade_course_chunks_table();

        // Upgrade quizzes table for multi-select support
        quiz_ai_pro_upgrade_quizzes_table_for_multiselect();
    } catch (Exception $e) {
        error_log('Quiz IA Pro: Error in table upgrades: ' . $e->getMessage());
    }

    // DON'T process courses during activation - this will be done later
    error_log('Quiz IA Pro: Course RAG processing will be done after activation');

    return [
        'created' => $created_tables,
        'failed' => $failed_tables,
        'success' => empty($failed_tables)
    ];
}

/**
 * Ensure comments table exists
 */
function quiz_ai_pro_ensure_comments_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'quiz_ia_comments';

    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

    if (!$table_exists) {
        error_log('Quiz IA Pro: Comments table missing, creating it now...');

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = quiz_ai_pro_get_quiz_comments_table_sql();
        $result = dbDelta($sql);

        // Verify creation
        $table_exists_after = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if ($table_exists_after) {
            error_log('Quiz IA Pro: Comments table created successfully');
        } else {
            error_log('Quiz IA Pro: Failed to create comments table');
            error_log('Quiz IA Pro: SQL used: ' . $sql);
        }
    } else {
        error_log('Quiz IA Pro: Comments table already exists');
    }
}

/**
 * Upgrade course chunks table to support advanced RAG features
 */
function quiz_ai_pro_upgrade_course_chunks_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_course_chunks';

    try {
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            error_log('Quiz IA Pro: Cannot upgrade course_chunks table - table does not exist');
            return false;
        }

        // Check if new columns exist
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        if (empty($columns)) {
            error_log('Quiz IA Pro: Cannot read columns from course_chunks table');
            return false;
        }

        $column_names = array_column($columns, 'Field');
        $needs_upgrade = false;

        // Add tfidf_vector column if it doesn't exist
        if (!in_array('tfidf_vector', $column_names)) {
            $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN tfidf_vector longtext AFTER chunk_order");
            if ($result !== false) {
                $needs_upgrade = true;
                error_log('Quiz IA Pro: Added tfidf_vector column to course_chunks table');
            } else {
                error_log('Quiz IA Pro: Failed to add tfidf_vector column: ' . $wpdb->last_error);
            }
        }

        // Add relevance_score column if it doesn't exist
        if (!in_array('relevance_score', $column_names)) {
            $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN relevance_score decimal(10,8) DEFAULT 0 AFTER tfidf_vector");
            if ($result !== false) {
                $wpdb->query("ALTER TABLE $table_name ADD KEY relevance_score (relevance_score)");
                $needs_upgrade = true;
                error_log('Quiz IA Pro: Added relevance_score column to course_chunks table');
            } else {
                error_log('Quiz IA Pro: Failed to add relevance_score column: ' . $wpdb->last_error);
            }
        }

        // Update FULLTEXT index if needed
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table_name WHERE Key_name = 'fulltext_search'");
        if (empty($indexes)) {
            // Drop old index if exists (ignore errors)
            $wpdb->query("ALTER TABLE $table_name DROP INDEX chunk_text_keywords");

            // Add new comprehensive FULLTEXT index
            $result = $wpdb->query("ALTER TABLE $table_name ADD FULLTEXT KEY fulltext_search (chunk_text, keywords, summary)");
            if ($result !== false) {
                $needs_upgrade = true;
                error_log('Quiz IA Pro: Updated FULLTEXT index on course_chunks table');
            } else {
                error_log('Quiz IA Pro: Failed to update FULLTEXT index: ' . $wpdb->last_error);
            }
        }

        if ($needs_upgrade) {
            error_log('Quiz IA Pro: Successfully upgraded course_chunks table for advanced RAG');
        } else {
            error_log('Quiz IA Pro: Course_chunks table already up to date');
        }

        return $needs_upgrade;
    } catch (Exception $e) {
        error_log('Quiz IA Pro: Error upgrading course_chunks table: ' . $e->getMessage());
        return false;
    }
}

/**
 * Upgrade quizzes table to support multi-select courses and categories
 */
function quiz_ai_pro_upgrade_quizzes_table_for_multiselect()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_quizzes';

    try {
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            error_log('Quiz IA Pro: Cannot upgrade quizzes table - table does not exist');
            return false;
        }

        // Check current column types
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        if (empty($columns)) {
            error_log('Quiz IA Pro: Cannot read columns from quizzes table');
            return false;
        }

        $column_info = [];
        foreach ($columns as $column) {
            $column_info[$column->Field] = $column->Type;
        }

        $needs_upgrade = false;

        // Check if course_id needs to be upgraded to JSON
        if (isset($column_info['course_id']) && strpos($column_info['course_id'], 'json') === false) {
            // First, migrate existing single course_id values to JSON format in settings
            $existing_quizzes = $wpdb->get_results("SELECT id, course_id, category_id, settings FROM $table_name WHERE course_id IS NOT NULL OR category_id IS NOT NULL");

            foreach ($existing_quizzes as $quiz) {
                $settings = $quiz->settings ? json_decode($quiz->settings, true) : [];

                // Migrate course_id to settings if not already there
                if ($quiz->course_id && !isset($settings['selected_courses'])) {
                    $settings['selected_courses'] = [$quiz->course_id];
                }

                // Migrate category_id to settings if not already there
                if ($quiz->category_id && !isset($settings['selected_categories'])) {
                    $settings['selected_categories'] = [$quiz->category_id];
                }

                // Update settings
                $wpdb->update(
                    $table_name,
                    ['settings' => wp_json_encode($settings)],
                    ['id' => $quiz->id]
                );
            }

            // Now change course_id to JSON type
            $result = $wpdb->query("ALTER TABLE $table_name MODIFY COLUMN course_id JSON");
            if ($result !== false) {
                error_log('Quiz IA Pro: Updated course_id column to JSON type');
                $needs_upgrade = true;
            } else {
                error_log('Quiz IA Pro: Failed to update course_id column: ' . $wpdb->last_error);
            }
        }

        // Check if category_id needs to be upgraded to JSON
        if (isset($column_info['category_id']) && strpos($column_info['category_id'], 'json') === false) {
            $result = $wpdb->query("ALTER TABLE $table_name MODIFY COLUMN category_id JSON");
            if ($result !== false) {
                error_log('Quiz IA Pro: Updated category_id column to JSON type');
                $needs_upgrade = true;
            } else {
                error_log('Quiz IA Pro: Failed to update category_id column: ' . $wpdb->last_error);
            }
        }

        // Now update all quizzes to use JSON format based on settings
        $all_quizzes = $wpdb->get_results("SELECT id, settings FROM $table_name");

        foreach ($all_quizzes as $quiz) {
            $settings = $quiz->settings ? json_decode($quiz->settings, true) : [];

            $course_ids_json = null;
            $category_ids_json = null;

            if (isset($settings['selected_courses']) && !empty($settings['selected_courses'])) {
                $course_ids_json = wp_json_encode(array_map('intval', $settings['selected_courses']));
            }

            if (isset($settings['selected_categories']) && !empty($settings['selected_categories'])) {
                $category_ids_json = wp_json_encode(array_map('intval', $settings['selected_categories']));
            }

            // Update with JSON values
            $wpdb->update(
                $table_name,
                [
                    'course_id' => $course_ids_json,
                    'category_id' => $category_ids_json
                ],
                ['id' => $quiz->id]
            );
        }

        if ($needs_upgrade) {
            error_log('Quiz IA Pro: Successfully upgraded quizzes table for multi-select support');
        } else {
            error_log('Quiz IA Pro: Quizzes table already supports multi-select');
        }

        return $needs_upgrade;
    } catch (Exception $e) {
        error_log('Quiz IA Pro: Error upgrading quizzes table: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get quizzes table SQL
 */
function quiz_ai_pro_get_quizzes_table_sql()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_quizzes';
    $charset_collate = $wpdb->get_charset_collate();

    return "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        description text,
        course_id JSON DEFAULT NULL,
        category_id JSON DEFAULT NULL,
        quiz_type varchar(50) DEFAULT 'qcm',
        form_type varchar(50) DEFAULT 'quiz',
        grading_system varchar(50) DEFAULT 'correct_incorrect',
        featured_image varchar(500),
        time_limit int(11) DEFAULT 0,
        questions_per_page int(11) DEFAULT 0,
        total_questions int(11) DEFAULT 0,
        settings longtext,
        ai_provider varchar(50) DEFAULT 'gemini',
        ai_generated tinyint(1) DEFAULT 0,
        ai_instructions text,
        quiz_code varchar(20),
        status varchar(20) DEFAULT 'draft',
        views int(11) DEFAULT 0,
        participants int(11) DEFAULT 0,
        learnpress_quiz_id bigint(20) UNSIGNED DEFAULT NULL,
        created_by bigint(20) UNSIGNED,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY quiz_code (quiz_code),
        KEY created_by (created_by),
        KEY status (status),
        KEY quiz_type (quiz_type),
        KEY ai_generated (ai_generated),
        KEY learnpress_quiz_id (learnpress_quiz_id)
    ) $charset_collate;";
}

/**
 * Get questions table SQL
 */
function quiz_ai_pro_get_questions_table_sql()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_questions';
    $charset_collate = $wpdb->get_charset_collate();

    return "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        quiz_id mediumint(9) NOT NULL,
        question_text longtext NOT NULL,
        question_type varchar(50) DEFAULT 'qcm',
        correct_answer text,
        points int(11) DEFAULT 1,
        explanation text,
        course_reference text,
        featured_image varchar(500),
        sort_order int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY quiz_id (quiz_id),
        KEY question_type (question_type),
        KEY sort_order (sort_order)
    ) $charset_collate;";
}

/**
 * Get answers table SQL
 */
function quiz_ai_pro_get_answers_table_sql()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_answers';
    $charset_collate = $wpdb->get_charset_collate();

    return "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        question_id mediumint(9) NOT NULL,
        answer_text text NOT NULL,
        is_correct tinyint(1) DEFAULT 0,
        sort_order int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY question_id (question_id),
        KEY is_correct (is_correct),
        KEY sort_order (sort_order)
    ) $charset_collate;";
}

/**
 * Get results table SQL
 */
function quiz_ai_pro_get_results_table_sql()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_results';
    $charset_collate = $wpdb->get_charset_collate();

    return "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        quiz_id mediumint(9) NOT NULL,
        user_email varchar(100),
        user_name varchar(100),
        user_id bigint(20) UNSIGNED,
        score int(11) DEFAULT 0,
        total_questions int(11) DEFAULT 0,
        correct_answers int(11) DEFAULT 0,
        time_taken int(11) DEFAULT 0,
        percentage decimal(5,2) DEFAULT 0,
        status varchar(20) DEFAULT 'completed',
        answers_data longtext,
        questions_data longtext,
        user_answers_json longtext,
        attempt_number int(11) DEFAULT 1,
        started_at datetime,
        completed_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY quiz_id (quiz_id),
        KEY user_id (user_id),
        KEY status (status),
        KEY percentage (percentage),
        KEY completed_at (completed_at),
        KEY user_quiz_attempt (user_email, quiz_id, attempt_number)
    ) $charset_collate;";
}

/**
 * Get quiz-courses relationship table SQL
 */
/**
 * Get course chunks table SQL (for RAG processing)
 */
function quiz_ai_pro_get_course_chunks_table_sql()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_course_chunks';
    $charset_collate = $wpdb->get_charset_collate();

    return "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        course_id mediumint(9) NOT NULL,
        chunk_text longtext NOT NULL,
        keywords text,
        summary text,
        word_count int(11) DEFAULT 0,
        chunk_order int(11) DEFAULT 0,
        tfidf_vector longtext,
        relevance_score decimal(10,8) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY course_id (course_id),
        KEY word_count (word_count),
        KEY chunk_order (chunk_order),
        KEY relevance_score (relevance_score),
        FULLTEXT KEY fulltext_search (chunk_text, keywords, summary)
    ) $charset_collate;";
}

/**
 * Get email preferences table SQL
 */
function quiz_ai_pro_get_email_preferences_table_sql()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_email_preferences';
    $charset_collate = $wpdb->get_charset_collate();

    return "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_email varchar(100) NOT NULL,
        user_name varchar(100),
        user_id bigint(20) UNSIGNED,
        receive_quiz_results tinyint(1) DEFAULT 1,
        receive_new_quiz_alerts tinyint(1) DEFAULT 1,
        quiz_id mediumint(9),
        preferences_json text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_user_quiz (user_email, quiz_id),
        KEY user_email (user_email),
        KEY user_id (user_id),
        KEY quiz_id (quiz_id),
        KEY receive_quiz_results (receive_quiz_results),
        KEY receive_new_quiz_alerts (receive_new_quiz_alerts)
    ) $charset_collate;";
}

/**
 * Check if all tables exist
 */
function quiz_ai_pro_check_all_tables()
{
    global $wpdb;

    $required_tables = [
        'quiz_ia_quizzes',
        'quiz_ia_questions',
        'quiz_ia_answers',
        'quiz_ia_results',
        'quiz_ia_course_chunks',
        'quiz_ia_email_preferences',
        'quiz_ia_comments'
    ];

    $existing_tables = [];
    $missing_tables = [];

    foreach ($required_tables as $table) {
        $full_table_name = $wpdb->prefix . $table;
        if ($wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name) {
            $existing_tables[] = $table;
        } else {
            $missing_tables[] = $table;
        }
    }

    return [
        'existing' => $existing_tables,
        'missing' => $missing_tables,
        'all_exist' => empty($missing_tables)
    ];
}

/**
 * Drop all plugin tables (for uninstall)
 */
function quiz_ai_pro_drop_all_tables()
{
    global $wpdb;

    $tables = [
        'quiz_ia_results',
        'quiz_ia_answers',
        'quiz_ia_questions',
        'quiz_ia_course_chunks',
        'quiz_ia_quizzes'
    ];

    $dropped_tables = [];
    $failed_tables = [];

    foreach ($tables as $table) {
        $full_table_name = $wpdb->prefix . $table;
        $result = $wpdb->query("DROP TABLE IF EXISTS $full_table_name");

        if ($result !== false) {
            $dropped_tables[] = $table;
        } else {
            $failed_tables[] = $table;
        }
    }

    return [
        'dropped' => $dropped_tables,
        'failed' => $failed_tables,
        'success' => empty($failed_tables)
    ];
}

/**
 * Get database info and status
 */
function quiz_ai_pro_get_database_info()
{
    global $wpdb;

    $info = [];

    // WordPress database info
    $info['wp_version'] = get_bloginfo('version');
    $info['mysql_version'] = $wpdb->db_version();
    $info['db_charset'] = $wpdb->charset;
    $info['db_collate'] = $wpdb->collate;

    // Table status
    $table_check = quiz_ai_pro_check_all_tables();
    $info['tables_status'] = $table_check;

    // Table sizes
    $info['table_sizes'] = [];
    foreach ($table_check['existing'] as $table) {
        $full_table_name = $wpdb->prefix . $table;
        $size_query = $wpdb->get_row("
            SELECT 
                table_name,
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                table_rows
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE() 
            AND table_name = '$full_table_name'
        ");

        if ($size_query) {
            $info['table_sizes'][$table] = [
                'size_mb' => $size_query->size_mb,
                'rows' => $size_query->table_rows
            ];
        }
    }

    return $info;
}

/* ===========================
   DASHBOARD STATISTICS
   =========================== */

function quiz_ai_pro_get_dashboard_stats()
{
    global $wpdb;

    $stats = [];

    // Get table names - using LearnPress courses instead of custom courses
    $learnpress_courses_table = $wpdb->prefix . 'learnpress_courses';
    $quizzes_table = $wpdb->prefix . 'quiz_ia_quizzes';
    $students_table = $wpdb->prefix . 'quiz_ia_results'; // Using results for student tracking
    $attempts_table = $wpdb->prefix . 'quiz_ia_results'; // Using results for attempts

    // Check if LearnPress table exists
    $lp_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$learnpress_courses_table'");

    // Total contents (courses) - use LearnPress if available, otherwise 0
    if ($lp_table_exists) {
        $stats['total_contents'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $learnpress_courses_table WHERE post_status = 'publish'");

        // New contents this month
        $stats['new_contents_this_month'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $learnpress_courses_table 
             WHERE post_status = 'publish' 
             AND post_date_gmt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
        );
    } else {
        $stats['total_contents'] = 0;
        $stats['new_contents_this_month'] = 0;
    }

    // Total quizzes
    $stats['total_quizzes'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $quizzes_table");

    // AI generated percentage
    $ai_generated = (int) $wpdb->get_var("SELECT COUNT(*) FROM $quizzes_table WHERE ai_generated = 1");
    $stats['ai_generated_percentage'] = $stats['total_quizzes'] > 0 ?
        round(($ai_generated / $stats['total_quizzes']) * 100) : 0;

    // Total students (unique users from results)
    $stats['total_students'] = (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_email) FROM $students_table WHERE user_email IS NOT NULL");

    // New students this week
    $stats['new_students_this_week'] = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT user_email) FROM $students_table 
         WHERE user_email IS NOT NULL
         AND completed_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)"
    );

    // Total attempts
    $stats['total_attempts'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $attempts_table");

    // Average score
    $avg_score = $wpdb->get_var("SELECT AVG(percentage) FROM $attempts_table");
    $stats['average_score'] = $avg_score ? round($avg_score, 1) : 0;

    // Top quiz
    $top_quiz = $wpdb->get_row(
        "SELECT q.title, COUNT(a.id) as attempt_count 
         FROM $quizzes_table q 
         LEFT JOIN $attempts_table a ON q.id = a.quiz_id 
         GROUP BY q.id 
         ORDER BY attempt_count DESC 
         LIMIT 1"
    );
    $stats['top_quiz_title'] = $top_quiz ? $top_quiz->title : 'Aucun quiz';

    // Success rate (passing score 60%)
    $success_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $attempts_table WHERE percentage >= 60");
    $stats['success_rate'] = $stats['total_attempts'] > 0 ?
        round(($success_count / $stats['total_attempts']) * 100) : 0;

    return $stats;
}

function quiz_ai_pro_get_recent_activities($limit = 10)
{
    global $wpdb;

    $activities = [];

    // Get recent quiz creations
    $quizzes_table = $wpdb->prefix . 'quiz_ia_quizzes';
    $recent_quizzes = $wpdb->get_results($wpdb->prepare(
        "SELECT title, ai_generated, created_at 
         FROM $quizzes_table 
         ORDER BY created_at DESC 
         LIMIT %d",
        $limit / 2
    ));

    foreach ($recent_quizzes as $quiz) {
        $activities[] = (object) [
            'icon' => $quiz->ai_generated ? '🤖' : '✏️',
            'description' => sprintf(
                '%s créé: %s',
                $quiz->ai_generated ? 'Quiz IA' : 'Quiz manuel',
                esc_html($quiz->title)
            ),
            'created_at' => $quiz->created_at
        ];
    }

    // Get recent attempts using results table
    $attempts_table = $wpdb->prefix . 'quiz_ia_results';
    $recent_attempts = $wpdb->get_results($wpdb->prepare(
        "SELECT q.title as quiz_title, r.user_name, r.percentage, r.completed_at
         FROM $attempts_table r
         JOIN $quizzes_table q ON r.quiz_id = q.id
         WHERE r.user_name IS NOT NULL
         ORDER BY r.completed_at DESC
         LIMIT %d",
        $limit / 2
    ));

    foreach ($recent_attempts as $attempt) {
        $student_name = $attempt->user_name ?: 'Étudiant';

        $activities[] = (object) [
            'icon' => $attempt->percentage >= 60 ? '✅' : '📝',
            'description' => sprintf(
                '%s a terminé "%s" (%s%%)',
                esc_html($student_name),
                esc_html($attempt->quiz_title),
                round($attempt->percentage)
            ),
            'created_at' => $attempt->completed_at
        ];
    }

    // Sort by date
    usort($activities, function ($a, $b) {
        return strtotime($b->created_at) - strtotime($a->created_at);
    });

    return array_slice($activities, 0, $limit);
}

/* ===========================
   CONTENT MANAGEMENT
   =========================== */

function quiz_ai_pro_get_content($content_id)
{
    // Try to get LearnPress course first
    $course = quiz_ai_pro_get_learnpress_course_by_id($content_id);

    if ($course) {
        // Convert LearnPress course format to expected format
        $course->id = $course->ID;
        $course->content = $course->post_content;
        $course->description = wp_trim_words(wp_strip_all_tags($course->post_content), 30);

        // Get assigned categories
        $course->categories = quiz_ai_pro_get_object_categories($content_id, 'course_category');
    }

    return $course;
}

/**
 * Get course with full category information
 */
function quiz_ai_pro_get_course_with_categories($course_id)
{
    // Use LearnPress course data
    $course = quiz_ai_pro_get_learnpress_course_by_id($course_id);

    if ($course) {
        // Convert format
        $course->id = $course->ID;
        $course->content = $course->post_content;

        // Get assigned categories with full details
        $course->assigned_categories = quiz_ai_pro_get_object_categories($course_id, 'course_category');

        // For backward compatibility, create category_ids_array
        $course->category_ids_array = [];
        if ($course->assigned_categories) {
            foreach ($course->assigned_categories as $cat) {
                $course->category_ids_array[] = $cat->id;
            }
        }
    }

    return $course;
}

function quiz_ai_pro_get_all_contents($args = [])
{
    global $wpdb;

    $learnpress_courses_table = $wpdb->prefix . 'learnpress_courses';

    // Check if LearnPress table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$learnpress_courses_table'");

    if (!$table_exists) {
        return [];
    }

    $defaults = [
        'status' => 'publish',
        'orderby' => 'post_date_gmt',
        'order' => 'DESC',
        'limit' => 50
    ];

    $args = wp_parse_args($args, $defaults);

    $where = "WHERE post_status = %s";
    $params = [$args['status']];

    $orderby = in_array($args['orderby'], ['post_title', 'post_date_gmt', 'ID']) ?
        $args['orderby'] : 'post_date_gmt';
    $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

    $query = "SELECT ID as id, post_title as title, post_content as content, post_date_gmt as created_at 
              FROM $learnpress_courses_table $where ORDER BY $orderby $order";

    if ($args['limit'] > 0) {
        $query .= " LIMIT " . intval($args['limit']);
    }

    return $wpdb->get_results($wpdb->prepare($query, ...$params));
}

/* ===========================
   QUIZ MANAGEMENT
   =========================== */

function quiz_ai_pro_save_quiz($data)
{
    global $wpdb;

    $table = $wpdb->prefix . 'quiz_ia_quizzes';

    $insert_data = [
        'title' => sanitize_text_field($data['title']),
        'description' => sanitize_textarea_field($data['description']),
        'course_id' => isset($data['content_id']) ? intval($data['content_id']) : null, // Map content_id to course_id
        'ai_generated' => isset($data['ai_generated']) ? (bool) $data['ai_generated'] : false,
        'status' => isset($data['status']) && in_array($data['status'], ['draft', 'published']) ?
            $data['status'] : 'draft',
        'created_by' => get_current_user_id()
    ];

    // Handle questions data
    if (isset($data['questions'])) {
        // For now, store in settings until we implement proper question handling
        $insert_data['settings'] = wp_json_encode(['questions' => $data['questions']]);
    }

    $result = $wpdb->insert($table, $insert_data);

    if ($result !== false) {
        return $wpdb->insert_id;
    }

    return false;
}

function quiz_ai_pro_get_quiz($quiz_id)
{
    global $wpdb;

    $table = $wpdb->prefix . 'quiz_ia_quizzes';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $quiz_id
    ));
}

/* ===========================
   STUDENT MANAGEMENT
   =========================== */

function quiz_ai_pro_get_or_create_student($email, $additional_data = [])
{
    global $wpdb;

    $table = $wpdb->prefix . 'quiz_ia_results';

    // Try to find existing student in results
    $student = $wpdb->get_row($wpdb->prepare(
        "SELECT DISTINCT user_email as email, user_name, user_id FROM $table WHERE user_email = %s LIMIT 1",
        $email
    ));

    if ($student) {
        // Convert to expected format
        return (object) [
            'id' => $student->user_id ?: 0,
            'email' => $student->email,
            'first_name' => isset($additional_data['first_name']) ? $additional_data['first_name'] : '',
            'last_name' => isset($additional_data['last_name']) ? $additional_data['last_name'] : '',
            'level' => isset($additional_data['level']) ? $additional_data['level'] : 'beginner'
        ];
    }

    // Return new student format (will be created when they take a quiz)
    return (object) [
        'id' => 0,
        'email' => $email,
        'first_name' => isset($additional_data['first_name']) ? $additional_data['first_name'] : '',
        'last_name' => isset($additional_data['last_name']) ? $additional_data['last_name'] : '',
        'level' => isset($additional_data['level']) ? $additional_data['level'] : 'beginner'
    ];
}

function quiz_ai_pro_get_student($student_id)
{
    global $wpdb;

    $table = $wpdb->prefix . 'quiz_ia_results';

    // Get student info from results table
    $student = $wpdb->get_row($wpdb->prepare(
        "SELECT DISTINCT user_id, user_email as email, user_name FROM $table WHERE user_id = %d LIMIT 1",
        $student_id
    ));

    if ($student) {
        // Parse name if available
        $name_parts = explode(' ', $student->user_name ?: '', 2);
        return (object) [
            'id' => $student->user_id,
            'email' => $student->email,
            'first_name' => $name_parts[0] ?? '',
            'last_name' => $name_parts[1] ?? '',
            'level' => 'intermediate'
        ];
    }

    return false;
}

/* ===========================
   SYSTEM CHECKS
   =========================== */

function quiz_ai_pro_check_database()
{
    global $wpdb;

    // Check Quiz IA tables
    [
        $wpdb->prefix . 'quiz_ia_quizzes',
        $wpdb->prefix . 'quiz_ia_questions',
        $wpdb->prefix . 'quiz_ia_answers',
        $wpdb->prefix . 'quiz_ia_course_chunks',
        $wpdb->prefix . 'quiz_ia_results'
    ];

    foreach ($tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            error_log("[QUIZ_IA] Table manquante: $table");
            return false;
        }
    }

    // Check LearnPress courses table exists
    $learnpress_courses_table = $wpdb->prefix . 'learnpress_courses';
    $lp_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$learnpress_courses_table'");

    if (!$lp_table_exists) {
        error_log("[QUIZ_IA] LearnPress courses table missing: $learnpress_courses_table");
        return false;
    }

    // Check LearnPress courses exist
    $courses_count = $wpdb->get_var("SELECT COUNT(*) FROM $learnpress_courses_table WHERE post_status = 'publish'");
    $categories_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}term_taxonomy WHERE taxonomy = 'course_category'");

    if ($courses_count == 0 || $categories_count == 0) {
        error_log("[QUIZ_IA] Tables vides - Cours LearnPress: $courses_count, Catégories: $categories_count");
        return false;
    }

    return true;
}

function quiz_ai_pro_check_ai_config()
{
    $provider = get_option('quiz_ai_pro_ai_provider', 'gemini');

    switch ($provider) {
        case 'gemini':
            return !empty(get_option('quiz_ai_gemini_api_key'));
        default:
            return false;
    }
}

function quiz_ai_pro_check_email_config()
{
    // Check if WordPress can send emails
    $to = get_option('admin_email');
    $subject = 'Quiz IA Pro - Test Email';
    $message = 'This is a test email from Quiz IA Pro plugin.';

    // We won't actually send the email, just check if the function exists and is configured
    return function_exists('wp_mail') && !empty($to);
}

/**
 * Get quiz statistics
 */
function quiz_ai_pro_get_quiz_statistics()
{
    global $wpdb;

    $stats = [];

    // Total quizzes
    $stats['total'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}quiz_ia_quizzes"
    );

    // Published quizzes
    $stats['published'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}quiz_ia_quizzes WHERE status = 'published'"
    );

    // AI generated quizzes
    $stats['ai_generated'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}quiz_ia_quizzes WHERE ai_generated = 1"
    );

    // Pending quizzes
    $stats['pending'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}quiz_ia_quizzes WHERE status = 'draft'"
    );

    // Average score
    $avg_score = $wpdb->get_var(
        "SELECT AVG(percentage) FROM {$wpdb->prefix}quiz_ia_results"
    );
    $stats['avg_score'] = $avg_score ? round($avg_score, 1) : 0;

    return $stats;
}

/**
 * Get content quiz count
 */
function quiz_ai_pro_get_content_quiz_count($content_id)
{
    global $wpdb;

    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}quiz_ia_quizzes WHERE course_id = %d",
        $content_id
    ));
}

/**
 * Get pending quizzes
 */
function quiz_ai_pro_get_pending_quizzes($limit = 5)
{
    global $wpdb;

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}quiz_ia_quizzes 
         WHERE status = 'pending' OR status = 'draft'
         ORDER BY created_at DESC 
         LIMIT %d",
        $limit
    ));
}



/**
 * Get content statistics
 */
function quiz_ai_pro_get_content_statistics()
{
    global $wpdb;

    $stats = [];

    // Use LearnPress courses
    $learnpress_courses_table = $wpdb->prefix . 'learnpress_courses';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$learnpress_courses_table'");

    if ($table_exists) {
        // Total contents
        $stats['total'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $learnpress_courses_table"
        );

        // Published contents
        $stats['published'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $learnpress_courses_table WHERE post_status = 'publish'"
        );

        // Contents with quizzes
        $stats['with_quizzes'] = $wpdb->get_var(
            "SELECT COUNT(DISTINCT course_id) FROM {$wpdb->prefix}quiz_ia_quizzes WHERE course_id IS NOT NULL"
        );

        // Estimate total word count from content
        $content_length = $wpdb->get_var(
            "SELECT SUM(CHAR_LENGTH(post_content)) FROM $learnpress_courses_table WHERE post_status = 'publish'"
        );
        $stats['word_count'] = $content_length ? intval($content_length / 5) : 0; // Rough estimate: 5 chars per word
    } else {
        $stats['total'] = 0;
        $stats['published'] = 0;
        $stats['with_quizzes'] = 0;
        $stats['word_count'] = 0;
    }

    return $stats;
}

/* ===========================
   COURSES & CATEGORIES DATA
   =========================== */

/**
 * Get all courses for dropdown
 */
function quiz_ai_pro_get_courses_for_dropdown()
{
    global $wpdb;

    $learnpress_courses_table = $wpdb->prefix . 'learnpress_courses';

    // Check if LearnPress table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$learnpress_courses_table'");

    if (!$table_exists) {
        return [];
    }

    return $wpdb->get_results(
        "SELECT ID as id, post_title as title FROM $learnpress_courses_table WHERE post_status = 'publish' ORDER BY post_title ASC"
    );
}

/**
 * Get LearnPress course by ID
 */
function quiz_ai_pro_get_learnpress_course_by_id($course_id)
{
    global $wpdb;

    $learnpress_courses_table = $wpdb->prefix . 'learnpress_courses';

    // Check if LearnPress table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$learnpress_courses_table'");

    if (!$table_exists) {
        return false;
    }

    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT ID, post_title as title, post_content, post_status 
         FROM $learnpress_courses_table 
         WHERE ID = %d AND post_status = 'publish'",
        $course_id
    ));

    return $course;
}

/**
 * Get LearnPress course sections and content
 */
function quiz_ai_pro_get_learnpress_course_sections($course_id)
{
    global $wpdb;

    $sections_table = $wpdb->prefix . 'learnpress_sections';

    // Check if LearnPress sections table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$sections_table'");

    if (!$table_exists) {
        return [];
    }

    $sections = $wpdb->get_results($wpdb->prepare(
        "SELECT section_id, section_name, section_description, section_order 
         FROM $sections_table 
         WHERE section_course_id = %d 
         ORDER BY section_order ASC",
        $course_id
    ));

    return $sections ? $sections : [];
}

/**
 * Get all categories for dropdown
 */
function quiz_ai_pro_get_categories_for_dropdown()
{
    global $wpdb;

    return $wpdb->get_results(
        "SELECT t.term_id as id, t.name, tt.description 
         FROM {$wpdb->prefix}terms t 
         INNER JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id 
         WHERE tt.taxonomy = 'course_category' 
         ORDER BY t.name ASC"
    );
}

/**
 * Get course by ID
 */
function quiz_ai_pro_get_course_by_id($course_id)
{
    // Use LearnPress course data
    return quiz_ai_pro_get_learnpress_course_by_id($course_id);
}

/**
 * Get category by ID
 */
function quiz_ai_pro_get_category_by_id($category_id)
{
    global $wpdb;

    return $wpdb->get_row($wpdb->prepare(
        "SELECT t.term_id as id, t.name, t.slug, tt.description, tt.parent 
         FROM {$wpdb->prefix}terms t 
         INNER JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id 
         WHERE tt.taxonomy = 'course_category' AND t.term_id = %d",
        $category_id
    ));
}

/**
 * Get categories assigned to a specific object (course, post, etc.)
 */
function quiz_ai_pro_get_object_categories($object_id, $taxonomy = 'course_category')
{
    global $wpdb;

    return $wpdb->get_results($wpdb->prepare(
        "SELECT t.term_id as id, t.name, t.slug, tt.description 
         FROM {$wpdb->prefix}terms t 
         INNER JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id 
         INNER JOIN {$wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id 
         WHERE tt.taxonomy = %s AND tr.object_id = %d 
         ORDER BY t.name ASC",
        $taxonomy,
        $object_id
    ));
}

/**
 * Assign category to object (course, post, etc.)
 */
function quiz_ai_pro_assign_category_to_object($object_id, $category_id, $taxonomy = 'course_category')
{
    global $wpdb;

    // First get the term_taxonomy_id
    $term_taxonomy_id = $wpdb->get_var($wpdb->prepare(
        "SELECT term_taxonomy_id FROM {$wpdb->prefix}term_taxonomy 
         WHERE term_id = %d AND taxonomy = %s",
        $category_id,
        $taxonomy
    ));

    if (!$term_taxonomy_id) {
        return false;
    }

    // Check if relationship already exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}term_relationships 
         WHERE object_id = %d AND term_taxonomy_id = %d",
        $object_id,
        $term_taxonomy_id
    ));

    if ($exists) {
        return true; // Already assigned
    }

    // Insert the relationship
    $result = $wpdb->insert(
        $wpdb->prefix . 'term_relationships',
        [
            'object_id' => $object_id,
            'term_taxonomy_id' => $term_taxonomy_id,
            'term_order' => 0
        ]
    );

    if ($result) {
        // Update the count in term_taxonomy
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}term_taxonomy 
             SET count = count + 1 
             WHERE term_taxonomy_id = %d",
            $term_taxonomy_id
        ));
    }

    return $result !== false;
}

/**
 * Remove category from object
 */
function quiz_ai_pro_remove_category_from_object($object_id, $category_id, $taxonomy = 'course_category')
{
    global $wpdb;

    // Get the term_taxonomy_id
    $term_taxonomy_id = $wpdb->get_var($wpdb->prepare(
        "SELECT term_taxonomy_id FROM {$wpdb->prefix}term_taxonomy 
         WHERE term_id = %d AND taxonomy = %s",
        $category_id,
        $taxonomy
    ));

    if (!$term_taxonomy_id) {
        return false;
    }

    // Remove the relationship
    $result = $wpdb->delete(
        $wpdb->prefix . 'term_relationships',
        [
            'object_id' => $object_id,
            'term_taxonomy_id' => $term_taxonomy_id
        ]
    );

    if ($result) {
        // Update the count in term_taxonomy
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}term_taxonomy 
             SET count = count - 1 
             WHERE term_taxonomy_id = %d AND count > 0",
            $term_taxonomy_id
        ));
    }

    return $result !== false;
}

/**
 * Get all quizzes with course and category names (supports multi-select)
 */
function quiz_ai_pro_get_all_quizzes_with_details($limit = 50, $offset = 0)
{
    global $wpdb;

    $quizzes_table = $wpdb->prefix . 'quiz_ia_quizzes';
    $learnpress_courses_table = $wpdb->prefix . 'posts';

    // Log the query parameters
    error_log("Fetching quizzes with limit: $limit and offset: $offset");

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            q.*,
            (SELECT COUNT(*) FROM {$wpdb->prefix}quiz_ia_questions WHERE quiz_id = q.id) as question_count
        FROM $quizzes_table q
        ORDER BY q.created_at DESC
        LIMIT %d OFFSET %d",
        $limit,
        $offset
    ));

    // Now process each quiz to get course and category names
    foreach ($results as $quiz) {
        // Handle course names (JSON array of course IDs)
        $quiz->course_titles = [];
        if (!empty($quiz->course_id)) {
            $course_ids = json_decode($quiz->course_id, true);
            if (is_array($course_ids)) {
                foreach ($course_ids as $course_id) {
                    $course = $wpdb->get_row($wpdb->prepare(
                        "SELECT post_title FROM $learnpress_courses_table 
                         WHERE ID = %d AND post_type = 'lp_course' AND post_status = 'publish'",
                        $course_id
                    ));
                    if ($course) {
                        $quiz->course_titles[] = $course->post_title;
                    }
                }
            }
        }

        // Handle category names (JSON array of category IDs)
        $quiz->category_names = [];
        if (!empty($quiz->category_id)) {
            $category_ids = json_decode($quiz->category_id, true);
            if (is_array($category_ids)) {
                foreach ($category_ids as $category_id) {
                    $category = $wpdb->get_row($wpdb->prepare(
                        "SELECT t.name FROM {$wpdb->prefix}terms t 
                         INNER JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id 
                         WHERE t.term_id = %d AND tt.taxonomy = 'course_category'",
                        $category_id
                    ));
                    if ($category) {
                        $quiz->category_names[] = $category->name;
                    }
                }
            }
        }

        // For backward compatibility, create concatenated strings
        $quiz->course_title = !empty($quiz->course_titles) ? implode(', ', $quiz->course_titles) : 'Aucun cours';
        $quiz->category_name = !empty($quiz->category_names) ? implode(', ', $quiz->category_names) : 'Aucune catégorie';
    }

    // Log the number of results fetched
    error_log("Fetched " . count($results) . " quizzes from the database");

    return $results;
}


/**
 * Save quiz data
 */
function quiz_ai_pro_save_quiz_data($quiz_data)
{
    global $wpdb;

    $table = $wpdb->prefix . 'quiz_ia_quizzes';

    // Generate unique quiz code if not provided
    if (empty($quiz_data['quiz_code'])) {
        $quiz_data['quiz_code'] = quiz_ai_pro_generate_quiz_code();
    }

    // Set created_by to current user
    if (empty($quiz_data['created_by'])) {
        $quiz_data['created_by'] = get_current_user_id();
    }

    // Handle multi-select course IDs
    if (isset($quiz_data['course_id'])) {
        if (is_array($quiz_data['course_id'])) {
            $quiz_data['course_id'] = wp_json_encode(array_map('intval', $quiz_data['course_id']));
        } elseif (is_numeric($quiz_data['course_id'])) {
            $quiz_data['course_id'] = wp_json_encode([intval($quiz_data['course_id'])]);
        }
    }

    // Handle multi-select category IDs
    if (isset($quiz_data['category_id'])) {
        if (is_array($quiz_data['category_id'])) {
            $quiz_data['category_id'] = wp_json_encode(array_map('intval', $quiz_data['category_id']));
        } elseif (is_numeric($quiz_data['category_id'])) {
            $quiz_data['category_id'] = wp_json_encode([intval($quiz_data['category_id'])]);
        }
    }

    // Sanitize settings data
    if (isset($quiz_data['settings']) && is_array($quiz_data['settings'])) {
        $quiz_data['settings'] = wp_json_encode($quiz_data['settings']);
    }

    $result = $wpdb->insert($table, $quiz_data);

    if ($result !== false) {
        return $wpdb->insert_id;
    }

    return false;
}

/**
 * Generate unique quiz code
 */
function quiz_ai_pro_generate_quiz_code()
{
    global $wpdb;

    $table = $wpdb->prefix . 'quiz_ia_quizzes';

    do {
        $code = 'QZ' . wp_rand(100000, 999999);
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE quiz_code = %s",
            $code
        ));
    } while ($exists);

    return $code;
}

/**
 * Update quiz status
 */
function quiz_ai_pro_update_quiz_status($quiz_id, $status)
{
    global $wpdb;

    $table = $wpdb->prefix . 'quiz_ia_quizzes';

    $valid_statuses = ['draft', 'published', 'pending', 'archived'];

    if (!in_array($status, $valid_statuses)) {
        return false;
    }

    return $wpdb->update(
        $table,
        ['status' => $status, 'updated_at' => current_time('mysql')],
        ['id' => $quiz_id],
        ['%s', '%s'],
        ['%d']
    );
}

/* ===========================
   COURSE CONTENT HELPERS
   =========================== */

/**
 * Process all courses for RAG (chunking content)
 */
function quiz_ai_pro_process_all_courses_for_rag()
{
    global $wpdb;

    $processed_count = 0;

    try {
        // First, try to process LearnPress courses
        $learnpress_courses_table = $wpdb->prefix . 'posts';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$learnpress_courses_table'");

        if ($table_exists) {
            // Get all published LearnPress courses
            $learnpress_courses = $wpdb->get_results(
                "SELECT ID, post_title, post_content 
                 FROM $learnpress_courses_table 
                 WHERE post_type = 'lp_course'
                 AND post_status = 'publish' 
                 AND post_content IS NOT NULL 
                 AND post_content != ''"
            );

            if (!empty($learnpress_courses)) {
                foreach ($learnpress_courses as $course) {
                    try {
                        $result = quiz_ai_pro_process_course_content_for_rag($course->ID);
                        if ($result) {
                            $processed_count++;
                            error_log("Quiz IA Pro: Processed LearnPress course {$course->ID}: {$course->post_title}");
                        }
                    } catch (Exception $e) {
                        error_log("Quiz IA Pro: Error processing course {$course->ID}: " . $e->getMessage());
                    }
                }
            } else {
                error_log('Quiz IA Pro: No LearnPress courses found with content');
            }
        } else {
            error_log('Quiz IA Pro: LearnPress posts table not found');
        }

        if ($processed_count == 0) {
            error_log('Quiz IA Pro: No courses found to process for RAG');
            return 0;
        }

        error_log("Quiz IA Pro: Processed $processed_count courses for RAG");
        return $processed_count;
    } catch (Exception $e) {
        error_log('Quiz IA Pro: Error in RAG processing: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Chunk course content for RAG processing
 */
function quiz_ai_pro_chunk_course_content($content, $title = '')
{
    $chunks = [];
    $max_words_per_chunk = 1000;

    // Clean and prepare content
    $clean_content = strip_tags($content);
    $clean_content = preg_replace('/\s+/', ' ', $clean_content);
    $words = explode(' ', $clean_content);

    // Split into chunks
    $word_chunks = array_chunk($words, $max_words_per_chunk);

    foreach ($word_chunks as $index => $word_chunk) {
        $chunk_text = implode(' ', $word_chunk);
        $word_count = count($word_chunk);

        // Extract keywords (simple approach - most frequent words)
        $keywords = quiz_ai_pro_extract_keywords($chunk_text);

        // Create summary (first 200 characters)
        $summary = substr($chunk_text, 0, 200) . '...';

        $chunks[] = [
            'text' => $chunk_text,
            'keywords' => implode(', ', $keywords),
            'summary' => $summary,
            'word_count' => $word_count
        ];
    }

    return $chunks;
}

/**
 * Extract keywords from text
 */
function quiz_ai_pro_extract_keywords($text, $max_keywords = 10)
{
    // Convert to lowercase and remove common words
    $stopwords = ['le', 'la', 'les', 'de', 'du', 'des', 'et', 'ou', 'est', 'sont', 'un', 'une', 'dans', 'pour', 'avec', 'sur', 'par', 'ce', 'cette', 'ces', 'que', 'qui', 'the', 'and', 'or', 'of', 'to', 'in', 'for', 'with', 'on', 'by', 'this', 'that', 'these', 'what', 'which'];

    $words = str_word_count(strtolower($text), 1, 'àáâäçéèêëíìîïóòôöúùûüÿñ');
    $words = array_filter($words, function ($word) use ($stopwords) {
        return strlen($word) > 3 && !in_array($word, $stopwords);
    });

    // Count word frequency
    $word_freq = array_count_values($words);
    arsort($word_freq);

    // Return top keywords
    return array_slice(array_keys($word_freq), 0, $max_keywords);
}

/**
 * Get detailed quiz statistics for stats page
 */
function quiz_ai_pro_get_detailed_stats()
{
    global $wpdb;

    $stats = [];

    // Get table names
    $quizzes_table = $wpdb->prefix . 'quiz_ia_quizzes';
    $results_table = $wpdb->prefix . 'quiz_ia_results';

    // Total active quizzes
    $stats['active_quizzes'] = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $quizzes_table WHERE status = 'published'"
    );

    // New quizzes this month
    $stats['new_quizzes_month'] = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $quizzes_table 
         WHERE status = 'published' 
         AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
    );

    // Total participants (count distinct users from results)
    $stats['total_participants'] = (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $results_table");

    // New participants this month
    $stats['new_participants_month'] = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT user_id) FROM $results_table 
         WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
    );

    // Average success rate
    $success_rate = $wpdb->get_var("SELECT AVG(percentage) FROM $results_table");
    $stats['success_rate'] = $success_rate ? round($success_rate, 1) : 0;

    // Success rate change this month
    $current_month_rate = $wpdb->get_var(
        "SELECT AVG(percentage) FROM $results_table 
         WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
    );
    $previous_month_rate = $wpdb->get_var(
        "SELECT AVG(percentage) FROM $results_table 
         WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)
         AND completed_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)"
    );

    if ($current_month_rate && $previous_month_rate) {
        $stats['success_rate_change'] = round($current_month_rate - $previous_month_rate, 1);
    } else {
        $stats['success_rate_change'] = 0;
    }

    // Average time (in seconds)
    $avg_time = $wpdb->get_var("SELECT AVG(time_taken) FROM $results_table WHERE time_taken > 0");
    $stats['average_time'] = $avg_time ? round($avg_time) : 0;

    // Time change this month
    $current_month_time = $wpdb->get_var(
        "SELECT AVG(time_taken) FROM $results_table 
         WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) AND time_taken > 0"
    );
    $previous_month_time = $wpdb->get_var(
        "SELECT AVG(time_taken) FROM $results_table 
         WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)
         AND completed_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND time_taken > 0"
    );

    if ($current_month_time && $previous_month_time) {
        $stats['time_change'] = round($current_month_time - $previous_month_time);
    } else {
        $stats['time_change'] = 0;
    }

    return $stats;
}

/**
 * Get popular quizzes for stats page
 */
function quiz_ai_pro_get_popular_quizzes($limit = 10)
{
    global $wpdb;

    $quizzes_table = $wpdb->prefix . 'quiz_ia_quizzes';
    $results_table = $wpdb->prefix . 'quiz_ia_results';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT q.id, q.title, 
                COUNT(DISTINCT r.id) as total_attempts,
                COUNT(DISTINCT r.user_id) as unique_participants,
                AVG(r.percentage) as avg_score,
                p.post_title as course_title
         FROM $quizzes_table q
         LEFT JOIN $results_table r ON q.id = r.quiz_id
         LEFT JOIN {$wpdb->prefix}posts p ON q.course_id = p.ID AND p.post_type = 'lp_course'
         WHERE q.status = 'published'
         GROUP BY q.id
         HAVING total_attempts > 0
         ORDER BY total_attempts DESC, unique_participants DESC
         LIMIT %d",
        $limit
    ));
}

/**
 * Get detailed quiz results for stats table
 */
function quiz_ai_pro_get_detailed_results($period = '30days', $quiz_id = 0, $search = '', $limit = 20, $offset = 0)
{
    global $wpdb;

    $quizzes_table = $wpdb->prefix . 'quiz_ia_quizzes';
    $results_table = $wpdb->prefix . 'quiz_ia_results';

    $where_clause = "WHERE 1=1";
    $params = [];

    // Add period filter
    $date_condition = quiz_ai_pro_get_date_condition($period);
    if ($date_condition) {
        $where_clause .= str_replace('r.submitted_at', 'r.completed_at', $date_condition);
    }

    // Add quiz filter
    if ($quiz_id > 0) {
        $where_clause .= " AND r.quiz_id = %d";
        $params[] = $quiz_id;
    }

    // Add search filter (now searches in both name and email with one input)
    if (!empty($search)) {
        $where_clause .= " AND (r.user_name LIKE %s OR r.user_email LIKE %s OR q.title LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $params[] = $limit;
    $params[] = $offset;

    $query = "SELECT r.id, 
                     r.percentage, 
                     r.time_taken as time_spent,
                     r.completed_at as submitted_at,
                     r.user_name,
                     r.user_email,
                     r.correct_answers,
                     r.total_questions,
                     COALESCE(r.attempt_number, 1) as attempt_number,
                     q.title as quiz_title,
                     q.id as quiz_id,
                     SUBSTRING_INDEX(r.user_name, ' ', 1) as first_name,
                     CASE 
                         WHEN LOCATE(' ', r.user_name) > 0 
                         THEN SUBSTRING(r.user_name, LOCATE(' ', r.user_name) + 1)
                         ELSE ''
                     END as last_name,
                     r.user_email as email
              FROM $results_table r
              JOIN $quizzes_table q ON r.quiz_id = q.id
              $where_clause
              ORDER BY r.completed_at DESC
              LIMIT %d OFFSET %d";

    return $wpdb->get_results($wpdb->prepare($query, ...$params));
}

/**
 * Get total results count for pagination
 */
function quiz_ai_pro_get_results_count($search = '', $quiz_id = 0)
{
    global $wpdb;

    $quizzes_table = $wpdb->prefix . 'quiz_ia_quizzes';
    $results_table = $wpdb->prefix . 'quiz_ia_results';

    $where_clause = "WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $where_clause .= " AND (r.user_name LIKE %s OR r.user_email LIKE %s OR q.title LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if ($quiz_id > 0) {
        $where_clause .= " AND r.quiz_id = %d";
        $params[] = $quiz_id;
    }

    $query = "SELECT COUNT(*)
              FROM $results_table r
              JOIN $quizzes_table q ON r.quiz_id = q.id
              $where_clause";

    if (!empty($params)) {
        return (int) $wpdb->get_var($wpdb->prepare($query, ...$params));
    } else {
        return (int) $wpdb->get_var($query);
    }
}

/* ===========================
   ADVANCED RAG FUNCTIONS
   =========================== */

/**
 * Extract clean terms from text for TF-IDF analysis
 */
function quiz_ai_pro_extract_query_terms($text)
{
    // Convert to lowercase and remove accents
    $text = strtolower($text);
    $text = remove_accents($text);

    // Remove punctuation and keep only letters, numbers, and spaces
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

    // Common stop words in French and English
    $stopwords = [
        'le',
        'la',
        'les',
        'de',
        'du',
        'des',
        'et',
        'ou',
        'est',
        'sont',
        'un',
        'une',
        'dans',
        'pour',
        'avec',
        'sur',
        'par',
        'ce',
        'cette',
        'ces',
        'que',
        'qui',
        'dont',
        'où',
        'quand',
        'comment',
        'pourquoi',
        'mais',
        'car',
        'donc',
        'si',
        'the',
        'and',
        'or',
        'of',
        'to',
        'in',
        'for',
        'with',
        'on',
        'by',
        'this',
        'that',
        'these',
        'what',
        'which',
        'how',
        'when',
        'where',
        'why',
        'but',
        'because',
        'so',
        'if',
        'then',
        'than',
        'such',
        'both',
        'through',
        'about',
        'into',
        'during',
        'before',
        'after',
        'above',
        'below',
        'up',
        'down',
        'out',
        'off',
        'over',
        'under',
        'again',
        'further',
        'then',
        'once'
    ];

    // Split into words
    $words = preg_split('/\s+/', trim($text));

    // Filter words: remove stop words and short words
    $words = array_filter($words, function ($word) use ($stopwords) {
        return strlen($word) > 2 && !in_array($word, $stopwords);
    });

    return array_unique(array_values($words));
}

/**
 * Calculate TF-IDF scores for query terms against chunks
 */
function quiz_ai_pro_calculate_tfidf_scores($query, $course_id = null, $limit = 5)
{
    global $wpdb;

    // Get query terms
    $query_terms = quiz_ai_pro_extract_query_terms($query);

    if (empty($query_terms)) {
        return [];
    }

    // Build WHERE clause
    $where = "WHERE 1=1";
    $params = [];

    if ($course_id) {
        $where .= " AND course_id = %d";
        $params[] = $course_id;
    }

    // Get all chunks
    $chunks = $wpdb->get_results($wpdb->prepare(
        "SELECT id, chunk_text, keywords, summary, word_count 
         FROM {$wpdb->prefix}quiz_ia_course_chunks $where",
        ...$params
    ));

    if (empty($chunks)) {
        return [];
    }

    $total_docs = count($chunks);
    $scores = [];

    // Calculate TF-IDF for each chunk
    foreach ($chunks as $chunk) {
        $doc_text = $chunk->chunk_text . ' ' . $chunk->keywords . ' ' . $chunk->summary;
        $doc_terms = quiz_ai_pro_extract_query_terms($doc_text);
        $doc_length = max(1, count($doc_terms));

        $score = 0;

        foreach ($query_terms as $term) {
            // Calculate TF (Term Frequency)
            $tf = quiz_ai_pro_count_term_occurrences($term, $doc_terms) / $doc_length;

            if ($tf > 0) {
                // Calculate IDF (Inverse Document Frequency)
                $docs_with_term = quiz_ai_pro_count_docs_with_term($term, $chunks);
                $idf = log($total_docs / max(1, $docs_with_term));

                // TF-IDF score
                $score += $tf * $idf;
            }
        }

        if ($score > 0) {
            $scores[] = [
                'chunk' => $chunk,
                'score' => $score,
                'matched_terms' => array_intersect($query_terms, $doc_terms)
            ];
        }
    }

    // Sort by score (descending)
    usort($scores, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    return array_slice($scores, 0, $limit);
}

/**
 * Count occurrences of a term in document terms array
 */
function quiz_ai_pro_count_term_occurrences($term, $doc_terms)
{
    return array_count_values($doc_terms)[$term] ?? 0;
}

/**
 * Count how many documents contain a specific term
 */
function quiz_ai_pro_count_docs_with_term($term, $chunks)
{
    $count = 0;
    foreach ($chunks as $chunk) {
        $text = strtolower($chunk->chunk_text . ' ' . $chunk->keywords . ' ' . $chunk->summary);
        if (strpos($text, $term) !== false) {
            $count++;
        }
    }
    return $count;
}

/**
 * Perform FULLTEXT search on chunks
 */
function quiz_ai_pro_fulltext_search($query, $course_id = null, $limit = 5)
{
    global $wpdb;

    $where = "WHERE MATCH(chunk_text, keywords, summary) AGAINST(%s IN BOOLEAN MODE)";
    $params = [$query];

    if ($course_id) {
        $where .= " AND course_id = %d";
        $params[] = $course_id;
    }

    $params[] = $limit;

    return $wpdb->get_results($wpdb->prepare(
        "SELECT *, MATCH(chunk_text, keywords, summary) AGAINST(%s IN BOOLEAN MODE) as relevance_score
         FROM {$wpdb->prefix}quiz_ia_course_chunks 
         $where
         ORDER BY relevance_score DESC 
         LIMIT %d",
        array_merge([$query], $params)
    ));
}

/**
 * Perform keyword-based search as fallback
 */
function quiz_ai_pro_keyword_search($query, $course_id = null, $limit = 5)
{
    global $wpdb;

    $query_terms = quiz_ai_pro_extract_query_terms($query);
    if (empty($query_terms)) {
        return [];
    }

    $search_conditions = [];
    $params = [];

    foreach ($query_terms as $term) {
        $search_conditions[] = "(chunk_text LIKE %s OR keywords LIKE %s OR summary LIKE %s)";
        $term_pattern = '%' . $wpdb->esc_like($term) . '%';
        $params[] = $term_pattern;
        $params[] = $term_pattern;
        $params[] = $term_pattern;
    }

    $where = "WHERE (" . implode(' OR ', $search_conditions) . ")";

    if ($course_id) {
        $where .= " AND course_id = %d";
        $params[] = $course_id;
    }

    $params[] = $limit;

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}quiz_ia_course_chunks 
         $where
         ORDER BY word_count DESC, chunk_order ASC 
         LIMIT %d",
        $params
    ));
}

/**
 * Hybrid search combining multiple techniques
 */
function quiz_ai_pro_get_relevant_chunks_hybrid($query, $course_id = null, $limit = 3)
{
    global $wpdb;

    $results = [];
    $used_ids = [];

    // 1. Try FULLTEXT search first (fastest and often most accurate)
    $fulltext_results = quiz_ai_pro_fulltext_search($query, $course_id, $limit);

    foreach ($fulltext_results as $result) {
        if (!in_array($result->id, $used_ids)) {
            $results[] = $result;
            $used_ids[] = $result->id;
        }
    }

    // 2. If we need more results, use TF-IDF
    if (count($results) < $limit) {
        $remaining = $limit - count($results);
        $tfidf_results = quiz_ai_pro_calculate_tfidf_scores($query, $course_id, $remaining * 2);

        foreach ($tfidf_results as $result_data) {
            if (!in_array($result_data['chunk']->id, $used_ids) && count($results) < $limit) {
                // Add TF-IDF score info to the chunk
                $chunk = $result_data['chunk'];
                $chunk->relevance_score = $result_data['score'];
                $chunk->matched_terms = implode(', ', $result_data['matched_terms']);

                $results[] = $chunk;
                $used_ids[] = $chunk->id;
            }
        }
    }

    // 3. Final fallback: keyword search for remaining slots
    if (count($results) < $limit) {
        $remaining = $limit - count($results);
        $keyword_results = quiz_ai_pro_keyword_search($query, $course_id, $remaining);

        foreach ($keyword_results as $result) {
            if (!in_array($result->id, $used_ids) && count($results) < $limit) {
                $result->relevance_score = 0.1; // Low score for keyword matches
                $results[] = $result;
                $used_ids[] = $result->id;
            }
        }
    }

    return array_slice($results, 0, $limit);
}

/**
 * Get quiz comments table SQL
 */
function quiz_ai_pro_get_quiz_comments_table_sql()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_ia_comments';
    $charset_collate = $wpdb->get_charset_collate();

    return "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        quiz_id mediumint(9) NOT NULL,
        user_id mediumint(9) DEFAULT 0,
        user_name varchar(100) NOT NULL,
        user_email varchar(100) NOT NULL,
        comment_text text NOT NULL,
        rating tinyint(1) DEFAULT NULL,
        ip_address varchar(45) NOT NULL,
        user_agent text,
        is_approved tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY quiz_id (quiz_id),
        KEY user_id (user_id),
        KEY user_email (user_email),
        KEY is_approved (is_approved),
        KEY created_at (created_at)
    ) $charset_collate;";
}

/**
 * Force update database tables - for manual upgrade
 */
function quiz_ai_pro_force_update_tables()
{
    global $wpdb;

    error_log('Quiz IA Pro: Starting forced table update...');

    // Get table check status
    $table_status = quiz_ai_pro_check_all_tables();

    if (!$table_status['all_exist']) {
        error_log('Quiz IA Pro: Missing tables detected: ' . implode(', ', $table_status['missing']));

        // Force create all tables
        $creation_result = quiz_ai_pro_create_all_tables();

        // Re-check after creation
        $table_status_after = quiz_ai_pro_check_all_tables();

        return [
            'before' => $table_status,
            'creation_result' => $creation_result,
            'after' => $table_status_after,
            'success' => $table_status_after['all_exist']
        ];
    }

    error_log('Quiz IA Pro: All tables already exist');
    return [
        'message' => 'All tables already exist',
        'tables' => $table_status,
        'success' => true
    ];
}

/**
 * Get stats data for a specific period and quiz
 */
function quiz_ai_pro_get_stats_data($period = '30days', $quiz_id = 0)
{
    global $wpdb;

    // Get date range based on period
    $date_condition = quiz_ai_pro_get_date_condition($period);

    // Build quiz condition
    $quiz_condition = '';
    if ($quiz_id > 0) {
        $quiz_condition = $wpdb->prepare(' AND r.quiz_id = %d', $quiz_id);
    }

    // Get basic stats
    $stats = $wpdb->get_row("
        SELECT 
            COUNT(DISTINCT r.quiz_id) as active_quizzes,
            COUNT(DISTINCT r.user_email) as total_participants,
            AVG(r.percentage) as avg_score,
            AVG(r.time_spent) as avg_time_spent,
            COUNT(*) as total_attempts
        FROM {$wpdb->prefix}quiz_ia_results r 
        WHERE 1=1 {$date_condition} {$quiz_condition}
    ");

    if (!$stats) {
        return [
            'active_quizzes' => 0,
            'total_participants' => 0,
            'avg_score' => 0,
            'avg_time_spent' => 0,
            'total_attempts' => 0,
            'success_rate' => 0
        ];
    }

    // Calculate success rate (percentage >= 60%)
    $success_count = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}quiz_ia_results r 
        WHERE r.percentage >= 60 {$date_condition} {$quiz_condition}
    ");

    $success_rate = $stats->total_attempts > 0 ? ($success_count / $stats->total_attempts) * 100 : 0;

    return [
        'active_quizzes' => intval($stats->active_quizzes),
        'total_participants' => intval($stats->total_participants),
        'avg_score' => round(floatval($stats->avg_score), 1),
        'avg_time_spent' => intval($stats->avg_time_spent),
        'total_attempts' => intval($stats->total_attempts),
        'success_rate' => round($success_rate, 1)
    ];
}

/**
 * Count detailed results for pagination
 */
function quiz_ai_pro_count_detailed_results($period = '30days', $quiz_id = 0, $search = '')
{
    global $wpdb;

    // Get date range based on period
    $date_condition = quiz_ai_pro_get_date_condition($period);

    // Build quiz condition
    $quiz_condition = '';
    if ($quiz_id > 0) {
        $quiz_condition = $wpdb->prepare(' AND r.quiz_id = %d', $quiz_id);
    }

    // Build search condition
    $search_condition = '';
    if (!empty($search)) {
        $search_condition = $wpdb->prepare(
            ' AND (r.user_name LIKE %s OR r.user_email LIKE %s)',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%'
        );
    }

    return intval($wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}quiz_ia_results r
        WHERE 1=1 {$date_condition} {$quiz_condition} {$search_condition}
    "));
}

/**
 * Get date condition for SQL queries
 */
function quiz_ai_pro_get_date_condition($period)
{
    global $wpdb;

    switch ($period) {
        case 'today':
            return " AND DATE(r.submitted_at) = CURDATE()";
        case 'week':
            return " AND r.submitted_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        case 'month':
            return " AND r.submitted_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        case '3months':
            return " AND r.submitted_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        case 'year':
            return " AND r.submitted_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        case '30days':
        default:
            return " AND r.submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
}

/**
 * Export stats data
 */
function quiz_ai_pro_export_stats_data($period = '30days', $quiz_id = 0, $format = 'csv')
{
    global $wpdb;

    // Get all results for export
    $results = quiz_ai_pro_get_detailed_results($period, $quiz_id, '', 1000, 0);

    // Create uploads directory if it doesn't exist
    $upload_dir = wp_upload_dir();
    $export_dir = $upload_dir['basedir'] . '/quiz-ia-exports/';
    if (!file_exists($export_dir)) {
        wp_mkdir_p($export_dir);
    }

    // Generate filename
    $filename = 'quiz-stats-' . date('Y-m-d-H-i-s') . '.' . $format;
    $filepath = $export_dir . $filename;

    if ($format === 'csv') {
        // Create CSV file
        $csv_data = [];
        $csv_data[] = ['Quiz', 'Participant', 'Email', 'Score', 'Percentage', 'Time Spent', 'Date'];

        foreach ($results as $result) {
            $csv_data[] = [
                $result->quiz_title,
                $result->user_name,
                $result->user_email,
                $result->correct_answers . '/' . $result->total_questions,
                $result->percentage . '%',
                quiz_ai_format_time_duration($result->time_spent),
                $result->submitted_at
            ];
        }

        // Write CSV
        $fp = fopen($filepath, 'w');
        foreach ($csv_data as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
    }

    return [
        'url' => $upload_dir['baseurl'] . '/quiz-ia-exports/' . $filename,
        'filename' => $filename,
        'path' => $filepath
    ];
}

/**
 * Get filtered quizzes
 */
function quiz_ai_pro_get_filtered_quizzes($filters, $page = 1, $per_page = 20)
{
    global $wpdb;

    $conditions = ['1=1'];
    $joins = [];

    // Status filter
    if (!empty($filters['status'])) {
        $conditions[] = $wpdb->prepare('q.status = %s', $filters['status']);
    }

    // Date filter
    if (!empty($filters['date'])) {
        switch ($filters['date']) {
            case 'today':
                $conditions[] = "DATE(q.created_at) = CURDATE()";
                break;
            case 'week':
                $conditions[] = "q.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $conditions[] = "q.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
    }

    // Category filter
    if (!empty($filters['category'])) {
        $conditions[] = $wpdb->prepare('q.category_id LIKE %s', '%"' . intval($filters['category']) . '"%');
    }

    // Search filter
    if (!empty($filters['search'])) {
        $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
        $conditions[] = $wpdb->prepare('(q.title LIKE %s OR q.description LIKE %s)', $search_term, $search_term);
    }

    // Build ORDER BY clause
    $sort_by = in_array($filters['sort_by'], ['title', 'created_at', 'status']) ? $filters['sort_by'] : 'created_at';
    $sort_order = ($filters['sort_order'] === 'asc') ? 'ASC' : 'DESC';
    $order_by = "ORDER BY q.{$sort_by} {$sort_order}";

    // Count total results
    $where_clause = implode(' AND ', $conditions);
    $total = intval($wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}quiz_ia_quizzes q 
        WHERE {$where_clause}
    "));

    // Get quizzes with pagination
    $offset = ($page - 1) * $per_page;
    $quizzes = $wpdb->get_results($wpdb->prepare("
        SELECT q.*, 
               (SELECT COUNT(*) FROM {$wpdb->prefix}quiz_ia_results r WHERE r.quiz_id = q.id) as participants,
               (SELECT COUNT(*) FROM {$wpdb->prefix}quiz_ia_results r WHERE r.quiz_id = q.id) as attempts
        FROM {$wpdb->prefix}quiz_ia_quizzes q 
        WHERE {$where_clause}
        {$order_by}
        LIMIT %d OFFSET %d
    ", $per_page, $offset));

    // Add course and category names
    foreach ($quizzes as $quiz) {
        $quiz->course_titles = quiz_ai_pro_get_course_names_for_quiz($quiz->id);
        $quiz->category_names = quiz_ai_pro_get_category_names_for_quiz($quiz->id);
    }

    return [
        'quizzes' => $quizzes,
        'total' => $total,
        'total_pages' => ceil($total / $per_page)
    ];
}

/**
 * Execute bulk quiz action
 */
function quiz_ai_pro_bulk_quiz_action($action, $quiz_ids)
{
    global $wpdb;

    if (empty($quiz_ids) || !is_array($quiz_ids)) {
        return ['message' => 'No quizzes selected', 'affected' => 0];
    }

    $quiz_ids = array_map('intval', $quiz_ids);
    $placeholders = implode(',', array_fill(0, count($quiz_ids), '%d'));

    $affected = 0;

    switch ($action) {
        case 'publish':
            $affected = $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}quiz_ia_quizzes 
                SET status = 'published', updated_at = NOW() 
                WHERE id IN ({$placeholders})
            ", ...$quiz_ids));

            // Send new quiz notifications for each published quiz
            if ($affected > 0) {
                foreach ($quiz_ids as $quiz_id) {
                    $quiz_data = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}quiz_ia_quizzes WHERE id = %d",
                        $quiz_id
                    ), ARRAY_A);

                    if ($quiz_data) {
                        do_action('quiz_ia_pro_quiz_published', $quiz_data);
                    }
                }
            }

            $message = sprintf('%d quiz(s) published successfully', $affected);
            break;

        case 'unpublish':
            $affected = $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}quiz_ia_quizzes 
                SET status = 'draft', updated_at = NOW() 
                WHERE id IN ({$placeholders})
            ", ...$quiz_ids));
            $message = sprintf('%d quiz(s) unpublished successfully', $affected);
            break;

        case 'archive':
            $affected = $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}quiz_ia_quizzes 
                SET status = 'archived', updated_at = NOW() 
                WHERE id IN ({$placeholders})
            ", ...$quiz_ids));
            $message = sprintf('%d quiz(s) archived successfully', $affected);
            break;

        case 'delete':
            // Delete related data first (in proper order due to foreign key constraints)

            // 1. Get question IDs first
            $question_ids = $wpdb->get_col($wpdb->prepare("
                SELECT id FROM {$wpdb->prefix}quiz_ia_questions 
                WHERE quiz_id IN ({$placeholders})
            ", ...$quiz_ids));

            if (!empty($question_ids)) {
                $question_placeholders = implode(',', array_fill(0, count($question_ids), '%d'));

                // 2. Delete answers for these questions
                $wpdb->query($wpdb->prepare("
                    DELETE FROM {$wpdb->prefix}quiz_ia_answers 
                    WHERE question_id IN ({$question_placeholders})
                ", ...$question_ids));
            }

            // 3. Delete quiz results
            $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->prefix}quiz_ia_results 
                WHERE quiz_id IN ({$placeholders})
            ", ...$quiz_ids));

            // 4. Delete questions
            $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->prefix}quiz_ia_questions 
                WHERE quiz_id IN ({$placeholders})
            ", ...$quiz_ids));

            // 5. Delete quizzes
            $affected = $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->prefix}quiz_ia_quizzes 
                WHERE id IN ({$placeholders})
            ", ...$quiz_ids));
            $message = sprintf('%d quiz(s) deleted successfully', $affected);
            break;

        default:
            return ['message' => 'Invalid action', 'affected' => 0];
    }

    return ['message' => $message, 'affected' => $affected];
}

/**
 * Duplicate a quiz
 */
function quiz_ai_pro_duplicate_quiz($quiz_id)
{
    global $wpdb;

    // Get original quiz
    $quiz = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}quiz_ia_quizzes WHERE id = %d
    ", $quiz_id));

    if (!$quiz) {
        error_log('Quiz IA Pro: Quiz not found for duplication: ' . $quiz_id);
        return false;
    }

    error_log('Quiz IA Pro: Starting duplication of quiz: ' . $quiz_id);

    // Generate unique, shorter quiz code to avoid field length issues
    $unique_code = 'CPY_' . substr($quiz->quiz_code, 0, 10) . '_' . rand(1000, 9999);

    // Create duplicate
    $insert_result = $wpdb->insert(
        $wpdb->prefix . 'quiz_ia_quizzes',
        [
            'title' => $quiz->title . ' (Copie)',
            'description' => $quiz->description,
            'course_id' => $quiz->course_id,
            'category_id' => $quiz->category_id,
            'quiz_type' => $quiz->quiz_type,
            'form_type' => $quiz->form_type,
            'quiz_code' => $unique_code,
            'status' => 'draft',
            'ai_generated' => $quiz->ai_generated,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]
    );

    if ($insert_result === false) {
        error_log('Quiz IA Pro: Failed to insert duplicate quiz: ' . $wpdb->last_error);
        return false;
    }

    $new_quiz_id = $wpdb->insert_id;
    error_log('Quiz IA Pro: Created duplicate quiz with ID: ' . $new_quiz_id);

    // Copy questions
    $questions = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}quiz_ia_questions WHERE quiz_id = %d ORDER BY order_num ASC
    ", $quiz_id));

    error_log('Quiz IA Pro: Found ' . count($questions) . ' questions to copy');

    foreach ($questions as $question) {
        $question_result = $wpdb->insert(
            $wpdb->prefix . 'quiz_ia_questions',
            [
                'quiz_id' => $new_quiz_id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'options' => $question->options,
                'correct_answer' => $question->correct_answer,
                'points' => $question->points,
                'explanation' => $question->explanation,
                'difficulty' => $question->difficulty,
                'tags' => $question->tags,
                'order_num' => $question->order_num,
                'created_at' => current_time('mysql')
            ]
        );

        if ($question_result === false) {
            error_log('Quiz IA Pro: Failed to copy question: ' . $wpdb->last_error);
            continue;
        }

        $new_question_id = $wpdb->insert_id;

        // Copy answers for this question
        $answers = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}quiz_ia_answers WHERE question_id = %d ORDER BY sort_order ASC
        ", $question->id));

        error_log('Quiz IA Pro: Copying ' . count($answers) . ' answers for question ' . $question->id);

        foreach ($answers as $answer) {
            $answer_result = $wpdb->insert(
                $wpdb->prefix . 'quiz_ia_answers',
                [
                    'question_id' => $new_question_id,
                    'answer_text' => $answer->answer_text,
                    'is_correct' => $answer->is_correct,
                    'sort_order' => $answer->sort_order,
                    'created_at' => current_time('mysql')
                ]
            );

            if ($answer_result === false) {
                error_log('Quiz IA Pro: Failed to copy answer: ' . $wpdb->last_error);
            }
        }
    }

    error_log('Quiz IA Pro: Successfully completed duplication');
    return $new_quiz_id;
}

/**
 * Helper function to format time duration
 */
function quiz_ai_format_time_duration($seconds)
{
    if (!$seconds || $seconds <= 0) return '--';
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    return sprintf('%d:%02d', $minutes, $seconds);
}

/**
 * Get course names for a quiz
 */
function quiz_ai_pro_get_course_names_for_quiz($quiz_id)
{
    global $wpdb;

    $quiz = $wpdb->get_row($wpdb->prepare("
        SELECT course_id FROM {$wpdb->prefix}quiz_ia_quizzes WHERE id = %d
    ", $quiz_id));

    if (!$quiz || !$quiz->course_id) {
        return ['Aucun cours'];
    }

    // Handle JSON-encoded course IDs
    $course_ids = json_decode($quiz->course_id, true);
    if (!is_array($course_ids)) {
        $course_ids = [$quiz->course_id];
    }

    $course_names = [];
    foreach ($course_ids as $course_id) {
        $course = get_post($course_id);
        if ($course) {
            $course_names[] = $course->post_title;
        }
    }

    return empty($course_names) ? ['Aucun cours'] : $course_names;
}

/**
 * Get category names for a quiz
 */
function quiz_ai_pro_get_category_names_for_quiz($quiz_id)
{
    global $wpdb;

    $quiz = $wpdb->get_row($wpdb->prepare("
        SELECT category_id FROM {$wpdb->prefix}quiz_ia_quizzes WHERE id = %d
    ", $quiz_id));

    if (!$quiz || !$quiz->category_id) {
        return ['Non catégorisé'];
    }

    // Handle JSON-encoded category IDs
    $category_ids = json_decode($quiz->category_id, true);
    if (!is_array($category_ids)) {
        $category_ids = [$quiz->category_id];
    }

    $category_names = [];
    foreach ($category_ids as $category_id) {
        $category = get_term($category_id, 'category');
        if ($category && !is_wp_error($category)) {
            $category_names[] = $category->name;
        }
    }

    return empty($category_names) ? ['Non catégorisé'] : $category_names;
}

/**
 * Get section names from a specific LearnPress course
 *
 * @param int $course_id Course ID
 * @return array Array of section names
 */
function quiz_ai_pro_get_course_sections($course_id)
{
    global $wpdb;

    error_log('Quiz IA Pro Debug: quiz_ai_pro_get_course_sections called with course_id: ' . print_r($course_id, true));
    error_log('Quiz IA Pro Debug: Course ID type: ' . gettype($course_id));

    // Clean and convert course_id to integer if it's malformed
    if (is_string($course_id)) {
        $course_id = trim($course_id, '[]');
    }
    $course_id = intval($course_id);

    error_log('Quiz IA Pro Debug: Cleaned course_id: ' . $course_id . ' (Type: ' . gettype($course_id) . ')');

    if (empty($course_id) || $course_id <= 0) {
        error_log('Quiz IA Pro Debug: Invalid course ID provided to get_course_sections: ' . $course_id);
        return [];
    }

    error_log('Quiz IA Pro Debug: Getting sections for course ID: ' . $course_id);

    // Get sections from LearnPress sections table
    $sections_table = $wpdb->prefix . 'learnpress_sections';
    error_log('Quiz IA Pro Debug: Using sections table: ' . $sections_table);

    $sql = $wpdb->prepare(
        "SELECT section_name 
         FROM {$sections_table} 
         WHERE section_course_id = %d
         ORDER BY section_order ASC",
        $course_id
    );
    // error_log('Quiz IA Pro Debug: SQL Query: ' . $sql);

    $sections = $wpdb->get_results($sql);
    // error_log('Quiz IA Pro Debug: Raw sections result: ' . print_r($sections, true));

    if ($wpdb->last_error) {
        error_log('Quiz IA Pro Debug: Database error: ' . $wpdb->last_error);
    }

    // Extract just the section names
    $section_names = [];
    foreach ($sections as $section) {
        if (!empty($section->section_name)) {
            $section_names[] = sanitize_text_field($section->section_name);
        }
    }

    error_log('Quiz IA Pro Debug: Final section names: ' . print_r($section_names, true));
    return $section_names;
}

/**
 * Generate enhanced feedback for QCM questions with course references
 *
 * @param array $question Question data
 * @param bool $user_answer_correct Whether user answer was correct
 * @param int $course_id Course ID for getting sections
 * @param string $correct_answer The correct answer text
 * @return string Enhanced feedback HTML
 */
function quiz_ai_pro_generate_enhanced_qcm_feedback($question, $user_answer_correct, $course_id, $correct_answer = '')
{
    // Get course slug for URL
    $course_slug = '';
    if (!empty($course_id)) {
        $course_slug = get_post_field('post_name', $course_id);
    }

    // Fallback course slug if we can't get it
    if (empty($course_slug)) {
        $course_slug = 'power-bi-exam-certification-preparation';
    }

    // Get section names from course
    $section_names = quiz_ai_pro_get_course_sections($course_id);

    // Randomly select 2-3 sections based on correctness
    shuffle($section_names);
    $num_sections = $user_answer_correct ? 2 : 3;
    $selected_sections = array_slice($section_names, 0, min($num_sections, count($section_names)));

    // Format sections as HTML list
    $sections_html = '';
    foreach ($selected_sections as $section) {
        $sections_html .= '<li>' . esc_html($section) . '</li>';
    }

    // Get explanation from question
    $explanation = isset($question['explanation']) ? $question['explanation'] : 'Explication non disponible.';

    // Build feedback HTML
    $feedback = '';
    if ($user_answer_correct) {
        $feedback = sprintf(
            '<div class="quiz-feedback correct">
                <p class="feedback-status">✅ Correct!</p>
                <p class="feedback-explanation">%s</p>
                <div class="feedback-resources">
                    <p class="feedback-resources-title">📚 Pour approfondir vos connaissances:</p>
                    <ul class="feedback-sections">%s</ul>
                    <p class="feedback-course-link">🔗 <a href="https://innovation.ma/cours/%s/" target="_blank">Accédez au cours complet</a></p>
                </div>
            </div>',
            wp_kses_post($explanation),
            $sections_html,
            esc_attr($course_slug)
        );
    } else {
        $correct_answer_text = !empty($correct_answer) ? $correct_answer : 'Réponse correcte non disponible';

        $feedback = sprintf(
            '<div class="quiz-feedback incorrect">
                <p class="feedback-status">❌ Incorrect.</p>
                <p class="feedback-correct-answer">La bonne réponse est: <strong>%s</strong></p>
                <p class="feedback-explanation">💡 Explication: %s</p>
                <div class="feedback-resources">
                    <p class="feedback-resources-title">📚 Nous vous recommandons de réviser ces sections:</p>
                    <ul class="feedback-sections">%s</ul>
                    <p class="feedback-course-link">🔗 <a href="https://innovation.ma/cours/%s/" target="_blank">Accédez au cours complet</a></p>
                </div>
            </div>',
            esc_html($correct_answer_text),
            wp_kses_post($explanation),
            $sections_html,
            esc_attr($course_slug)
        );
    }

    return $feedback;
}

/**
 * Render quiz table HTML for AJAX responses
 */
function quiz_ai_render_quiz_table($quizzes)
{
    if (empty($quizzes)) {
        return '<tr class="no-items">
                    <td class="colspanchange" colspan="8">
                        <div class="no-quiz-message">
                            <h3>Aucun quiz trouvé</h3>
                            <p>Aucun quiz ne correspond aux critères de filtrage sélectionnés.</p>
                            <p>Ajustez vos critères de recherche ou réinitialisez les filtres.</p>
                        </div>
                    </td>
                </tr>';
    }

    $output = '';
    foreach ($quizzes as $quiz) {
        $title = esc_html($quiz->title ?: 'Quiz sans titre');
        $description = esc_html($quiz->description ?: 'Aucune description');
        $quiz_code = esc_html($quiz->quiz_code);
        $status = esc_html($quiz->status ?: 'draft');
        $created_at = $quiz->created_at ? human_time_diff(strtotime($quiz->created_at)) . ' ago' : 'Date inconnue';

        // Handle multi-select courses and categories
        $course_names = !empty($quiz->course_titles) ? $quiz->course_titles : ['Aucun cours'];
        $category_names = !empty($quiz->category_names) ? $quiz->category_names : ['Non catégorisé'];

        // Status badge classes
        $status_class = '';
        switch ($status) {
            case 'published':
                $status_class = 'status-published';
                break;
            case 'draft':
                $status_class = 'status-draft';
                break;
            case 'pending':
                $status_class = 'status-pending';
                break;
            default:
                $status_class = 'status-archived';
        }

        $output .= '<tr id="quiz-' . $quiz->id . '" class="quiz-item">
            <th scope="row" class="check-column">
                <label class="screen-reader-text" for="cb-select-' . $quiz->id . '">Sélectionner ' . $title . '</label>
                <input id="cb-select-' . $quiz->id . '" type="checkbox" name="quiz[]" value="' . $quiz->id . '">
            </th>
            <td class="title column-title has-row-actions column-primary" data-colname="Titre">
                <strong>
                    <span class="row-title" aria-label="' . $title . '">
                        ' . $title . '
                    </span>
                </strong>
                <div class="quiz-description">' . wp_trim_words($description, 15) . '</div>
                <div class="quiz-meta">
                    <small>
                        <strong>Cours:</strong>
                        <div class="course-list" style="display: inline-block; margin-right: 8px;">';

        foreach ($course_names as $course_name) {
            $output .= '<span class="course-tag">' . esc_html($course_name) . '</span>';
        }

        $output .= '</div>
                        | <strong>Catégories:</strong>
                        <div class="category-list" style="display: inline-block;">';

        foreach ($category_names as $category_name) {
            $output .= '<span class="category-tag">' . esc_html($category_name) . '</span>';
        }

        $output .= '</div>';

        if ($quiz->ai_generated) {
            $output .= ' | <span class="ai-badge">🤖 IA</span>';
        }

        $output .= '    </small>
                </div>
                <div class="row-actions">
                    <span class="edit"><a href="' . admin_url('admin.php?page=quiz-ai-pro-edit&quiz_id=' . $quiz->id) . '" aria-label="Modifier">Modifier</a> | </span>';

        if ($quiz->status === 'published') {
            $output .= '<span class="view"><a href="#" aria-label="Voir" target="_blank">Voir</a> | </span>
                        <span class="unpublish"><a href="#" class="quiz-action" data-action="unpublish" data-quiz-id="' . esc_attr($quiz->id) . '" aria-label="Dépublier">Dépublier</a> | </span>';
        } else {
            $output .= '<span class="publish"><a href="#" class="quiz-action" data-action="publish" data-quiz-id="' . esc_attr($quiz->id) . '" aria-label="Publier">Publier</a> | </span>';
        }

        $output .= '    <span class="duplicate"><a href="#" aria-label="Dupliquer">Dupliquer</a> | </span>
                    <span class="trash"><a href="#" class="submitdelete" aria-label="Supprimer">Supprimer</a></span>
                </div>
            </td>
            <td class="code column-code" data-colname="Code">
                <span class="quiz-code">' . $quiz_code . '</span>
                <button type="button" class="button-link copy-code" title="Copier le code">
                    <span class="dashicons dashicons-admin-page"></span>
                </button>
            </td>
            <td class="questions column-questions" data-colname="Questions">
                <span class="questions-count">' . intval($quiz->question_count ?? $quiz->total_questions ?? 0) . '</span>
                <div class="questions-breakdown">
                    <small>Questions générées</small>
                </div>
            </td>
            <td class="views column-views" data-colname="Vues">
                <span class="views-count">' . intval($quiz->views ?: 0) . '</span>
            </td>
            <td class="participants column-participants" data-colname="Participants">
                <span class="participants-count">' . intval($quiz->participants ?: 0) . '</span>';

        if ($quiz->participants > 0) {
            $output .= '<div class="completion-rate">
                            <small>Tentatives: ' . intval($quiz->attempts ?: 0) . '</small>
                        </div>';
        }

        $output .= '</td>
            <td class="status column-status" data-colname="Statut">
                <span class="status-badge ' . $status_class . '">
                    ' . ucfirst($status) . '
                </span>
            </td>
            <td class="date column-date" data-colname="Date">
                <abbr title="' . esc_attr($quiz->created_at) . '">' . $created_at . '</abbr>
            </td>
        </tr>';
    }

    return $output;
}
