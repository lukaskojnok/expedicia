<?php
require_once __DIR__ . "/../config/common.php";

header("Content-Type: application/json; charset=utf-8");

function delete_order_response($success, $message, $http_code = 200) {
  http_response_code($http_code);
  echo json_encode([
    "success" => $success,
    "message" => $message
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !auth_is_logged_in()) {
  delete_order_response(false, "Neplatná požiadavka.", 401);
}

$order_id = (int) ($_POST["order_id"] ?? 0);
$csrf_token = (string) ($_POST["csrf_token"] ?? "");

if ($order_id <= 0 || !auth_csrf_is_valid($csrf_token)) {
  delete_order_response(false, "Neplatná alebo expirovaná požiadavka.", 422);
}

try {
  $db->beginTransaction();

  $query = $db->prepare("SELECT id FROM orders WHERE id = :id LIMIT 1 FOR UPDATE");
  $query->execute([":id" => $order_id]);

  if (!$query->fetchColumn()) {
    $db->rollBack();
    delete_order_response(false, "Objednávka nebola nájdená.", 404);
  }

  $db->prepare("UPDATE expedicne_boxy SET order_id = NULL, obsadeny_at = NULL WHERE order_id = :order_id")->execute([":order_id" => $order_id]);
  $db->prepare("DELETE FROM controls_logs WHERE order_id = :order_id")->execute([":order_id" => $order_id]);
  $db->prepare("DELETE FROM orders_items WHERE order_id = :order_id")->execute([":order_id" => $order_id]);
  $db->prepare("DELETE FROM orders WHERE id = :id")->execute([":id" => $order_id]);

  $db->commit();
  delete_order_response(true, "Objednávka bola vymazaná.");
} catch (Throwable $error) {
  if ($db->inTransaction()) {
    $db->rollBack();
  }

  delete_order_response(false, "Objednávku sa nepodarilo vymazať.", 500);
}
