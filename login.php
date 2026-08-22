<?php
ini_set("display_errors", "0");
require_once __DIR__ . "/config/common.php";

if (auth_is_logged_in()) {
  header("Location: /");
  exit;
}

$login = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $login = trim((string) ($_POST["login"] ?? ""));
  $password = (string) ($_POST["password"] ?? "");
  $csrf_token = (string) ($_POST["csrf_token"] ?? "");

  if (!auth_csrf_is_valid($csrf_token)) {
    $error = "Platnosť formulára vypršala. Skús to znova.";
  } elseif ($login === "" || $password === "") {
    $error = "Zadaj prihlasovacie meno aj heslo.";
  } else {
    $admin = auth_admin_by_login($db, $login);
    if ($admin && auth_password_matches($admin, $password)) {
      auth_login($db, $admin);
      header("Location: /");
      exit;
    }
    usleep(350000);
    $error = "Nesprávne prihlasovacie meno alebo heslo.";
  }
}

$login = "lukaskojnok";
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
          <p>Prihláste sa pre pokračovanie do systému.</p>
        </header>
        <?php if ($error !== "") { ?>
          <div class="login-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
        <?php } ?>
        <form action="/login.php" method="post" class="login-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
          <label for="login">Prihlasovacie meno</label>
          <input type="text" name="login" id="login" value="<?= htmlspecialchars($login, ENT_QUOTES, "UTF-8") ?>" placeholder="" autocomplete="username" autocapitalize="none" required autofocus>
          <label for="password">Heslo</label>
          <input type="password" name="password" id="password" placeholder="" value="Heslo10" autocomplete="current-password" required>
          <button type="submit">Prihlásiť sa</button>
        </form>
      </section>
    </main>
  </body>
</html>
