<?php
include("config/common.php");

header("Content-Type: text/plain; charset=utf-8");



/*
 * =========================================================
 * TESTOVACIE NASTAVENIA
 * =========================================================
 */

$test = [
  /*
   * Vyber produkt podľa kódu z poľa $dpd_products.
   *
   * Najčastejšie:
   * 9  = DPD Home
   * 17 = DPD Pickup miesto
   */
  "product" => 9,

  /*
   * Unikátne číslo testovacej zásielky.
   */
  "reference" => "TEST-" . date("Ymd-His"),

  /*
   * EUR = Slovensko
   * CZK = Česko
   */
  "currency" => "EUR",

  /*
   * Poznámka zobrazená na štítku.
   * Maximálne 70 znakov.
   */
  "note" => "Testovacia zásielka",

  /*
   * Zvoz zásielky.
   */
  "pickup" => [
    "enabled" => true,
    "date" => date("Ymd"),
    "beginning" => "1400",
    "end" => "1700"
  ],

  /*
   * Príjemca.
   *
   * Typ adresy sa nastaví automaticky:
   *
   * product 17 = psd – DPD Pickup miesto
   * ostatné    = b2c – adresa zákazníka
   */
  "recipient" => [
    "name" => "Lukáš Kojnok",
    "street" => "Námestie slobody",
    "houseNumber" => "1",
    "zip" => "05001",
    "city" => "Revúca",
    "phone" => "+421900000000",
    "email" => "test@example.com"
  ],

  /*
   * ID DPD Pickup miesta.
   *
   * Povinné iba pri product = 17.
   */
  "parcelshop_id" => "",

  /*
   * Dobierka.
   */
  "cod" => [
    "enabled" => false,
    "amount" => 49.90,
    "variableSymbol" => "202600001"
  ],

  /*
   * Balíky v jednej zásielke.
   *
   * Každý záznam predstavuje jeden samostatný balík.
   */
  "parcels" => [
    [
      "reference2" => "BALIK-1",
      "weight" => 1,
      "height" => 35,
      "width" => 35,
      "depth" => 35
    ]

    /*
    ,
    [
      "reference2" => "BALIK-2",
      "weight" => 2,
      "height" => 35,
      "width" => 35,
      "depth" => 35
    ],
    [
      "reference2" => "BALIK-3",
      "weight" => 1.5,
      "height" => 35,
      "width" => 35,
      "depth" => 35
    ]
    */
  ]
];

/*
 * =========================================================
 * KONTROLA PRODUKTU
 * =========================================================
 */

$product = (int) $test["product"];

if (!isset($dpd_products[$product])) {
  echo "CHYBA: Neznámy DPD produkt " . $product . ".\n\n";
  echo "Povolené produkty:\n";

  foreach ($dpd_products as $product_id => $product_name) {
    echo $product_id . " = " . $product_name . "\n";
  }

  exit;
}

$product_name = $dpd_products[$product];

/*
 * =========================================================
 * ZÁKLADNÉ NASTAVENIA
 * =========================================================
 */

$reference = trim((string) $test["reference"]);
$currency = strtoupper(trim((string) $test["currency"]));

if ($reference === "") {
  echo "CHYBA: Reference zásielky nesmie byť prázdne.";
  exit;
}

/*
 * Krajina a web podľa meny.
 */

if ($currency === "CZK") {
  $country = 203;
  $web = "okfish.cz";
} else {
  $currency = "EUR";
  $country = 703;
  $web = "okfish.sk";
}

/*
 * =========================================================
 * TYP ADRESY PRÍJEMCU
 * =========================================================
 *
 * b2c = domáca adresa zákazníka
 * psd = adresa DPD Pickup miesta
 */

$is_parcelshop_delivery = $product === 17;

if ($is_parcelshop_delivery) {
  $address_recipient_type = "psd";
} else {
  $address_recipient_type = "b2c";
}

/*
 * =========================================================
 * BALÍKY
 * =========================================================
 */

$parcels = [];

foreach ($test["parcels"] as $index => $parcel) {
  $weight = (float) ($parcel["weight"] ?? 0);

  if ($weight <= 0) {
    continue;
  }

  $parcel_data = [
    "reference1" => $reference,
    "weight" => $weight,
    "height" => (int) ($parcel["height"] ?? 35),
    "width" => (int) ($parcel["width"] ?? 35),
    "depth" => (int) ($parcel["depth"] ?? 35)
  ];

  $reference2 = trim((string) ($parcel["reference2"] ?? ""));

  if ($reference2 !== "") {
    $parcel_data["reference2"] = $reference2;
  }

  $parcels[] = $parcel_data;
}

if (empty($parcels)) {
  echo "CHYBA: Musíš zadať aspoň jeden balík s hmotnosťou vyššou ako 0.";
  exit;
}

/*
 * =========================================================
 * DOPLNKOVÉ SLUŽBY
 * =========================================================
 */

$services = [];

/*
 * DPD Pickup miesto.
 */

if ($is_parcelshop_delivery) {
  $parcelshop_id = trim((string) $test["parcelshop_id"]);

  if ($parcelshop_id === "") {
    echo "CHYBA: Pri produkte 17 musí byť vyplnené parcelshop_id.";
    exit;
  }

  $services["parcelShopDelivery"] = [
    "parcelShopId" => $parcelshop_id
  ];
}

/*
 * Dobierka.
 */

