<?php
// Error reporting untuk debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// --- GANTI DENGAN INFORMASI DATABASE ANDA ---
require_once 'includes/db.php';
// -----------------------------------------

$conn = null;

try {
    // 1. Buat koneksi di dalam try-catch
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

    // 2. Query yang aman dengan prepared statement
    $sql = "SELECT DISTINCT mata_pelajaran FROM jadwal_mengajar WHERE guru_id = ? ORDER BY mata_pelajaran ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL Prepare Error: " . $conn->error);
    }

    $stmt->bind_param("i", $guru_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while($row = $result->fetch_assoc()) {
        $data[] = ['label' => $row['mata_pelajaran'], 'value' => $row['mata_pelajaran']];
    }
    $stmt->close();

    http_response_code(200);
    echo json_encode(['data' => $data]);

} catch (Exception $e) {
    // 3. Kirim pesan error yang jelas jika terjadi masalah
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