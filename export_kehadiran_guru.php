<?php
// export_kehadiran_guru.php
ini_set('display_errors', 1); error_reporting(E_ALL);
header("Content-Type: application/json; charset=UTF-8"); header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS"); // Tambahkan GET dan OPTIONS
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Database Connection Details ---
require_once 'includes/db.php';
// ------------------------------------

$conn = null; // Initialize connection variable

try {
    // Establish database connection
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        // Throw an exception if connection fails
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    // <-- HAPUS 'try {' YANG ADA DI SINI
    
    $guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
    $mapel = isset($_GET['mata_pelajaran']) ? $_GET['mata_pelajaran'] : '';
    $bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;
    $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;

    if ($guru_id === 0 || empty($mapel) || $bulan === 0 || $tahun === 0) {
        http_response_code(400); // Bad Request
        throw new Exception("Filter tidak lengkap.");
    }

    $sql = "SELECT a.waktu_absensi, jm.kelas, jm.jam_mulai, jm.jam_selesai, a.status
            FROM absensi a
            JOIN jadwal_mengajar jm ON a.jadwal_id = jm.id AND a.tipe_absensi = 'mengajar'
            WHERE a.guru_id = ?
              AND jm.mata_pelajaran = ?
              AND MONTH(a.waktu_absensi) = ?
              AND YEAR(a.waktu_absensi) = ?
            ORDER BY a.waktu_absensi ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isii", $guru_id, $mapel, $bulan, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while($row = $result->fetch_assoc()) {
        $data[] = $row; // Kirim data mentah
    }
    $stmt->close();

    http_response_code(200);
    echo json_encode(['data' => $data]);

} catch (Exception $e) { 
    // Tangani error dari koneksi atau logika
    if (http_response_code() === 200) {
        http_response_code(500); // Internal Server Error
    }
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally { 
    // Selalu tutup koneksi
    if ($conn) $conn->close(); 
}
?>