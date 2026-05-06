/* ==========================================================================
   MMG Checkout Dashboard — Admin Script
   ========================================================================== */

/**
 * Show a toast notification.
 *
 * @param {string} message - Text to display.
 * @param {string} type    - 'success' or 'error'.
 */
function mmgShowToast(message, type) {
  var $toast = document.getElementById("mmg-toast");
  if (!$toast) return;
  $toast.textContent = message;
  $toast.className = "mmg-toast mmg-toast-" + (type || "success");
  // Force reflow then show.
  void $toast.offsetWidth;
  $toast.classList.add("mmg-toast-visible");
  clearTimeout($toast._timer);
  $toast._timer = setTimeout(function () {
    $toast.classList.remove("mmg-toast-visible");
  }, 2500);
}

/**
 * Copy text to clipboard and show toast.
 *
 * @param {string} text - The text to copy.
 */
function mmgCopyToClipboard(text) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(function () {
      mmgShowToast("Copied to clipboard!", "success");
    });
  } else {
    var tmp = document.createElement("textarea");
    tmp.value = text;
    document.body.appendChild(tmp);
    tmp.select();
    document.execCommand("copy");
    document.body.removeChild(tmp);
    mmgShowToast("Copied to clipboard!", "success");
  }
}

jQuery(document).ready(function ($) {
  /* ---- Tab Navigation (client-side) ---- */
  $(".mmg-nav-link").on("click", function (e) {
    e.preventDefault();
    var tab = $(this).data("tab");

    // Update nav active state.
    $(".mmg-nav-link").removeClass("mmg-nav-active");
    $(this).addClass("mmg-nav-active");

    // Switch panels.
    $(".mmg-tab-panel").removeClass("mmg-tab-active");
    $("#mmg-panel-" + tab).addClass("mmg-tab-active");

    // Update URL hash (for direct linking).
    if (history.replaceState) {
      history.replaceState(null, null, "#" + tab);
    }
  });

  // After settings save, return to credentials tab so the user sees what changed.
  if (window.location.search.indexOf("settings-updated=true") !== -1) {
    $(".mmg-nav-link[data-tab='credentials']").trigger("click");
  } else {
    // Restore tab from URL hash on load.
    var hash = window.location.hash.replace("#", "");
    if (hash && $(".mmg-nav-link[data-tab='" + hash + "']").length) {
      $(".mmg-nav-link[data-tab='" + hash + "']").trigger("click");
    }
  }

  /* ---- Card Collapse / Expand ---- */
  $(".mmg-card-header[data-collapse]").on("click", function () {
    $(this).closest(".mmg-card").toggleClass("mmg-collapsed");
  });

  /* ---- Toggle Secret Key Visibility ---- */
  $(".toggle-secret-key").on("click", function () {
    var targetId = $(this).data("target");
    var $input = $("#" + targetId);
    if ($input.attr("type") === "password") {
      $input.attr("type", "text");
      $(this).text("Hide");
    } else {
      $input.attr("type", "password");
      $(this).text("Show");
    }
  });

  /* ---- Settings Form Confirmation ---- */
  var originalValues = {};
  $("#mmg-checkout-settings-form :input").each(function () {
    if (this.id) originalValues[this.id] = $(this).val();
  });

  $("#mmg-checkout-settings-form").on("submit", function (e) {
    var changedFields = [];
    $("#mmg-checkout-settings-form :input").each(function () {
      if (this.id && $(this).val() !== originalValues[this.id]) {
        var label = $(this).closest(".mmg-form-row").find(".mmg-form-label").first().text().trim();
        if (label) changedFields.push(label);
      }
    });

    if (changedFields.length > 0) {
      var msg;
      if (changedFields.indexOf("Mode") > -1) {
        msg =
          "You are switching modes. Are you sure you want to save this change?";
      } else {
        msg =
          "You have changed: " +
          changedFields.join(", ") +
          ".\nSave these changes?";
      }
      if (!confirm(msg)) {
        e.preventDefault();
      }
    }
  });

  /* ---- Re-authenticate (Dashboard tab) ---- */
  $("#mmg-reauthenticate").on("click", function () {
    var $btn = $(this);
    var $msg = $("#mmg-reauth-message");
    $btn.prop("disabled", true);
    $msg.hide();
    $.post(mmg_admin_params.ajax_url, {
      action: "mmg_reauthenticate",
      nonce: mmg_admin_params.nonce,
    })
      .done(function (r) {
        if (r.success) {
          mmgShowToast(r.data.message, "success");
          $msg.text(r.data.message).css("color", "var(--mmg-success)").show();
          $("#mmg-auth-stat-icon-box").removeClass("mmg-stat-icon-danger").addClass("mmg-stat-icon-success");
          $("#mmg-auth-stat-icon").removeClass("dashicons-warning").addClass("dashicons-yes-alt");
          $("#mmg-auth-status-pill").removeClass("mmg-status-disconnected").addClass("mmg-status-connected");
          $("#mmg-auth-status-text").text("Connected");
        } else {
          mmgShowToast(r.data.message || "Failed.", "error");
          $msg.text(r.data.message || "Failed.").css("color", "var(--mmg-danger)").show();
          $("#mmg-auth-stat-icon-box").removeClass("mmg-stat-icon-success").addClass("mmg-stat-icon-danger");
          $("#mmg-auth-stat-icon").removeClass("dashicons-yes-alt").addClass("dashicons-warning");
          $("#mmg-auth-status-pill").removeClass("mmg-status-connected").addClass("mmg-status-disconnected");
          $("#mmg-auth-status-text").text("Not Connected");
        }
      })
      .fail(function () {
        mmgShowToast("Server error.", "error");
        $msg.text("Server error.").css("color", "var(--mmg-danger)").show();
      })
      .always(function () {
        $btn.prop("disabled", false);
      });
  });

  /* ---- Balance tab ---- */
  $("#mmg-check-balance").on("click", function () {
    var $btn = $(this),
      $spinner = $("#mmg-balance-spinner"),
      $result = $("#mmg-balance-result"),
      $error = $("#mmg-balance-error");
    $btn.prop("disabled", true);
    $spinner.show();
    $error.hide();
    $.post(mmg_admin_params.ajax_url, {
      action: "mmg_check_balance",
      nonce: mmg_admin_params.nonce,
    })
      .done(function (r) {
        if (r.success) {
          var bal =
            r.data.balance ??
            r.data.availableBalance ??
            JSON.stringify(r.data);
          $result.text(bal);
          mmgShowToast("Balance retrieved.", "success");
        } else {
          $error.text(r.data.message || "Request failed.").show();
        }
      })
      .fail(function () {
        $error.text("Server error. Please try again.").show();
      })
      .always(function () {
        $btn.prop("disabled", false);
        $spinner.hide();
      });
  });

  /* ---- Transactions tab — history ---- */
  $("#mmg-fetch-transactions").on("click", function () {
    var $btn = $(this),
      $spinner = $("#mmg-txn-spinner"),
      $results = $("#mmg-txn-results"),
      $error = $("#mmg-txn-error");
    $btn.prop("disabled", true);
    $spinner.show();
    $error.hide();
    $results.empty();
    $.post(mmg_admin_params.ajax_url, {
      action: "mmg_get_transactions",
      nonce: mmg_admin_params.nonce,
      start_date: $("#mmg-start-date").val(),
      end_date: $("#mmg-end-date").val(),
    })
      .done(function (r) {
        if (!r.success) {
          $error.text(r.data.message || "Request failed.").show();
          return;
        }
        var rows = Array.isArray(r.data)
          ? r.data
          : r.data.transactions || [];
        if (!rows.length) {
          $results.html(
            '<p style="color:var(--mmg-text-muted);">No transactions found.</p>'
          );
          return;
        }
        var html = '<div class="mmg-table-wrap"><table class="mmg-table">';
        html +=
          "<thead><tr><th>ID</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead><tbody>";
        rows.forEach(function (t) {
          html += "<tr>";
          html += "<td>" + (t.transactionId || t.id || "—") + "</td>";
          html += "<td>" + (t.date || t.createdAt || "—") + "</td>";
          html += "<td>" + (t.amount || "—") + "</td>";
          html += "<td>" + (t.status || "—") + "</td>";
          html += "</tr>";
        });
        html += "</tbody></table></div>";
        $results.html(html);
      })
      .fail(function () {
        $error.text("Server error. Please try again.").show();
      })
      .always(function () {
        $btn.prop("disabled", false);
        $spinner.hide();
      });
  });

  /* ---- Transactions tab — lookup ---- */
  $("#mmg-lookup-txn").on("click", function () {
    var txnId = $("#mmg-lookup-txn-id").val().trim();
    var $btn = $(this),
      $spinner = $("#mmg-lookup-spinner"),
      $result = $("#mmg-lookup-result"),
      $error = $("#mmg-lookup-error");
    if (!txnId) {
      $error.text("Please enter a Transaction ID.").show();
      return;
    }
    $btn.prop("disabled", true);
    $spinner.show();
    $error.hide();
    $result.hide();
    $.post(mmg_admin_params.ajax_url, {
      action: "mmg_lookup_transaction",
      nonce: mmg_admin_params.nonce,
      txn_id: txnId,
    })
      .done(function (r) {
        if (r.success) {
          $result.text(JSON.stringify(r.data, null, 2)).show();
        } else {
          $error.text(r.data.message || "Lookup failed.").show();
        }
      })
      .fail(function () {
        $error.text("Server error. Please try again.").show();
      })
      .always(function () {
        $btn.prop("disabled", false);
        $spinner.hide();
      });
  });
});
