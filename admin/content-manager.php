<?php

/**
 * Content Manager Page for Quiz IA Pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        quiz_ai_pro_handle_content_action();
    }
}

// Get current action
$action = $_GET['action'] ?? 'list';
$content_id = $_GET['id'] ?? 0;

?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        Gestionnaire de Contenu
        <?php if ($action === 'list'): ?>
            <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-content&action=add'); ?>" class="page-title-action">Ajouter du contenu</a>
        <?php endif; ?>
    </h1>

    <?php quiz_ai_pro_show_admin_notices(); ?>

    <div class="quiz-ai-pro-admin">
        <?php
        switch ($action) {
            case 'add':
            case 'edit':
                quiz_ai_pro_content_form($content_id);
                break;
            case 'view':
                quiz_ai_pro_content_view($content_id);
                break;
            default:
                quiz_ai_pro_content_list();
                break;
        }
        ?>
    </div>
</div>

<style>
    .quiz-ai-pro-admin {
        max-width: 1200px;
    }

    .content-form {
        background: white;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-top: 20px;
    }

    .form-table th {
        width: 150px;
        font-weight: 600;
    }

    .form-field {
        margin-bottom: 20px;
    }

    .form-field label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .form-field input,
    .form-field select,
    .form-field textarea {
        width: 100%;
        max-width: 500px;
    }

    .form-field textarea {
        height: 200px;
        resize: vertical;
    }

    .content-editor {
        height: 400px;
    }

    .form-actions {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }

    .button-primary {
        margin-right: 10px;
    }

    .content-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #ddd;
        text-align: center;
    }

    .stat-number {
        font-size: 2em;
        font-weight: bold;
        color: #2271b1;
        display: block;
    }

    .stat-label {
        color: #666;
        font-size: 0.9em;
    }

    .content-table {
        width: 100%;
        background: white;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .content-table th,
    .content-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    .content-table th {
        background: #f8f9fa;
        font-weight: 600;
    }

    .content-table tr:hover {
        background: #f8f9fa;
    }

    .content-actions a {
        margin-right: 10px;
        text-decoration: none;
    }

    .content-meta {
        font-size: 0.9em;
        color: #666;
    }

    .level-badge {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: 500;
    }

    .level-badge.debutant {
        background: #d4edda;
        color: #155724;
    }

    .level-badge.intermediaire {
        background: #fff3cd;
        color: #856404;
    }

    .level-badge.avance {
        background: #f8d7da;
        color: #721c24;
    }

    .search-box {
        float: right;
        margin-bottom: 20px;
    }

    .content-preview {
        background: white;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-top: 20px;
    }

    .content-header {
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 1px solid #ddd;
    }

    .content-title {
        margin: 0 0 10px 0;
        font-size: 1.5em;
    }

    .content-meta-info {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.9em;
        color: #666;
    }

    .content-body {
        line-height: 1.6;
    }

    .back-link {
        margin-bottom: 20px;
    }
</style>

<?php

/**
 * Display content list
 */
function quiz_ai_pro_content_list()
{
    global $wpdb;

    // Get search term
    $search = $_GET['s'] ?? '';

    // Get content statistics
    $stats = quiz_ai_pro_get_content_statistics();

    // Build query
    $where = "WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $where .= " AND (title LIKE %s OR description LIKE %s OR content LIKE %s)";
        $search_term = '%' . $search . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // Get contents
    $contents = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}quiz_ai_pro_contents 
         $where
         ORDER BY created_at DESC",
        ...$params
    ));

