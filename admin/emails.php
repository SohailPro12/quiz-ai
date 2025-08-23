<?php

/**
 * Email Management Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions
// Handle form submissions
if (isset($_POST['delete_subscriber_email'])) {
    $delete_email = sanitize_email($_POST['delete_subscriber_email']);
    if (is_email($delete_email)) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quiz_ia_email_preferences';
        $deleted = $wpdb->delete($table_name, ['user_email' => $delete_email]);
        if ($deleted) {
            $message = 'Abonné supprimé : ' . esc_html($delete_email);
            $message_type = 'success';
        } else {
            $message = 'Erreur lors de la suppression de l\'abonné.';
            $message_type = 'error';
        }
    }
}

if (isset($_POST['action'])) {
    $action = sanitize_text_field($_POST['action']);

    if ($action === 'send_test_email' && isset($_POST['test_email'])) {
        $test_email = sanitize_email($_POST['test_email']);
        if (is_email($test_email)) {
            // Send test quiz result email
            $test_quiz_data = (object)[
                'id' => 999,
                'title' => 'Quiz de Test'
            ];
            $test_result_data = [
                'user_name' => 'Utilisateur Test',
                'score' => 8,
                'total' => 10,
                'percentage' => 80,
                'details' => [
                    ['question_number' => 1, 'correct' => true, 'points' => 1, 'max_points' => 1],
                    ['question_number' => 2, 'correct' => false, 'points' => 0, 'max_points' => 1],
                    ['question_number' => 3, 'correct' => true, 'points' => 1, 'max_points' => 1]
                ]
            ];

            if (quiz_ai_pro_send_quiz_result_email($test_email, $test_quiz_data, $test_result_data)) {
                $message = 'Email de test envoyé avec succès à ' . $test_email;
                $message_type = 'success';
            } else {
                $message = 'Erreur lors de l\'envoi de l\'email de test';
                $message_type = 'error';
            }
        }
    }

    if ($action === 'send_test_new_quiz' && isset($_POST['test_email'])) {
        $test_email = sanitize_email($_POST['test_email']);
        if (is_email($test_email)) {
            // First save test preferences
            quiz_ai_pro_save_email_preferences($test_email, [
                'user_name' => 'Utilisateur Test',
                'receive_new_quiz_alerts' => true
            ]);

            // Send test new quiz alert
            $test_quiz_data = [
                'id' => 999,
                'title' => 'Nouveau Quiz de Test',
                'description' => 'Ceci est un quiz de test pour vérifier les notifications email.'
            ];

            if (quiz_ai_pro_send_new_quiz_alert($test_quiz_data)) {
                $message = 'Email de nouveau quiz envoyé avec succès';
                $message_type = 'success';
            } else {
                $message = 'Erreur lors de l\'envoi de l\'email de nouveau quiz';
                $message_type = 'error';
            }
        }
    }
}

// Get email statistics
$email_stats = quiz_ai_pro_get_email_stats();

?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-email-alt" style="margin-right: 8px;"></span>
        Notifications Email
    </h1>

    <?php if (isset($message)): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="quiz-admin-grid">
        <!-- Email Statistics Card -->
        <div class="quiz-admin-card">
            <div class="quiz-admin-card-header">
                <h2>
                    <span class="dashicons dashicons-chart-bar"></span>
                    Statistiques des Emails
                </h2>
            </div>
            <div class="quiz-admin-card-body">
                <div class="quiz-stats-row">
                    <div class="quiz-stat-item">
                        <div class="quiz-stat-number"><?php echo esc_html($email_stats['total_subscribers'] ?? 0); ?></div>
                        <div class="quiz-stat-label">Total Abonnés</div>
                    </div>
                    <div class="quiz-stat-item">
                        <div class="quiz-stat-number"><?php echo esc_html($email_stats['quiz_result_subscribers'] ?? 0); ?></div>
                        <div class="quiz-stat-label">Résultats Quiz</div>
                    </div>
                    <div class="quiz-stat-item">
                        <div class="quiz-stat-number"><?php echo esc_html($email_stats['new_quiz_subscribers'] ?? 0); ?></div>
                        <div class="quiz-stat-label">Nouveaux Quiz</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Testing Card -->
        <div class="quiz-admin-card">
            <div class="quiz-admin-card-header">
                <h2>
                    <span class="dashicons dashicons-email"></span>
                    Test des Emails
                </h2>
            </div>
            <div class="quiz-admin-card-body">
                <p>Testez les templates d'emails en envoyant des exemples à une adresse email.</p>

                <form method="post" style="margin-bottom: 20px;">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="test_email">Email de Test</label>
                            </th>
                            <td>
                                <input type="email" id="test_email" name="test_email" class="regular-text"
                                    placeholder="test@example.com" required>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="action" value="send_test_email" class="button button-secondary">
                            <span class="dashicons dashicons-email-alt"></span>
                            Envoyer Test Résultat Quiz
                        </button>
                        <button type="submit" name="action" value="send_test_new_quiz" class="button button-secondary"
                            style="margin-left: 10px;">
                            <span class="dashicons dashicons-megaphone"></span>
                            Envoyer Test Nouveau Quiz
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <!-- Email Subscribers List -->
        <div class="quiz-admin-card full-width">
            <div class="quiz-admin-card-header">
                <h2>
                    <span class="dashicons dashicons-groups"></span>
                    Liste des Abonnés
                </h2>
            </div>
            <div class="quiz-admin-card-body">
                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'quiz_ia_email_preferences';

                $subscribers = $wpdb->get_results("
                    SELECT user_email, user_name, receive_quiz_results, receive_new_quiz_alerts, created_at
                    FROM {$table_name}
                    ORDER BY created_at DESC
                    LIMIT 50
                ");

                if ($subscribers): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Nom</th>
                                <th>Résultats Quiz</th>
                                <th>Nouveaux Quiz</th>
                                <th>Date d'inscription</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscribers as $subscriber): ?>
                                <tr>
                                    <td><?php echo esc_html($subscriber->user_email); ?></td>
                                    <td><?php echo esc_html($subscriber->user_name ?: '—'); ?></td>
                                    <td>
                                        <span class="<?php echo $subscriber->receive_quiz_results ? 'status-enabled' : 'status-disabled'; ?>">
                                            <?php echo $subscriber->receive_quiz_results ? '✓ Activé' : '✗ Désactivé'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="<?php echo $subscriber->receive_new_quiz_alerts ? 'status-enabled' : 'status-disabled'; ?>">
                                            <?php echo $subscriber->receive_new_quiz_alerts ? '✓ Activé' : '✗ Désactivé'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html(date('d/m/Y H:i', strtotime($subscriber->created_at))); ?></td>
                                    <td>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cet abonné ?');">
                                            <input type="hidden" name="delete_subscriber_email" value="<?php echo esc_attr($subscriber->user_email); ?>">
                                            <button type="submit" class="button button-danger" title="Supprimer">
                                                <span class="dashicons dashicons-trash"></span> Supprimer
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data-message">
                        <span class="dashicons dashicons-info"></span>
                        <p>Aucun abonné email trouvé.</p>
                        <p><small>Les préférences email s'afficheront ici une fois que des utilisateurs auront rempli le formulaire de contact avec les options email cochées.</small></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Email Settings Card -->
        <div class="quiz-admin-card full-width">
            <div class="quiz-admin-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-settings"></span>
                    Configuration Email
                </h2>
            </div>
            <div class="quiz-admin-card-body">
                <h3>Templates d'Email</h3>
                <p>Les templates d'email sont automatiquement générés et incluent :</p>
                <ul style="list-style: disc; margin-left: 30px;">
                    <li><strong>Email de résultats :</strong> Contient le score, le pourcentage, les détails par question et les recommandations de cours</li>
                    <li><strong>Email nouveau quiz :</strong> Annonce la publication d'un nouveau quiz avec un lien pour le passer</li>
                    <li><strong>Liens de désinscription :</strong> Chaque email contient un lien sécurisé de désinscription</li>
                </ul>

                <h3>Configuration SMTP</h3>
                <p>Pour améliorer la délivrabilité des emails, nous recommandons d'utiliser un plugin SMTP comme :</p>
                <ul style="list-style: disc; margin-left: 30px;">
                    <li>WP Mail SMTP</li>
                    <li>Easy WP SMTP</li>
                    <li>Post SMTP</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
    .quiz-admin-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-top: 20px;
    }

    .quiz-admin-card.full-width {
        grid-column: 1 / -1;
    }

    .quiz-admin-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
    }

    .quiz-admin-card-header {
        border-bottom: 1px solid #ccd0d4;
        padding: 15px 20px;
        background: #f9f9f9;
    }

    .quiz-admin-card-header h2 {
        margin: 0;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .quiz-admin-card-body {
        padding: 20px;
    }

    .quiz-stats-row {
        display: flex;
        gap: 20px;
        justify-content: space-around;
    }

    .quiz-stat-item {
        text-align: center;
        flex: 1;
    }

    .quiz-stat-number {
        font-size: 32px;
        font-weight: 600;
        color: #0073aa;
        line-height: 1.2;
    }

    .quiz-stat-label {
        font-size: 13px;
        color: #666;
        margin-top: 5px;
    }

    .status-enabled {
        color: #007cba;
        font-weight: 600;
    }

    .status-disabled {
        color: #999;
    }

    .no-data-message {
        text-align: center;
        padding: 40px 20px;
        color: #666;
    }

    .no-data-message .dashicons {
        font-size: 48px;
        opacity: 0.5;
        margin-bottom: 15px;
    }

    @media (max-width: 768px) {
        .quiz-admin-grid {
            grid-template-columns: 1fr;
        }

        .quiz-stats-row {
            flex-direction: column;
            gap: 15px;
        }
    }
</style>