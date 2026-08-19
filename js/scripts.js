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