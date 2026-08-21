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
      const $shipmentSubmit = $("#shipment-submit");
      const $shipmentMessage = $("#shipment-message");
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
        $shipmentSubmit.prop("disabled", getShipmentWeights().length === 0);
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

        $activeWeight.val(value).trigger("focus");
        updateShipmentSubmit();
      });

      $shipmentForm.on("submit", function(event) {
        event.preventDefault();

        const weights = getShipmentWeights();

        if (!weights.length) {
          return;
        }

        $shipmentSubmit.prop("disabled", true).text("Odosielam…");
        $shipmentMessage.removeClass("is-error is-success").text("");

        $.ajax({
          url: "/scripts/send_data.php",
          method: "POST",
          dataType: "json",
          data: { order_id: $shipmentForm.data("order-id"), weights: weights }
        }).done(function(response) {
          if (response.success) {
            $shipmentMessage.addClass("is-success").text(response.message || "Zásielka bola odoslaná dopravcovi.");
            $shipmentSubmit.text("Odoslané dopravcovi");
            return;
          }

          $shipmentMessage.addClass("is-error").text(response.message || "Zásielku sa nepodarilo odoslať.");
          $shipmentSubmit.prop("disabled", false).text("Poslať dopravcovi");
        }).fail(function(xhr) {
          const response = xhr.responseJSON || {};
          $shipmentMessage.addClass("is-error").text(response.message || "Nastala chyba pri komunikácii so serverom.");
          $shipmentSubmit.prop("disabled", false).text("Poslať dopravcovi");
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