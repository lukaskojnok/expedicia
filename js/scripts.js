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
    control_completed: "Kontrola objednávky dokončená",
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
  const $barcodeInput = $("#barcode-input");

  if (!$barcodeInput.length) {
    return;
  }

  $(document).on("click", function(e) {
    if ($(e.target).closest("[nofocus]").length) {
      return;
    }

    $barcodeInput.trigger("focus");
  });
});