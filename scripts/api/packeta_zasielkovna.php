<?php

function packeta_normalized_response($success, $message, $http_code, $response_code, $response, $reference, $labels = []) {
  return ["success" => $success, "message" => $message, "http_code" => $http_code, "response_code" => $response_code, "response" => $response, "reference" => $reference, "labels" => $labels];
}

function packeta_api_password() {
  if (defined("PACKETA_API_PASSWORD")) {
    return trim((string) PACKETA_API_PASSWORD);
  }
  return defined("PACKETA_KEY") ? trim((string) PACKETA_KEY) : "";
}

function packeta_xml_value($value) {
  return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, "UTF-8");
}

function packeta_post_xml($xml) {
  $url = defined("PACKETA_URL") ? PACKETA_URL : "https://www.zasilkovna.cz/api/rest";
  $curl = curl_init($url);
  curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $xml,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ["Content-Type: application/xml; charset=UTF-8", "Accept: */*"]
  ]);
  $response = curl_exec($curl);
  $result = [
    "response" => $response === false ? "" : (string) $response,
    "curl_error" => curl_error($curl),
    "http_code" => (int) curl_getinfo($curl, CURLINFO_HTTP_CODE),
    "content_type" => (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE)
  ];
  curl_close($curl);
  return $result;
}

function packeta_parse_xml($xml) {
  $previous = libxml_use_internal_errors(true);
  $object = simplexml_load_string((string) $xml, "SimpleXMLElement", LIBXML_NOCDATA);
  libxml_clear_errors();
  libxml_use_internal_errors($previous);
  if ($object === false) {
    return null;
  }
  $data = json_decode(json_encode($object, JSON_UNESCAPED_UNICODE), true);
  return is_array($data) ? $data : [];
}

function packeta_error_messages($response_data) {
  $messages = [];
  $walk = function($value, $key = "") use (&$walk, &$messages) {
    if (is_array($value)) {
      foreach ($value as $child_key => $child_value) {
        $walk($child_value, (string) $child_key);
      }
      return;
    }
    if (in_array(strtolower($key), ["fault", "string", "message"], true)) {
      $message = trim((string) $value);
      if ($message !== "") {
        $messages[] = $message;
      }
    }
  };
  $walk($response_data);
  return array_values(array_unique($messages));
}

function packeta_first_value($data, $keys) {
  foreach ($keys as $key) {
    if (isset($data[$key]) && trim((string) $data[$key]) !== "") {
      return trim((string) $data[$key]);
    }
  }
  return "";
}

function packeta_pickup_point_id($order, $shipping) {
  $id = packeta_first_value($order, [
    "packeta_id", "packeta_pickup_point_id", "zasielkovna_id", "zasielkovna_pobocka_id",
    "odberne_miesto_id", "odberne_miesto_kod", "doprava_odberne_miesto_id", "parcelshop_id",
    "foxdeli_pickup_point_id", "foxdeli_pick_up_place_id", "foxdeli_pick_up_place"
  ]);
  return $id !== "" ? $id : packeta_first_value($shipping, ["pickup_point_id"]);
}

function packeta_is_cod($order) {
  foreach (["dobierka", "is_cod", "cod"] as $key) {
    if (array_key_exists($key, $order)) {
      return filter_var($order[$key], FILTER_VALIDATE_BOOLEAN) || (float) $order[$key] > 0;
    }
  }
  $payment = strtolower(packeta_first_value($order, ["platba_kod", "platba_nazov", "payment_code", "payment_name"]));
  return strpos($payment, "dobier") !== false || strpos($payment, "cash on delivery") !== false || $payment === "cod";
}

function packeta_create_packet($api_password, $attributes) {
  $xml = '<?xml version="1.0" encoding="UTF-8"?>';
  $xml .= "<createPacket><apiPassword>" . packeta_xml_value($api_password) . "</apiPassword><packetAttributes>";
  foreach ($attributes as $name => $value) {
    if ($value !== null && $value !== "") {
      $xml .= "<" . $name . ">" . packeta_xml_value($value) . "</" . $name . ">";
    }
  }
  $xml .= "</packetAttributes></createPacket>";
  return packeta_post_xml($xml);
}

