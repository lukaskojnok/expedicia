<?php
ini_set("display_errors", "0");
require_once __DIR__ . "/config/common.php";

if (auth_is_logged_in()) {
  $logged_admin = auth_admin_by_id($db, $_SESSION["admin_id"]);
  header("Location: " . auth_primary_url($logged_admin));
  exit;
}

/*
 * false = prihlásenie heslom je vypnuté
 * true  = prihlásenie heslom je zapnuté
 */
$password_login_enabled = false;

$login = "";
$error = "";

$admins_stmt = $db->query("
  SELECT *
  FROM admins
  ORDER BY login ASC
");

$admins = $admins_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $login_type = (string) ($_POST["login_type"] ?? "");
  $login = trim((string) ($_POST["login"] ?? ""));
  $password = (string) ($_POST["password"] ?? "");
  $csrf_token = (string) ($_POST["csrf_token"] ?? "");

  if (!auth_csrf_is_valid($csrf_token)) {
    $error = "Platnosť formulára vypršala. Skús to znova.";
  } elseif ($login_type === "quick") {
    if ($login === "") {
      $error = "Vyber používateľa.";
    } else {
      $admin = auth_admin_by_login($db, $login);

      if ($admin) {
        auth_login($db, $admin);

        header("Location: " . auth_primary_url($admin));
        exit;
      }

      $error = "Vybraný používateľ neexistuje.";
    }
  } elseif ($login_type === "password") {
    if (!$password_login_enabled) {
      $error = "Prihlásenie pomocou hesla je momentálne vypnuté.";
    } elseif ($login === "" || $password === "") {
      $error = "Zadaj prihlasovacie meno aj heslo.";
    } else {
      $admin = auth_admin_by_login($db, $login);

      if ($admin && auth_password_matches($admin, $password)) {
        auth_login($db, $admin);

        header("Location: " . auth_primary_url($admin));
        exit;
      }

      usleep(350000);
      $error = "Nesprávne prihlasovacie meno alebo heslo.";
    }
  } else {
    $error = "Neplatný spôsob prihlásenia.";
  }
}
?>
<!DOCTYPE html>
<html lang="sk">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="/css/css.css?v<?= filemtime(__DIR__ . "/css/css.css") ?>" rel="stylesheet">

    <title>Prihlásenie | Expedícia</title>
  </head>

  <body class="login-page">
    <main class="login-main">
      <section class="login-box">
        <header class="login-header">
          <span>Expedícia</span>
          <h1>Prihlásenie</h1>
          <p>Vyberte používateľa a pokračujte do systému.</p>
        </header>

        <?php if ($error !== "") { ?>
          <div class="login-error" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
          </div>
        <?php } ?>

        <form action="/login.php" method="post" class="login-form">
          <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>"
          >

          <input type="hidden" name="login_type" value="quick">

          <label for="quick-login">Rýchle prihlásenie</label>

          <select name="login" id="quick-login" required autofocus>
            <option value="">Vyberte používateľa</option>

            <?php foreach ($admins as $admin) { ?>
              <?php
              $admin_login = (string) ($admin["login"] ?? "");

              $admin_name = trim((string) (
                $admin["meno"]
                ?? $admin["name"]
                ?? $admin["username"]
                ?? ""
              ));

              if ($admin_name === "") {
                $admin_name = $admin_login;
              }
              ?>

              <option
                value="<?= htmlspecialchars($admin_login, ENT_QUOTES, "UTF-8") ?>"
                <?= $login === $admin_login ? "selected" : "" ?>
              >
                <?= htmlspecialchars($admin_name, ENT_QUOTES, "UTF-8") ?>
              </option>
            <?php } ?>
          </select>

          <button type="submit">Rýchlo prihlásiť</button>
        </form>

        <div class="login-divider">
          <span>alebo</span>
        </div>

        <form action="/login.php" method="post" class="login-form login-form-password <?= !$password_login_enabled ? "is-disabled" : "" ?>">
          <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>"
          >

          <input type="hidden" name="login_type" value="password">

          <div class="login-form-title">
            Prihlásenie heslom

            <?php if (!$password_login_enabled) { ?>
              <span>Momentálne vypnuté</span>
            <?php } ?>
          </div>

          <label for="password-login">Prihlasovacie meno</label>

          <input
            type="text"
            name="login"
            id="password-login"
            autocomplete="username"
            autocapitalize="none"
            <?= !$password_login_enabled ? "disabled" : "required" ?>
          >

          <label for="password">Heslo</label>

          <input
            type="password"
            name="password"
            id="password"
            autocomplete="current-password"
            <?= !$password_login_enabled ? "disabled" : "required" ?>
          >

          <button type="submit" <?= !$password_login_enabled ? "disabled" : "" ?>>
            Prihlásiť sa heslom
          </button>
        </form>
      </section>
    </main>
  </body>
</html>
