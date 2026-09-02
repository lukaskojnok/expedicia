function confirmAction(message, onConfirm, confirmButtonText) {
  if (!$("#app-confirm-modal-styles").length) {
    $("head").append(
      '<style id="app-confirm-modal-styles">' +
        '.app-confirm-modal{position:fixed;z-index:10000;inset:0;display:flex;align-items:center;justify-content:center;padding:20px}' +
        '.app-confirm-modal_backdrop{position:absolute;inset:0;background:rgba(0,0,0,.55)}' +
        '.app-confirm-modal_content{position:relative;width:min(100%,440px);padding:28px;border-radius:12px;background:#fff;box-shadow:0 16px 50px rgba(0,0,0,.3)}' +
        '.app-confirm-modal_content p{margin:0;font-size:17px;line-height:1.45}' +
        '.app-confirm-modal_actions{display:flex;justify-content:flex-end;gap:10px;margin-top:24px}' +
      '</style>'
    );
  }

  const $modal = $(
    '<div class="app-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="app-confirm-modal-message">' +
      '<div class="app-confirm-modal_backdrop"></div>' +
      '<div class="app-confirm-modal_content">' +
        '<p id="app-confirm-modal-message"></p>' +
        '<div class="app-confirm-modal_actions">' +
          '<button type="button" class="button button-secondary app-confirm-modal-cancel">Zrušiť</button>' +
          '<button type="button" class="button app-confirm-modal-confirm">Áno, uvoľniť</button>' +
        '</div>' +
      '</div>' +
    '</div>'
  );

  function closeModal() {
    $modal.remove();
    $(document).off("keydown.appConfirmModal");
  }

  $modal.find("#app-confirm-modal-message").text(message);
  $modal.find(".app-confirm-modal-confirm").text(confirmButtonText || "Áno, potvrdiť");
  $modal.find(".app-confirm-modal-cancel, .app-confirm-modal_backdrop").on("click", closeModal);

  $modal.find(".app-confirm-modal-confirm").on("click", function() {
    closeModal();
    onConfirm();
  });

  $(document).on("keydown.appConfirmModal", function(event) {
    if (event.key === "Escape") {
      closeModal();
    }
  });

  $("body").append($modal);
  $modal.find(".app-confirm-modal-cancel").trigger("focus");
}

$(function() {
  const $menuButton = $(".topbar-hamburger");
  const $menu = $("#topbar-dropdown-menu");

  if (!$menuButton.length || !$menu.length) {
    return;
  }

  $menuButton.on("click", function(event) {
    event.preventDefault();
    event.stopPropagation();

    const isOpen = !$menu.is("[hidden]");

    if (isOpen) {
      $menu.attr("hidden", "hidden");
      $menuButton.attr("aria-expanded", "false");
    } else {
      $menu.removeAttr("hidden");
      $menuButton.attr("aria-expanded", "true");
    }
  });

  $menu.on("click", function(event) {
    event.stopPropagation();
  });

  $(document).on("click", function() {
    $menu.attr("hidden", "hidden");
    $menuButton.attr("aria-expanded", "false");
  });

  $(document).on("keydown", function(event) {
    if (event.key === "Escape") {
      $menu.attr("hidden", "hidden");
      $menuButton.attr("aria-expanded", "false");
    }
  });
});

$(function() {
  $(document).on("click", ".expedicny-box-release", function() {
    const $button = $(this);

    confirmAction("Naozaj chcete manuálne uvoľniť tento expedičný box?", function() {
      $button.prop("disabled", true).text("Uvoľňujem…");

      $.ajax({
        url: "/scripts/expedicny_box_release.php",
        method: "POST",
        dataType: "json",
        data: {
          box_id: $button.data("box-id"),
          csrf_token: $("#expedicny-box-release-csrf").val()
        }
      }).done(function(response) {
        if (response.success) {
          window.location.reload();
          return;
        }

        alert(response.message || "Box sa nepodarilo uvoľniť.");
        $button.prop("disabled", false).text("Uvoľniť box");
      }).fail(function(xhr) {
        const response = xhr.responseJSON || {};

        alert(response.message || "Box sa nepodarilo uvoľniť.");
        $button.prop("disabled", false).text("Uvoľniť box");
      });
    }, "Uvoľniť box");
  });
});

