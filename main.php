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
          $barcodeInput.addClass("is-error");

          setTimeout(function() {
            $barcodeInput.removeClass("is-error");
          }, 2000);

          $barcodeInput.val("").trigger("focus");
          return;
        }

        const $row = $amountControl.closest("tr");
        const $codeCell = $row.find(".invoice-item_code");
        const $highlightCells = $codeCell.add($amountControl);
        const currentAmount = parseInt($amountControl.attr("item_amount"), 10) || 0;

        if (currentAmount <= 0 || $row.is("[hidden]")) {
          $barcodeInput.val("").trigger("focus");
          return;
        }

        const newAmount = currentAmount - 1;

        $amountControl.attr("item_amount", newAmount).text(newAmount);

        clearTimeout($row.data("counted-timeout"));
        $highlightCells.removeClass("is-counted");

        requestAnimationFrame(function() {
          $highlightCells.addClass("is-counted");
        });

        if (newAmount === 0) {
          $row.attr("hidden", "hidden");
        }

        const countedTimeout = setTimeout(function() {
          $highlightCells.removeClass("is-counted");
        }, 2000);

        $row.data("counted-timeout", countedTimeout);

        $barcodeInput.val("").trigger("focus");
      }

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
