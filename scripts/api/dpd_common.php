<?php
/*
 * =========================================================
 * DPD PRODUKTY
 * =========================================================
 */
$dpd_products = [
  1 => "DPD Classic",
  2 => "DPD 18:00",
  3 => "DPD 10:00",
  4 => "DPD 12:00",
  5 => "DPD COD",
  6 => "Saturday Delivery",
  7 => "Small Parcel",
  8 => "DPD Guarantee",
  9 => "DPD Home",
  10 => "City Service Standard",
  17 => "DPD Pickup miesto"
];


function dpd_country_code($country) {
  $country = strtoupper(trim((string) $country));

  if (in_array($country, ["CZ", "CZECH REPUBLIC", "ČESKÁ REPUBLIKA"], true)) {
    return 203;
  }

  return 703;
}

function dpd_create_shipment($shipment_data, $parcelshop_id = "") {
  $order = $shipment_data["order"];
  $shipping = $shipment_data["shipping"];
  $weights = $shipment_data["weights"];
  $product = (int) ($shipping["product"] ?? 0);
  $is_parcelshop_delivery = $product === 17;

  if (!defined("DPD_URL") || !defined("DPD_KEY") || !defined("DPD_EMAIL") || !defined("DPD_DELIS_ID") || !defined("DPD_ZVOZOVA_ADRESA_1")) {
    return ["success" => false, "message" => "Chýbajú DPD prihlasovacie údaje v konfigurácii.", "http_code" => 500];
  }

  if ($is_parcelshop_delivery && $parcelshop_id === "") {
    return ["success" => false, "message" => "Pri DPD odbernom mieste chýba parcelShopId. Do importu treba uložiť presné DPD ID odberného miesta.", "http_code" => 422];
  }

  $reference = trim((string) ($order["cislo_objednavky"] ?: $order["id"]));
  $recipient_name = trim((string) ($order["dodacie_meno"] ?: $order["fakturacne_meno"]));
  $recipient_street = trim((string) ($order["dodacia_ulica"] ?: $order["fakturacna_ulica"]));
  $recipient_house_number = trim((string) ($order["dodacie_cislo_domu"] ?: $order["fakturacne_cislo_domu"]));
  $recipient_zip = trim((string) ($order["dodacie_psc"] ?: $order["fakturacne_psc"]));
  $recipient_city = trim((string) ($order["dodacie_mesto"] ?: $order["fakturacne_mesto"]));

  if ($recipient_name === "" || $recipient_zip === "" || $recipient_city === "") {
    return ["success" => false, "message" => "Objednávka nemá úplnú dodaciu adresu.", "http_code" => 422];
  }

  $parcels = [];

  foreach ($weights as $index => $weight) {
    $parcels[] = [
      "reference1" => $reference,
      "reference2" => "BALIK-" . ($index + 1),
      "weight" => $weight,
      "height" => 35,
      "width" => 35,
      "depth" => 35
    ];
  }

  $shipment = [
    "reference" => $reference,
    "delisId" => DPD_DELIS_ID,
    "note" => "Objednávka " . $reference,
    "product" => $product,
    "addressSender" => ["id" => (int) DPD_ZVOZOVA_ADRESA_1],
    "addressRecipient" => [
      "type" => $is_parcelshop_delivery ? "psd" : "b2c",
      "name" => $recipient_name,
      "street" => $recipient_street,
      "houseNumber" => $recipient_house_number,
      "zip" => $recipient_zip,
      "country" => dpd_country_code($order["dodacia_krajina"] ?: $order["fakturacna_krajina"]),
      "city" => $recipient_city,
      "phone" => trim((string) $order["telefon"]),
      "email" => trim((string) $order["email"]),
      "note" => ""
    ],
    "parcels" => ["parcel" => $parcels]
  ];

  if ($is_parcelshop_delivery) {
    $shipment["services"] = [
      "parcelShopDelivery" => ["parcelShopId" => $parcelshop_id]
    ];
  }

  $request_data = [
    "jsonrpc" => "2.0",
    "method" => "create",
    "params" => [
      "DPDSecurity" => ["SecurityToken" => ["ClientKey" => DPD_KEY, "Email" => DPD_EMAIL]],
      "shipment" => [$shipment]
    ],
    "id" => $reference
  ];
  $json = json_encode($request_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $curl = curl_init(DPD_URL);

  curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $json,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Accept: application/json"]
  ]);

  $response = curl_exec($curl);
  $curl_error = curl_error($curl);
  $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);

  if ($curl_error !== "") {
    return ["success" => false, "message" => "DPD spojenie zlyhalo: " . $curl_error, "http_code" => 502];
  }

  $response_data = json_decode($response, true);

  if ($http_code < 200 || $http_code >= 300 || !empty($response_data["error"])) {
    $error_message = $response_data["error"]["message"] ?? "DPD odmietlo vytvorenie zásielky.";
    return ["success" => false, "message" => $error_message, "http_code" => 422];
  }

  return ["success" => true, "message" => "Zásielka bola úspešne odoslaná do DPD.", "http_code" => 200];
}