$(function() {
  const $claimData = $("#work-claim-data");
  const $conflictModal = $("#work-conflict-modal");
  let claimInProgress = false;

  function closeConflictModal() {
    $conflictModal.attr("hidden", "hidden").attr("aria-hidden", "true");
    $("body").removeClass("work-conflict-modal-open");
  }

  function showConflictModal(workerName) {
    const controlType = String($claimData.data("control-type") || "");
    const action = controlType === "vyskladnenie" ? "vyskladňuje" : "expeduje";

    $("#work-conflict-message").text(
      "Túto objednávku už " + action + " " + workerName + ". Ak budete pokračovať, objednávku prevezmete na svoje meno."
    );
    $conflictModal.removeAttr("hidden").attr("aria-hidden", "false");
    $("body").addClass("work-conflict-modal-open");
  }

  function claimOrder(force) {
    if (!$claimData.length || claimInProgress || String($claimData.data("completed")) === "1") {
      return;
    }

    claimInProgress = true;

    $.ajax({
      url: "/scripts/order_claim.php",
      method: "POST",
      dataType: "json",
      data: {
        order_id: $claimData.data("order-id"),
        typ_kontroly: $claimData.data("control-type"),
        csrf_token: $claimData.data("csrf-token"),
        force: force ? 1 : 0
      }
    }).done(function(response) {
      if (response.success) {
        closeConflictModal();
        $(document).trigger("order-claim-success", [response]);
      }
    }).fail(function(xhr) {
      const response = xhr.responseJSON || {};

      if (response.conflict) {
        showConflictModal(response.worker_name || "iný používateľ");
        return;
      }

      alert(response.message || "Objednávku sa nepodarilo priradiť používateľovi.");
    }).always(function() {
      claimInProgress = false;
    });
  }

  $conflictModal.on("click", "[data-work-conflict-takeover], .work-conflict-modal_backdrop", function() {
    claimOrder(true);
  });

  claimOrder(false);
});

$(function() {
  const $workersTable = $("[data-workers-control-type]");

  if (!$workersTable.length) {
    return;
  }

  const controlType = String($workersTable.data("workers-control-type") || "");

  function renderWorker($cell, worker) {
    $cell.empty();

    if (!worker || !worker.worker_name) {
      $("<span>", { class: "table-empty", text: "—" }).appendTo($cell);
      return;
    }

    const isCompleted = worker.work_status === "ukoncene";
    const label = controlType === "vyskladnenie"
      ? (isCompleted ? "Vyskladnil" : "Vyskladňuje")
      : (isCompleted ? "Expedoval" : "Expeduje");
    const $worker = $("<span>", {
      class: "working-user" + (isCompleted ? " working-user_done" : "")
    });

    $worker.append(document.createTextNode(label + ": "));
    $("<strong>", { text: worker.worker_name }).appendTo($worker);
    $worker.appendTo($cell);
  }

  function refreshWorkers() {
    $.ajax({
      url: "/scripts/order_workers.php",
      method: "GET",
      dataType: "json",
      cache: false,
      data: {
        typ_kontroly: controlType
      }
    }).done(function(response) {
      if (!response.success) {
        return;
      }

      const workers = {};

      $.each(response.workers || [], function(index, worker) {
        workers[String(worker.id)] = worker;
      });

      $("[data-order-worker-id]").each(function() {
        const orderId = String($(this).data("order-worker-id"));
        renderWorker($(this), workers[orderId]);
      });
    });
  }

  refreshWorkers();
  window.setInterval(refreshWorkers, 5000);
});

$(function() {
  $(document).on("dblclick", ".invoice-row", function(event) {
    if ($(event.target).closest("a, button, input, select, textarea").length) {
      return;
    }

    const url = $(this).data("url");

    if (url) {
      window.location.href = url;
    }
  });

  $(document).on("pointerup", ".invoice-row", function(event) {
    if (event.originalEvent.pointerType !== "touch") {
      return;
    }

    if ($(event.target).closest("a, button, input, select, textarea").length) {
      return;
    }

    const now = Date.now();
    const lastTap = $(this).data("last-tap") || 0;

    if (now - lastTap < 500) {
      const url = $(this).data("url");

      if (url) {
        window.location.href = url;
      }
    }

    $(this).data("last-tap", now);
  });
});

