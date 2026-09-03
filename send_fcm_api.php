<?php
// send_fcm_api.php
// URL: https://api.smkt.alhasan.co.id/send_fcm_api.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

// --- KEAMANAN SEDERHANA ---
// Kunci ini untuk memastikan hanya server web admin Anda yang bisa menembak API ini
$secret_key = "SMKTAH_Classync_2026_Secure!"; 

$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validasi Kunci Keamanan
if (!isset($data['secret']) || $data['secret'] !== $secret_key) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized / Kunci Rahasia Salah']);
    exit();
}

// Validasi Data
if (empty($data['token']) || empty($data['title']) || empty($data['body'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
    exit();
}

// --- KONFIGURASI FCM V1 API ---
$serviceAccountKeyPath = '/DATA/k1807225/credentials/classyncapp-9a6b6-firebase-adminsdk-fbsvc-a059a16151.json';
$projectId = 'classyncapp-9a6b6';

// Memanggil autoload vendor karena berada di server yang sama
require_once __DIR__ . '/vendor/autoload.php';

function getAccessToken($keyFilePath) {
    $client = new \Google\Client();
    $client->setAuthConfig($keyFilePath);
    $client->addScope('https://www.googleapis.com/auth/cloud-platform');
    $client->fetchAccessTokenWithAssertion();
    $accessToken = $client->getAccessToken();
    return $accessToken['access_token'] ?? null;
}

try {
    $accessToken = getAccessToken($serviceAccountKeyPath);
    if (!$accessToken) throw new Exception("Gagal mendapatkan Access Token dari Google");

    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    
    // Payload Notifikasi
    $payload = [
        'message' => [
            'token' => $data['token'],
            'notification' => [
                'title' => $data['title'],
                'body' => $data['body']
            ],
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'sound' => 'default',
                    'channel_id' => 'default'
                ]
            ],
            'data' => [
                'screen' => isset($data['screen']) ? $data['screen'] : ''
            ]
        ]
    ];

    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $result = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode == 200) {
        echo json_encode(['status' => 'success', 'response' => json_decode($result)]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'fcm_response' => json_decode($result)]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>