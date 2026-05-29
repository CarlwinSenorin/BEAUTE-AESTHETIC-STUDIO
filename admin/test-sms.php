<?php
/**
 * Test SMS API endpoint for admin settings page
 * POST with: phone (optional, defaults to sms_from_number)
 */
require_once '../config/functions.php';
requireAdmin();

header('Content-Type: application/json');

$conn = getDBConnection();

// Get current settings
$stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('sms_api_key', 'sms_from_number', 'sms_enabled')");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$api_key = trim($settings['sms_api_key'] ?? '');
$from_number = trim($settings['sms_from_number'] ?? '');

// Validate config
if (empty($api_key)) {
    echo json_encode(['success' => false, 'message' => 'API Key is empty. Please enter your httpSMS API key first.']);
    exit;
}

if (strlen($api_key) > 200 || substr($api_key, 0, 1) === '{' || substr($api_key, 0, 1) === '[') {
    echo json_encode(['success' => false, 'message' => 'API Key looks invalid — it appears to be a JSON response, not an API key. Your API key should be a short token (e.g. pk_xxxx...). Go to httpsms.com/settings to copy the correct key.']);
    exit;
}

if (empty($from_number)) {
    echo json_encode(['success' => false, 'message' => 'From Number is empty. Enter your Android phone number.']);
    exit;
}

// Format from_number for the heartbeat check
$owner = preg_replace('/[^0-9+]/', '', $from_number);
if (substr($owner, 0, 1) !== '+') {
    $owner = '+' . $owner;
}

// First test: verify API key by calling heartbeats endpoint
$ch = curl_init('https://api.httpsms.com/v1/heartbeats?owner=' . urlencode($owner));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'x-api-key: ' . $api_key,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo json_encode(['success' => false, 'message' => "Connection error: $curl_error"]);
    exit;
}

if ($http_code === 401) {
    echo json_encode(['success' => false, 'message' => 'API Key is INVALID (401 Unauthorized). Please go to httpsms.com/settings and copy the correct API key. Make sure you copy the API key, not a JSON response.', 'http_code' => $http_code]);
    exit;
}

if ($http_code === 422) {
    echo json_encode(['success' => false, 'message' => "API Validation Error (422). Please ensure your From Number ($from_number) is in international format (e.g. +639...).", 'http_code' => $http_code]);
    exit;
}

if ($http_code !== 200) {
    echo json_encode(['success' => false, 'message' => "API returned HTTP $http_code. Response: " . substr($response, 0, 200), 'http_code' => $http_code]);
    exit;
}

// API key is valid! Now send a test message
$phone = $_POST['phone'] ?? $from_number;
$result = sendHttpSMS($phone, 'Test SMS from Beaute Aesthetic Studio at ' . date('g:i A, M j'));

if ($result === true) {
    echo json_encode(['success' => true, 'message' => "✅ Test SMS sent successfully to $phone! Check your phone."]);
} elseif ($result === 'config_missing') {
    echo json_encode(['success' => false, 'message' => 'Configuration missing. Check API key and From Number.']);
} else {
    echo json_encode(['success' => false, 'message' => "API key is valid but message failed to send. Make sure your phone ($from_number) is connected in the httpSMS Android app and is online."]);
}