$(function() {
  const $modal = $("#order-logs-modal");
  const $modalTitle = $("#order-logs-modal-title");
  const $modalBody = $("#order-logs-modal-body");
  const actionLabels = {
    invoice_opened: "Otvorený detail objednávky",
    work_claimed: "Objednávku začal spracovávať používateľ",
    work_taken_over: "Objednávku prevzal iný používateľ",
    control_completed: "Kontrola objednávky dokončená",
    quick_control_completed: "Rýchle spracovanie objednávky",
    carrier_sent: "Odoslanie zásielky dopravcovi"
  };
  const controlTypeLabels = {
    vyskladnenie: "Vyskladnenie",
    expedicia: "Expedícia"
  };
  const statusLabels = {
    opened: "Otvorené",
    success: "Úspešné",
    error: "Chyba"
  };

  if (!$modal.length) {
    return;
  }

  function formatLogDate(value) {
    const date = String(value || "").trim();
    const match = date.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2}:\d{2})/);

    if (!match) {
      return date || "—";
    }

    return match[3] + ". " + match[2] + ". " + match[1] + ", " + match[4];
  }

  function formatLogJson(value) {
    const text = String(value || "").trim();

    if (text === "") {
      return "";
    }

    try {
      return JSON.stringify(JSON.parse(text), null, 2);
    } catch (error) {
      return text;
    }
  }

  function appendLogMeta($container, label, value) {
    if (value === null || value === undefined || String(value).trim() === "") {
      return;
    }

    const $item = $("<span>");
    $("<strong>").text(label + ": ").appendTo($item);
    $item.append(document.createTextNode(String(value)));
    $container.append($item);
  }

  function renderLogs(response) {
    $modalBody.empty();

    if (response.order && response.order.label_files && response.order.label_files.length) {
      const $orderLabels = $("<div>", { class: "order-log_labels order-log_labels_primary" }).appendTo($modalBody);

      $.each(response.order.label_files, function(labelIndex, file) {
        $("<a>", {
          href: file.url,
          text: response.order.label_files.length > 1 ? "Stiahnuť štítok " + (labelIndex + 1) : "Stiahnuť štítok",
          download: file.name || "stitok"
        }).appendTo($orderLabels);
      });
    }

    if (!response.logs || response.logs.length === 0) {
      $("<div>", {
        class: "order-logs-empty",
        text: "K tejto objednávke zatiaľ nie sú uložené žiadne záznamy."
      }).appendTo($modalBody);
      return;
    }

    $.each(response.logs, function(index, log) {
      const $log = $("<article>", {
        class: "order-log order-log_" + (log.status || "unknown")
      });
      const $header = $("<div>", { class: "order-log_header" }).appendTo($log);
      const $title = $("<div>", { class: "order-log_title" }).appendTo($header);
      const $meta = $("<div>", { class: "order-log_meta" });

      $("<strong>", {
        text: actionLabels[log.action] || log.action || "Záznam objednávky"
      }).appendTo($title);
      $("<time>", {
        text: formatLogDate(log.created_at)
      }).appendTo($title);
      $("<span>", {
        class: "order-log_status order-log_status_" + (log.status || "unknown"),
        text: statusLabels[log.status] || log.status || "Záznam"
      }).appendTo($header);

      appendLogMeta($meta, "Typ", controlTypeLabels[log.typ_kontroly] || log.typ_kontroly);
      appendLogMeta($meta, "Používateľ", log.user_name);
      appendLogMeta($meta, "Dopravca", log.carrier);
      appendLogMeta($meta, "Referencia", log.shipment_reference);
      appendLogMeta($meta, "HTTP", log.api_http_code);

      if (log.print_requested) {
        appendLogMeta($meta, "Tlač", "Vyžiadaná");
      }

      if ($meta.children().length) {
        $meta.appendTo($log);
      }

      if (log.message) {
        $("<div>", {
          class: "order-log_message",
          text: log.message
        }).appendTo($log);
      }

      if (log.label_files && log.label_files.length) {
        const $labels = $("<div>", { class: "order-log_labels" }).appendTo($log);

        $.each(log.label_files, function(labelIndex, file) {
          $("<a>", {
            href: file.url,
            text: log.label_files.length > 1 ? "Stiahnuť štítok " + (labelIndex + 1) : "Stiahnuť štítok",
            download: file.name || "stitok"
          }).appendTo($labels);
        });
      }

      const apiResponse = formatLogJson(log.api_response);

      if (apiResponse !== "") {
        const $details = $("<details>", { class: "order-log_json" }).appendTo($log);
        $("<summary>", { text: "JSON odpoveď dopravcu" }).appendTo($details);
        $("<pre>", { text: apiResponse }).appendTo($details);
      }

      $log.appendTo($modalBody);
    });
  }

  function closeLogsModal() {
    $modal.attr("hidden", "hidden").attr("aria-hidden", "true");
    $("body").removeClass("order-logs-modal-open");
  }

  $(document).on("click", ".order-logs-open", function(event) {
    event.preventDefault();
    event.stopPropagation();

    const orderId = parseInt($(this).data("order-id"), 10) || 0;
    const orderNumber = String($(this).data("order-number") || "—");

    $modalTitle.text(orderNumber);
    $modalBody.html('<div class="order-logs-loading">Načítavam históriu…</div>');
    $modal.removeAttr("hidden").attr("aria-hidden", "false");
    $("body").addClass("order-logs-modal-open");

    $.ajax({
      url: "/scripts/order_logs.php",
      method: "GET",
      dataType: "json",
      data: {
        order_id: orderId
      }
    }).done(function(response) {
      if (!response.success) {
        $modalBody.html("");
        $("<div>", {
          class: "order-logs-error",
          text: response.message || "Históriu objednávky sa nepodarilo načítať."
        }).appendTo($modalBody);
        return;
      }

      renderLogs(response);
    }).fail(function(xhr) {
      const response = xhr.responseJSON || {};

      $modalBody.html("");
      $("<div>", {
        class: "order-logs-error",
        text: response.message || "Históriu objednávky sa nepodarilo načítať."
      }).appendTo($modalBody);
    });
  });

  $modal.on("click", "[data-order-logs-close]", closeLogsModal);

  $(document).on("keydown", function(event) {
    if (event.key === "Escape" && !$modal.is("[hidden]")) {
      closeLogsModal();
    }
  });
});

