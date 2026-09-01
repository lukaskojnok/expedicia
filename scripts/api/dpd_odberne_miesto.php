<?php
require_once __DIR__ . "/dpd_common.php";

/*
 * foxdeli_pick_up_place je iba názov/adresa miesta. DPD API používa
 * presné parcelShopId uložené priamo pri objednávke.
 */
$parcelshop_id = trim((string) ($shipment_data["order"]["parcelShopId"] ?? ""));
$carrier_response = dpd_create_shipment($shipment_data, $parcelshop_id);
