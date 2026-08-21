<header class="topbar">
  <div class="topbar_inner">

    <div class="topbar_left">
      <a href="/" class="topbar_logo">Expedícia</a>

      <div class="topbar_page">
        <h1><?= htmlspecialchars($page_title, ENT_QUOTES, "UTF-8") ?></h1>

        <?php if ($topbar_count_value !== "") { ?>
          <span class="topbar_count">
            <strong><?= htmlspecialchars((string) $topbar_count_value, ENT_QUOTES, "UTF-8") ?></strong>
            <?= htmlspecialchars($topbar_count_label, ENT_QUOTES, "UTF-8") ?>
          </span>
        <?php } ?>
      </div>
    </div>

    <nav class="topbar_menu">
      <?php if (!empty($topbar_back_url)) { ?>
        <a href="<?= htmlspecialchars($topbar_back_url, ENT_QUOTES, "UTF-8") ?>" class="topbar_logout">Späť na zoznam</a>
      <?php } ?>

      <span>Prihlásený: <strong><?= htmlspecialchars($prihlaseny_meno, ENT_QUOTES, "UTF-8") ?></strong></span>
      <a href="/logout.php" class="topbar_logout">Odhlásiť sa</a>
    </nav>

  </div>
</header>

<main class="content-main">

  <?php
  if ($page === "invoice") {
    require __DIR__ . "/includes/invoice.php";
  } else {
    require __DIR__ . "/includes/invoices.php";
  }
  ?>

</main>

<div class="order-logs-modal" id="order-logs-modal" aria-hidden="true" hidden nofocus>
  <div class="order-logs-modal_backdrop" data-order-logs-close></div>
  <section class="order-logs-modal_dialog" role="dialog" aria-modal="true" aria-labelledby="order-logs-modal-title">
    <header class="order-logs-modal_header">
      <div>
        <span>História objednávky</span>
        <strong id="order-logs-modal-title">—</strong>
      </div>
      <button type="button" class="order-logs-modal_close" data-order-logs-close aria-label="Zatvoriť">×</button>
    </header>
    <div class="order-logs-modal_body" id="order-logs-modal-body">
      <div class="order-logs-loading">Načítavam históriu…</div>
    </div>
  </section>
</div>



