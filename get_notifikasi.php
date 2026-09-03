<?php
// get_notifikasi.php
header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', 1); error_reporting(E_ALL);

// Database
require_once 'includes/db.php';

$conn = null;
$response = [];

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi gagal: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    $guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
    if ($guru_id === 0) {
        throw new Exception("Guru ID tidak valid.");
    }

    $sql = "SELECT id, judul, isi, is_read, tanggal_dibuat 
            FROM notifikasi 
            WHERE guru_id = ? 
            ORDER BY tanggal_dibuat DESC 
            LIMIT 50"; // Batasi 50 notif terbaru
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $guru_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifikasi = [];
    while ($row = $result->fetch_assoc()) {
        $notifikasi[] = $row;
    }
    
    http_response_code(200);
    echo json_encode(['status' => 'success', 'data' => $notifikasi]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>