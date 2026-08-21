<?php
require_once __DIR__ . "/../config/common.php";
require_once __DIR__ . "/../config/controls_log.php";

header("Content-Type: application/json; charset=utf-8");

function control_log_response($success, $message, $http_code = 200) {
  http_response_code($http_code);
  echo json_encode(["success" => $success, "message" => $message], JSON_UNESCAPED_UNICODE);
  exit;
}

$order_id = (int) ($_POST["order_id"] ?? 0);
$typ_kontroly = $_POST["typ_kontroly"] ?? "";

if (!in_array($typ_kontroly, ["vyskladnenie", "expedicia"], true) || $order_id <= 0) {
  control_log_response(false, "Neplatné údaje kontroly.", 422);
}

$admin_data = controls_get_admin_data($db);
$user_id = (int) ($admin_data["id"] ?? 0);

if ($user_id <= 0) {
  control_log_response(false, "Nepodarilo sa určiť prihláseného administrátora.", 401);
}

$status_column = $typ_kontroly === "vyskladnenie" ? "status_vyskladnenie" : "status_expedicia";
$user_column = $typ_kontroly === "vyskladnenie" ? "vyskladnenie_user_id" : "expedicia_user_id";
$new_status = $typ_kontroly === "vyskladnenie" ? "ukoncene" : "v_procese";

$query = $db->prepare("
  UPDATE orders SET
    {$status_column} = :status,
    {$user_column} = :user_id,
    zmena = CASE
      WHEN :finished_status = 'ukoncene' THEN 0
      ELSE zmena
    END
  WHERE id = :id
");
$query->execute([
  ":status" => $new_status,
  ":user_id" => $user_id,
  ":finished_status" => $new_status,
  ":id" => $order_id
]);

controls_add_log($db, $order_id, $user_id, $typ_kontroly, "control_completed", "success", [
  "finished" => true,
  "message" => "Kontrola položiek úspešne dokončená."
]);

control_log_response(true, "Kontrola bola zapísaná.");