?>

    <!-- Statistics Cards -->
    <div class="content-stats">
        <div class="stat-card">
            <span class="stat-number"><?php echo $stats['total']; ?></span>
            <span class="stat-label">Total Contenus</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo $stats['published']; ?></span>
            <span class="stat-label">Publiés</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo $stats['with_quizzes']; ?></span>
            <span class="stat-label">Avec Quiz</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?php echo $stats['word_count']; ?></span>
            <span class="stat-label">Mots au Total</span>
        </div>
    </div>

    <!-- Search Box -->
    <div class="search-box">
        <form method="get">
            <input type="hidden" name="page" value="quiz-ai-pro-content">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Rechercher du contenu...">
            <button type="submit" class="button">Rechercher</button>
            <?php if (!empty($search)): ?>
                <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-content'); ?>" class="button">Effacer</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="clear: both;"></div>

    <!-- Content Table -->
    <table class="content-table">
        <thead>
            <tr>
                <th>Titre</th>
                <th>Type</th>
                <th>Niveau</th>
                <th>Quiz Associés</th>
                <th>Mots</th>
                <th>Créé le</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($contents)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px;">
                        <?php if (!empty($search)): ?>
                            Aucun contenu trouvé pour "<?php echo esc_html($search); ?>".
                        <?php else: ?>
                            Aucun contenu disponible. <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-content&action=add'); ?>">Créer le premier contenu</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($contents as $content): ?>
                    <?php
                    $quiz_count = quiz_ai_pro_get_content_quiz_count($content->id);
                    $word_count = str_word_count(wp_strip_all_tags($content->content));
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($content->title); ?></strong>
                            <?php if (!empty($content->description)): ?>
                                <div class="content-meta"><?php echo esc_html(wp_trim_words($content->description, 15)); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(ucfirst($content->type)); ?></td>
                        <td>
                            <span class="level-badge <?php echo esc_attr($content->level); ?>">
                                <?php echo esc_html(ucfirst($content->level)); ?>
                            </span>
                        </td>
                        <td><?php echo $quiz_count; ?></td>
                        <td><?php echo number_format($word_count); ?></td>
                        <td><?php echo date_i18n('d/m/Y H:i', strtotime($content->created_at)); ?></td>
                        <td class="content-actions">
                            <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-content&action=view&id=' . $content->id); ?>">Voir</a>
                            <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-content&action=edit&id=' . $content->id); ?>">Modifier</a>
                            <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-quiz-generator&content_id=' . $content->id); ?>">Générer Quiz</a>
                            <a href="#" onclick="deleteContent(<?php echo $content->id; ?>)" style="color: #dc3545;">Supprimer</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        function deleteContent(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce contenu ? Cette action est irréversible.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="content_id" value="${id}">
                <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('quiz_ai_pro_content'); ?>">
            `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

<?php
}

/**
 * Display content form (add/edit)
 */
function quiz_ai_pro_content_form($content_id = 0)
{
    global $wpdb;

    $content = null;
    $is_edit = false;

    if ($content_id) {
        $content = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}quiz_ai_pro_contents WHERE id = %d",
            $content_id
        ));
        $is_edit = true;
    }

?>

    <div class="back-link">
        <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-content'); ?>">&larr; Retour à la liste</a>
    </div>

    <div class="content-form">
        <h2><?php echo $is_edit ? 'Modifier le contenu' : 'Ajouter du contenu'; ?></h2>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?php echo $is_edit ? 'update' : 'create'; ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="content_id" value="<?php echo $content->id; ?>">
            <?php endif; ?>
            <?php wp_nonce_field('quiz_ai_pro_content'); ?>

            <div class="form-field">
                <label for="title">Titre *</label>
                <input type="text" id="title" name="title" required
                    value="<?php echo $is_edit ? esc_attr($content->title) : ''; ?>">
            </div>

            <div class="form-field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?php echo $is_edit ? esc_textarea($content->description) : ''; ?></textarea>
            </div>

            <div class="form-field">
                <label for="type">Type de contenu</label>
                <select id="type" name="type">
                    <option value="course" <?php echo ($is_edit && $content->type === 'course') ? 'selected' : ''; ?>>Cours</option>
                    <option value="lesson" <?php echo ($is_edit && $content->type === 'lesson') ? 'selected' : ''; ?>>Leçon</option>
                    <option value="article" <?php echo ($is_edit && $content->type === 'article') ? 'selected' : ''; ?>>Article</option>
                    <option value="tutorial" <?php echo ($is_edit && $content->type === 'tutorial') ? 'selected' : ''; ?>>Tutoriel</option>
                    <option value="documentation" <?php echo ($is_edit && $content->type === 'documentation') ? 'selected' : ''; ?>>Documentation</option>
                </select>
            </div>

            <div class="form-field">
                <label for="level">Niveau</label>
                <select id="level" name="level">
                    <option value="debutant" <?php echo ($is_edit && $content->level === 'debutant') ? 'selected' : ''; ?>>Débutant</option>
                    <option value="intermediaire" <?php echo ($is_edit && $content->level === 'intermediaire') ? 'selected' : ''; ?>>Intermédiaire</option>
                    <option value="avance" <?php echo ($is_edit && $content->level === 'avance') ? 'selected' : ''; ?>>Avancé</option>
                </select>
            </div>

            <div class="form-field">
                <label for="content">Contenu *</label>
                <?php
                wp_editor(
                    $is_edit ? $content->content : '',
                    'content',
                    [
                        'textarea_name' => 'content',
                        'textarea_rows' => 20,
                        'teeny' => false,
                        'media_buttons' => true,
                        'tinymce' => [
                            'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,blockquote,hr,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,fullscreen,wp_adv',
                            'toolbar2' => 'styleselect,fontselect,fontsizeselect,forecolor,backcolor,indent,outdent,undo,redo,wp_help'
                        ]
                    ]
                );
                ?>
            </div>

            <div class="form-field">
                <label for="tags">Tags (séparés par des virgules)</label>
                <input type="text" id="tags" name="tags"
                    value="<?php echo $is_edit ? esc_attr($content->tags) : ''; ?>"
                    placeholder="wordpress, développement, tutoriel">
            </div>

            <div class="form-field">
                <label for="source_url">URL Source (optionnel)</label>
                <input type="url" id="source_url" name="source_url"
                    value="<?php echo $is_edit ? esc_attr($content->source_url) : ''; ?>"
                    placeholder="https://exemple.com/article-source">
            </div>

            <div class="form-actions">
                <button type="submit" class="button-primary">
                    <?php echo $is_edit ? 'Mettre à jour' : 'Créer le contenu'; ?>
                </button>
                <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-content'); ?>" class="button">Annuler</a>

                <?php if ($is_edit): ?>
                    <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-quiz-generator&content_id=' . $content->id); ?>"
                        class="button button-secondary" style="margin-left: 20px;">
                        Générer un Quiz
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

<?php
}

/**
 * Display content view
 */
function quiz_ai_pro_content_view($content_id)
{
    global $wpdb;

    $content = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}quiz_ai_pro_contents WHERE id = %d",
        $content_id
    ));

    if (!$content) {
        echo '<div class="notice notice-error"><p>Contenu introuvable.</p></div>';
        return;
    }

    $quiz_count = quiz_ai_pro_get_content_quiz_count($content->id);
    $word_count = str_word_count(wp_strip_all_tags($content->content));

?>

    <div class="back-link">
        <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-content'); ?>">&larr; Retour à la liste</a>
    </div>

    <div class="content-preview">
        <div class="content-header">
            <h2 class="content-title"><?php echo esc_html($content->title); ?></h2>

            <div class="content-meta-info">
                <div class="meta-item">
                    <strong>Type:</strong> <?php echo esc_html(ucfirst($content->type)); ?>
                </div>
                <div class="meta-item">
                    <strong>Niveau:</strong>
                    <span class="level-badge <?php echo esc_attr($content->level); ?>">
                        <?php echo esc_html(ucfirst($content->level)); ?>
                    </span>
                </div>
                <div class="meta-item">
                    <strong>Quiz associés:</strong> <?php echo $quiz_count; ?>
                </div>
                <div class="meta-item">
                    <strong>Nombre de mots:</strong> <?php echo number_format($word_count); ?>
                </div>
                <div class="meta-item">
                    <strong>Créé le:</strong> <?php echo date_i18n('d/m/Y à H:i', strtotime($content->created_at)); ?>
                </div>
            </div>

            <?php if (!empty($content->description)): ?>
                <div class="content-description">
                    <strong>Description:</strong><br>
                    <?php echo esc_html($content->description); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($content->tags)): ?>
                <div class="content-tags">
                    <strong>Tags:</strong> <?php echo esc_html($content->tags); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($content->source_url)): ?>
                <div class="content-source">
                    <strong>Source:</strong> <a href="<?php echo esc_url($content->source_url); ?>" target="_blank"><?php echo esc_url($content->source_url); ?></a>
                </div>
            <?php endif; ?>
        </div>

        <div class="content-body">
            <?php echo wp_kses_post($content->content); ?>
        </div>

        <div class="form-actions">
            <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-content&action=edit&id=' . $content->id); ?>"
                class="button-primary">Modifier</a>
            <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-quiz-generator&content_id=' . $content->id); ?>"
                class="button">Générer un Quiz</a>
        </div>
    </div>

<?php
}

/**
 * Handle content actions
 */
function quiz_ai_pro_handle_content_action()
{
    // Verify nonce
    if (!wp_verify_nonce($_POST['_wpnonce'], 'quiz_ai_pro_content')) {
        wp_die('Erreur de sécurité');
    }

    $action = $_POST['action'];

    switch ($action) {
        case 'create':
            quiz_ai_pro_create_content();
            break;
        case 'update':
            quiz_ai_pro_update_content();
            break;
        case 'delete':
            quiz_ai_pro_delete_content();
            break;
    }
}

/**
 * Create new content
 */
function quiz_ai_pro_create_content()
{
    global $wpdb;

    $data = [
        'title' => sanitize_text_field($_POST['title']),
        'description' => sanitize_textarea_field($_POST['description']),
        'content' => wp_kses_post($_POST['content']),
        'type' => sanitize_text_field($_POST['type']),
        'level' => sanitize_text_field($_POST['level']),
        'tags' => sanitize_text_field($_POST['tags']),
        'source_url' => esc_url_raw($_POST['source_url']),
        'slug' => quiz_ai_pro_generate_slug($_POST['title'], 'quiz_ai_pro_contents'),
        'created_at' => current_time('mysql')
    ];

    $result = $wpdb->insert(
        $wpdb->prefix . 'quiz_ai_pro_contents',
        $data,
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );

    if ($result) {
        quiz_ai_pro_add_admin_notice('Contenu créé avec succès.', 'success');
        wp_redirect(admin_url('admin.php?page=quiz-ai-pro-content&action=edit&id=' . $wpdb->insert_id));
    } else {
        quiz_ai_pro_add_admin_notice('Erreur lors de la création du contenu.', 'error');
    }
}

/**
 * Update existing content
 */
function quiz_ai_pro_update_content()
{
    global $wpdb;

    $content_id = intval($_POST['content_id']);

    $data = [
        'title' => sanitize_text_field($_POST['title']),
        'description' => sanitize_textarea_field($_POST['description']),
        'content' => wp_kses_post($_POST['content']),
        'type' => sanitize_text_field($_POST['type']),
        'level' => sanitize_text_field($_POST['level']),
        'tags' => sanitize_text_field($_POST['tags']),
        'source_url' => esc_url_raw($_POST['source_url']),
        'updated_at' => current_time('mysql')
    ];

    $result = $wpdb->update(
        $wpdb->prefix . 'quiz_ai_pro_contents',
        $data,
        ['id' => $content_id],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );

    if ($result !== false) {
        quiz_ai_pro_add_admin_notice('Contenu mis à jour avec succès.', 'success');
    } else {
        quiz_ai_pro_add_admin_notice('Erreur lors de la mise à jour du contenu.', 'error');
    }
}

/**
 * Delete content
 */
function quiz_ai_pro_delete_content()
{
    global $wpdb;

    $content_id = intval($_POST['content_id']);

    // Check if content has associated quizzes
    $quiz_count = quiz_ai_pro_get_content_quiz_count($content_id);

    if ($quiz_count > 0) {
        quiz_ai_pro_add_admin_notice("Impossible de supprimer ce contenu car il a $quiz_count quiz associé(s).", 'error');
        return;
    }

    $result = $wpdb->delete(
        $wpdb->prefix . 'quiz_ai_pro_contents',
        ['id' => $content_id],
        ['%d']
    );

    if ($result) {
        quiz_ai_pro_add_admin_notice('Contenu supprimé avec succès.', 'success');
        wp_redirect(admin_url('admin.php?page=quiz-ai-pro-content'));
    } else {
        quiz_ai_pro_add_admin_notice('Erreur lors de la suppression du contenu.', 'error');
    }
}
