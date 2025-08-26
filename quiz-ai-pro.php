<?php

/**
 * Plugin Name: Quiz IA Pro
 * Plugin URI: https://github.com/SohailPro12/quiz-ai
 * Description: Plateforme intelligente de génération et gestion de quiz avec IA
 * Version: 1.0.0
 * Author: Sohail Charef
 * Author URI: https://github.com/SohailPro12/
 * License: GPL v2 or later
 * Text Domain: quiz-ia-pro
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('QUIZ_IA_PRO_VERSION', '1.0.0');
define('QUIZ_IA_PRO_PLUGIN_FILE', __FILE__);
define('QUIZ_IA_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('QUIZ_IA_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class QuizIAPro
{

    public function __construct()
    {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('admin_init', array($this, 'check_database_status'));
        // Removed table recreation hook since we'll use AJAX instead
        register_activation_hook(__FILE__, function () {
            try {
                error_log('Quiz IA Pro: Starting plugin activation...');

                // Only do basic table creation during activation
                if (function_exists('quiz_ai_pro_create_all_tables_safe')) {
                    $result = quiz_ai_pro_create_all_tables_safe();
                    if ($result) {
                        error_log('Quiz IA Pro: Tables created successfully during activation');
                    } else {
                        error_log('Quiz IA Pro: Warning - Some tables may not have been created properly');
                    }
                } else {
                    error_log('Quiz IA Pro: Function quiz_ai_pro_create_all_tables_safe not found during activation');
                }
                $days = 15; // Number of days to keep logs
                global $wpdb;
                $table_name = $wpdb->prefix . 'quiz_ia_security_logs';
                return $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                    intval($days)
                ));
                // Flush rewrite rules for unsubscribe page
                flush_rewrite_rules();

                error_log('Quiz IA Pro: Plugin activation completed successfully');
            } catch (Exception $e) {
                error_log('Quiz IA Pro Activation Error: ' . $e->getMessage());
            }
        });
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        // Optionally, schedule cleanup on plugin activation

    }

    public function init()
    {
        // Initialize plugin
        $this->load_includes();
        $this->register_shortcodes();
    }

    /**
     * Load include files
     */
    private function load_includes()
    {
        require_once QUIZ_IA_PRO_PLUGIN_DIR . 'includes/db-functions.php';
        require_once QUIZ_IA_PRO_PLUGIN_DIR . 'includes/rag-functions.php';
        require_once QUIZ_IA_PRO_PLUGIN_DIR . 'includes/ajax-handlers.php';
        require_once QUIZ_IA_PRO_PLUGIN_DIR . 'includes/helpers.php';
        require_once QUIZ_IA_PRO_PLUGIN_DIR . 'includes/enhanced-feedback.php';
        require_once QUIZ_IA_PRO_PLUGIN_DIR . 'includes/learnpress-integration.php';
        require_once QUIZ_IA_PRO_PLUGIN_DIR . 'includes/frontend-functions.php';
        require_once QUIZ_IA_PRO_PLUGIN_DIR . 'includes/email-functions.php';

        // Initialize AJAX handlers
        new QuizGeneratorAjax();

        // Hook for new quiz notifications
        add_action('quiz_ia_pro_quiz_published', array($this, 'send_new_quiz_notifications'));

        // Register unsubscribe shortcode
        add_shortcode('quiz_ai_unsubscribe', array($this, 'unsubscribe_shortcode_handler'));
    }

    /**
     * Register shortcodes
     */
    private function register_shortcodes()
    {
        add_shortcode('quiz_categories', array($this, 'quiz_categories_shortcode'));
        // Register the latest quizzes shortcode
        require_once QUIZ_IA_PRO_PLUGIN_DIR . 'includes/quiz-ai-latest-quizzes-shortcode.php';
        add_shortcode('quiz_ai_latest_quizzes', 'quiz_ai_latest_quizzes_shortcode');
        add_action('wp_enqueue_scripts', 'quiz_ai_latest_quizzes_enqueue_styles');
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        // Load database functions
        require_once QUIZ_IA_PRO_PLUGIN_DIR . 'includes/db-functions.php';
        require_once QUIZ_IA_PRO_PLUGIN_DIR . 'includes/rag-functions.php';

        // Create all tables dynamically (without RAG processing during activation)
        $table_creation_result = quiz_ai_pro_create_all_tables_safe();

        if ($table_creation_result['success']) {
            // Log activation success
            error_log('Quiz IA Pro: Plugin activated successfully. Tables created: ' . implode(', ', $table_creation_result['created']));

            // Set activation flag
            update_option('quiz_ia_pro_activated', true);
            update_option('quiz_ia_pro_activation_date', current_time('mysql'));

            // Schedule RAG processing for later (after all plugins are loaded)
            wp_schedule_single_event(time() + 30, 'quiz_ai_pro_process_courses_delayed');
        } else {
            // Log activation errors
            error_log('Quiz IA Pro: Plugin activation had errors. Failed tables: ' . implode(', ', $table_creation_result['failed']));
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        // Log deactivation
        error_log('Quiz IA Pro: Plugin deactivated');

        // You can add cleanup tasks here if needed
        // Note: We don't drop tables on deactivation, only on uninstall
    }

    /**
     * Check database status and display admin notices if needed
     */
    public function check_database_status()
    {
        // Only check on Quiz IA Pro admin pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'quiz-ia-pro') === false) {
            return;
        }

        // Load database functions
        require_once QUIZ_IA_PRO_PLUGIN_DIR . 'includes/db-functions.php';

        $table_check = quiz_ai_pro_check_all_tables();

        if (!$table_check['all_exist']) {
            add_action('admin_notices', function () use ($table_check) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>Quiz IA Pro:</strong> Some database tables are missing: ' . implode(', ', $table_check['missing']) . '</p>';
                echo '<p><button id="recreate-tables-btn" class="button button-primary">Recreate Tables</button></p>';
                echo '</div>';
            });
        }
    }

    public function admin_menu()
    {
        // Main menu
        add_menu_page(
            'Quiz IA Pro',
            'Quiz IA Pro',
            'edit_posts',
            'quiz-ai-pro',
            array($this, 'admin_dashboard'),
            'dashicons-chart-area',
            30
        );

        // Submenu - Liste des Quiz
        add_submenu_page(
            'quiz-ai-pro',
            'Liste des Quiz',
            'Liste des Quiz',
            'edit_posts',
            'quiz-ai-pro-list',
            array($this, 'quiz_list_page')
        );

        // Submenu - Générer un Quiz
        add_submenu_page(
            'quiz-ai-pro',
            'Générer un Quiz',
            'Générer un Quiz',
            'edit_posts',
            'quiz-ai-pro-generate',
            array($this, 'generate_quiz_page')
        );

        // Submenu - Modifier un Quiz (hidden from menu)
        add_submenu_page(
            null, // null parent to hide from menu
            'Modifier un Quiz',
            'Modifier un Quiz',
            'edit_posts',
            'quiz-ai-pro-edit',
            array($this, 'edit_quiz_page')
        );

        // Submenu - Résultats & Statistiques
        add_submenu_page(
            'quiz-ai-pro',
            'Résultats & Statistiques',
            'Résultats & Stats',
            'edit_posts',
            'quiz-ai-pro-stats',
            array($this, 'stats_page')
        );

        // Submenu - Email Notifications
        add_submenu_page(
            'quiz-ai-pro',
            'Notifications Email',
            'Notifications Email',
            'manage_options',
            'quiz-ia-pro-emails',
            array($this, 'emails_page')
        );
    }

    public function admin_scripts($hook)
    {
        // Load scripts on all admin pages for table recreation functionality
        wp_enqueue_script('quiz-ia-pro-admin-global', QUIZ_IA_PRO_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), QUIZ_IA_PRO_VERSION, true);

        // Global localization for table recreation
        wp_localize_script('quiz-ia-pro-admin-global', 'quiz_ai_pro_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('quiz_ai_pro_nonce')
        ));

        // Also add the expected variable name for new functionality
        wp_localize_script('quiz-ia-pro-admin-global', 'quiz_ai_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('quiz_ai_admin_nonce')
        ));

        // Add inline script for table recreation
        $inline_script = "
        jQuery(document).ready(function($) {
            // Handle table recreation via AJAX
            $(document).on('click', '#recreate-tables-btn', function(e) {
                e.preventDefault();
                
                if (!confirm('Êtes-vous sûr de vouloir recréer les tables de la base de données ?')) {
                    return;
                }
                
                const \$button = $(this);
                const originalText = \$button.text();
                
                // Show loading state
                \$button.prop('disabled', true).text('🔄 Création en cours...');
                
                $.ajax({
                    url: quiz_ai_pro_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'quiz_ai_force_update_tables',
                        nonce: quiz_ai_pro_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            \$('.notice-error').hide();
                            \$('<div class=\"notice notice-success is-dismissible\"><p><strong>Quiz IA Pro:</strong> Tables de base de données créées avec succès!</p></div>')
                                .insertAfter('.wrap h1');
                            
                            // Remove the button since tables are now created
                            \$button.closest('.notice').fadeOut();
                        } else {
                            alert('Erreur: ' + (response.data || 'Une erreur inconnue est survenue'));
                            \$button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Erreur AJAX: ' + error);
                        \$button.prop('disabled', false).text(originalText);
                    }
                });
            });
        });";
        wp_add_inline_script('quiz-ia-pro-admin-global', $inline_script);

        // Load additional scripts only on Quiz IA Pro pages
        if (strpos($hook, 'quiz-ia-pro') !== false) {
            wp_enqueue_style('quiz-ia-pro-admin', QUIZ_IA_PRO_PLUGIN_URL . 'assets/css/admin.css', array(), QUIZ_IA_PRO_VERSION);

            // Localize script for AJAX
            wp_localize_script('quiz-ia-pro-admin-global', 'quiz_ai_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('quiz_generator_action'),
                'messages' => array(
                    'generating' => __('Génération en cours...', 'quiz-ia-pro'),
                    'success' => __('Quiz généré avec succès!', 'quiz-ia-pro'),
                    'error' => __('Erreur lors de la génération', 'quiz-ia-pro'),
                    'draft_saved' => __('Brouillon sauvegardé', 'quiz-ia-pro')
                )
            ));
        }
    }

    public function admin_dashboard()
    {
        include QUIZ_IA_PRO_PLUGIN_DIR . 'admin/dashboard.php';
    }

    public function quiz_list_page()
    {
        include QUIZ_IA_PRO_PLUGIN_DIR . 'admin/quiz-list.php';
    }

    public function generate_quiz_page()
    {
        include QUIZ_IA_PRO_PLUGIN_DIR . 'admin/generate-quiz.php';
    }

    public function edit_quiz_page()
    {
        include QUIZ_IA_PRO_PLUGIN_DIR . 'admin/edit-quiz.php';
    }

    public function stats_page()
    {
        include QUIZ_IA_PRO_PLUGIN_DIR . 'admin/stats.php';
    }

    public function emails_page()
    {
        include QUIZ_IA_PRO_PLUGIN_DIR . 'admin/emails.php';
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function frontend_scripts()
    {
        global $post;

        // Only enqueue on pages that contain our shortcode
        if ($post && has_shortcode($post->post_content, 'quiz_categories')) {
            wp_enqueue_style('quiz-ia-pro-frontend', QUIZ_IA_PRO_PLUGIN_URL . 'assets/css/frontend.css', array(), QUIZ_IA_PRO_VERSION);
            wp_enqueue_style('quiz-ia-pro-enhanced-feedback', QUIZ_IA_PRO_PLUGIN_URL . 'assets/css/enhanced-feedback.css', array('quiz-ia-pro-frontend'), QUIZ_IA_PRO_VERSION);
            wp_enqueue_script('quiz-ia-pro-frontend', QUIZ_IA_PRO_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), QUIZ_IA_PRO_VERSION, true);

            // Localize script for AJAX
            wp_localize_script('quiz-ia-pro-frontend', 'quiz_ai_frontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('quiz_frontend_action'),
                'is_user_logged_in' => is_user_logged_in(),
                'login_url' => 'https://innovation.ma/',
                'register_url' => site_url('/wp-login.php?action=register'),
                'user_email' => is_user_logged_in() ? wp_get_current_user()->user_email : '',
                'messages' => array(
                    'loading' => __('Chargement...', 'quiz-ia-pro'),
                    'error' => __('Une erreur est survenue', 'quiz-ia-pro'),
                    'no_quizzes' => __('Aucun quiz trouvé', 'quiz-ia-pro')
                )
            ));
        }
    }

    /**
     * Quiz Categories Shortcode Handler
     */
    public function quiz_categories_shortcode($atts)
    {
        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'columns' => '3',
            'show_empty' => 'false',
            'order' => 'name',
            'orderby' => 'ASC'
        ), $atts, 'quiz_categories');

        // Load frontend functions
        require_once QUIZ_IA_PRO_PLUGIN_DIR . 'includes/frontend-functions.php';

        // Generate and return the HTML
        return quiz_ai_pro_render_categories_with_quizzes($atts);
    }

    /**
     * Send new quiz notifications
     */
    public function send_new_quiz_notifications($quiz_data)
    {
        if (function_exists('quiz_ai_pro_send_new_quiz_alert')) {
            quiz_ai_pro_send_new_quiz_alert($quiz_data);
        }
    }

    /**
     * Handle unsubscribe requests
     */
    public function handle_unsubscribe_requests()
    {
        if (isset($_GET['quiz-unsubscribe'])) {
            if (function_exists('quiz_ai_pro_handle_unsubscribe')) {
                $result = quiz_ai_pro_handle_unsubscribe();

                if ($result) {
                    wp_die('<h1>Désinscription réussie</h1><p>Vous avez été désinscrit avec succès de nos emails.</p>');
                } else {
                    wp_die('<h1>Erreur</h1><p>Une erreur est survenue lors de la désinscription.</p>');
                }
            }
        }
    }

    /**
     * Unsubscribe shortcode handler
     */
    public function unsubscribe_shortcode_handler($atts)
    {
        ob_start();
        $type_label = '';
        $success = false;
        if (isset($_GET['email']) && isset($_GET['type']) && isset($_GET['token'])) {
            if (function_exists('quiz_ai_pro_handle_unsubscribe')) {
                $success = quiz_ai_pro_handle_unsubscribe();
                $type = sanitize_text_field($_GET['type']);
                if ($type === 'quiz_results') {
                    $type_label = 'des notifications de résultats de quiz';
                } elseif ($type === 'new_quiz_alerts') {
                    $type_label = 'des notifications de nouveaux quiz';
                } else {
                    $type_label = 'de toutes les notifications email';
                }
            }
        }
?>
        <div class="unsubscribe-container" style="max-width:500px;margin:60px auto;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.08);padding:40px;text-align:center;">
            <?php if ($success): ?>
                <h1 style="color:#0073aa;margin-bottom:20px;">Désinscription réussie</h1>
                <p class="success" style="color:#28a745;font-weight:bold;">Vous avez été désinscrit avec succès <?php echo $type_label; ?>.</p>
            <?php else: ?>
                <h1 style="color:#0073aa;margin-bottom:20px;">Erreur</h1>
                <p class="error" style="color:#dc3545;font-weight:bold;">Une erreur est survenue lors de la désinscription.<br>Vérifiez le lien ou contactez le support.</p>
            <?php endif; ?>
            <p><a href="<?php echo esc_url(home_url()); ?>" style="color:#0073aa;text-decoration:none;">← Retour au site</a></p>
        </div>
<?php
        return ob_get_clean();
    }
}

// Initialize the plugin
new QuizIAPro();
