<?php
// Error reporting untuk debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// --- INFORMASI DATABASE ANDA ---
require_once 'includes/db.php';
// -----------------------------------------

$conn = null; // Inisialisasi koneksi

try {
    // Buat koneksi di dalam blok try
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    $guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;

    if ($guru_id === 0) {
        // Kirim array kosong jika tidak ada guru_id, jangan buat error
        echo json_encode(['data' => []]);
        exit();
    }

    // Query untuk mengambil riwayat jurnal
    $sql = "SELECT id, tanggal, mata_pelajaran, kelas FROM jurnal_harian WHERE guru_id = ? ORDER BY tanggal DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL Prepare Error: " . $conn->error);
    }

    $stmt->bind_param("i", $guru_id);
    
    if (!$stmt->execute()) {
        throw new Exception("SQL Execute Error: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $data = [];
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();

    http_response_code(200);
    echo json_encode(['data' => $data]);

} catch (Exception $e) {
    // Kirim pesan error yang jelas jika terjadi masalah
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>