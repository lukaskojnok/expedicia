<?php
function controls_get_admin_data($db) {
  global $ADMIN_DATA;

  if (!empty($ADMIN_DATA["id"])) {
    return $ADMIN_DATA;
  }

  $admin_id = (int) ($_SESSION["admin_id"] ?? 0);
  $login = trim((string) ($_SESSION["admin_login"] ?? ""));

  if ($admin_id <= 0 || $login === "") {
    return [];
  }

  $query = $db->prepare("SELECT id, login, email, name, permissions FROM admins WHERE id = :id AND login = :login AND active = 1 LIMIT 1");
  $query->execute([":id" => $admin_id, ":login" => $login]);
  $ADMIN_DATA = $query->fetch(PDO::FETCH_ASSOC) ?: [];

  return $ADMIN_DATA;
}

function controls_add_log($db, $order_id, $user_id, $typ_kontroly, $action, $status, $data = []) {
  if ($order_id <= 0 || $user_id <= 0) {
    return;
  }

  $query = $db->prepare("
    INSERT INTO controls_logs SET
      order_id = :order_id,
      user_id = :user_id,
      typ_kontroly = :typ_kontroly,
      action = :action,
      status = :status,
      ukoncene_at = CASE
        WHEN :is_finished = 1 THEN NOW()
        ELSE NULL
      END,
      carrier = :carrier,
      shipment_reference = :shipment_reference,
      api_http_code = :api_http_code,
      api_response = :api_response,
      message = :message
  ");

  $query->execute([
    ":order_id" => $order_id,
    ":user_id" => $user_id,
    ":typ_kontroly" => $typ_kontroly,
    ":action" => $action,
    ":status" => $status,
    ":is_finished" => !empty($data["finished"]) ? 1 : 0,
    ":carrier" => $data["carrier"] ?? null,
    ":shipment_reference" => $data["shipment_reference"] ?? null,
    ":api_http_code" => $data["api_http_code"] ?? null,
    ":api_response" => $data["api_response"] ?? null,
    ":message" => $data["message"] ?? null
  ]);
}
