jQuery(document).ready(function ($) {
  console.log("[quiz-ai] admin-spam-batch.js loaded");
  console.log(
    "[quiz-ai] #quiz-ai-spam-users-list exists:",
    $("#quiz-ai-spam-users-list").length > 0
  );
  console.log("[quiz-ai] window.quizAiSpamUsers:", window.quizAiSpamUsers);

  var batchActive = false;
  var batchIndex = 0;
  var batchSize = 100;
  var deletedCount = 0;
  var spamUsers = window.quizAiSpamUsers || [];
  var pageSize = 100;
  var currentPage = 1;

  function renderSpamUsersPage() {
    var $ul = $("#quiz-ai-spam-list-ul");
    if (!$ul.length) return;
    $ul.empty();
    var start = (currentPage - 1) * pageSize;
    var end = Math.min(start + pageSize, spamUsers.length);
    for (var i = start; i < end; i++) {
      $ul.append(
        '<li style="padding:8px 16px;border-bottom:1px solid #f5c6cb;display:flex;align-items:center;gap:10px;">' +
          '<span style="font-weight:500;color:#dc3545;">' +
          spamUsers[i].user_email +
          "</span>" +
          "</li>"
      );
    }
    // Pagination controls
    var totalPages = Math.ceil(spamUsers.length / pageSize);
    var $pagination = $("#quiz-ai-spam-pagination");
    if (!$pagination.length) {
      $ul.after(
        '<div id="quiz-ai-spam-pagination" style="margin-top:12px;text-align:center;"></div>'
      );
      $pagination = $("#quiz-ai-spam-pagination");
    }
    var html = "";
    if (totalPages > 1) {
      html +=
        '<button id="quiz-ai-spam-prev" class="button"' +
        (currentPage === 1 ? " disabled" : "") +
        ">Previous</button> ";
      html +=
        '<span style="margin:0 8px;">Page ' +
        currentPage +
        " of " +
        totalPages +
        "</span>";
      html +=
        '<button id="quiz-ai-spam-next" class="button"' +
        (currentPage === totalPages ? " disabled" : "") +
        ">Next</button>";
    }
    $pagination.html(html);
  }

  window.renderSpamUsersList = function () {
    var $list = $("#quiz-ai-spam-users-list");
    spamUsers = window.quizAiSpamUsers || [];
    // Only show after detection
    if (
      typeof window.quizAiSpamDetectionTriggered === "undefined" ||
      !window.quizAiSpamDetectionTriggered
    ) {
      $list.html("");
      $("#quiz-ai-spam-delete-progress").html("");
      return;
    }
    if (spamUsers.length) {
      $list.html(
        '<div class="notice notice-warning" style="margin-bottom:16px;">' +
          '<p style="font-size:1.1rem;margin-bottom:12px;">Found <strong>' +
          spamUsers.length +
          "</strong> users with suspicious email domains:</p>" +
          '<div style="border:1px solid #f5c6cb;background:#fff6f6;border-radius:8px;padding:12px 0;">' +
          '<ul id="quiz-ai-spam-list-ul" style="margin:0;padding:0;list-style:none;max-height:220px;overflow:auto;"></ul>' +
          "</div>" +
          "</div>"
      );
      // Add batch delete controls and progress bar
      $("#quiz-ai-spam-delete-progress").html(
        '<button id="quiz-ai-batch-delete-btn" class="button button-danger" style="height:40px;padding:0 24px;font-size:1rem;border-radius:6px;background:#dc3545;color:#fff;">Delete</button> <div id="quiz-ai-batch-progress-bar" style="display:inline-block;width:200px;height:16px;background:#eee;border-radius:8px;vertical-align:middle;margin:0 12px;"><div id="quiz-ai-batch-progress-fill" style="height:100%;width:0%;background:#dc3545;border-radius:8px;"></div></div> <span id="quiz-ai-batch-progress-text"></span>'
      );
      currentPage = 1;
      renderSpamUsersPage();
    } else {
      $list.html(
        '<div class="notice notice-success"><p>No spam users found.</p></div>'
      );
      $("#quiz-ai-spam-delete-progress").html("");
    }
  };

  function updateProgressBar() {
    var percent = spamUsers.length
      ? Math.round((deletedCount / (deletedCount + spamUsers.length)) * 100)
      : 100;
    $("#quiz-ai-batch-progress-fill").css({
      width: percent + "%",
      background: percent > 0 ? "#dc3545" : "#eee",
    });
    $("#quiz-ai-batch-progress-text").text(
      deletedCount + " deleted (" + percent + "%)"
    );
  }

  function doBatchDelete() {
    if (!batchActive || batchIndex >= spamUsers.length) {
      batchActive = false;
      $("#quiz-ai-batch-delete-btn")
        .text("Delete")
        .prop("disabled", false);
      updateProgressBar();
      return;
    }
    var batch = spamUsers.slice(batchIndex, batchIndex + batchSize);
    var ids = batch.map(function (u) {
      return u.ID;
    });
    $("#quiz-ai-batch-delete-btn").text("Stop").prop("disabled", false);
    $("#quiz-ai-batch-delete-btn")
      .addClass("button-danger")
      .removeClass("button-primary");
    $.post(
      ajaxurl,
      {
        action: "quiz_ai_batch_delete_spam",
        ids: ids,
        _ajax_nonce: quiz_ai_ajax.nonce,
      },
      function (resp) {
        batchIndex += batchSize;
        if (resp.success) {
          deletedCount += batch.length;
          // Remove deleted from UI
          batch.forEach(function (u) {
            var idx = spamUsers.findIndex(function (su) {
              return su.ID === u.ID;
            });
            if (idx !== -1) spamUsers.splice(idx, 1);
          });
          renderSpamUsersPage();
          updateProgressBar();
        }
        if (batchActive) {
          setTimeout(doBatchDelete, 500);
        } else {
          $("#quiz-ai-batch-delete-btn")
            .text("Delete")
            .prop("disabled", false);
        }
      }
    );
  }

  // Initial render on page load
  window.renderSpamUsersList();

  // Listen for custom event to re-render after POST
  window.addEventListener("quizAiSpamUsersUpdated", function () {
    window.renderSpamUsersList();
  });

  $(document).on("click", "#quiz-ai-batch-delete-btn", function (e) {
    e.preventDefault();
    if (!batchActive) {
      batchActive = true;
      batchIndex = 0;
      deletedCount = 0;
      $(this).text("Stop").prop("disabled", false);
      doBatchDelete();
    } else {
      batchActive = false;
      $(this).text("Delete").prop("disabled", false);
    }
  });

  $(document).on("click", "#quiz-ai-spam-prev", function (e) {
    e.preventDefault();
    if (currentPage > 1) {
      currentPage--;
      renderSpamUsersPage();
    }
  });
  $(document).on("click", "#quiz-ai-spam-next", function (e) {
    e.preventDefault();
    var totalPages = Math.ceil(spamUsers.length / pageSize);
    if (currentPage < totalPages) {
      currentPage++;
      renderSpamUsersPage();
    }
  });
});
