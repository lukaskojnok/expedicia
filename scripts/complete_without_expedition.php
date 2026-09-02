<?php
require_once __DIR__ . "/../config/common.php";
require_once __DIR__ . "/../config/controls_log.php";

header("Content-Type: application/json; charset=utf-8");

function complete_without_expedition_response($success, $message, $http_code = 200) {
  http_response_code($http_code);
  echo json_encode(["success" => $success, "message" => $message], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !auth_is_logged_in()) {
  complete_without_expedition_response(false, "Neplatná požiadavka.", 401);
}

$order_id = (int) ($_POST["order_id"] ?? 0);
$csrf_token = (string) ($_POST["csrf_token"] ?? "");

if ($order_id <= 0 || !auth_csrf_is_valid($csrf_token)) {
  complete_without_expedition_response(false, "Neplatná alebo expirovaná požiadavka.", 422);
}

$admin_data = controls_get_admin_data($db);
$user_id = (int) ($admin_data["id"] ?? 0);

if ($user_id <= 0) {
  complete_without_expedition_response(false, "Nepodarilo sa určiť prihláseného administrátora.", 401);
}

try {
  $db->beginTransaction();

  $query = $db->prepare("SELECT id, status_odoslanie_dopravcovi FROM orders WHERE id = :id LIMIT 1 FOR UPDATE");
  $query->execute([":id" => $order_id]);
  $order = $query->fetch(PDO::FETCH_ASSOC);

  if (!$order) {
    $db->rollBack();
    complete_without_expedition_response(false, "Objednávka nebola nájdená.", 404);
  }

  if (($order["status_odoslanie_dopravcovi"] ?? "") === "success") {
    $db->rollBack();
    complete_without_expedition_response(false, "Objednávka už bola odoslaná dopravcovi.", 409);
  }

  if (($order["status_odoslanie_dopravcovi"] ?? "") === "skipped") {
    $db->rollBack();
    complete_without_expedition_response(false, "Objednávka už bola ukončená bez expedovania.", 409);
  }

  $query = $db->prepare("UPDATE orders SET status_expedicia = 'ukoncene', status_odoslanie_dopravcovi = 'skipped', expedicia_user_id = :user_id, zmena = 0 WHERE id = :id");
  $query->execute([":user_id" => $user_id, ":id" => $order_id]);

  $db->prepare("UPDATE expedicne_boxy SET order_id = NULL, obsadeny_at = NULL WHERE order_id = :order_id")->execute([":order_id" => $order_id]);

  controls_add_log($db, $order_id, $user_id, "expedicia", "expedition_completed_without_control", "success", [
    "finished" => true,
    "message" => "Ukončená bez kontroly. Bez vytvorenia štítku a bez odoslania dopravcovi."
  ]);

  $db->commit();
  complete_without_expedition_response(true, "Objednávka bola ukončená bez expedovania.");
} catch (Throwable $error) {
  if ($db->inTransaction()) {
    $db->rollBack();
  }

  complete_without_expedition_response(false, "Objednávku sa nepodarilo ukončiť.", 500);
}
