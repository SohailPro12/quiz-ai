/**
 * Quiz IA Pro - Admin JavaScript
 */

(function ($) {
  "use strict";

  // Document ready
  $(document).ready(function () {
    console.log("Quiz IA Pro Admin JS loaded");

    // Refresh nonces on page load to ensure they're current
    refreshNonces(function () {
      initQuizList();
      initStatsPage();
      initDashboard();
      initFilters();
      initBulkActions();
      initCopyCode();
      initTooltips();
      initQuizGenerator();
      initQuizEditor();
    });
  });

  /**
   * Refresh nonces to ensure they're current
   */
  function refreshNonces(callback) {
    $.ajax({
      url: quiz_ai_ajax.ajax_url,
      type: "POST",
      data: {
        action: "quiz_ai_refresh_nonces",
      },
      success: function (response) {
        if (response.success) {
          // Update both nonce objects
          if (typeof quiz_ai_ajax !== "undefined") {
            quiz_ai_ajax.nonce = response.data.quiz_ai_admin_nonce;
          }
          if (typeof quiz_ai_pro_ajax !== "undefined") {
            quiz_ai_pro_ajax.nonce = response.data.quiz_ai_pro_nonce;
          }
          console.log("Nonces refreshed successfully");
          if (callback) callback();
        } else {
          console.error("Failed to refresh nonces");
          if (callback) callback(); // Continue anyway
        }
      },
      error: function () {
        console.error("Error refreshing nonces");
        if (callback) callback(); // Continue anyway
      },
    });
  }

  /**
   * Initialize Quiz List functionality
   */
  function initQuizList() {
    // Tri des colonnes
    $(".quiz-list-table th.sortable a").on("click", function (e) {
      e.preventDefault();
      var $this = $(this);
      var $th = $this.closest("th");

      // Toggle sort direction
      if ($th.hasClass("sorted")) {
        $th.toggleClass("desc asc");
      } else {
        $(".quiz-list-table th").removeClass("sorted desc asc");
        $th.addClass("sorted desc");
      }

      // Here you would typically make an AJAX call to sort the data
      showNotification("Tri appliqué", "success");
    });

    // Individual quiz action handlers - NEW IMPROVED VERSION
    $(document).on("click", ".quiz-action", function (e) {
      e.preventDefault();

      console.log("Quiz action clicked"); // Debug

      var $this = $(this);
      var action = $this.data("action");
      var quizId = $this.data("quiz-id");

      console.log("Action:", action, "Quiz ID:", quizId); // Debug

      if (!action || !quizId) {
        console.error("Missing action or quiz ID");
        return;
      }

      // Handle different action types
      let confirmText = "";
      switch (action) {
        case "publish":
          confirmText = "Êtes-vous sûr de vouloir publier ce quiz ?";
          break;
        case "unpublish":
          confirmText = "Êtes-vous sûr de vouloir dépublier ce quiz ?";
          break;
        case "delete":
          confirmText =
            "Êtes-vous sûr de vouloir supprimer ce quiz ? Cette action est irréversible.";
          break;
        default:
          confirmText = "Êtes-vous sûr de vouloir effectuer cette action ?";
      }

      if (!confirm(confirmText)) {
        return;
      }

      // For delete actions, use bulk action endpoint
      if (action === "delete") {
        showLoader();

        $.ajax({
          url: quiz_ai_ajax.ajax_url,
          type: "POST",
          data: {
            action: "quiz_ai_bulk_quiz_action",
            nonce: quiz_ai_ajax.nonce,
            action_type: action,
            quiz_ids: [quizId],
          },
          success: function (response) {
            hideLoader();
            if (response.success) {
              showNotification(response.data.message, "success");
              // Reload the page to update the quiz list
              setTimeout(() => {
                location.reload();
              }, 1000);
            } else {
              showNotification(
                response.data || "Erreur lors de l'exécution de l'action",
                "error"
              );
              console.error("Error response:", response);
            }
          },
          error: function (xhr, status, error) {
            hideLoader();
            showNotification(
              "Erreur de communication avec le serveur",
              "error"
            );
            console.error("AJAX Error:", status, error, xhr.responseText);
          },
        });
        return;
      }

      // For publish/unpublish actions, use original endpoint
      showLoader();

      $.ajax({
        url: quiz_ai_pro_ajax.ajax_url || ajaxurl,
        type: "POST",
        data: {
          action: "quiz_ai_pro_" + action + "_quiz",
          quiz_id: quizId,
          nonce: quiz_ai_pro_ajax.nonce,
        },
        success: function (response) {
          hideLoader();
          if (response.success) {
            showNotification(response.data, "success");
            // Reload the page to update the quiz list
            setTimeout(() => {
              location.reload();
            }, 1000);
          } else {
            showNotification(response.data, "error");
          }
        },
        error: function (xhr, status, error) {
          hideLoader();
          showNotification("Erreur de communication avec le serveur", "error");
          console.error("AJAX Error:", status, error, xhr.responseText);
        },
      });
    });

    // Handle edit links (both title and action links)
    $(".row-title, .row-actions a[aria-label*='Modifier']").on(
      "click",
      function (e) {
        var href = $(this).attr("href");
        if (href && href !== "#") {
          // Let the browser handle the navigation normally
          console.log("Navigating to edit page:", href);
        } else {
          e.preventDefault();
          console.warn("Edit link has no valid href");
        }
      }
    );

    // Pagination
    $(".pagination-links a").on("click", function (e) {
      e.preventDefault();
      var page = $(this).attr("href") || $(this).data("page");
      loadPage(page);
    });

    // LearnPress Integration - Create LearnPress quiz
    $(document).on("click", ".create-learnpress-quiz", function (e) {
      e.preventDefault();
      var quizId = $(this).data("quiz-id");
      var $button = $(this);
      var $cell = $button.closest(".learnpress");

      if (!quizId) {
        showNotification("Erreur: ID du quiz non trouvé", "error");
        return;
      }

      // Show loading state
      $button.html(
        '<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> Création...'
      );
      $button.prop("disabled", true);

      // AJAX call to create LearnPress quiz
      $.ajax({
        url: quiz_ai_pro_ajax.ajax_url,
        method: "POST",
        data: {
          action: "quiz_ai_pro_create_learnpress_quiz",
          quiz_id: quizId,
          nonce: quiz_ai_pro_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            // Update the cell to show synced status
            $cell.html(
              '<span class="learnpress-synced" title="Synchronisé avec LearnPress (ID: ' +
                response.data.learnpress_quiz_id +
                ')">' +
                '<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> Synchronisé' +
                "</span>" +
                '<div class="row-actions">' +
                '<span class="view-learnpress">' +
                '<a href="' +
                response.data.edit_url +
                '" target="_blank">Voir dans LearnPress</a>' +
                "</span>" +
                "</div>"
            );
            showNotification(
              response.data.message || "Quiz créé avec succès dans LearnPress!",
              "success"
            );
          } else {
            $button.html("Créer dans LearnPress");
            $button.prop("disabled", false);
            showNotification(
              response.data.message ||
                "Erreur lors de la création du quiz LearnPress",
              "error"
            );
          }
        },
        error: function (xhr, status, error) {
          $button.html("Créer dans LearnPress");
          $button.prop("disabled", false);
          showNotification("Erreur AJAX: " + error, "error");
        },
      });
    });
  }

  /**
   * Initialize Stats Page functionality
   */
  function initStatsPage() {
    // Filtre de période
    $("#stats-period").on("change", function () {
      var period = $(this).val();
      var quizId = $("#stats-quiz-filter").val();
      updateStatsData(period, quizId);
    });

    // Filtre de quiz
    $("#stats-quiz-filter").on("change", function () {
      var period = $("#stats-period").val();
      var quizId = $(this).val();
      updateStatsData(period, quizId);
    });

    // Recherche de participants
    $(".search-participants").on(
      "keyup",
      debounce(function () {
        var search = $(this).val();
        searchParticipants(search);
      }, 300)
    );

    // Toggle widget
    $(".widget-toggle").on("click", function () {
      var $widget = $(this).closest(".dashboard-widget");
      var $content = $widget.find(".widget-content");

      $content.slideToggle();
      $(this)
        .find(".dashicons")
        .toggleClass("dashicons-arrow-down-alt2 dashicons-arrow-up-alt2");
    });

    // Initialize charts if Chart.js is available
    if (typeof Chart !== "undefined") {
      initPerformanceChart();
    }
  }

  /**
   * Initialize performance chart
   */
  function initPerformanceChart() {
    const ctx = document.getElementById("performance-chart");
    if (!ctx) return;

    // Sample data - in real implementation, this would come from AJAX
    const chartData = {
      labels: ["Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim"],
      datasets: [
        {
          label: "Taux de réussite (%)",
          data: [75, 82, 78, 85, 79, 88, 83],
          borderColor: "#3498db",
          backgroundColor: "rgba(52, 152, 219, 0.1)",
          tension: 0.1,
        },
        {
          label: "Participants",
          data: [12, 18, 15, 22, 17, 25, 20],
          borderColor: "#2ecc71",
          backgroundColor: "rgba(46, 204, 113, 0.1)",
          tension: 0.1,
          yAxisID: "y1",
        },
      ],
    };

    const config = {
      type: "line",
      data: chartData,
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          y: {
            type: "linear",
            display: true,
            position: "left",
            max: 100,
          },
          y1: {
            type: "linear",
            display: true,
            position: "right",
            grid: {
              drawOnChartArea: false,
            },
          },
        },
      },
    };

    window.performanceChart = new Chart(ctx, config);
  }

  /**
   * Update performance chart with new data
   */
  window.updatePerformanceChart = function (chartData) {
    if (window.performanceChart && chartData) {
      window.performanceChart.data = chartData;
      window.performanceChart.update();
    }
  };

  /**
   * Initialize Dashboard functionality
   */
  function initDashboard() {
    // Actions rapides
    $(".quick-action-btn").on("click", function (e) {
      var action = $(this).find("span:last").text();
      showNotification("Redirection vers : " + action, "info");
    });

    // Actualisation des métriques
    setInterval(function () {
      updateDashboardMetrics();
    }, 30000); // Actualise toutes les 30 secondes

    // Debug configuration button
    $("#debug-tables").on("click", function () {
      const button = $(this);
      const results = $("#debug-results");

      button.prop("disabled", true).text("🔄 Vérification...");

      $.ajax({
        url: quiz_ai_ajax.ajax_url,
        type: "POST",
        data: {
          action: "quiz_ai_debug_tables",
          nonce: quiz_ai_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            let html = "<h4>✅ Diagnostic de Configuration</h4>";
            html +=
              '<pre style="background: white; padding: 10px; border: 1px solid #ddd; border-radius: 4px; white-space: pre-wrap;">';
            html += JSON.stringify(response.data, null, 2);
            html += "</pre>";
            results.html(html).show();
          } else {
            results
              .html(
                '<div class="notice notice-error"><p>❌ Erreur: ' +
                  response.data +
                  "</p></div>"
              )
              .show();
          }
        },
        error: function () {
          results
            .html(
              '<div class="notice notice-error"><p>❌ Erreur de communication</p></div>'
            )
            .show();
        },
        complete: function () {
          button.prop("disabled", false).text("🔍 Vérifier Configuration");
        },
      });
    });

    // Fix category column button
    $("#fix-category-column").on("click", function () {
      const button = $(this);
      const results = $("#debug-results");

      if (
        !confirm("Voulez-vous corriger la structure de la base de données?")
      ) {
        return;
      }

      button.prop("disabled", true).text("🔄 Correction...");

      $.ajax({
        url: quiz_ai_ajax.ajax_url,
        type: "POST",
        data: {
          action: "quiz_ai_fix_category_column",
          nonce: quiz_ai_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            results
              .html(
                '<div class="notice notice-success"><p>✅ ' +
                  response.data +
                  "</p></div>"
              )
              .show();
          } else {
            results
              .html(
                '<div class="notice notice-error"><p>❌ Erreur: ' +
                  response.data +
                  "</p></div>"
              )
              .show();
          }
        },
        error: function () {
          results
            .html(
              '<div class="notice notice-error"><p>❌ Erreur de communication</p></div>'
            )
            .show();
        },
        complete: function () {
          button.prop("disabled", false).text("🔧 Corriger Colonne Category");
        },
      });
    });

    // Populate course chunks button
    $("#populate-chunks").on("click", function () {
      const button = $(this);
      const results = $("#debug-results");

      // Debug: Vérifier les variables AJAX
      console.log("[DEBUG] === AJAX VARIABLES CHECK ===");
      console.log(
        "[DEBUG] quiz_ai_ajax defined:",
        typeof quiz_ai_ajax !== "undefined"
      );
      console.log("[DEBUG] quiz_ai_ajax.ajax_url:", quiz_ai_ajax.ajax_url);
      console.log("[DEBUG] quiz_ai_ajax.nonce:", quiz_ai_ajax.nonce);

      if (typeof quiz_ai_ajax === "undefined") {
        results
          .html(
            '<div class="notice notice-error"><p>❌ Erreur: Variables AJAX non définies</p></div>'
          )
          .show();
        return;
      }

      if (
        !confirm(
          "Voulez-vous préparer le contenu pour la génération IA? Cela peut prendre quelques minutes."
        )
      ) {
        return;
      }

      button.prop("disabled", true).text("🔄 Traitement...");

      $.ajax({
        url: quiz_ai_ajax.ajax_url,
        type: "POST",
        data: {
          action: "quiz_ai_populate_course_chunks",
          nonce: quiz_ai_ajax.nonce,
        },
        beforeSend: function () {
          console.log("[DEBUG] Starting populate_course_chunks AJAX call");
          console.log("[DEBUG] URL:", quiz_ai_ajax.ajax_url);
          console.log("[DEBUG] Action: quiz_ai_populate_course_chunks");
          console.log("[DEBUG] Nonce:", quiz_ai_ajax.nonce);
        },
        success: function (response) {
          console.log("[DEBUG] AJAX Success - Raw Response:", response);
          console.log("[DEBUG] Response type:", typeof response);
          console.log("[DEBUG] Response.success:", response.success);
          console.log("[DEBUG] Response.data:", response.data);

          if (response.success) {
            const data = response.data;
            const message =
              typeof data === "string"
                ? data
                : data.message || "Contenu préparé avec succès";
            results
              .html(
                '<div class="notice notice-success"><p>✅ ' +
                  message +
                  "</p></div>"
              )
              .show();
          } else {
            results
              .html(
                '<div class="notice notice-error"><p>❌ Erreur: ' +
                  response.data +
                  "</p></div>"
              )
              .show();
          }
        },
        error: function (xhr, status, error) {
          console.log("[DEBUG] AJAX Error occurred for populate_course_chunks");
          console.log("[DEBUG] Status:", status);
          console.log("[DEBUG] Error:", error);
          console.log("[DEBUG] Response Text:", xhr.responseText);
          console.log("[DEBUG] Status Code:", xhr.status);
          console.log("[DEBUG] Ready State:", xhr.readyState);

          let errorMessage = "❌ Erreur de communication";
          if (xhr.responseText) {
            console.log("[DEBUG] Full Response Text:", xhr.responseText);
            errorMessage +=
              "<br><small>Détails: " +
              xhr.responseText.substring(0, 300) +
              (xhr.responseText.length > 300 ? "..." : "") +
              "</small>";
          }

          results
            .html(
              '<div class="notice notice-error"><p>' +
                errorMessage +
                "</p></div>"
            )
            .show();
        },
        complete: function () {
          button.prop("disabled", false).text("🤖 Préparer Contenu IA");
        },
      });
    });
  }

  /**
   * Initialize Filters functionality
   */
  function initFilters() {
    console.log("Initializing filters...");
    console.log("quiz_ai_ajax object:", quiz_ai_ajax);

    // Bouton réinitialiser
    $(".reset-btn").on("click", function () {
      resetFilters();
    });

    // Auto-apply all filters on change
    $("#filter-date").on("change", function () {
      if ($(this).val() === "custom") {
        showDateRangePicker();
      } else {
        applyFilters();
      }
    });

    // Auto-apply filters on change for all filter inputs
    $("#filter-status, #filter-category").on("change", function () {
      console.log("Filter changed:", $(this).attr("id"), $(this).val());
      applyFilters();
    });

    // Auto-apply search filter with debounce
    let searchTimeout;
    $("#search-quiz").on("input", function () {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(function () {
        applyFilters();
      }, 300);
    });

    // Recherche en temps réel
    $("#search-quiz").on(
      "keyup",
      debounce(function () {
        applyFilters();
      }, 300)
    );
  }

  /**
   * Apply filters to quiz list
   */
  function applyFilters() {
    applyFiltersWithPage(1);
  }

  /**
   * Reset all filters
   */
  function resetFilters() {
    $("#filter-status").val("");
    $("#filter-date").val("");
    $("#filter-category").val("");
    $("#search-quiz").val("");
    applyFilters();
  }

  /**
   * Update quiz table with new data
   */
  function updateQuizTable(quizzes) {
    const $tbody = $(".quiz-list-table tbody");

    $tbody.empty();

    if (quizzes && quizzes.length > 0) {
      quizzes.forEach((quiz) => {
        const row = renderQuizRow(quiz);
        $tbody.append(row);
      });

      // Re-initialize row events
      initQuizRowEvents();
    } else {
      $tbody.append(`
        <tr class="no-items">
          <td colspan="8" style="text-align: center; padding: 40px;">
            <h3>Aucun quiz trouvé</h3>
            <p>Aucun quiz ne correspond aux critères de recherche.</p>
          </td>
        </tr>
      `);
    }
  }

  /**
   * Update quiz table with HTML content
   */
  function updateQuizTableWithHTML(html) {
    const $tbody = $(".quiz-list-table tbody");
    $tbody.html(html);

    // Re-initialize row events after updating HTML
    initQuizRowEvents();
    initCopyCode();
  }

  /**
   * Render a single quiz row
   */
  function renderQuizRow(quiz) {
    const statusClass = getStatusClass(quiz.status);
    const courseNames = quiz.course_titles || ["Aucun cours"];
    const categoryNames = quiz.category_names || ["Non catégorisé"];

    return `
      <tr id="quiz-${quiz.id}" class="quiz-item">
        <th scope="row" class="check-column">
          <input id="cb-select-${
            quiz.id
          }" type="checkbox" name="quiz[]" value="${quiz.id}">
        </th>
        <td class="title column-title has-row-actions column-primary">
          <strong>
            <span class="row-title">${quiz.title || "Quiz sans titre"}</span>
          </strong>
          <div class="quiz-description">${
            quiz.description || "Aucune description"
          }</div>
          <div class="quiz-meta">
            <small>
              <strong>Cours:</strong> ${courseNames.join(", ")}
              | <strong>Catégories:</strong> ${categoryNames.join(", ")}
              ${
                quiz.ai_generated ? '| <span class="ai-badge">🤖 IA</span>' : ""
              }
            </small>
          </div>
          <div class="row-actions">
            <span class="edit"><a href="admin.php?page=quiz-ia-pro-edit&quiz_id=${
              quiz.id
            }">Modifier</a> | </span>
            ${
              quiz.status === "published"
                ? '<span class="unpublish"><a href="#" class="quiz-action" data-action="unpublish" data-quiz-id="' +
                  quiz.id +
                  '">Dépublier</a> | </span>'
                : '<span class="publish"><a href="#" class="quiz-action" data-action="publish" data-quiz-id="' +
                  quiz.id +
                  '">Publier</a> | </span>'
            }
            <span class="trash"><a href="#" class="quiz-action" data-action="delete" data-quiz-id="${
              quiz.id
            }">Supprimer</a></span>
          </div>
        </td>
        <td class="code column-code">
          <span class="quiz-code">${quiz.quiz_code}</span>
          <button type="button" class="button-link copy-code" title="Copier le code">
            <span class="dashicons dashicons-admin-page"></span>
          </button>
        </td>
        <td class="questions column-questions">
          <span class="questions-count">${
            quiz.question_count || quiz.total_questions || 0
          }</span>
        </td>
        <td class="views column-views">
          <span class="views-count">${quiz.views || 0}</span>
        </td>
        <td class="participants column-participants">
          <span class="participants-count">${quiz.participants || 0}</span>
        </td>
        <td class="status column-status">
          <span class="status-badge ${statusClass}">${quiz.status}</span>
        </td>
        <td class="date column-date">
          <abbr title="${quiz.created_at}">${timeAgo(quiz.created_at)}</abbr>
        </td>
      </tr>
    `;
  }

  /**
   * Initialize row events
   */
  function initQuizRowEvents() {
    // Quiz actions
    $(".quiz-action")
      .off("click")
      .on("click", function (e) {
        e.preventDefault();
        const action = $(this).data("action");
        const quizId = $(this).data("quiz-id");
        const quizTitle = $(this).closest("tr").find(".row-title").text();

        if (action === "delete") {
          if (confirm(`Êtes-vous sûr de vouloir supprimer "${quizTitle}" ?`)) {
            executeQuizAction(action, [quizId]);
          }
        } else {
          executeQuizAction(action, [quizId]);
        }
      });

    // Copy code buttons
    $(".copy-code")
      .off("click")
      .on("click", function () {
        const code = $(this).siblings(".quiz-code").text();
        navigator.clipboard.writeText(code).then(() => {
          showNotification("Code copié dans le presse-papiers", "success");
        });
      });

    // Checkbox events
    $('.quiz-list-table tbody input[type="checkbox"]')
      .off("change")
      .on("change", function () {
        updateBulkActionButton();
        updateSelectAllCheckbox();
      });
  }

  /**
   * Helper functions
   */
  function getStatusClass(status) {
    switch (status) {
      case "published":
        return "status-published";
      case "draft":
        return "status-draft";
      case "pending":
        return "status-pending";
      default:
        return "status-archived";
    }
  }

  function getCurrentSortColumn() {
    const $sorted = $(".quiz-list-table th.sorted");
    return $sorted.length ? $sorted.attr("id") : "created_at";
  }

  function getCurrentSortOrder() {
    const $sorted = $(".quiz-list-table th.sorted");
    return $sorted.hasClass("desc") ? "desc" : "asc";
  }

  /**
   * Update quiz pagination
   */
  function updateQuizPagination(total, totalPages, currentPage) {
    const $pagination = $(".tablenav-pages");

    if (totalPages <= 1) {
      $pagination.hide();
      return;
    }

    let html = `
      <span class="displaying-num">${total} éléments</span>
      <span class="pagination-links">
    `;

    // Previous button
    if (currentPage > 1) {
      html += `<a class="prev-page button" data-page="${
        currentPage - 1
      }">‹</a>`;
    } else {
      html += `<span class="tablenav-pages-navspan button disabled">‹</span>`;
    }

    // Page input
    html += `
      <span class="paging-input">
        <input class="current-page" type="text" value="${currentPage}" size="1" data-total-pages="${totalPages}">
        <span class="tablenav-paging-text"> sur <span class="total-pages">${totalPages}</span></span>
      </span>
    `;

    // Next button
    if (currentPage < totalPages) {
      html += `<a class="next-page button" data-page="${
        currentPage + 1
      }">›</a>`;
    } else {
      html += `<span class="tablenav-pages-navspan button disabled">›</span>`;
    }

    html += `</span>`;

    $pagination.html(html).show();

    // Bind pagination events
    $pagination.find(".prev-page, .next-page").on("click", function (e) {
      e.preventDefault();
      const page = $(this).data("page");
      applyFiltersWithPage(page);
    });

    $pagination.find(".current-page").on("keypress", function (e) {
      if (e.which === 13) {
        const page = Math.max(
          1,
          Math.min(parseInt($(this).val()) || 1, totalPages)
        );
        applyFiltersWithPage(page);
      }
    });
  }

  /**
   * Apply filters with specific page
   */
  function applyFiltersWithPage(page) {
    console.log("Applying filters with page:", page);

    const filters = {
      status: $("#filter-status").val(),
      date: $("#filter-date").val(),
      category: $("#filter-category").val(),
      search: $("#search-quiz").val(),
      sort_by: getCurrentSortColumn(),
      sort_order: getCurrentSortOrder(),
    };

    console.log("Filters:", filters);

    showLoader();

    $.ajax({
      url: quiz_ai_ajax.ajax_url,
      type: "POST",
      data: {
        action: "quiz_ai_filter_quizzes",
        nonce: quiz_ai_ajax.nonce,
        ...filters,
        page: page,
      },
      success: function (response) {
        console.log("AJAX Response:", response);
        if (response.success) {
          console.log("Updating table with HTML");
          updateQuizTableWithHTML(response.data.html);
          updateQuizPagination(
            response.data.total,
            response.data.total_pages,
            response.data.current_page
          );
        } else {
          console.error("Filter error:", response.data);
          showNotification("Erreur lors du filtrage", "error");
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Error:", xhr, status, error);
        showNotification("Erreur de communication", "error");
      },
      complete: function () {
        hideLoader();
      },
    });
  }

  /**
   * Initialize Bulk Actions
   */
  function initBulkActions() {
    // Sélection tout/rien
    $("#cb-select-all-1, #cb-select-all-2").on("change", function () {
      var checked = $(this).prop("checked");
      $('.quiz-list-table input[type="checkbox"]').prop("checked", checked);
      updateBulkActionButton();
    });

    // Actions groupées
    $("#doaction, #doaction2").on("click", function (e) {
      e.preventDefault();
      var action = $(this).siblings("select").val();
      var selected = getSelectedQuizzes();

      if (action === "-1") {
        showNotification("Veuillez sélectionner une action", "warning");
        return;
      }

      if (selected.length === 0) {
        showNotification("Veuillez sélectionner au moins un quiz", "warning");
        return;
      }

      // Confirm destructive actions
      if (action === "delete" || action === "archive") {
        if (
          !confirm(
            `Êtes-vous sûr de vouloir ${
              action === "delete" ? "supprimer" : "archiver"
            } ${selected.length} quiz(s) ?`
          )
        ) {
          return;
        }
      }

      executeBulkAction(action, selected);
    });
  }

  /**
   * Execute bulk action
   */
  function executeBulkAction(action, quizIds) {
    showLoader();

    $.ajax({
      url: quiz_ai_ajax.ajax_url,
      type: "POST",
      data: {
        action: "quiz_ai_bulk_quiz_action",
        nonce: quiz_ai_ajax.nonce,
        action_type: action,
        quiz_ids: quizIds,
      },
      success: function (response) {
        if (response.success) {
          showNotification(response.data.message, "success");
          // Refresh the quiz list
          applyFilters();
        } else {
          showNotification("Erreur lors de l'exécution de l'action", "error");
        }
      },
      error: function () {
        showNotification("Erreur de communication", "error");
      },
      complete: function () {
        hideLoader();
      },
    });
  }

  /**
   * Execute single quiz action
   */
  function executeQuizAction(action, quizIds) {
    executeBulkAction(action, quizIds);
  }

  /**
   * Get selected quiz IDs
   */
  function getSelectedQuizzes() {
    var selected = [];
    $('.quiz-list-table tbody input[type="checkbox"]:checked').each(
      function () {
        selected.push($(this).val());
      }
    );
    return selected;
  }

  /**
   * Update bulk action button state
   */
  function updateBulkActionButton() {
    var selected = getSelectedQuizzes();
    var $buttons = $("#doaction, #doaction2");

    if (selected.length > 0) {
      $buttons.prop("disabled", false);
    } else {
      $buttons.prop("disabled", true);
    }
  }

  /**
   * Update select all checkbox state
   */
  function updateSelectAllCheckbox() {
    var $checkboxes = $('.quiz-list-table tbody input[type="checkbox"]');
    var $selectAll = $("#cb-select-all-1, #cb-select-all-2");
    var total = $checkboxes.length;
    var checked = $checkboxes.filter(":checked").length;

    if (checked === 0) {
      $selectAll.prop("indeterminate", false).prop("checked", false);
    } else if (checked === total) {
      $selectAll.prop("indeterminate", false).prop("checked", true);
    } else {
      $selectAll.prop("indeterminate", true);
    }
  }

  /**
   * Initialize Copy Code functionality
   */
  function initCopyCode() {
    $(".copy-code").on("click", function () {
      var code = $(this).siblings(".quiz-code").text();
      copyToClipboard(code);
      showNotification("Code copié : " + code, "success");
    });
  }

  /**
   * Initialize Tooltips
   */
  function initTooltips() {
    if ($.ui && $.ui.tooltip) {
      $("[title]").tooltip({
        position: { my: "center bottom-20", at: "center top" },
      });
    }
  }

  /**
   * Search quizzes
   */
  function searchQuizzes(search) {
    var $rows = $(".quiz-list-table tbody tr");

    if (search === "") {
      $rows.show();
      return;
    }

    $rows.each(function () {
      var $row = $(this);
      var title = $row.find(".row-title").text().toLowerCase();
      var code = $row.find(".quiz-code").text().toLowerCase();
      var description = $row.find(".quiz-description").text().toLowerCase();

      var matches =
        title.includes(search.toLowerCase()) ||
        code.includes(search.toLowerCase()) ||
        description.includes(search.toLowerCase());

      $row.toggle(matches);
    });

    updateResultsCount();
  }

  /**
   * Update bulk action button state
   */
  function updateBulkActionButton() {
    var selected = getSelectedQuizzes();
    var $buttons = $("#doaction, #doaction2");

    if (selected.length > 0) {
      $buttons.prop("disabled", false);
    } else {
      $buttons.prop("disabled", true);
    }
  }

  /**
   * Update select all checkbox
   */
  function updateSelectAllCheckbox() {
    var total = $('.quiz-list-table tbody input[type="checkbox"]').length;
    var selected = $(
      '.quiz-list-table tbody input[type="checkbox"]:checked'
    ).length;
    var $selectAll = $("#cb-select-all-1, #cb-select-all-2");

    if (selected === 0) {
      $selectAll.prop("checked", false).prop("indeterminate", false);
    } else if (selected === total) {
      $selectAll.prop("checked", true).prop("indeterminate", false);
    } else {
      $selectAll.prop("checked", false).prop("indeterminate", true);
    }
  }

  /**
   * Get selected quizzes
   */
  function getSelectedQuizzes() {
    var selected = [];
    $('.quiz-list-table tbody input[type="checkbox"]:checked').each(
      function () {
        selected.push($(this).val());
      }
    );
    return selected;
  }

  /**
   * Execute bulk action
   */
  /**
   * Copy text to clipboard
   */
  function copyToClipboard(text) {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text);
    } else {
      var textArea = document.createElement("textarea");
      textArea.value = text;
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();
      document.execCommand("copy");
      document.body.removeChild(textArea);
    }
  }

  /**
   * Show notification
   */
  function showNotification(message, type) {
    type = type || "info";

    var $notification = $(
      '<div class="quiz-notification quiz-' + type + '">' + message + "</div>"
    );

    $("body").append($notification);

    $notification.animate(
      {
        top: "20px",
        opacity: 1,
      },
      300
    );

    setTimeout(function () {
      $notification.animate(
        {
          top: "-100px",
          opacity: 0,
        },
        300,
        function () {
          $(this).remove();
        }
      );
    }, 3000);
  }

  /**
   * Show loader
   */
  function showLoader() {
    if (!$(".quiz-loader").length) {
      var $loader = $(
        '<div class="quiz-loader"><div class="quiz-spinner"></div><span>Chargement...</span></div>'
      );
      $("body").append($loader);
    }
    $(".quiz-loader").show();
  }

  /**
   * Hide loader
   */
  function hideLoader() {
    $(".quiz-loader").hide();
  }

  /**
   * Update results count
   */
  function updateResultsCount() {
    var visible = $(".quiz-list-table tbody tr:visible").length;
    $(".displaying-num").text(visible + " éléments");
  }

  /**
   * Update dashboard metrics
   */
  function updateDashboardMetrics() {
    // Simulation de mise à jour des métriques
    console.log("Updating dashboard metrics...");
  }

  /**
   * Update stats data
   */
  function updateStatsData(period, quizId = 0, page = 1) {
    showLoader();

    $.ajax({
      url: quiz_ai_ajax.ajax_url,
      type: "POST",
      data: {
        action: "quiz_ai_filter_stats",
        nonce: quiz_ai_ajax.nonce,
        period: period,
        quiz_id: quizId,
        search: $(".search-participants").val(),
        page: page,
      },
      success: function (response) {
        if (response.success) {
          // Update stats cards
          updateStatsCards(response.data.stats);

          // Update results table
          updateResultsTable(
            response.data.results,
            response.data.total_results,
            response.data.total_pages,
            response.data.current_page
          );

          // Update charts if available
          if (typeof window.updatePerformanceChart === "function") {
            window.updatePerformanceChart(response.data.stats.chart_data);
          }

          showNotification("Statistiques mises à jour", "success");
        } else {
          showNotification(
            "Erreur lors de la mise à jour des statistiques",
            "error"
          );
        }
      },
      error: function () {
        showNotification("Erreur de communication", "error");
      },
      complete: function () {
        hideLoader();
      },
    });
  }

  /**
   * Search participants
   */
  function searchParticipants(search) {
    // For real-time search, trigger the filter with current settings
    const period = $("#stats-period").val();
    const quizId = $("#stats-quiz-filter").val();

    updateStatsData(period, quizId, 1);
  }

  /**
   * Update stats cards with new data
   */
  function updateStatsCards(stats) {
    if (stats) {
      $(".stats-overview .metric-value").each(function (index) {
        const $this = $(this);
        const key = $this.data("metric");
        if (stats[key] !== undefined) {
          $this.text(stats[key]);
        }
      });
    }
  }

  /**
   * Update results table with new data
   */
  function updateResultsTable(results, totalResults, totalPages, currentPage) {
    const $tbody = $(".results-table tbody");
    const $pagination = $(".results-pagination");

    // Clear existing rows
    $tbody.empty();

    if (results && results.length > 0) {
      // Add new rows
      results.forEach((result) => {
        const row = `
          <tr>
            <td class="participant column-participant">
              <div class="participant-details">
                <strong>${result.user_name || "Anonyme"}</strong>
                <div class="participant-email">${result.user_email || ""}</div>
              </div>
            </td>
            <td class="quiz column-quiz">
              <a href="#" class="quiz-title">${result.quiz_title}</a>
            </td>
            <td class="score column-score">
              <span class="score-value ${getScoreBadgeClass(
                result.percentage
              )}">${result.percentage}%</span>
              <div class="score-details">${result.correct_answers}/${
          result.total_questions
        }</div>
            </td>
            <td class="time column-time">
              <span class="time-duration">${formatTimeDuration(
                result.time_spent
              )}</span>
            </td>
            <td class="attempts column-attempts">
              <span class="attempts-count">${result.attempt_number}</span>
            </td>
            <td class="date column-date">
              <abbr title="${result.submitted_at}">
                ${timeAgo(result.submitted_at)}
              </abbr>
            </td>
            <td class="actions column-actions">
              <a href="#" class="button button-small">Voir détails</a>
            </td>
          </tr>
        `;
        $tbody.append(row);
      });

      // Update pagination
      updatePagination($pagination, totalResults, totalPages, currentPage);
    } else {
      $tbody.append(
        '<tr><td colspan="7" style="text-align: center; padding: 40px;">Aucun résultat trouvé</td></tr>'
      );
      $pagination.hide();
    }
  }

  /**
   * Update pagination controls
   */
  function updatePagination($container, total, totalPages, currentPage) {
    if (totalPages <= 1) {
      $container.hide();
      return;
    }

    let html = `
      <div class="tablenav-pages">
        <span class="displaying-num">${total} résultats</span>
        <span class="pagination-links">
    `;

    // Previous button
    if (currentPage > 1) {
      html += `<a class="prev-page button" data-page="${
        currentPage - 1
      }">‹</a>`;
    } else {
      html += `<span class="tablenav-pages-navspan button disabled">‹</span>`;
    }

    // Page input
    html += `
      <span class="paging-input">
        <input class="current-page" type="text" value="${currentPage}" size="1" data-total-pages="${totalPages}">
        <span class="tablenav-paging-text"> sur <span class="total-pages">${totalPages}</span></span>
      </span>
    `;

    // Next button
    if (currentPage < totalPages) {
      html += `<a class="next-page button" data-page="${
        currentPage + 1
      }">›</a>`;
    } else {
      html += `<span class="tablenav-pages-navspan button disabled">›</span>`;
    }

    html += `</span></div>`;

    $container.html(html).show();

    // Bind pagination events
    $container.find(".prev-page, .next-page").on("click", function (e) {
      e.preventDefault();
      const page = $(this).data("page");
      const period = $("#stats-period").val();
      const quizId = $("#stats-quiz-filter").val();
      updateStatsData(period, quizId, page);
    });

    $container.find(".current-page").on("keypress", function (e) {
      if (e.which === 13) {
        const page = Math.max(
          1,
          Math.min(parseInt($(this).val()) || 1, totalPages)
        );
        const period = $("#stats-period").val();
        const quizId = $("#stats-quiz-filter").val();
        updateStatsData(period, quizId, page);
      }
    });
  }

  /**
   * Helper functions
   */
  function getScoreBadgeClass(percentage) {
    if (percentage >= 80) return "score-excellent";
    if (percentage >= 60) return "score-good";
    return "score-average";
  }

  function formatTimeDuration(seconds) {
    if (!seconds || seconds <= 0) return "--";
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}:${remainingSeconds.toString().padStart(2, "0")}`;
  }

  function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 60) return `${diffMins} minutes ago`;
    if (diffHours < 24) return `${diffHours} hours ago`;
    return `${diffDays} days ago`;
  }

  /**
   * Delete quiz
   */
  function deleteQuiz($row) {
    var quizId = $row.find('input[type="checkbox"]').val();

    if (!quizId) {
      showNotification("Impossible de trouver l'ID du quiz", "error");
      return;
    }

    showLoader();

    $.ajax({
      url: quiz_ai_ajax.ajax_url,
      type: "POST",
      data: {
        action: "quiz_ai_bulk_quiz_action",
        nonce: quiz_ai_ajax.nonce,
        action_type: "delete",
        quiz_ids: [quizId],
      },
      success: function (response) {
        hideLoader();
        if (response.success) {
          showNotification(response.data.message, "success");
          $row.fadeOut(function () {
            $(this).remove();
            updateResultsCount();
          });
        } else {
          showNotification(
            response.data || "Erreur lors de la suppression",
            "error"
          );
        }
      },
      error: function () {
        hideLoader();
        showNotification("Erreur de communication", "error");
      },
    });
  }

  /**
   * Load page
   */
  function loadPage(page) {
    showLoader();

    setTimeout(function () {
      hideLoader();
      showNotification("Page " + page + " chargée", "info");
    }, 800);
  }

  /**
   * Show date range picker
   */
  function showDateRangePicker() {
    // Implémentation d'un sélecteur de plage de dates
    var startDate = prompt("Date de début (YYYY-MM-DD):");
    var endDate = prompt("Date de fin (YYYY-MM-DD):");

    if (startDate && endDate) {
      showNotification(
        "Période personnalisée : " + startDate + " - " + endDate,
        "info"
      );
    } else {
      $("#filter-date").val("");
    }
  }

  /**
   * Debounce function
   */
  function debounce(func, wait) {
    var timeout;
    return function executedFunction() {
      var context = this;
      var args = arguments;
      var later = function () {
        timeout = null;
        func.apply(context, args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  /**
   * Initialize Quiz Generator functionality
   */
  function initQuizGenerator() {
    // Course/Category selection logic
    $("#course_id").on("change", function () {
      if ($(this).val()) {
        $("#category_id").val("");
        showNotification("Cours sélectionné", "info");
      }
    });

    $("#category_id").on("change", function () {
      if ($(this).val()) {
        $("#course_id").val("");
        showNotification("Catégorie sélectionnée", "info");
      }
    });

    // Featured image type selection
    $('input[name="featured_image_type"]').on("change", function () {
      var value = $(this).val();
      $(".conditional-field").hide();

      if (value === "url") {
        $("#image-url-field").show();
      } else if (value === "media") {
        $("#media-library-field").show();
      }
    });

    // Media library selection (simulation)
    $("#select-media").on("click", function () {
      // In a real implementation, this would open WordPress media library
      var mediaData = {
        id: 123,
        filename: "quiz-featured-image.jpg",
        url: "https://example.com/wp-content/uploads/quiz-featured-image.jpg",
        size: "150 KB",
      };

      $("#featured_image_id").val(mediaData.id);
      $("#selected-media")
        .addClass("has-media")
        .html(
          '<div style="display: flex; align-items: center;">' +
            '<img src="' +
            mediaData.url +
            '" alt="Featured Image">' +
            '<div class="media-info">' +
            '<div class="media-filename">' +
            mediaData.filename +
            "</div>" +
            '<div class="media-size">' +
            mediaData.size +
            "</div>" +
            "</div>" +
            "</div>"
        );

      showNotification("Image sélectionnée depuis la médiathèque", "success");
    });

    // Form validation
    $("#quiz-generator-form").on("submit", function (e) {
      e.preventDefault();

      if (!validateGeneratorForm()) {
        return false;
      }

      startQuizGeneration();
    });

    // Cancel generation
    $("#cancel-generation").on("click", function () {
      cancelGeneration();
    });

    // Auto-save functionality
    var autoSaveTimer;
    $(
      "#quiz-generator-form input, #quiz-generator-form select, #quiz-generator-form textarea"
    ).on("change keyup", function () {
      clearTimeout(autoSaveTimer);
      autoSaveTimer = setTimeout(function () {
        autoSave();
      }, 5000); // Auto-save after 5 seconds of inactivity
    });
  }

  /**
   * Validate generator form
   */
  function validateGeneratorForm() {
    var isValid = true;
    var errors = [];

    // Check required fields
    if (!$("#quiz_name").val().trim()) {
      errors.push("Le nom du quiz est requis");
      $("#quiz_name").focus();
      isValid = false;
    }

    if (!$("#quiz_type").val()) {
      errors.push("Le type de quiz est requis");
      isValid = false;
    }

    // Check for course or category selection - check global variables first
    var courseSelected = false;
    var categorySelected = false;

    // Check global selectedCourses and selectedCategories arrays (from generate-quiz.php)
    if (
      typeof window.selectedCourses !== "undefined" &&
      window.selectedCourses &&
      window.selectedCourses.length > 0
    ) {
      courseSelected = true;
    }

    if (
      typeof window.selectedCategories !== "undefined" &&
      window.selectedCategories &&
      window.selectedCategories.length > 0
    ) {
      categorySelected = true;
    }

    // Fallback: Check various possible field names and formats
    if (!courseSelected) {
      if (
        $("#course_id").val() ||
        $("#course_ids").val() ||
        $("select[name='course_id']").val() ||
        $("select[name='course_ids']").val() ||
        $("input[name='course_id']").val() ||
        $("input[name='course_ids']").val()
      ) {
        courseSelected = true;
      }

      // Check for multiple course selections (checkboxes)
      if (
        $("input[name='course_ids[]']:checked").length > 0 ||
        $("input[name='selected_courses[]']:checked").length > 0
      ) {
        courseSelected = true;
      }
    }

    if (!categorySelected) {
      // Check various possible category field names and formats
      if (
        $("#category_id").val() ||
        $("#category_ids").val() ||
        $("select[name='category_id']").val() ||
        $("select[name='category_ids']").val() ||
        $("input[name='category_id']").val() ||
        $("input[name='category_ids']").val()
      ) {
        categorySelected = true;
      }

      // Check for multiple category selections (checkboxes)
      if (
        $("input[name='category_ids[]']:checked").length > 0 ||
        $("input[name='selected_categories[]']:checked").length > 0
      ) {
        categorySelected = true;
      }
    }

    console.log("Validation debug:");
    console.log("Course selected:", courseSelected);
    console.log("Category selected:", categorySelected);
    console.log(
      "Global selectedCourses:",
      typeof window.selectedCourses !== "undefined"
        ? window.selectedCourses
        : "undefined"
    );
    console.log(
      "Global selectedCategories:",
      typeof window.selectedCategories !== "undefined"
        ? window.selectedCategories
        : "undefined"
    );
    console.log("Course ID value:", $("#course_id").val());
    console.log("Category ID value:", $("#category_id").val());

    if (!courseSelected && !categorySelected) {
      errors.push("Veuillez sélectionner un cours ou une catégorie");
      isValid = false;
    }

    var numQuestions = parseInt($("#num_questions").val());
    if (!numQuestions || numQuestions < 1 || numQuestions > 50) {
      errors.push("Le nombre de questions doit être entre 1 et 50");
      isValid = false;
    }

    if (!isValid) {
      console.log("Validation errors:", errors);
      showNotification("Erreurs de validation: " + errors.join(", "), "error");
    }

    return isValid;
  }

  /**
   * Start quiz generation process
   */
  function startQuizGeneration() {
    // Show generation modal
    $("#generation-modal").show();

    // Reset progress
    $(".progress-step").removeClass("active completed");
    $(".progress-step[data-step='1']").addClass("active");
    $(".progress-fill").css("width", "0%");
    $("#generation-log").empty();

    // Collect form data
    var formData = {
      quiz_name: $("#quiz_name").val(),
      quiz_type: $("#quiz_type").val(),
      form_type: $("#form_type").val(),
      grading_system: $("#grading_system").val(),
      ai_provider: $("#ai_provider").val(),
      num_questions: $("#num_questions").val(),
      difficulty_level: $("#difficulty_level").val(),
      language: $("#language").val(),
      additional_instructions: $("#additional_instructions").val(),
      time_limit: $("#time_limit").val(),
      questions_per_page: $("#questions_per_page").val(),

      // Course and category selections (get from global variables if they exist)
      course_ids:
        typeof window.selectedCourses !== "undefined" && window.selectedCourses
          ? window.selectedCourses.map((course) => course.id)
          : [],
      category_ids:
        typeof window.selectedCategories !== "undefined" &&
        window.selectedCategories
          ? window.selectedCategories.map((category) => category.id)
          : [],

      // Fallback to single selection fields if available
      course_id: $("#course_id").val() || null,
      category_id: $("#category_id").val() || null,

      // Display options
      show_contact_form: $('input[name="show_contact_form"]').is(":checked")
        ? "1"
        : "0",
      show_page_number: $('input[name="show_page_number"]').is(":checked")
        ? "1"
        : "0",
      show_question_images_results: $(
        'input[name="show_question_images_results"]'
      ).is(":checked")
        ? "1"
        : "0",
      show_progress_bar: $('input[name="show_progress_bar"]').is(":checked")
        ? "1"
        : "0",

      // Advanced settings
      require_login: $('input[name="require_login"]').is(":checked")
        ? "1"
        : "0",
      disable_first_page: $('input[name="disable_first_page"]').is(":checked")
        ? "1"
        : "0",
      enable_comments: $('input[name="enable_comments"]').is(":checked")
        ? "1"
        : "0",

      // Featured image settings
      featured_image_type:
        $('input[name="featured_image_type"]:checked').val() || "none",
      featured_image_url: $("#featured_image_url").val() || "",
      featured_image_id: $("#featured_image_id").val() || "",

      settings: collectFormSettings(),
    };

    // Debug: Log collected form data
    console.log("=== FORM DATA DEBUG ===");
    console.log("Course IDs:", formData.course_ids);
    console.log("Category IDs:", formData.category_ids);
    console.log("Course ID (single):", formData.course_id);
    console.log("Category ID (single):", formData.category_id);
    console.log("Quiz Name:", formData.quiz_name);
    console.log("Quiz Type:", formData.quiz_type);
    console.log(
      "Selected Courses (global):",
      typeof window.selectedCourses !== "undefined"
        ? window.selectedCourses
        : "undefined"
    );
    console.log(
      "Selected Categories (global):",
      typeof window.selectedCategories !== "undefined"
        ? window.selectedCategories
        : "undefined"
    );
    console.log("Form Data:", formData);
    console.log("=== END FORM DATA DEBUG ===");

    // Start real AJAX generation
    generateQuizWithAjax(formData);
  }

  /**
   * Generate quiz with AJAX
   */
  function generateQuizWithAjax(formData) {
    addGenerationLog("Démarrage de la génération...", "info");
    $(".progress-step[data-step='1']").addClass("active");
    $(".progress-fill").animate({ width: "25%" }, 500);

    $.ajax({
      url: quiz_ai_ajax.ajax_url,
      type: "POST",
      dataType: "text", // Changed from default to handle mixed response
      data: {
        action: "generate_quiz",
        form_data: formData,
        nonce: quiz_ai_ajax.nonce,
      },
      beforeSend: function () {
        addGenerationLog("Envoi des données au serveur...", "info");
        updateGenerationStep(2, "Analyse du contenu...");
      },
      success: function (response) {
        console.log("=== AJAX RESPONSE DEBUG ===");
        console.log("Response type:", typeof response);
        console.log("Response length:", response.length);
        console.log(
          "Response preview (first 500 chars):",
          response.substring(0, 500)
        );
        console.log(
          "Response ending (last 500 chars):",
          response.substring(Math.max(0, response.length - 500))
        );

        // Look for any script tags or other contamination
        var scriptTags = response.match(/<script[^>]*>.*?<\/script>/g);
        if (scriptTags) {
          console.warn("Found script tags in response:", scriptTags.length);
          scriptTags.forEach(function (tag, index) {
            console.warn(
              "Script tag " + (index + 1) + ":",
              tag.substring(0, 100) + "..."
            );
          });
        }

        // Look for WordPress error divs
        var errorDivs = response.match(/<div[^>]*error[^>]*>.*?<\/div>/g);
        if (errorDivs) {
          console.warn("Found error divs in response:", errorDivs.length);
          errorDivs.forEach(function (div, index) {
            console.warn("Error div " + (index + 1) + ":", div);
          });
        }

        // Extract JSON from response (handle script tag pollution)
        // Look for the main JSON response that contains quiz data
        var jsonStart = response.indexOf('{"success":true,"data":{');
        var jsonResponse = null;

        if (jsonStart !== -1) {
          var jsonString = response.substring(jsonStart);
          console.log(
            "Extracted JSON string (first 200 chars):",
            jsonString.substring(0, 200)
          );

          // Find the end of the JSON by counting braces
          var braceCount = 0;
          var jsonEnd = -1;
          var inString = false;
          var escapeNext = false;

          for (var i = 0; i < jsonString.length; i++) {
            var char = jsonString[i];

            if (escapeNext) {
              escapeNext = false;
              continue;
            }

            if (char === "\\") {
              escapeNext = true;
              continue;
            }

            if (char === '"') {
              inString = !inString;
              continue;
            }

            if (!inString) {
              if (char === "{") {
                braceCount++;
              } else if (char === "}") {
                braceCount--;
                if (braceCount === 0) {
                  jsonEnd = i + 1;
                  break;
                }
              }
            }
          }

          if (jsonEnd > 0) {
            jsonString = jsonString.substring(0, jsonEnd);
            console.log("Cleaned JSON string length:", jsonString.length);
          }

          try {
            jsonResponse = JSON.parse(jsonString);
            console.log(
              "Parsed JSON successfully - has data:",
              !!jsonResponse.data
            );
            console.log("Quiz code available:", !!jsonResponse.data?.quiz_code);
          } catch (e) {
            console.error("JSON parsing failed:", e);
            console.log("Attempting fallback parsing...");

            // Fallback: Look for smaller JSON structure if main parsing fails
            var fallbackStart = response.lastIndexOf('{"success"');
            if (fallbackStart !== -1 && fallbackStart !== jsonStart) {
              var fallbackString = response.substring(fallbackStart);
              var fallbackEnd = fallbackString.indexOf("}}") + 2;
              if (fallbackEnd > 2) {
                fallbackString = fallbackString.substring(0, fallbackEnd);
                try {
                  var fallbackResponse = JSON.parse(fallbackString);
                  console.log("Fallback parsing successful:", fallbackResponse);
                  // Use fallback if it doesn't have the expected structure
                  if (!jsonResponse) {
                    jsonResponse = fallbackResponse;
                  }
                } catch (fallbackError) {
                  console.error("Fallback parsing also failed:", fallbackError);
                }
              }
            }
          }
        } else {
          console.error("No main JSON found, looking for any JSON structure");
          // Last resort: try to find any JSON structure
          var lastJsonStart = response.lastIndexOf('{"success"');
          if (lastJsonStart !== -1) {
            var lastJsonString = response.substring(lastJsonStart);
            try {
              // Simple extraction for basic JSON
              var simpleEnd = lastJsonString.indexOf("}}") + 2;
              if (simpleEnd > 2) {
                lastJsonString = lastJsonString.substring(0, simpleEnd);
                jsonResponse = JSON.parse(lastJsonString);
                console.log("Last resort parsing successful:", jsonResponse);
              }
            } catch (e) {
              console.error("Last resort parsing failed:", e);
            }
          }
        }

        console.log("Final response object:", jsonResponse);
        console.log("=== END AJAX RESPONSE DEBUG ===");

        if (jsonResponse && jsonResponse.success) {
          console.log("=== PROCESSING SUCCESS RESPONSE ===");
          console.log("Response structure:", Object.keys(jsonResponse));
          console.log("Has data object:", !!jsonResponse.data);
          if (jsonResponse.data) {
            console.log("Data keys:", Object.keys(jsonResponse.data));
            console.log("Quiz code:", jsonResponse.data.quiz_code);
            console.log("Quiz ID:", jsonResponse.data.quiz_id);
          }

          // Log comprehensive debug information
          if (jsonResponse.data && jsonResponse.data.debug_info) {
            console.log("=== 🔍 COMPREHENSIVE QUIZ GENERATION DEBUG ===");
            console.log(
              "🕐 Timestamp:",
              jsonResponse.data.debug_info.timestamp
            );
            console.log("👤 User ID:", jsonResponse.data.debug_info.user_id);

            // Course Selection Debug
            if (jsonResponse.data.debug_info.course_selection) {
              console.log("📚 COURSE SELECTION:");
              console.log(
                "  - Form course_id:",
                jsonResponse.data.debug_info.course_selection.form_course_id
              );
              console.log(
                "  - Form course_ids:",
                jsonResponse.data.debug_info.course_selection.form_course_ids
              );
              console.log(
                "  - Selected courses:",
                jsonResponse.data.debug_info.course_selection.selected_courses
              );
              console.log(
                "  - Category ID:",
                jsonResponse.data.debug_info.course_selection.category_id
              );
            }

            // AI Parameters Debug
            if (jsonResponse.data.debug_info.ai_parameters) {
              console.log("🤖 AI PARAMETERS:");
              console.log(
                "  - Provider:",
                jsonResponse.data.debug_info.ai_parameters.provider
              );
              console.log(
                "  - Questions:",
                jsonResponse.data.debug_info.ai_parameters.num_questions
              );
              console.log(
                "  - Type:",
                jsonResponse.data.debug_info.ai_parameters.quiz_type
              );
              console.log(
                "  - Difficulty:",
                jsonResponse.data.debug_info.ai_parameters.difficulty
              );
              console.log(
                "  - Language:",
                jsonResponse.data.debug_info.ai_parameters.language
              );
              console.log(
                "  - Topic:",
                jsonResponse.data.debug_info.ai_parameters.topic
              );
            }

            // RAG Content Debug
            if (jsonResponse.data.debug_info.rag_content) {
              console.log("📖 RAG CONTENT RETRIEVAL:");
              console.log(
                "  - Course IDs:",
                jsonResponse.data.debug_info.rag_content.course_ids
              );
              console.log(
                "  - Quiz Topic:",
                jsonResponse.data.debug_info.rag_content.quiz_topic
              );
              console.log(
                "  - Content Length:",
                jsonResponse.data.debug_info.rag_content.content_length
              );
              console.log(
                "  - Content Preview:",
                jsonResponse.data.debug_info.rag_content.content_preview
              );
              console.log(
                "  - FULL RAG CONTENT:",
                jsonResponse.data.debug_info.rag_content.full_content
              );
            }

            // RAG Function Debug
            if (jsonResponse.data.debug_info.rag_function) {
              console.log("🔧 RAG FUNCTION DETAILS:");
              console.log(
                "  - Function Start:",
                jsonResponse.data.debug_info.rag_function.start
              );
              console.log(
                "  - Database Tables:",
                jsonResponse.data.debug_info.rag_function.database_tables
              );
              console.log(
                "  - Formatted Content:",
                jsonResponse.data.debug_info.rag_function.formatted_content
              );
              console.log(
                "  - Final Content:",
                jsonResponse.data.debug_info.rag_function.final_content
              );

              if (jsonResponse.data.debug_info.rag_function.no_courses) {
                console.warn("  ⚠ NO COURSES SELECTED - Using fallback");
              }
              if (jsonResponse.data.debug_info.rag_function.function_missing) {
                console.error("  ❌ RAG FUNCTION MISSING");
              }
              if (jsonResponse.data.debug_info.rag_function.tables_missing) {
                console.error("  ❌ DATABASE TABLES MISSING");
              }
            }

            // AI Prompt Debug
            if (jsonResponse.data.debug_info.ai_prompt) {
              console.log("💬 AI PROMPT:");
              console.log(
                "  - Prompt Length:",
                jsonResponse.data.debug_info.ai_prompt.prompt_length
              );
              console.log(
                "  - Prompt Preview:",
                jsonResponse.data.debug_info.ai_prompt.prompt_preview
              );
              console.log(
                "  - FULL AI PROMPT:",
                jsonResponse.data.debug_info.ai_prompt.full_prompt
              );
            }

            // API Result Debug
            if (jsonResponse.data.debug_info.api_result) {
              console.log("🌐 API RESULT:");
              console.log(
                "  - Success:",
                jsonResponse.data.debug_info.api_result.success
              );
              console.log(
                "  - Result Type:",
                jsonResponse.data.debug_info.api_result.result_type
              );
              console.log(
                "  - Questions Count:",
                jsonResponse.data.debug_info.api_result.questions_count
              );
              console.log(
                "  - Error Message:",
                jsonResponse.data.debug_info.api_result.error_message
              );
              console.log(
                "  - First Question Preview:",
                jsonResponse.data.debug_info.api_result.first_question_preview
              );
            }

            console.log("=== 🔚 END DEBUG INFORMATION ===");
          }

          updateGenerationStep(3, "Questions générées avec succès");
          updateGenerationStep(4, "Finalisation...");

          setTimeout(function () {
            completeGenerationSuccess(jsonResponse.data, formData);
          }, 1000);
        } else {
          // Enhanced error logging with undefined protection
          var errorMessage = "Erreur inconnue lors de la génération";
          var debugInfo = "";

          if (jsonResponse && jsonResponse.data) {
            if (typeof jsonResponse.data === "string") {
              errorMessage = jsonResponse.data;
            } else if (
              typeof jsonResponse.data === "object" &&
              jsonResponse.data.message
            ) {
              errorMessage = jsonResponse.data.message;
              if (jsonResponse.data.debug) {
                debugInfo = JSON.stringify(jsonResponse.data.debug, null, 2);
                console.log(
                  "Quiz Generation Debug Info:",
                  jsonResponse.data.debug
                );
              }
            } else if (typeof jsonResponse.data === "object") {
              errorMessage = "Erreur de format de réponse du serveur";
              console.log(
                "Invalid jsonResponse.data format:",
                jsonResponse.data
              );
            }
          } else if (jsonResponse) {
            console.log(
              "jsonResponse.data is undefined. Full jsonResponse:",
              jsonResponse
            );
            errorMessage = "Données de réponse manquantes";
          } else {
            console.log(
              "No valid JSON found in response. Raw response:",
              response
            );
            errorMessage = "Impossible d'analyser la réponse du serveur";
          }

          addGenerationLog("Erreur: " + errorMessage, "error");
          if (debugInfo) {
            addGenerationLog("Debug Info: " + debugInfo, "error");
          }
          showGenerationError(errorMessage);
        }
      },
      error: function (xhr, status, error) {
        addGenerationLog("Erreur réseau: " + error, "error");
        showGenerationError("Erreur de connexion au serveur");
      },
    });
  }

  /**
   * Update generation step
   */
  function updateGenerationStep(step, message) {
    $(".progress-step").removeClass("active");
    $(".progress-step[data-step='" + step + "']").addClass("active");
    $("#generation-status").text(message);
    $(".progress-fill").animate({ width: step * 25 + "%" }, 500);
    addGenerationLog(message, "info");
  }

  /**
   * Complete generation success
   */
  function completeGenerationSuccess(data, formData) {
    console.log("=== COMPLETING GENERATION SUCCESS ===");
    console.log("Data received:", data);
    console.log("Data type:", typeof data);
    console.log("Data keys:", data ? Object.keys(data) : "No data");

    $(".progress-step").removeClass("active").addClass("completed");
    $(".progress-fill").animate({ width: "100%" }, 500);
    $("#generation-status").text("Quiz généré avec succès!");

    // Handle different possible data structures
    var quizCode = data.quiz_code || "Code non disponible";
    var quizId = data.quiz_id || "ID non disponible";
    var message = data.message || "Quiz généré avec succès";
    var stayOnPage = data.stay_on_page || false;
    var editUrl =
      data.edit_url || "admin.php?page=quiz-ia-pro-edit&quiz_id=" + quizId;
    var listUrl = data.list_url || "admin.php?page=quiz-ai-pro-list";

    addGenerationLog("Quiz créé: " + quizCode, "success");
    addGenerationLog("ID du quiz: " + quizId, "success");
    addGenerationLog("Statut: Brouillon (en attente d'approbation)", "info");

    setTimeout(function () {
      $("#generation-modal").hide();

      // Show green notification
      showNotification(message, "success");

      // If stay_on_page is true, don't redirect automatically
      if (stayOnPage) {
        // Show action buttons instead of redirecting
        var actionHtml =
          '<div class="quiz-generation-actions" style="margin-top: 10px;">' +
          '<button type="button" class="button button-primary" onclick="window.location.href=\'' +
          editUrl +
          "'\">✏️ Éditer le Quiz</button> " +
          '<button type="button" class="button" onclick="window.location.href=\'' +
          listUrl +
          "'\">📋 Voir la Liste</button> " +
          '<button type="button" class="button" onclick="resetGeneratorForm()">➕ Créer un Nouveau Quiz</button>' +
          "</div>";

        // Add the action buttons after the form
        if (!$(".quiz-generation-actions").length) {
          $("#quiz-generator-form").after(actionHtml);
        }

        // Auto-remove action buttons after 30 seconds
        setTimeout(function () {
          $(".quiz-generation-actions").fadeOut(500, function () {
            $(this).remove();
          });
        }, 30000);
      } else {
        // Legacy behavior: show confirmation dialog
        if (
          confirm(
            "Quiz créé avec succès et sauvegardé en brouillon! Voulez-vous voir la liste des quiz pour l'approuver ou continuer à créer un nouveau quiz?"
          )
        ) {
          window.location.href = listUrl;
        } else {
          resetGeneratorForm();
        }
      }
    }, 2000);
  }

  /**
   * Show generation error
   */
  function showGenerationError(message) {
    $("#generation-status").text("Erreur lors de la génération");
    addGenerationLog("Génération échouée: " + message, "error");

    setTimeout(function () {
      $("#generation-modal").hide();
      showNotification("Erreur: " + message, "error");
    }, 3000);
  }

  /**
   * Collect form settings
   */
  function collectFormSettings() {
    return {
      show_contact_form: $('input[name="show_contact_form"]').is(":checked"),
      show_page_number: $('input[name="show_page_number"]').is(":checked"),
      show_question_images_results: $(
        'input[name="show_question_images_results"]'
      ).is(":checked"),
      show_progress_bar: $('input[name="show_progress_bar"]').is(":checked"),
      require_login: $('input[name="require_login"]').is(":checked"),
      disable_first_page: $('input[name="disable_first_page"]').is(":checked"),
      enable_comments: $('input[name="enable_comments"]').is(":checked"),
      featured_image_type: $('input[name="featured_image_type"]:checked').val(),
      featured_image_url: $("#featured_image_url").val(),
      featured_image_id: $("#featured_image_id").val(),
    };
  }

  /**
   * Simulate generation process
   */
  function simulateGenerationProcess(formData) {
    var steps = [
      {
        step: 1,
        title: "Analyse du contenu",
        duration: 2000,
        progress: 25,
        logs: [
          "Analyse du cours/catégorie sélectionné...",
          "Extraction des concepts clés...",
          "Préparation du contexte pour l'IA...",
        ],
      },
      {
        step: 2,
        title: "Génération des questions",
        duration: 3000,
        progress: 50,
        logs: [
          "Connexion à " + formData.ai_provider + "...",
          "Génération de " + formData.num_questions + " questions...",
          "Application du niveau de difficulté: " +
            formData.difficulty_level +
            "...",
        ],
      },
      {
        step: 3,
        title: "Création des réponses",
        duration: 2500,
        progress: 75,
        logs: [
          "Génération des options de réponse...",
          "Validation de la cohérence...",
          "Application du système de notation...",
        ],
      },
      {
        step: 4,
        title: "Finalisation",
        duration: 1500,
        progress: 100,
        logs: [
          "Sauvegarde du quiz en base de données...",
          "Génération du code unique...",
          "Quiz créé avec succès!",
        ],
      },
    ];

    var currentStep = 0;

    function processStep() {
      if (currentStep >= steps.length) {
        completeGeneration(formData);
        return;
      }

      var step = steps[currentStep];

      // Update UI
      $(".progress-step").removeClass("active");
      $(".progress-step[data-step='" + step.step + "']").addClass("active");
      $("#generation-status").text(step.title + "...");
      $(".progress-fill").animate({ width: step.progress + "%" }, 500);

      // Add logs
      step.logs.forEach(function (log, index) {
        setTimeout(function () {
          addGenerationLog(log, "info");
        }, (index * step.duration) / step.logs.length);
      });

      setTimeout(function () {
        $(".progress-step[data-step='" + step.step + "']")
          .removeClass("active")
          .addClass("completed");
        currentStep++;
        processStep();
      }, step.duration);
    }

    processStep();
  }

  /**
   * Add generation log entry
   */
  function addGenerationLog(message, type) {
    type = type || "info";
    var timestamp = new Date().toLocaleTimeString();
    var logEntry = $(
      '<div class="log-entry log-' +
        type +
        '">[' +
        timestamp +
        "] " +
        message +
        "</div>"
    );

    $("#generation-log").append(logEntry);
    $("#generation-log").scrollTop($("#generation-log")[0].scrollHeight);
  }

  /**
   * Complete generation
   */
  function completeGeneration(formData) {
    addGenerationLog("Quiz généré avec succès!", "success");
    $("#generation-status").text("Génération terminée!");

    setTimeout(function () {
      $("#generation-modal").hide();
      showNotification(
        'Quiz "' + formData.quiz_name + '" créé avec succès!',
        "success"
      );

      // Redirect to quiz list or edit page
      if (
        confirm(
          "Quiz créé avec succès! Voulez-vous voir la liste des quiz ou continuer à créer un nouveau quiz?"
        )
      ) {
        // Redirect to quiz list
        window.location.href = "admin.php?page=quiz-ai-pro-list";
      } else {
        // Reset form
        resetGeneratorForm();
      }
    }, 2000);
  }

  /**
   * Cancel generation
   */
  function cancelGeneration() {
    if (
      confirm(
        "Êtes-vous sûr de vouloir annuler la génération du quiz? Le processus en cours sera interrompu."
      )
    ) {
      $("#generation-modal").hide();
      showNotification("Génération annulée", "warning");
    }
  }

  /**
   * Save draft
   */
  function saveDraft() {
    if (!$("#quiz_name").val().trim()) {
      showNotification("Veuillez saisir un nom pour le quiz", "warning");
      $("#quiz_name").focus();
      return;
    }

    showLoader();

    var formData = {
      quiz_name: $("#quiz_name").val(),
      course_id: $("#course_id").val(),
      category_id: $("#category_id").val(),
      quiz_type: $("#quiz_type").val(),
      form_type: $("#form_type").val(),
      grading_system: $("#grading_system").val(),
      ai_provider: $("#ai_provider").val(),
      num_questions: $("#num_questions").val(),
      difficulty_level: $("#difficulty_level").val(),
      language: $("#language").val(),
      additional_instructions: $("#additional_instructions").val(),
      time_limit: $("#time_limit").val(),
      questions_per_page: $("#questions_per_page").val(),
      settings: collectFormSettings(),
    };

    $.ajax({
      url: quiz_ai_ajax.ajax_url,
      type: "POST",
      data: {
        action: "save_quiz_draft",
        form_data: formData,
        nonce: quiz_ai_ajax.nonce,
      },
      success: function (response) {
        hideLoader();
        if (response.success) {
          showNotification(response.data.message, "success");
          // Store quiz ID for future updates
          $("#quiz-generator-form").data("quiz-id", response.data.quiz_id);
        } else {
          showNotification("Erreur: " + response.data, "error");
        }
      },
      error: function () {
        hideLoader();
        showNotification("Erreur de connexion", "error");
      },
    });
  }

  /**
   * Preview quiz
   */
  function previewQuiz() {
    if (!validateGeneratorForm()) {
      return;
    }

    showNotification("Aperçu du quiz (fonctionnalité à venir)", "info");
  }

  /**
   * Auto-save
   */
  function autoSave() {
    if ($("#quiz_name").val().trim()) {
      console.log("Auto-saving quiz draft...");
      // In real implementation, this would make an AJAX call
    }
  }

  /**
   * Reset generator form
   */
  function resetGeneratorForm() {
    $("#quiz-generator-form")[0].reset();
    $(".conditional-field").hide();
    $("#selected-media").removeClass("has-media").empty();
    showNotification("Formulaire réinitialisé", "info");
  }

  /**
   * Initialize Quiz Editor functionality
   */
  function initQuizEditor() {
    // Check if we're on the quiz editor page
    if (!$(".quiz-editor-container").length) {
      return;
    }

    console.log("Initializing Quiz Editor...");

    // Quiz title editing
    $("#quiz-title").on("blur", function () {
      var newTitle = $(this).val().trim();
      if (newTitle) {
        console.log("Quiz title changed:", newTitle);
        // Auto-save functionality can be added here
        showNotification("Titre mis à jour", "info");
      }
    });

    // Publish quiz functionality
    $(".publish-quiz").on("click", function () {
      var quizTitle = $("#quiz-title").val().trim();
      if (!quizTitle) {
        showNotification("Veuillez saisir un titre pour le quiz", "warning");
        $("#quiz-title").focus();
        return;
      }

      if (
        confirm(
          'Êtes-vous sûr de vouloir publier le quiz "' + quizTitle + '" ?'
        )
      ) {
        // Publish quiz functionality - to be implemented
        showNotification(
          "Fonctionnalité de publication - à implémenter",
          "info"
        );
      }
    });

    // Notice dismiss functionality
    $(".notice-dismiss-btn").on("click", function () {
      $(this).closest(".notice").fadeOut();
    });

    // Question search functionality
    $("#question-search").on(
      "keyup",
      debounce(function () {
        var searchTerm = $(this).val().toLowerCase();
        $(".question-item").each(function () {
          var questionText = $(this)
            .find(".question-text")
            .text()
            .toLowerCase();
          var answersText = $(this)
            .find(".question-answers")
            .text()
            .toLowerCase();

          if (
            questionText.includes(searchTerm) ||
            answersText.includes(searchTerm)
          ) {
            $(this).show();
          } else {
            $(this).hide();
          }
        });
      }, 300)
    );

    // Question actions
    $(document).on("click", ".edit-question", function (e) {
      e.preventDefault();
      var questionId = $(this).closest(".question-item").data("question-id");
      console.log("Edit question:", questionId);
      showNotification("Modification de question - à implémenter", "info");
    });

    $(document).on("click", ".duplicate-question", function (e) {
      e.preventDefault();
      var questionId = $(this).closest(".question-item").data("question-id");
      console.log("Duplicate question:", questionId);
      showNotification("Duplication de question - à implémenter", "info");
    });

    $(document).on("click", ".delete-question", function (e) {
      e.preventDefault();
      var questionId = $(this).closest(".question-item").data("question-id");
      var questionText = $(this)
        .closest(".question-item")
        .find(".question-text")
        .text();

      if (
        confirm(
          'Êtes-vous sûr de vouloir supprimer cette question ?\n\n"' +
            questionText +
            '"'
        )
      ) {
        console.log("Delete question:", questionId);
        $(this).closest(".question-item").fadeOut();
        showNotification("Question supprimée", "success");
      }
    });

    // Add question functionality
    $(".page-actions .button-primary, .create-page-btn").on(
      "click",
      function (e) {
        e.preventDefault();
        console.log("Add new question clicked");
        showNotification("Ajout de question - à implémenter", "info");
      }
    );

    // Tab switching functionality (if using AJAX tabs in the future)
    $(".nav-tab").on("click", function (e) {
      var href = $(this).attr("href");
      if (href && href.indexOf("#") !== -1) {
        e.preventDefault();
        // AJAX tab switching functionality can be added here
        console.log("Tab clicked:", href);
      }
    });

    console.log("Quiz Editor initialized successfully");
  }
})(jQuery);

// CSS pour les notifications et loader
var quizStyles = `
<style>
.quiz-notification {
    position: fixed;
    top: -100px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 4px;
    color: white;
    font-weight: 600;
    z-index: 999999;
    opacity: 0;
    min-width: 250px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.quiz-success {
    background-color: #27ae60;
}

.quiz-info {
    background-color: #3498db;
}

.quiz-warning {
    background-color: #f39c12;
}

.quiz-error {
    background-color: #e74c3c;
}

.quiz-loader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    z-index: 999999;
    gap: 15px;
}

.quiz-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    animation: quiz-spin 1s linear infinite;
}

@keyframes quiz-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.quiz-loader span {
    font-weight: 600;
    color: #3498db;
}
</style>
`;

document.head.insertAdjacentHTML("beforeend", quizStyles);