if (!empty($test["cod"]["enabled"])) {
  $cod_amount = (float) $test["cod"]["amount"];
  $variable_symbol = trim(
    (string) $test["cod"]["variableSymbol"]
  );

  if ($cod_amount <= 0) {
    echo "CHYBA: Pri dobierke musí byť suma vyššia ako 0.";
    exit;
  }

  if ($variable_symbol === "") {
    echo "CHYBA: Pri dobierke musí byť vyplnený variabilný symbol.";
    exit;
  }

  /*
   * EUR:
   * účet v EUR, platba hotovosťou alebo kartou.
   *
   * CZK:
   * účet v CZK, iba hotovosť.
   */

  if ($currency === "CZK") {
    $bank_account_id = (int) DPD_BANK_ID_SLSP_CZK_1;
    $payment_method = 0;
  } else {
    $bank_account_id = (int) DPD_BANK_ID_SLSP_EUR_1;
    $payment_method = 1;
  }

  /*
   * Pri doručení do Pickup miesta používal
   * pôvodný skript iba platbu v hotovosti.
   */

  if ($is_parcelshop_delivery) {
    $payment_method = 0;
  }

  $services["cod"] = [
    "amount" => $cod_amount,
    "currency" => $currency,
    "bankAccount" => [
      "id" => $bank_account_id
    ],
    "variableSymbol" => $variable_symbol,
    "paymentMethod" => $payment_method
  ];
}

/*
 * =========================================================
 * ZÁSIELKA
 * =========================================================
 */

$shipment = [
  "reference" => $reference,
  "delisId" => DPD_DELIS_ID,
  "note" => trim((string) $test["note"]) !== ""
    ? trim((string) $test["note"])
    : $web,
  "product" => $product,
  "addressSender" => [
    "id" => (int) DPD_ZVOZOVA_ADRESA_1
  ],
  "addressRecipient" => [
    "type" => $address_recipient_type,
    "name" => trim((string) $test["recipient"]["name"]),
    "street" => trim((string) $test["recipient"]["street"]),
    "houseNumber" => trim(
      (string) $test["recipient"]["houseNumber"]
    ),
    "zip" => trim((string) $test["recipient"]["zip"]),
    "country" => $country,
    "city" => trim((string) $test["recipient"]["city"]),
    "phone" => trim((string) $test["recipient"]["phone"]),
    "email" => trim((string) $test["recipient"]["email"]),
    "note" => ""
  ],
  "parcels" => [
    "parcel" => $parcels
  ]
];

/*
 * Zvoz.
 */

if (!empty($test["pickup"]["enabled"])) {
  $pickup_date = trim((string) $test["pickup"]["date"]);
  $pickup_beginning = trim(
    (string) $test["pickup"]["beginning"]
  );
  $pickup_end = trim((string) $test["pickup"]["end"]);

  if (!preg_match('/^\d{8}$/', $pickup_date)) {
    echo "CHYBA: Dátum zvozu musí byť vo formáte YYYYMMDD.";
    exit;
  }

  if (!preg_match('/^\d{4}$/', $pickup_beginning)) {
    echo "CHYBA: Začiatok zvozu musí byť vo formáte HHMM.";
    exit;
  }

  if (!preg_match('/^\d{4}$/', $pickup_end)) {
    echo "CHYBA: Koniec zvozu musí byť vo formáte HHMM.";
    exit;
  }

  $shipment["pickup"] = [
    "date" => $pickup_date,
    "timeWindow" => [
      "beginning" => $pickup_beginning,
      "end" => $pickup_end
    ]
  ];
}

/*
 * Services pridáme len vtedy, ak obsahujú
 * dobierku alebo DPD Pickup miesto.
 */

if (!empty($services)) {
  $shipment["services"] = $services;
}

/*
 * =========================================================
 * FINÁLNA POŽIADAVKA
 * =========================================================
 */

$data = [
  "jsonrpc" => "2.0",
  "method" => "create",
  "params" => [
    "DPDSecurity" => [
      "SecurityToken" => [
        "ClientKey" => DPD_KEY,
        "Email" => DPD_EMAIL
      ]
    ],
    "shipment" => [
      $shipment
    ]
  ],
  "id" => $reference
];

$json = json_encode(
  $data,
  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ($json === false) {
  echo "CHYBA JSON:\n";
  echo json_last_error_msg();
  exit;
}

/*
 * =========================================================
 * ODOSLANIE DO DPD
 * =========================================================
 */

$curl = curl_init(DPD_URL);

curl_setopt_array($curl, [
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => $json,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CONNECTTIMEOUT => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTPHEADER => [
    "Content-Type: application/json",
    "Accept: application/json",
    "Content-Length: " . strlen($json)
  ]
]);

$response = curl_exec($curl);
$curl_error = curl_error($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

curl_close($curl);

/*
 * =========================================================
 * VÝSTUP
 * =========================================================
 */

echo "DPD produkt: " . $product . " – " . $product_name . "\n";
echo "Typ príjemcu: " . $address_recipient_type . "\n";
echo "Počet balíkov: " . count($parcels) . "\n";
echo "HTTP kód: " . $http_code . "\n\n";

if ($curl_error !== "") {
  echo "cURL chyba:\n";
  echo $curl_error;
  exit;
}

/*
 * Vo výpise zamaskujeme API kľúč.
 */

$safe_data = $data;
$safe_data["params"]["DPDSecurity"]["SecurityToken"]["ClientKey"] = "***";

echo "Odoslané dáta:\n";

echo json_encode(
  $safe_data,
  JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

echo "\n\nOdpoveď DPD:\n";

$decoded_response = json_decode($response, true);

if (is_array($decoded_response)) {
  echo json_encode(
    $decoded_response,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
  );
} else {
  echo $response;
}