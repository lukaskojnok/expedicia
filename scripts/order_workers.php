<?php
require_once __DIR__ . "/../config/common.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function order_workers_response($success, $message, $http_code = 200, $data = []) {
  http_response_code($http_code);
  echo json_encode(array_merge([
    "success" => $success,
    "message" => $message
  ], $data), JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET" || !auth_is_logged_in()) {
  order_workers_response(false, "Neplatná požiadavka.", 401);
}

$typ_kontroly = (string) ($_GET["typ_kontroly"] ?? "");

if (!in_array($typ_kontroly, ["vyskladnenie", "expedicia"], true)) {
  order_workers_response(false, "Neplatný typ kontroly.", 422);
}

$status_column = $typ_kontroly === "vyskladnenie" ? "status_vyskladnenie" : "status_expedicia";
$user_column = $typ_kontroly === "vyskladnenie" ? "vyskladnenie_user_id" : "expedicia_user_id";

$query = $db->query("
  SELECT
    orders.id,
    orders.{$status_column} AS work_status,
    COALESCE(NULLIF(admins.name, ''), admins.login) AS worker_name
  FROM orders
  INNER JOIN admins ON admins.id = orders.{$user_column}
  WHERE orders.{$user_column} IS NOT NULL
    AND orders.{$user_column} > 0
    AND orders.{$status_column} IN ('v_procese', 'ukoncene')
");

order_workers_response(true, "Pracovníci boli načítaní.", 200, [
  "workers" => $query->fetchAll(PDO::FETCH_ASSOC)
]);
