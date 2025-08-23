<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get real statistics from database
$detailed_stats = quiz_ai_pro_get_detailed_stats();
$popular_quizzes = quiz_ai_pro_get_popular_quizzes(3);

// Get pagination and filter parameters
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$quiz_filter = isset($_GET['quiz_filter']) ? intval($_GET['quiz_filter']) : 0;

// Get detailed results
$detailed_results = quiz_ai_pro_get_detailed_results('30days', $quiz_filter, $search, $per_page, $offset);
$total_results = quiz_ai_pro_get_results_count($search, $quiz_filter);
$total_pages = ceil($total_results / $per_page);

// Get all quizzes for filter dropdown
global $wpdb;
$all_quizzes = $wpdb->get_results(
    "SELECT id, title FROM {$wpdb->prefix}quiz_ia_quizzes WHERE status = 'published' ORDER BY title"
);

// Helper function to format time
function format_time_duration($seconds)
{
    if (!$seconds || $seconds <= 0) return '--';
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    return sprintf('%d:%02d', $minutes, $seconds);
}

// Helper function to get score badge class
function get_score_badge_class($percentage)
{
    if ($percentage >= 80) return 'score-excellent';
    if ($percentage >= 60) return 'score-good';
    return 'score-average';
}
?>

<div class="wrap quiz-ia-pro-admin">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-chart-bar"></span>
        Quiz IA Pro - Résultats & Statistiques
    </h1>

    <hr class="wp-header-end">

    <!-- Vue d'ensemble -->
    <div class="stats-overview">
        <div class="stats-cards">
            <div class="stats-card">
                <div class="stats-card-icon">
                    <span class="dashicons dashicons-chart-area"></span>
                </div>
                <div class="stats-card-content">
                    <h3>Quiz Actifs</h3>
                    <div class="stats-number"><?php echo esc_html($detailed_stats['active_quizzes']); ?></div>
                    <div class="stats-change <?php echo $detailed_stats['new_quizzes_month'] > 0 ? 'positive' : 'neutral'; ?>">
                        <?php if ($detailed_stats['new_quizzes_month'] > 0): ?>
                            +<?php echo esc_html($detailed_stats['new_quizzes_month']); ?> ce mois
                        <?php else: ?>
                            Aucun nouveau ce mois
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="stats-card">
                <div class="stats-card-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="stats-card-content">
                    <h3>Participants Totaux</h3>
                    <div class="stats-number"><?php echo esc_html(number_format($detailed_stats['total_participants'])); ?></div>
                    <div class="stats-change <?php echo $detailed_stats['new_participants_month'] > 0 ? 'positive' : 'neutral'; ?>">
                        <?php if ($detailed_stats['new_participants_month'] > 0): ?>
                            +<?php echo esc_html($detailed_stats['new_participants_month']); ?> ce mois
                        <?php else: ?>
                            Aucun nouveau ce mois
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="stats-card">
                <div class="stats-card-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="stats-card-content">
                    <h3>Taux de Réussite Moyen</h3>
                    <div class="stats-number"><?php echo esc_html($detailed_stats['success_rate']); ?>%</div>
                    <div class="stats-change <?php echo $detailed_stats['success_rate_change'] >= 0 ? 'positive' : 'negative'; ?>">
                        <?php if ($detailed_stats['success_rate_change'] > 0): ?>
                            +<?php echo esc_html($detailed_stats['success_rate_change']); ?>% ce mois
                        <?php elseif ($detailed_stats['success_rate_change'] < 0): ?>
                            <?php echo esc_html($detailed_stats['success_rate_change']); ?>% ce mois
                        <?php else: ?>
                            Stable ce mois
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="stats-card">
                <div class="stats-card-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="stats-card-content">
                    <h3>Temps Moyen</h3>
                    <div class="stats-number"><?php echo format_time_duration($detailed_stats['average_time']); ?></div>
                    <div class="stats-change <?php echo $detailed_stats['time_change'] <= 0 ? 'positive' : 'negative'; ?>">
                        <?php if ($detailed_stats['time_change'] > 0): ?>
                            +<?php echo format_time_duration(abs($detailed_stats['time_change'])); ?> ce mois
                        <?php elseif ($detailed_stats['time_change'] < 0): ?>
                            -<?php echo format_time_duration(abs($detailed_stats['time_change'])); ?> ce mois
                        <?php else: ?>
                            Stable ce mois
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphiques et analyses -->
    <div class="stats-dashboard">
        <div class="dashboard-row">
            <!-- Top Quiz -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h3>Quiz les Plus Populaires</h3>
                </div>
                <div class="widget-content">
                    <div class="top-quiz-list">
                        <?php if (!empty($popular_quizzes)): ?>
                            <?php foreach ($popular_quizzes as $index => $quiz): ?>
                                <div class="top-quiz-item">
                                    <div class="quiz-rank"><?php echo $index + 1; ?></div>
                                    <div class="quiz-info">
                                        <div class="quiz-title"><?php echo esc_html($quiz->title); ?></div>
                                        <div class="quiz-stats">
                                            <?php echo esc_html($quiz->total_attempts); ?> tentatives •
                                            <?php echo esc_html($quiz->unique_participants); ?> participants
                                            <?php if ($quiz->course_title): ?>
                                                • <?php echo esc_html($quiz->course_title); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="quiz-score"><?php echo esc_html(round($quiz->avg_score)); ?>%</div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data-message">
                                <p>Aucun quiz avec des résultats pour le moment.</p>
                                <p><em>Les quiz apparaîtront ici une fois que des utilisateurs auront commencé à les compléter.</em></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-row">
            <!-- Tableau des résultats détaillés -->
            <div class="dashboard-widget full-width">
                <div class="widget-header">
                    <h3>Résultats Détaillés</h3>
                    <div class="widget-actions">
                        <form method="get" style="display: inline-flex; align-items: center; gap: 10px;">
                            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>">
                            <select name="quiz_filter" onchange="this.form.submit()">
                                <option value="">Tous les quiz</option>
                                <?php foreach ($all_quizzes as $quiz_option): ?>
                                    <option value="<?php echo esc_attr($quiz_option->id); ?>"
                                        <?php selected(isset($_GET['quiz_filter']) ? $_GET['quiz_filter'] : '', $quiz_option->id); ?>>
                                        <?php echo esc_html($quiz_option->title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="search" placeholder="Rechercher un participant..."
                                class="search-participants" value="<?php echo esc_attr($search); ?>">
                            <button type="submit" class="button">Filtrer</button>
                            <?php if ($search || $quiz_filter): ?>
                                <a href="<?php echo esc_url(remove_query_arg(['search', 'paged', 'quiz_filter'])); ?>" class="button">Effacer</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <div class="widget-content">
                    <?php if (!empty($detailed_results)): ?>
                        <table class="wp-list-table widefat fixed striped results-table">
                            <thead>
                                <tr>
                                    <th scope="col" class="manage-column column-participant">Participant</th>
                                    <th scope="col" class="manage-column column-quiz">Quiz</th>
                                    <th scope="col" class="manage-column column-score">Score</th>
                                    <th scope="col" class="manage-column column-time">Temps</th>
                                    <th scope="col" class="manage-column column-attempts">Tentative</th>
                                    <th scope="col" class="manage-column column-date">Date</th>
                                    <th scope="col" class="manage-column column-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detailed_results as $result): ?>
                                    <?php
                                    $student_name = trim($result->first_name . ' ' . $result->last_name);
                                    if (empty($student_name)) $student_name = 'Utilisateur';
                                    $initials = '';
                                    if ($result->first_name && $result->last_name) {
                                        $initials = strtoupper(substr($result->first_name, 0, 1) . substr($result->last_name, 0, 1));
                                    } elseif ($result->first_name) {
                                        $initials = strtoupper(substr($result->first_name, 0, 2));
                                    } elseif ($result->email) {
                                        $initials = strtoupper(substr($result->email, 0, 2));
                                    } else {
                                        $initials = 'U';
                                    }
                                    ?>
                                    <tr>
                                        <td class="participant column-participant">
                                            <div class="participant-info">
                                                <div class="participant-avatar">
                                                    <span class="avatar-initials"><?php echo esc_html($initials); ?></span>
                                                </div>
                                                <div class="participant-details">
                                                    <strong><?php echo esc_html($student_name); ?></strong>
                                                    <?php if ($result->email): ?>
                                                        <div class="participant-email"><?php echo esc_html($result->email); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="quiz column-quiz">
                                            <a href="#" class="quiz-link"><?php echo esc_html($result->quiz_title); ?></a>
                                        </td>
                                        <td class="score column-score">
                                            <span class="score-badge <?php echo get_score_badge_class($result->percentage); ?>">
                                                <?php echo esc_html(round($result->percentage)); ?>%
                                            </span>
                                        </td>
                                        <td class="time column-time">
                                            <span class="time-duration"><?php echo format_time_duration($result->time_spent); ?></span>
                                        </td>
                                        <td class="attempts column-attempts">
                                            <span class="attempts-count"><?php echo esc_html($result->attempt_number); ?></span>
                                        </td>
                                        <td class="date column-date">
                                            <abbr title="<?php echo esc_attr($result->submitted_at); ?>">
                                                <?php echo esc_html(human_time_diff(strtotime($result->submitted_at), current_time('timestamp'))); ?> ago
                                            </abbr>
                                        </td>
                                        <td class="actions column-actions">
                                            <a href="#" class="button button-small view-details-btn"
                                                data-result-id="<?php echo esc_attr($result->id); ?>"
                                                data-participant="<?php echo esc_attr($student_name); ?>"
                                                data-quiz-title="<?php echo esc_attr($result->quiz_title); ?>">
                                                Voir détails
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if ($total_pages > 1): ?>
                            <div class="results-pagination">
                                <div class="tablenav-pages">
                                    <span class="displaying-num"><?php echo esc_html(number_format($total_results)); ?> résultats</span>
                                    <span class="pagination-links">
                                        <?php if ($page > 1): ?>
                                            <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $page - 1)); ?>">‹</a>
                                        <?php else: ?>
                                            <span class="tablenav-pages-navspan button disabled">‹</span>
                                        <?php endif; ?>

                                        <span class="paging-input">
                                            <input class="current-page" type="text" name="paged" value="<?php echo esc_attr($page); ?>" size="1">
                                            <span class="tablenav-paging-text"> sur <span class="total-pages"><?php echo esc_html($total_pages); ?></span></span>
                                        </span>

                                        <?php if ($page < $total_pages): ?>
                                            <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $page + 1)); ?>">›</a>
                                        <?php else: ?>
                                            <span class="tablenav-pages-navspan button disabled">›</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-data-message" style="padding: 40px; text-align: center;">
                            <?php if ($search): ?>
                                <h3>Aucun résultat trouvé</h3>
                                <p>Aucun résultat ne correspond à votre recherche "<?php echo esc_html($search); ?>".</p>
                                <a href="<?php echo esc_url(remove_query_arg(['search', 'paged'])); ?>" class="button">Voir tous les résultats</a>
                            <?php else: ?>
                                <h3>Aucun résultat disponible</h3>
                                <p>Il n'y a pas encore de résultats de quiz dans votre base de données.</p>
                                <p><em>Les résultats apparaîtront ici une fois que des utilisateurs auront commencé à compléter vos quiz.</em></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Result Details -->
