<?php
// delete_jurnal.php
ini_set('display_errors', '0');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Gunakan POST untuk delete
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database credentials
require_once 'includes/db.php';
require_once __DIR__ . '/includes/auth.php';
$conn = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi gagal: " . $conn->connect_error);
    }

    // Ambil ID dari body request (lebih aman via POST)
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $jurnal_id = isset($data['jurnal_id']) ? (int)$data['jurnal_id'] : 0;
    // Anda mungkin perlu verifikasi tambahan di sini, misal cek guru_id

    if ($jurnal_id === 0) {
        http_response_code(400);
        throw new Exception("Parameter jurnal_id wajib diisi.");
    }

    // Fase B (inventaris K4): dengan token, hanya jurnal milik pemilik token
    // yang tersentuh; milik guru lain diperlakukan seperti id yang tidak ada.
    // Tanpa token (aplikasi yang beredar) tetap seperti sebelumnya.
    $guru_token = guru_id_dari_token($conn);
    if ($guru_token === null) {
        $stmt = $conn->prepare("DELETE FROM jurnal_harian WHERE id = ?");
        $stmt->bind_param("i", $jurnal_id);
    } else {
        $stmt = $conn->prepare("DELETE FROM jurnal_harian WHERE id = ? AND guru_id = ?");
        $stmt->bind_param("ii", $jurnal_id, $guru_token);
    }

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Modul ajar berhasil dihapus.']);
        } else {
             http_response_code(404); // Not Found
            throw new Exception("Modul ajar dengan ID tersebut tidak ditemukan.");
        }
    } else {
        throw new Exception("Gagal menghapus modul ajar: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    if (http_response_code() === 200) http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>