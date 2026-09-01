<?php
// https://diligent-pink-lynx.87-236-196-161.cpanel.site/generate_barcode.php

require_once __DIR__ . "/vendor/autoload.php";

use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

$input = $_POST["codes"] ?? "";
$codes = [];
$barcodes = [];
$error = "";

if ($input !== "") {
  $lines = preg_split("/\R/", $input);

  foreach ($lines as $line) {
    $code = trim($line);

    if ($code !== "") {
      $codes[] = $code;
    }
  }

  $codes = array_values(array_unique($codes));

  if (count($codes) > 100) {
    $error = "Naraz môžeš vygenerovať maximálne 100 čiarových kódov.";
    $codes = [];
  }

  foreach ($codes as $code) {
    try {
      $barcode = (new TypeCode128())->getBarcode($code);

      $renderer = new SvgRenderer();
      $renderer->setSvgType(SvgRenderer::TYPE_SVG_INLINE);

      $barcodes[] = [
        "code" => $code,
        "svg" => $renderer->render(
          $barcode,
          $barcode->getWidth() * 4,
          120
        )
      ];
    } catch (Throwable $e) {
      $error = "Nepodarilo sa vygenerovať kód: " . $code;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="sk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Generátor čiarových kódov</title>

  <style>
    * {
      box-sizing: border-box;
    }
    body {
      margin: 0; padding: 30px; font-family: Arial, sans-serif; color: #111; background: #f5f5f5;
    }
    .barcode-generator {
      width: 100%; max-width: 900px; margin: 0 auto;
    }
    .barcode-form {
      padding: 25px; background: #fff; border: 1px solid #ddd; border-radius: 8px;
    }
    .barcode-form h1 {
      margin: 0 0 15px; font-size: 26px;
    }
    .barcode-form p {
      margin: 0 0 15px; color: #666;
    }
    .barcode-form textarea {
      display: block; width: 100%; height: 220px; padding: 15px; font-family: monospace; font-size: 18px; line-height: 1.5; border: 1px solid #bbb; border-radius: 5px; resize: vertical;
    }
    .barcode-form button {
      display: inline-block; margin-top: 15px; padding: 12px 22px; font-size: 16px; font-weight: bold; color: #fff; background: #111; border: 0; border-radius: 5px; cursor: pointer;
    }
    .barcode-form button:hover {
      background: #333;
    }
    .barcode-error {
      margin-top: 15px; padding: 12px; color: #a40000; background: #ffe5e5; border-radius: 5px;
    }
    .barcode-actions {
      display: flex; justify-content: flex-end; margin: 25px 0 10px;
    }
    .barcode-actions button {
      padding: 10px 18px; font-size: 15px; color: #fff; background: #2f80ed; border: 0; border-radius: 5px; cursor: pointer;
    }
    .barcode-list {
      padding: 20mm; background: #fff;
    }
    .barcode-item {
      display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 42mm; padding: 10mm 0; page-break-inside: avoid; break-inside: avoid;
    }
    .barcode-item svg {
      display: block; max-width: 100%; height: 30mm;
    }
    .barcode-code {
      margin-top: 8px; font-size: 22px; font-weight: bold; letter-spacing: 2px;
    }
    @media print {
      @page {
        size: A4 portrait;
        margin: 10mm;
      }
      body {
        padding: 0; background: #fff;
      }
      .barcode-generator {
        max-width: none;
      }
      .barcode-form, .barcode-actions {
        display: none;
      }
      .barcode-list {
        padding: 0;
      }
    }
  </style>
</head>

<body>

  <main class="barcode-generator">

    <form method="post" class="barcode-form">
      <h1>Generátor čiarových kódov</h1>

      <p>Každý kód vlož na samostatný riadok.</p>

      <textarea
        name="codes"
        placeholder="92084&#10;39764&#10;38770"
        autofocus
      ><?= htmlspecialchars($input, ENT_QUOTES, "UTF-8") ?></textarea>

      <button type="submit">Vygenerovať čiarové kódy</button>

      <?php if ($error !== "") { ?>
        <div class="barcode-error">
          <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </div>
      <?php } ?>
    </form>

    <?php if (!empty($barcodes)) { ?>

      <div class="barcode-actions">
        <button type="button" onclick="window.print()">
          Vytlačiť na A4
        </button>
      </div>

      <div class="barcode-list">

        <?php foreach ($barcodes as $item) { ?>

          <div class="barcode-item">

            <?= $item["svg"] ?>

            <div class="barcode-code">
              <?= htmlspecialchars($item["code"], ENT_QUOTES, "UTF-8") ?>
            </div>

          </div>

        <?php } ?>

      </div>

    <?php } ?>

  </main>

</body>
</html>