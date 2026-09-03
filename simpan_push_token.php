<?php
// simpan_push_token.php
ini_set('display_errors', 1); error_reporting(E_ALL);
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'includes/db.php';

$conn = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi gagal: " . $conn->connect_error);
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $guru_id = isset($data['guru_id']) ? (int)$data['guru_id'] : 0;
    $push_token = isset($data['push_token']) ? $data['push_token'] : '';

    if ($guru_id === 0 || empty($push_token)) {
        http_response_code(400);
        throw new Exception("Data guru_id dan push_token wajib diisi.");
    }

    // Simpan atau Perbarui token
    $sql = "UPDATE guru SET push_token = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $push_token, $guru_id);

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Token berhasil disimpan.']);
    } else {
        throw new Exception("Gagal menyimpan token.");
    }
    $stmt->close();

} catch (Exception $e) {
    if (http_response_code() === 200) http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>