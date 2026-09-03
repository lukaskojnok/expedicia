<?php
ini_set("display_errors", "0");

include __DIR__ . "/config/common.php";

exit;

$shoptet_private_api_token = SHOPTET_API;

/**
 * Zmení stav objednávky v Shoptete.
 *
 * @param string $order_code Číslo objednávky, napr. 2026001532
 * @param int $status_id ID cieľového stavu v Shoptete
 * @param string $token Private API token
 */
function changeShoptetOrderStatus($order_code, $status_id, $token) {
  $url = "https://api.myshoptet.com/api/orders/"
    . rawurlencode($order_code)
    . "/status";

  $data = [
    "data" => [
      "statusId" => (int) $status_id
    ]
  ];

  $ch = curl_init($url);

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "PATCH",
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
      "Shoptet-Private-API-Token: " . $token,
      "Content-Type: application/json"
    ],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30
  ]);

  $raw_response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curl_error = curl_error($ch);

  curl_close($ch);

  if ($raw_response === false) {
    return [
      "success" => false,
      "http_code" => $http_code,
      "message" => "Chyba CURL: " . $curl_error
    ];
  }

  $response = json_decode($raw_response, true);

  if ($http_code < 200 || $http_code >= 300) {
    return [
      "success" => false,
      "http_code" => $http_code,
      "message" => $response["errors"][0]["message"] ?? "Shoptet vrátil chybu.",
      "response" => $response
    ];
  }

  return [
    "success" => true,
    "http_code" => $http_code,
    "message" => "Stav objednávky bol zmenený.",
    "response" => $response
  ];
}


// POUŽITIE:

$order_code = "2026001542";
$status_id = "-3"; // Sem vlož skutočné ID stavu v tvojom Shoptete

$result = changeShoptetOrderStatus(
  $order_code,
  $status_id,
  $shoptet_private_api_token
);

if ($result["success"]) {
  echo "OK: " . $result["message"];
} else {
  echo "CHYBA: " . $result["message"];
}



















exit;

header("Content-Type: application/json; charset=utf-8");

$required_constants = [
  "DPD_URL",
  "DPD_KEY",
  "DPD_EMAIL",
  "DPD_DELIS_ID",
  "DPD_ZVOZOVA_ADRESA_1"
];
$missing_constants = array_values(array_filter($required_constants, function($name) {
  return !defined($name);
}));

if (!empty($missing_constants)) {
  http_response_code(500);
  echo json_encode([
    "error" => "Chýbajú DPD konštanty v config/psw.php.",
    "missing" => $missing_constants
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
  exit;
}

/*
 * Pred spustením zmeň údaje príjemcu na skutočnú testovaciu adresu.
 * Referencia musí byť pri každom pokuse jedinečná.
 */
$reference = "DPD-TEST-" . date("Ymd-His");

$request_data = [
  "jsonrpc" => "2.0",
  "method" => "create",
  "params" => [
    "DPDSecurity" => [
      "SecurityToken" => [
        "ClientKey" => DPD_KEY,
        "Email" => DPD_EMAIL
      ]
    ],
    "shipment" => [[
      "reference" => $reference,
      "delisId" => DPD_DELIS_ID,
      "note" => "Test viacbalíkovej zásielky " . $reference,
      "product" => 9,
      "addressSender" => [
        "id" => (int) DPD_ZVOZOVA_ADRESA_1
      ],
      "addressRecipient" => [
        "type" => "b2c",
        "name" => "TEST PRIJEMCA",
        "street" => "DOPLN ULICU",
        "houseNumber" => "1",
        "zip" => "01001",
        "country" => 703,
        "city" => "Zilina",
        "phone" => "+421900000000",
        "email" => "test@example.com",
        "note" => ""
      ],
      "parcels" => [
        "parcel" => [
          [
            "reference1" => $reference,
            "reference2" => "BALIK-1",
            "weight" => 1,
            "height" => 20,
            "width" => 20,
            "depth" => 20
          ],
          [
            "reference1" => $reference,
            "reference2" => "BALIK-2",
            "weight" => 1,
            "height" => 20,
            "width" => 20,
            "depth" => 20
          ]
        ]
      ]
    ]]
  ],
  "id" => $reference
];

$json = json_encode($request_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$response_headers = [];
$curl = curl_init(DPD_URL);

curl_setopt_array($curl, [
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => $json,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CONNECTTIMEOUT => 10,
  CURLOPT_TIMEOUT => 60,
  CURLOPT_HTTPHEADER => [
    "Content-Type: application/json",
    "Accept: application/json"
  ],
  CURLOPT_HEADERFUNCTION => function($curl, $header_line) use (&$response_headers) {
    $response_headers[] = rtrim($header_line, "\r\n");
    return strlen($header_line);
  }
]);

$raw_response = curl_exec($curl);
$curl_error_number = curl_errno($curl);
$curl_error = curl_error($curl);
$curl_info = curl_getinfo($curl);
$http_code = (int) ($curl_info["http_code"] ?? 0);
curl_close($curl);

/*
 * Nevypisujeme request, pretože obsahuje ClientKey.
 * DPD response zostáva úplne neupravená v raw_response.
 */
http_response_code($http_code > 0 ? $http_code : 502);
echo json_encode([
  "test_reference" => $reference,
  "curl_errno" => $curl_error_number,
  "curl_error" => $curl_error,
  "curl_info" => $curl_info,
  "response_headers" => $response_headers,
  "raw_response" => $raw_response,
  "decoded_response" => is_string($raw_response) ? json_decode($raw_response, true) : null,
  "json_decode_error" => is_string($raw_response) ? json_last_error_msg() : null
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
