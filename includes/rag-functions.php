<?php

/**
 * RAG (Retrieval-Augmented Generation) Functions for Quiz IA Pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/* ===========================
   RAG COURSE CONTENT PROCESSING
   =========================== */

/**
 * Process course content into chunks for RAG
 */
function quiz_ai_pro_process_course_content_for_rag($course_id)
{
    global $wpdb;

    // First try to get LearnPress course, fallback to custom course
    $course = quiz_ai_pro_get_learnpress_course_by_id($course_id);
    if (!$course) {
        $course = quiz_ai_pro_get_course_by_id($course_id);
        if (!$course) {
            return false;
        }
    }

    $chunks_table = $wpdb->prefix . 'quiz_ia_course_chunks';

    // Delete existing chunks for this course
    $wpdb->delete($chunks_table, ['course_id' => $course_id]);

    $chunks = [];

    // Chunk the main content
    $content_field = isset($course->post_content) ? $course->post_content : $course->content;
    if (!empty($content_field)) {
        $content_chunks = quiz_ai_pro_split_text_into_chunks($content_field, 1000);
        foreach ($content_chunks as $index => $chunk) {
            $chunks[] = [
                'course_id' => $course_id,
                'chunk_text' => $chunk,
                'chunk_order' => $index,
                'word_count' => str_word_count($chunk),
                'keywords' => implode(', ', quiz_ai_pro_extract_keywords($chunk)),
                'summary' => quiz_ai_pro_generate_chunk_summary($chunk),
                'created_at' => current_time('mysql')
            ];
        }
    }

    // Chunk the description
    $description_field = isset($course->description) ? $course->description : '';
    if (!empty($description_field)) {
        $desc_chunks = quiz_ai_pro_split_text_into_chunks($description_field, 500);
        foreach ($desc_chunks as $index => $chunk) {
            $chunks[] = [
                'course_id' => $course_id,
                'chunk_text' => $chunk,
                'chunk_order' => count($chunks) + $index, // Continue numbering from content chunks
                'word_count' => str_word_count($chunk),
                'keywords' => implode(', ', quiz_ai_pro_extract_keywords($chunk)),
                'summary' => quiz_ai_pro_generate_chunk_summary($chunk),
                'created_at' => current_time('mysql')
            ];
        }
    }

    // If this is a LearnPress course, also process sections
    if (isset($course->post_content)) { // This indicates it's a LearnPress course
        $sections = quiz_ai_pro_get_learnpress_course_sections($course_id);
        foreach ($sections as $section) {
            if (!empty($section->section_description)) {
                $section_chunks = quiz_ai_pro_split_text_into_chunks($section->section_description, 800);
                foreach ($section_chunks as $index => $chunk) {
                    $chunks[] = [
                        'course_id' => $course_id,
                        'chunk_text' => "Section: {$section->section_name}\n\n{$chunk}",
                        'chunk_order' => count($chunks) + $index,
                        'word_count' => str_word_count($chunk),
                        'keywords' => implode(', ', quiz_ai_pro_extract_keywords($chunk)),
                        'summary' => quiz_ai_pro_generate_chunk_summary($chunk),
                        'created_at' => current_time('mysql')
                    ];
                }
            }
        }
    }

    // Insert chunks into database
    $inserted = 0;
    foreach ($chunks as $chunk) {
        $result = $wpdb->insert($chunks_table, $chunk);
        if ($result) $inserted++;
    }

    return $inserted;
}

/**
 * Split text into chunks of specified size
 */