function packeta_download_labels($api_password, $packet_ids) {
  if (!class_exists("SoapClient")) {
    return [
      "labels" => [],
      "error" => "Na serveri chýba PHP SOAP rozšírenie.",
      "details" => []
    ];
  }

  $wsdl = defined("PACKETA_SOAP_WSDL")
    ? PACKETA_SOAP_WSDL
    : "https://www.zasilkovna.cz/api/soap-php-bugfix.wsdl";
  $labels = [];
  $details = [];

  try {
    $client = new SoapClient($wsdl, [
      "connection_timeout" => 15,
      "exceptions" => true,
      "cache_wsdl" => WSDL_CACHE_BOTH
    ]);

    foreach ($packet_ids as $packet_id) {
      try {
        $pdf = $client->packetLabelPdf($api_password, $packet_id, "A8 on A8", 0);
        $pdf = is_string($pdf) ? $pdf : "";
        $pdf_position = strpos($pdf, "%PDF-");

        if ($pdf_position !== false && $pdf_position <= 32) {
          $pdf = substr($pdf, $pdf_position);
        }

        if (strncmp($pdf, "%PDF-", 5) !== 0) {
          $details[] = [
            "packet_id" => (string) $packet_id,
            "status" => "error",
            "message" => "Packeta SOAP nevrátil platné PDF dáta."
          ];
          continue;
        }

        $labels[] = [
          "content" => base64_encode($pdf),
          "mime_type" => "application/pdf",
          "extension" => "pdf"
        ];
        $details[] = [
          "packet_id" => (string) $packet_id,
          "status" => "ok",
          "bytes" => strlen($pdf)
        ];
      } catch (SoapFault $exception) {
        $details[] = [
          "packet_id" => (string) $packet_id,
          "status" => "error",
          "message" => trim((string) $exception->getMessage())
        ];
      }
    }
  } catch (Throwable $exception) {
    return [
      "labels" => [],
      "error" => "Packeta SOAP spojenie zlyhalo: " . trim((string) $exception->getMessage()),
      "details" => []
    ];
  }

  $errors = [];

  foreach ($details as $detail) {
    if (($detail["status"] ?? "") === "error") {
      $errors[] = "Zásielka " . $detail["packet_id"] . ": " . $detail["message"];
    }
  }

  return [
    "labels" => $labels,
    "error" => !empty($errors) ? implode(" ", $errors) : "",
    "details" => $details
  ];
}

