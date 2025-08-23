/**
 * Quiz IA Pro Frontend JavaScript
 */

jQuery(document).ready(function ($) {
  // Search filter for categories
  $(document).on("input", ".quiz-categories-search-input", function () {
    const val = $(this).val();
    if (
      typeof window.QuizFrontend !== "undefined" &&
      window.QuizFrontend.filterCategories
    ) {
      window.QuizFrontend.filterCategories(val);
    }
  });

  // Prevent form submit reload
  $(document).on("submit", ".quiz-categories-search-form", function (e) {
    e.preventDefault();
    return false;
  });
  // Handle 'Voir les quiz' button click for categories
  $(document).on("click", ".voir-les-quiz-btn", function (e) {
    e.preventDefault();
    const categoryId = $(this).data("category-id");
    const $quizList = $("#category-quiz-list-" + categoryId);
    // Hide all other open quiz lists and their quiz cards
    $(".category-quiz-list").not($quizList).slideUp().empty();
    // Toggle display for the clicked one
    if ($quizList.is(":visible")) {
      $quizList.slideUp();
      return;
    }
    // Show loading
    $quizList
      .html('<div class="quiz-loading">Chargement des quiz...</div>')
      .slideDown();
    // Fetch quizzes for this category via AJAX
    $.ajax({
      url: quiz_ai_frontend.ajax_url,
      type: "POST",
      data: {
        action: "get_quizzes_by_category",
        category_id: categoryId,
        nonce: quiz_ai_frontend.nonce,
      },
      success: function (response) {
        if (response.success) {
          $quizList.html(response.data.html);
        } else {
          $quizList.html(
            '<div class="quiz-error">Erreur lors du chargement des quiz.</div>'
          );
        }
      },
      error: function (xhr, status, error) {
        $quizList.html('<div class="quiz-error">Erreur de connexion.</div>');
      },
    });
  });
  // Initialize frontend functionality
  initQuizFrontend();

  function initQuizFrontend() {
    // Use event delegation for preview and take quiz buttons (for dynamic content)
    $(document).on("click", ".btn-preview-quiz", function (e) {
      e.preventDefault();
      const quizId = $(this).data("quiz-id");
      showQuizPreview(quizId);
    });

    $(document).on("click", ".btn-take-quiz", function (e) {
      e.preventDefault();
      const quizId = $(this).data("quiz-id");

      /* console.log(
        "Quiz IA Debug: Take quiz button clicked for quiz ID:",
        quizId
      );
      console.log(
        "Quiz IA Debug: Current user email in localStorage:",
        localStorage.getItem("quiz_user_email")
      ); */

      // First check if user has previous attempts
      checkUserAttempts(quizId, function (hasAttempts, attempts) {
        /* console.log(
          "Quiz IA Debug: Attempts check result - hasAttempts:",
          hasAttempts,
          "attempts count:",
          attempts?.length || 0
        ); */

        if (hasAttempts) {
          // Show attempts modal with option to take quiz again
          //  console.log("Quiz IA Debug: Showing user attempts modal");
          showUserAttempts(quizId, attempts);
        } else {
          // No previous attempts, start quiz directly
          /*  console.log(
            "Quiz IA Debug: No previous attempts, starting quiz directly"
          ); */
          startQuizDirect(quizId);
        }
      });
    });

    // Handle modal close
    $(document).on("click", ".quiz-modal-close, .quiz-modal", function (e) {
      if (e.target === this) {
        closeQuizModal();
      }
    });

    // Close modal on escape key
    $(document).on("keydown", function (e) {
      if (e.key === "Escape") {
        closeQuizModal();
      }
    });
  }

  /**
   * Check if user has previous attempts for a quiz
   */
  function checkUserAttempts(quizId, callback) {
    //console.log("Quiz IA Debug: Checking user attempts for quiz ID:", quizId);

    // Get user email if available (for anonymous users)
    const userEmail = localStorage.getItem("quiz_user_email") || "";

    const ajaxData = {
      action: "get_user_attempts",
      quiz_id: quizId,
      nonce: quiz_ai_frontend.nonce,
    };

    // Add user email for anonymous users
    if (userEmail) {
      ajaxData.user_email = userEmail;
    }

    //console.log("Quiz IA Debug: AJAX data for attempts check:", ajaxData);

    $.ajax({
      url: quiz_ai_frontend.ajax_url,
      type: "POST",
      data: ajaxData,
      success: function (response) {
        //console.log("Quiz IA Debug: Attempts check response:", response);
        if (response.success) {
          /*   console.log(
            "Quiz IA Debug: Has attempts:",
            response.data.has_attempts,
            "Count:",
            response.data.attempts?.length || 0
          ); */
          callback(response.data.has_attempts, response.data.attempts);
        } else {
          //console.log("Quiz IA Debug: Attempts check failed:", response.data);
          // On error, assume no attempts
          callback(false, []);
        }
      },
      error: function (xhr, status, error) {
        console.log("Quiz IA Debug: Attempts check AJAX error:", {
          xhr,
          status,
          error,
        });
        // On error, assume no attempts
        callback(false, []);
      },
    });
  }

  /**
   * Show user's previous attempts modal
   */
  function showUserAttempts(quizId, attempts) {
    let attemptsHtml = '<div class="user-attempts-list">';

    // Store attempts data globally for review access
    window.quizAttemptsData = {};

    attempts.forEach((attempt, index) => {
      const date = new Date(attempt.completed_at).toLocaleString();
      const grade = attempt.percentage >= 60 ? "passed" : "failed";

      // Store attempt data globally with unique ID
      const attemptKey = `attempt_${attempt.id}`;
      window.quizAttemptsData[attemptKey] = attempt;

      attemptsHtml += `
        <div class="attempt-card ${grade}">
          <div class="attempt-header">
            <h4>Tentative ${index + 1}</h4>
            <span class="attempt-date">${date}</span>
          </div>
          <div class="attempt-stats">
            <span class="attempt-score">${attempt.score}/${attempt.total}</span>
            <span class="attempt-percentage ${grade}">${
        attempt.percentage
      }%</span>
          </div>
          <div class="attempt-actions">
            <button class="btn-review-attempt" data-attempt-key="${attemptKey}">
              Réviser les réponses
            </button>
          </div>
        </div>
      `;
    });

    attemptsHtml += "</div>";

    const modalContent = `
      <div class="quiz-modal" id="user-attempts-modal">
        <div class="quiz-modal-content">
          <button class="quiz-modal-close">&times;</button>
          <div class="quiz-modal-header">
            <h2 class="quiz-modal-title">Vos tentatives précédentes</h2>
          </div>
          <div class="quiz-modal-body">
            ${attemptsHtml}
            <div class="attempts-actions">
              <button class="btn-retake-quiz" data-quiz-id="${quizId}">
                Refaire le quiz
              </button>
              <button class="btn-close-modal">Fermer</button>
            </div>
          </div>
        </div>
      </div>
    `;

    $(".quiz-modal").remove();
    $("body").append(modalContent);
    $("#user-attempts-modal").fadeIn(300);

    // Handle buttons
    $(document)
      .off("click.attempts")
      .on("click.attempts", ".btn-retake-quiz", function () {
        const quizId = $(this).data("quiz-id");
        closeQuizModal();
        startQuizDirect(quizId);
      });

    $(document)
      .off("click.review")
      .on("click.review", ".btn-review-attempt", function () {
        const attemptKey = $(this).attr("data-attempt-key");
        const attempt = window.quizAttemptsData[attemptKey];
        if (attempt) {
          showAttemptReview(attempt);
        } else {
          console.error("Attempt data not found for key:", attemptKey);
        }
      });
  }

  /**
   * Show detailed review of a specific attempt
   */
  function showAttemptReview(attempt) {
    // Helper function to format user answer based on question type
    function formatUserAnswer(item) {
      if (item.type === "fill_blank" || item.type === "text_a_completer") {
        if (Array.isArray(item.user_answer) && item.user_answer.length > 0) {
          const userAnswers = item.user_answer.filter(
            (answer) => answer && answer.trim()
          );
          if (userAnswers.length > 0) {
            return userAnswers.map((answer) => escapeHtml(answer)).join(", ");
          }
        }
        return "<em>[aucune réponse fournie]</em>";
      } else if (item.user_answer) {
        return escapeHtml(item.user_answer);
      }
      return "<em>[aucune réponse]</em>";
    }

    // Helper function to format correct answer based on question type
    function formatCorrectAnswer(item) {
      if (item.type === "fill_blank" || item.type === "text_a_completer") {
        if (
          Array.isArray(item.expected_answers) &&
          item.expected_answers.length > 0
        ) {
          return item.expected_answers
            .map((answer) => escapeHtml(answer))
            .join(", ");
        } else if (
          Array.isArray(item.correct_answer) &&
          item.correct_answer.length > 0
        ) {
          return item.correct_answer
            .map((answer) => escapeHtml(answer))
            .join(", ");
        }
        return "Réponses non disponibles";
      } else if (item.correct_answer) {
        return escapeHtml(item.correct_answer);
      }
      return "Réponse correcte non disponible";
    }

    let reviewHtml = `
      <div class="attempt-review-summary">
        <h3>Résultats détaillés</h3>
        <p>Score: ${attempt.score} / ${attempt.total} (${
      attempt.percentage
    }%)</p>
        <p>Date: ${new Date(attempt.completed_at).toLocaleString()}</p>
      </div>
      <div class="attempt-review-details">
    `;

    if (attempt.details && attempt.details.length > 0) {
      attempt.details.forEach((item, i) => {
        // STANDARDIZED FORMAT FOR ALL QUESTION TYPES
        const isCorrect =
          item.is_correct ||
          item.correct ||
          (item.ai_score !== null && item.ai_score >= 50);

        reviewHtml += '<div class="review-question">';
        reviewHtml +=
          "<h4>Q" + (i + 1) + ": " + escapeHtml(item.question) + "</h4>";
        reviewHtml +=
          '<div class="user-answer"><strong>Votre réponse:</strong> ' +
          formatUserAnswer(item) +
          "</div>";

        // Show correct answer if user was wrong
        if (!isCorrect) {
          reviewHtml +=
            '<div class="correct-answer"><strong>Bonne réponse:</strong> ' +
            formatCorrectAnswer(item) +
            "</div>";
        }

        // Show explanation (keep it short and natural)
        let explanation = "";
        if (item.explanation && item.explanation.trim()) {
          explanation = item.explanation.trim();
        } else if (item.ai_feedback && item.type !== "open") {
          explanation = item.ai_feedback.trim();
        }

        if (explanation) {
          // Limit explanation to max 3 lines (approximately 200 characters)
          if (explanation.length > 200) {
            explanation = explanation.substring(0, 200) + "...";
          }
          reviewHtml +=
            '<div class="explanation"><strong>Explication:</strong> ' +
            escapeHtml(explanation) +
            "</div>";
        }

        // Show real course sections (not AI generated)
        if (
          item.ai_suggested_sections &&
          Array.isArray(item.ai_suggested_sections) &&
          item.ai_suggested_sections.length > 0
        ) {
          reviewHtml +=
            '<div class="suggested-sections"><strong>Sections recommandées à réviser:</strong><ul>';

          item.ai_suggested_sections.forEach((section) => {
            reviewHtml += "<li>" + escapeHtml(section) + "</li>";
          });

          reviewHtml += "</ul></div>";
        }

        // Show result status
        reviewHtml +=
          '<div class="answer-status ' +
          (isCorrect ? "correct" : "incorrect") +
          '">';
        reviewHtml += isCorrect ? "✔️ Correct" : "❌ Incorrect";
        reviewHtml += "</div></div>";
      });
    } else {
      reviewHtml += "<p>Aucun détail disponible pour cette tentative.</p>";
    }

    reviewHtml += `
      </div>
      <div class="review-actions">
        <button class="btn-back-to-attempts">Retour aux tentatives</button>
        <button class="btn-close-modal">Fermer</button>
      </div>
    `;

    const modalContent = `
      <div class="quiz-modal" id="attempt-review-modal">
        <div class="quiz-modal-content">
          <button class="quiz-modal-close">&times;</button>
          <div class="quiz-modal-header">
            <h2 class="quiz-modal-title">Révision de la tentative</h2>
          </div>
          <div class="quiz-modal-body">${reviewHtml}</div>
        </div>
      </div>
    `;

    $(".quiz-modal").remove();
    $("body").append(modalContent);
    $("#attempt-review-modal").fadeIn(300);

    $(document)
      .off("click.back")
      .on("click.back", ".btn-back-to-attempts", function () {
        $("#attempt-review-modal").remove();
        // Show attempts modal again - would need to store quiz ID and attempts
      });
  }

  /**
   * Start taking a quiz directly (with cinematic fade)
   */
  function startQuizDirect(quizId) {
    const $categoriesContainer = $(".quiz-categories-container");
    $categoriesContainer.fadeOut(600, function () {
      startQuiz(quizId);
    });
  }

  /**
   * Show quiz preview in modal
   */
  function showQuizPreview(quizId) {
    // Show loading state
    showLoadingModal("Chargement de l'aperçu...");

    // Debug logging
    /*    console.log("Quiz IA Debug: Preview quiz with ID:", quizId);
    console.log("Quiz IA Debug: AJAX URL:", quiz_ai_frontend.ajax_url);
    console.log("Quiz IA Debug: Nonce:", quiz_ai_frontend.nonce);
 */
    // Get quiz details via AJAX
    $.ajax({
      url: quiz_ai_frontend.ajax_url,
      type: "POST",
      data: {
        action: "get_quiz_details",
        quiz_id: quizId,
        nonce: quiz_ai_frontend.nonce,
      },
      success: function (response) {
        //console.log("Quiz IA Debug: Preview AJAX Success:", response);
        if (response.success) {
          displayQuizPreview(response.data);
        } else {
          /*  console.log(
            "Quiz IA Debug: Preview AJAX Error (success=false):",
            response.data
          ); */
          showErrorModal(
            "Erreur lors du chargement de l'aperçu: " + response.data
          );
        }
      },
      error: function (xhr, status, error) {
        console.log("Quiz IA Debug: Preview AJAX Error:", {
          xhr,
          status,
          error,
        });
        //console.log("Quiz IA Debug: Preview Response Text:", xhr.responseText);
        showErrorModal("Erreur de connexion lors du chargement de l'aperçu.");
      },
    });
  }

  /**
   * Start taking a quiz (modal UI)
   */
  function startQuiz(quizId) {
    // Initialiser le flag du formulaire de contact et réinitialiser les infos utilisateur
    window.quizContactFormShown = false;
    window.quizUserInfo = null;

    // Show loading modal after fade out
    showLoadingModal("Chargement du quiz...");

    // Debug logging
    /*   console.log("Quiz IA Debug: Starting quiz with ID:", quizId);
    console.log("Quiz IA Debug: AJAX URL:", quiz_ai_frontend.ajax_url);
    console.log("Quiz IA Debug: Nonce:", quiz_ai_frontend.nonce);
 */
    // Get quiz details via AJAX (reuse preview logic)
    $.ajax({
      url: quiz_ai_frontend.ajax_url,
      type: "POST",
      data: {
        action: "get_quiz_details",
        quiz_id: quizId,
        nonce: quiz_ai_frontend.nonce,
      },
      success: function (response) {
        // console.log("Quiz IA Debug: AJAX Success:", response);
        if (response.success) {
          // Remove categories container from DOM for full focus
          $(".quiz-categories-container").remove();
          //console.log("Quiz IA Debug: About to call displayQuizPassing");
          displayQuizPassing(response.data);
        } else {
          /*  console.log(
            "Quiz IA Debug: AJAX Error (success=false):",
            response.data
          ); */
          showErrorModal("Erreur lors du chargement du quiz: " + response.data);
        }
      },
      error: function (xhr, status, error) {
        /*  console.log("Quiz IA Debug: AJAX Error:", { xhr, status, error });
        console.log("Quiz IA Debug: Response Text:", xhr.responseText); */
        showErrorModal("Erreur de connexion lors du chargement du quiz.");
      },
    });
  }

  /**
   * Display quiz passing modal
   */
  function displayQuizPassing(data) {
    //console.log("Quiz IA Debug: displayQuizPassing called with data:", data);

    const quiz = data.quiz;
    const questions = data.questions;
    const settings = data.settings || {}; // Get quiz settings
    let userAnswers = Array(questions.length).fill(null);
    let currentPage = 0; // Track current page instead of question index
    let quizStartTime = null;
    let timerInterval = null;
    let contactFormSubmitted = false;

    // Calculate questions per page (default 1)
    const questionsPerPage = Math.max(
      1,
      parseInt(settings.questions_per_page) || 1
    );
    const totalPages = Math.ceil(questions.length / questionsPerPage);

    // Store quiz data globally for event handlers BEFORE starting quiz
    window.currentQuizData = {
      currentPage,
      questionsPerPage,
      questions,
      userAnswers,
      totalPages,
      showPassingModal,
      quiz,
      settings,
      submitQuizAnswers,
    };

    /*  console.log(
      "Quiz IA Debug: Setting window.currentQuizData EARLY:",
      window.currentQuizData
    );
    console.log(
      "Quiz IA Debug: window.currentQuizData exists after early setting:",
      !!window.currentQuizData
    ); */

    // Check if contact form is required and user needs to login
    if (settings.require_login && !quiz_ai_frontend.is_user_logged_in) {
      showLoginRequiredModal();
      return;
    }

    // Show contact form if required
    if (settings.show_contact_form && !contactFormSubmitted) {
      showContactFormModal();
      return;
    }

    // Start the actual quiz
    startActualQuiz();

    function showLoginRequiredModal() {
      const modalContent = `
        <div class="quiz-modal" id="quiz-login-modal">
          <div class="quiz-modal-content">
            <button class="quiz-modal-close">&times;</button>
            <div class="quiz-modal-header">
              <h2 class="quiz-modal-title">Connexion Requise</h2>
            </div>
            <div class="quiz-modal-body">
              <p>Vous devez être connecté pour passer ce quiz.</p>
              <div class="quiz-modal-actions">
                <a href="${
                  quiz_ai_frontend.login_url || "/wp-login.php"
                }" class="btn-login">Se connecter</a>
                <button class="btn-close-modal">Fermer</button>
              </div>
            </div>
          </div>
        </div>
      `;
      $("#quiz-login-modal").remove();
      $("body").append(modalContent);
      $("#quiz-login-modal").fadeIn(300);
    }

    function showContactFormModal() {
      // Marquer que le formulaire de contact a été affiché
      window.quizContactFormShown = true;

      const modalContent = `
        <div class="quiz-modal" id="quiz-contact-modal">
          <div class="quiz-modal-content">
            <button class="quiz-modal-close">&times;</button>
            <div class="quiz-modal-header">
              <h2 class="quiz-modal-title">Informations Personnelles</h2>
            </div>
            <div class="quiz-modal-body">
              ${
                !quiz_ai_frontend.is_user_logged_in
                  ? `
                  <div class="account-invitation">
                    <p><strong>💡 Créez un compte gratuit</strong> pour sauvegarder vos résultats et accéder à plus de fonctionnalités!</p>
                    <a href="${
                      quiz_ai_frontend.register_url || "#"
                    }" class="btn-create-account" target="_blank">
                      <span class="dashicons dashicons-admin-users"></span> Créer un compte
                    </a>
                  </div>
                  <div class="form-divider">ou</div>
                `
                  : ""
              }
              <form id="quiz-contact-form">
                <div class="form-group">
                  <label for="user-name">Nom *</label>
                  <input type="text" id="user-name" name="user_name" required>
                </div>
                <div class="form-group">
                  <label for="user-email">Email *</label>
                  <input type="email" id="user-email" name="user_email" required>
                </div>
                <div class="form-group">
                  <label for="user-phone">Téléphone</label>
                  <input type="tel" id="user-phone" name="user_phone">
                </div>
                <div class="form-group">
                  <label class="checkbox-label">
                    <input type="checkbox" id="email-quiz-results" name="email_quiz_results" checked>
                    <span class="checkmark"></span>
                    Recevoir les résultats de ce quiz par email
                  </label>
                </div>
                <div class="form-group">
                  <label class="checkbox-label">
                    <input type="checkbox" id="email-new-quizzes" name="email_new_quizzes" checked>
                    <span class="checkmark"></span>
                    Recevoir les notifications des nouveaux quiz
                  </label>
                </div>
                <div class="quiz-modal-actions">
                  <button type="submit" class="btn-start-quiz">Commencer le Quiz</button>
                  <button type="button" class="btn-close-modal">Fermer</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      `;
      $("#quiz-contact-modal").remove();
      $("body").append(modalContent);
      $("#quiz-contact-modal").fadeIn(300);

      // Handle contact form submission
      $("#quiz-contact-form").on("submit", function (e) {
        e.preventDefault();
        const name = $("#user-name").val().trim();
        const email = $("#user-email").val().trim();

        if (!name || !email) {
          alert("Veuillez remplir tous les champs obligatoires.");
          return;
        }

        // Store user info for quiz submission
        window.quizUserInfo = {
          name: name,
          email: email,
          phone: $("#user-phone").val().trim(),
          email_quiz_results: $("#email-quiz-results").is(":checked"),
          email_new_quizzes: $("#email-new-quizzes").is(":checked"),
        };

        // Store email in localStorage for future attempts checking
        if (email) {
          localStorage.setItem("quiz_user_email", email);
          /* console.log(
            "Quiz IA Debug: Stored user email in localStorage:",
            email
          ); */
        }

        contactFormSubmitted = true;
        $("#quiz-contact-modal").fadeOut(300, function () {
          $(this).remove();
          startActualQuiz();
        });
      });
    }

    function startActualQuiz() {
      // console.log("Quiz IA Debug: startActualQuiz called");
      quizStartTime = Date.now();

      // Start timer if time limit is set
      if (settings.time_limit && settings.time_limit > 0) {
        startQuizTimer(settings.time_limit * 60); // Convert minutes to seconds
      }

      // Check if first page should be disabled (skip intro/welcome page)
      if (settings.disable_first_page) {
        // Go directly to first page of questions
        currentPage = 0;
        /*  console.log(
          "Quiz IA Debug: About to call showPassingModal (disable_first_page=true)"
        ); */
        showPassingModal(currentPage);
      } else {
        // Show intro/welcome page first
        // console.log("Quiz IA Debug: About to call showIntroPage (normal flow)");
        showIntroPage();
      }
    }

    function showIntroPage() {
      //console.log("Quiz IA Debug: showIntroPage called");

      // Build intro page content
      let introHtml = `
        <div class="quiz-intro-page">
          <div class="quiz-intro-header">
            <h2 class="quiz-intro-title">${escapeHtml(quiz.title)}</h2>
            ${
              quiz.featured_image
                ? `<div class="quiz-intro-image">
              <img src="${escapeHtml(quiz.featured_image)}" alt="${escapeHtml(
                    quiz.title
                  )}" style="max-width: 100%; height: auto; border-radius: 8px; margin: 20px 0;">
            </div>`
                : ""
            }
          </div>
          
          <div class="quiz-intro-content">
            ${
              quiz.description
                ? `<div class="quiz-intro-description">
              <p>${escapeHtml(quiz.description)}</p>
            </div>`
                : ""
            }
            
            <div class="quiz-intro-info">
              <div class="quiz-info-grid">
                <div class="quiz-info-item">
                  <span class="quiz-info-label">📝 Questions:</span>
                  <span class="quiz-info-value">${questions.length}</span>
                </div>
                
                <div class="quiz-info-item">
                  <span class="quiz-info-label">📄 Pages:</span>
                  <span class="quiz-info-value">${totalPages}</span>
                </div>
                
                <div class="quiz-info-item">
                  <span class="quiz-info-label">⭐ Difficulté:</span>
                  <span class="quiz-info-value quiz-difficulty-${
                    quiz.difficulty
                  }">${quiz.difficulty}</span>
                </div>
                
                ${
                  settings.time_limit && settings.time_limit > 0
                    ? `
                <div class="quiz-info-item">
                  <span class="quiz-info-label">⏰ Temps limite:</span>
                  <span class="quiz-info-value">${settings.time_limit} minutes</span>
                </div>
                `
                    : ""
                }
              </div>
            </div>
            
            <div class="quiz-intro-instructions">
              <h3>Instructions:</h3>
              <ul>
                <li>Lisez attentivement chaque question</li>
                <li>Sélectionnez la meilleure réponse pour chaque question</li>
                ${
                  settings.time_limit
                    ? "<li>Attention au temps limité pour ce quiz</li>"
                    : ""
                }
                <li>Vous pouvez naviguer entre les pages avec les boutons Précédent/Suivant</li>
                <li>Assurez-vous de répondre à toutes les questions avant de valider</li>
              </ul>
            </div>
          </div>
          
          <div class="quiz-intro-actions">
            <button class="btn-start-quiz-now">Commencer le Quiz</button>
            <button class="btn-close-modal">Fermer</button>
          </div>
        </div>
      `;

      const modalContent = `
        <div class="quiz-modal" id="quiz-intro-modal">
          <div class="quiz-modal-content">
            <button class="quiz-modal-close">&times;</button>
            <div class="quiz-modal-header">
              <h2 class="quiz-modal-title">Prêt à commencer ?</h2>
            </div>
            <div class="quiz-modal-body">
              ${introHtml}
            </div>
          </div>
        </div>
      `;

      $("#quiz-intro-modal").remove();
      $("body").append(modalContent);
      $("#quiz-intro-modal").fadeIn(300);

      // Handle start quiz button
      $(document)
        .off("click.start-quiz")
        .on("click.start-quiz", ".btn-start-quiz-now", function () {
          //  console.log("Quiz IA Debug: Starting quiz from intro page");
          currentPage = 0;
          $("#quiz-intro-modal").fadeOut(300, function () {
            $(this).remove();
            showPassingModal(currentPage);
          });
        });
    }

    function startQuizTimer(timeInSeconds) {
      let timeLeft = timeInSeconds;

      timerInterval = setInterval(function () {
        timeLeft--;
        updateTimerDisplay(timeLeft);

        if (timeLeft <= 0) {
          clearInterval(timerInterval);
          alert("Temps écoulé ! Le quiz va être soumis automatiquement.");
          submitQuizAnswers();
        }
      }, 1000);
    }

    function updateTimerDisplay(timeLeft) {
      const minutes = Math.floor(timeLeft / 60);
      const seconds = timeLeft % 60;
      const timeString = `${minutes.toString().padStart(2, "0")}:${seconds
        .toString()
        .padStart(2, "0")}`;
      $("#quiz-timer").text(timeString);

      // Change color when time is running out
      if (timeLeft <= 60) {
        $("#quiz-timer").addClass("timer-warning");
      }
      if (timeLeft <= 30) {
        $("#quiz-timer").addClass("timer-critical");
      }
    }

    function renderPage(pageIndex) {
      //  console.log("Quiz IA Debug: Rendering page", pageIndex, "of", totalPages);

      const startQuestionIndex = pageIndex * questionsPerPage;
      const endQuestionIndex = Math.min(
        startQuestionIndex + questionsPerPage,
        questions.length
      );
      const pageQuestions = questions.slice(
        startQuestionIndex,
        endQuestionIndex
      );

      /*  console.log(
        "Quiz IA Debug: Page questions:",
        pageQuestions.length,
        "from index",
        startQuestionIndex,
        "to",
        endQuestionIndex
      ); */

      let pageHtml = "";

      // Build timer HTML if enabled
      let timerHtml = "";
      if (settings.time_limit && settings.time_limit > 0) {
        timerHtml = `
          <div class="quiz-timer-container">
            <span class="quiz-timer-label">Temps restant:</span>
            <span id="quiz-timer" class="quiz-timer">--:--</span>
          </div>
        `;
      }

      // Build progress bar HTML if enabled
      let progressBarHtml = "";
      if (settings.show_progress_bar) {
        const progressPercentage = ((pageIndex + 1) / totalPages) * 100;
        progressBarHtml = `
          <div class="quiz-progress-container">
            <div class="quiz-progress-bar">
              <div class="quiz-progress-fill" style="width: ${progressPercentage}%"></div>
            </div>
            <div class="quiz-progress-text">Page ${
              pageIndex + 1
            } sur ${totalPages}</div>
          </div>
        `;
      }

      // Build page number HTML if enabled
      let pageNumberHtml = "";
      if (settings.show_page_number) {
        pageNumberHtml = `
          <div class="quiz-page-number">
            <span class="page-indicator">Page ${
              pageIndex + 1
            } / ${totalPages}</span>
          </div>
        `;
      }

      // Render all questions for this page
      pageQuestions.forEach((question, localIndex) => {
        const globalIndex = startQuestionIndex + localIndex;
        let optionsHtml = "";

        // Handle different question types
        if (question.type === "open" || question.type === "text") {
          // For open questions, show a textarea
          optionsHtml = `<textarea name="quiz-answer-${globalIndex}" placeholder="Saisissez votre réponse ici..." rows="4" style="width: 100%; margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">${
            userAnswers[globalIndex] || ""
          }</textarea>`;
        } else if (
          question.type === "fill_blank" ||
          question.type === "text_a_completer"
        ) {
          // For fill-in-the-blank questions, parse the question text and create input fields
          let questionText = question.question;
          let blankIndex = 0;

          // Replace {answer} placeholders with input fields
          questionText = questionText.replace(
            /\{([^}]+)\}/g,
            function (match, content) {
              const inputId = `fill-blank-${globalIndex}-${blankIndex}`;
              const savedValue = userAnswers[globalIndex]
                ? userAnswers[globalIndex][blankIndex] || ""
                : "";
              blankIndex++;
              return `<input type="text" id="${inputId}" class="fill-blank-input" data-blank-index="${
                blankIndex - 1
              }" value="${savedValue}" placeholder="..." style="border: none; border-bottom: 2px solid #007cba; background: transparent; min-width: 100px; text-align: center; margin: 0 5px;">`;
            }
          );

          optionsHtml = `<div class="fill-blank-question" data-blank-count="${blankIndex}">${questionText}</div>`;
        } else {
          // For multiple choice questions (qcm), show radio buttons
          question.options.forEach((option, i) => {
            optionsHtml += `<label class="quiz-option">
              <input type="radio" name="quiz-answer-${globalIndex}" value="${i}" ${
              userAnswers[globalIndex] === i ? "checked" : ""
            }>
              ${escapeHtml(option)}
            </label><br>`;
          });
        }

        pageHtml += `
          <div class="quiz-question-container" data-question-index="${globalIndex}">
            <h4>Question ${globalIndex + 1} / ${questions.length}: ${escapeHtml(
          question.question
        )}</h4>
            ${
              question.image
                ? `<div class="quiz-question-image">
              <img src="${escapeHtml(question.image)}" alt="Question ${
                    globalIndex + 1
                  }" style="max-width: 100%; height: auto; border-radius: 4px; margin: 15px 0;">
            </div>`
                : ""
            }
            <div class="quiz-question-content">
              ${optionsHtml}
            </div>
          </div>
        `;
      });

      const buttonsHtml = `
        ${
          pageIndex > 0
            ? `<button class="btn-prev-page">Précédent</button>`
            : ""
        }
        ${
          pageIndex < totalPages - 1
            ? `<button class="btn-next-page">Suivant</button>`
            : `<button class="btn-submit-quiz">Valider le Quiz</button>`
        }
        <button class="btn-close-modal">Fermer</button>
      `;

      //  console.log("Quiz IA Debug: Generated buttons HTML:", buttonsHtml);

      return `
        <div class="quiz-passing-page">
          ${timerHtml}
          ${progressBarHtml}
          ${pageNumberHtml}
          <form id="quiz-passing-form">
            ${pageHtml}
          </form>
          <div class="quiz-passing-actions">
            ${buttonsHtml}
          </div>
        </div>
      `;
    }

    function showPassingModal(pageIndex) {
      const modalContent = `
        <div class="quiz-modal" id="quiz-passing-modal">
          <div class="quiz-modal-content">
            <button class="quiz-modal-close">&times;</button>
            <div class="quiz-modal-header">
              <h2 class="quiz-modal-title">${escapeHtml(quiz.title)}</h2>
            </div>
            <div class="quiz-modal-body">
              ${renderPage(pageIndex)}
            </div>
          </div>
        </div>
      `;
      $("#quiz-passing-modal").remove();
      $("body").append(modalContent);
      $("#quiz-passing-modal").fadeIn(300);
    }

    function submitQuizAnswers() {
      // Clear timer if running
      if (timerInterval) {
        clearInterval(timerInterval);
      }

      // DEBUG: Vérifier les conditions
      console.log("Quiz IA Debug - submitQuizAnswers conditions:");
      console.log(
        "- window.quizContactFormShown:",
        window.quizContactFormShown
      );
      console.log("- window.quizUserInfo:", window.quizUserInfo);

      // Vérifier si le formulaire de contact doit être affiché avant la soumission
      if (!window.quizContactFormShown && !window.quizUserInfo) {
        console.log(
          "Quiz IA Debug: Showing end contact form before submission"
        );
        showEndContactFormBeforeSubmission();
        return; // Arrêter ici et attendre la soumission du formulaire
      }

      // Continuer avec la soumission normale
      console.log("Quiz IA Debug: Proceeding with normal submission");
      performQuizSubmission();
    }

    function performQuizSubmission() {
      //  console.log("Quiz IA Debug: Submitting quiz answers:", userAnswers);

      // Prepare submission data
      let submissionData = {
        action: "submit_quiz_answers",
        quiz_id: quiz.id,
        answers: JSON.stringify(userAnswers),
        nonce: quiz_ai_frontend.nonce,
      };

      //console.log("Quiz IA Debug: Submission data:", submissionData);

      // Add user info if collected from contact form
      if (window.quizUserInfo) {
        submissionData.user_info = JSON.stringify(window.quizUserInfo);

        // Store email in localStorage for future attempts checking
        if (window.quizUserInfo.email) {
          localStorage.setItem("quiz_user_email", window.quizUserInfo.email);
          /*  console.log(
            "Quiz IA Debug: Stored user email in localStorage from quiz submission:",
            window.quizUserInfo.email
          ); */
        }
      }

      // Add quiz timing info
      if (quizStartTime) {
        submissionData.quiz_duration = Math.round(
          (Date.now() - quizStartTime) / 1000
        ); // Duration in seconds
      }

      $.ajax({
        url: quiz_ai_frontend.ajax_url,
        type: "POST",
        data: submissionData,
        success: function (response) {
          if (response.success) {
            showQuizResult(response.data, settings, questions);
          } else {
            showErrorModal("Erreur lors de la soumission: " + response.data);
          }
        },
        error: function () {
          showErrorModal("Erreur de connexion lors de la soumission du quiz.");
        },
      });
    }

    function showEndContactFormBeforeSubmission() {
      const modalContent = `
        <div class="quiz-modal" id="quiz-end-contact-modal">
          <div class="quiz-modal-content">
            <div class="quiz-modal-header">
              <h2 class="quiz-modal-title">Avant de voir vos résultats...</h2>
              <p style="margin: 10px 0 0 0; opacity: 0.9;">Souhaitez-vous recevoir vos résultats par email ?</p>
            </div>
            <div class="quiz-modal-body">
              <div class="end-contact-form-container">
                ${
                  !quiz_ai_frontend.is_user_logged_in
                    ? `
                    <div class="account-invitation">
                      <p><strong>💡 Créez un compte gratuit</strong> pour sauvegarder vos résultats et accéder à plus de fonctionnalités!</p>
                      <a href="${
                        quiz_ai_frontend.register_url || "#"
                      }" class="btn-create-account" target="_blank">
                        <span class="dashicons dashicons-admin-users"></span> Créer un compte
                      </a>
                    </div>
                    <div class="form-divider">ou</div>
                    <div class="contact-user-info">
                      <div class="form-row">
                        <div class="form-group">
                          <label for="end-contact-name">Nom *</label>
                          <input type="text" id="end-contact-name" placeholder="Votre nom" required>
                        </div>
                        <div class="form-group">
                          <label for="end-contact-email">Email *</label>
                          <input type="email" id="end-contact-email" placeholder="Votre email" required>
                        </div>
                      </div>
                    </div>
                  `
                    : `
                    <div class="logged-user-info">
                      <p><span class="dashicons dashicons-admin-users"></span> Connecté en tant que <strong>${
                        quiz_ai_frontend.user_email || "utilisateur"
                      }</strong></p>
                    </div>
                  `
                }
                <div class="email-preferences">
                  <label class="checkbox-label">
                    <input type="checkbox" id="end-receive-results" checked>
                    <span class="checkmark"></span>
                    Recevoir les résultats de ce quiz par email
                  </label>
                  <label class="checkbox-label">
                    <input type="checkbox" id="end-receive-alerts" checked>
                    <span class="checkmark"></span>
                    Être notifié des nouveaux quiz
                  </label>
                </div>
                <div class="modal-actions">
                  <button id="submit-end-contact" class="contact-submit-btn">
                    <span class="dashicons dashicons-email"></span>
                    Continuer avec ces préférences
                  </button>
                  <button id="skip-end-contact" class="contact-skip-btn">
                    Ignorer et voir les résultats
                  </button>
                </div>
              </div>
              <div id="end-contact-status"></div>
            </div>
          </div>
        </div>
      `;

      $(".quiz-modal").remove();
      $("body").append(modalContent);
      $("#quiz-end-contact-modal").fadeIn(300);

      // Gestionnaire pour soumettre le formulaire
      $(document)
        .off("click", "#submit-end-contact")
        .on("click", "#submit-end-contact", function () {
          const $button = $(this);
          const $status = $("#end-contact-status");
          const receiveResults = $("#end-receive-results").is(":checked");
          const receiveAlerts = $("#end-receive-alerts").is(":checked");
          const userName = $("#end-contact-name").val() || "";
          const userEmail = $("#end-contact-email").val() || "";

          // Validation pour utilisateurs non connectés
          if (!quiz_ai_frontend.is_user_logged_in) {
            if (!userName.trim()) {
              $status.html(
                '<div class="contact-error">⚠️ Veuillez saisir votre nom.</div>'
              );
              return;
            }
            if (!userEmail.trim() || !isValidEmail(userEmail)) {
              $status.html(
                '<div class="contact-error">⚠️ Veuillez saisir une adresse email valide.</div>'
              );
              return;
            }
          }

          // Stocker les informations utilisateur
          window.quizUserInfo = {
            name: userName || "Utilisateur connecté",
            email: userEmail || quiz_ai_frontend.user_email,
            receive_results: receiveResults,
            receive_alerts: receiveAlerts,
          };

          // Marquer le formulaire comme traité
          window.quizContactFormShown = true;

          // Fermer le modal et continuer avec la soumission
          $("#quiz-end-contact-modal").fadeOut(300, function () {
            $(this).remove();
            performQuizSubmission();
          });
        });

      // Gestionnaire pour ignorer le formulaire
      $(document)
        .off("click", "#skip-end-contact")
        .on("click", "#skip-end-contact", function () {
          // Marquer le formulaire comme traité (même si ignoré)
          window.quizContactFormShown = true;

          // Fermer le modal et continuer avec la soumission
          $("#quiz-end-contact-modal").fadeOut(300, function () {
            $(this).remove();
            performQuizSubmission();
          });
        });
    }

    // Update the global data with the function references now that they're defined
    window.currentQuizData.showPassingModal = showPassingModal;
    window.currentQuizData.submitQuizAnswers = submitQuizAnswers;

    /* console.log(
      "Quiz IA Debug: Updated window.currentQuizData with function references:",
      window.currentQuizData
    );
 */

    // Navigation and answer selection - Updated for page-based navigation
    $(document)
      .off("change.quiz-answer keyup.quiz-answer")
      .on(
        "change.quiz-answer keyup.quiz-answer",
        "input[name^='quiz-answer-'], textarea[name^='quiz-answer-']",
        function () {
          // Extract question index from input name (quiz-answer-0, quiz-answer-1, etc.)
          const name = $(this).attr("name");
          const questionIndex = parseInt(name.replace("quiz-answer-", ""));
          const currentQuestion = questions[questionIndex];

          console.log(
            "Quiz IA Debug: Answer selected for question",
            questionIndex,
            "value:",
            $(this).val()
          );

          if (
            currentQuestion.type === "open" ||
            currentQuestion.type === "text"
          ) {
            // For open questions, store the text value
            userAnswers[questionIndex] = $(this).val();
          } else if (
            currentQuestion.type === "fill_blank" ||
            currentQuestion.type === "text_a_completer"
          ) {
            // For fill-in-the-blank questions, collect all blank answers
            if (!userAnswers[questionIndex]) {
              userAnswers[questionIndex] = [];
            }
            // Find all fill-blank inputs for this question
            const blankInputs = $(
              `.quiz-question-container[data-question-index="${questionIndex}"] .fill-blank-input`
            );
            userAnswers[questionIndex] = [];
            blankInputs.each(function (index) {
              userAnswers[questionIndex][index] = $(this).val();
            });
          } else {
            // For multiple choice, store the option index
            userAnswers[questionIndex] = parseInt($(this).val());
          }

          // console.log("Quiz IA Debug: Updated userAnswers:", userAnswers);
        }
      );

    $(document)
      .off("click.quiz-close")
      .on(
        "click.quiz-close",
        ".btn-close-modal, .quiz-modal-close",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          closeQuizModal();
        }
      );
  }

  // Global close modal handlers
  $(document).on("click", ".btn-close-modal, .quiz-modal-close", function (e) {
    e.preventDefault();
    e.stopPropagation();
    closeQuizModal();
  });

  /**
   * Show quiz result modal
   */
  function showQuizResult(result, quizSettings = {}, quizQuestions = []) {
    let resultHtml = `<div class="quiz-result-summary">
      <h3>Résultat du Quiz</h3>
      <p>Score: ${result.score} / ${result.total}</p>
      <ul class="quiz-result-list">`;
    result.details.forEach((item, i) => {
      resultHtml += `<li>
        <strong>Q${i + 1}:</strong> ${escapeHtml(item.question)}<br>`;

      // Show question image if enabled and available
      if (
        quizSettings.show_question_images_results &&
        quizQuestions[i] &&
        quizQuestions[i].image
      ) {
        resultHtml += `<div class="quiz-result-question-image">
          <img src="${escapeHtml(quizQuestions[i].image)}" alt="Question ${
          i + 1
        } image" style="max-width: 300px; margin: 10px 0;">
        </div>`;
      }

      resultHtml += `<span class="quiz-result-user">Votre réponse: ${escapeHtml(
        item.user_answer
      )}</span><br>`;

      // STANDARDIZED FORMAT FOR ALL QUESTION TYPES
      const isCorrect =
        item.is_correct ||
        item.correct ||
        (item.ai_score !== null && item.ai_score >= 50);

      // Show correct answer if user was wrong
      if (!isCorrect && item.correct_answer) {
        resultHtml += `<strong>Bonne réponse:</strong> ${escapeHtml(
          item.correct_answer
        )}<br><br>`;
      }

      // Show short, natural explanation
      let explanation = "";
      if (item.explanation && item.explanation.trim()) {
        explanation = item.explanation.trim();
      } else if (item.ai_feedback && item.ai_feedback.trim()) {
        explanation = item.ai_feedback.trim();
      }

      if (explanation) {
        // Limit explanation to max 3 lines (approximately 200 characters)
        if (explanation.length > 200) {
          explanation = explanation.substring(0, 200) + "...";
        }
        resultHtml += `<strong>Explication:</strong> ${escapeHtml(
          explanation
        )}<br><br>`;
      }

      // Show real course sections (not AI generated)
      if (
        item.ai_suggested_sections &&
        Array.isArray(item.ai_suggested_sections) &&
        item.ai_suggested_sections.length > 0
      ) {
        resultHtml += `<strong>Sections recommandées à réviser:</strong><br>`;
        item.ai_suggested_sections.forEach((section) => {
          resultHtml += `• ${escapeHtml(section)}<br>`;
        });
        resultHtml += `<br>`;
      }

      // Show result status
      resultHtml += `<span class="quiz-result-status ${
        isCorrect ? "correct" : "incorrect"
      }">
        ${isCorrect ? "✔️ Correct" : "❌ Incorrect"}
      </span>

      </li>`;
    });
    resultHtml += `</ul>`;

    // Add overall course recommendation if available
    if (
      result.course_recommendation &&
      result.course_recommendation.course_title
    ) {
      const rec = result.course_recommendation;
      resultHtml += `
        <div class="quiz-overall-course-recommendation">
          <div class="course-recommendation-content">
            <p>${escapeHtml(rec.recommendation_text)} <a href="${escapeHtml(
        rec.course_url
      )}" target="_blank" rel="noopener">'${escapeHtml(
        rec.course_title
      )}'</a> : ${escapeHtml(rec.course_url)}</p>
            <p><a href="${escapeHtml(
              rec.course_url
            )}" target="_blank" rel="noopener" class="course-access-button">🔗 Accéder au cours</a></p>
          </div>
        </div>
      `;
    }

    // ✅ NOUVELLE FONCTIONNALITÉ: Boîte de commentaires
    if (quizSettings.enable_comments) {
      resultHtml += `
        <div class="quiz-comments-section">
          <h4><span class="dashicons dashicons-admin-comments"></span> Laissez un commentaire</h4>
          <div class="quiz-comments-form">
            ${
              !quiz_ai_frontend.is_user_logged_in
                ? `
              <div class="comment-user-info">
                <div class="form-row">
                  <div class="form-group">
                    <input type="text" id="comment-user-name" placeholder="Votre nom *" required>
                  </div>
                  <div class="form-group">
                    <input type="email" id="comment-user-email" placeholder="Votre email *" required>
                  </div>
                </div>
              </div>
            `
                : ""
            }
            <div class="comment-rating">
              <label>Note (optionnel):</label>
              <div class="rating-stars">
                <span class="star" data-rating="1">⭐</span>
                <span class="star" data-rating="2">⭐</span>
                <span class="star" data-rating="3">⭐</span>
                <span class="star" data-rating="4">⭐</span>
                <span class="star" data-rating="5">⭐</span>
              </div>
              <input type="hidden" id="comment-rating" value="">
            </div>
            <textarea 
              id="quiz-comment-text" 
              placeholder="Partagez votre expérience avec ce quiz..."
              maxlength="500"
              rows="4"
              required
            ></textarea>
            <div class="quiz-comments-info">
              <small>Minimum 10 caractères, maximum 500 caractères</small>
              <span id="comment-char-count">0/500</span>
            </div>
            <button 
              id="submit-quiz-comment" 
              class="quiz-comment-submit-btn"
            >
              <span class="dashicons dashicons-yes"></span>
              Envoyer le commentaire
            </button>
          </div>
          <div id="quiz-comment-status"></div>
        </div>
      `;
    }

    resultHtml += `
      <div class="quiz-result-actions">
        <button class="btn-close-modal">Fermer</button>
      </div>
    </div>`;

    const modalContent = `<div class="quiz-modal" id="quiz-result-modal">
      <div class="quiz-modal-content">
        <div class="quiz-modal-header">
          <h2 class="quiz-modal-title">Résultat du Quiz</h2>
        </div>
        <div class="quiz-modal-body">${resultHtml}</div>
      </div>
    </div>`;

    $(".quiz-modal").remove();
    $("body").append(modalContent);
    $("#quiz-result-modal").fadeIn(300);

    // ✅ Gestion des événements pour les commentaires
    if (quizSettings.enable_comments) {
      setupCommentHandlers(
        window.currentQuizData ? window.currentQuizData.quiz.id : null
      );
    }

    $(document)
      .off("click.quiz-close-result")
      .on("click.quiz-close-result", ".btn-close-modal", function () {
        closeQuizModal();
      });
  }
  /**
   * Display quiz preview in modal
   */
  function displayQuizPreview(data) {
    const quiz = data.quiz;
    const questions = data.questions;

    let modalContent = `
            <div class="quiz-modal" id="quiz-preview-modal">
                <div class="quiz-modal-content">
                    <button class="quiz-modal-close">&times;</button>
                    <div class="quiz-modal-header">
                        <h2 class="quiz-modal-title">${escapeHtml(
                          quiz.title
                        )}</h2>
                        <div class="quiz-modal-meta">
                            <span class="quiz-difficulty ${quiz.difficulty}">${
      quiz.difficulty
    }</span>
                            <span class="question-count">${
                              questions.length
                            } questions</span>
                            <span class="quiz-type">${quiz.quiz_type}</span>
                        </div>
                    </div>
                    <div class="quiz-modal-body">
        `;

    if (quiz.description) {
      modalContent += `<p class="quiz-description">${escapeHtml(
        quiz.description
      )}</p>`;
    }

    modalContent += '<div class="quiz-preview-questions">';

    questions.forEach((question, index) => {
      modalContent += `
                <div class="quiz-preview-question">
                    <h4>Question ${index + 1}: ${escapeHtml(
        question.question
      )}</h4>
                    <!-- Options are intentionally hidden in preview mode -->
      `;
      // Do not render options here
      if (question.explanation) {
        modalContent += `<p class="quiz-explanation"><strong>Explication:</strong> ${escapeHtml(
          question.explanation
        )}</p>`;
      }
      modalContent += "</div>";
    });

    modalContent += `
                    </div>
                    <div class="quiz-modal-actions">
                        <button class="btn-take-quiz" data-quiz-id="${quiz.id}">Passer ce Quiz</button>
                        <button class="btn-close-modal">Fermer</button>
                    </div>
                </div>
            </div>
        `;

    // Remove existing modal
    $("#quiz-preview-modal").remove();

    // Add new modal to body
    $("body").append(modalContent);

    // Show modal
    $("#quiz-preview-modal").fadeIn(300);

    // Handle close button in modal
    $(".btn-close-modal").on("click", closeQuizModal);
  }

  /**
   * Show loading modal
   */
  function showLoadingModal(message) {
    const modalContent = `
            <div class="quiz-modal" id="quiz-loading-modal">
                <div class="quiz-modal-content">
                    <div class="quiz-loading">${message}</div>
                </div>
            </div>
        `;

    // Remove existing modals
    $(".quiz-modal").remove();

    // Add loading modal
    $("body").append(modalContent);
    $("#quiz-loading-modal").fadeIn(300);
  }

  /**
   * Show error modal
   */
  function showErrorModal(message) {
    const modalContent = `
            <div class="quiz-modal" id="quiz-error-modal">
                <div class="quiz-modal-content">
                    <button class="quiz-modal-close">&times;</button>
                    <div class="quiz-modal-header">
                        <h2 class="quiz-modal-title">Erreur</h2>
                    </div>
                    <div class="quiz-modal-body">
                        <p class="quiz-categories-error">${message}</p>
                    </div>
                    <div class="quiz-modal-actions">
                        <button class="btn-close-modal">Fermer</button>
                    </div>
                </div>
            </div>
        `;

    // Remove existing modals
    $(".quiz-modal").remove();

    // Add error modal
    $("body").append(modalContent);
    $("#quiz-error-modal").fadeIn(300);

    // Handle close button
    $(".btn-close-modal").on("click", closeQuizModal);
  }

  /**
   * Close quiz modal
   */
  function closeQuizModal() {
    $(".quiz-modal").fadeOut(300, function () {
      $(this).remove();
      // Cinematic restore of categories
      if ($(".quiz-categories-container").length === 0) {
        // Try to find the original container markup in a hidden clone or reload via AJAX
        if (
          window.QuizFrontend &&
          typeof window.QuizFrontend.restoreCategories === "function"
        ) {
          window.QuizFrontend.restoreCategories();
        } else if (window.quizCategoriesHtml) {
          // If we saved the HTML before fade out, restore it
          $("main, #main, body").first().append(window.quizCategoriesHtml);
          $(".quiz-categories-container").hide().fadeIn(600);
        } else {
          // Fallback: reload the page
          window.location.reload();
        }
      }
    });
  }

  /**
   * Escape HTML to prevent XSS
   */
  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Filter categories (if search is implemented)
   */
  function filterCategories(searchTerm) {
    const $categories = $(".quiz-category-card");

    if (!searchTerm) {
      $categories.show();
      return;
    }

    $categories.each(function () {
      const $category = $(this);
      const categoryName = $category
        .find(".category-title")
        .text()
        .toLowerCase();
      const categoryDesc = $category
        .find(".category-description")
        .text()
        .toLowerCase();
      const quizTitles = $category
        .find(".quiz-title")
        .map(function () {
          return $(this).text().toLowerCase();
        })
        .get()
        .join(" ");

      const searchText = (
        categoryName +
        " " +
        categoryDesc +
        " " +
        quizTitles
      ).toLowerCase();

      if (searchText.includes(searchTerm.toLowerCase())) {
        $category.show();
      } else {
        $category.hide();
      }
    });
  }

  // Save the categories container HTML, parent, and index for restoration
  if (typeof window.quizCategoriesHtml === "undefined") {
    var $cat = $(".quiz-categories-container");
    window.quizCategoriesHtml = $cat.prop("outerHTML");
    if ($cat.length) {
      var $parent = $cat.parent();
      window.quizCategoriesParentSelector = getUniqueSelector($parent[0]);
      window.quizCategoriesIndex = $cat.index();
    }
  }

  // Helper to get a unique selector for a DOM element
  function getUniqueSelector(el) {
    if (!el) return "body";
    if (el.id) return "#" + el.id;
    if (el === document.body) return "body";
    var path = [];
    while (el && el.nodeType === 1 && el !== document.body) {
      var selector = el.nodeName.toLowerCase();
      if (el.className)
        selector += "." + $.trim(el.className).replace(/\s+/g, ".");
      path.unshift(selector);
      el = el.parentNode;
    }
    return path.length ? path.join(" > ") : "body";
  }

  // Expose functions globally if needed
  window.QuizFrontend = {
    filterCategories: filterCategories,
    showQuizPreview: showQuizPreview,
    startQuiz: startQuiz,
    restoreCategories: function () {
      if (
        $(".quiz-categories-container").length === 0 &&
        window.quizCategoriesHtml
      ) {
        var $parent = window.quizCategoriesParentSelector
          ? $(window.quizCategoriesParentSelector)
          : $("main, #main, body").first();
        var $restored = $(window.quizCategoriesHtml).hide();
        if ($parent.length && typeof window.quizCategoriesIndex === "number") {
          // Insert at the original index
          if ($parent.children().eq(window.quizCategoriesIndex).length) {
            $parent.children().eq(window.quizCategoriesIndex).before($restored);
          } else {
            $parent.append($restored);
          }
        } else {
          $parent.append($restored);
        }
        $restored.fadeIn(600);
      }
    },
  };

  // Global event handlers for quiz navigation - moved outside function scope
  $(document).on("click", ".btn-next-page", function (e) {
    //  console.log("Quiz IA Debug: Global Next page button clicked");
    /*  console.log(
      "Quiz IA Debug: window.currentQuizData exists:",
      !!window.currentQuizData
    ); */
    e.preventDefault();

    // Try to access the quiz variables from the window object if they exist
    if (window.currentQuizData) {
      const {
        currentPage,
        questionsPerPage,
        questions,
        totalPages,
        showPassingModal,
      } = window.currentQuizData;

      // Validate all questions on current page before proceeding by checking the actual form values
      const startQuestionIndex = currentPage * questionsPerPage;
      const endQuestionIndex = Math.min(
        startQuestionIndex + questionsPerPage,
        questions.length
      );

      /* console.log(
        "Quiz IA Debug: Validating questions from",
        startQuestionIndex,
        "to",
        endQuestionIndex
      ); */
      /*      console.log("Quiz IA Debug: Current page:", currentPage);
      console.log("Quiz IA Debug: Questions per page:", questionsPerPage);
      console.log("Quiz IA Debug: Total pages:", totalPages); */

      // Check each question on the current page by looking at the actual form inputs
      for (let i = startQuestionIndex; i < endQuestionIndex; i++) {
        const currentQuestion = questions[i];

        if (
          currentQuestion.type === "open" ||
          currentQuestion.type === "text"
        ) {
          // For open questions, check the textarea value
          const textValue = $(`textarea[name="quiz-answer-${i}"]`).val();
          /*      console.log(
            `Quiz IA Debug: Question ${i + 1} (open) value:`,
            textValue
          ); */
          if (!textValue || textValue.trim() === "") {
            alert(`Veuillez saisir une réponse à la question ${i + 1}.`);
            return;
          }
          // Update the global array
          window.currentQuizData.userAnswers[i] = textValue;
        } else if (
          currentQuestion.type === "fill_blank" ||
          currentQuestion.type === "text_a_completer" ||
          currentQuestion.type === "fill_in_the_blank"
        ) {
          // For fill-in-the-blank questions, check all blank inputs
          const blankInputs = $(
            `.quiz-question-container[data-question-index="${i}"] .fill-blank-input`
          );
          let allFilled = true;
          const blankAnswers = [];

          blankInputs.each(function (index) {
            const value = $(this).val();
            blankAnswers[index] = value;
            if (!value || value.trim() === "") {
              allFilled = false;
            }
          });

          /*   console.log(
            `Quiz IA Debug: Question ${i + 1} (fill-blank) values:`,
            blankAnswers
          ); */

          if (!allFilled) {
            alert(
              `Veuillez compléter tous les espaces à la question ${i + 1}.`
            );
            return;
          }

          // Update the global array
          window.currentQuizData.userAnswers[i] = blankAnswers;
        } else {
          // For multiple choice, check if a radio button is selected
          const selectedValue = $(
            `input[name="quiz-answer-${i}"]:checked`
          ).val();
          /*  console.log(
            `Quiz IA Debug: Question ${i + 1} (multiple choice) selected:`,
            selectedValue
          ); */
          if (selectedValue === undefined) {
            alert(`Veuillez sélectionner une réponse à la question ${i + 1}.`);
            return;
          }
          // Update the global array
          window.currentQuizData.userAnswers[i] = parseInt(selectedValue);
        }
      }

      /*  console.log("Quiz IA Debug: Validation passed, moving to next page");
      console.log(
        "Quiz IA Debug: Updated userAnswers:",
        window.currentQuizData.userAnswers
      ); */
      window.currentQuizData.currentPage++;
      /*  console.log(
        "Quiz IA Debug: Updated currentPage to:",
        window.currentQuizData.currentPage
      ); */
      showPassingModal(window.currentQuizData.currentPage);
    } else {
      console.error("Quiz IA Debug: No quiz data found in window object");
    }
  });

  $(document).on("click", ".btn-prev-page", function (e) {
    // console.log("Quiz IA Debug: Global Previous page button clicked");
    e.preventDefault();

    if (window.currentQuizData) {
      window.currentQuizData.currentPage--;
      window.currentQuizData.showPassingModal(
        window.currentQuizData.currentPage
      );
    }
  });

  $(document).on("click", ".btn-submit-quiz", function (e) {
    //console.log("Quiz IA Debug: Global Submit button clicked");
    e.preventDefault();

    if (window.currentQuizData) {
      const { currentPage, questionsPerPage, questions } =
        window.currentQuizData;

      // Validate all questions on current page before submitting by checking actual form values
      const startQuestionIndex = currentPage * questionsPerPage;
      const endQuestionIndex = Math.min(
        startQuestionIndex + questionsPerPage,
        questions.length
      );

      // Check each question on the current page
      for (let i = startQuestionIndex; i < endQuestionIndex; i++) {
        const currentQuestion = questions[i];

        if (
          currentQuestion.type === "open" ||
          currentQuestion.type === "text"
        ) {
          const textValue = $(`textarea[name="quiz-answer-${i}"]`).val();
          if (!textValue || textValue.trim() === "") {
            alert(`Veuillez saisir une réponse à la question ${i + 1}.`);
            return;
          }
          // Update the global array
          window.currentQuizData.userAnswers[i] = textValue;
        } else if (
          currentQuestion.type === "fill_blank" ||
          currentQuestion.type === "text_a_completer" ||
          currentQuestion.type === "fill_in_the_blank"
        ) {
          // For fill-in-the-blank questions, check all blank inputs
          const blankInputs = $(
            `.quiz-question-container[data-question-index="${i}"] .fill-blank-input`
          );
          let allFilled = true;
          const blankAnswers = [];

          blankInputs.each(function (index) {
            const value = $(this).val();
            blankAnswers[index] = value;
            if (!value || value.trim() === "") {
              allFilled = false;
            }
          });

          if (!allFilled) {
            alert(
              `Veuillez compléter tous les espaces à la question ${i + 1}.`
            );
            return;
          }

          // Update the global array
          window.currentQuizData.userAnswers[i] = blankAnswers;
        } else {
          const selectedValue = $(
            `input[name="quiz-answer-${i}"]:checked`
          ).val();
          if (selectedValue === undefined) {
            alert(`Veuillez sélectionner une réponse à la question ${i + 1}.`);
            return;
          }
          // Update the global array
          window.currentQuizData.userAnswers[i] = parseInt(selectedValue);
        }
      }

      // Call the submit function that should be stored in the global data
      if (window.currentQuizData.submitQuizAnswers) {
        window.currentQuizData.submitQuizAnswers();
      }
    }
  });

  // Global event handlers for all quiz buttons
  $(document).on("click", ".btn-close-modal, .quiz-modal-close", function (e) {
    e.preventDefault();
    e.stopPropagation();
    closeQuizModal();
  });

  // Close modal on background click
  $(document).on("click", ".quiz-modal", function (e) {
    if (e.target === this) {
      closeQuizModal();
    }
  });

  // Prevent modal content clicks from closing modal
  $(document).on("click", ".quiz-modal-content", function (e) {
    e.stopPropagation();
  });

  // Close modal on ESC key
  $(document).on("keydown", function (e) {
    if (e.key === "Escape" && $(".quiz-modal:visible").length > 0) {
      closeQuizModal();
    }
  });

  $(document).on("click", ".btn-retake-quiz", function (e) {
    e.preventDefault();
    const quizId = $(this).data("quiz-id");
    if (quizId) {
      startQuizDirect(quizId);
    }
  });

  $(document).on("click", ".btn-review-attempt", function (e) {
    e.preventDefault();
    const attemptKey = $(this).attr("data-attempt-key");
    const attempt = window.quizAttemptsData[attemptKey];
    if (attempt) {
      showAttemptReview(attempt);
    }
  });

  $(document).on("click", ".btn-back-to-attempts", function (e) {
    e.preventDefault();
    const quizId = $(this).data("quiz-id");
    if (quizId) {
      checkUserAttempts(quizId);
    }
  });

  /**
   * Configuration des gestionnaires d'événements pour les commentaires
   */
  function setupCommentHandlers(quizId) {
    // Gestion des étoiles de notation
    $(document).on("click", ".rating-stars .star", function () {
      const rating = $(this).data("rating");
      $("#comment-rating").val(rating);

      // Mise à jour visuelle des étoiles
      $(".rating-stars .star").each(function (index) {
        if (index < rating) {
          $(this).addClass("selected").text("⭐");
        } else {
          $(this).removeClass("selected").text("☆");
        }
      });
    });

    // Compteur de caractères
    $(document).on("input", "#quiz-comment-text", function () {
      const currentLength = $(this).val().length;
      const maxLength = 500;
      $("#comment-char-count").text(`${currentLength}/${maxLength}`);

      // Changement de couleur près de la limite
      if (currentLength > 450) {
        $("#comment-char-count").css("color", "#d63384");
      } else if (currentLength < 10) {
        $("#comment-char-count").css("color", "#dc3545");
      } else {
        $("#comment-char-count").css("color", "#28a745");
      }

      // Validation en temps réel
      const $submitBtn = $("#submit-quiz-comment");
      if (currentLength >= 10 && currentLength <= 500) {
        $submitBtn.prop("disabled", false);
      } else {
        $submitBtn.prop("disabled", true);
      }
    });

    // Soumission du commentaire
    $(document).on("click", "#submit-quiz-comment", function () {
      const commentText = $("#quiz-comment-text").val().trim();
      const rating = $("#comment-rating").val();
      const userName = $("#comment-user-name").val() || "";
      const userEmail = $("#comment-user-email").val() || "";
      const $button = $(this);
      const $status = $("#quiz-comment-status");

      // Validation côté client
      if (!commentText) {
        $status.html(
          '<div class="comment-error">⚠️ Veuillez saisir un commentaire.</div>'
        );
        return;
      }

      if (commentText.length < 10) {
        $status.html(
          '<div class="comment-error">⚠️ Le commentaire doit contenir au moins 10 caractères.</div>'
        );
        return;
      }

      if (commentText.length > 500) {
        $status.html(
          '<div class="comment-error">⚠️ Le commentaire est trop long (maximum 500 caractères).</div>'
        );
        return;
      }

      // Validation pour utilisateurs non connectés
      if (!quiz_ai_frontend.is_user_logged_in) {
        if (!userName.trim()) {
          $status.html(
            '<div class="comment-error">⚠️ Veuillez saisir votre nom.</div>'
          );
          return;
        }
        if (!userEmail.trim() || !isValidEmail(userEmail)) {
          $status.html(
            '<div class="comment-error">⚠️ Veuillez saisir une adresse email valide.</div>'
          );
          return;
        }
      }

      // Désactiver le bouton pendant l'envoi
      $button
        .prop("disabled", true)
        .html(
          '<span class="dashicons dashicons-update"></span> Envoi en cours...'
        );
      $status.html(
        '<div class="comment-info">📤 Envoi du commentaire...</div>'
      );

      // Préparer les données
      const commentData = {
        action: "submit_quiz_comment",
        quiz_id: quizId,
        comment_text: commentText,
        nonce: quiz_ai_frontend.nonce,
      };

      // Ajouter la note si sélectionnée
      if (rating) {
        commentData.rating = rating;
      }

      // Ajouter les infos utilisateur si non connecté
      if (!quiz_ai_frontend.is_user_logged_in) {
        commentData.user_name = userName;
        commentData.user_email = userEmail;
      }

      // Envoyer via AJAX
      $.ajax({
        url: quiz_ai_frontend.ajax_url,
        type: "POST",
        data: commentData,
        success: function (response) {
          if (response.success) {
            $status.html(
              '<div class="comment-success">✅ Commentaire enregistré avec succès!</div>'
            );

            // Réinitialiser le formulaire
            $("#quiz-comment-text").val("");
            $("#comment-rating").val("");
            $("#comment-user-name").val("");
            $("#comment-user-email").val("");
            $(".rating-stars .star").removeClass("selected").text("☆");
            $("#comment-char-count").text("0/500").css("color", "#666");

            // Masquer le formulaire après 3 secondes
            setTimeout(function () {
              $(".quiz-comments-form").slideUp();
            }, 3000);
          } else {
            $status.html(
              `<div class="comment-error">❌ ${
                response.data ||
                "Erreur lors de l'enregistrement du commentaire."
              }</div>`
            );
          }
        },
        error: function () {
          $status.html(
            '<div class="comment-error">❌ Erreur de connexion lors de l\'envoi du commentaire.</div>'
          );
        },
        complete: function () {
          // Réactiver le bouton
          $button
            .prop("disabled", false)
            .html(
              '<span class="dashicons dashicons-yes"></span> Envoyer le commentaire'
            );
        },
      });
    });
  }

  /**
   * Validation d'email simple
   */
  function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }
});
