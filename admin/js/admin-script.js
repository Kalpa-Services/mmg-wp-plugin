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

  /* ---- Check for Updates (Dashboard tab) ---- */
  $("#mmg-check-for-updates").on("click", function () {
    var $btn = $(this);
    var $spinner = $("#mmg-update-spinner");
    var $msg = $("#mmg-update-message");
    $btn.prop("disabled", true);
    $spinner.show();
    $msg.hide();
    $.post(mmg_admin_params.ajax_url, {
      action: "mmg_check_for_updates",
      nonce: mmg_admin_params.nonce,
    })
      .done(function (r) {
        if (r.success) {
          if (r.data.update_available) {
            var text = "Update available: v" + r.data.new_version + " — go to Dashboard > Updates to install.";
            $msg.text(text).css("color", "var(--mmg-success)").show();
            mmgShowToast(text, "success");
          } else {
            $msg.text("You are on the latest version.").css("color", "var(--mmg-text-muted)").show();
            mmgShowToast("You are on the latest version.", "success");
          }
        } else {
          var errText = (r.data && r.data.message) ? r.data.message : "Update check failed.";
          $msg.text(errText).css("color", "var(--mmg-danger)").show();
          mmgShowToast(errText, "error");
        }
      })
      .fail(function () {
        $msg.text("Server error.").css("color", "var(--mmg-danger)").show();
        mmgShowToast("Server error.", "error");
      })
      .always(function () {
        $btn.prop("disabled", false);
        $spinner.hide();
      });
  });

  /* ---- Balance tab ---- */
  var MMG_BALANCE_KEY = "mmg_balance_cache";

  function mmgFmtVal(v) {
    return v !== null && v !== undefined ? String(v) : "—";
  }

  function mmgPopulateBalance(balData, timestamp) {
    $("#mmg-balance-current").text(mmgFmtVal(balData.currentBalance));
    $("#mmg-balance-result").text(mmgFmtVal(balData.availableBalance));
    $("#mmg-balance-currency").text(mmgFmtVal(balData.currency));
    $("#mmg-balance-reserved").text(mmgFmtVal(balData.reservedBalance));
    $("#mmg-balance-uncleared").text(mmgFmtVal(balData.unclearedBalance));
    $("#mmg-balance-upper-limit").text(mmgFmtVal(balData.upperLimit));
    $("#mmg-balance-lower-limit").text(mmgFmtVal(balData.lowerLimit));
    $("#mmg-balance-threshold").text(mmgFmtVal(balData.notificationThreshold));
    if (timestamp) {
      $("#mmg-balance-timestamp").text(new Date(timestamp).toLocaleTimeString());
      $("#mmg-balance-last-updated").show();
    }
  }

  // Restore cached balance on page load.
  try {
    var cached = sessionStorage.getItem(MMG_BALANCE_KEY);
    if (cached) {
      var parsed = JSON.parse(cached);
      mmgPopulateBalance(parsed.balData, parsed.timestamp);
    }
  } catch (e) {}

  $("#mmg-check-balance").on("click", function () {
    var $btn = $(this),
      $spinner = $("#mmg-balance-spinner"),
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
          var balData = r.data.accounts && r.data.accounts[0] && r.data.accounts[0].accountBalance;
          if (balData) {
            var ts = Date.now();
            mmgPopulateBalance(balData, ts);
            try { sessionStorage.setItem(MMG_BALANCE_KEY, JSON.stringify({ balData: balData, timestamp: ts })); } catch (e) {}
          } else {
            $("#mmg-balance-result").text(r.data.availableBalance || r.data.balance || "—");
          }
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

  /* ---- Logs tab ---- */
  $(".mmg-log-filter").on("click", function () {
    var filter = $(this).data("filter");
    $(".mmg-log-filter").removeClass("mmg-log-filter-active");
    $(this).addClass("mmg-log-filter-active");
    if (filter === "all") {
      $(".mmg-log-entry").show();
    } else {
      $(".mmg-log-entry").hide();
      $('.mmg-log-entry[data-level="' + filter + '"]').show();
    }
  });

  $("#mmg-clear-logs").on("click", function () {
    if (!confirm("Clear all logs? This cannot be undone.")) return;
    var $btn = $(this);
    var $spinner = $("#mmg-clear-logs-spinner");
    $btn.prop("disabled", true);
    $spinner.show();
    $.post(mmg_admin_params.ajax_url, {
      action: "mmg_clear_logs",
      nonce: mmg_admin_params.nonce,
    })
      .done(function (r) {
        if (r.success) {
          $("#mmg-log-list").replaceWith(
            '<div class="mmg-logs-empty">' +
              '<span class="dashicons dashicons-yes-alt mmg-logs-empty-icon"></span>' +
              "<p>No log entries yet. Events will appear here as the plugin operates.</p>" +
              "</div>"
          );
          $(".mmg-log-count").text("0");
          $("#mmg-download-logs, #mmg-clear-logs").prop("disabled", true);
          mmgShowToast("Logs cleared.", "success");
        }
      })
      .fail(function () {
        mmgShowToast("Failed to clear logs.", "error");
      })
      .always(function () {
        $btn.prop("disabled", false);
        $spinner.hide();
      });
  });

  $("#mmg-download-logs").on("click", function () {
    var lines = [];
    $(".mmg-log-entry").each(function () {
      var lvl  = $(this).find(".mmg-log-badge").text().trim();
      var time = $(this).find(".mmg-log-time").text().trim();
      var msg  = $(this).find(".mmg-log-msg").text().trim();
      lines.push("[" + lvl + "] " + time + " | " + msg);
    });
    if (!lines.length) return;
    var blob = new Blob([lines.join("\n")], { type: "text/plain" });
    var url  = URL.createObjectURL(blob);
    var a    = document.createElement("a");
    a.href     = url;
    a.download = "mmg-checkout-logs-" + new Date().toISOString().slice(0, 10) + ".txt";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
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
      offset: $("#mmg-txn-offset").val(),
    })
      .done(function (r) {
        if (!r.success) {
          $error.text(r.data.message || "Request failed.").show();
          return;
        }
        var rows = r.data.TransactionList || r.data.transactions || (Array.isArray(r.data) ? r.data : []);
        if (!rows.length) {
          $results.html(
            '<p style="color:var(--mmg-text-muted); padding: 20px;">No transactions found.</p>'
          );
          return;
        }
        var html = '<div class="mmg-table-wrap"><table class="mmg-table">';
        html +=
          "<thead><tr><th>ID</th><th>Date</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead><tbody>";
        rows.forEach(function (t) {
          var txnId = t.transactionReference || t.transactionReceipt || t.transactionId || t.id || "—";
          var date = t.modificationDate || t.date || t.createdAt || "—";
          var amount = (t.currency ? t.currency + " " : "") + (t.amount || "—");
          var status = t.transactionStatus || t.status || "—";
          var statusLower = status.toLowerCase();
          var canRefund = (statusLower === 'completed' || statusLower === 'successful' || statusLower === 'success');

          html += "<tr>";
          html += '<td style="font-family:monospace; font-size:11px;">' + txnId + "</td>";
          html += "<td>" + date + "</td>";
          html += "<td><strong>" + amount + "</strong></td>";
          html += "<td>" + status + "</td>";
          html += "<td>";
          if (canRefund) {
              html += '<button type="button" class="mmg-btn mmg-btn-danger mmg-btn-sm mmg-refund-btn" data-txnid="' + txnId + '">';
              html += '<span class="dashicons dashicons-undo" style="font-size:14px;width:14px;height:14px;"></span> Refund</button>';
          }
          html += "</td>";
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
          var t = r.data;
          var debitPartyVal = "—";
          if (t.debitParty && t.debitParty.length > 0) {
            debitPartyVal = t.debitParty[0].value;
          }
          var desc = t.descriptionText || "—";
          if (desc === "—" && t.metadata) {
            var metaDesc = t.metadata.find(function(m) { return m.key === 'description'; });
            if (metaDesc && metaDesc.value) {
                desc = metaDesc.value;
            }
          }
          
          var html = '<div class="mmg-table-wrap"><table class="mmg-table">';
          html += "<tbody>";
          html += "<tr><th>Amount</th><td>" + (t.currency || "") + " " + (t.amount || "—") + "</td></tr>";
          html += "<tr><th>Status</th><td>" + (t.transactionStatus || "—") + "</td></tr>";
          html += "<tr><th>Description</th><td>" + desc + "</td></tr>";
          html += "<tr><th>Debit Party</th><td>" + debitPartyVal + "</td></tr>";
          html += "</tbody></table></div>";
          
          var statusLower = (t.transactionStatus || "").toLowerCase();
          if (statusLower === 'successful' || statusLower === 'completed' || statusLower === 'success') {
             html += '<div style="margin-top:16px;">';
             html += '<button type="button" class="mmg-btn mmg-btn-danger mmg-btn-sm mmg-refund-btn" data-txnid="' + txnId + '">';
             html += '<span class="dashicons dashicons-undo" style="font-size:14px;width:14px;height:14px;"></span> Refund Transaction</button>';
             html += '<span class="spinner mmg-refund-spinner" style="float:none;display:none;margin-left:8px;"></span>';
             html += '<span class="mmg-refund-msg" style="margin-left:8px;font-size:13px;font-weight:500;"></span>';
             html += '</div>';
          }
          
          $result.html(html).show();
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

  /* ---- Transactions tab — refund ---- */
  $("#mmg-panel-transactions").on("click", ".mmg-refund-btn", function() {
    var $btn = $(this);
    var txnId = $btn.data("txnid");
    var $spinner = $btn.siblings(".mmg-refund-spinner");
    var $msg = $btn.siblings(".mmg-refund-msg");
    
    if (!confirm("Are you sure you want to refund transaction " + txnId + "?")) {
        return;
    }
    
    $btn.prop("disabled", true);
    if ($spinner.length) $spinner.show();
    if ($msg.length) $msg.text("").removeClass("mmg-error-text");
    
    $.post(mmg_admin_params.ajax_url, {
      action: "mmg_reversal",
      nonce: mmg_admin_params.nonce,
      txn_id: txnId
    }).done(function(r) {
      if (r.success) {
         if ($msg.length) $msg.text("Refund successful.").css("color", "var(--mmg-success)");
         mmgShowToast("Transaction refunded.", "success");
         $btn.hide();
      } else {
         var err = r.data.message || "Refund failed.";
         if ($msg.length) $msg.addClass("mmg-error-text").text(err);
         mmgShowToast(err, "error");
         $btn.prop("disabled", false);
      }
    }).fail(function() {
      mmgShowToast("Server error.", "error");
      if ($msg.length) $msg.addClass("mmg-error-text").text("Server error.");
      $btn.prop("disabled", false);
    }).always(function() {
      if ($spinner.length) $spinner.hide();
    });
  });
  /* ---- Currency Conversion tab ---- */
  $("#mmg-add-currency-btn").on("click", function() {
    var code = $("#mmg-new-currency-select").val();
    if (!code) return;

    var name = $("#mmg-new-currency-select option:selected").text().split(" - ")[1] || "Custom Currency";
    var flagCode = code.substring(0, 2).toLowerCase();

    var html = '<div class="mmg-currency-card" data-code="' + code + '">' +
        '<div class="mmg-currency-info">' +
          '<img src="https://flagcdn.com/w40/' + flagCode + '.png" class="mmg-flag-icon" onerror="this.src=\'https://flagcdn.com/w40/un.png\'" alt="' + code + '" />' +
          '<div>' +
            '<div class="mmg-currency-code">' + code + '</div>' +
            '<div class="mmg-currency-name">' + name + '</div>' +
          '</div>' +
        '</div>' +
        '<div class="mmg-currency-actions">' +
          '<div class="mmg-input-group">' +
            '<span class="mmg-rate-prefix">1 ' + code + ' =</span>' +
            '<input type="number" step="0.01" name="mmg_currency_rates[' + code + '][rate]" value="1.00" class="mmg-rate-input" />' +
            '<span class="mmg-rate-suffix">GYD</span>' +
          '</div>' +
          '<div class="mmg-toggle-group">' +
            '<label class="mmg-switch">' +
              '<input type="checkbox" name="mmg_currency_rates[' + code + '][enabled]" value="yes" checked />' +
              '<span class="mmg-slider"></span>' +
            '</label>' +
            '<button type="button" class="mmg-btn-remove-currency" title="Remove">&times;</button>' +
          '</div>' +
        '</div>' +
      '</div>';

    $("#mmg-currency-list").append(html);
    $("#mmg-new-currency-select option[value='" + code + "']").remove();
    $("#mmg-new-currency-select").val("");
    
    mmgShowToast(code + " added. Remember to save changes.", "success");
  });

  $("#mmg-currency-list").on("click", ".mmg-btn-remove-currency", function() {
    var $card = $(this).closest(".mmg-currency-card");
    var code = $card.data("code");
    var name = $card.find(".mmg-currency-name").text();

    if (confirm("Remove " + code + " conversion?")) {
      $card.remove();
      $("#mmg-new-currency-select").append('<option value="' + code + '">' + code + ' - ' + name + '</option>');
      mmgShowToast(code + " removed. Remember to save changes.", "success");
    }
  });
});
