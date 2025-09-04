<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue necessary scripts for this page
wp_enqueue_script('jquery');

// Get quiz ID from URL parameter
$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

if (!$quiz_id) {
    wp_die('Quiz ID is required');
}

// Fetch quiz data from database
global $wpdb;
$quiz_table = $wpdb->prefix . 'quiz_ia_quizzes';
$questions_table = $wpdb->prefix . 'quiz_ia_questions';
$answers_table = $wpdb->prefix . 'quiz_ia_answers';

$quiz = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $quiz_table WHERE id = %d",
    $quiz_id
));

if (!$quiz) {
    wp_die('Quiz not found');
}

// Fetch quiz questions with their answers
$questions = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $questions_table WHERE quiz_id = %d ORDER BY sort_order ASC",
    $quiz_id
));

// Fetch answers for all questions
$questions_with_answers = [];
foreach ($questions as $question) {
    $answers = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $answers_table WHERE question_id = %d ORDER BY sort_order ASC",
        $question->id
    ));

    $question->answers = $answers;
    $questions_with_answers[] = $question;
}

// Parse course and category IDs
$course_ids = $quiz->course_id ? json_decode($quiz->course_id, true) : [];
$category_ids = $quiz->category_id ? json_decode($quiz->category_id, true) : [];

// Parse settings JSON
$settings = $quiz->settings ? json_decode($quiz->settings, true) : [];

// Get courses and categories for display
$courses = [];
$categories = [];

if (!empty($course_ids)) {
    $course_ids_placeholder = implode(',', array_fill(0, count($course_ids), '%d'));
    $courses = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($course_ids_placeholder) AND post_type = 'lp_course'",
        ...$course_ids
    ));
}

if (!empty($category_ids)) {
    $category_ids_placeholder = implode(',', array_fill(0, count($category_ids), '%d'));
    $categories = $wpdb->get_results($wpdb->prepare(
        "SELECT term_id, name FROM {$wpdb->terms} WHERE term_id IN ($category_ids_placeholder)",
        ...$category_ids
    ));
}
?>

