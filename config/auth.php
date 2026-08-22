<?php
function auth_admin_by_id($db, $admin_id) {
  $query = $db->prepare("SELECT id, login, email, name, permissions FROM admins WHERE id = :id AND active = 1 LIMIT 1");
  $query->execute([":id" => (int) $admin_id]);
  return $query->fetch(PDO::FETCH_ASSOC) ?: [];
}
function auth_admin_by_login($db, $login) {
  $query = $db->prepare("SELECT * FROM admins WHERE login = :login AND active = 1 LIMIT 1");
  $query->execute([":login" => $login]);
  return $query->fetch(PDO::FETCH_ASSOC) ?: [];
}
function auth_is_logged_in() {
  return !empty($_SESSION["admin_id"]) && !empty($_SESSION["admin_login"]);
}
function auth_password_matches($admin, $password) {
  $stored_password = (string) ($admin["password"] ?? "");
  if ($stored_password === "" || $password === "") return false;
  if (password_get_info($stored_password)["algoName"] !== "unknown") return password_verify($password, $stored_password);
  if (!defined("HAS_ADMIN")) return false;
  return hash_equals($stored_password, hash("sha512", $admin["login"] . $password . HAS_ADMIN));
}
function auth_login($db, $admin) {
  session_regenerate_id(true);
  $_SESSION["admin_id"] = (int) $admin["id"];
  $_SESSION["admin_login"] = (string) $admin["login"];
  $_SESSION["admin_name"] = (string) ($admin["name"] ?: $admin["login"]);
  $session_id = session_id();
  $user_agent = substr((string) ($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 255);
  $ip = substr((string) ($_SERVER["REMOTE_ADDR"] ?? ""), 0, 100);
  $unique_code = hash("sha512", $admin["login"] . $session_id . $ip . $user_agent);
  $_SESSION["admin_unique_code"] = $unique_code;
  $db->prepare("UPDATE admins SET date_login_last = NOW() WHERE id = :id")->execute([":id" => (int) $admin["id"]]);
  $query = $db->prepare("INSERT INTO admins_logs SET login = :login, session_id = :session_id, user_agent = :user_agent, ip = :ip, unique_code = :unique_code, date_login = NOW(), date_last_do = NOW()");
  $query->execute([":login" => $admin["login"], ":session_id" => $session_id, ":user_agent" => $user_agent, ":ip" => $ip, ":unique_code" => $unique_code]);
}
function auth_logout($db) {
  $unique_code = (string) ($_SESSION["admin_unique_code"] ?? "");
  if ($unique_code !== "") $db->prepare("DELETE FROM admins_logs WHERE unique_code = :unique_code")->execute([":unique_code" => $unique_code]);
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
  }
  session_destroy();
}
function auth_csrf_token() {
  if (empty($_SESSION["csrf_token"])) $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
  return $_SESSION["csrf_token"];
}
function auth_csrf_is_valid($token) {
  return !empty($_SESSION["csrf_token"]) && is_string($token) && hash_equals($_SESSION["csrf_token"], $token);
}