function packeta_send_shipment($shipment_data) {
  $order = $shipment_data["order"];
  $shipping = $shipment_data["shipping"];
  $weights = $shipment_data["weights"];
  $api_password = packeta_api_password();
  $reference = trim((string) ($order["cislo_objednavky"] ?: $order["id"]));
  if ($api_password === "") {
    return packeta_normalized_response(false, "Chýba Packeta API heslo v konfigurácii.", 0, 500, "", $reference);
  }
  if (!function_exists("curl_init") || !function_exists("simplexml_load_string")) {
    return packeta_normalized_response(false, "Na serveri chýba PHP rozšírenie cURL alebo SimpleXML.", 0, 500, "", $reference);
  }
  $currency = strtoupper(trim((string) ($order["mena"] ?? "EUR")));
  $country = strtoupper(packeta_first_value($order, ["dodacia_krajina", "fakturacna_krajina"]));
  $home_address_id = $currency === "CZK" || in_array($country, ["CZ", "CZECH REPUBLIC", "ČESKÁ REPUBLIKA"], true) ? "106" : "131";
  $pickup_point_id = packeta_pickup_point_id($order, $shipping);
  $is_pickup_point = !empty($shipping["pickup_point"]) || !empty($shipping["is_pickup_point"]) || $pickup_point_id !== "";
  $address_id = $is_pickup_point ? $pickup_point_id : packeta_first_value($shipping, ["address_id", "addressId"]);
  if ($address_id === "") {
    $address_id = $home_address_id;
  }
  if ($is_pickup_point && $pickup_point_id === "") {
    return packeta_normalized_response(false, "Pri Packeta odbernom mieste chýba ID výdajného miesta.", 0, 422, "", $reference);
  }
  $recipient_name = packeta_first_value($order, ["dodacie_meno", "fakturacne_meno"]);
  $street = packeta_first_value($order, ["dodacia_ulica", "fakturacna_ulica"]);
  $house_number = packeta_first_value($order, ["dodacie_cislo_domu", "fakturacne_cislo_domu"]);
  $city = packeta_first_value($order, ["dodacie_mesto", "fakturacne_mesto"]);
  $zip = preg_replace('/\s+/', '', packeta_first_value($order, ["dodacie_psc", "fakturacne_psc"]));
  $company = packeta_first_value($order, ["dodacia_firma", "dodacie_firma", "fakturacna_firma", "fakturacne_firma"]);
  $phone = trim((string) ($order["telefon"] ?? ""));
  $email = trim((string) ($order["email"] ?? ""));
  if ($recipient_name === "" || $phone === "" || $email === "") {
    return packeta_normalized_response(false, "Objednávka nemá meno, telefón alebo e-mail príjemcu.", 0, 422, "", $reference);
  }
  if (!$is_pickup_point && ($street === "" || $city === "" || $zip === "")) {
    return packeta_normalized_response(false, "Objednávka nemá úplnú dodaciu adresu.", 0, 422, "", $reference);
  }
  $order_value = round((float) ($order["cena_na_uhradu"] ?? 0), 2);
  $cod = packeta_is_cod($order) ? $order_value : 0;
  $eshop = packeta_first_value($shipping, ["eshop", "sender"]);
  if ($eshop === "") {
    $eshop = $currency === "CZK" ? "okfish.cz" : "okfish.sk";
  }
  $packet_results = [];
  $packet_ids = [];
  $last_http_code = 0;
  foreach ($weights as $index => $weight) {
    $packet_reference = count($weights) > 1 ? $reference . "-" . ($index + 1) : $reference;
    $attributes = [
      "number" => $packet_reference,
      "name" => $recipient_name,
      "company" => $company,
      "phone" => $phone,
      "email" => $email,
      "addressId" => $address_id,
      "cod" => number_format($cod, 2, ".", ""),
      "value" => number_format($order_value, 2, ".", ""),
      "weight" => number_format((float) $weight, 3, ".", ""),
      "eshop" => $eshop,
      "currency" => $currency
    ];
    if (!$is_pickup_point) {
      $attributes["street"] = $street;
      $attributes["houseNumber"] = $house_number;
      $attributes["city"] = $city;
      $attributes["zip"] = $zip;
    }
    $api_result = packeta_create_packet($api_password, $attributes);
    $last_http_code = $api_result["http_code"];
    if ($api_result["curl_error"] !== "") {
      return packeta_normalized_response(false, "Packeta spojenie zlyhalo: " . $api_result["curl_error"], 0, 502, "", $reference);
    }
    $response_data = packeta_parse_xml($api_result["response"]);
    if (!is_array($response_data)) {
      return packeta_normalized_response(false, "Packeta vrátila neplatnú odpoveď.", $last_http_code, 502, $api_result["response"], $reference);
    }
    if ($last_http_code < 200 || $last_http_code >= 300 || strtolower((string) ($response_data["status"] ?? "")) === "fault") {
      $messages = packeta_error_messages($response_data);
      $message = !empty($messages) ? implode(" ", $messages) : "Packeta odmietla vytvorenie zásielky.";
      return packeta_normalized_response(false, $message, $last_http_code, 422, $api_result["response"], $reference);
    }
    $packet_id = trim((string) ($response_data["result"]["id"] ?? ""));
    if ($packet_id === "") {
      return packeta_normalized_response(false, "Packeta nevrátila ID vytvorenej zásielky.", $last_http_code, 502, $api_result["response"], $reference);
    }
    $packet_ids[] = $packet_id;
    $packet_results[] = ["number" => $packet_reference, "id" => $packet_id, "barcode" => (string) ($response_data["result"]["barcode"] ?? ""), "response" => $response_data];
  }
  $label_result = packeta_download_labels($api_password, $packet_ids);
  $labels = $label_result["labels"] ?? [];
  $label_error = trim((string) ($label_result["error"] ?? ""));
  $log_response = json_encode([
    "status" => "ok",
    "packets" => $packet_results,
    "labels" => $label_result["details"] ?? [],
    "label_error" => $label_error
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $message = count($packet_ids) > 1 ? "Zásielky boli úspešne odoslané do Packety." : "Zásielka bola úspešne odoslaná do Packety.";
  if ($label_error !== "") {
    $message .= " " . $label_error;
  }
  return packeta_normalized_response(true, $message, $last_http_code, 200, $log_response, implode(", ", $packet_ids), $labels);
}

$carrier_response = packeta_send_shipment($shipment_data);
