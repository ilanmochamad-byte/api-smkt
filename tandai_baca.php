<?php
// tandai_baca.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }
ini_set('display_errors', 1); error_reporting(E_ALL);

// Database
require_once 'includes/db.php';

$conn = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi gagal: " . $conn->connect_error);
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $notifikasi_id = isset($data['notifikasi_id']) ? (int)$data['notifikasi_id'] : 0;
    if ($notifikasi_id === 0) {
        throw new Exception("Notifikasi ID tidak valid.");
    }

    $sql = "UPDATE notifikasi SET is_read = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $notifikasi_id);
    $stmt->execute();
    
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Notifikasi ditandai terbaca.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>