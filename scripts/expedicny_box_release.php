<?php
require_once __DIR__ . "/../config/common.php";

header("Content-Type: application/json; charset=utf-8");

function box_release_response($success, $message, $http_code = 200) {
  http_response_code($http_code);
  echo json_encode(["success" => $success, "message" => $message], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !auth_is_logged_in()) {
  box_release_response(false, "Neplatná požiadavka.", 401);
}

$box_id = (int) ($_POST["box_id"] ?? 0);
$csrf_token = (string) ($_POST["csrf_token"] ?? "");

if ($box_id <= 0 || !auth_csrf_is_valid($csrf_token)) {
  box_release_response(false, "Neplatná alebo expirovaná požiadavka.", 422);
}

$query = $db->prepare("SELECT order_id FROM expedicne_boxy WHERE id = :id LIMIT 1");
$query->execute([":id" => $box_id]);
$order_id = (int) $query->fetchColumn();

if ($order_id > 0) {
  $db->beginTransaction();
  $db->prepare("UPDATE expedicne_boxy SET order_id = NULL, obsadeny_at = NULL WHERE id = :id")->execute([":id" => $box_id]);
  $db->prepare("UPDATE orders SET ulozne_miesto = NULL WHERE id = :id")->execute([":id" => $order_id]);
  $db->commit();
}

box_release_response(true, "Expedičný box bol uvoľnený.");
