jQuery(document).ready(function ($) {
  var originalValues = {};

  // Store original values
  $("form#mmg-checkout-settings-form :input").each(function () {
    originalValues[this.id] = $(this).val();
  });

  $(".toggle-secret-key").click(function () {
    var targetId = $(this).data("target");
    var secretKeyInput = $("#" + targetId);
    if (secretKeyInput.attr("type") === "password") {
      secretKeyInput.attr("type", "text");
      $(this).text("Hide");
    } else {
      secretKeyInput.attr("type", "password");
      $(this).text("Show");
    }
  });

  function toggleLiveModeIndicator() {
    if ($("#mmg_mode").val() === "live") {
      $("#live-mode-indicator").show();
    } else {
      $("#live-mode-indicator").hide();
    }
  }

  $("#mmg_mode").on("change", toggleLiveModeIndicator);
  toggleLiveModeIndicator(); // Initial state

  $("form#mmg-checkout-settings-form").submit(function (e) {
    var changedFields = [];
    $("form#mmg-checkout-settings-form :input").each(function () {
      if ($(this).val() !== originalValues[this.id]) {
        changedFields.push($(this).closest("tr").find("th").text());
      }
    });

    if (changedFields.length > 0) {
      var confirmMessage = "";
      if (changedFields.includes("Mode")) {
        var oldMode = originalValues["mmg_mode"];
        var newMode = $("#mmg_mode").val();
        confirmMessage =
          "You have switched from " +
          oldMode +
          " to " +
          newMode +
          ".\n\nAre you sure you want to save this change?";
      } else {
        confirmMessage +=
          "You have changed the following fields:\n" +
          changedFields.join("\n") +
          "\nAre you sure you want to save these changes?";
      }
      if (!confirm(confirmMessage)) {
        e.preventDefault();
      }
    }
  });
});

function copyToClipboard(text) {
  var tempInput = document.createElement("input");
  tempInput.value = text;
  document.body.appendChild(tempInput);
  tempInput.select();
  document.execCommand("copy");
  document.body.removeChild(tempInput);

  var successMessage = document.getElementById("copy-success");
  successMessage.style.display = "inline";
  setTimeout(function () {
    successMessage.style.display = "none";
  }, 2000);
}

jQuery(document).ready(function ($) {
  // Re-authenticate button (Settings tab)
  $("#mmg-reauthenticate").on("click", function () {
    var $btn = $(this);
    var $msg = $("#mmg-reauth-message");
    $btn.prop("disabled", true);
    $msg.hide();
    $.post(mmg_admin_params.ajax_url, { action: "mmg_reauthenticate", nonce: mmg_admin_params.nonce })
      .done(function (r) {
        $msg.text(r.success ? r.data.message : (r.data.message || "Failed."))
            .css("color", r.success ? "green" : "#c00").show();
      })
      .fail(function () { $msg.text("Server error.").css("color", "#c00").show(); })
      .always(function () { $btn.prop("disabled", false); });
  });

  // Balance tab
  $("#mmg-check-balance").on("click", function () {
    var $btn = $(this), $spinner = $("#mmg-balance-spinner"),
        $result = $("#mmg-balance-result"), $error = $("#mmg-balance-error");
    $btn.prop("disabled", true); $spinner.show(); $error.hide();
    $.post(mmg_admin_params.ajax_url, { action: "mmg_check_balance", nonce: mmg_admin_params.nonce })
      .done(function (r) {
        if (r.success) {
          var bal = r.data.balance ?? r.data.availableBalance ?? JSON.stringify(r.data);
          $result.text(bal);
        } else { $error.text(r.data.message || "Request failed.").show(); }
      })
      .fail(function () { $error.text("Server error. Please try again.").show(); })
      .always(function () { $btn.prop("disabled", false); $spinner.hide(); });
  });

  // Transactions tab — history
  $("#mmg-fetch-transactions").on("click", function () {
    var $btn = $(this), $spinner = $("#mmg-txn-spinner"),
        $results = $("#mmg-txn-results"), $error = $("#mmg-txn-error");
    $btn.prop("disabled", true); $spinner.show(); $error.hide(); $results.empty();
    $.post(mmg_admin_params.ajax_url, {
      action: "mmg_get_transactions", nonce: mmg_admin_params.nonce,
      start_date: $("#mmg-start-date").val(), end_date: $("#mmg-end-date").val(),
    })
      .done(function (r) {
        if (!r.success) { $error.text(r.data.message || "Request failed.").show(); return; }
        var rows = Array.isArray(r.data) ? r.data : (r.data.transactions || []);
        if (!rows.length) { $results.text("No transactions found."); return; }
        var $table = $("<table class='widefat'>").append(
          "<thead><tr><th>ID</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead>"
        );
        var $tbody = $("<tbody>");
        rows.forEach(function (t) {
          var $row = $("<tr>");
          $("<td>").text(t.transactionId || t.id || "—").appendTo($row);
          $("<td>").text(t.date || t.createdAt || "—").appendTo($row);
          $("<td>").text(t.amount || "—").appendTo($row);
          $("<td>").text(t.status || "—").appendTo($row);
          $tbody.append($row);
        });
        $results.empty().append($table.append($tbody));
      })
      .fail(function () { $error.text("Server error. Please try again.").show(); })
      .always(function () { $btn.prop("disabled", false); $spinner.hide(); });
  });

  // Transactions tab — lookup
  $("#mmg-lookup-txn").on("click", function () {
    var txnId = $("#mmg-lookup-txn-id").val().trim();
    var $btn = $(this), $spinner = $("#mmg-lookup-spinner"),
        $result = $("#mmg-lookup-result"), $error = $("#mmg-lookup-error");
    if (!txnId) { $error.text("Please enter a Transaction ID.").show(); return; }
    $btn.prop("disabled", true); $spinner.show(); $error.hide(); $result.hide();
    $.post(mmg_admin_params.ajax_url, { action: "mmg_lookup_transaction", nonce: mmg_admin_params.nonce, txn_id: txnId })
      .done(function (r) {
        if (r.success) { $result.text(JSON.stringify(r.data, null, 2)).show(); }
        else { $error.text(r.data.message || "Lookup failed.").show(); }
      })
      .fail(function () { $error.text("Server error. Please try again.").show(); })
      .always(function () { $btn.prop("disabled", false); $spinner.hide(); });
  });
});
