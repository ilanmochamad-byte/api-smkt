<?php
/**
 * API Proxy untuk mengatasi CORS
 * Upload file ini ke folder 'api' di cPanel
 */

// Enable error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, User-Agent');
header('Content-Type: application/json');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log untuk debugging (opsional, hapus di production)
$logFile = __DIR__ . '/proxy-log.txt';
$logData = date('Y-m-d H:i:s') . " - " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . "\n";
file_put_contents($logFile, $logData, FILE_APPEND);

// Ambil endpoint dari URL
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = dirname($_SERVER['SCRIPT_NAME']);

// Remove script path to get the endpoint
$endpoint = str_replace($scriptName . '/', '', $requestUri);
$endpoint = str_replace('/api/', '', $endpoint);

// API base URL
$apiBaseUrl = 'https://api.smkt.alhasan.co.id/';
$targetUrl = $apiBaseUrl . $endpoint;

// Log target URL
file_put_contents($logFile, "Target: $targetUrl\n", FILE_APPEND);

// Siapkan data request
$method = $_SERVER['REQUEST_METHOD'];
$postData = null;

if ($method === 'POST' || $method === 'PUT') {
    $postData = file_get_contents('php://input');
    file_put_contents($logFile, "Data: $postData\n", FILE_APPEND);
}

// Setup cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Set User-Agent (penting untuk server yang memerlukan ini)
curl_setopt($ch, CURLOPT_USERAGENT, 'ClassyncWeb/1.0 (PHP Proxy)');

// Set headers
$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Set method dan data
if ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    if ($postData) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
} elseif ($method === 'PUT') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    if ($postData) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
} elseif ($method === 'DELETE') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
}

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

// Log response
file_put_contents($logFile, "Response Code: $httpCode\n", FILE_APPEND);
if ($error) {
    file_put_contents($logFile, "Error: $error\n", FILE_APPEND);
}

curl_close($ch);

// Set response code
http_response_code($httpCode);

// Return response
if ($response === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Proxy error: ' . $error,
    ]);
} else {
    echo $response;
}

// Log separator
file_put_contents($logFile, "---\n", FILE_APPEND);
?>
