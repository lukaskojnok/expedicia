<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use setasign\Fpdi\Fpdi;

$tempFiles = [];

try {
  /*
   * 1. TEST FPDF
   * Vytvoríme dve samostatné PDF.
   */
  for ($i = 1; $i <= 2; $i++) {
    $filePath = sys_get_temp_dir() . '/fpdi-test-' . uniqid('', true) . '-' . $i . '.pdf';
    $tempFiles[] = $filePath;

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(0, 15, 'TEST PDF - strana ' . $i, 0, 1, 'C');

    $pdf->SetFont('Arial', '', 12);
    $pdf->Ln(10);
    $pdf->MultiCell(
      0,
      8,
      'Toto PDF bolo vytvorene pomocou kniznice setasign/fpdf.'
    );

    $pdf->Output('F', $filePath);

    if (!is_file($filePath) || filesize($filePath) === 0) {
      throw new RuntimeException('Nepodarilo sa vytvorit testovacie PDF cislo ' . $i . '.');
    }
  }

  /*
   * 2. TEST FPDI
   * Obe vytvorené PDF spojíme do jedného.
   */
  $mergedPdf = new Fpdi();

  foreach ($tempFiles as $filePath) {
    $pageCount = $mergedPdf->setSourceFile($filePath);

    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
      $templateId = $mergedPdf->importPage($pageNumber);
      $pageSize = $mergedPdf->getTemplateSize($templateId);

      $mergedPdf->AddPage(
        $pageSize['orientation'],
        [$pageSize['width'], $pageSize['height']]
      );

      $mergedPdf->useTemplate($templateId);
    }
  }

  /*
   * 3. Výsledok zobrazíme priamo v prehliadači.
   */
  $mergedPdf->Output('I', 'fpdf-fpdi-test.pdf');
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=UTF-8');

  echo "TEST NEUSPESNY\n\n";
  echo 'Chyba: ' . $e->getMessage();
} finally {
  foreach ($tempFiles as $filePath) {
    if (is_file($filePath)) {
      unlink($filePath);
    }
  }
}