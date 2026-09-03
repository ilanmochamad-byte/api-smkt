<?php
// get_unread_count.php
header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', 1); error_reporting(E_ALL);

// Database
require_once 'includes/db.php';

$conn = null;

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

    // Query yang sangat efisien: Hanya menghitung
    $sql = "SELECT COUNT(id) as unread_count 
            FROM notifikasi 
            WHERE guru_id = ? AND is_read = 0"; 
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $guru_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $unread_count = $row ? (int)$row['unread_count'] : 0;
    
    http_response_code(200);
    // Kembalikan jumlahnya saja
    echo json_encode(['status' => 'success', 'unread_count' => $unread_count]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>