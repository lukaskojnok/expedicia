<?php
require_once __DIR__ . "/../config/common.php";

header("Content-Type: application/json; charset=utf-8");

function find_order_response($success, $message, $http_code = 200, $data = []) {
  http_response_code($http_code);
  echo json_encode(array_merge([
    "success" => $success,
    "message" => $message
  ], $data), JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !auth_is_logged_in()) {
  find_order_response(false, "Neplatná požiadavka.", 401);
}

$code = trim((string) ($_POST["code"] ?? ""));
$typ_kontroly = (string) ($_POST["typ_kontroly"] ?? "");

if ($code === "" || !in_array($typ_kontroly, ["vyskladnenie", "expedicia"], true)) {
  find_order_response(false, "Naskenujte platný kód.", 422);
}

$query = $db->prepare("
  SELECT order_id
  FROM expedicne_boxy
  WHERE UPPER(kod) = UPPER(:code)
  LIMIT 1
");
$query->execute([":code" => $code]);
$box_order_id = $query->fetchColumn();

if ($box_order_id !== false) {
  if ($typ_kontroly !== "expedicia") {
    find_order_response(false, "Expedičný box sa dá načítať iba v režime expedície.", 422);
  }

  $order_id = (int) $box_order_id;

  if ($order_id <= 0) {
    find_order_response(false, "Expedičný box {$code} je prázdny.", 404);
  }

  $query = $db->prepare("
    SELECT id
    FROM orders
    WHERE id = :id
      AND status_vyskladnenie = 'ukoncene'
    LIMIT 1
  ");
  $query->execute([":id" => $order_id]);
  $order_id = (int) $query->fetchColumn();

  if ($order_id <= 0) {
    find_order_response(false, "Objednávka priradená k boxu {$code} nebola nájdená alebo ešte nie je vyskladnená.", 404);
  }

  find_order_response(true, "Objednávka bola nájdená.", 200, [
    "url" => "/invoice?id={$order_id}&typ=expedicia"
  ]);
}

$status_condition = $typ_kontroly === "vyskladnenie"
  ? "status_vyskladnenie IN ('nove', 'v_procese')"
  : "status_vyskladnenie = 'ukoncene'";

$query = $db->prepare("
  SELECT id
  FROM orders
  WHERE (cislo_objednavky = :order_code OR cislo_faktury = :invoice_code)
    AND {$status_condition}
  LIMIT 1
");
$query->execute([
  ":order_code" => $code,
  ":invoice_code" => $code
]);
$order_id = (int) $query->fetchColumn();

if ($order_id <= 0) {
  find_order_response(false, "Objednávka, faktúra alebo expedičný box s týmto kódom sa nenašli.", 404);
}

find_order_response(true, "Objednávka bola nájdená.", 200, [
  "url" => "/invoice?id={$order_id}&typ=" . rawurlencode($typ_kontroly)
]);