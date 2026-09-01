<div class="table-box">
  <table class="data-table expedicne-boxy-table">
    <thead>
      <tr>
        <th>Expedičný box</th>
        <th>Stav</th>
        <th>Objednávka</th>
        <th>Faktúra</th>
        <th>Zákazník</th>
        <th>Obsadený od</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($expedicne_boxy as $expedicny_box) { ?>
        <?php $is_occupied = !empty($expedicny_box["order_id"]); ?>
        <tr data-box-row="<?= (int) $expedicny_box["id"] ?>">
          <td><strong><?= htmlspecialchars($expedicny_box["kod"], ENT_QUOTES, "UTF-8") ?></strong></td>
          <td><span class="status <?= $is_occupied ? "status-active" : "status-done" ?>"><?= $is_occupied ? "Obsadený" : "Voľný" ?></span></td>
          <td><?= $is_occupied ? htmlspecialchars($expedicny_box["cislo_objednavky"] ?: "—", ENT_QUOTES, "UTF-8") : "—" ?></td>
          <td><?= $is_occupied ? htmlspecialchars($expedicny_box["cislo_faktury"] ?: "—", ENT_QUOTES, "UTF-8") : "—" ?></td>
          <td><?= $is_occupied ? htmlspecialchars($expedicny_box["dodacie_meno"] ?: $expedicny_box["fakturacne_meno"] ?: "—", ENT_QUOTES, "UTF-8") : "—" ?></td>
          <td><?= $is_occupied && !empty($expedicny_box["obsadeny_at"]) ? htmlspecialchars(date("d. m. Y, H:i", strtotime($expedicny_box["obsadeny_at"])), ENT_QUOTES, "UTF-8") : "—" ?></td>
          <td class="data-table_action">
            <?php if ($is_occupied) { ?>
              <button type="button" class="button button-light expedicny-box-release" data-box-id="<?= (int) $expedicny_box["id"] ?>">Uvoľniť box</button>
            <?php } ?>
          </td>
        </tr>
      <?php } ?>
    </tbody>
  </table>
</div>
<input type="hidden" id="expedicny-box-release-csrf" value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
