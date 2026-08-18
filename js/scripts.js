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