function showPreloader() {
  $("#page-preloader").addClass("is-active").attr("aria-hidden", "false");
  $("body").addClass("preloader-active");
}

function hidePreloader() {
  $("#page-preloader").removeClass("is-active").attr("aria-hidden", "true");
  $("body").removeClass("preloader-active");
}

$(window).on("pageshow", function() {
  hidePreloader();
});

$(function() {
  const $automaticUpdate = $("#automatic-order-update");

  if (!$automaticUpdate.length) {
    return;
  }

  $.ajax({
    url: "/scripts/update_invoices.php",
    method: "POST",
    dataType: "json",
    global: false,
    data: {
      csrf_token: $automaticUpdate.data("csrf-token"),
      request_type: "quick",
      auto_check: 1
    }
  }).done(function(response) {
    const hasChanges = (parseInt(response.new_orders, 10) || 0) > 0
      || (parseInt(response.changed_orders, 10) || 0) > 0;

    if (response.updated && hasChanges && String($automaticUpdate.data("reload-orders")) === "1") {
      window.location.reload();
    }
  });
});

$(function() {
  const $barcodeInput = $("#barcode-input");
  const $keyboardToggle = $("[data-keyboard-toggle]");

  if (!$barcodeInput.length) {
    return;
  }

  $keyboardToggle.on("click", function() {
    const manualMode = $barcodeInput.attr("inputmode") === "text";
    const nextManualMode = !manualMode;

    $barcodeInput.attr("inputmode", nextManualMode ? "text" : "none");
    $keyboardToggle
      .toggleClass("is-active", nextManualMode)
      .attr("aria-pressed", nextManualMode ? "true" : "false")
      .attr("aria-label", nextManualMode ? "Skryť klávesnicu" : "Zapnúť klávesnicu")
      .attr("title", nextManualMode ? "Skryť klávesnicu" : "Zapnúť klávesnicu");

    $barcodeInput.trigger("blur");

    window.setTimeout(function() {
      $barcodeInput.trigger("focus");
    }, 100);
  });

  $(document).on("click", function(e) {
    if ($(e.target).closest("[nofocus]").length) {
      return;
    }

    $barcodeInput.trigger("focus");
  });
});


$(function() {
  const $summaryToggle = $(".invoice-summary_toggle");
  const $summary = $("#invoice-summary");

  if (!$summaryToggle.length || !$summary.length) {
    return;
  }

  $summaryToggle.on("click", function() {
    const isOpen = $summary.hasClass("is-open");

    $summary.toggleClass("is-open", !isOpen);

    $summaryToggle
      .attr("aria-expanded", isOpen ? "false" : "true")
      .text(isOpen ? "Viac info" : "Menej info");
  });
});