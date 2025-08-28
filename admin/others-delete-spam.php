<?php
if (!defined('ABSPATH')) exit;

function quiz_ai_delete_spam_emails_page()
{
    global $wpdb;

    $trusted_table = $wpdb->prefix . 'quiz_ia_trusted_domains';

    // ------------------------
    // Handle add trusted domain
    // ------------------------
    if (isset($_POST['quiz_ai_add_trusted_domain']) && !empty($_POST['trusted_domain'])) {
        $domain = strtolower(trim($_POST['trusted_domain']));
        if (filter_var('test@' . $domain, FILTER_VALIDATE_EMAIL)) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $trusted_table WHERE domain = %s", $domain));
            if (!$exists) {
                $wpdb->insert($trusted_table, ['domain' => $domain]);
                echo '<div class="notice notice-success" style="margin-top:12px;"><p>Domain <strong>' . esc_html($domain) . '</strong> added.</p></div>';
            } else {
                echo '<div class="notice notice-warning" style="margin-top:12px;"><p>Domain already exists.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error" style="margin-top:12px;"><p>Invalid domain format.</p></div>';
        }
    }

    // ------------------------
    // Handle delete trusted domain
    // ------------------------
    if (isset($_POST['quiz_ai_delete_trusted_domain']) && !empty($_POST['delete_domain'])) {
        $domain = strtolower(trim($_POST['delete_domain']));
        $wpdb->delete($trusted_table, ['domain' => $domain]);
        echo '<div class="notice notice-success" style="margin-top:12px;"><p>Domain <strong>' . esc_html($domain) . '</strong> removed.</p></div>';
    }

    // ------------------------
    // Fetch all trusted domains
    // ------------------------
    $trusted_domains = [];
    if ($wpdb->get_var("SHOW TABLES LIKE '$trusted_table'")) {
        $rows = $wpdb->get_results("SELECT domain FROM $trusted_table ORDER BY id ASC");
        foreach ($rows as $row) {
            $trusted_domains[] = strtolower(trim($row->domain));
        }
    }

    // ------------------------
    // Trusted domains UI
    // ------------------------
    echo '<div class="wrap" style="max-width:700px;margin:40px auto;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.08);padding:40px;">';
    echo '<h1 style="margin-bottom:24px;font-size:2rem;color:#0073aa;display:flex;align-items:center;gap:12px;">Trusted Domains</h1>';

    // Add domain form
    echo '<form method="post" style="display:flex;gap:10px;align-items:center;margin-bottom:20px;">';
    echo '<input type="text" name="trusted_domain" placeholder="Add domain (e.g. gmail.com)" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;flex:1;">';
    echo '<button type="submit" name="quiz_ai_add_trusted_domain" class="button button-primary" style="height:36px;padding:0 16px;border-radius:6px;">+</button>';
    echo '</form>';

    // List of domains
    if ($trusted_domains) {
        echo '<ul style="list-style:none;margin:0;padding:0;max-height:200px;overflow:auto;">';
        foreach ($trusted_domains as $domain) {
            echo '<li style="margin-bottom:6px;display:flex;align-items:center;justify-content:space-between;">';
            echo '<span style="font-weight:600;">' . esc_html($domain) . '</span>';
            echo '<form method="post" style="margin:0;"><input type="hidden" name="delete_domain" value="' . esc_attr($domain) . '">';
            echo '<button type="submit" name="quiz_ai_delete_trusted_domain" class="button" style="background:#eee;color:#333;padding:2px 10px;border-radius:4px;font-size:0.9rem;">Delete</button></form>';
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p style="color:#888;">No trusted domains yet.</p>';
    }
    // DO NOT render any spam users HTML here. Only inside the spam section below.
    echo '</div>'; // close wrap for trusted domains

    // ------------------------
    // Spam users detection section (always at bottom)
    // ------------------------
    echo '<div id="quiz-ai-spam-users-section" style="max-width:700px;margin:40px auto;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.08);padding:40px;margin-top:40px;">';
    echo '<h1 style="font-size:2rem;color:#dc3545;display:flex;align-items:center;gap:12px;">';
    echo '<span style="display:inline-block;vertical-align:middle;">';
    echo '<svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">';
    echo '<rect width="32" height="32" rx="8" fill="#ffeaea"/>';
    echo '<path d="M8 24V10a2 2 0 012-2h12a2 2 0 012 2v14" stroke="#dc3545" stroke-width="2"/>';
    echo '<path d="M12 14h8M12 18h8" stroke="#dc3545" stroke-width="2"/></svg></span>Delete Spam Emails</h1>';
    echo '<form id="quiz-ai-detect-spam-form" method="post" style="margin-top:16px;">';
    wp_nonce_field('quiz_ai_delete_spam_emails_action');
    echo '<button type="submit" name="quiz_ai_start_detecting" class="button button-danger" style="height:40px;padding:0 24px;font-size:1rem;border-radius:6px;background:#dc3545;color:#fff;">Start Detecting Spam Emails</button>';
    echo '</form>';
    echo '<div id="quiz-ai-spam-users-list"></div>';
    echo '<div id="quiz-ai-spam-delete-progress" style="margin-top:24px;"></div>';
    echo '</div>';

    // If detection requested, output spam users as JSON for JS
    if (isset($_POST['quiz_ai_start_detecting']) && check_admin_referer('quiz_ai_delete_spam_emails_action')) {
        $subscribed_domains = [];
        $enrolled_users = $wpdb->get_results("
            SELECT DISTINCT u.user_email
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->prefix}learnpress_user_items lpu
            ON u.ID = lpu.user_id
            WHERE lpu.item_type = 'lp_course'
        ");
        foreach ($enrolled_users as $row) {
            $parts = explode('@', strtolower(trim($row->user_email)));
            if (count($parts) === 2) $subscribed_domains[] = $parts[1];
        }
        $allowed_domains = array_unique(array_merge($trusted_domains, $subscribed_domains));
        $users = $wpdb->get_results("SELECT ID, user_email FROM {$wpdb->users}");
        $spam_users = [];
        foreach ($users as $user) {
            $parts = explode('@', strtolower(trim($user->user_email)));
            $domain = count($parts) === 2 ? $parts[1] : '';
            if (!in_array($domain, $allowed_domains)) $spam_users[] = $user;
        }
        // Set spam users JS variable and detection flag, then trigger custom event for rendering
        echo '<script>window.quizAiSpamUsers = ' . json_encode($spam_users) . '; window.quizAiSpamDetectionTriggered = true; window.dispatchEvent(new Event("quizAiSpamUsersUpdated"));</script>';
    }

    // ------------------------
    // Delete spam users
    // ------------------------
    if (isset($_POST['quiz_ai_delete_spam_emails']) && check_admin_referer('quiz_ai_delete_spam_emails_action')) {
        $deleted_count = 0;
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($spam_users as $user) {
            if (wp_delete_user($user->ID)) $deleted_count++;
        }
        echo '<div class="notice notice-success" style="margin-top:24px;"><p>' . intval($deleted_count) . ' spam users deleted.</p></div>';
    }

    echo '<script>setTimeout(function(){ if(window.renderSpamUsersList) window.renderSpamUsersList(); }, 100);</script>';
    echo '</div>'; // wrap
}
