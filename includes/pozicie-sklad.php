<section class="stock-positions-editor">
  <?php if ($pozicie_sklad_saved) { ?>
    <div class="stock-positions-message stock-positions-message_success">Pozície skladu boli uložené.</div>
  <?php } ?>

  <?php if ($pozicie_sklad_error !== "") { ?>
    <div class="stock-positions-message stock-positions-message_error"><?= htmlspecialchars($pozicie_sklad_error, ENT_QUOTES, "UTF-8") ?></div>
  <?php } ?>

  <form method="post" action="/?page=pozicie-sklad&amp;typ=<?= urlencode($typ_kontroly) ?>" class="stock-positions-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
    <textarea
      name="pozicie"
      class="stock-positions-textarea"
      aria-label="Pozície skladu, každá na samostatnom riadku"
      spellcheck="false"
      autofocus
    ><?= htmlspecialchars($pozicie_sklad_text, ENT_QUOTES, "UTF-8") ?></textarea>
    <button type="submit" class="button stock-positions-save">Uložiť pozície</button>
  </form>
</section>
