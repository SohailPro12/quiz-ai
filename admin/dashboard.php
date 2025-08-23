<?php

/**
 * Dashboard Page for Quiz IA Pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function quiz_ai_pro_dashboard_page()
{
    // Check user permissions
    if (!current_user_can('edit_posts')) {
        wp_die(__('Vous n\'avez pas les permissions suffisantes pour accéder à cette page.'));
    }

    // Check database status first
    $table_check = quiz_ai_pro_check_all_tables();
    $has_db_issues = !$table_check['all_exist'];

    // Only get statistics if database is OK
    $stats = null;
    $recent_activities = [];

    if (!$has_db_issues) {
        try {
            $stats = quiz_ai_pro_get_dashboard_stats();
            $recent_activities = quiz_ai_pro_get_recent_activities(10);
        } catch (Exception $e) {
            $has_db_issues = true;
            error_log('Quiz IA Pro Dashboard Error: ' . $e->getMessage());
        }
    }

?>
    <div class="wrap quiz-ai-pro-dashboard">
        <h1 class="wp-heading-inline">
            🧠 Quiz IA Pro - Tableau de Bord
        </h1>

        <p class="description">
            Plateforme intelligente de génération de quiz et cas pratiques avec IA.
            Gérez vos contenus, créez des évaluations automatiquement et suivez les performances de vos étudiants.
        </p>

        <?php if ($has_db_issues): ?>
            <!-- Database Error Notice -->
            <div class="notice notice-error" style="margin: 20px 0; padding: 15px;">
                <h3>⚠️ Problème de Base de Données Détecté</h3>
                <p><strong>Certaines tables de la base de données sont manquantes :</strong></p>
                <ul style="margin-left: 20px;">
                    <?php foreach ($table_check['missing'] as $missing_table): ?>
                        <li><code><?php echo esc_html($missing_table); ?></code></li>
                    <?php endforeach; ?>
                </ul>
                <p>
                    <button id="recreate-tables-btn" class="button button-primary button-large" type="button">
                        🔧 Recréer les Tables de la Base de Données
                    </button>
                </p>
                <p style="color: #666; font-style: italic;">
                    Cette action va recréer toutes les tables manquantes et réinsérer les données d'exemple.
                    Vos données existantes seront préservées.
                </p>
            </div>
        <?php endif; ?>

        <!-- Quick Stats Cards -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $has_db_issues ? '⚠️' : $stats['total_contents']; ?></div>
                    <div class="stat-label">Contenus Sources</div>
                    <div class="stat-change"><?php echo $has_db_issues ? 'Base de données à réparer' : '+' . $stats['new_contents_this_month'] . ' ce mois'; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🧠</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $has_db_issues ? '⚠️' : $stats['total_quizzes']; ?></div>
                    <div class="stat-label">Quiz Créés</div>
                    <div class="stat-change"><?php echo $has_db_issues ? 'Base de données à réparer' : $stats['ai_generated_percentage'] . '% générés par IA'; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $has_db_issues ? '⚠️' : $stats['total_students']; ?></div>
                    <div class="stat-label">Étudiants Actifs</div>
                    <div class="stat-change"><?php echo $has_db_issues ? 'Base de données à réparer' : '+' . $stats['new_students_this_week'] . ' cette semaine'; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $has_db_issues ? '⚠️' : $stats['total_attempts']; ?></div>
                    <div class="stat-label">Quiz Complétés</div>
                    <div class="stat-change"><?php echo $has_db_issues ? 'Base de données à réparer' : $stats['average_score'] . '% score moyen'; ?></div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-actions">
            <h2>⚡ Actions Rapides</h2>
            <div class="action-grid">
                <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-generate'); ?>" class="action-card primary">
                    <div class="action-icon">🤖</div>
                    <div class="action-content">
                        <h3>Générer Quiz IA</h3>
                        <p>Créez un quiz automatiquement à partir de vos contenus</p>
                    </div>
                </a>

                <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-list'); ?>" class="action-card">
                    <div class="action-icon">📋</div>
                    <div class="action-content">
                        <h3>Voir les Quiz</h3>
                        <p>Consultez et gérez tous vos quiz créés</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- API Configuration Section -->
        <div class="dashboard-api-config">
            <h2>🔑 Configuration API Gemini</h2>
            <div class="api-config-card">
                <div class="api-config-content">
                    <div class="api-status" id="api-status">
                        <div class="status-icon">⚠️</div>
                        <div class="status-text">Vérification de la clé API...</div>
                    </div>

                    <div class="api-form">
                        <div class="form-group">
                            <label for="gemini-api-key">Clé API Gemini:</label>
                            <div class="api-key-input-group">
                                <input type="password"
                                    id="gemini-api-key"
                                    placeholder="Entrez votre clé API Gemini"
                                    class="api-key-input">
                                <button type="button" id="toggle-api-key" class="toggle-btn">👁️</button>
                                <button type="button" id="save-api-key" class="save-btn">Sauvegarder</button>
                            </div>
                            <p class="api-help">
                                Obtenez votre clé API sur
                                <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Debug Section (for troubleshooting) -->
        <div class="dashboard-debug-section" style="margin: 30px 0;">
            <h2>🔧 Debug & Diagnostic</h2>
            <div class="debug-card" style="background: #fff; border: 1px solid #e1e8ed; border-radius: 8px; padding: 25px;">
                <p>Si vous rencontrez des erreurs, utilisez cet outil de diagnostic:</p>
                <button type="button" id="debug-tables" class="button button-secondary">
                    🔍 Vérifier Configuration
                </button>
                <button type="button" id="force-update-tables" class="button button-primary" style="margin-left: 10px;">
                    🔧 Forcer Mise à Jour Tables
                </button>
                <div id="debug-results" style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 4px; display: none;">
                    <pre id="debug-output" style="white-space: pre-wrap; font-family: monospace; font-size: 12px;"></pre>
                </div>
            </div>
        </div>

        <!-- Performance Overview -->
        <div class="dashboard-row">
            <div class="dashboard-col">
                <div class="dashboard-widget">
                    <h3>📈 Performances Récentes</h3>
                    <div class="performance-chart">
                        <canvas id="performanceChart" width="400" height="200"></canvas>
                    </div>
                    <div class="performance-summary">
                        <div class="summary-item">
                            <span class="label">Quiz les plus populaires:</span>
                            <span class="value"><?php echo $stats['top_quiz_title']; ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Taux de réussite moyen:</span>
                            <span class="value"><?php echo $stats['success_rate']; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-col">
                <div class="dashboard-widget">
                    <h3>🎯 Quiz en Attente de Validation</h3>
                    <?php
                    $pending_quizzes = quiz_ai_pro_get_pending_quizzes(5);
                    if (!empty($pending_quizzes)):
                    ?>
                        <div class="pending-list">
                            <?php foreach ($pending_quizzes as $quiz): ?>
                                <div class="pending-item">
                                    <div class="pending-info">
                                        <strong><?php echo esc_html($quiz->title); ?></strong>
                                        <div class="pending-meta">
                                            <?php if ($quiz->ai_generated): ?>
                                                <span class="badge ai">🤖 IA</span>
                                            <?php else: ?>
                                                <span class="badge manual">✏️ Manuel</span>
                                            <?php endif; ?>
                                            <span class="date"><?php echo human_time_diff(strtotime($quiz->created_at)); ?> ago</span>
                                        </div>
                                    </div>
                                    <div class="pending-actions">
                                        <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-list'); ?>"
                                            class="button button-small">Valider</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="widget-footer">
                            <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-list'); ?>"
                                class="button">Voir tous les quiz en attente</a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>✅ Aucun quiz en attente de validation</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="dashboard-widget">
            <h3>📋 Activités Récentes</h3>
            <?php if (!empty($recent_activities)): ?>
                <div class="activities-list">
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon"><?php echo $activity->icon; ?></div>
                            <div class="activity-content">
                                <div class="activity-text"><?php echo $activity->description; ?></div>
                                <div class="activity-time"><?php echo human_time_diff(strtotime($activity->created_at)); ?> ago</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>🎯 Commencez par créer votre premier contenu ou quiz !</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- System Status -->
        <div class="dashboard-widget system-status">
            <h3>⚙️ État du Système</h3>
            <div class="status-grid">
                <div class="status-item">
                    <span class="status-label">Base de données:</span>
                    <span class="status-value <?php echo quiz_ai_pro_check_database() ? 'status-good' : 'status-error'; ?>">
                        <?php echo quiz_ai_pro_check_database() ? '✅ Opérationnelle' : '❌ Erreur'; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">API IA configurée:</span>
                    <span class="status-value <?php echo quiz_ai_pro_check_ai_config() ? 'status-good' : 'status-warning'; ?>">
                        <?php echo quiz_ai_pro_check_ai_config() ? '✅ Configurée' : '⚠️ À configurer'; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">Emails:</span>
                    <span class="status-value <?php echo quiz_ai_pro_check_email_config() ? 'status-good' : 'status-warning'; ?>">
                        <?php echo quiz_ai_pro_check_email_config() ? '✅ Fonctionnels' : '⚠️ À vérifier'; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">Version:</span>
                    <span class="status-value">v<?php echo QUIZ_IA_PRO_VERSION; ?></span>
                </div>
            </div>
            <?php if (!quiz_ai_pro_check_ai_config()): ?>
                <div class="status-notice">
                    <p>⚠️ <strong>Configuration incomplète:</strong>
                        <a href="<?php echo admin_url('admin.php?page=quiz-ai-pro-settings'); ?>">Configurez votre API IA</a>
                        pour commencer à générer des quiz automatiquement.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .quiz-ai-pro-dashboard {
            max-width: 1200px;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #0073aa;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            font-size: 32px;
            margin-right: 15px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #0073aa;
            line-height: 1;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
        }

        .stat-change {
            font-size: 12px;
            color: #00a32a;
            font-weight: 500;
        }

        .dashboard-actions {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .action-card {
            display: flex;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            text-decoration: none;
            border-color: #0073aa;
        }

        .action-card.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        .action-card.secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-color: #f093fb;
        }

        .action-icon {
            font-size: 36px;
            margin-right: 15px;
        }

        .action-content h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
        }

        .action-content p {
            margin: 0;
            font-size: 13px;
            opacity: 0.8;
        }

        .dashboard-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }

        .dashboard-col {
            min-width: 0;
        }

        .dashboard-widget {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin: 20px 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .dashboard-widget h3 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
            color: #333;
        }

        .pending-list {
            margin-bottom: 15px;
        }

        .pending-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .pending-item:last-child {
            border-bottom: none;
        }

        .pending-meta {
            margin-top: 5px;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            margin-right: 10px;
        }

        .badge.ai {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge.manual {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .activities-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            font-size: 20px;
            margin-right: 15px;
            width: 30px;
            text-align: center;
        }

        .activity-text {
            font-size: 14px;
            color: #333;
        }

        .activity-time {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .status-label {
            font-weight: 500;
        }

        .status-value.status-good {
            color: #00a32a;
        }

        .status-value.status-warning {
            color: #ff8c00;
        }

        .status-value.status-error {
            color: #d63638;
        }

        .status-notice {
            margin-top: 15px;
            padding: 15px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .widget-footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        /* API Configuration Styles */
        .dashboard-api-config {
            margin: 30px 0;
        }

        .dashboard-api-config h2 {
            margin-bottom: 20px;
            color: #333;
            font-size: 20px;
            font-weight: 600;
        }

        .api-config-card {
            background: #fff;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .api-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 6px;
            background: #f8f9fa;
            border-left: 4px solid #ffc107;
        }

        .api-status.status-success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }

        .api-status.status-error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }

        .api-status.status-warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }

        .api-form .form-group {
            margin-bottom: 20px;
        }

        .api-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .api-key-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .api-key-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: monospace;
        }

        .api-key-input:focus {
            outline: none;
            border-color: #007cba;
            box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.1);
        }

        .toggle-btn,
        .save-btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .toggle-btn {
            background: #f8f9fa;
            border: 1px solid #ddd;
        }

        .toggle-btn:hover {
            background: #e9ecef;
        }

        .save-btn {
            background: #007cba;
            color: white;
            font-weight: 600;
        }

        .save-btn:hover {
            background: #005a87;
        }

        .save-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .api-help {
            margin-top: 8px;
            font-size: 13px;
            color: #666;
        }

        .api-help a {
            color: #007cba;
            text-decoration: none;
        }

        .api-help a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .dashboard-row {
                grid-template-columns: 1fr;
            }

            .action-grid {
                grid-template-columns: 1fr;
            }

            .api-key-input-group {
                flex-direction: column;
                align-items: stretch;
            }

            .toggle-btn,
            .save-btn {
                width: 100%;
                margin-top: 5px;
            }
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            // Initialize performance chart if canvas exists
            const canvas = document.getElementById('performanceChart');
            if (canvas) {
                // Simple chart implementation or integrate Chart.js
                const ctx = canvas.getContext('2d');

                // Sample data visualization
                ctx.fillStyle = '#0073aa';
                ctx.fillRect(50, 150, 30, 40);
                ctx.fillRect(100, 130, 30, 60);
                ctx.fillRect(150, 110, 30, 80);
                ctx.fillRect(200, 100, 30, 90);
                ctx.fillRect(250, 90, 30, 100);

                // Add labels
                ctx.fillStyle = '#666';
                ctx.font = '12px Arial';
                ctx.fillText('Jan', 55, 175);
                ctx.fillText('Feb', 105, 175);
                ctx.fillText('Mar', 155, 175);
                ctx.fillText('Apr', 205, 175);
                ctx.fillText('Mai', 255, 175);
            }
        });

        // API Key Management
        document.addEventListener('DOMContentLoaded', function() {
            const apiKeyInput = document.getElementById('gemini-api-key');
            const toggleBtn = document.getElementById('toggle-api-key');
            const saveBtn = document.getElementById('save-api-key');
            const apiStatus = document.getElementById('api-status');

            // Load existing API key
            loadApiKey();

            // Toggle password visibility
            toggleBtn.addEventListener('click', function() {
                if (apiKeyInput.type === 'password') {
                    apiKeyInput.type = 'text';
                    toggleBtn.textContent = '🙈';
                } else {
                    apiKeyInput.type = 'password';
                    toggleBtn.textContent = '👁️';
                }
            });

            // Save API key
            saveBtn.addEventListener('click', function() {
                const apiKey = apiKeyInput.value.trim();

                if (!apiKey) {
                    updateApiStatus('error', 'Veuillez entrer une clé API');
                    return;
                }

                saveBtn.disabled = true;
                saveBtn.textContent = 'Sauvegarde...';

                const formData = new FormData();
                formData.append('action', 'save_gemini_api_key');
                formData.append('api_key', apiKey);
                formData.append('nonce', '<?php echo wp_create_nonce('quiz_generator_action'); ?>');

                fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateApiStatus('success', 'Clé API sauvegardée avec succès');
                            loadApiKey(); // Reload to show masked key
                        } else {
                            updateApiStatus('error', data.data || 'Erreur lors de la sauvegarde');
                        }
                    })
                    .catch(error => {
                        updateApiStatus('error', 'Erreur de connexion');
                    })
                    .finally(() => {
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Sauvegarder';
                    });
            });

            function loadApiKey() {
                const formData = new FormData();
                formData.append('action', 'get_gemini_api_key');
                formData.append('nonce', '<?php echo wp_create_nonce('quiz_generator_action'); ?>');

                fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (data.data.has_key) {
                                apiKeyInput.value = data.data.api_key;
                                apiKeyInput.placeholder = 'Clé API configurée (masquée)';
                                updateApiStatus('success', 'Clé API configurée et prête à utiliser');
                            } else {
                                updateApiStatus('warning', 'Aucune clé API configurée');
                            }
                        } else {
                            updateApiStatus('error', 'Erreur lors du chargement de la clé API');
                        }
                    })
                    .catch(error => {
                        updateApiStatus('error', 'Erreur de connexion');
                    });
            }

            function updateApiStatus(type, message) {
                const statusIcon = apiStatus.querySelector('.status-icon');
                const statusText = apiStatus.querySelector('.status-text');

                // Remove existing classes
                apiStatus.classList.remove('status-success', 'status-error', 'status-warning');

                // Add new class and update content
                apiStatus.classList.add('status-' + type);
                statusText.textContent = message;

                switch (type) {
                    case 'success':
                        statusIcon.textContent = '✅';
                        break;
                    case 'error':
                        statusIcon.textContent = '❌';
                        break;
                    case 'warning':
                        statusIcon.textContent = '⚠️';
                        break;
                }
            }
        });

        // Debug button functionality
        document.getElementById('debug-tables').addEventListener('click', function() {
            const debugBtn = this;
            const debugResults = document.getElementById('debug-results');
            const debugOutput = document.getElementById('debug-output');

            debugBtn.disabled = true;
            debugBtn.textContent = '🔄 Vérification...';

            fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'debug_quiz_tables',
                        nonce: '<?php echo wp_create_nonce('quiz_generator_action'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    debugResults.style.display = 'block';
                    if (data.success) {
                        debugOutput.textContent = JSON.stringify(data.data, null, 2);
                    } else {
                        debugOutput.textContent = 'Erreur: ' + (data.data || 'Erreur inconnue');
                    }
                })
                .catch(error => {
                    debugResults.style.display = 'block';
                    debugOutput.textContent = 'Erreur de connexion: ' + error.message;
                })
                .finally(() => {
                    debugBtn.disabled = false;
                    debugBtn.textContent = '🔍 Vérifier Configuration';
                });
        });

        // Force update tables button
        document.getElementById('force-update-tables').addEventListener('click', function() {
            const updateBtn = this;
            const debugResults = document.getElementById('debug-results');
            const debugOutput = document.getElementById('debug-output');

            updateBtn.disabled = true;
            updateBtn.textContent = '🔧 Mise à jour en cours...';
            debugResults.style.display = 'block';
            debugOutput.textContent = 'Mise à jour des tables en cours...';

            fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'quiz_ai_force_update_tables',
                        nonce: '<?php echo wp_create_nonce('quiz_generator_action'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        debugOutput.textContent = 'Mise à jour réussie!\n\n' + JSON.stringify(data.data, null, 2);
                    } else {
                        debugOutput.textContent = 'Erreur: ' + (data.data || 'Erreur inconnue');
                    }
                })
                .catch(error => {
                    debugOutput.textContent = 'Erreur de connexion: ' + error.message;
                })
                .finally(() => {
                    updateBtn.disabled = false;
                    updateBtn.textContent = '🔧 Forcer Mise à Jour Tables';
                });
        });

        // Handle table recreation via AJAX
        jQuery('#recreate-tables-btn').on('click', function(e) {
            e.preventDefault();

            if (!confirm('Êtes-vous sûr de vouloir recréer les tables de la base de données ?')) {
                return;
            }

            const $button = jQuery(this);
            const originalText = $button.text();

            // Show loading state
            $button.prop('disabled', true).text('🔄 Création en cours...');

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'quiz_ai_force_update_tables',
                    nonce: '<?php echo wp_create_nonce('quiz_ai_pro_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        jQuery('.notice-error').hide();
                        jQuery('<div class="notice notice-success is-dismissible"><p><strong>Quiz IA Pro:</strong> Tables de base de données créées avec succès!</p></div>')
                            .insertAfter('.wrap h1');

                        // Remove the button since tables are now created
                        $button.closest('.notice').fadeOut();
                    } else {
                        alert('Erreur: ' + (response.data || 'Une erreur inconnue est survenue'));
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Erreur AJAX: ' + error);
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    </script>
<?php
}

// Call the function when this file is included
quiz_ai_pro_dashboard_page();
