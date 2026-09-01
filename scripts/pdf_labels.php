<?php

function shipment_pdf_safe_filename_part($value, $fallback) {
  $value = preg_replace('/[^a-zA-Z0-9_-]+/', '-', trim((string) $value));
  $value = trim((string) $value, "-_");

  return $value !== "" ? $value : $fallback;
}

function shipment_pdf_label_absolute_path($file) {
  $filename = basename((string) ($file["name"] ?? $file["path"] ?? ""));

  if ($filename === "" || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== "pdf") {
    return "";
  }

  $directory = rtrim(DATAS_ROOT, "/\\") . DIRECTORY_SEPARATOR . "stitky";
  $absolute_path = $directory . DIRECTORY_SEPARATOR . $filename;

  if (!is_file($absolute_path) || !is_readable($absolute_path)) {
    return "";
  }

  $handle = fopen($absolute_path, "rb");
  $signature = $handle ? fread($handle, 5) : "";

  if (is_resource($handle)) {
    fclose($handle);
  }

  return $signature === "%PDF-" ? $absolute_path : "";
}

function shipment_prepare_print_pdf($label_files, $order, $carrier_code) {
  $pdf_files = [];

  foreach ((array) $label_files as $file) {
    if (!is_array($file)) {
      continue;
    }

    $absolute_path = shipment_pdf_label_absolute_path($file);

    if ($absolute_path === "") {
      return [
        "success" => false,
        "file" => [],
        "error" => "Automatická tlač podporuje iba platné PDF štítky."
      ];
    }

    $pdf_files[] = [
      "file" => $file,
      "absolute_path" => $absolute_path
    ];
  }

  if (empty($pdf_files)) {
    return [
      "success" => false,
      "file" => [],
      "error" => "Na automatickú tlač sa nenašiel žiadny PDF štítok."
    ];
  }

  if (count($pdf_files) === 1) {
    $file = $pdf_files[0]["file"];
    $filename = basename((string) ($file["name"] ?? $file["path"]));

    return [
      "success" => true,
      "file" => [
        "name" => $filename,
        "path" => "data/stitky/" . $filename,
        "url" => "/data/stitky/" . rawurlencode($filename),
        "mime_type" => "application/pdf"
      ],
      "error" => ""
    ];
  }

  $autoload_path = __DIR__ . "/../vendor/autoload.php";

  if (!is_file($autoload_path)) {
    return [
      "success" => false,
      "file" => [],
      "error" => "Chýba Composer autoload. Nainštaluj balíky setasign/fpdi a setasign/fpdf."
    ];
  }

  require_once $autoload_path;

  if (!class_exists("\\setasign\\Fpdi\\Fpdi")) {
    return [
      "success" => false,
      "file" => [],
      "error" => "Knižnica FPDI nie je nainštalovaná. Spusť Composer require pre setasign/fpdi a setasign/fpdf."
    ];
  }

  $order_number = shipment_pdf_safe_filename_part(
    $order["cislo_objednavky"] ?? $order["id"] ?? "objednavka",
    "objednavka"
  );
  $carrier = shipment_pdf_safe_filename_part($carrier_code, "dopravca");
  $directory = rtrim(DATAS_ROOT, "/\\") . DIRECTORY_SEPARATOR . "stitky";
  $filename = "print-" . strtolower($carrier) . "-" . $order_number . "-" . date("Ymd-His") . ".pdf";
  $absolute_path = $directory . DIRECTORY_SEPARATOR . $filename;

  try {
    $random_suffix = bin2hex(random_bytes(6));
  } catch (Throwable $exception) {
    $random_suffix = str_replace(".", "", uniqid("", true));
  }

  $temporary_path = $absolute_path . ".tmp-" . $random_suffix;

  try {
    $pdf = new \setasign\Fpdi\Fpdi();
    $pdf->SetAutoPageBreak(false);

    foreach ($pdf_files as $pdf_file) {
      $page_count = $pdf->setSourceFile($pdf_file["absolute_path"]);

      for ($page_number = 1; $page_number <= $page_count; $page_number++) {
        $template_id = $pdf->importPage($page_number);
        $size = $pdf->getTemplateSize($template_id);
        $orientation = $size["width"] > $size["height"] ? "L" : "P";

        $pdf->AddPage($orientation, [$size["width"], $size["height"]]);
        $pdf->useTemplate($template_id);
      }
    }

    $pdf->Output("F", $temporary_path);

    if (!is_file($temporary_path) || filesize($temporary_path) === 0) {
      throw new RuntimeException("FPDI nevytvorilo výsledný PDF súbor.");
    }

    if (!rename($temporary_path, $absolute_path)) {
      throw new RuntimeException("Výsledný PDF súbor sa nepodarilo uložiť.");
    }
  } catch (Throwable $exception) {
    if (is_file($temporary_path)) {
      unlink($temporary_path);
    }

    return [
      "success" => false,
      "file" => [],
      "error" => "PDF štítky sa nepodarilo spojiť: " . $exception->getMessage()
    ];
  }

  return [
    "success" => true,
    "file" => [
      "name" => $filename,
      "path" => "data/stitky/" . $filename,
      "url" => "/data/stitky/" . rawurlencode($filename),
      "mime_type" => "application/pdf"
    ],
    "error" => ""
  ];
}