function quiz_ai_pro_split_text_into_chunks($text, $max_words = 1000)
{
    // Use WordPress function if available, otherwise use PHP's strip_tags
    $text = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($text) : strip_tags($text);

    // Clean up the text
    $text = preg_replace('/\s+/', ' ', trim($text));

    // If text is shorter than max_words, return as single chunk
    $word_count = str_word_count($text);
    if ($word_count <= $max_words) {
        return [$text];
    }

    // Split by sentences first
    $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

    $chunks = [];
    $current_chunk = '';
    $current_word_count = 0;

    foreach ($sentences as $sentence) {
        $sentence = trim($sentence);
        if (empty($sentence)) continue;

        $sentence_word_count = str_word_count($sentence);

        // If adding this sentence would exceed the limit and we have content, start new chunk
        if ($current_word_count + $sentence_word_count > $max_words && !empty($current_chunk)) {
            // Add the current chunk
            $chunks[] = trim($current_chunk);

            // Start new chunk with current sentence
            $current_chunk = $sentence . '. ';
            $current_word_count = $sentence_word_count;
        } else {
            // Add sentence to current chunk
            $current_chunk .= $sentence . '. ';
            $current_word_count += $sentence_word_count;
        }
    }

    // Add the last chunk if it has content
    if (!empty($current_chunk)) {
        $chunks[] = trim($current_chunk);
    }

    // If we still have empty chunks, fall back to word-based splitting
    if (empty($chunks)) {
        $words = explode(' ', $text);
        $word_chunks = array_chunk($words, $max_words);
        foreach ($word_chunks as $word_chunk) {
            $chunks[] = implode(' ', $word_chunk);
        }
    }

    return $chunks;
}

/**
 * Generate a summary of a text chunk
 */
function quiz_ai_pro_generate_chunk_summary($text, $max_length = 200)
{
    // Use WordPress function if available, otherwise use PHP's strip_tags
    $text = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($text) : strip_tags($text);
    $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

    if (empty($sentences)) return '';

    // Take first 2-3 sentences or until max length
    $summary = '';
    $sentence_count = 0;

    foreach ($sentences as $sentence) {
        $sentence = trim($sentence);
        if (empty($sentence)) continue;

        if (strlen($summary . $sentence) > $max_length && $sentence_count > 0) {
            break;
        }

        $summary .= $sentence . '. ';
        $sentence_count++;

        if ($sentence_count >= 3) break;
    }

    return trim($summary);
}

/**
 * Extract relevant keywords from course content for search matching
 */

/**
 * Format relevant content for AI prompt
 */