<div class="wrap quiz-ia-pro-admin">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-edit"></span>
        Modifier le Quiz: <?php echo esc_html($quiz->title); ?>
    </h1>

    <div class="notice notice-info" style="margin: 20px 0; padding: 12px; background: #e7f5ff; border-left: 4px solid #0073aa;">
        <p style="margin: 0; font-size: 14px;">
            <strong>💡 Mode édition en ligne :</strong>
            Survolez et cliquez sur n'importe quel élément (titre, question, options, réponses, explications, images) pour les modifier directement.
            Utilisez le bouton "Sauvegarder Tout" en bas pour enregistrer tous vos changements.
        </p>
    </div>

    <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-list'); ?>" class="page-title-action">
        ← Retour à la liste
    </a>

    <hr class="wp-header-end">

    <div class="quiz-editor-container">

        <!-- Quiz Status Card -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">Statut du Quiz</h2>
            </div>
            <div class="inside">
                <div class="quiz-status-info">
                    <div class="status-item">
                        <span class="label">Statut:</span>
                        <span class="status-badge status-<?php echo esc_attr($quiz->status); ?>">
                            <?php if ($quiz->status === 'published'): ?>
                                ✅ Publié
                            <?php else: ?>
                                🟡 Brouillon
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="label">Code du Quiz:</span>
                        <span class="quiz-code"><?php echo esc_html($quiz->quiz_code); ?></span>
                        <button type="button" class="button-link copy-code" title="Copier le code">
                            <span class="dashicons dashicons-admin-page"></span>
                        </button>
                    </div>
                    <div class="status-item">
                        <span class="label">Créé le:</span>
                        <span><?php echo date('d/m/Y à H:i', strtotime($quiz->created_at)); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="label">Questions:</span>
                        <span><?php echo count($questions_with_answers); ?> question(s)</span>
                    </div>
                </div>

                <div class="quiz-actions">
                    <?php if ($quiz->status === 'draft'): ?>
                        <button type="button" class="button button-primary quiz-action" data-action="publish" data-quiz-id="<?php echo $quiz->id; ?>">
                            <span class="dashicons dashicons-yes"></span> Publier le Quiz
                        </button>
                    <?php else: ?>
                        <button type="button" class="button quiz-action" data-action="unpublish" data-quiz-id="<?php echo $quiz->id; ?>">
                            <span class="dashicons dashicons-hidden"></span> Dépublier le Quiz
                        </button>
                    <?php endif; ?>


                    <button type="button" class="button button-primary button-large save-all-changes" id="save-all-changes">
                        <span class="dashicons dashicons-yes"></span> Enregistrer toutes les modifications
                    </button>
                </div>
            </div>
        </div>

        <!-- Quiz Information -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">Informations du Quiz</h2>
            </div>
            <div class="inside">
                <form id="quiz-info-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="quiz_title">Titre du Quiz</label></th>
                            <td>
                                <input type="text" id="quiz_title" name="quiz_title" value="<?php echo esc_attr($quiz->title); ?>" class="regular-text" data-original-value="<?php echo esc_attr($quiz->title); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="quiz_description">Description</label></th>
                            <td>
                                <textarea id="quiz_description" name="quiz_description" rows="3" class="large-text"><?php echo esc_textarea($quiz->description); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Image du Quiz</th>
                            <td>
                                <div class="quiz-image-section">
                                    <?php if (!empty($quiz->featured_image)): ?>
                                        <div class="quiz-image-preview">
                                            <img src="<?php echo esc_url($quiz->featured_image); ?>" alt="Quiz Image" class="quiz-image" style="max-width: 300px; height: auto; border-radius: 6px; border: 1px solid #ddd;">
                                            <div class="quiz-image-actions" style="margin-top: 10px;">
                                                <button type="button" class="button change-quiz-image-btn">
                                                    <span class="dashicons dashicons-edit"></span> Changer l'image
                                                </button>
                                                <button type="button" class="button button-link-delete remove-quiz-image-btn" style="color: #d63638;">
                                                    <span class="dashicons dashicons-trash"></span> Supprimer l'image
                                                </button>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="add-quiz-image-section">
                                            <button type="button" class="button add-quiz-image-btn">
                                                <span class="dashicons dashicons-format-image"></span> Ajouter une image au quiz
                                            </button>
                                            <p class="description">Image recommandée : 800x400 pixels (ratio 2:1)</p>
                                        </div>
                                    <?php endif; ?>
                                    <input type="hidden" id="quiz_featured_image" name="quiz_featured_image" value="<?php echo esc_attr($quiz->featured_image ?? ''); ?>">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Cours Associés</th>
                            <td>
                                <?php if (!empty($courses)): ?>
                                    <ul class="course-list">
                                        <?php foreach ($courses as $course): ?>
                                            <li><span class="dashicons dashicons-book"></span> <?php echo esc_html($course->post_title); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <em>Aucun cours associé</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Catégories Associées</th>
                            <td>
                                <div class="quiz-categories-section">
                                    <?php if (!empty($categories)): ?>
                                        <ul class="category-list" id="quiz-categories-list">
                                            <?php foreach ($categories as $category): ?>
                                                <li data-category-id="<?php echo $category->term_id; ?>">
                                                    <span class="dashicons dashicons-category"></span>
                                                    <?php echo esc_html($category->name); ?>
                                                    <button type="button" class="button-link remove-category-btn" data-category-id="<?php echo $category->term_id; ?>" style="color: #d63638; margin-left: 10px;">
                                                        <span class="dashicons dashicons-no-alt"></span>
                                                    </button>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <ul class="category-list" id="quiz-categories-list">
                                            <li class="no-categories"><em>Aucune catégorie associée</em></li>
                                        </ul>
                                    <?php endif; ?>
                                    <div class="add-category-section" style="margin-top: 15px;">
                                        <button type="button" class="button" id="add-category-btn">
                                            <span class="dashicons dashicons-plus-alt"></span> Ajouter une catégorie
                                        </button>
                                        <select id="available-categories" style="display: none; margin-left: 10px; min-width: 200px;">
                                            <option value="">Sélectionner une catégorie...</option>
                                        </select>
                                        <button type="button" class="button button-primary" id="confirm-add-category" style="display: none; margin-left: 5px;">Ajouter</button>
                                        <button type="button" class="button" id="cancel-add-category" style="display: none; margin-left: 5px;">Annuler</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Limite de Temps</th>
                            <td>
                                <div class="time-limit-section">
                                    <input type="number" id="quiz_time_limit" name="quiz_time_limit" value="<?php echo esc_attr($quiz->time_limit ?? 0); ?>" min="0" max="999" style="width: 80px;" />
                                    <span style="margin-left: 8px;">minutes</span>
                                    <p class="description">0 = Pas de limite de temps</p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Questions par Page</th>
                            <td>
                                <div class="questions-per-page-section">
                                    <input type="number" id="quiz_questions_per_page" name="quiz_questions_per_page" value="<?php echo esc_attr($quiz->questions_per_page ?? 1); ?>" min="1" max="50" style="width: 80px;" />
                                    <span style="margin-left: 8px;">questions</span>
                                    <p class="description">Nombre de questions affichées simultanément</p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Type de Quiz</th>
                            <td>
                                <span class="quiz-info-value"><?php echo esc_html($quiz->quiz_type); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Système de Notation</th>
                            <td>
                                <span class="quiz-info-value"><?php echo esc_html($quiz->grading_system); ?></span>
                            </td>
                        </tr>
                    </table>

                    <!-- Advanced Options Section -->
                    <h3 class="advanced-options-header" style="margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid #ccd0d4; padding-bottom: 10px;">
                        <span class="dashicons dashicons-admin-generic"></span> Options Avancées
                    </h3>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="show_contact_form">Formulaire de Contact</label>
                            </th>
                            <td>
                                <label class="checkbox-wrapper">
                                    <input type="checkbox" id="show_contact_form" name="show_contact_form" value="1"
                                        <?php checked(isset($settings['show_contact_form']) && $settings['show_contact_form'] === true); ?> />
                                    <span class="checkmark"></span>
                                    Afficher un formulaire de contact avant le quiz
                                </label>
                                <p class="description">Les utilisateurs devront remplir leurs informations avant de commencer</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="show_page_number">Numéro de Page</label>
                            </th>
                            <td>
                                <label class="checkbox-wrapper">
                                    <input type="checkbox" id="show_page_number" name="show_page_number" value="1"
                                        <?php checked(isset($settings['show_page_number']) && $settings['show_page_number'] === true); ?> />
                                    <span class="checkmark"></span>
                                    Afficher le numéro de page actuel
                                </label>
                                <p class="description">Permet aux utilisateurs de voir leur progression page par page</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="show_question_images">Images de Questions</label>
                            </th>
                            <td>
                                <label class="checkbox-wrapper">
                                    <input type="checkbox" id="show_question_images" name="show_question_images" value="1"
                                        <?php checked(isset($settings['show_question_images']) && $settings['show_question_images'] === true); ?> />
                                    <span class="checkmark"></span>
                                    Afficher les images des questions sur la page de résultats
                                </label>
                                <p class="description">Les images des questions seront visibles dans le récapitulatif final</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="show_progress_bar">Barre de Progression</label>
                            </th>
                            <td>
                                <label class="checkbox-wrapper">
                                    <input type="checkbox" id="show_progress_bar" name="show_progress_bar" value="1"
                                        <?php checked(isset($settings['show_progress_bar']) && $settings['show_progress_bar'] === true); ?> />
                                    <span class="checkmark"></span>
                                    Afficher la barre de progression
                                </label>
                                <p class="description">Une barre de progression sera affichée en haut du quiz</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="require_login">Connexion Requise</label>
                            </th>
                            <td>
                                <label class="checkbox-wrapper">
                                    <input type="checkbox" id="require_login" name="require_login" value="1"
                                        <?php checked(isset($settings['require_login']) && $settings['require_login'] === true); ?> />
                                    <span class="checkmark"></span>
                                    Nécessiter une connexion utilisateur
                                </label>
                                <p class="description">Seuls les utilisateurs connectés pourront accéder au quiz</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="disable_first_page">Première Page</label>
                            </th>
                            <td>
                                <label class="checkbox-wrapper">
                                    <input type="checkbox" id="disable_first_page" name="disable_first_page" value="1"
                                        <?php checked(isset($settings['disable_first_page']) && $settings['disable_first_page'] === true); ?> />
                                    <span class="checkmark"></span>
                                    Désactiver la première page du quiz
                                </label>
                                <p class="description">Le quiz commencera directement par la première question</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="enable_comments">Boîte de Commentaires</label>
                            </th>
                            <td>
                                <label class="checkbox-wrapper">
                                    <input type="checkbox" id="enable_comments" name="enable_comments" value="1"
                                        <?php checked(isset($settings['enable_comments']) && $settings['enable_comments'] === true); ?> />
                                    <span class="checkmark"></span>
                                    Activer la boîte de commentaires
                                </label>
                                <p class="description">Les utilisateurs pourront laisser des commentaires à la fin du quiz</p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="button" id="save-quiz-info" class="button button-primary">
                            <span class="dashicons dashicons-yes"></span> Sauvegarder les Informations
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <!-- Quiz Questions -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">Questions du Quiz</h2>
                <div class="handle-actions">
                    <button type="button" class="button button-primary">
                        <span class="dashicons dashicons-plus"></span> Ajouter une Question
                    </button>
                </div>
            </div>
            <div class="inside">
                <?php if (empty($questions_with_answers)): ?>
                    <div class="no-questions">
                        <p><em>Aucune question trouvée pour ce quiz.</em></p>
                        <button type="button" class="button button-primary add-question-btn">
                            <span class="dashicons dashicons-plus"></span> Ajouter votre première question
                        </button>
                    </div>
                <?php else: ?>
                    <div class="questions-container">
                        <?php foreach ($questions_with_answers as $index => $question): ?>
                            <div class="question-item user-view-style" data-question-id="<?php echo $question->id; ?>">
                                <!-- Question Header with Admin Controls -->
                                <div class="question-admin-header">
                                    <div class="question-meta">
                                        <span class="question-number">Question <?php echo $index + 1; ?></span>
                                        <div class="question-type-selector">
                                            <select class="question-type-dropdown" data-question-id="<?php echo $question->id; ?>" data-original-type="<?php echo esc_attr($question->question_type); ?>">
                                                <option value="qcm" <?php selected($question->question_type, 'qcm'); ?>>QCM</option>
                                                <option value="multiple-choice" <?php selected($question->question_type, 'multiple-choice'); ?>>Choix Multiple</option>
                                                <option value="single-choice" <?php selected($question->question_type, 'single-choice'); ?>>Choix Unique</option>
                                                <option value="true-false" <?php selected($question->question_type, 'true-false'); ?>>Vrai/Faux</option>
                                                <option value="fill_blank" <?php selected($question->question_type, 'fill_blank'); ?>>Texte à Compléter</option>
                                                <option value="text" <?php selected($question->question_type, 'text'); ?>>Texte Libre</option>
                                                <option value="essay" <?php selected($question->question_type, 'essay'); ?>>Essai</option>
                                            </select>
                                        </div>
                                        <?php if ($question->points): ?>
                                            <span class="points-badge"><?php echo intval($question->points); ?> pts</span>
                                        <?php endif; ?>
                                        <?php if (isset($question->time_limit) && $question->time_limit): ?>
                                            <span class="time-badge"><?php echo intval($question->time_limit); ?>s</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="question-actions">
                                        <button type="button" class="button-link edit-question-btn" title="Modifier cette question">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                        <button type="button" class="button-link duplicate-question-btn" title="Dupliquer cette question">
                                            <span class="dashicons dashicons-admin-page"></span>
                                        </button>
                                        <button type="button" class="button-link move-up-btn" title="Déplacer vers le haut" <?php echo $index === 0 ? 'disabled' : ''; ?>>
                                            <span class="dashicons dashicons-arrow-up-alt2"></span>
                                        </button>
                                        <button type="button" class="button-link move-down-btn" title="Déplacer vers le bas" <?php echo $index === count($questions_with_answers) - 1 ? 'disabled' : ''; ?>>
                                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                                        </button>
                                        <button type="button" class="button-link delete-question-btn" title="Supprimer cette question">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Question Content as User Sees It -->
                                <div class="question-user-view">
                                    <div class="question-header-user">
                                        <h3 class="question-title editable-text" data-field="question_text">
                                            <?php echo wp_kses_post($question->question_text); ?>
                                        </h3>
                                        <?php if (!empty($question->question_description)): ?>
                                            <p class="question-description editable-text" data-field="question_description">
                                                <?php echo wp_kses_post($question->question_description); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Question Image if exists -->
                                    <div class="question-image-section">
                                        <?php if (!empty($question->featured_image)): ?>
                                            <div class="question-image">
                                                <img src="<?php echo esc_url($question->featured_image); ?>" alt="Question Image" class="question-img">
                                                <div class="image-actions">
                                                    <button type="button" class="button-link change-image-btn" title="Changer l'image">
                                                        <span class="dashicons dashicons-edit"></span>
                                                    </button>
                                                    <button type="button" class="button-link remove-image-btn" title="Supprimer l'image">
                                                        <span class="dashicons dashicons-trash"></span>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="add-image-section">
                                                <button type="button" class="button add-image-btn">
                                                    <span class="dashicons dashicons-format-image"></span> Ajouter une image
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                        <input type="hidden" class="question-featured-image" value="<?php echo esc_attr($question->featured_image ?? ''); ?>">
                                    </div>

                                    <!-- Answers Section -->
                                    <div class="answers-section">
                                        <?php if ($question->question_type === 'multiple-choice' || $question->question_type === 'single-choice' || $question->question_type === 'qcm'): ?>
                                            <div class="answers-list multiple-choice">
                                                <?php
                                                $answer_letters = ['A', 'B', 'C', 'D', 'E', 'F'];
                                                foreach ($question->answers as $answer_index => $answer): ?>
                                                    <div class="answer-option <?php echo $answer->is_correct ? 'correct-answer' : ''; ?>" data-answer-id="<?php echo $answer->id; ?>">
                                                        <label class="answer-label">
                                                            <input type="<?php echo ($question->question_type === 'multiple-choice' || $question->question_type === 'qcm') ? 'checkbox' : 'radio'; ?>"
                                                                name="question_<?php echo $question->id; ?>"
                                                                value="<?php echo $answer->id; ?>"
                                                                <?php echo $answer->is_correct ? 'checked' : ''; ?>
                                                                disabled>
                                                            <span class="answer-letter"><?php echo $answer_letters[$answer_index]; ?>.</span>
                                                            <span class="answer-text editable-text" data-field="answer_text">
                                                                <?php echo esc_html($answer->answer_text); ?>
                                                            </span>
                                                            <?php if ($answer->is_correct): ?>
                                                                <span class="correct-indicator" title="Réponse correcte">
                                                                    <span class="dashicons dashicons-yes-alt"></span>
                                                                </span>
                                                            <?php endif; ?>
                                                        </label>
                                                        <div class="answer-actions">
                                                            <button type="button" class="button-link toggle-correct-btn" title="Marquer comme <?php echo $answer->is_correct ? 'incorrecte' : 'correcte'; ?>">
                                                                <span class="dashicons dashicons-<?php echo $answer->is_correct ? 'dismiss' : 'yes'; ?>"></span>
                                                            </button>
                                                            <button type="button" class="button-link edit-answer-btn" title="Modifier cette réponse">
                                                                <span class="dashicons dashicons-edit"></span>
                                                            </button>
                                                            <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                                                                <span class="dashicons dashicons-trash"></span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>

                                                <div class="add-answer-section">
                                                    <button type="button" class="button add-answer-btn">
                                                        <span class="dashicons dashicons-plus"></span> Ajouter une réponse
                                                    </button>
                                                </div>
                                            </div>

                                        <?php elseif ($question->question_type === 'true-false'): ?>
                                            <div class="answers-list true-false">
                                                <?php foreach ($question->answers as $answer): ?>
                                                    <div class="answer-option <?php echo $answer->is_correct ? 'correct-answer' : ''; ?>" data-answer-id="<?php echo $answer->id; ?>">
                                                        <label class="answer-label">
                                                            <input type="radio"
                                                                name="question_<?php echo $question->id; ?>"
                                                                value="<?php echo $answer->id; ?>"
                                                                <?php echo $answer->is_correct ? 'checked' : ''; ?>
                                                                disabled>
                                                            <span class="answer-text"><?php echo esc_html($answer->answer_text); ?></span>
                                                            <?php if ($answer->is_correct): ?>
                                                                <span class="correct-indicator" title="Réponse correcte">
                                                                    <span class="dashicons dashicons-yes-alt"></span>
                                                                </span>
                                                            <?php endif; ?>
                                                        </label>
                                                        <button type="button" class="button-link toggle-correct-btn" title="Marquer comme <?php echo $answer->is_correct ? 'incorrecte' : 'correcte'; ?>">
                                                            <span class="dashicons dashicons-<?php echo $answer->is_correct ? 'dismiss' : 'yes'; ?>"></span>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                        <?php elseif ($question->question_type === 'text' || $question->question_type === 'essay'): ?>
                                            <div class="text-answer-section">
                                                <textarea placeholder="Réponse de l'utilisateur..." disabled class="user-answer-preview"></textarea>
                                                <div class="correct-answer-section">
                                                    <label><strong>Réponse(s) acceptée(s):</strong></label>
                                                    <?php foreach ($question->answers as $answer): ?>
                                                        <div class="text-answer-item" data-answer-id="<?php echo $answer->id; ?>">
                                                            <input type="text" value="<?php echo esc_attr($answer->answer_text); ?>" class="editable-text" data-field="answer_text">
                                                            <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                                                                <span class="dashicons dashicons-trash"></span>
                                                            </button>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <button type="button" class="button add-text-answer-btn">
                                                        <span class="dashicons dashicons-plus"></span> Ajouter une réponse acceptée
                                                    </button>
                                                </div>
                                            </div>

                                        <?php elseif ($question->question_type === 'fill_blank' || $question->question_type === 'text_a_completer'): ?>
                                            <div class="fill-blank-section">
                                                <div class="question-preview">
                                                    <label><strong>Aperçu avec espaces à compléter:</strong></label>
                                                    <div class="fill-blank-preview">
                                                        <?php
                                                        // Parse the question text to show blanks
                                                        $preview_text = $question->question_text;
                                                        $preview_text = preg_replace('/\{([^}]+)\}/', '<input type="text" class="blank-input" placeholder="..." disabled>', $preview_text);
                                                        echo $preview_text;
                                                        ?>
                                                    </div>
                                                </div>
                                                <div class="fill-blank-answers">
                                                    <label><strong>Réponses pour les espaces à compléter:</strong></label>
                                                    <small>Format: utilisez {réponse} dans le texte de la question pour créer des espaces à compléter</small>
                                                    <?php foreach ($question->answers as $answer_index => $answer): ?>
                                                        <div class="fill-blank-answer-item" data-answer-id="<?php echo $answer->id; ?>">
                                                            <span class="blank-number">Espace <?php echo ($answer_index + 1); ?>:</span>
                                                            <input type="text" value="<?php echo esc_attr($answer->answer_text); ?>" class="editable-text" data-field="answer_text" placeholder="Réponse attendue">
                                                            <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                                                                <span class="dashicons dashicons-trash"></span>
                                                            </button>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <button type="button" class="button add-fill-blank-answer-btn">
                                                        <span class="dashicons dashicons-plus"></span> Ajouter une réponse
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Question Explanation -->
                                    <?php if (!empty($question->explanation)): ?>
                                        <div class="question-explanation-section">
                                            <div class="explanation-header">
                                                <strong>💡 Explication:</strong>
                                                <button type="button" class="button-link edit-explanation-btn" title="Modifier l'explication">
                                                    <span class="dashicons dashicons-edit"></span>
                                                </button>
                                            </div>
                                            <div class="explanation-content editable-text" data-field="explanation">
                                                <?php echo wp_kses_post($question->explanation); ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="add-explanation-section">
                                            <button type="button" class="button add-explanation-btn">
                                                <span class="dashicons dashicons-plus"></span> Ajouter une explication
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Add New Question Button -->
                        <div class="add-question-section">
                            <button type="button" class="button button-primary button-large add-question-btn">
                                <span class="dashicons dashicons-plus"></span> Ajouter une nouvelle question
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Save All Changes - Bottom Button -->
                <div class="save-all-section">
                    <hr style="margin: 30px 0;">
                    <div style="text-align: center;">
                        <button type="button" class="button button-primary button-hero save-all-changes">
                            <span class="dashicons dashicons-yes"></span> Enregistrer toutes les modifications
                        </button>
                        <p class="description" style="margin-top: 10px;">
                            <em>Sauvegarde toutes les modifications apportées au quiz et aux questions</em>
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Question Type Selection Modal -->
<div id="question-type-modal" class="quiz-modal" style="display: none;">
    <div class="quiz-modal-content">
        <div class="quiz-modal-header">
            <h2>Choisir le type de question</h2>
            <span class="quiz-modal-close">&times;</span>
        </div>
        <div class="quiz-modal-body">
            <p>Sélectionnez le type de question que vous souhaitez ajouter :</p>
            <div class="question-type-grid">
                <div class="question-type-option" data-type="qcm">
                    <div class="question-type-icon">
                        <span class="dashicons dashicons-yes"></span>
                    </div>
                    <h3>QCM</h3>
                    <p>Question à choix multiples avec une seule bonne réponse</p>
                </div>
                <div class="question-type-option" data-type="multiple-choice">
                    <div class="question-type-icon">
                        <span class="dashicons dashicons-forms"></span>
                    </div>
                    <h3>Choix Multiple</h3>
                    <p>Question avec plusieurs bonnes réponses possibles</p>
                </div>
                <div class="question-type-option" data-type="true-false">
                    <div class="question-type-icon">
                        <span class="dashicons dashicons-editor-help"></span>
                    </div>
                    <h3>Vrai/Faux</h3>
                    <p>Question simple avec deux options : Vrai ou Faux</p>
                </div>
                <div class="question-type-option" data-type="fill_blank">
                    <div class="question-type-icon">
                        <span class="dashicons dashicons-edit"></span>
                    </div>
                    <h3>Texte à Compléter</h3>
                    <p>Question avec des espaces à remplir dans le texte</p>
                </div>
                <div class="question-type-option" data-type="text">
                    <div class="question-type-icon">
                        <span class="dashicons dashicons-text"></span>
                    </div>
                    <h3>Texte Libre</h3>
                    <p>Question ouverte avec réponse en texte libre</p>
                </div>
                <div class="question-type-option" data-type="essay">
                    <div class="question-type-icon">
                        <span class="dashicons dashicons-text-page"></span>
                    </div>
                    <h3>Essai</h3>
                    <p>Question longue nécessitant une réponse développée</p>
                </div>
            </div>
        </div>
        <div class="quiz-modal-footer">
            <button type="button" class="button button-secondary quiz-modal-cancel">Annuler</button>
        </div>
    </div>
