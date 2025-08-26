<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Handle category filter from GET
$category_filter = isset($_GET['filter_category']) ? intval($_GET['filter_category']) : '';

// Build filters array for query
$filters = [];
if ($category_filter) {
    $filters['category'] = $category_filter;
}
$status_filter = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
if ($status_filter) {
    $filters['status'] = $status_filter;
}

// Get filtered quizzes from database
if (!empty($filters)) {

    // Get filtered quizzes
    $quizzes = quiz_ai_pro_get_filtered_quizzes($filters, 1, 100);
    error_log('DEBUG: quizzes=' . print_r($quizzes, true));
    $quizzes = is_array($quizzes) && isset($quizzes['quizzes']) ? $quizzes['quizzes'] : [];
} else {
    $quizzes = quiz_ai_pro_get_all_quizzes_with_details(100, 0);
}

// Get LearnPress course categories for filter dropdown
global $wpdb;
$categories = $wpdb->get_results(
    "SELECT t.term_id as id, t.name 
     FROM {$wpdb->terms} t 
     INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
     WHERE tt.taxonomy = 'course_category' 
     ORDER BY t.name"
);

// Calculate pagination info
$total_quizzes = count($quizzes);
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$total_pages = max(1, ceil($total_quizzes / $per_page));
$offset = ($current_page - 1) * $per_page;
$display_quizzes = array_slice($quizzes, $offset, $per_page);

// Define status filter variable
$status_filter = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
?>

