<?php
require_once '../config/functions.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$date = $_GET['date'] ?? null;
$duration = (int)($_GET['duration'] ?? 60);
$staff_id = $_GET['staff_id'] ?? null;

if (!$date) {
    jsonResponse(['error' => 'Date is required'], 400);
}

$staff_id = $staff_id ? (int)$staff_id : null;
$pax = (int)($_GET['pax'] ?? 1);
$identifier = $_GET['identifier'] ?? null;
$service_category = $_GET['service_category'] ?? null;
cleanupTemporarySelections(); 
$slots = getAvailableTimeSlots($date, $duration, $staff_id, $pax, $identifier, true, $service_category);

// Get AI suggestions if no slots available
$suggestions = [];
if (empty($slots)) {
    $suggestions = getAITimeSlotSuggestions($date, $duration, $staff_id);
}

jsonResponse([
    'slots' => $slots,
    'suggestions' => $suggestions
]);
