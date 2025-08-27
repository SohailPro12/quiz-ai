<?php
if (!defined('ABSPATH')) exit;

// Enable shortcodes in SiteOrigin Editor widget
add_filter('siteorigin_widgets_text', 'do_shortcode');

/**
 * Shortcode: [quiz_ai_latest_quizzes]
 * Displays latest quizzes in a card layout.
 * Params:
 *   number (int): Number of quizzes to show (default: 6)
 *   orderby (string): Field to order by (default: 'created_at')
 *   order (string): ASC/DESC (default: 'DESC')
 *   only_with_images (bool): Show only quizzes with images (default: false)
 *   category (string): Filter by category slug (optional)
 */
function quiz_ai_latest_quizzes_shortcode($atts)
{
    $atts = shortcode_atts([
        'number' => 6,
        'orderby' => 'created_at',
        'order' => 'DESC',
        'only_with_images' => false,
        'category' => '',
    ], $atts, 'quiz_ai_latest_quizzes');

    global $wpdb;
    $table = $wpdb->prefix . 'quiz_ia_quizzes';
    $where = '';
    $params = [];
    if (!empty($atts['category'])) {
        $where .= $wpdb->prepare(' AND category_slug = %s', $atts['category']);
    }
    if ($atts['only_with_images']) {
        $where .= " AND (featured_image IS NOT NULL AND featured_image != '')";
    }
    $orderby = esc_sql($atts['orderby']);
    $order = (strtoupper($atts['order']) === 'ASC') ? 'ASC' : 'DESC';
    $number = intval($atts['number']);
    $sql = "SELECT * FROM $table WHERE 1=1 $where ORDER BY $orderby $order LIMIT $number";
    $quizzes = $wpdb->get_results($sql);

    if (!$quizzes) {
        return '<div class="quiz-ai-latest-quizzes">No quizzes found.</div>';
    }

    ob_start();
    // Debug: Output the CSS file URL for troubleshooting
    echo '<div class="quiz-ai-latest-quizzes-cards">';
    foreach ($quizzes as $quiz) {
        $has_image = !empty($quiz->featured_image);
        $img = $has_image ? (function_exists('esc_url') ? esc_url($quiz->featured_image) : $quiz->featured_image) : '';
        $title = function_exists('esc_html') ? esc_html($quiz->title) : $quiz->title;
        $category_names = function_exists('quiz_ai_pro_get_category_names_for_quiz') ? quiz_ai_pro_get_category_names_for_quiz($quiz->id) : [];
        $category = !empty($category_names) ? (function_exists('esc_html') ? esc_html($category_names[0]) : $category_names[0]) : (function_exists('__') ? __('Uncategorized', 'quiz-ai') : 'Uncategorized');
        $date = function_exists('date_i18n') ? date_i18n('j M Y', strtotime($quiz->created_at)) : date('j M Y', strtotime($quiz->created_at));
        $link = 'https://innovation.ma/quiz-tests-exam-questions/';
        $footer_text = "$category • $date";
        echo "<div class='quiz-ai-card'>
                <a href='$link' class='quiz-ai-card-link'>
                    <div class='quiz-ai-card-img-container'>";
        if ($has_image) {
            echo "<img src='$img' alt='$title' class='quiz-ai-card-img'/>";
        } else {
            // Improved placeholder with icon and label
            echo "<div class='quiz-ai-card-img-placeholder'>
                    <svg width='48' height='48' viewBox='0 0 48 48' fill='none' xmlns='http://www.w3.org/2000/svg' class='quiz-ai-card-img-placeholder-icon'>
                        <rect width='48' height='48' rx='12' fill='#e0e0e0'/>
                        <path d='M24 14C20.6863 14 18 16.6863 18 20C18 23.3137 20.6863 26 24 26C27.3137 26 30 23.3137 30 20C30 16.6863 27.3137 14 24 14ZM24 24C21.7909 24 20 22.2091 20 20C20 17.7909 21.7909 16 24 16C26.2091 16 28 17.7909 28 20C28 22.2091 26.2091 24 24 24Z' fill='#bdbdbd'/>
                        <path d='M12 36C12 31.5817 16.5817 28 22 28H26C31.4183 28 36 31.5817 36 36V38H12V36Z' fill='#bdbdbd'/>
                    </svg>
                    <span class='quiz-ai-card-img-placeholder-label'>No Image</span>
                </div>";
        }
        echo "    </div>
                    <div class='quiz-ai-card-footer'>
                        <span class='quiz-ai-card-footer-text'>$footer_text</span>
                    </div>
                </a>
                <div class='quiz-ai-card-title-hover'>$title</div>
            </div>";
    }
    echo '</div>';
    return ob_get_clean();
}
add_shortcode('quiz_ai_latest_quizzes', 'quiz_ai_latest_quizzes_shortcode');

// Enqueue styles for the card layout
function quiz_ai_latest_quizzes_enqueue_styles()
{
    wp_register_style('quiz-ai-latest-quizzes', plugins_url('assets/css/quiz-ai-latest-quizzes.css', defined('QUIZ_IA_PRO_PLUGIN_FILE') ? QUIZ_IA_PRO_PLUGIN_FILE : __FILE__));
    wp_enqueue_style('quiz-ai-latest-quizzes');
}
add_action('wp_enqueue_scripts', 'quiz_ai_latest_quizzes_enqueue_styles');
