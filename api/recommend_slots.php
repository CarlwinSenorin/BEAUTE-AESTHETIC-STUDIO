<?php
/**
 * API Endpoint: Recommend Slots
 * Accepts JSON request and returns intelligent resource allocation.
 */

require_once '../config/functions.php';
require_once '../includes/SchedulingEngine.php';

header('Content-Type: application/json');

// Get request body
$rawInput = file_get_contents('php://input');
$request = json_decode($rawInput, true);

if (!$request || !isset($request['service_type'])) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request. Please provide service_type, duration_minutes, and preferred_date_range."
    ]);
    exit;
}

try {
    $conn = getDBConnection();
    $engine = new SchedulingEngine($conn);
    
    $recommendation = $engine->getRecommendation($request);
    
    echo json_encode($recommendation, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Internal server error: " . $e->getMessage()
    ]);
}
