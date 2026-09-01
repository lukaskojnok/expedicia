<?php
require_once __DIR__ . "/../config/common.php";
require_once __DIR__ . "/../config/controls_log.php";

header("Content-Type: application/json; charset=utf-8");

function box_assign_response($success, $message, $http_code = 200, $data = []) {
  http_response_code($http_code);
  echo json_encode(array_merge([
    "success" => $success,
    "message" => $message
  ], $data), JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !auth_is_logged_in()) {
  box_assign_response(false, "Neplatná požiadavka.", 401);
}

$order_id = (int) ($_POST["order_id"] ?? 0);
$kod = strtoupper(trim((string) ($_POST["kod"] ?? "")));
$csrf_token = (string) ($_POST["csrf_token"] ?? "");

if (!auth_csrf_is_valid($csrf_token)) {
  box_assign_response(false, "Platnosť požiadavky vypršala. Obnov stránku.", 419);
}

if ($order_id <= 0 || $kod === "") {
  box_assign_response(false, "Naskenujte platný kód expedičného boxu.", 422);
}

try {
  $db->beginTransaction();

  $query = $db->prepare("SELECT id, kod, order_id FROM expedicne_boxy WHERE kod = :kod LIMIT 1 FOR UPDATE");
  $query->execute([":kod" => $kod]);
  $box = $query->fetch(PDO::FETCH_ASSOC);

  if (!$box) {
    $db->rollBack();
    box_assign_response(false, "Expedičný box s kódom {$kod} neexistuje.", 404);
  }

  if (!empty($box["order_id"]) && (int) $box["order_id"] !== $order_id) {
    $db->rollBack();
    box_assign_response(false, "Expedičný box {$kod} už obsahuje inú objednávku.", 409);
  }

  $query = $db->prepare("SELECT id FROM orders WHERE id = :id LIMIT 1 FOR UPDATE");
  $query->execute([":id" => $order_id]);
  $order = $query->fetch(PDO::FETCH_ASSOC);

  if (!$order) {
    $db->rollBack();
    box_assign_response(false, "Objednávka neexistuje.", 404);
  }

  $query = $db->prepare("UPDATE expedicne_boxy SET order_id = NULL, obsadeny_at = NULL WHERE order_id = :order_id AND id <> :box_id");
  $query->execute([":order_id" => $order_id, ":box_id" => (int) $box["id"]]);

  $query = $db->prepare("UPDATE expedicne_boxy SET order_id = :order_id, obsadeny_at = NOW() WHERE id = :id");
  $query->execute([":order_id" => $order_id, ":id" => (int) $box["id"]]);

  $admin_data = controls_get_admin_data($db);
  $user_id = (int) ($admin_data["id"] ?? 0);

  $query = $db->prepare("
    UPDATE orders
    SET status_vyskladnenie = 'ukoncene', vyskladnenie_user_id = :user_id, ulozne_miesto = :kod, zmena = 0
    WHERE id = :id
  ");
  $query->execute([":user_id" => $user_id, ":kod" => $kod, ":id" => $order_id]);

  controls_add_log($db, $order_id, $user_id, "vyskladnenie", "box_assigned", "success", [
    "finished" => true,
    "message" => "Objednávka uložená do expedičného boxu {$kod}."
  ]);

  $db->commit();
  box_assign_response(true, "Objednávka bola uložená do boxu.", 200, ["kod" => $kod]);
} catch (Throwable $error) {
  if ($db->inTransaction()) {
    $db->rollBack();
  }

  box_assign_response(false, "Objednávku sa nepodarilo uložiť do boxu.", 500);
}
