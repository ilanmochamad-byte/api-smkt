<?php
// send_fcm_api.php
// URL: https://api.smkt.alhasan.co.id/send_fcm_api.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

// --- KEAMANAN SEDERHANA ---
// Kunci ini untuk memastikan hanya server web admin Anda yang bisa menembak API
// ini. Dibaca dari luar webroot: kedua repositori publik, jadi kunci harfiah di
// berkas ini sama saja dengan tidak ada kunci sama sekali.
//
// $fcm_secrets_sah adalah DAFTAR, bukan satu nilai, dan itu disengaja. Selama
// rotasi ia memuat kunci lama dan baru sekaligus, sehingga tidak pernah ada
// momen ketika pengirim dan penerima berbeda pendapat — dan rotasinya sendiri
// tidak menuntut deploy kode sama sekali.
$config_fcm = '/DATA/k1807225/config/fcm-classync.php';
if (!is_readable($config_fcm)) {
    error_log("send_fcm_api: konfigurasi FCM tidak terbaca di " . $config_fcm);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Konfigurasi server tidak lengkap']);
    exit();
}
require $config_fcm;

$secrets_sah = (isset($fcm_secrets_sah) && is_array($fcm_secrets_sah)) ? $fcm_secrets_sah : []; 

$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validasi Kunci Keamanan. hash_equals membandingkan dalam waktu tetap, dan
// daftar kosong berarti tidak ada yang lolos — bukan semua lolos.
$secret_dikirim = isset($data['secret']) ? (string)$data['secret'] : '';
$kunci_cocok = false;
foreach ($secrets_sah as $kunci_sah) {
    if (is_string($kunci_sah) && $kunci_sah !== '' && hash_equals($kunci_sah, $secret_dikirim)) {
        $kunci_cocok = true;
        break;
    }
}

if (!$kunci_cocok) {
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

// --- PEMILAHAN MENURUT BENTUK TOKEN ---
// ClassyncApp memanggil getDevicePushTokenAsync(), yang mengembalikan token
// ASLI platform. Bentuknya berbeda, dan bedanya menentukan tujuannya:
//
//   mengandung ':'        token registrasi FCM (Android)  -> FCM v1
//   heksadesimal murni    token perangkat APNs (iOS)      -> APNs langsung
//   ExponentPushToken...  sisa migrasi sebelum 13 Jul 2026 -> tidak bisa dikirim
//
// Sebelum pemilahan ini, token APNs dikirim ke FCM v1 dan selalu ditolak
// dengan "The registration token is not a valid FCM registration token".
// Seluruh guru pengguna iPhone tidak menerima notifikasi selama dua bulan.
$token_tujuan = (string)$data['token'];

if (strpos($token_tujuan, 'ExponentPushToken') === 0) {
    error_log("send_fcm_api: token Expo lama, guru perlu memasang ulang aplikasi.");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Token perangkat sudah usang. Guru perlu memasang ulang aplikasi.']);
    exit();
}

if (strpos($token_tujuan, ':') === false && preg_match('/^[0-9a-fA-F]+$/', $token_tujuan)) {
    // Jalur APNs. Sengaja dipasang SEBELUM autoload vendor Google, supaya
    // notifikasi iOS tidak ikut memuat pustaka yang tidak dipakainya.
    require_once __DIR__ . '/includes/pengirim_apns.php';

    $hasil_apns = kirimApns(
        $token_tujuan,
        $data['title'],
        $data['body'],
        isset($data['screen']) ? (string)$data['screen'] : ''
    );

    if ($hasil_apns['ok']) {
        echo json_encode(['status' => 'success', 'response' => ['apns' => true]]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'APNs menolak: ' . $hasil_apns['reason']]);
    }
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