</div>

<style>
    .quiz-editor-container {
        max-width: 1200px;
    }

    /* Question Type Modal Styles */
    .quiz-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 100000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .quiz-modal-content {
        background: white;
        border-radius: 8px;
        max-width: 800px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .quiz-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 30px;
        border-bottom: 1px solid #ddd;
        background: #f9f9f9;
        border-radius: 8px 8px 0 0;
    }

    .quiz-modal-header h2 {
        margin: 0;
        color: #333;
        font-size: 24px;
    }

    .quiz-modal-close {
        font-size: 28px;
        font-weight: bold;
        color: #aaa;
        cursor: pointer;
        line-height: 1;
        transition: color 0.2s;
    }

    .quiz-modal-close:hover {
        color: #333;
    }

    .quiz-modal-body {
        padding: 30px;
    }

    .quiz-modal-body p {
        margin-bottom: 20px;
        font-size: 16px;
        color: #666;
    }

    .question-type-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .question-type-option {
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: white;
    }

    .question-type-option:hover {
        border-color: #0073aa;
        box-shadow: 0 4px 12px rgba(0, 115, 170, 0.1);
        transform: translateY(-2px);
    }

    .question-type-option.selected {
        border-color: #0073aa;
        background: #f0f8ff;
        box-shadow: 0 4px 12px rgba(0, 115, 170, 0.2);
    }

    .question-type-icon {
        font-size: 32px;
        color: #0073aa;
        margin-bottom: 10px;
    }

    .question-type-option h3 {
        margin: 10px 0 8px 0;
        font-size: 18px;
        color: #333;
    }

    .question-type-option p {
        margin: 0;
        font-size: 14px;
        color: #666;
        line-height: 1.4;
    }

    .quiz-modal-footer {
        padding: 20px 30px;
        border-top: 1px solid #ddd;
        text-align: right;
        background: #f9f9f9;
        border-radius: 0 0 8px 8px;
    }

    .quiz-modal-footer .button {
        margin-left: 10px;
    }

    /* Text Answer Info Styles */
    .text-answer-info {
        padding: 20px;
        background: #f8f9fa;
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        text-align: center;
        margin: 10px 0;
    }

    .text-answer-info p {
        margin: 0;
        color: #6c757d;
        font-style: italic;
    }

    .quiz-status-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .status-item {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .status-item .label {
        font-weight: 600;
        min-width: 100px;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-badge.status-published {
        background-color: #d4edda;
        color: #155724;
    }

    .status-badge.status-draft {
        background-color: #fff3cd;
        color: #856404;
    }

    .quiz-actions {
        border-top: 1px solid #ddd;
        padding-top: 15px;
        display: flex;
        gap: 10px;
    }

    .course-list,
    .category-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .course-list li,
    .category-list li {
        padding: 5px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .quiz-info-value {
        background-color: #f1f1f1;
        padding: 4px 8px;
        border-radius: 3px;
        font-family: monospace;
    }

    /* Question Styling - User View */
    .questions-container {
        margin-bottom: 20px;
    }

    .question-item.user-view-style {
        border: 1px solid #ddd;
        border-radius: 8px;
        margin-bottom: 20px;
        background: #ffffff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .question-admin-header {
        background: #f8f9fa;
        padding: 12px 20px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .question-meta {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .question-number {
        font-weight: 600;
        color: #2271b1;
        font-size: 14px;
    }

    .question-type-selector {
        position: relative;
        margin: 0 10px;
    }

    .question-type-dropdown {
        padding: 4px 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: white;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: #2271b1;
        cursor: pointer;
        transition: all 0.2s;
    }

    .question-type-dropdown:hover {
        border-color: #2271b1;
        box-shadow: 0 0 0 1px rgba(34, 113, 177, 0.2);
    }

    .question-type-dropdown:focus {
        outline: none;
        border-color: #2271b1;
        box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.2);
    }

    .question-type-badge,
    .points-badge,
    .time-badge {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .question-type-badge {
        background-color: #e7f3ff;
        color: #2271b1;
    }

    .points-badge {
        background-color: #fff3cd;
        color: #856404;
    }

    .time-badge {
        background-color: #f8d7da;
        color: #721c24;
    }

    .question-actions {
        display: flex;
        gap: 5px;
    }

    .question-actions .button-link {
        padding: 5px;
        border-radius: 3px;
        transition: background-color 0.2s;
    }

    .question-actions .button-link:hover {
        background-color: #f0f0f0;
    }

    .question-actions .button-link:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Question User View */
    .question-user-view {
        padding: 25px;
    }

    .question-header-user {
        margin-bottom: 20px;
    }

    .question-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin: 0 0 10px 0;
        line-height: 1.4;
        cursor: pointer;
        border: 2px solid transparent;
        padding: 8px;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .question-title:hover {
        background-color: #f8f9fa;
        border-color: #dee2e6;
    }

    .question-description {
        color: #666;
        font-size: 14px;
        margin: 0;
        cursor: pointer;
        padding: 8px;
        border: 2px solid transparent;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .question-description:hover {
        background-color: #f8f9fa;
        border-color: #dee2e6;
    }

    .question-image {
        position: relative;
        margin: 15px 0;
        display: inline-block;
    }

    .question-img {
        max-width: 100%;
        height: auto;
        border-radius: 4px;
        border: 1px solid #ddd;
    }

    .image-actions {
        position: absolute;
        top: 5px;
        right: 5px;
        display: flex;
        gap: 3px;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .question-image:hover .image-actions {
        opacity: 1;
    }

    .image-actions .button-link {
        background: rgba(0, 0, 0, 0.7);
        color: white;
        border-radius: 3px;
        padding: 5px;
    }

    .add-image-section {
        text-align: center;
        margin: 15px 0;
        padding: 20px;
        border: 2px dashed #ccc;
        border-radius: 6px;
        background-color: #fafafa;
    }

    .add-image-btn {
        background: none !important;
        border: none !important;
        color: #666 !important;
        font-size: 14px !important;
        cursor: pointer !important;
    }

    .add-image-btn:hover {
        color: #2271b1 !important;
    }

    /* Answers Styling */
    .answers-section {
        margin: 20px 0;
    }

    .answers-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .answer-option {
        margin: 10px 0;
        padding: 15px;
        border: 2px solid #e9ecef;
        border-radius: 6px;
        transition: all 0.2s;
        position: relative;
    }

    .answer-option:hover {
        border-color: #2271b1;
        background-color: #f8f9fa;
    }

    .answer-option.correct-answer {
        background-color: #d4edda;
        border-color: #28a745;
    }

    .answer-label {
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        margin: 0;
        font-weight: 500;
        width: 100%;
    }

    .answer-letter {
        background-color: #2271b1;
        color: white;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 600;
        flex-shrink: 0;
    }

    .answer-text {
        flex: 1;
        cursor: pointer;
        padding: 4px 8px;
        border: 2px solid transparent;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .answer-text:hover {
        background-color: #ffffff;
        border-color: #dee2e6;
    }

    .correct-indicator {
        color: #28a745;
        font-weight: 600;
        margin-left: auto;
    }

    .answer-actions {
        position: absolute;
        top: 5px;
        right: 5px;
        display: flex;
        gap: 3px;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .answer-option:hover .answer-actions {
        opacity: 1;
    }

    .answer-actions .button-link {
        padding: 3px;
        border-radius: 3px;
        background: rgba(255, 255, 255, 0.9);
    }

    /* True/False Styling */
    .true-false .answer-option {
        display: inline-block;
        margin: 10px 15px 10px 0;
        padding: 12px 20px;
        min-width: 120px;
        text-align: center;
    }

    /* Text Answer Styling */
    .text-answer-section {
        margin: 20px 0;
    }

    .user-answer-preview {
        width: 100%;
        min-height: 80px;
        padding: 12px;
        border: 2px solid #e9ecef;
        border-radius: 4px;
        background-color: #f8f9fa;
        resize: vertical;
        margin-bottom: 15px;
    }

    .correct-answer-section {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 6px;
        border: 1px solid #dee2e6;
    }

    .text-answer-item {
        display: flex;
        gap: 10px;
        margin: 8px 0;
        align-items: center;
    }

    .text-answer-item input {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    /* Question Explanation */
    .question-explanation-section {
        margin: 20px 0;
        padding: 15px;
        background-color: #e7f3ff;
        border-left: 4px solid #2271b1;
        border-radius: 4px;
    }

    .explanation-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .explanation-content {
        cursor: pointer;
        padding: 8px;
        border: 2px solid transparent;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .explanation-content:hover {
        background-color: rgba(255, 255, 255, 0.5);
        border-color: #dee2e6;
    }

    /* Add Buttons */
    .add-answer-section,
    .add-explanation-section,
    .add-question-section {
        text-align: center;
        margin: 20px 0;
    }

    .add-question-section {
        padding: 30px;
        border: 2px dashed #ccc;
        border-radius: 8px;
        background-color: #fafafa;
    }

    .no-questions {
        text-align: center;
        padding: 40px 20px;
        color: #666;
    }

    /* Editable Elements */
    .editable-text,
    .editable-field {
        transition: all 0.2s;
    }

    .editable-text.editing {
        background-color: #fff;
        border-color: #2271b1 !important;
        box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.2);
    }

    /* Utility */
    .copy-code {
        margin-left: 5px;
    }

    .small-text {
        width: 80px;
    }

    /* Save All Changes Button */
    .save-all-changes {
        font-size: 14px !important;
        font-weight: 600 !important;
        padding: 8px 16px !important;
        height: auto !important;
        line-height: 1.4 !important;
        border-radius: 6px !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
        transition: all 0.2s !important;
    }

    .save-all-changes:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15) !important;
    }

    .save-all-section {
        margin-top: 20px;
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }

    .button-hero {
        font-size: 16px !important;
        padding: 12px 24px !important;
        height: auto !important;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .question-admin-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }

        .question-meta {
            flex-wrap: wrap;
        }

        .answer-actions {
            position: static;
            opacity: 1;
            justify-content: center;
            margin-top: 10px;
        }

        /* Enhanced editing states */
        .editing {
            position: relative;
        }

        .edit-input,
        .edit-textarea {
            width: 100% !important;
            border: 2px solid #0073aa !important;
            background: #fff !important;
            font-size: inherit !important;
            font-family: inherit !important;
            padding: 5px 8px !important;
            border-radius: 4px !important;
            outline: none !important;
            box-shadow: 0 0 0 1px rgba(0, 115, 170, 0.3) !important;
        }

        .edit-textarea {
            min-height: 60px !important;
            resize: vertical !important;
        }

        /* Enhanced editable elements hover state */
        .editable-text:not(.editing):hover {
            background: rgba(0, 115, 170, 0.1) !important;
            border-radius: 3px;
            outline: 2px dashed rgba(0, 115, 170, 0.5);
            cursor: text;
            position: relative;
        }

        .editable-text:not(.editing):hover::after {
            content: "✏️ Cliquez pour modifier";
            position: absolute;
            top: -30px;
            left: 0;
            background: #333;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 1000;
            pointer-events: none;
            font-weight: normal;
        }

        /* Enhanced question title editing */
        .question-title {
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            transition: all 0.2s;
            position: relative;
            border: 2px solid transparent;
        }

        .question-title:hover {
            background: #f0f0f1;
            border-color: #0073aa;
        }

        /* Enhanced answer text editing */
        .answer-text {
            cursor: pointer;
            padding: 6px 8px;
            border-radius: 3px;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .answer-text:hover {
            background: rgba(0, 115, 170, 0.1);
            border-color: #0073aa;
        }

        /* Enhanced explanation content */
        .explanation-content {
            cursor: pointer;
            padding: 8px;
            border-radius: 3px;
            transition: all 0.2s;
            border: 2px solid transparent;
        }

        .explanation-content:hover {
            background: rgba(133, 100, 4, 0.1);
            border-color: #0073aa;
        }

        /* Enhanced question description */
        .question-description {
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            transition: all 0.2s;
            border: 2px solid transparent;
        }

        .question-description:hover {
            background: #f0f0f1;
            border-color: #0073aa;
        }

        /* Enhanced badges */
        .points-badge {
            background: #00a32a !important;
            color: white !important;
        }

        .time-badge {
            background: #ff8c00 !important;
            color: white !important;
        }

        /* Move button states */
        .move-up-btn:disabled,
        .move-down-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        /* Enhanced content section titles */
        .content-section h3 {
            cursor: pointer;
            position: relative;
            padding: 5px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .content-section h3:hover {
            background: rgba(0, 163, 42, 0.1);
        }

        /* Enhanced explanation content */
        .explanation-content {
            cursor: pointer;
            padding: 5px;
            border-radius: 3px;
            transition: background-color 0.2s;
        }

        .explanation-content:hover {
            background: rgba(133, 100, 4, 0.1);
        }

        /* Notifications */
        .quiz-notification {
            position: fixed;
            top: -100px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            min-width: 250px;
            text-align: center;
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .quiz-success {
            background: #00a32a;
        }

        .quiz-info {
            background: #0073aa;
        }

        .quiz-warning {
            background: #ff8c00;
        }

        .quiz-error {
            background: #d63638;
        }

        /* Loading states */
        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .question-deleted {
            opacity: 0.5;
            pointer-events: none;
        }
    }

    /* New styles for quiz settings and category management */
    .quiz-categories-section {
        position: relative;
    }

    .category-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .category-list li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        margin: 5px 0;
        background: #f8f9fa;
        border-radius: 4px;
        border: 1px solid #e9ecef;
    }

    .category-list li.no-categories {
        background: none;
        border: none;
        padding: 8px 0;
        font-style: italic;
        color: #666;
    }

    .category-list li .dashicons {
        margin-right: 8px;
        color: #2271b1;
    }

    .remove-category-btn {
        color: #d63638 !important;
        text-decoration: none;
        padding: 2px 6px;
        border-radius: 3px;
        transition: background-color 0.2s;
    }

    .remove-category-btn:hover {
        background-color: rgba(214, 54, 56, 0.1);
    }

    .add-category-section {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e9ecef;
    }

    .time-limit-section input,
    .questions-per-page-section input {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 6px 8px;
        transition: border-color 0.2s;
    }

    .time-limit-section input:focus,
    .questions-per-page-section input:focus {
        border-color: #2271b1;
        box-shadow: 0 0 0 1px #2271b1;
        outline: none;
    }

    .time-limit-section .description,
    .questions-per-page-section .description {
        margin-top: 5px;
        color: #666;
        font-size: 13px;
    }

    #available-categories {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 6px 8px;
    }

    /* Animations for category management */
    .category-list li {
        animation: slideInDown 0.3s ease-out;
    }

    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Advanced options styles */
    .advanced-options-header {
        color: #23282d;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .advanced-options-header .dashicons {
        color: #2271b1;
    }

    .checkbox-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-weight: 500;
    }

    .checkbox-wrapper input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }

    .checkbox-wrapper:hover {
        color: #2271b1;
    }

    .form-table th {
        vertical-align: top;
        padding-top: 15px;
    }

    .form-table td .description {
        margin-top: 5px;
        color: #666;
        font-size: 13px;
        font-style: italic;
    }
</style>
<script>
    // Define AJAX object directly
    window.quiz_ai_pro_ajax = {
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('quiz_ai_pro_nonce'); ?>'
    };

    // Ensure AJAX URL is available
    if (typeof ajaxurl === 'undefined') {
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }

    jQuery(document).ready(function($) {
        console.log('Quiz Editor initialized');
        console.log('AJAX object:', typeof quiz_ai_pro_ajax !== 'undefined' ? quiz_ai_pro_ajax : 'NOT DEFINED');
        console.log('Fallback AJAX URL:', ajaxurl);

        // Copy quiz code functionality
        $('.copy-code').on('click', function() {
            var code = $(this).siblings('.quiz-code').text();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(function() {
                    showNotification('Code copié: ' + code, 'success');
                });
            }
        });

        // Quiz publish/unpublish functionality
        $(document).on('click', '.quiz-action', function() {
            var $btn = $(this);
            var action = $btn.data('action');
            var quizId = $btn.data('quiz-id');
            var originalHtml = $btn.html();

            if (action === 'publish') {
                if (!confirm('Êtes-vous sûr de vouloir publier ce quiz ? Il sera accessible aux utilisateurs.')) {
                    return;
                }
            } else if (action === 'unpublish') {
                if (!confirm('Êtes-vous sûr de vouloir dépublier ce quiz ? Il ne sera plus accessible aux utilisateurs.')) {
                    return;
                }
            }

            // Show loading state
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + (action === 'publish' ? 'Publication...' : 'Dépublication...'));

            var ajaxAction = action === 'publish' ? 'quiz_ai_pro_publish_quiz' : 'quiz_ai_pro_unpublish_quiz';

            $.ajax({
                url: (typeof quiz_ai_pro_ajax !== 'undefined' && quiz_ai_pro_ajax.ajax_url) ? quiz_ai_pro_ajax.ajax_url : ajaxurl,
                type: 'POST',
                data: {
                    action: ajaxAction,
                    quiz_id: quizId,
                    nonce: (typeof quiz_ai_pro_ajax !== 'undefined' && quiz_ai_pro_ajax.nonce) ? quiz_ai_pro_ajax.nonce : '<?php echo wp_create_nonce('quiz_ai_pro_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        showNotification(action === 'publish' ? 'Quiz publié avec succès!' : 'Quiz dépublié avec succès!', 'success');
                        // Update the page to reflect the new status
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification('Erreur: ' + (response.data || 'Impossible de modifier le statut'), 'error');
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function() {
                    showNotification('Erreur de communication avec le serveur', 'error');
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        // Save quiz info
        $('#save-quiz-info').on('click', function() {
            var $btn = $(this);
            var originalHtml = $btn.html();

            // Collect form data
            var settings = {
                title: $('#quiz_title').val().trim(),
                description: $('#quiz_description').val().trim(),
                featured_image: $('#quiz_featured_image').val(),
                time_limit: $('#quiz_time_limit').val(),
                questions_per_page: $('#quiz_questions_per_page').val(),

                // Advanced options
                show_contact_form: $('#show_contact_form').is(':checked'),
                show_page_number: $('#show_page_number').is(':checked'),
                show_question_images: $('#show_question_images').is(':checked'),
                show_progress_bar: $('#show_progress_bar').is(':checked'),
                require_login: $('#require_login').is(':checked'),
                disable_first_page: $('#disable_first_page').is(':checked'),
                enable_comments: $('#enable_comments').is(':checked')
            };

            // Debug: Log the settings being sent
            console.log('Settings being sent:', settings);
            console.log('Checkbox states:');
            console.log('show_contact_form:', $('#show_contact_form').is(':checked'));
            console.log('show_page_number:', $('#show_page_number').is(':checked'));
            console.log('show_question_images:', $('#show_question_images').is(':checked'));
            console.log('show_progress_bar:', $('#show_progress_bar').is(':checked'));
            console.log('require_login:', $('#require_login').is(':checked'));
            console.log('disable_first_page:', $('#disable_first_page').is(':checked'));
            console.log('enable_comments:', $('#enable_comments').is(':checked'));

            // Validate required fields
            if (!settings.title) {
                showNotification('❌ Le titre du quiz est requis', 'error');
                return;
            }

            // Show loading state
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Sauvegarde...');

            $.ajax({
                url: (typeof quiz_ai_pro_ajax !== 'undefined' && quiz_ai_pro_ajax.ajax_url) ? quiz_ai_pro_ajax.ajax_url : ajaxurl,
                type: 'POST',
                data: {
                    action: 'quiz_ai_pro_save_quiz_settings',
                    quiz_id: <?php echo $quiz_id; ?>,
                    settings: settings,
                    nonce: '<?php echo wp_create_nonce('quiz_ai_pro_save_settings'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('✅ Informations du quiz sauvegardées avec succès!', 'success');

                        // Update the page title if title was changed
                        if (settings.title && settings.title !== $('#quiz_title').data('original-value')) {
                            $('.wp-heading-inline').html('<span class="dashicons dashicons-edit"></span> Modifier le Quiz: ' + settings.title);
                            $('#quiz_title').data('original-value', settings.title);
                        }
                    } else {
                        showNotification('❌ Erreur: ' + (response.data || 'Impossible de sauvegarder'), 'error');
                    }
                },
                error: function() {
                    showNotification('❌ Erreur de communication avec le serveur', 'error');
                },
                complete: function() {
                    // Restore button state
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        // Inline text editing
        $(document).on('click', '.editable-text', function() {
            var $this = $(this);
            if ($this.hasClass('editing')) return;

            var originalText = $this.text();
            var field = $this.data('field');

            $this.addClass('editing');

            if ($this.is('h3') || $this.hasClass('question-title')) {
                // For titles, use input
                var $input = $('<input type="text" class="edit-input" value="' + originalText + '">');
                $this.html($input);
                $input.focus().select();

                $input.on('blur keypress', function(e) {
                    if (e.type === 'keypress' && e.which !== 13) return;

                    var newText = $input.val().trim();
                    if (newText && newText !== originalText) {
                        $this.text(newText);
                        saveFieldChange($this, field, newText);
                    } else {
                        $this.text(originalText);
                    }
                    $this.removeClass('editing');
                });
            } else {
                // For other text, use textarea
                var $textarea = $('<textarea class="edit-textarea">' + originalText + '</textarea>');
                $this.html($textarea);
                $textarea.focus().select();

                $textarea.on('blur', function() {
                    var newText = $textarea.val().trim();
                    if (newText && newText !== originalText) {
                        $this.text(newText);
                        saveFieldChange($this, field, newText);
                    } else {
                        $this.text(originalText);
                    }
                    $this.removeClass('editing');
                });

                $textarea.on('keydown', function(e) {
                    if (e.ctrlKey && e.which === 13) {
                        $textarea.blur();
                    }
                });
            }
        });

        // Toggle correct answer
        $(document).on('click', '.toggle-correct-btn', function() {
            var $answerOption = $(this).closest('.answer-option');
            var answerId = $answerOption.data('answer-id');
            var isCurrentlyCorrect = $answerOption.hasClass('correct-answer');

            // Toggle visual state
            $answerOption.toggleClass('correct-answer');

            // Update button icon and title
            var $icon = $(this).find('.dashicons');
            var newTitle = isCurrentlyCorrect ? 'Marquer comme correcte' : 'Marquer comme incorrecte';

            $icon.toggleClass('dashicons-yes dashicons-dismiss');
            $(this).attr('title', newTitle);

            // Update checkbox/radio state
            var $input = $answerOption.find('input[type="checkbox"], input[type="radio"]');
            $input.prop('checked', !isCurrentlyCorrect);

            // Add/remove correct indicator
            var $indicator = $answerOption.find('.correct-indicator');
            if (!isCurrentlyCorrect) {
                if ($indicator.length === 0) {
                    $answerOption.find('.answer-text').after('<span class="correct-indicator" title="Réponse correcte"><span class="dashicons dashicons-yes-alt"></span></span>');
                }
            } else {
                $indicator.remove();
            }

            // Save change
            saveAnswerCorrectness(answerId, !isCurrentlyCorrect);

            showNotification('✏️ Réponse mise à jour. Cliquez sur "Enregistrer Tout" pour sauvegarder.', 'info');
        });

        // Question type change handler
        $(document).on('change', '.question-type-dropdown', function() {
            var $dropdown = $(this);
            var questionId = $dropdown.data('question-id');
            var newType = $dropdown.val();
            var originalType = $dropdown.data('original-type');
            var $questionItem = $dropdown.closest('.question-item');

            if (newType === originalType) {
                return; // No change needed
            }

            // Confirm the change
            if (!confirm(`Êtes-vous sûr de vouloir changer le type de cette question de "${originalType}" vers "${newType}" ?\n\nCela peut affecter les réponses existantes.`)) {
                $dropdown.val(originalType);
                return;
            }

            // Show loading state
            var originalHtml = $dropdown.html();
            $dropdown.prop('disabled', true).html('<option>Changement...</option>');

            $.ajax({
                url: (typeof quiz_ai_pro_ajax !== 'undefined' && quiz_ai_pro_ajax.ajax_url) ? quiz_ai_pro_ajax.ajax_url : ajaxurl,
                type: 'POST',
                data: {
                    action: 'quiz_ai_pro_change_question_type',
                    question_id: questionId,
                    new_type: newType,
                    old_type: originalType,
                    nonce: '<?php echo wp_create_nonce('quiz_ai_pro_change_question_type'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Update the original type
                        $dropdown.data('original-type', newType);

                        // Regenerate the answers section based on new type
                        updateAnswersSection($questionItem, newType, response.data.answers || []);

                        showNotification(`Type de question changé vers "${newType}" avec succès`, 'success');
                    } else {
                        showNotification('Erreur: ' + (response.data || 'Impossible de changer le type'), 'error');
                        $dropdown.val(originalType);
                    }
                },
                error: function() {
                    showNotification('Erreur de communication avec le serveur', 'error');
                    $dropdown.val(originalType);
                },
                complete: function() {
                    // Restore dropdown
                    $dropdown.prop('disabled', false).html(originalHtml);
                    // Re-select the correct option
                    $dropdown.val($dropdown.data('original-type'));
                }
            });
        });

        // Function to update answers section based on question type
        function updateAnswersSection($questionItem, questionType, answers) {
            var $answersSection = $questionItem.find('.answers-section');
            var questionId = $questionItem.data('question-id');
            var answerLetters = ['A', 'B', 'C', 'D', 'E', 'F'];
            var newHtml = '';

            console.log('Updating answers section for type:', questionType);
            console.log('Answers data:', answers);

            switch (questionType) {
                case 'qcm':
                case 'multiple-choice':
                case 'single-choice':
                    newHtml = '<div class="answers-list multiple-choice">';

                    // Use provided answers or create defaults
                    if (answers && answers.length > 0) {
                        answers.forEach(function(answer, index) {
                            var inputType = (questionType === 'multiple-choice' || questionType === 'qcm') ? 'checkbox' : 'radio';
                            var isCorrect = answer.is_correct || false;

                            newHtml += `
                                <div class="answer-option ${isCorrect ? 'correct-answer' : ''}" data-answer-id="${answer.id || 'new_' + Date.now() + '_' + index}">
                                    <label class="answer-label">
                                        <input type="${inputType}" name="question_${questionId}" value="${answer.id || index}" ${isCorrect ? 'checked' : ''} disabled>
                                        <span class="answer-letter">${answerLetters[index] || (index + 1)}.</span>
                                        <span class="answer-text editable-text" data-field="answer_text">
                                            ${answer.answer_text || answer.text || 'Réponse ' + (index + 1)}
                                        </span>
                                        ${isCorrect ? '<span class="correct-indicator" title="Réponse correcte"><span class="dashicons dashicons-yes-alt"></span></span>' : ''}
                                    </label>
                                    <div class="answer-actions">
                                        <button type="button" class="button-link toggle-correct-btn" title="Marquer comme ${isCorrect ? 'incorrecte' : 'correcte'}">
                                            <span class="dashicons dashicons-${isCorrect ? 'dismiss' : 'yes'}"></span>
                                        </button>
                                        <button type="button" class="button-link edit-answer-btn" title="Modifier cette réponse">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                        <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        // Create default answers
                        var defaultAnswers = ['Réponse A', 'Réponse B'];
                        defaultAnswers.forEach(function(answerText, index) {
                            var inputType = (questionType === 'multiple-choice' || questionType === 'qcm') ? 'checkbox' : 'radio';

                            newHtml += `
                                <div class="answer-option" data-answer-id="new_${Date.now()}_${index}">
                                    <label class="answer-label">
                                        <input type="${inputType}" name="question_${questionId}" value="${index}" disabled>
                                        <span class="answer-letter">${answerLetters[index]}.</span>
                                        <span class="answer-text editable-text" data-field="answer_text">${answerText}</span>
                                    </label>
                                    <div class="answer-actions">
                                        <button type="button" class="button-link toggle-correct-btn" title="Marquer comme correcte">
                                            <span class="dashicons dashicons-yes"></span>
                                        </button>
                                        <button type="button" class="button-link edit-answer-btn" title="Modifier cette réponse">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                        <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                    }

                    newHtml += `
                        <div class="add-answer-section">
                            <button type="button" class="button add-answer-btn">
                                <span class="dashicons dashicons-plus"></span> Ajouter une réponse
                            </button>
                        </div>
                    </div>`;
                    break;

                case 'true-false':
                    newHtml = '<div class="answers-list true-false">';

                    var trueAnswerCorrect = false;
                    var falseAnswerCorrect = false;

                    // Check existing answers for correctness
                    if (answers && answers.length > 0) {
                        answers.forEach(function(answer) {
                            if (answer.answer_text === 'True' || answer.answer_text === 'Vrai') {
                                trueAnswerCorrect = answer.is_correct || false;
                            } else if (answer.answer_text === 'False' || answer.answer_text === 'Faux') {
                                falseAnswerCorrect = answer.is_correct || false;
                            }
                        });
                    }

                    // Create True/False options
                    var trueFalseOptions = [{
                            text: 'Vrai',
                            correct: trueAnswerCorrect,
                            id: answers && answers[0] ? answers[0].id : 'new_true_' + Date.now()
                        },
                        {
                            text: 'Faux',
                            correct: falseAnswerCorrect,
                            id: answers && answers[1] ? answers[1].id : 'new_false_' + Date.now()
                        }
                    ];

                    trueFalseOptions.forEach(function(option) {
                        newHtml += `
                            <div class="answer-option ${option.correct ? 'correct-answer' : ''}" data-answer-id="${option.id}">
                                <label class="answer-label">
                                    <input type="radio" name="question_${questionId}" value="${option.id}" ${option.correct ? 'checked' : ''} disabled>
                                    <span class="answer-text">${option.text}</span>
                                    ${option.correct ? '<span class="correct-indicator" title="Réponse correcte"><span class="dashicons dashicons-yes-alt"></span></span>' : ''}
                                </label>
                                <button type="button" class="button-link toggle-correct-btn" title="Marquer comme ${option.correct ? 'incorrecte' : 'correcte'}">
                                    <span class="dashicons dashicons-${option.correct ? 'dismiss' : 'yes'}"></span>
                                </button>
                            </div>
                        `;
                    });

                    newHtml += '</div>';
                    break;

                case 'text':
                case 'essay':
                    newHtml = `
                        <div class="text-answer-section">
                            <textarea placeholder="Réponse de l'utilisateur..." disabled class="user-answer-preview"></textarea>
                            <div class="correct-answer-section">
                                <label><strong>Réponse(s) acceptée(s):</strong></label>
                    `;

                    if (answers && answers.length > 0) {
                        answers.forEach(function(answer) {
                            newHtml += `
                                <div class="text-answer-item" data-answer-id="${answer.id}">
                                    <input type="text" value="${answer.answer_text || answer.text || ''}" class="editable-text" data-field="answer_text">
                                    <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            `;
                        });
                    } else {
                        newHtml += `
                            <div class="text-answer-item" data-answer-id="new_${Date.now()}">
                                <input type="text" value="" class="editable-text" data-field="answer_text" placeholder="Réponse acceptée">
                                <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        `;
                    }

                    newHtml += `
                                <button type="button" class="button add-text-answer-btn">
                                    <span class="dashicons dashicons-plus"></span> Ajouter une réponse acceptée
                                </button>
                            </div>
                        </div>
                    `;
                    break;

                case 'fill_blank':
                case 'text_a_completer':
                    var questionText = $questionItem.find('.question-title').text();
                    var previewText = questionText.replace(/\{([^}]+)\}/g, '<input type="text" class="blank-input" placeholder="..." disabled>');

                    newHtml = `
                        <div class="fill-blank-section">
                            <div class="question-preview">
                                <label><strong>Aperçu avec espaces à compléter:</strong></label>
                                <div class="fill-blank-preview">${previewText}</div>
                            </div>
                            <div class="fill-blank-answers">
                                <label><strong>Réponses pour les espaces à compléter:</strong></label>
                                <small>Format: utilisez {réponse} dans le texte de la question pour créer des espaces à compléter</small>
                    `;

                    if (answers && answers.length > 0) {
                        answers.forEach(function(answer, index) {
                            newHtml += `
                                <div class="fill-blank-answer-item" data-answer-id="${answer.id}">
                                    <span class="blank-number">Espace ${index + 1}:</span>
                                    <input type="text" value="${answer.answer_text || answer.text || ''}" class="editable-text" data-field="answer_text" placeholder="Réponse attendue">
                                    <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            `;
                        });
                    } else {
                        newHtml += `
                            <div class="fill-blank-answer-item" data-answer-id="new_${Date.now()}">
                                <span class="blank-number">Espace 1:</span>
                                <input type="text" value="" class="editable-text" data-field="answer_text" placeholder="Réponse attendue">
                                <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        `;
                    }

                    newHtml += `
                                <button type="button" class="button add-fill-blank-answer-btn">
                                    <span class="dashicons dashicons-plus"></span> Ajouter une réponse
                                </button>
                            </div>
                        </div>
                    `;
                    break;

                default:
                    newHtml = '<p>Type de question non supporté: ' + questionType + '</p>';
                    break;
            }

            $answersSection.html(newHtml);
            console.log('Updated answers section HTML:', newHtml);
        }

        // Question actions
        $(document).on('click', '.edit-question-btn', function() {
            showNotification('Édition de question - à implémenter', 'info');
        });

        $(document).on('click', '.duplicate-question-btn', function() {
            var questionId = $(this).closest('.question-item').data('question-id');
            if (confirm('Dupliquer cette question ?')) {
                showNotification('Duplication de question - à implémenter', 'info');
            }
        });

        $(document).on('click', '.delete-question-btn', function() {
            var $questionItem = $(this).closest('.question-item');
            var questionId = $questionItem.data('question-id');
            var questionText = $questionItem.find('.question-title').text().substring(0, 50) + '...';

            if (confirm('Êtes-vous sûr de vouloir supprimer cette question ?\n\n"' + questionText + '"\n\nNote: Les modifications seront visibles après avoir cliqué sur "Enregistrer".')) {
                // Show loading state
                var $deleteBtn = $(this);
                var originalHtml = $deleteBtn.html();
                $deleteBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>');

                $.ajax({
                    url: (typeof quiz_ai_pro_ajax !== 'undefined' && quiz_ai_pro_ajax.ajax_url) ? quiz_ai_pro_ajax.ajax_url : ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'quiz_ai_pro_delete_question',
                        quiz_id: <?php echo $quiz_id; ?>,
                        question_id: questionId,
                        nonce: '<?php echo wp_create_nonce('quiz_ai_pro_delete_question'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $questionItem.addClass('question-deleted').fadeOut(function() {
                                $(this).remove();
                                updateQuestionNumbers();
                            });
                            showNotification('Question supprimée avec succès', 'success');
                        } else {
                            showNotification('Erreur: ' + response.data, 'error');
                            $deleteBtn.prop('disabled', false).html(originalHtml);
                        }
                    },
                    error: function() {
                        showNotification('Erreur de communication avec le serveur', 'error');
                        $deleteBtn.prop('disabled', false).html(originalHtml);
                    }
                });
            }
        });

        // Move questions up/down
        $(document).on('click', '.move-up-btn', function() {
            var $current = $(this).closest('.question-item');
            var $prev = $current.prev('.question-item');

            if ($prev.length) {
                $current.insertBefore($prev);
                updateQuestionNumbers();
                showNotification('Question déplacée vers le haut', 'success');
            }
        });

        $(document).on('click', '.move-down-btn', function() {
            var $current = $(this).closest('.question-item');
            var $next = $current.next('.question-item');

            if ($next.length) {
                $current.insertAfter($next);
                updateQuestionNumbers();
                showNotification('Question déplacée vers le bas', 'success');
            }
        });

        // Add new text answer
        $(document).on('click', '.add-text-answer-btn', function() {
            var newAnswerId = 'new_' + Date.now();
            var newAnswerHtml = `
                <div class="text-answer-item" data-answer-id="${newAnswerId}" data-is-new="true">
                    <input type="text" value="" class="editable-text" data-field="answer_text" placeholder="Réponse acceptée">
                    <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            `;

            $(this).before(newAnswerHtml);
            showNotification('Nouvelle réponse acceptée ajoutée', 'success');
        });

        // Add new answer
        $(document).on('click', '.add-answer-btn', function() {
            var $answersList = $(this).closest('.answers-section').find('.answers-list');
            var questionId = $(this).closest('.question-item').data('question-id');
            var answerCount = $answersList.find('.answer-option').length;
            var letters = ['A', 'B', 'C', 'D', 'E', 'F'];

            if (answerCount >= 6) {
                showNotification('Maximum 6 réponses par question', 'warning');
                return;
            }

            var newAnswerId = 'new_' + Date.now();
            var newAnswerHtml = `
            <div class="answer-option" data-answer-id="${newAnswerId}" data-is-new="true">
                <label class="answer-label">
                    <input type="checkbox" name="question_${questionId}" value="${newAnswerId}" disabled>
                    <span class="answer-letter">${letters[answerCount]}.</span>
                    <span class="answer-text editable-text" data-field="answer_text">Nouvelle réponse</span>
                </label>
                <div class="answer-actions">
                    <button type="button" class="button-link toggle-correct-btn" title="Marquer comme correcte">
                        <span class="dashicons dashicons-yes"></span>
                    </button>
                    <button type="button" class="button-link edit-answer-btn" title="Modifier cette réponse">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>
        `;

            $(this).closest('.add-answer-section').before(newAnswerHtml);
            markAsUnsaved(); // Mark page as having unsaved changes
            showNotification('Nouvelle réponse ajoutée. Cliquez sur "Enregistrer Tout" pour sauvegarder.', 'info');
        });

        // Add new fill-in-blank answer
        $(document).on('click', '.add-fill-blank-answer-btn', function() {
            var $fillBlankSection = $(this).closest('.fill-blank-section');
            var $answersContainer = $(this).parent(); // The .fill-blank-answers container
            var answerCount = $answersContainer.find('.fill-blank-answer-item').length;

            if (answerCount >= 10) {
                showNotification('Maximum 10 espaces à compléter par question', 'warning');
                return;
            }

            var newAnswerId = 'new_' + Date.now();
            var newAnswerHtml = `
            <div class="fill-blank-answer-item" data-answer-id="${newAnswerId}" data-is-new="true">
                <span class="blank-number">Espace ${answerCount + 1}:</span>
                <input type="text" value="" class="editable-text" data-field="answer_text" placeholder="Réponse attendue">
                <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        `;

            $(this).before(newAnswerHtml);
            showNotification('Nouvelle réponse ajoutée', 'success');

            // Update blank numbers
            updateFillBlankNumbers($answersContainer);
        });

        // Delete answer
        $(document).on('click', '.delete-answer-btn', function() {
            var $answerOption = $(this).closest('.answer-option');
            var $fillBlankItem = $(this).closest('.fill-blank-answer-item');

            if ($answerOption.length > 0) {
                // Handle multiple choice answer deletion
                var answerText = $answerOption.find('.answer-text').text();

                if (confirm('Supprimer cette réponse ?\n\n"' + answerText + '"')) {
                    $answerOption.fadeOut(function() {
                        $(this).remove();
                        updateAnswerLetters();
                    });
                }
            } else if ($fillBlankItem.length > 0) {
                // Handle fill-in-blank answer deletion
                var answerText = $fillBlankItem.find('input').val();

                if (confirm('Supprimer cette réponse ?\n\n"' + answerText + '"')) {
                    $fillBlankItem.fadeOut(function() {
                        var $answersContainer = $(this).parent();
                        $(this).remove();
                        updateFillBlankNumbers($answersContainer);
                    });
                }
            }
        });

        // Update fill-in-blank numbers
        function updateFillBlankNumbers($container) {
            $container.find('.fill-blank-answer-item').each(function(index) {
                $(this).find('.blank-number').text('Espace ' + (index + 1) + ':');
            });
        }

        // Original delete answer handler (keeping for backward compatibility)
        $(document).on('click', '.delete-answer-btn-old', function() {
            var $answerOption = $(this).closest('.answer-option');
            var answerText = $answerOption.find('.answer-text').text();

            if (confirm('Supprimer cette réponse ?\n\n"' + answerText + '"')) {
                $answerOption.fadeOut(function() {
                    $(this).remove();
                    updateAnswerLetters();
                    showNotification('Réponse supprimée', 'success');
                });
            }
        });

        // Quiz image upload functionality
        $(document).on('click', '.add-quiz-image-btn, .change-quiz-image-btn', function() {
            var $fileInput = $('<input type="file" accept="image/*" style="display: none;">');
            $('body').append($fileInput);

            $fileInput.on('change', function() {
                var file = this.files[0];
                if (file) {
                    uploadImageToWordPress(file, function(imageUrl, attachmentId) {
                        var $imageSection = $('.quiz-image-section');

                        var imageHtml = `
                            <div class="quiz-image-preview">
                                <img src="${imageUrl}" alt="Quiz Image" class="quiz-image" style="max-width: 300px; height: auto; border-radius: 6px; border: 1px solid #ddd;">
                                <div class="quiz-image-actions" style="margin-top: 10px;">
                                    <button type="button" class="button change-quiz-image-btn">
                                        <span class="dashicons dashicons-edit"></span> Changer l'image
                                    </button>
                                    <button type="button" class="button button-link-delete remove-quiz-image-btn" style="color: #d63638;">
                                        <span class="dashicons dashicons-trash"></span> Supprimer l'image
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" id="quiz_featured_image" name="quiz_featured_image" value="${imageUrl}">
                        `;

                        $imageSection.html(imageHtml);
                        showNotification('📸 Image du quiz ajoutée avec succès!', 'success');
                    });
                }
                $fileInput.remove();
            });

            $fileInput.click();
        });

        // Remove quiz image
        $(document).on('click', '.remove-quiz-image-btn', function() {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette image ?')) {
                var $imageSection = $('.quiz-image-section');

                var emptyHtml = `
                    <div class="add-quiz-image-section">
                        <button type="button" class="button add-quiz-image-btn">
                            <span class="dashicons dashicons-format-image"></span> Ajouter une image au quiz
                        </button>
                        <p class="description">Image recommandée : 800x400 pixels (ratio 2:1)</p>
                    </div>
                    <input type="hidden" id="quiz_featured_image" name="quiz_featured_image" value="">
                `;

                $imageSection.html(emptyHtml);
                showNotification('🗑️ Image du quiz supprimée', 'info');
            }
        });

        // Question image upload functionality
        $(document).on('click', '.add-image-btn, .change-image-btn', function() {
            var $questionItem = $(this).closest('.question-item');
            var questionId = $questionItem.data('question-id');

            var $fileInput = $('<input type="file" accept="image/*" style="display: none;">');
            $('body').append($fileInput);

            $fileInput.on('change', function() {
                var file = this.files[0];
                if (file) {
                    uploadImageToWordPress(file, function(imageUrl, attachmentId) {
                        var $imageSection = $questionItem.find('.question-image-section');

                        var imageHtml = `
                            <div class="question-image">
                                <img src="${imageUrl}" alt="Question Image" class="question-img">
                                <div class="image-actions">
                                    <button type="button" class="button-link change-image-btn" title="Changer l'image">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <button type="button" class="button-link remove-image-btn" title="Supprimer l'image">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" class="question-featured-image" value="${imageUrl}">
                        `;

                        $imageSection.html(imageHtml);
                        showNotification('📸 Image de question ajoutée avec succès!', 'success');
                    });
                }
                $fileInput.remove();
            });

            $fileInput.click();
        });

        // Remove question image
        $(document).on('click', '.remove-image-btn', function() {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette image ?')) {
                var $questionItem = $(this).closest('.question-item');
                var $imageSection = $questionItem.find('.question-image-section');

                var emptyHtml = `
                    <div class="add-image-section">
                        <button type="button" class="button add-image-btn">
                            <span class="dashicons dashicons-format-image"></span> Ajouter une image
                        </button>
                    </div>
                    <input type="hidden" class="question-featured-image" value="">
                `;

                $imageSection.html(emptyHtml);
                showNotification('🗑️ Image de question supprimée', 'info');
            }
        });

        // WordPress Media Library upload function
        function uploadImageToWordPress(file, callback) {
            // Validate file size
            if (file.size > 5 * 1024 * 1024) { // 5MB
                showNotification('❌ Fichier trop volumineux. Taille maximale: 5MB', 'error');
                return;
            }

            // Validate file type
            var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                showNotification('❌ Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WebP.', 'error');
                return;
            }

            var formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'quiz_ai_pro_upload_image');
            formData.append('nonce', quiz_ai_pro_ajax.nonce);

            showNotification('📤 Upload en cours...', 'info');

            $.ajax({
                url: quiz_ai_pro_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        callback(response.data.url, response.data.attachment_id);
                    } else {
                        showNotification('❌ Erreur upload: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showNotification('❌ Erreur de communication serveur', 'error');
                }
            });
        }

        // Add explanation
        $(document).on('click', '.add-explanation-btn', function() {
            var explanationHtml = `
            <div class="question-explanation-section">
                <div class="explanation-header">
                    <strong>💡 Explication:</strong>
                    <button type="button" class="button-link edit-explanation-btn" title="Modifier l'explication">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                </div>
                <div class="explanation-content editable-text" data-field="explanation">
                    Cliquez pour ajouter une explication...
                </div>
            </div>
        `;

            $(this).closest('.add-explanation-section').replaceWith(explanationHtml);
            showNotification('Section explication ajoutée', 'success');
        });

        // Add new question - show type selection modal first
        $(document).on('click', '.add-question-btn', function() {
            showQuestionTypeModal();
        });

        // Show question type selection modal
        function showQuestionTypeModal() {
            $('#question-type-modal').show();
        }

        // Close modal handlers
        $(document).on('click', '.quiz-modal-close, .quiz-modal-cancel', function() {
            $('#question-type-modal').hide();
        });

        // Close modal when clicking outside
        $(document).on('click', '.quiz-modal', function(e) {
            if (e.target === this) {
                $('#question-type-modal').hide();
            }
        });

        // Question type selection handler
        $(document).on('click', '.question-type-option', function() {
            // Remove previous selection
            $('.question-type-option').removeClass('selected');

            // Select this option
            $(this).addClass('selected');

            // Get the selected type
            const selectedType = $(this).data('type');

            // Hide the modal
            $('#question-type-modal').hide();

            // Add the question with the selected type
            addNewQuestion(selectedType);
        });

        // Function to generate answers HTML based on question type
        function generateAnswersForType(questionType) {
            const timestamp = Date.now();

            switch (questionType) {
                case 'true-false':
                    return `
                        <div class="answers-list single-choice">
                            <div class="answer-option" data-answer-id="new_${timestamp}_1" data-is-new="true">
                                <label class="answer-label">
                                    <input type="radio" name="question_new" value="1" disabled>
                                    <span class="answer-letter">A.</span>
                                    <span class="answer-text editable-text" data-field="answer_text">Vrai</span>
                                </label>
                                <div class="answer-actions">
                                    <button type="button" class="button-link toggle-correct-btn" title="Marquer comme correcte">
                                        <span class="dashicons dashicons-yes"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="answer-option" data-answer-id="new_${timestamp}_2" data-is-new="true">
                                <label class="answer-label">
                                    <input type="radio" name="question_new" value="2" disabled>
                                    <span class="answer-letter">B.</span>
                                    <span class="answer-text editable-text" data-field="answer_text">Faux</span>
                                </label>
                                <div class="answer-actions">
                                    <button type="button" class="button-link toggle-correct-btn" title="Marquer comme correcte">
                                        <span class="dashicons dashicons-yes"></span>
                                    </button>
                                </div>
                            </div>
                        </div>`;

                case 'fill_blank':
                case 'text':
                case 'essay':
                    return `
                        <div class="answers-list text-answer">
                            <div class="text-answer-info">
                                <p><em>Cette question utilise une réponse libre. Les réponses seront évaluées automatiquement par l'IA.</em></p>
                            </div>
                        </div>`;

                case 'multiple-choice':
                    return `
                        <div class="answers-list multiple-choice">
                            <div class="answer-option" data-answer-id="new_${timestamp}_1" data-is-new="true">
                                <label class="answer-label">
                                    <input type="checkbox" name="question_new" value="1" disabled>
                                    <span class="answer-letter">A.</span>
                                    <span class="answer-text editable-text" data-field="answer_text">Réponse A</span>
                                </label>
                                <div class="answer-actions">
                                    <button type="button" class="button-link toggle-correct-btn" title="Marquer comme correcte">
                                        <span class="dashicons dashicons-yes"></span>
                                    </button>
                                    <button type="button" class="button-link edit-answer-btn" title="Modifier cette réponse">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="answer-option" data-answer-id="new_${timestamp}_2" data-is-new="true">
                                <label class="answer-label">
                                    <input type="checkbox" name="question_new" value="2" disabled>
                                    <span class="answer-letter">B.</span>
                                    <span class="answer-text editable-text" data-field="answer_text">Réponse B</span>
                                </label>
                                <div class="answer-actions">
                                    <button type="button" class="button-link toggle-correct-btn" title="Marquer comme correcte">
                                        <span class="dashicons dashicons-yes"></span>
                                    </button>
                                    <button type="button" class="button-link edit-answer-btn" title="Modifier cette réponse">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="add-answer-section">
                                <button type="button" class="button add-answer-btn">
                                    <span class="dashicons dashicons-plus"></span> Ajouter une réponse
                                </button>
                            </div>
                        </div>`;

                default: // qcm, single-choice
                    return `
                        <div class="answers-list single-choice">
                            <div class="answer-option" data-answer-id="new_${timestamp}_1" data-is-new="true">
                                <label class="answer-label">
                                    <input type="radio" name="question_new" value="1" disabled>
                                    <span class="answer-letter">A.</span>
                                    <span class="answer-text editable-text" data-field="answer_text">Réponse A</span>
                                </label>
                                <div class="answer-actions">
                                    <button type="button" class="button-link toggle-correct-btn" title="Marquer comme correcte">
                                        <span class="dashicons dashicons-yes"></span>
                                    </button>
                                    <button type="button" class="button-link edit-answer-btn" title="Modifier cette réponse">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="answer-option" data-answer-id="new_${timestamp}_2" data-is-new="true">
                                <label class="answer-label">
                                    <input type="radio" name="question_new" value="2" disabled>
                                    <span class="answer-letter">B.</span>
                                    <span class="answer-text editable-text" data-field="answer_text">Réponse B</span>
                                </label>
                                <div class="answer-actions">
                                    <button type="button" class="button-link toggle-correct-btn" title="Marquer comme correcte">
                                        <span class="dashicons dashicons-yes"></span>
                                    </button>
                                    <button type="button" class="button-link edit-answer-btn" title="Modifier cette réponse">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <button type="button" class="button-link delete-answer-btn" title="Supprimer cette réponse">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="add-answer-section">
                                <button type="button" class="button add-answer-btn">
                                    <span class="dashicons dashicons-plus"></span> Ajouter une réponse
                                </button>
                            </div>
                        </div>`;
            }
        }

        // Function to add a new question with specified type
        function addNewQuestion(questionType = 'qcm') {
            const quizId = <?php echo $quiz_id; ?>;

            // Show loading state
            showNotification('Création de la question...', 'info');

            // Prepare data for AJAX call
            const ajaxData = {
                action: 'quiz_ai_pro_add_question',
                quiz_id: quizId,
                question_type: questionType,
                nonce: '<?php echo wp_create_nonce('quiz_ai_pro_add_question'); ?>'
            };

            // Debug: log the data being sent
            console.log('Sending AJAX data:', ajaxData);

            // Use the existing AJAX action to create the question in database
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    if (response.success) {
                        const newQuestionId = response.data.new_question_id;

                        // Now create the frontend HTML with the real question ID
                        createQuestionHTML(newQuestionId, questionType);

                        showNotification('Question créée avec succès!', 'success');
                    } else {
                        showNotification('Erreur: ' + response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    showNotification('Erreur de connexion', 'error');
                }
            });
        }

        // Function to create the question HTML in frontend
        function createQuestionHTML(questionId, questionType) {
            const questionCount = $('.question-item').length;
            const newQuestionNumber = questionCount + 1;

            // Generate answers HTML based on question type
            let answersHtml = generateAnswersForType(questionType);

            // Create new question HTML with real question ID
            const newQuestionHtml = `
                <div class="question-item user-view-style" data-question-id="${questionId}" data-is-new="false">
                    <!-- Question Header with Admin Controls -->
                    <div class="question-admin-header">
                        <div class="question-meta">
                            <span class="question-number">Question ${newQuestionNumber}</span>
                            <div class="question-type-selector">
                                <select class="question-type-dropdown" data-question-id="${questionId}" data-original-type="${questionType}">
                                    <option value="qcm" ${questionType === 'qcm' ? 'selected' : ''}>QCM</option>
                                    <option value="multiple-choice" ${questionType === 'multiple-choice' ? 'selected' : ''}>Choix Multiple</option>
                                    <option value="single-choice" ${questionType === 'single-choice' ? 'selected' : ''}>Choix Unique</option>
                                    <option value="true-false" ${questionType === 'true-false' ? 'selected' : ''}>Vrai/Faux</option>
                                    <option value="fill_blank" ${questionType === 'fill_blank' ? 'selected' : ''}>Texte à Compléter</option>
                                    <option value="text" ${questionType === 'text' ? 'selected' : ''}>Texte Libre</option>
                                    <option value="essay" ${questionType === 'essay' ? 'selected' : ''}>Essai</option>
                                </select>
                            </div>
                            <span class="points-badge">1 pts</span>
                        </div>
                        <div class="question-actions">
                            <button type="button" class="button-link edit-question-btn" title="Modifier cette question">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button type="button" class="button-link duplicate-question-btn" title="Dupliquer cette question">
                                <span class="dashicons dashicons-admin-page"></span>
                            </button>
                            <button type="button" class="button-link move-up-btn" title="Déplacer vers le haut" ${questionCount === 0 ? 'disabled' : ''}>
                                <span class="dashicons dashicons-arrow-up-alt2"></span>
                            </button>
                            <button type="button" class="button-link move-down-btn" title="Déplacer vers le bas">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                            <button type="button" class="button-link delete-question-btn" title="Supprimer cette question">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Question Content as User Sees It -->
                    <div class="question-user-view">
                        <div class="question-header-user">
                            <h3 class="question-title editable-text" data-field="question_text">
                                Nouvelle question - Cliquez pour modifier
                            </h3>
                        </div>

                        <!-- Question Image if exists -->
                        <div class="question-image-section">
                            <div class="add-image-section">
                                <button type="button" class="button add-image-btn">
                                    <span class="dashicons dashicons-format-image"></span> Ajouter une image
                                </button>
                            </div>
                            <input type="hidden" class="question-featured-image" value="">
                        </div>

                        <!-- Answers Section -->
                        <div class="answers-section">
                            ${answersHtml}
                        </div>

                        <!-- Explanation Section -->
                        <div class="question-explanation-section">
                            <div class="add-explanation-section">
                                <button type="button" class="button add-explanation-btn">
                                    <span class="dashicons dashicons-plus"></span> Ajouter une explication
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Add the question to the questions container
            if ($('.questions-container').length === 0) {
                // If no questions exist, replace the "no questions" div
                $('.no-questions').replaceWith('<div class="questions-container">' + newQuestionHtml + '</div>');
            } else {
                // Append to existing questions
                $('.questions-container').append(newQuestionHtml);
            }

            // Update question numbers
            updateQuestionNumbers();

            // Scroll to the new question
            const newQuestion = $('.question-item').last();
            newQuestion[0].scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });

            // Focus on the question text for immediate editing
            setTimeout(() => {
                newQuestion.find('.question-title').click();
            }, 500);
        }

        // Function to update answer letters (A, B, C, etc.)
        function updateAnswerLetters() {
            $('.answers-list.multiple-choice').each(function() {
                var letters = ['A', 'B', 'C', 'D', 'E', 'F'];
                $(this).find('.answer-option').each(function(index) {
                    $(this).find('.answer-letter').text(letters[index] + '.');
                });
            });
        }

        // Function to update question numbers
        function updateQuestionNumbers() {
            $('.question-item').each(function(index) {
                $(this).find('.question-number').text('Question ' + (index + 1));
            });
        }



        // Save all changes
        $(document).on('click', '.save-all-changes', function() {
            var $btn = $(this);
            var originalHtml = $btn.html();

            // Show loading state
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Sauvegarde en cours...');

            // Collect quiz data
            var quizData = {
                quiz_id: <?php echo $quiz_id; ?>,
                title: $('#quiz_title').val(),
                description: $('#quiz_description').val(),
                featured_image: $('#quiz_featured_image').val() || '',
                time_limit: parseInt($('#quiz_time_limit').val()) || 0,
                questions_per_page: parseInt($('#quiz_questions_per_page').val()) || 1,
                questions: []
            };

            // Collect question data
            $('.question-item').each(function(index) {
                var $question = $(this);
                var questionId = $question.data('question-id');
                var isNewQuestion = $question.data('is-new') === true || questionId === 0;

                // Get question type from the dropdown instead of badge
                var questionType = $question.find('.question-type-dropdown').val() || 'qcm';

                var questionData = {
                    id: isNewQuestion ? 0 : questionId, // 0 for new questions
                    is_new: isNewQuestion,
                    question_order: index + 1,
                    question_text: $question.find('.question-title').text().trim(),
                    question_description: $question.find('.question-description').text().trim() || '',
                    featured_image: $question.find('.question-featured-image').val() || '',
                    explanation: $question.find('.explanation-content').text().trim() || '',
                    points: parseInt($question.find('.points-badge').text()) || 1,
                    time_limit: parseInt($question.find('.time-badge').text()) || 0,
                    difficulty: 'medium',
                    question_type: questionType,
                    answers: []
                };

                // Collect answers based on question type
                if (questionType === 'fill_blank' || questionType === 'text_a_completer') {
                    // Handle fill-in-blank answers
                    $question.find('.fill-blank-answer-item').each(function(answerIndex) {
                        var $answer = $(this);
                        var answerId = $answer.data('answer-id');
                        var isNewAnswer = String(answerId).startsWith('new_') || answerId === 0;
                        var answerText = $answer.find('input[data-field="answer_text"]').val().trim();

                        if (answerText) { // Only include non-empty answers
                            questionData.answers.push({
                                id: isNewAnswer ? 0 : answerId, // 0 for new answers
                                is_new: isNewAnswer,
                                answer_text: answerText,
                                is_correct: 1, // Fill-in-blank answers are always "correct"
                                sort_order: answerIndex + 1
                            });
                        }
                    });
                } else {
                    // Handle multiple choice, true/false, and other answer types
                    $question.find('.answer-option').each(function(answerIndex) {
                        var $answer = $(this);
                        var answerId = $answer.data('answer-id');
                        var isNewAnswer = String(answerId).startsWith('new_') || answerId === 0;
                        var answerText = $answer.find('.answer-text').text().trim();
                        var isCorrect = $answer.hasClass('correct-answer');

                        if (answerText) { // Only include non-empty answers
                            questionData.answers.push({
                                id: isNewAnswer ? 0 : answerId, // 0 for new answers
                                is_new: isNewAnswer,
                                answer_text: answerText,
                                is_correct: isCorrect ? 1 : 0,
                                sort_order: answerIndex + 1
                            });
                        }
                    });
                }

                quizData.questions.push(questionData);
            });

            console.log('Saving quiz data:', quizData);

            // Send AJAX request to save all changes
            $.ajax({
                url: (typeof quiz_ai_pro_ajax !== 'undefined' && quiz_ai_pro_ajax.ajax_url) ? quiz_ai_pro_ajax.ajax_url : ajaxurl,
                type: 'POST',
                data: {
                    action: 'quiz_ai_pro_save_all_changes',
                    quiz_data: JSON.stringify(quizData),
                    nonce: '<?php echo wp_create_nonce('quiz_ai_pro_save_all'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('✅ Toutes les modifications ont été sauvegardées avec succès!', 'success');

                        // Mark as saved
                        markAsSaved();

                        // Update any IDs for new questions/answers that were created
                        if (response.data && response.data.updates) {
                            $.each(response.data.updates, function(oldId, newData) {
                                if (newData.question_id) {
                                    $('[data-question-id="' + oldId + '"]').attr('data-question-id', newData.question_id);
                                }
                                if (newData.answers) {
                                    $.each(newData.answers, function(oldAnswerId, newAnswerId) {
                                        $('[data-answer-id="' + oldAnswerId + '"]').attr('data-answer-id', newAnswerId);
                                    });
                                }
                            });
                        }
                    } else {
                        showNotification('❌ Erreur lors de la sauvegarde: ' + (response.data || 'Erreur inconnue'), 'error');
                    }

                    $btn.prop('disabled', false).html(originalHtml);
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        xhr: xhr,
                        status: status,
                        error: error
                    });
                    showNotification('❌ Erreur de communication avec le serveur', 'error');
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        // Helper functions
        function saveFieldChange($element, field, value) {
            // Just store the change visually - actual saving happens with "Enregistrer Tout"
            console.log('Field changed:', {
                field: field,
                value: value,
                element: $element
            });

            // Show temporary feedback
            showNotification('✏️ Modification enregistrée. Cliquez sur "Enregistrer Tout" pour sauvegarder.', 'info');
        }

        function saveAnswerCorrectness(answerId, isCorrect) {
            // Just store the change visually - actual saving happens with "Enregistrer Tout"
            console.log('Answer correctness changed:', {
                answerId: answerId,
                isCorrect: isCorrect
            });
        }

        // Unsaved changes tracking
        var hasUnsavedChanges = false;

        function markAsUnsaved() {
            hasUnsavedChanges = true;
            $('.save-all-changes').addClass('unsaved-changes');
        }

        function markAsSaved() {
            hasUnsavedChanges = false;
            $('.save-all-changes').removeClass('unsaved-changes');
        }

        // Mark as unsaved when any change is made
        $(document).on('click', '.toggle-correct-btn', function() {
            markAsUnsaved();
        });

        $(document).on('input', '.editable-text, .question-type-dropdown', function() {
            markAsUnsaved();
        });

        // Warn user about unsaved changes before leaving page
        $(window).on('beforeunload', function(e) {
            if (hasUnsavedChanges) {
                var message = 'Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter cette page ?';
                e.returnValue = message;
                return message;
            }
        });

        // Add styling for unsaved changes indicator
        $('<style>')
            .prop('type', 'text/css')
            .html(`
                .save-all-changes.unsaved-changes {
                    background: #dc3232 !important;
                    border-color: #dc3232 !important;
                    animation: pulse-red 2s infinite;
                }

                @keyframes pulse-red {
                    0% { background: #dc3232; }
                    50% { background: #e74c3c; }
                    100% { background: #dc3232; }
                }
            `)
            .appendTo('head');

        function updateQuestionNumbers() {
            var totalQuestions = $('.question-item').length;

            $('.question-item').each(function(index) {
                $(this).find('.question-number').text('Question ' + (index + 1));

                // Update move button states
                var $moveUp = $(this).find('.move-up-btn');
                var $moveDown = $(this).find('.move-down-btn');

                $moveUp.prop('disabled', index === 0);
                $moveDown.prop('disabled', index === totalQuestions - 1);
            });

            // Update the questions count in the quiz info section
            var $questionsInfo = $('.status-item').filter(function() {
                return $(this).find('.label').text().includes('Questions');
            });

            if ($questionsInfo.length) {
                $questionsInfo.find('span:last').text(totalQuestions + ' question(s)');
            }
        }

        function updateAnswerLetters() {
            $('.answers-list').each(function() {
                var letters = ['A', 'B', 'C', 'D', 'E', 'F'];
                $(this).find('.answer-option').each(function(index) {
                    $(this).find('.answer-letter').text(letters[index] + '.');
                });
            });
        }

        function showNotification(message, type) {
            type = type || 'info';

            // Remove existing notifications
            $('.quiz-notification').remove();

            var $notification = $('<div class="quiz-notification quiz-' + type + '">' + message + '</div>');
            $('body').append($notification);

            $notification.animate({
                top: '20px',
                opacity: 1
            }, 300);

            setTimeout(function() {
                $notification.animate({
                    top: '-100px',
                    opacity: 0
                }, 300, function() {
                    $(this).remove();
                });
            }, 3000);
        }

        // Initialize move button states
        updateQuestionNumbers();

        // Quiz settings: Time limit and questions per page change handlers
        $('#quiz_time_limit, #quiz_questions_per_page').on('change blur', function() {
            var $this = $(this);
            var settingName = $this.attr('id').replace('quiz_', '');
            var settingValue = $this.val();
            var quizId = <?php echo $quiz_id; ?>;

            // Validate values
            if (settingName === 'time_limit' && parseInt(settingValue) < 0) {
                showNotification('❌ La limite de temps ne peut pas être négative', 'error');
                $this.val(0);
                return;
            }
            if (settingName === 'questions_per_page' && parseInt(settingValue) < 1) {
                showNotification('❌ Le nombre de questions par page doit être d\'au moins 1', 'error');
                $this.val(1);
                return;
            }

            // Send AJAX request to update setting
            $.ajax({
                url: (typeof quiz_ai_pro_ajax !== 'undefined' && quiz_ai_pro_ajax.ajax_url) ? quiz_ai_pro_ajax.ajax_url : ajaxurl,
                type: 'POST',
                data: {
                    action: 'quiz_ai_pro_update_quiz_settings',
                    quiz_id: quizId,
                    setting_name: settingName,
                    setting_value: settingValue,
                    nonce: (typeof quiz_ai_pro_ajax !== 'undefined' && quiz_ai_pro_ajax.nonce) ? quiz_ai_pro_ajax.nonce : '<?php echo wp_create_nonce('quiz_ai_pro_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var friendlyName = settingName === 'time_limit' ? 'Limite de temps' : 'Questions par page';
                        showNotification('✅ ' + friendlyName + ' mis à jour: ' + response.data.setting_value, 'success');
                    } else {
                        showNotification('❌ Erreur: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showNotification('❌ Erreur de communication serveur', 'error');
                }
            });
        });

        // Category management: Add category functionality
        $('#add-category-btn').on('click', function() {
            var $this = $(this);
            var $select = $('#available-categories');
            var $confirmBtn = $('#confirm-add-category');
            var $cancelBtn = $('#cancel-add-category');

            // Load available categories if not loaded
            if ($select.find('option').length <= 1) {
                $.ajax({
                    url: (typeof quiz_ai_pro_ajax !== 'undefined' && quiz_ai_pro_ajax.ajax_url) ? quiz_ai_pro_ajax.ajax_url : ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_categories'
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            $select.empty().append('<option value="">Sélectionner une catégorie...</option>');

                            // Get currently assigned category IDs
                            var assignedCategoryIds = [];
                            $('#quiz-categories-list li[data-category-id]').each(function() {
                                assignedCategoryIds.push(parseInt($(this).data('category-id')));
                            });

                            // Add available categories (not already assigned)
                            response.data.forEach(function(category) {
                                if (!assignedCategoryIds.includes(parseInt(category.id))) {
                                    $select.append('<option value="' + category.id + '">' + category.name + '</option>');
                                }
                            });

                            if ($select.find('option').length <= 1) {
                                showNotification('ℹ️ Toutes les catégories disponibles sont déjà associées', 'info');
                                return;
                            }

                            // Show selection interface
                            $this.hide();
                            $select.show();
                            $confirmBtn.show();
                            $cancelBtn.show();
                        } else {
                            showNotification('❌ Erreur lors du chargement des catégories', 'error');
                        }
                    },
                    error: function() {
                        showNotification('❌ Erreur de communication serveur', 'error');
                    }
                });
            } else {
                // Show selection interface
                $this.hide();
                $select.show();
                $confirmBtn.show();
                $cancelBtn.show();
            }
        });

        // Confirm add category
        $('#confirm-add-category').on('click', function() {
            var categoryId = $('#available-categories').val();
            var categoryName = $('#available-categories option:selected').text();
            var quizId = <?php echo $quiz_id; ?>;

            if (!categoryId) {
                showNotification('❌ Veuillez sélectionner une catégorie', 'error');
                return;
            }

            $.ajax({
                url: (typeof quiz_ai_pro_ajax !== 'undefined' && quiz_ai_pro_ajax.ajax_url) ? quiz_ai_pro_ajax.ajax_url : ajaxurl,
                type: 'POST',
                data: {
                    action: 'quiz_ai_pro_add_category',
                    quiz_id: quizId,
                    category_id: categoryId,
                    nonce: (typeof quiz_ai_pro_ajax !== 'undefined' && quiz_ai_pro_ajax.nonce) ? quiz_ai_pro_ajax.nonce : '<?php echo wp_create_nonce('quiz_ai_pro_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Remove "no categories" message if present
                        $('#quiz-categories-list .no-categories').remove();

                        // Add new category to the list
                        var categoryHtml = `
                            <li data-category-id="${categoryId}">
                                <span class="dashicons dashicons-category"></span> 
                                ${response.data.category_name}
                                <button type="button" class="button-link remove-category-btn" data-category-id="${categoryId}" style="color: #d63638; margin-left: 10px;">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </li>
                        `;
                        $('#quiz-categories-list').append(categoryHtml);

                        // Remove this category from the available options
                        $('#available-categories option[value="' + categoryId + '"]').remove();

                        // Hide selection interface
                        $('#add-category-btn').show();
                        $('#available-categories, #confirm-add-category, #cancel-add-category').hide();
                        $('#available-categories').val('');

                        showNotification('✅ Catégorie ajoutée: ' + response.data.category_name, 'success');
                    } else {
                        showNotification('❌ Erreur: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showNotification('❌ Erreur de communication serveur', 'error');
                }
            });
        });

        // Cancel add category
        $('#cancel-add-category').on('click', function() {
            $('#add-category-btn').show();
            $('#available-categories, #confirm-add-category, #cancel-add-category').hide();
            $('#available-categories').val('');
        });

        // Remove category functionality
        $(document).on('click', '.remove-category-btn', function() {
            var $this = $(this);
            var categoryId = $this.data('category-id');
            var $categoryItem = $this.closest('li');
            var categoryName = $categoryItem.text().trim().replace('×', '').trim();
            var quizId = <?php echo $quiz_id; ?>;

            if (!confirm('Supprimer la catégorie "' + categoryName + '" de ce quiz ?')) {
                return;
            }

            $.ajax({
                url: (typeof quiz_ai_pro_ajax !== 'undefined' && quiz_ai_pro_ajax.ajax_url) ? quiz_ai_pro_ajax.ajax_url : ajaxurl,
                type: 'POST',
                data: {
                    action: 'quiz_ai_pro_remove_category',
                    quiz_id: quizId,
                    category_id: categoryId,
                    nonce: (typeof quiz_ai_pro_ajax !== 'undefined' && quiz_ai_pro_ajax.nonce) ? quiz_ai_pro_ajax.nonce : '<?php echo wp_create_nonce('quiz_ai_pro_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Remove category from list
                        $categoryItem.fadeOut(function() {
                            $(this).remove();

                            // Show "no categories" message if list is empty
                            if ($('#quiz-categories-list li').length === 0) {
                                $('#quiz-categories-list').append('<li class="no-categories"><em>Aucune catégorie associée</em></li>');
                            }
                        });

                        // Add this category back to available options if dropdown is loaded
                        if ($('#available-categories option').length > 1) {
                            $('#available-categories').append('<option value="' + categoryId + '">' + categoryName + '</option>');
                        }

                        showNotification('✅ Catégorie supprimée: ' + categoryName, 'success');
                    } else {
                        showNotification('❌ Erreur: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showNotification('❌ Erreur de communication serveur', 'error');
                }
            });
        });
    });
</script>