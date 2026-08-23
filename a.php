<?php
// https://diligent-pink-lynx.87-236-196-161.cpanel.site/a.php

declare(strict_types=1);

ob_start();

ini_set("display_errors", "0");
error_reporting(E_ALL);

// Odstráni prípadný nechcený výstup z načítaných súborov
ob_clean();

header("Content-Type: application/xml; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo "<SHOP>\n";

  echo "  <SHOPITEM>\n";
  echo "    <CODE>50</CODE>\n";
  echo "    <STOCK>\n";
  echo "      <WAREHOUSES>\n";
  echo "        <WAREHOUSE>\n";
  echo "          <NAME>Predvolený sklad</NAME>\n";
  echo "          <LOCATION></LOCATION>\n";
  echo "        </WAREHOUSE>\n";
  echo "      </WAREHOUSES>\n";
  echo "    </STOCK>\n";

  echo "  </SHOPITEM>\n";

echo "</SHOP>\n";