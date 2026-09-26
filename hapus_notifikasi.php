<?php
// hapus_notifikasi.php
ini_set('display_errors', '0');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Menggunakan POST untuk menghapus
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$conn = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $notifikasi_id = $data['notifikasi_id'] ?? 0;

    if ($notifikasi_id === 0) {
        http_response_code(400);
        throw new Exception("ID Notifikasi wajib diisi.");
    }

    // Fase B (inventaris K4): dengan token, hanya notifikasi milik pemilik token
    // yang tersentuh; milik guru lain diperlakukan seperti id yang tidak ada.
    // Tanpa token (aplikasi yang beredar) tetap seperti sebelumnya.
    $guru_token = guru_id_dari_token($conn);
    if ($guru_token === null) {
        $stmt = $conn->prepare("DELETE FROM notifikasi WHERE id = ?");
        $stmt->bind_param("i", $notifikasi_id);
    } else {
        $stmt = $conn->prepare("DELETE FROM notifikasi WHERE id = ? AND guru_id = ?");
        $stmt->bind_param("ii", $notifikasi_id, $guru_token);
    }

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Notifikasi berhasil dihapus.']);
        } else {
            http_response_code(404);
            throw new Exception("Notifikasi tidak ditemukan.");
        }
    } else {
        throw new Exception("Gagal menghapus notifikasi: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    if (http_response_code() === 200) http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>