<?php
if (empty($page)) { //form
?>
<footer class="footbar">
  <div class="footbar_inner">

      <div class="footbar_title">
        <strong>Načítať objednávku</strong>
        <span>Naskenujte čiarový kód alebo ho napíšte ručne</span>
      </div>

      <form action="/" method="get" class="footbar_search" id="barcode-form-order">
        <input
          type="text"
          name="code"
          id="barcode-input"
          class="footbar_input"
          placeholder="Číslo objednávky alebo faktúry"
          autocomplete="off"
          autofocus
        >

        <button type="submit" class="footbar_button">Nájsť</button>
      </form>

      <script>
      $(function() {
        const $barcodeInput = $("#barcode-input");

        $barcodeInput.trigger("focus");

        $(window).on("focus", function() {
          if (!$("body").hasClass("preloader-active")) {
            $barcodeInput.trigger("focus");
          }
        });

        $("#barcode-form-order").on("submit", function(e) {
          e.preventDefault();

          const code = ($barcodeInput.val() || "").trim();
          let invoiceUrl = "";

          if (code === "") {
            $barcodeInput.trigger("focus");
            return;
          }

          $(".invoice-row").each(function() {
            const invoiceNumber = ($(this).attr("data-invoice-number") || "").trim();
            const orderNumber = ($(this).attr("data-order-number") || "").trim();

            if (code === invoiceNumber || code === orderNumber) {
              invoiceUrl = $(this).attr("data-url") || "";
              return false;
            }
          });

          if (invoiceUrl !== "") {
            showPreloader();
            window.location.href = invoiceUrl;
            return;
          }

          alert("Faktúra alebo objednávka s číslom " + code + " sa nenašla.");

          $barcodeInput.val("").trigger("focus");
        });
      });
      </script>

  </div>
</footer>
<?php
} else { //form
?>
<footer class="footbar">
  <div class="footbar_inner">

    <form action="/" method="get" class="footbar_search" id="barcode-form-item">
      <input
        type="text"
        name="code"
        id="barcode-input"
        class="footbar_input"
        placeholder="Kód produktu"
        autocomplete="off"
        autofocus
      >

      <button type="submit" class="footbar_button">Nájsť</button>
    </form>

    <script>
    $(function() {
      const $barcodeInput = $("#barcode-input");
      const $notFound = $("#product-not-found");
      const $notFoundTitle = $("#product-not-found-title");
      const $notFoundCodes = $("#product-not-found-codes");
      let controlLogSent = false;

      function logSuccessfulControl() {
        if (controlLogSent) {
          return;
        }

        controlLogSent = true;

        $.ajax({
          url: "/scripts/control_log.php",
          method: "POST",
          dataType: "json",
          data: {
            order_id: $("#shipment-form").data("order-id") || <?= (int) $order_id ?>,
            typ_kontroly: "<?= htmlspecialchars($typ_kontroly, ENT_QUOTES, "UTF-8") ?>"
          }
        });
      }

      function playWarningSound() {
        const AudioContext = window.AudioContext || window.webkitAudioContext;

        if (!AudioContext) {
          return;
        }

        const audioContext = new AudioContext();
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();

        oscillator.type = "square";
        oscillator.frequency.setValueAtTime(780, audioContext.currentTime);
        oscillator.frequency.setValueAtTime(520, audioContext.currentTime + 0.16);
        gain.gain.setValueAtTime(0.18, audioContext.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.35);

        oscillator.connect(gain);
        gain.connect(audioContext.destination);
        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.35);

        oscillator.onended = function() {
          audioContext.close();
        };
      }

      function showScanError(message, code) {
        const $code = $("<strong>").text(code);

        $notFoundTitle.text(message);
        $notFoundCodes.append($code);
        $notFound.addClass("is-active").attr("aria-hidden", "false");
        playWarningSound();
        $barcodeInput.val("").trigger("focus");
      }

      function showSuccessfulControl() {
        let hasRemainingItems = false;

        $(".invoice-item_amount_control").each(function() {
          const amount = parseInt($(this).attr("item_amount"), 10) || 0;

          if (amount > 0) {
            hasRemainingItems = true;
            return false;
          }
        });

        if (!hasRemainingItems) {
          $("#invoice-items-box").attr("hidden", "hidden");
          $("#invoice-control-success").removeAttr("hidden");
          $(".footbar").attr("hidden", "hidden");
          logSuccessfulControl();

          setTimeout(function() {
            $(".shipment-weight").first().trigger("focus");
          }, 0);
        }
      }

      function processItemCode(code) {
        code = String(code || "").trim();

        if (code === "") {
          $barcodeInput.trigger("focus");
          return;
        }

        let $amountControl = $();

        $(".invoice-item_amount_control").each(function() {
          if (String($(this).attr("item_id") || "").trim() === code) {
            $amountControl = $(this);
            return false;
          }
        });

        if (!$amountControl.length) {
          showScanError("Produkt s týmto kódom nebol nájdený", code);
          return;
        }

        const $row = $amountControl.closest("tr");
        const $codeCell = $row.find(".invoice-item_code");
        const $highlightCells = $codeCell.add($amountControl);
        const currentAmount = parseInt($amountControl.attr("item_amount"), 10) || 0;

        if (currentAmount <= 0 || $row.is("[hidden]")) {
          showScanError("Produkt je naskenovaný navyše", code);
          return;
        }

        const newAmount = currentAmount - 1;

        $amountControl.attr("item_amount", newAmount).text(newAmount);
        $(".invoice-item_code, .invoice-item_amount_control").removeClass("is-counted");

        if (newAmount === 0) {
          $row.attr("hidden", "hidden");
          showSuccessfulControl();
        } else {
          $row.removeAttr("hidden");
          $row.closest("tbody").prepend($row);
          $highlightCells.addClass("is-counted");
        }

        $barcodeInput.val("").trigger("focus");
      }

      $notFound.on("click", function() {
        $notFound.removeClass("is-active").attr("aria-hidden", "true");
        $notFoundCodes.empty();
        $barcodeInput.val("").trigger("focus");
      });

      $barcodeInput.trigger("focus");

      $(window).on("focus", function() {
        if (!$("body").hasClass("preloader-active")) {
          $barcodeInput.trigger("focus");
        }
      });

      $("#barcode-form-item").on("submit", function(e) {
        e.preventDefault();
        processItemCode($barcodeInput.val());
      });

      $(".invoice-item_code").on("dblclick", function() {
        const code = String($(this).attr("item_id") || "").trim();

        $barcodeInput.val(code);
        $("#barcode-form-item").trigger("submit");
      });

      const $shipmentForm = $("#shipment-form");

      if (!$shipmentForm.length) {
        return;
      }

      const $parcels = $("#shipment-parcels");
      const $shipmentButtons = $(".shipment-submit[data-print-label]");
      const $shipmentMessage = $("#shipment-message");
      const $shipmentErrorJson = $("#shipment-error-json");
      const $shipmentCompleted = $("#shipment-completed");
      const $shipmentCompletedWarning = $("#shipment-completed-warning");
      const $shipmentCompletedLabels = $("#shipment-completed-labels");
      let $activeWeight = $parcels.find(".shipment-weight").first();

      function updateParcelLabels() {
        $parcels.find(".shipment-parcel").each(function(index) {
          $(this).find(".shipment-parcel_label").text("Balík " + (index + 1));
        });

        $(".shipment-parcel_remove").toggle($parcels.find(".shipment-parcel").length > 1);
      }

      function getShipmentWeights() {
        const weights = [];

        $parcels.find(".shipment-weight").each(function() {
          const weight = parseFloat(String($(this).val()).replace(",", "."));

          if (weight > 0) {
            weights.push(weight);
          }
        });

        return weights;
      }

      function updateShipmentSubmit() {
        $shipmentButtons.prop("disabled", getShipmentWeights().length === 0);
      }

      function formatApiResponse(apiResponse) {
        if (apiResponse && typeof apiResponse === "object") {
          return JSON.stringify(apiResponse, null, 2);
        }

        const responseText = String(apiResponse || "").trim();

        if (responseText === "") {
          return JSON.stringify({
            success: false,
            message: "Server ani dopravca nevrátili JSON odpoveď."
          }, null, 2);
        }

        try {
          return JSON.stringify(JSON.parse(responseText), null, 2);
        } catch (error) {
          return responseText;
        }
      }

      function showShipmentError(message, apiResponse) {
        $shipmentMessage.removeClass("is-success").addClass("is-error").text(message || "Zásielku sa nepodarilo odoslať.");
        $shipmentErrorJson.text(formatApiResponse(apiResponse)).removeAttr("hidden");
        updateShipmentSubmit();
      }

      function showShipmentCompleted(response) {
        $shipmentForm.attr("hidden", "hidden");
        $shipmentCompleted.removeAttr("hidden");
        $shipmentCompletedLabels.empty();
        $(".order-logs-open")
          .removeClass("status-waiting status-active")
          .addClass("status-done")
          .text("Ukončené");

        if (response.warning) {
          $shipmentCompletedWarning.text(response.warning).removeAttr("hidden");
        } else {
          $shipmentCompletedWarning.attr("hidden", "hidden").text("");
        }

        $.each(response.label_files || [], function(index, file) {
          const linkText = (response.label_files || []).length > 1
            ? "Stiahnuť štítok " + (index + 1)
            : "Stiahnuť štítok";

          $("<a>", {
            href: file.url,
            text: linkText,
            download: file.name || "stitok"
          }).appendTo($shipmentCompletedLabels);
        });
      }

      function selectWeight($input) {
        $activeWeight = $input;
        $(".shipment-weight").removeClass("is-active");
        $activeWeight.addClass("is-active");
      }

      $parcels.on("focus click", ".shipment-weight", function() {
        selectWeight($(this));
      });

      $parcels.on("input", ".shipment-weight", function() {
        selectWeight($(this));
        updateShipmentSubmit();
      });

      $("#shipment-add-parcel").on("click", function() {
        const $newParcel = $parcels.find(".shipment-parcel").first().clone();

        $newParcel.find(".shipment-weight").val("");
        $parcels.append($newParcel);
        updateParcelLabels();
        $newParcel.find(".shipment-weight").trigger("focus");
      });

      $parcels.on("click", ".shipment-parcel_remove", function() {
        const $parcel = $(this).closest(".shipment-parcel");
        const wasActive = $parcel.find(".shipment-weight").is($activeWeight);

        if ($parcels.find(".shipment-parcel").length <= 1) {
          return;
        }

        $parcel.remove();
        updateParcelLabels();

        if (wasActive) {
          selectWeight($parcels.find(".shipment-weight").last());
        }

        updateShipmentSubmit();
      });

      $("#shipment-keypad").on("click", "button", function() {
        const key = String($(this).data("key"));
        let value = String($activeWeight.val() || "");

        if (key === "delete") {
          value = value.slice(0, -1);
        } else if (key === ",") {
          if (value.indexOf(",") !== -1) {
            return;
          }

          value += value === "" ? "0," : ",";
        } else if (value.length < 6) {
          value += key;
        }

        $activeWeight.val(value);
        updateShipmentSubmit();
      });

      $shipmentForm.on("submit", function(event) {
        event.preventDefault();
      });

      $shipmentButtons.on("click", function() {
        const $clickedButton = $(this);
        const printLabel = String($clickedButton.data("print-label")) === "1";

        const weights = getShipmentWeights();

        if (!weights.length) {
          return;
        }

        $shipmentButtons.prop("disabled", true);
        $clickedButton.text("Odosielam…");
        $shipmentMessage.removeClass("is-error is-success").text("");
        $shipmentErrorJson.attr("hidden", "hidden").text("");

        $.ajax({
          url: "/scripts/send_data.php",
          method: "POST",
          dataType: "json",
          data: {
            order_id: $shipmentForm.data("order-id"),
            typ_kontroly: $shipmentForm.data("control-type"),
            weights: weights,
            print_label: printLabel ? 1 : 0
          }
        }).done(function(response) {
          if (response.success) {
            showShipmentCompleted(response);

            if (printLabel && response.print_url) {
              $("#shipment-label-print-frame").remove();

              $("<iframe>", {
                id: "shipment-label-print-frame",
                src: response.print_url,
                title: "Tlač štítku",
                css: {
                  position: "fixed",
                  width: "1px",
                  height: "1px",
                  right: "0",
                  bottom: "0",
                  opacity: "0",
                  border: "0",
                  pointerEvents: "none"
                }
              }).appendTo("body");
            }

            return;
          }

          showShipmentError(response.message, response.api_response || response);
        }).fail(function(xhr) {
          const response = xhr.responseJSON || {};
          const rawResponse = response.api_response || xhr.responseText || response;

          showShipmentError(response.message || "Nastala neznáma chyba pri komunikácii so serverom.", rawResponse);
        }).always(function() {
          $shipmentButtons.each(function() {
            const printValue = String($(this).data("print-label"));
            $(this).text(printValue === "1" ? "Poslať a vytlačiť štítok" : "Poslať bez vytlačenia");
          });
        });
      });

      updateParcelLabels();
      selectWeight($activeWeight);
      updateShipmentSubmit();
    });
    </script>

  </div>
</footer>
<?php
} //form
?>


<div class="preloader" id="page-preloader" aria-hidden="true">
  <span class="preloader_spinner"></span>
</div>

<?php if ($page === "invoice") { ?>
  <div class="product-not-found" id="product-not-found" aria-hidden="true">
    <div class="product-not-found_content">
      <span id="product-not-found-title">Produkt s týmto kódom nebol nájdený</span>
      <div class="product-not-found_codes" id="product-not-found-codes"></div>
      <small>Kliknutím kdekoľvek zatvorte hlášku</small>
    </div>
  </div>
<?php } ?>
