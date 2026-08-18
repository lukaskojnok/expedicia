<header class="topbar">
  <div class="topbar_inner">

    <div class="topbar_left">
      <a href="/" class="topbar_logo">Expedícia</a>

      <div class="topbar_page">
        <h1><?= htmlspecialchars($page_title, ENT_QUOTES, "UTF-8") ?></h1>

        <?php if ($topbar_count_value !== "") { ?>
          <span class="topbar_count">
            <strong><?= htmlspecialchars((string) $topbar_count_value, ENT_QUOTES, "UTF-8") ?></strong>
            <?= htmlspecialchars($topbar_count_label, ENT_QUOTES, "UTF-8") ?>
          </span>
        <?php } ?>
      </div>
    </div>

    <nav class="topbar_menu">
      <?php if (!empty($topbar_back_url)) { ?>
        <a href="<?= htmlspecialchars($topbar_back_url, ENT_QUOTES, "UTF-8") ?>" class="topbar_logout">Späť na zoznam</a>
      <?php } ?>

      <span>Prihlásený: <strong><?= htmlspecialchars($prihlaseny_meno, ENT_QUOTES, "UTF-8") ?></strong></span>
      <a href="/logout.php" class="topbar_logout">Odhlásiť sa</a>
    </nav>

  </div>
</header>

<main class="content-main">

  <?php
  if ($page === "invoice") {
    require __DIR__ . "/includes/invoice.php";
  } else {
    require __DIR__ . "/includes/invoices.php";
  }
  ?>

</main>