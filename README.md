# Quiz IA Pro

A powerful WordPress plugin for interactive, AI-powered quizzes with advanced user tracking, email notifications, and dynamic feedback.

## Features

- Create and manage quizzes with multiple question types (QCM, open, fill-in-the-blank, etc.)
- AI-powered feedback and course recommendations
- User statistics: views, participants, attempts
- Email notifications for quiz results and new quizzes
- Contact form integration for anonymous users
- Admin management: delete subscribers, view statistics
- AJAX-based frontend for seamless user experience
- Loading spinner/modal for quiz actions
- GDPR-friendly unsubscribe logic
- Comment system for quiz feedback

## Installation

1. Clone or download the repository into your WordPress `wp-content/plugins` directory:
   ```
   git clone https://github.com/SohailPro12/quiz-ai.git
   ```
2. Activate the plugin from the WordPress admin panel.
3. (Optional) Configure plugin settings in the admin area.

## Usage

- Add quizzes via the WordPress admin interface.
- Display quizzes on any page using the provided shortcodes or blocks.
- Users can take quizzes, receive instant feedback, and get results by email.
- Admins can view statistics, manage subscribers, and moderate comments.

## Shortcodes

- `[quiz_ai_list]` — Display a list of available quizzes.
- `[quiz_ai_quiz id="123"]` — Display a specific quiz by ID.

## AJAX Endpoints

- `quiz_ai_pro_increment_quiz_views` — Track quiz views when users start a quiz.
- `get_user_attempts` — Retrieve user attempts for a quiz.
- `submit_quiz_answers` — Submit answers and get results.
- Additional endpoints for category filtering, comments, etc.

## Customization

- Frontend styles can be customized via the plugin's CSS files in `assets/css/`.
- JS logic for modals, loading spinners, and AJAX is in `assets/js/frontend.js`.
- Email templates and admin features are in the `includes/` directory.

## Development

- Requires WordPress 5.0+
- PHP 7.4+
- jQuery (bundled with WordPress)

### Folder Structure

```
quiz-ai/
├── assets/
│   ├── css/
│   ├── js/
├── includes/
├── languages/
├── templates/
├── quiz-ai-pro.php
├── README.md
```

## Contributing

Pull requests and issues are welcome! Please follow WordPress coding standards and document your changes.

## License

This project is licensed under the GPL v2 or later.

## Credits

Developed by SohailPro12 and contributors.

---

For support or questions, open an issue on GitHub or contact the maintainer.