function quiz_ai_pro_format_content_for_ai($course_ids, $quiz_topic = '', $max_chunks = 8)
{
    // === DEBUG: RAG Content Formatting ===
   /*  error_log("=== RAG DEBUG: Content Formatting (UPGRADED) ===");
    error_log("Course IDs received: " . (is_array($course_ids) ? implode(', ', $course_ids) : $course_ids));
    error_log("Quiz topic: " . $quiz_topic);
    error_log("Max chunks: " . $max_chunks); */

    // Convert to array if single course ID
    if (!is_array($course_ids)) {
        $course_ids = [$course_ids];
    }

    $formatted_content = "=== CONTENU INTELLIGENT DES COURS ===\n\n";
    $total_chunks_collected = 0;

    // For each course, get relevant chunks using our intelligent system
    foreach ($course_ids as $course_id) {
        if (empty($course_id)) continue;

        // Get course info
        $course = quiz_ai_pro_get_learnpress_course_by_id($course_id);
        if (!$course) {
            error_log("Course not found: " . $course_id);
            continue;
        }

       // error_log("Processing course: " . $course->title . " (ID: $course_id)");

        // Build search query from quiz topic and course title
        $search_query = $quiz_topic;
        if (empty($search_query)) {
            $search_query = "quiz questions " . $course->title;
        } else {
            $search_query = $quiz_topic . " " . $course->title;
        }

       /// error_log("Search query: " . $search_query);

        // Use our intelligent hybrid search to get relevant chunks
        $chunks_for_course = quiz_ai_pro_get_relevant_chunks_hybrid($search_query, $course_id, min(5, $max_chunks - $total_chunks_collected));

        if (empty($chunks_for_course)) {
            error_log("No chunks found for course: " . $course->title);
            continue;
        }

        error_log("Found " . count($chunks_for_course) . " relevant chunks for course: " . $course->title);

        // Add course header
        $formatted_content .= "📚 **Cours: {$course->title}**\n";

        // Add each relevant chunk
        foreach ($chunks_for_course as $chunk) {
            $formatted_content .= "📖 **Segment pertinent:**\n";
            $formatted_content .= trim($chunk->chunk_text) . "\n\n";

            // Add keywords if available
            if (!empty($chunk->keywords)) {
                $formatted_content .= "🔑 **Mots-clés:** {$chunk->keywords}\n\n";
            }

            // Add relevance score if available
            if (isset($chunk->relevance_score) && $chunk->relevance_score > 0) {
                $formatted_content .= "� **Score de pertinence:** " . round($chunk->relevance_score, 3) . "\n\n";
            }

            // Add matched terms if available
            if (isset($chunk->matched_terms) && !empty($chunk->matched_terms)) {
                $formatted_content .= "🎯 **Termes correspondants:** {$chunk->matched_terms}\n\n";
            }

            $formatted_content .= "---\n\n";
            $total_chunks_collected++;

            if ($total_chunks_collected >= $max_chunks) {
                break 2; // Break out of both loops
            }
        }
    }

    if ($total_chunks_collected == 0) {
        error_log("RAG PROBLEM: No intelligent chunks found! Falling back to basic content.");
        // Fallback to basic course content
        foreach ($course_ids as $course_id) {
            $course = quiz_ai_pro_get_learnpress_course_by_id($course_id);
            if ($course && !empty($course->post_content)) {
                $formatted_content .= "� **Cours: {$course->title}**\n";
                $formatted_content .= "📖 **Contenu de base:**\n";
                $formatted_content .= wp_trim_words(strip_tags($course->post_content), 200) . "\n\n";
                $formatted_content .= "---\n\n";
            }
        }
    }

    error_log("Final intelligent content length: " . strlen($formatted_content));
    error_log("Total chunks collected: " . $total_chunks_collected);
    error_log("Final content preview: " . substr($formatted_content, 0, 300) . "...");
    error_log("=== END RAG DEBUG (UPGRADED) ===");

    return $formatted_content;
}

/**
 * Get quiz-course relationships
 */
function quiz_ai_pro_get_quiz_courses($quiz_id)
{
    global $wpdb;

    // Get the course_id from the quiz
    $quiz = $wpdb->get_row($wpdb->prepare(
        "SELECT course_id FROM {$wpdb->prefix}quiz_ia_quizzes WHERE id = %d",
        $quiz_id
    ));

    if (!$quiz || !$quiz->course_id) {
        return [];
    }

    // Get the LearnPress course
    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT ID as id, post_title as title, post_content as content
         FROM {$wpdb->prefix}posts
         WHERE ID = %d AND post_type = 'lp_course' AND post_status = 'publish'",
        $quiz->course_id
    ));

    return $course ? [$course] : [];
}

/**
 * Get statistics about RAG content processing
 */
function quiz_ai_pro_get_rag_statistics()
{
    global $wpdb;

    $chunks_table = $wpdb->prefix . 'quiz_ia_course_chunks';
    $learnpress_courses_table = $wpdb->prefix . 'learnpress_courses';

    $stats = [];

    // Total chunks
    $stats['total_chunks'] = $wpdb->get_var("SELECT COUNT(*) FROM $chunks_table");

    // Average words per chunk
    $stats['avg_words_per_chunk'] = $wpdb->get_var("SELECT AVG(word_count) FROM $chunks_table");
    $stats['avg_words_per_chunk'] = $stats['avg_words_per_chunk'] ? round($stats['avg_words_per_chunk']) : 0;

    // Courses with processed content
    $stats['processed_courses'] = $wpdb->get_var(
        "SELECT COUNT(DISTINCT course_id) FROM $chunks_table"
    );

    // Check if LearnPress table exists for total courses count
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$learnpress_courses_table'");
    if ($table_exists) {
        $stats['total_courses'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $learnpress_courses_table WHERE post_status = 'publish'"
        );
    } else {
        $stats['total_courses'] = 0;
    }

    return $stats;
}
