<?php
// db.php - Jantung Koneksi Backend API Classync

// 1. Set Timezone (Wajib agar waktu absen tidak melenceng)
date_default_timezone_set('Asia/Jakarta');

// 2. Set Header Standar API (CORS & JSON)
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Jika request adalah OPTIONS (Preflight dari React Native/Axios/Fetch), langsung hentikan dengan status 200 OK
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 3. Kredensial Database — dimuat dari luar webroot.
// Berkas itu mendefinisikan $db_host, $db_user, $db_pass, $db_name.
$config_db = '/DATA/k1807225/config/db-classync.php';
if (!is_readable($config_db)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => 'Sistem sedang gangguan (DB Error).'
    ]);
    exit();
}
require $config_db;

// 4. Inisialisasi Koneksi
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }
    
    // Set charset ke UTF-8 agar karakter khusus aman
    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    // Jika database mati, keluarkan output JSON error yang rapi
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => 'Sistem sedang gangguan (DB Error).'
    ]);
    exit(); // Hentikan semua eksekusi file yang memanggil db.php ini
}
?>