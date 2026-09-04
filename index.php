<?php
include_once("config/psw.php");

$allowed_ips = [
  "45.152.96.6dd",
];

if ( isset($_GET["a"])) {
  // https://expokfish.kojnok.sk/?a
  $allowed_ips = [
  ];
}

$current_ip = $_SERVER["REMOTE_ADDR"] ?? "";
$saved_token = $_COOKIE["company_access_token_expedicia"] ?? "";

function find_allowed_company(string $token, array $allowed_tokens): ?string {
  if ($token === "") {
    return null;
  }

  foreach ($allowed_tokens as $company => $allowed_token) {
    if (hash_equals($allowed_token, $token)) {
      return $company;
    }
  }

  return null;
}

/*
 * Kontrola IP adresy alebo už uloženého tokenu.
 */
$ip_is_allowed = in_array($current_ip, $allowed_ips, true);
$allowed_company = find_allowed_company($saved_token, $allowed_tokens);

if ($ip_is_allowed || $allowed_company !== null) {
  require_once __DIR__ . "/indexMAIN.php";
  exit;
}

/*
 * Spracovanie tokenu načítaného z QR kódu.
 */
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $submitted_token = trim((string) ($_POST["access_token"] ?? ""));
  $allowed_company = find_allowed_company($submitted_token, $allowed_tokens);

  if ($allowed_company !== null) {
    setcookie("company_access_token_expedicia", $submitted_token, [
      "expires" => time() + (30 * 24 * 60 * 60),
      "path" => "/",
      "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
      "httponly" => true,
      "samesite" => "Strict",
    ]);

    /*
     * Presmerovanie odstráni odoslaný token z POST požiadavky.
     */

    header("Location: /");
    exit;
  }

  $error_message = "Načítaný QR kód nie je platný.";
}

http_response_code(403);
?>
<!DOCTYPE html>
<html lang="sk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Prístup zamietnutý</title>

  <style>
    * { box-sizing: border-box; }

    body { display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; font-family: Arial, sans-serif; color: #263040; background: #eef1f5; }

    .access-denied { position: relative; width: 100%; max-width: 480px; padding: 38px 34px; text-align: left; background: #fff; border: 1px solid #d6dce4; border-top: 5px solid #d53131; box-shadow: 0 4px 14px rgba(23, 32, 51, .09); }

    .access-denied_icon { display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; margin: 0 0 20px; font-size: 26px; font-weight: bold; color: #fff; background: #d53131; }

    .access-denied h1 { margin: 0 0 10px; font-size: 25px; line-height: 1.2; color: #202938; }

    .access-denied p { margin: 0; font-size: 15px; color: #687386; line-height: 1.5; }

    .access-denied_ip { display: block; margin-top: 18px; padding: 11px 13px; font-family: monospace; font-size: 14px; color: #394456; background: #f3f5f8; border: 1px solid #dce1e8; border-left: 4px solid #8791a1; }

    .access-form { margin-top: 26px; padding-top: 25px; border-top: 1px solid #dce1e8; }

    .access-form label { display: block; margin-bottom: 9px; font-size: 14px; font-weight: bold; color: #263040; }

    .access-form input { width: 100%; height: 50px; padding: 0 14px; font-size: 17px; color: #263040; background: #fff; border: 2px solid #bfc7d2; border-radius: 0; outline: none; }

    .access-form input:focus { border-color: #2878d0; box-shadow: 0 0 0 2px rgba(40, 120, 208, .12); }

    .access-form button { width: 100%; height: 50px; margin-top: 12px; padding: 0 18px; font-size: 15px; font-weight: bold; color: #fff; background: #2878d0; border: 0; border-radius: 0; cursor: pointer; transition: background .15s ease; }

    .access-form button:hover { background: #1f66b4; }

    .access-form button:active { background: #194f8b; }

    .access-form_note { margin-top: 12px !important; font-size: 13px !important; color: #8791a1 !important; }

    .access-error { margin-top: 15px; padding: 12px 14px; font-size: 14px; font-weight: bold; color: #a71919; background: #fff0f0; border: 1px solid #e3aaaa; border-left: 4px solid #d53131; }

    @media (max-width: 600px) {
      body { align-items: flex-start; padding: 12px; }

      .access-denied { margin-top: 25px; padding: 28px 22px; }

      .access-denied h1 { font-size: 22px; }
    }
  </style>
</head>

<body>
  <div class="access-denied">
    <div class="access-denied_icon">!</div>

    <h1>Nemáte prístup</h1>

    <p>
      Z tejto IP adresy nie je automaticky povolený prístup do systému.
    </p>

    <div class="access-denied_ip">
      Vaša IP: <?= htmlspecialchars($current_ip, ENT_QUOTES, "UTF-8") ?>
    </div>

    <form class="access-form" method="post" autocomplete="off">
      <label for="access-token">
        Načítajte prístupový QR kód
      </label>

      <input
        type="password"
        id="access-token"
        name="access_token"
        placeholder="Načítajte QR kód"
        autocomplete="off"
        autocapitalize="off"
        spellcheck="false"
        required
        autofocus
      >

      <button type="submit">
        Povoliť prístup
      </button>

      <p class="access-form_note">
        Po úspešnom načítaní zostane zariadenie povolené jeden rok.
      </p>

      <?php if ($error_message !== ""): ?>
        <div class="access-error">
          <?= htmlspecialchars($error_message, ENT_QUOTES, "UTF-8") ?>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <script>
    const accessTokenInput = document.getElementById("access-token");

    accessTokenInput.focus();

    document.addEventListener("click", function () {
      accessTokenInput.focus();
    });
  </script>
</body>
</html>