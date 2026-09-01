<?php
require_once __DIR__ . "/../config/common.php";
require_once __DIR__ . "/../config/controls_log.php";

header("Content-Type: application/json; charset=utf-8");

function order_claim_response($success, $message, $http_code = 200, $data = []) {
  http_response_code($http_code);
  echo json_encode(array_merge([
    "success" => $success,
    "message" => $message
  ], $data), JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !auth_is_logged_in()) {
  order_claim_response(false, "Neplatná požiadavka.", 401);
}

$order_id = (int) ($_POST["order_id"] ?? 0);
$typ_kontroly = (string) ($_POST["typ_kontroly"] ?? "");
$csrf_token = (string) ($_POST["csrf_token"] ?? "");
$force = (int) ($_POST["force"] ?? 0) === 1;

if ($order_id <= 0 || !in_array($typ_kontroly, ["vyskladnenie", "expedicia"], true)) {
  order_claim_response(false, "Neplatné údaje objednávky.", 422);
}

if (!auth_csrf_is_valid($csrf_token)) {
  order_claim_response(false, "Platnosť požiadavky vypršala. Obnovte stránku.", 419);
}

$admin_data = controls_get_admin_data($db);
$user_id = (int) ($admin_data["id"] ?? 0);
$user_name = trim((string) ($admin_data["name"] ?? $admin_data["login"] ?? ""));

if ($user_id <= 0) {
  order_claim_response(false, "Nepodarilo sa určiť prihláseného používateľa.", 401);
}

$status_column = $typ_kontroly === "vyskladnenie" ? "status_vyskladnenie" : "status_expedicia";
$user_column = $typ_kontroly === "vyskladnenie" ? "vyskladnenie_user_id" : "expedicia_user_id";

try {
  $db->beginTransaction();

  $query = $db->prepare("
    SELECT {$status_column} AS work_status, {$user_column} AS worker_id
    FROM orders
    WHERE id = :id
    LIMIT 1
    FOR UPDATE
  ");
  $query->execute([":id" => $order_id]);
  $order = $query->fetch(PDO::FETCH_ASSOC);

  if (!$order) {
    $db->rollBack();
    order_claim_response(false, "Objednávka nebola nájdená.", 404);
  }

  $work_status = (string) ($order["work_status"] ?? "nove");
  $worker_id = (int) ($order["worker_id"] ?? 0);

  if ($work_status === "ukoncene") {
    $db->commit();
    order_claim_response(true, "Objednávka je už ukončená.", 200, ["completed" => true]);
  }

  $is_other_worker = $worker_id > 0 && $worker_id !== $user_id;
  $previous_worker_name = "";

  if ($is_other_worker) {
    $query = $db->prepare("SELECT COALESCE(NULLIF(name, ''), login) FROM admins WHERE id = :id LIMIT 1");
    $query->execute([":id" => $worker_id]);
    $previous_worker_name = trim((string) ($query->fetchColumn() ?: "iný používateľ"));

    if (!$force) {
      $db->rollBack();
      order_claim_response(false, "Na objednávke už pracuje iný používateľ.", 409, [
        "conflict" => true,
        "worker_name" => $previous_worker_name
      ]);
    }
  }

  $query = $db->prepare("
    UPDATE orders
    SET {$status_column} = 'v_procese', {$user_column} = :user_id
    WHERE id = :id
  ");
  $query->execute([
    ":user_id" => $user_id,
    ":id" => $order_id
  ]);

  if ($is_other_worker) {
    controls_add_log($db, $order_id, $user_id, $typ_kontroly, "work_taken_over", "opened", [
      "message" => "Objednávku prevzal {$user_name} od používateľa {$previous_worker_name}."
    ]);
  } elseif ($worker_id <= 0 || $work_status === "nove") {
    controls_add_log($db, $order_id, $user_id, $typ_kontroly, "work_claimed", "opened", [
      "message" => "Na objednávke začal pracovať {$user_name}."
    ]);
  }

  $db->commit();
  order_claim_response(true, "Objednávka bola priradená používateľovi.", 200, [
    "worker_name" => $user_name,
    "taken_over" => $is_other_worker
  ]);
} catch (Throwable $error) {
  if ($db->inTransaction()) {
    $db->rollBack();
  }

  order_claim_response(false, "Objednávku sa nepodarilo priradiť používateľovi.", 500);
}