<div id="result-details-modal" class="quiz-modal" style="display: none;">
    <div class="quiz-modal-overlay"></div>
    <div class="quiz-modal-content">
        <div class="quiz-modal-header">
            <h2>Détails du Résultat</h2>
            <button type="button" class="quiz-modal-close">&times;</button>
        </div>
        <div class="quiz-modal-body">
            <div id="result-details-content">
                <div class="loading">Chargement des détails...</div>
            </div>
        </div>
    </div>
</div>

<style>
    .quiz-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 9999;
    }

    .quiz-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
    }

    .quiz-modal-content {
        position: relative;
        background: white;
        margin: 2% auto;
        width: 90%;
        max-width: 800px;
        max-height: 90%;
        overflow-y: auto;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }

    .quiz-modal-header {
        padding: 20px;
        border-bottom: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .quiz-modal-header h2 {
        margin: 0;
        color: #333;
    }

    .quiz-modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #999;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .quiz-modal-close:hover {
        background: #f0f0f0;
        color: #333;
    }

    .quiz-modal-body {
        padding: 20px;
    }

    .result-details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .result-detail-card {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 6px;
        border-left: 4px solid #0073aa;
    }

    .result-detail-card h4 {
        margin: 0 0 10px 0;
        color: #333;
    }

    .result-detail-card .value {
        font-size: 18px;
        font-weight: bold;
        color: #0073aa;
    }

    .questions-details {
        margin-top: 20px;
    }

    .question-result {
        background: white;
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .question-result.correct {
        border-left: 4px solid #28a745;
    }

    .question-result.incorrect {
        border-left: 4px solid #dc3545;
    }

    .question-title {
        font-weight: bold;
        margin-bottom: 10px;
    }

    .answer-given {
        padding: 8px 12px;
        border-radius: 4px;
        margin: 5px 0;
    }

    .answer-given.correct {
        background: #d4edda;
        color: #155724;
    }

    .answer-given.incorrect {
        background: #f8d7da;
        color: #721c24;
    }
</style>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    jQuery(document).ready(function($) {
        // Handle "Voir détails" button click
        $(document).on('click', '.view-details-btn', function(e) {
            e.preventDefault();

            const resultId = $(this).data('result-id');
            const participant = $(this).data('participant');
            const quizTitle = $(this).data('quiz-title');

            // Show modal
            $('#result-details-modal').show();
            $('#result-details-content').html('<div class="loading">Chargement des détails...</div>');

            // Load result details via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'quiz_ai_pro_get_result_details',
                    result_id: resultId,
                    security: '<?php echo wp_create_nonce('quiz_ai_pro_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        displayResultDetails(response.data, participant, quizTitle);
                    } else {
                        $('#result-details-content').html('<div class="error">Erreur lors du chargement des détails: ' + response.data + '</div>');
                    }
                },
                error: function() {
                    $('#result-details-content').html('<div class="error">Erreur de connexion lors du chargement des détails.</div>');
                }
            });
        });

        // Close modal
        $(document).on('click', '.quiz-modal-close, .quiz-modal-overlay', function() {
            $('#result-details-modal').hide();
        });

        // Function to display result details
        function displayResultDetails(data, participant, quizTitle) {
            const html = `
            <div class="result-details-grid">
                <div class="result-detail-card">
                    <h4>Participant</h4>
                    <div class="value">${participant}</div>
                </div>
                <div class="result-detail-card">
                    <h4>Quiz</h4>
                    <div class="value">${quizTitle}</div>
                </div>
                <div class="result-detail-card">
                    <h4>Score Final</h4>
                    <div class="value">${data.percentage}% (${data.correct_answers}/${data.total_questions})</div>
                </div>
                <div class="result-detail-card">
                    <h4>Temps Passé</h4>
                    <div class="value">${data.time_taken_formatted || data.time_taken || '--'}</div>
                </div>
                <div class="result-detail-card">
                    <h4>Date de Soumission</h4>
                    <div class="value">${data.completed_at ? new Date(data.completed_at).toLocaleString('fr-FR') : '--'}</div>
                </div>
                <div class="result-detail-card">
                    <h4>Tentative</h4>
                    <div class="value">#${data.attempt_number}</div>
                </div>
            </div>
            
            <div class="questions-details">
                <h3>Détail des Réponses</h3>
                ${data.questions_details && data.questions_details.length > 0 ? data.questions_details.map((question, index) => `
                    <div class="question-result ${question.is_correct ? 'correct' : 'incorrect'}">
                        <div class="question-title">
                            Question ${index + 1}: ${question.question_text}
                        </div>
                        <div class="answer-given ${question.is_correct ? 'correct' : 'incorrect'}">
                            <strong>Réponse donnée:</strong> ${question.user_answer || 'Aucune réponse'}
                        </div>
                        ${question.correct_answer ? `
                            <div class="answer-given correct">
                                <strong>Réponse correcte:</strong> ${question.correct_answer}
                            </div>
                        ` : ''}
                        ${question.explanation ? `
                            <div style="margin-top: 10px; font-style: italic; color: #666;">
                                <strong>Explication:</strong> ${question.explanation}
                            </div>
                        ` : ''}
                    </div>
                `).join('') : '<p>Aucun détail de question disponible.</p>'}
            </div>
        `;

            $('#result-details-content').html(html);
        }
    });
</script>