<?php
if (!defined('ABSPATH')) exit;

// Admin page for exporting LearnPress students
function quiz_ai_export_learnpress_students_page()
{
    global $wpdb;

    echo '<div class="wrap" style="max-width:900px;margin:40px auto;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.08);padding:40px;">';

    echo '<h1 style="margin-bottom:24px;font-size:2rem;color:#0073aa;display:flex;align-items:center;gap:12px;">
        <span style="display:inline-block;vertical-align:middle;">
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="8" fill="#e0e0e0"/><path d="M8 24V10a2 2 0 012-2h12a2 2 0 012 2v14" stroke="#0073aa" stroke-width="2"/><path d="M12 14h8M12 18h8" stroke="#0073aa" stroke-width="2"/></svg>
        </span>Export LearnPress Students</h1>';

    // Course select form
    echo '<form method="post" style="margin-bottom:30px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">';
    echo '<label for="course_id" style="font-weight:600;">Select Course:</label>';
    echo '<select name="course_id" id="course_id" style="min-width:260px;padding:8px 12px;border-radius:6px;border:1px solid #ccc;">';

    $courses = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'lp_course' AND post_status = 'publish'");
    foreach ($courses as $course) {
        echo '<option value="' . esc_attr($course->ID) . '">' . esc_html($course->post_title) . '</option>';
    }

    echo '</select>';
    echo '<button type="submit" name="quiz_ai_load_students" class="button button-primary" style="height:40px;padding:0 24px;font-size:1rem;border-radius:6px;">
            View Students</button>';
    echo '<button type="submit" name="quiz_ai_export_lp_students_all" class="button" style="height:40px;padding:0 24px;font-size:1rem;border-radius:6px;background:#e0e0e0;color:#333;">
            Export All Courses</button>';
    echo '</form>';
    // Handle export for all courses
    if (isset($_POST['quiz_ai_export_lp_students_all'])) {
        // Display all students from all courses in a table
        echo '<h2 style="margin-top:20px;color:#333;">All Students in All Courses</h2>';
        echo '<table class="widefat striped" style="margin-top:20px;border-radius:8px;overflow:hidden;">';
        echo '<thead><tr>
                <th>Course</th>
                <th>User ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Registered At</th>
            </tr></thead><tbody>';
        $row_count = 0;
        foreach ($courses as $course) {
            $course_id = $course->ID;
            $course_name = $course->post_title;
            $students = $wpdb->get_results($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}learnpress_user_items WHERE item_id = %d AND item_type = 'lp_course'", $course_id));
            foreach ($students as $student) {
                $user = get_userdata($student->user_id);
                $reg_date = '';
                if ($user) {
                    $user_item = $wpdb->get_row($wpdb->prepare("SELECT start_time FROM {$wpdb->prefix}learnpress_user_items WHERE user_id = %d AND item_id = %d AND item_type = 'lp_course'", $user->ID, $course_id));
                    $reg_date = $user_item ? $user_item->start_time : '-';
                    echo '<tr>
                        <td>' . esc_html($course_name) . '</td>
                        <td>' . esc_html($user->ID) . '</td>
                        <td>' . esc_html($user->user_login) . '</td>
                        <td>' . esc_html($user->user_email) . '</td>
                        <td>' . esc_html($reg_date) . '</td>
                    </tr>';
                    $row_count++;
                }
            }
        }
        if ($row_count === 0) {
            echo '<tr><td colspan="5" style="text-align:center;color:#888;">No students found in any course.</td></tr>';
        }
        echo '</tbody></table>';
        // Export button for all courses
        echo '<button id="quiz-ai-export-all-csv-btn" class="button button-secondary" style="padding:8px 20px;border-radius:6px;">Export All as CSV</button>';
        // Enqueue JS for AJAX export
        echo '<script src="' . plugins_url('assets/js/admin-export-learnpress.js', defined('QUIZ_IA_PRO_PLUGIN_FILE') ? QUIZ_IA_PRO_PLUGIN_FILE : __FILE__) . '"></script>';
    }
    // Handle export all as CSV
    // AJAX handler will be in PHP

    // Show students in table if requested
    if (isset($_POST['quiz_ai_load_students']) && !empty($_POST['course_id'])) {
        $course_id = intval($_POST['course_id']);
        $students = $wpdb->get_results($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}learnpress_user_items WHERE item_id = %d AND item_type = 'lp_course'", $course_id));

        if ($students) {
            $course_obj = get_post($course_id);
            $course_name = $course_obj ? $course_obj->post_title : 'Course';

            echo '<h2 style="margin-top:20px;color:#333;">Students in <em>' . esc_html($course_name) . '</em></h2>';

            echo '<table class="widefat striped" style="margin-top:20px;border-radius:8px;overflow:hidden;">';
            echo '<thead><tr>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Registered At</th>
                </tr></thead><tbody>';

            foreach ($students as $student) {
                $user = get_userdata($student->user_id);
                if ($user) {
                    $user_item = $wpdb->get_row($wpdb->prepare("SELECT start_time FROM {$wpdb->prefix}learnpress_user_items WHERE user_id = %d AND item_id = %d AND item_type = 'lp_course'", $user->ID, $course_id));
                    $reg_date = $user_item ? $user_item->start_time : '-';
                    echo '<tr>
                        <td>' . esc_html($user->ID) . '</td>
                        <td>' . esc_html($user->user_login) . '</td>
                        <td>' . esc_html($user->user_email) . '</td>
                        <td>' . esc_html($reg_date) . '</td>
                    </tr>';
                }
            }

            echo '</tbody></table>';

            // Export button
            echo '<form method="post" style="margin-top:20px;">';
            echo '<input type="hidden" name="course_id" value="' . esc_attr($course_id) . '">';
            echo '<button type="submit" name="quiz_ai_export_lp_students" class="button button-secondary" style="padding:8px 20px;border-radius:6px;">Export as CSV</button>';
            echo '</form>';
        } else {
            echo '<div class="notice notice-warning" style="margin-top:24px;"><p>No students found for this course.</p></div>';
        }
    }

    // Handle export
    if (isset($_POST['quiz_ai_export_lp_students']) && !empty($_POST['course_id'])) {
        $course_id = intval($_POST['course_id']);
        $students = $wpdb->get_results($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}learnpress_user_items WHERE item_id = %d AND item_type = 'lp_course'", $course_id));

        if ($students) {
            $course_obj = get_post($course_id);
            $course_name = $course_obj ? $course_obj->post_title : 'Course';

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="learnpress_students_course_' . $course_id . '.csv"');
            echo "Course,User ID,Username,Email,Registered At\n";
            foreach ($students as $student) {
                $user = get_userdata($student->user_id);
                if ($user) {
                    $user_item = $wpdb->get_row($wpdb->prepare("SELECT start_time FROM {$wpdb->prefix}learnpress_user_items WHERE user_id = %d AND item_id = %d AND item_type = 'lp_course'", $user->ID, $course_id));
                    $reg_date = $user_item ? $user_item->start_time : '';
                    echo '"' . str_replace('"', '""', $course_name) . '",' . $user->ID . ',"' . str_replace('"', '""', $user->user_login) . '","' . str_replace('"', '""', $user->user_email) . '","' . $reg_date . "\"\n";
                }
            }
            exit;
        }
    }

    echo '</div>';
}
