<?php
require_once '../config/functions.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$service_ids = $data['services'] ?? [];
$package_id = $data['package_id'] ?? null;
$package_ids = $data['package_ids'] ?? ($package_id ? [$package_id] : []);
$promotion_code = $data['promotion_code'] ?? null;
$pax = (int)($data['pax'] ?? 1);

$pricing = calculateAppointmentPrice($service_ids, $package_ids, $promotion_code);

jsonResponse($pricing);