<div class="wrap quiz-ia-pro-admin">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-chart-area"></span>
        Quiz IA Pro - Liste des Quiz
    </h1>

    <hr class="wp-header-end">

    <!-- Filtres avancés -->
    <div class="quiz-filters-section">
        <div class="filters-container">
            <div class="filter-group">
                <label for="filter-date">Période :</label>
                <select id="filter-date" name="filter_date">
                    <option value="">Toutes les dates</option>
                    <option value="today">Aujourd'hui</option>
                    <option value="week">Cette semaine</option>
                    <option value="month">Ce mois</option>
                    <option value="custom">Période personnalisée</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="filter-status">Statut :</label>
                <select id="filter-status" name="filter_status">
                    <option value="">Tous les statuts</option>
                    <option value="published">✅ Publié</option>
                    <option value="draft">🟡 Brouillon</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="filter-category">Catégorie :</label>
                <select id="filter-category" name="filter_category">
                    <option value="">Toutes les catégories</option>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo esc_attr($category->id); ?>"><?php echo esc_html($category->name); ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>Aucune catégorie trouvée</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="filter-group">
                <input type="text" id="search-quiz" name="search_quiz" placeholder="Rechercher un quiz..." class="search-input">
            </div>

            <div class="filter-actions">
                <button type="button" class="button reset-btn">Réinitialiser</button>
            </div>
        </div>
    </div>

    <!-- Statistiques rapides -->
    <div class="quiz-stats-quick">
        <div class="stats-quick-item">
            <span class="stats-number"><?php echo count($quizzes); ?></span>
            <span class="stats-label">Total Quiz</span>
        </div>
        <div class="stats-quick-item">
            <span class="stats-number"><?php echo count(array_filter($quizzes, function ($q) {
                                            return $q->status === 'published';
                                        })); ?></span>
            <span class="stats-label">Publiés</span>
        </div>
        <div class="stats-quick-item">
            <span class="stats-number"><?php echo count(array_filter($quizzes, function ($q) {
                                            return $q->status === 'draft';
                                        })); ?></span>
            <span class="stats-label">Brouillons</span>
        </div>
    </div>

    <!-- Navigation haute -->
    <div class="tablenav top">
        <div class="alignleft actions bulkactions">
            <label for="bulk-action-selector-top" class="screen-reader-text">Sélectionner une action groupée</label>
            <select name="action" id="bulk-action-selector-top">
                <option value="-1">Actions groupées</option>
                <?php if ($status_filter === 'published'): ?>
                    <option value="unpublish">Dépublier</option>
                <?php elseif ($status_filter === 'draft'): ?>
                    <option value="publish">Publier</option>
                <?php else: ?>
                    <option value="publish">Publier</option>
                    <option value="unpublish">Dépublier</option>
                <?php endif; ?>
                <option value="delete">Supprimer</option>
            </select>
            <input type="submit" id="doaction" class="button action" value="Appliquer">
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo count($quizzes); ?> éléments</span>
                <span class="pagination-links">
                    <?php if ($current_page > 1): ?>
                        <a class="prev-page button" href="<?php echo add_query_arg('paged', $current_page - 1); ?>">
                            <span class="screen-reader-text">Page précédente</span><span aria-hidden="true">‹</span>
                        </a>
                    <?php else: ?>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
                    <?php endif; ?>

                    <span class="paging-input">
                        <label for="current-page-selector-top" class="screen-reader-text">Page actuelle</label>
                        <input class="current-page" id="current-page-selector-top" type="text" name="paged" value="<?php echo $current_page; ?>" size="1">
                        <span class="tablenav-paging-text"> sur <span class="total-pages"><?php echo $total_pages; ?></span></span>
                    </span>

                    <?php if ($current_page < $total_pages): ?>
                        <a class="next-page button" href="<?php echo add_query_arg('paged', $current_page + 1); ?>">
                            <span class="screen-reader-text">Page suivante</span><span aria-hidden="true">›</span>
                        </a>
                    <?php else: ?>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <form id="quiz-list-form" method="post">
        <?php wp_nonce_field('quiz_ai_admin_nonce', 'quiz_ai_nonce'); ?>
        <table class="wp-list-table widefat fixed striped quiz-list-table">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <label class="screen-reader-text" for="cb-select-all-1">Tout sélectionner</label>
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <th scope="col" id="title" class="manage-column column-title column-primary sortable desc">
                        <a href="#"><span>Titre du Quiz</span><span class="sorting-indicator"></span></a>
                    </th>
                    <th scope="col" id="code" class="manage-column column-code">Code</th>
                    <th scope="col" id="questions" class="manage-column column-questions">Questions</th>
                    <th scope="col" id="views" class="manage-column column-views">Vues</th>
                    <th scope="col" id="participants" class="manage-column column-participants">Participants</th>
                    <th scope="col" id="learnpress" class="manage-column column-learnpress">LearnPress</th>
                    <th scope="col" id="status" class="manage-column column-status">Statut</th>
                    <th scope="col" id="date" class="manage-column column-date sortable asc">
                        <a href="#"><span>Date de création</span><span class="sorting-indicator"></span></a>
                    </th>
                </tr>
            </thead>

            <tbody id="the-list">
                <?php if (!empty($display_quizzes)): ?>
                    <?php foreach ($display_quizzes as $index => $quiz): ?>
                        <?php
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
                        ?>
                        <tr id="quiz-<?php echo $quiz->id; ?>" class="quiz-item">
                            <th scope="row" class="check-column">
                                <label class="screen-reader-text" for="cb-select-<?php echo $quiz->id; ?>">Sélectionner <?php echo $title; ?></label>
                                <input id="cb-select-<?php echo $quiz->id; ?>" type="checkbox" name="quiz[]" value="<?php echo $quiz->id; ?>">
                            </th>
                            <td class="title column-title has-row-actions column-primary" data-colname="Titre">
                                <strong>
                                    <span class="row-title" aria-label="<?php echo $title; ?>">
                                        <?php echo $title; ?>
                                    </span>
                                </strong>
                                <div class="quiz-description"><?php echo wp_trim_words($description, 15); ?></div>
                                <div class="quiz-meta">
                                    <small>
                                        <strong>Cours:</strong>
                                        <div class="course-list" style="display: inline-block; margin-right: 8px;">
                                            <?php foreach ($course_names as $course_name): ?>
                                                <span class="course-tag"><?php echo esc_html($course_name); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        | <strong>Catégories:</strong>
                                        <div class="category-list" style="display: inline-block;">
                                            <?php foreach ($category_names as $category_name): ?>
                                                <span class="category-tag"><?php echo esc_html($category_name); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if ($quiz->ai_generated): ?>
                                            | <span class="ai-badge">🤖 IA</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="row-actions">
                                    <span class="edit"><a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-edit&quiz_id=' . $quiz->id); ?>" aria-label="Modifier">Modifier</a> | </span>
                                    <?php if ($quiz->status === 'published'): ?>

                                        <span class="unpublish"><a href="#" class="quiz-action" data-action="unpublish" data-quiz-id="<?php echo esc_attr($quiz->id); ?>" aria-label="Dépublier">Dépublier</a> | </span>
                                    <?php else: ?>
                                        <span class="publish"><a href="#" class="quiz-action" data-action="publish" data-quiz-id="<?php echo esc_attr($quiz->id); ?>" aria-label="Publier">Publier</a> | </span>
                                    <?php endif; ?>
                                    <span class="trash"><a href="#" class="quiz-action" data-action="delete" data-quiz-id="<?php echo esc_attr($quiz->id); ?>" aria-label="Supprimer">Supprimer</a></span>
                                </div>
                            </td>
                            <td class="code column-code" data-colname="Code">
                                <span class="quiz-code"><?php echo $quiz_code; ?></span>
                                <button type="button" class="button-link copy-code" title="Copier le code">
                                    <span class="dashicons dashicons-admin-page"></span>
                                </button>
                            </td>
                            <td class="questions column-questions" data-colname="Questions">
                                <span class="questions-count"><?php echo intval($quiz->question_count ?? $quiz->total_questions ?? 0); ?></span>
                                <div class="questions-breakdown">
                                    <small>Questions générées</small>
                                </div>
                            </td>
                            <td class="views column-views" data-colname="Vues">
                                <span class="views-count"><?php echo intval($quiz->views ?: 0); ?></span>
                            </td>
                            <td class="participants column-participants" data-colname="Participants">
                                <span class="participants-count"><?php echo intval($quiz->participants ?: 0); ?></span>
                                <?php if ($quiz->participants > 0): ?>
                                    <div class="completion-rate">
                                        <small>Tentatives: <?php echo intval($quiz->attempts ?: 0); ?></small>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="learnpress column-learnpress" data-colname="LearnPress">
                                <?php if (!empty($quiz->learnpress_quiz_id)): ?>
                                    <?php
                                    // Check if LearnPress quiz actually exists
                                    $lp_quiz_exists = false;
                                    if (class_exists('LearnPress')) {
                                        $lp_quiz = get_post($quiz->learnpress_quiz_id);
                                        $lp_quiz_exists = ($lp_quiz && $lp_quiz->post_type === 'lp_quiz' && $lp_quiz->post_status !== 'trash');
                                    }
                                    ?>

                                    <?php if ($lp_quiz_exists): ?>
                                        <span class="learnpress-synced" title="Synchronisé avec LearnPress (ID: <?php echo intval($quiz->learnpress_quiz_id); ?>)">
                                            <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                            Synchronisé
                                        </span>
                                        <?php if (class_exists('LearnPress')): ?>
                                            <div class="row-actions">
                                                <span class="view-learnpress">
                                                    <a href="#" class="create-learnpress-quiz" data-quiz-id="<?php echo intval($quiz->id); ?>">
                                                        Re-sync
                                                    </a>
                                                </span> |
                                                <span class="view-learnpress-edit">
                                                    <a href="<?php echo admin_url('post.php?post=' . intval($quiz->learnpress_quiz_id) . '&action=edit'); ?>" target="_blank">
                                                        Voir dans LearnPress
                                                    </a>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="learnpress-broken" title="Quiz LearnPress supprimé ou inexistant (ID: <?php echo intval($quiz->learnpress_quiz_id); ?>)">
                                            <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                                            Lien brisé
                                        </span>
                                        <?php if (class_exists('LearnPress')): ?>
                                            <div class="row-actions">
                                                <span class="create-learnpress">
                                                    <a href="#" class="create-learnpress-quiz" data-quiz-id="<?php echo intval($quiz->id); ?>">
                                                        Re-créer
                                                    </a>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="learnpress-not-synced" title="Non synchronisé avec LearnPress">
                                        <span class="dashicons dashicons-minus" style="color: #dba617;"></span>
                                        Non synchronisé
                                    </span>
                                    <?php if (class_exists('LearnPress')): ?>
                                        <div class="row-actions">
                                            <span class="create-learnpress">
                                                <a href="#" class="create-learnpress-quiz" data-quiz-id="<?php echo intval($quiz->id); ?>">
                                                    Créer dans LearnPress
                                                </a>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="status column-status" data-colname="Statut">
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td class="date column-date" data-colname="Date">
                                <abbr title="<?php echo esc_attr($quiz->created_at); ?>"><?php echo $created_at; ?></abbr>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="no-items">
                        <td class="colspanchange" colspan="8">
                            <div class="no-quiz-message">
                                <h3>Aucun quiz disponible</h3>
                                <p>Vous n'avez pas encore créé de quiz avec les filtres actuels.</p>
                                <p>Ajustez vos critères de recherche ou réinitialisez les filtres.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <!-- Pagination bas -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-bottom" class="screen-reader-text">Sélectionner une action groupée</label>
                <select name="action2" id="bulk-action-selector-bottom">
                    <option value="-1">Actions groupées</option>
                    <option value="publish">Publier</option>
                    <option value="unpublish">Dépublier</option>
                    <option value="delete">Supprimer</option>
                </select>
                <input type="submit" id="doaction2" class="button action" value="Appliquer">
            </div>

            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo count($quizzes); ?> éléments</span>
                <span class="pagination-links">
                    <?php if ($current_page > 1): ?>
                        <a class="prev-page button" href="<?php echo add_query_arg('paged', $current_page - 1); ?>">
                            <span class="screen-reader-text">Page précédente</span><span aria-hidden="true">‹</span>
                        </a>
                    <?php else: ?>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
                    <?php endif; ?>

                    <span class="paging-input">
                        <label for="current-page-selector-bottom" class="screen-reader-text">Page actuelle</label>
                        <input class="current-page" id="current-page-selector-bottom" type="text" name="paged" value="<?php echo $current_page; ?>" size="1">
                        <span class="tablenav-paging-text"> sur <span class="total-pages"><?php echo $total_pages; ?></span></span>
                    </span>

                    <?php if ($current_page < $total_pages): ?>
                        <a class="next-page button" href="<?php echo add_query_arg('paged', $current_page + 1); ?>">
                            <span class="screen-reader-text">Page suivante</span><span aria-hidden="true">›</span>
                        </a>
                    <?php else: ?>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>