jQuery(document).ready(function ($) {
  // AJAX Export All as CSV
  $(document).on("click", "#quiz-ai-export-all-csv-btn", function (e) {
    e.preventDefault();
    var $btn = $(this);
    $btn
      .prop("disabled", true)
      .html(
        '<span class="spinner is-active" style="float:none;display:inline-block;"></span> Exporting...'
      );
    $.ajax({
      url: quiz_ai_ajax.ajax_url,
      type: "POST",
      data: {
        action: "quiz_ai_export_learnpress_all_csv",
        nonce: quiz_ai_ajax.nonce,
      },
      xhrFields: {
        responseType: "blob",
      },
      success: function (blob) {
        var link = document.createElement("a");
        link.href = window.URL.createObjectURL(blob);
        link.download = "learnpress_students_all_courses.csv";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        $btn.prop("disabled", false).html("Export All as CSV");
      },
      error: function () {
        alert("Export failed.");
        $btn.prop("disabled", false).html("Export All as CSV");
      },
    });
  });
});
