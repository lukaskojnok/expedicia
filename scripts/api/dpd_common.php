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

function dpd_normalized_response($success, $message, $http_code, $response_code, $response, $reference, $labels = []) {
  return [
    "success" => $success,
    "message" => $message,
    "http_code" => $http_code,
    "response_code" => $response_code,
    "response" => $response,
    "reference" => $reference,
    "labels" => $labels
  ];
}

function dpd_extract_labels($response_data) {
  $label_keys = [
    "label",
    "labels",
    "labelfile",
    "labeldata",
    "pdfbase64",
    "parcellabelspdf"
  ];
  $labels = [];

  $walk = function($value, $key = "", $label_context = false) use (&$walk, &$labels, $label_keys) {
    $normalized_key = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $key));
    $is_label_key = in_array($normalized_key, $label_keys, true);
    $is_label_context = $label_context || $is_label_key;

    if (is_string($value) && $is_label_context && trim($value) !== "") {
      $labels[] = [
        "content" => $value,
        "mime_type" => "application/pdf",
        "extension" => "pdf"
      ];
      return;
    }

    if (!is_array($value)) {
      return;
    }

    if ($is_label_context) {
      $content = $value["content"] ?? $value["data"] ?? $value["base64"] ?? $value["file"] ?? null;

      if (is_string($content) && trim($content) !== "") {
        $labels[] = [
          "content" => $content,
          "mime_type" => $value["mime_type"] ?? $value["mimeType"] ?? "application/pdf",
          "extension" => $value["extension"] ?? "pdf"
        ];
        return;
      }
    }

    foreach ($value as $child_key => $child_value) {
      $child_is_numeric = is_int($child_key) || ctype_digit((string) $child_key);
      $walk($child_value, $child_key, $is_label_context && $child_is_numeric);
    }
  };

  $walk($response_data);
  $unique_labels = [];
  $seen = [];

  foreach ($labels as $label) {
    $hash = hash("sha256", (string) $label["content"]);

    if (isset($seen[$hash])) {
      continue;
    }

    $seen[$hash] = true;
    $unique_labels[] = $label;
  }

  return $unique_labels;
}

function dpd_create_shipment($shipment_data, $parcelshop_id = "") {
  $order = $shipment_data["order"];
  $shipping = $shipment_data["shipping"];
  $weights = $shipment_data["weights"];
  $product = (int) ($shipping["product"] ?? 0);
  $is_parcelshop_delivery = $product === 17;
  $reference = trim((string) ($order["cislo_objednavky"] ?: $order["id"]));

  if (!defined("DPD_URL") || !defined("DPD_KEY") || !defined("DPD_EMAIL") || !defined("DPD_DELIS_ID") || !defined("DPD_ZVOZOVA_ADRESA_1")) {
    return dpd_normalized_response(false, "Chýbajú DPD prihlasovacie údaje v konfigurácii.", 0, 500, "", $reference);
  }

  if ($is_parcelshop_delivery && $parcelshop_id === "") {
    return dpd_normalized_response(false, "Pri DPD odbernom mieste chýba parcelShopId.", 0, 422, "", $reference);
  }

  $recipient_name = trim((string) ($order["dodacie_meno"] ?: $order["fakturacne_meno"]));
  $recipient_street = trim((string) ($order["dodacia_ulica"] ?: $order["fakturacna_ulica"]));
  $recipient_house_number = trim((string) ($order["dodacie_cislo_domu"] ?: $order["fakturacne_cislo_domu"]));
  $recipient_zip = trim((string) ($order["dodacie_psc"] ?: $order["fakturacne_psc"]));
  $recipient_city = trim((string) ($order["dodacie_mesto"] ?: $order["fakturacne_mesto"]));

  if ($recipient_name === "" || $recipient_zip === "" || $recipient_city === "") {
    return dpd_normalized_response(false, "Objednávka nemá úplnú dodaciu adresu.", 0, 422, "", $reference);
  }

  $parcels = [];

  foreach ($weights as $index => $weight) {
    if ($weight > 31.5) {
      $parcel_number = $index + 1;

      return dpd_normalized_response(
        false,
        "Balík {$parcel_number} presahuje maximálnu povolenú hmotnosť DPD 31,5 kg.",
        0,
        422,
        "",
        $reference
      );
    }

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
    return dpd_normalized_response(false, "DPD spojenie zlyhalo: " . $curl_error, 0, 502, "", $reference);
  }

  $response_data = json_decode($response, true);

  if (!is_array($response_data)) {
    return dpd_normalized_response(false, "DPD vrátilo neplatnú odpoveď.", $http_code, 502, (string) $response, $reference);
  }

  if ($http_code < 200 || $http_code >= 300 || !empty($response_data["error"])) {
    $error_message = $response_data["error"]["message"] ?? "DPD odmietlo vytvorenie zásielky.";
    return dpd_normalized_response(false, $error_message, $http_code, 422, (string) $response, $reference);
  }

  $shipment_result = $response_data["result"]["result"][0] ?? null;

  if (!is_array($shipment_result) || !array_key_exists("success", $shipment_result)) {
    return dpd_normalized_response(false, "DPD nevrátilo výsledok vytvorenia zásielky.", $http_code, 502, (string) $response, $reference);
  }

  $reference = trim((string) ($shipment_result["reference"] ?? $reference));
  $dpd_success = $shipment_result["success"] === true;

  if (!$dpd_success) {
    $messages = $shipment_result["messages"] ?? [];

    if (!is_array($messages)) {
      $messages = [(string) $messages];
    }

    $messages = array_values(array_filter(array_map(function($message) {
      return trim((string) $message);
    }, $messages)));
    $error_message = !empty($messages)
      ? implode(" ", $messages)
      : "DPD odmietlo vytvorenie zásielky.";

    return dpd_normalized_response(false, $error_message, $http_code, 422, (string) $response, $reference);
  }

  $labels = dpd_extract_labels($response_data);

  return dpd_normalized_response(true, "Zásielka bola úspešne odoslaná do DPD.", $http_code, 200, (string) $response, $reference, $labels);
}
