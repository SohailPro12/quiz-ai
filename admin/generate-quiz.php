<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap quiz-ia-pro-admin">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-plus-alt"></span>
        Quiz IA Pro - Générateur de Contenu Éducatif
    </h1>

    <hr class="wp-header-end">

    <!-- Navigation Tabs -->
    <div class="nav-tab-wrapper">
        <a href="#" class="nav-tab nav-tab-active" data-tab="quiz-tab">
            <span class="dashicons dashicons-forms"></span>
            Générer un Quiz
        </a>
        <a href="#" class="nav-tab" data-tab="exercise-tab">
            <span class="dashicons dashicons-admin-tools"></span>
            Exercices Pratiques
        </a>
    </div>

    <!-- Quiz Generator Tab -->
    <div id="quiz-tab" class="tab-content active">
        <div class="quiz-generator-container">
            <form id="quiz-generator-form" method="post" action="">
                <?php wp_nonce_field('quiz_generator_action', 'quiz_generator_nonce'); ?>

                <!-- Section 1: Configuration de base -->
                <div class="generator-section">
                    <h2><span class="dashicons dashicons-admin-settings"></span> Configuration de Base</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="course_ids">Choisir les Cours</label>
                            <div class="multi-select-container">
                                <div class="multi-select-header" id="courses-select-header">
                                    <span class="placeholder">-- Sélectionner des cours --</span>
                                    <span class="toggle-arrow">▼</span>
                                </div>
                                <div class="multi-select-dropdown" id="courses-dropdown" style="display: none;">
                                    <div class="multi-select-search">
                                        <input type="text" placeholder="Rechercher des cours..." id="courses-search">
                                    </div>
                                    <div class="multi-select-options" id="courses-options">
                                        <div class="loading-placeholder">Chargement des cours...</div>
                                    </div>
                                    <div class="multi-select-actions">
                                        <button type="button" class="select-all-btn" data-target="courses">Tout sélectionner</button>
                                        <button type="button" class="clear-all-btn" data-target="courses">Tout désélectionner</button>
                                    </div>
                                </div>
                            </div>
                            <div class="selected-items" id="selected-courses"></div>
                            <small class="form-help">Sélectionnez un ou plusieurs cours pour la génération</small>
                        </div>

                        <div class="form-group">
                            <label for="category_ids">Sélectionner les Catégories</label>
                            <div class="multi-select-container">
                                <div class="multi-select-header" id="categories-select-header">
                                    <span class="placeholder">-- Sélectionner des catégories --</span>
                                    <span class="toggle-arrow">▼</span>
                                </div>
                                <div class="multi-select-dropdown" id="categories-dropdown" style="display: none;">
                                    <div class="multi-select-search">
                                        <input type="text" placeholder="Rechercher des catégories..." id="categories-search">
                                    </div>
                                    <div class="multi-select-options" id="categories-options">
                                        <div class="loading-placeholder">Chargement des catégories...</div>
                                    </div>
                                    <div class="multi-select-actions">
                                        <button type="button" class="select-all-btn" data-target="categories">Tout sélectionner</button>
                                        <button type="button" class="clear-all-btn" data-target="categories">Tout désélectionner</button>
                                    </div>
                                </div>
                            </div>
                            <div class="selected-items" id="selected-categories"></div>
                            <small class="form-help">Sélectionnez une ou plusieurs catégories pour la génération</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="quiz_type">Type de Quiz <span class="required">*</span></label>
                            <select id="quiz_type" name="quiz_type" class="form-control" required>
                                <option value="">-- Sélectionner le type --</option>
                                <option value="qcm">QCM (Questions à Choix Multiple)</option>
                                <option value="open">Questions Ouvertes</option>
                                <option value="mixed">Mixte (QCM + Ouvertes)</option>
                                <option value="true_false">Vrai/Faux</option>
                                <option value="fill_blank">Texte à Compléter</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="quiz_name">Nom du Quiz <span class="required">*</span></label>
                            <input type="text" id="quiz_name" name="quiz_name" class="form-control" required
                                placeholder="Ex: Quiz de Mathématiques - Niveau Débutant">
                        </div>
                    </div>
                </div>

                <!-- Section 3: Type de Formulaire et Évaluation -->
                <div class="generator-section">
                    <h2><span class="dashicons dashicons-forms"></span> Type de Formulaire et Évaluation</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="form_type">Type de Formulaire</label>
                            <select id="form_type" name="form_type" class="form-control">
                                <option value="quiz">Quiz</option>
                                <option value="poll">Sondage</option>
                                <option value="simple_form">Formulaire Simple</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="grading_system">Système de Notation</label>
                            <select id="grading_system" name="grading_system" class="form-control">
                                <option value="correct_incorrect">Correct/Incorrect</option>
                                <option value="points">Points</option>
                                <option value="both">Les Deux</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Options d'Affichage -->
                <div class="generator-section">
                    <h2><span class="dashicons dashicons-visibility"></span> Options d'Affichage</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="show_contact_form" value="1">
                                <span class="checkmark"></span>
                                Afficher un formulaire de contact avant le quiz
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="show_page_number" value="1" checked>
                                <span class="checkmark"></span>
                                Afficher le numéro de page actuel
                            </label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="show_question_images" value="1">
                                <span class="checkmark"></span>
                                Afficher les images des questions sur la page de résultats
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="show_progress_bar" value="1" checked>
                                <span class="checkmark"></span>
                                Afficher la barre de progression
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Section 5: Paramètres Avancés -->
                <div class="generator-section">
                    <h2><span class="dashicons dashicons-admin-generic"></span> Paramètres Avancés</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="time_limit">Limite de Temps (en minutes)</label>
                            <input type="number" id="time_limit" name="time_limit" class="form-control"
                                min="0" value="0" placeholder="0 = Pas de limite">
                            <small class="form-help">Définir à 0 pour aucune limite de temps</small>
                        </div>

                        <div class="form-group">
                            <label for="questions_per_page">Questions par Page</label>
                            <input type="number" id="questions_per_page" name="questions_per_page"
                                class="form-control" min="0" value="0" placeholder="0 = Pagination par défaut">
                            <small class="form-help">Saisir 0 pour utiliser les paramètres de pagination par défaut</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="require_login" value="1">
                                <span class="checkmark"></span>
                                Nécessiter une connexion utilisateur
                            </label>
                            <small class="form-help">Permettre seulement aux utilisateurs connectés d'accéder au quiz</small>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="disable_first_page" value="1">
                                <span class="checkmark"></span>
                                Désactiver la première page du quiz
                            </label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="enable_comments" value="1">
                                <span class="checkmark"></span>
                                Activer la boîte de commentaires
                            </label>
                            <small class="form-help">Permettre aux utilisateurs de poster des commentaires à la fin du quiz</small>
                        </div>
                    </div>
                </div>

                <!-- Section 6: Configuration IA -->
                <div class="generator-section">
                    <h2><span class="dashicons dashicons-superhero"></span> Configuration IA</h2>

                    <div class="ai-info-banner">
                        <div class="ai-info-icon">🤖</div>
                        <div class="ai-info-content">
                            <strong>Propulsé par Google Gemini</strong>
                            <p>Génération intelligente de quiz utilisant l'IA Gemini de Google pour créer des questions pertinentes et adaptées à vos contenus.</p>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="num_questions">Nombre de Questions</label>
                            <input type="number" id="num_questions" name="num_questions"
                                class="form-control" min="1" max="50" value="10" required>
                            <small class="form-help">Entre 1 et 50 questions</small>
                        </div>

                        <div class="form-group">
                            <label for="difficulty_level">Niveau de Difficulté</label>
                            <select id="difficulty_level" name="difficulty_level" class="form-control">
                                <option value="beginner">Débutant</option>
                                <option value="intermediate">Intermédiaire</option>
                                <option value="advanced">Avancé</option>
                                <option value="mixed">Mixte</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="language">Langue</label>
                            <select id="language" name="language" class="form-control">
                                <option value="fr">Français</option>
                                <option value="en">Anglais</option>
                                <option value="es">Espagnol</option>
                                <option value="de">Allemand</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row full-width">
                        <div class="form-group">
                            <label for="additional_instructions">Instructions Supplémentaires pour l'IA</label>
                            <textarea id="additional_instructions" name="additional_instructions"
                                class="form-control" rows="4"
                                placeholder="Ex: Concentrez-vous sur les concepts pratiques, incluez des exemples concrets, évitez les questions trop théoriques..."></textarea>
                            <small class="form-help">Optionnel: Donnez des instructions spécifiques à l'IA pour personnaliser la génération</small>
                        </div>
                    </div>
                </div>

                <!-- Section Boutons d'action -->
                <div class="generator-actions">
                    <div class="actions-left">
                        <button type="button" class="button button-secondary" onclick="history.back()">
                            <span class="dashicons dashicons-arrow-left-alt"></span>
                            Retour
                        </button>
                    </div>

                    <div class="actions-right">
                        <button type="submit" id="generate-quiz" class="button button-primary button-large">
                            <span class="dashicons dashicons-superhero"></span>
                            Générer le Quiz avec IA
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Practical Exercise Generator Tab -->
    <div id="exercise-tab" class="tab-content">
        <div class="exercise-generator-container">
            <form id="exercise-generator-form" method="post" action="">
                <?php wp_nonce_field('exercise_generator_action', 'exercise_generator_nonce'); ?>

                <!-- Section 1: Configuration de base -->
                <div class="generator-section">
                    <h2><span class="dashicons dashicons-admin-settings"></span> Configuration de l'Exercice Pratique</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="exercise_course_ids">Choisir les Cours</label>
                            <div class="multi-select-container">
                                <div class="multi-select-header" id="exercise-courses-select-header">
                                    <span class="placeholder">-- Sélectionner des cours --</span>
                                    <span class="toggle-arrow">▼</span>
                                </div>
                                <div class="multi-select-dropdown" id="exercise-courses-dropdown" style="display: none;">
                                    <div class="multi-select-search">
                                        <input type="text" placeholder="Rechercher des cours..." id="exercise-courses-search">
                                    </div>
                                    <div class="multi-select-options" id="exercise-courses-options">
                                        <div class="loading-placeholder">Chargement des cours...</div>
                                    </div>
                                    <div class="multi-select-actions">
                                        <button type="button" class="select-all-btn" data-target="exercise-courses">Tout sélectionner</button>
                                        <button type="button" class="clear-all-btn" data-target="exercise-courses">Tout désélectionner</button>
                                    </div>
                                </div>
                            </div>
                            <div class="selected-items" id="selected-exercise-courses"></div>
                            <small class="form-help">Sélectionnez un ou plusieurs cours pour l'exercice pratique</small>
                        </div>

                        <div class="form-group">
                            <label for="exercise_category_ids">Sélectionner les Catégories</label>
                            <div class="multi-select-container">
                                <div class="multi-select-header" id="exercise-categories-select-header">
                                    <span class="placeholder">-- Sélectionner des catégories --</span>
                                    <span class="toggle-arrow">▼</span>
                                </div>
                                <div class="multi-select-dropdown" id="exercise-categories-dropdown" style="display: none;">
                                    <div class="multi-select-search">
                                        <input type="text" placeholder="Rechercher des catégories..." id="exercise-categories-search">
                                    </div>
                                    <div class="multi-select-options" id="exercise-categories-options">
                                        <div class="loading-placeholder">Chargement des catégories...</div>
                                    </div>
                                    <div class="multi-select-actions">
                                        <button type="button" class="select-all-btn" data-target="exercise-categories">Tout sélectionner</button>
                                        <button type="button" class="clear-all-btn" data-target="exercise-categories">Tout désélectionner</button>
                                    </div>
                                </div>
                            </div>
                            <div class="selected-items" id="selected-exercise-categories"></div>
                            <small class="form-help">Optionnel - Catégories supplémentaires pour l'exercice</small>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Configuration de l'exercice -->
                <div class="generator-section">
                    <h2><span class="dashicons dashicons-admin-tools"></span> Paramètres de l'Exercice</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="exercise_title">Titre de l'Exercice</label>
                            <input type="text" id="exercise_title" name="exercise_title" class="regular-text" placeholder="Ex: Construire un Tableau de Bord Power BI">
                            <small class="form-help">Titre descriptif de l'exercice pratique</small>
                        </div>

                        <div class="form-group">
                            <label for="exercise_description">Description (Optionnelle)</label>
                            <textarea id="exercise_description" name="exercise_description" class="large-text code" rows="4" placeholder="Description détaillée de l'objectif et du contexte de l'exercice..."></textarea>
                            <small class="form-help">Description du contexte et des objectifs de l'exercice</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="exercise_sections">Nombre de Sections</label>
                            <input type="number" id="exercise_sections" name="exercise_sections" value="5" min="3" max="15">
                            <small class="form-help">Nombre d'étapes principales de l'exercice (3-15)</small>
                        </div>

                        <div class="form-group">
                            <label for="exercise_complexity">Niveau de Complexité</label>
                            <select id="exercise_complexity" name="exercise_complexity">
                                <option value="beginner">Débutant</option>
                                <option value="intermediate" selected>Intermédiaire</option>
                                <option value="advanced">Avancé</option>
                                <option value="expert">Expert</option>
                            </select>
                            <small class="form-help">Niveau de difficulté de l'exercice</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="exercise_type">Type d'Exercice</label>
                            <select id="exercise_type" name="exercise_type">
                                <option value="dashboard">Tableau de Bord</option>
                                <option value="analysis">Analyse de Données</option>
                                <option value="visualization">Visualisation</option>
                                <option value="project">Projet Complet</option>
                                <option value="case_study">Étude de Cas</option>
                                <option value="hands_on">Pratique Dirigée</option>
                            </select>
                            <small class="form-help">Type d'exercice pratique à générer</small>
                        </div>

                        <div class="form-group">
                            <label for="exercise_tools">Outils/Technologies</label>
                            <input type="text" id="exercise_tools" name="exercise_tools" class="regular-text" placeholder="Ex: Power BI, Tableau, Excel, SQL">
                            <small class="form-help">Outils ou technologies utilisés dans l'exercice</small>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Contenu et Structure -->
                <div class="generator-section">
                    <h2><span class="dashicons dashicons-editor-ul"></span> Structure du Contenu</h2>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="exercise_include_datasets">
                                <input type="checkbox" id="exercise_include_datasets" name="exercise_include_datasets" checked>
                                Inclure des références aux jeux de données
                            </label>
                        </div>

                        <div class="form-group full-width">
                            <label for="exercise_include_screenshots">
                                <input type="checkbox" id="exercise_include_screenshots" name="exercise_include_screenshots" checked>
                                Suggérer des emplacements pour captures d'écran
                            </label>
                        </div>

                        <div class="form-group full-width">
                            <label for="exercise_include_validation">
                                <input type="checkbox" id="exercise_include_validation" name="exercise_include_validation" checked>
                                Inclure des critères de validation/vérification
                            </label>
                        </div>

                        <div class="form-group full-width">
                            <label for="exercise_include_extensions">
                                <input type="checkbox" id="exercise_include_extensions" name="exercise_include_extensions">
                                Proposer des extensions/améliorations
                            </label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="exercise_additional_requirements">Exigences Supplémentaires</label>
                            <textarea id="exercise_additional_requirements" name="exercise_additional_requirements" class="large-text code" rows="3" placeholder="Spécifications particulières, contraintes, ou éléments à inclure..."></textarea>
                            <small class="form-help">Détails spécifiques à inclure dans l'exercice</small>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Génération -->
                <div class="generator-section">
                    <div class="form-actions">
                        <button type="submit" id="generate-exercise-btn" class="button button-primary button-hero">
                            <span class="dashicons dashicons-admin-tools"></span>
                            Générer l'Exercice Pratique
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Génération -->
    <div id="generation-modal" class="quiz-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><span class="dashicons dashicons-superhero"></span> Génération du Quiz en Cours</h3>
            </div>
            <div class="modal-body">
                <div class="generation-progress">
                    <div class="progress-step active" data-step="1">
                        <span class="step-number">1</span>
                        <span class="step-text">Analyse du contenu</span>
                    </div>
                    <div class="progress-step" data-step="2">
                        <span class="step-number">2</span>
                        <span class="step-text">Génération des questions</span>
                    </div>
                    <div class="progress-step" data-step="3">
                        <span class="step-number">3</span>
                        <span class="step-text">Création des réponses</span>
                    </div>
                    <div class="progress-step" data-step="4">
                        <span class="step-number">4</span>
                        <span class="step-text">Finalisation</span>
                    </div>
                </div>

                <div class="generation-details">
                    <p id="generation-status">Préparation de la génération...</p>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%"></div>
                    </div>
                    <div id="generation-log" class="generation-log"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="cancel-generation" class="button">Annuler</button>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        // Make these global so admin.js can access them
        window.selectedCourses = [];
        window.selectedCategories = [];

        // Exercise-specific arrays
        window.selectedExerciseCourses = [];
        window.selectedExerciseCategories = [];

        jQuery(document).ready(function($) {
            // Multi-select functionality - using global variables
            let allCourses = [];
            let allCategories = [];

            // Exercise-specific arrays
            let allExerciseCourses = [];
            let allExerciseCategories = [];

            // Function to get selected items by type
            function getSelectedItems(type) {
                switch (type) {
                    case 'courses':
                        return window.selectedCourses.map(item => item.id);
                    case 'categories':
                        return window.selectedCategories.map(item => item.id);
                    case 'exercise-courses':
                        return window.selectedExerciseCourses.map(item => item.id);
                    case 'exercise-categories':
                        return window.selectedExerciseCategories.map(item => item.id);
                    default:
                        return [];
                }
            }

            // Initialize multi-select dropdowns
            function initMultiSelect() {
                // Course multi-select
                $('#courses-select-header').on('click', function() {
                    $('#courses-dropdown').toggle();
                    $('#categories-dropdown').hide();
                });

                // Category multi-select
                $('#categories-select-header').on('click', function() {
                    $('#categories-dropdown').toggle();
                    $('#courses-dropdown').hide();
                });

                // Close dropdowns when clicking outside
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.multi-select-container').length) {
                        $('.multi-select-dropdown').hide();
                    }
                });

                // Search functionality for courses
                $('#courses-search').on('input', function() {
                    const searchTerm = $(this).val().toLowerCase();
                    filterOptions('courses', searchTerm);
                });

                // Search functionality for categories
                $('#categories-search').on('input', function() {
                    const searchTerm = $(this).val().toLowerCase();
                    filterOptions('categories', searchTerm);
                });

                // Select/Clear all buttons
                $('.select-all-btn').on('click', function() {
                    const target = $(this).data('target');
                    selectAllItems(target);
                });

                $('.clear-all-btn').on('click', function() {
                    const target = $(this).data('target');
                    clearAllItems(target);
                });
            }

            // Filter options based on search
            function filterOptions(type, searchTerm) {
                let items;
                if (type.startsWith('exercise-')) {
                    const exerciseType = type.replace('exercise-', '');
                    items = exerciseType === 'courses' ? allExerciseCourses : allExerciseCategories;
                } else {
                    items = type === 'courses' ? allCourses : allCategories;
                }

                const container = $(`#${type}-options`);

                container.empty();

                const filteredItems = items.filter(item =>
                    item.title?.toLowerCase().includes(searchTerm) ||
                    item.name?.toLowerCase().includes(searchTerm)
                );

                renderOptions(type, filteredItems);
            }

            // Render options in dropdown
            function renderOptions(type, items) {
                const container = $(`#${type}-options`);
                let selectedItems;

                if (type.startsWith('exercise-')) {
                    const exerciseType = type.replace('exercise-', '');
                    selectedItems = exerciseType === 'courses' ? window.selectedExerciseCourses : window.selectedExerciseCategories;
                } else {
                    selectedItems = type === 'courses' ? window.selectedCourses : window.selectedCategories;
                }

                if (items.length === 0) {
                    container.html('<div class="no-results">Aucun résultat trouvé</div>');
                    return;
                }

                items.forEach(item => {
                    const isSelected = selectedItems.some(selected => selected.id == item.id);
                    const title = item.title || item.name;

                    const optionHtml = `
                    <div class="multi-select-option ${isSelected ? 'selected' : ''}" 
                         data-id="${item.id}" data-type="${type}">
                        <input type="checkbox" ${isSelected ? 'checked' : ''} />
                        <span class="option-text">${title}</span>
                    </div>
                `;
                    container.append(optionHtml);
                });

                // Add click handlers for new options
                container.find('.multi-select-option').on('click', function() {
                    const id = $(this).data('id');
                    const type = $(this).data('type');
                    const checkbox = $(this).find('input[type="checkbox"]');
                    const isChecked = !checkbox.prop('checked');

                    checkbox.prop('checked', isChecked);
                    $(this).toggleClass('selected', isChecked);

                    if (isChecked) {
                        if (type.startsWith('exercise-')) {
                            const exerciseType = type.replace('exercise-', '');
                            addSelectedExerciseItem(exerciseType, id);
                        } else {
                            addSelectedItem(type, id);
                        }
                    } else {
                        if (type.startsWith('exercise-')) {
                            const exerciseType = type.replace('exercise-', '');
                            removeSelectedExerciseItem(exerciseType, id);
                        } else {
                            removeSelectedItem(type, id);
                        }
                    }

                    if (type.startsWith('exercise-')) {
                        const exerciseType = type.replace('exercise-', '');
                        updateExerciseSelectedDisplay(exerciseType);
                        updateExerciseHeaderText(exerciseType);
                    } else {
                        updateSelectedDisplay(type);
                        updateHeaderText(type);
                    }
                });
            }

            // Add selected item
            function addSelectedItem(type, id) {
                const items = type === 'courses' ? allCourses : allCategories;
                const selectedItems = type === 'courses' ? window.selectedCourses : window.selectedCategories;

                const item = items.find(i => i.id == id);
                if (item && !selectedItems.some(selected => selected.id == id)) {
                    selectedItems.push(item);
                }
            }

            // Remove selected item
            function removeSelectedItem(type, id) {
                const selectedItems = type === 'courses' ? window.selectedCourses : window.selectedCategories;
                const index = selectedItems.findIndex(item => item.id == id);
                if (index > -1) {
                    selectedItems.splice(index, 1);
                }
            }

            // Update selected items display
            function updateSelectedDisplay(type) {
                const selectedItems = type === 'courses' ? window.selectedCourses : window.selectedCategories;
                const container = $(`#selected-${type}`);

                container.empty();

                if (selectedItems.length === 0) {
                    container.hide();
                    return;
                }

                container.show();

                selectedItems.forEach(item => {
                    const title = item.title || item.name;
                    const tag = $(`
                    <span class="selected-tag" data-id="${item.id}" data-type="${type}">
                        ${title}
                        <span class="remove-tag" data-id="${item.id}" data-type="${type}">×</span>
                    </span>
                `);
                    container.append(tag);
                });

                // Add remove handlers
                container.find('.remove-tag').on('click', function() {
                    const id = $(this).data('id');
                    const type = $(this).data('type');
                    removeSelectedItem(type, id);
                    updateSelectedDisplay(type);
                    updateHeaderText(type);
                    // Update checkbox in dropdown
                    $(`#${type}-options .multi-select-option[data-id="${id}"]`)
                        .removeClass('selected')
                        .find('input[type="checkbox"]')
                        .prop('checked', false);
                });
            }

            // Update header text
            function updateHeaderText(type) {
                const selectedItems = type === 'courses' ? window.selectedCourses : window.selectedCategories;
                const header = $(`#${type}-select-header .placeholder`);

                if (selectedItems.length === 0) {
                    header.text(type === 'courses' ?
                        '-- Sélectionner des cours --' :
                        '-- Sélectionner des catégories --');
                } else if (selectedItems.length === 1) {
                    const title = selectedItems[0].title || selectedItems[0].name;
                    header.text(title);
                } else {
                    header.text(`${selectedItems.length} ${type === 'courses' ? 'cours' : 'catégories'} sélectionné(s)`);
                }
            }

            // Select all options (general function)
            function selectAllOptions(type) {
                if (type.startsWith('exercise-')) {
                    const exerciseType = type.replace('exercise-', '');
                    const items = exerciseType === 'courses' ? allExerciseCourses : allExerciseCategories;
                    const selectedItems = exerciseType === 'courses' ? window.selectedExerciseCourses : window.selectedExerciseCategories;

                    // Clear current selection and add all items
                    selectedItems.length = 0;
                    selectedItems.push(...items);

                    // Update UI
                    updateExerciseSelectedDisplay(exerciseType);
                    updateExerciseHeaderText(exerciseType);

                    // Update checkboxes in dropdown
                    $(`#${type}-options .multi-select-option`)
                        .addClass('selected')
                        .find('input[type="checkbox"]')
                        .prop('checked', true);
                } else {
                    selectAllItems(type);
                }
            }

            // Clear all options (general function)
            function clearAllOptions(type) {
                if (type.startsWith('exercise-')) {
                    const exerciseType = type.replace('exercise-', '');
                    const selectedItems = exerciseType === 'courses' ? window.selectedExerciseCourses : window.selectedExerciseCategories;

                    selectedItems.length = 0;

                    // Update UI
                    updateExerciseSelectedDisplay(exerciseType);
                    updateExerciseHeaderText(exerciseType);

                    // Update checkboxes in dropdown
                    $(`#${type}-options .multi-select-option`)
                        .removeClass('selected')
                        .find('input[type="checkbox"]')
                        .prop('checked', false);
                } else {
                    clearAllItems(type);
                }
            }

            // Select all items
            function selectAllItems(type) {
                const items = type === 'courses' ? allCourses : allCategories;
                const selectedItems = type === 'courses' ? window.selectedCourses : window.selectedCategories;

                // Clear current selection
                selectedItems.length = 0;
                // Add all items
                selectedItems.push(...items);

                // Update UI
                updateSelectedDisplay(type);
                updateHeaderText(type);

                // Update checkboxes in dropdown
                $(`#${type}-options .multi-select-option`)
                    .addClass('selected')
                    .find('input[type="checkbox"]')
                    .prop('checked', true);
            }

            // Clear all items
            function clearAllItems(type) {
                const selectedItems = type === 'courses' ? window.selectedCourses : window.selectedCategories;
                selectedItems.length = 0;

                // Update UI
                updateSelectedDisplay(type);
                updateHeaderText(type);

                // Update checkboxes in dropdown
                $(`#${type}-options .multi-select-option`)
                    .removeClass('selected')
                    .find('input[type="checkbox"]')
                    .prop('checked', false);
            }

            // Load courses dynamically
            function loadCourses() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_courses',
                        nonce: '<?php echo wp_create_nonce('quiz_generator_action'); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            allCourses = response.data;
                            renderOptions('courses', allCourses);
                            console.log('Courses loaded successfully:', response.data.length, 'courses');
                        } else {
                            $('#courses-options').html('<div class="error-message">Erreur lors du chargement des cours</div>');
                            console.error('Failed to load courses:', response);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#courses-options').html('<div class="error-message">Erreur lors du chargement des cours</div>');
                        console.error('AJAX error loading courses:', error);
                    }
                });
            }

            // Load categories dynamically
            function loadCategories() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_categories',
                        nonce: '<?php echo wp_create_nonce('quiz_generator_action'); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            allCategories = response.data;
                            renderOptions('categories', allCategories);
                            console.log('Categories loaded successfully:', response.data.length, 'categories');
                        } else {
                            $('#categories-options').html('<div class="error-message">Erreur lors du chargement des catégories</div>');
                            console.error('Failed to load categories:', response);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#categories-options').html('<div class="error-message">Erreur lors du chargement des catégories</div>');
                        console.error('AJAX error loading categories:', error);
                    }
                });
            }

            // Exercise-specific helper functions
            function renderExerciseOptions(type, items) {
                renderOptions('exercise-' + type, items);
            }

            function filterExerciseOptions(type, searchTerm) {
                filterOptions('exercise-' + type, searchTerm);
            }

            function selectAllExerciseOptions(type) {
                selectAllOptions('exercise-' + type);
            }

            function clearAllExerciseOptions(type) {
                clearAllOptions('exercise-' + type);
            }

            function addSelectedExerciseItem(type, id) {
                const items = type === 'courses' ? allExerciseCourses : allExerciseCategories;
                const selectedItems = type === 'courses' ? window.selectedExerciseCourses : window.selectedExerciseCategories;

                const item = items.find(i => i.id == id);
                if (item && !selectedItems.some(selected => selected.id == id)) {
                    selectedItems.push(item);
                }
            }

            function removeSelectedExerciseItem(type, id) {
                const selectedItems = type === 'courses' ? window.selectedExerciseCourses : window.selectedExerciseCategories;
                const index = selectedItems.findIndex(item => item.id == id);
                if (index > -1) {
                    selectedItems.splice(index, 1);
                }
            }

            function updateExerciseSelectedDisplay(type) {
                const selectedItems = type === 'courses' ? window.selectedExerciseCourses : window.selectedExerciseCategories;
                const container = $(`#selected-exercise-${type}`);

                container.empty();

                if (selectedItems.length === 0) {
                    container.hide();
                    return;
                }

                container.show();

                selectedItems.forEach(item => {
                    const title = item.title || item.name;
                    const tag = $(`
                    <span class="selected-tag" data-id="${item.id}" data-type="exercise-${type}">
                        ${title}
                        <span class="remove-tag" data-id="${item.id}" data-type="exercise-${type}">×</span>
                    </span>
                `);
                    container.append(tag);
                });

                // Add remove handlers
                container.find('.remove-tag').on('click', function() {
                    const id = $(this).data('id');
                    const exerciseType = type;
                    removeSelectedExerciseItem(exerciseType, id);
                    updateExerciseSelectedDisplay(exerciseType);
                    updateExerciseHeaderText(exerciseType);
                    // Update checkbox in dropdown
                    $(`#exercise-${exerciseType}-options .multi-select-option[data-id="${id}"]`)
                        .removeClass('selected')
                        .find('input[type="checkbox"]')
                        .prop('checked', false);
                });
            }

            function updateExerciseHeaderText(type) {
                const selectedItems = type === 'courses' ? window.selectedExerciseCourses : window.selectedExerciseCategories;
                const header = $(`#exercise-${type}-select-header .placeholder`);

                if (selectedItems.length === 0) {
                    header.text(type === 'courses' ?
                        '-- Sélectionner des cours --' :
                        '-- Sélectionner des catégories --');
                } else if (selectedItems.length === 1) {
                    const title = selectedItems[0].title || selectedItems[0].name;
                    header.text(title);
                } else {
                    header.text(`${selectedItems.length} éléments sélectionnés`);
                }
            }

            // Initialize everything
            initMultiSelect();
            loadCourses();
            loadCategories();

            // Tab switching functionality
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();

                // Remove active class from all tabs and content
                $('.nav-tab').removeClass('nav-tab-active');
                $('.tab-content').removeClass('active');

                // Add active class to clicked tab
                $(this).addClass('nav-tab-active');

                // Show corresponding content
                const targetTab = $(this).data('tab');
                $('#' + targetTab).addClass('active');

                // Initialize multi-select for exercise tab if needed
                if (targetTab === 'exercise-tab' && !window.exerciseMultiSelectInitialized) {
                    initExerciseMultiSelect();
                    loadExerciseCourses();
                    loadExerciseCategories();
                    window.exerciseMultiSelectInitialized = true;
                }
            });

            // Initialize exercise multi-select (similar to quiz multi-select)
            function initExerciseMultiSelect() {
                // Exercise courses multi-select
                $('#exercise-courses-select-header').on('click', function() {
                    $('#exercise-courses-dropdown').toggle();
                });

                // Exercise categories multi-select  
                $('#exercise-categories-select-header').on('click', function() {
                    $('#exercise-categories-dropdown').toggle();
                });

                // Search functionality for exercise courses
                $('#exercise-courses-search').on('input', function() {
                    const searchTerm = $(this).val().toLowerCase();
                    filterOptions('exercise-courses', searchTerm);
                });

                // Search functionality for exercise categories
                $('#exercise-categories-search').on('input', function() {
                    const searchTerm = $(this).val().toLowerCase();
                    filterOptions('exercise-categories', searchTerm);
                });

                // Select/Clear all buttons for exercises
                $('.select-all-btn[data-target="exercise-courses"]').on('click', function() {
                    selectAllOptions('exercise-courses');
                });

                $('.clear-all-btn[data-target="exercise-courses"]').on('click', function() {
                    clearAllOptions('exercise-courses');
                });

                $('.select-all-btn[data-target="exercise-categories"]').on('click', function() {
                    selectAllOptions('exercise-categories');
                });

                $('.clear-all-btn[data-target="exercise-categories"]').on('click', function() {
                    clearAllOptions('exercise-categories');
                });
            }

            // Load courses for exercise tab
            function loadExerciseCourses() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_courses',
                        nonce: '<?php echo wp_create_nonce('quiz_generator_action'); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            allExerciseCourses = response.data;
                            renderOptions('exercise-courses', allExerciseCourses);
                            console.log('Exercise courses loaded successfully:', response.data.length, 'courses');
                        } else {
                            $('#exercise-courses-options').html('<div class="error-message">Erreur lors du chargement des cours</div>');
                            console.error('Failed to load exercise courses:', response);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#exercise-courses-options').html('<div class="error-message">Erreur lors du chargement des cours</div>');
                        console.error('AJAX error loading exercise courses:', error);
                    }
                });
            }

            // Load categories for exercise tab
            function loadExerciseCategories() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_categories',
                        nonce: '<?php echo wp_create_nonce('quiz_generator_action'); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            allExerciseCategories = response.data;
                            renderOptions('exercise-categories', allExerciseCategories);
                            console.log('Exercise categories loaded successfully:', response.data.length, 'categories');
                        } else {
                            $('#exercise-categories-options').html('<div class="error-message">Erreur lors du chargement des catégories</div>');
                            console.error('Failed to load exercise categories:', response);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#exercise-categories-options').html('<div class="error-message">Erreur lors du chargement des catégories</div>');
                        console.error('AJAX error loading exercise categories:', error);
                    }
                });
            }

            // Handle exercise form submission
            $('#exercise-generator-form').on('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('action', 'generate_practical_exercise');

                // Add selected courses and categories
                const selectedCourses = getSelectedItems('exercise-courses');
                const selectedCategories = getSelectedItems('exercise-categories');

                formData.append('selected_courses', JSON.stringify(selectedCourses));
                formData.append('selected_categories', JSON.stringify(selectedCategories));

                // Show generation modal
                $('#exercise-generation-modal').show();

                // Start generation process
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            // Handle successful generation
                            window.location.href = response.data.redirect_url || 'edit.php?post_type=lp_course';
                        } else {
                            alert('Erreur lors de la génération: ' + (response.data || 'Erreur inconnue'));
                            $('#exercise-generation-modal').hide();
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Erreur de connexion lors de la génération');
                        $('#exercise-generation-modal').hide();
                        console.error('Exercise generation error:', error);
                    }
                });
            });
        });
    </script>

    <!-- Exercise Generation Modal -->
    <div id="exercise-generation-modal" class="generation-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="dashicons dashicons-admin-tools"></i> Génération de l'exercice pratique en cours...</h3>
            </div>
            <div class="modal-body">
                <div class="generation-progress">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <div class="progress-steps">
                        <div class="step active">
                            <i class="dashicons dashicons-admin-tools"></i>
                            <span>Analyse du contenu des cours</span>
                        </div>
                        <div class="step">
                            <i class="dashicons dashicons-edit"></i>
                            <span>Génération des étapes pratiques</span>
                        </div>
                        <div class="step">
                            <i class="dashicons dashicons-admin-post"></i>
                            <span>Création du cours LearnPress</span>
                        </div>
                        <div class="step">
                            <i class="dashicons dashicons-yes-alt"></i>
                            <span>Finalisation</span>
                        </div>
                    </div>
                </div>
                <div class="generation-message">
                    <p>Veuillez patienter pendant que l'IA génère votre exercice pratique personnalisé...</p>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Tab Content Visibility */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .generation-modal .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            max-width: 600px;
            margin: 50px auto;
        }

        .generation-modal .modal-header h3 {
            color: #2271b1;
            margin: 0 0 20px 0;
            font-size: 18px;
        }

        .generation-modal .modal-header h3 i {
            margin-right: 8px;
            color: #72aee6;
        }

        .generation-modal .progress-steps {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            gap: 10px;
        }

        .generation-modal .step {
            text-align: center;
            flex: 1;
            padding: 10px;
            color: #666;
            font-size: 12px;
        }

        .generation-modal .step i {
            display: block;
            font-size: 20px;
            margin-bottom: 5px;
            color: #ddd;
        }

        .generation-modal .step.active {
            color: #2271b1;
        }

        .generation-modal .step.active i {
            color: #72aee6;
        }

        .generation-modal .generation-message {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
    </style>

</div>