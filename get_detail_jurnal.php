<?php
// Error reporting untuk debugging
ini_set('display_errors', '0');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// --- INFORMASI DATABASE ANDA ---
require_once 'includes/db.php';
require_once __DIR__ . '/includes/auth.php';
// -----------------------------------------

$conn = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    $jurnal_id = isset($_GET['jurnal_id']) ? (int)$_GET['jurnal_id'] : 0;

    if ($jurnal_id === 0) {
        throw new Exception("Parameter jurnal_id dibutuhkan.");
    }

    // Query untuk mengambil semua data dari jurnal_harian dan nama guru
    // Fase B (inventaris K4): dengan token, hanya jurnal milik pemilik token
    // yang tersentuh; milik guru lain diperlakukan seperti id yang tidak ada.
    // Tanpa token (aplikasi yang beredar) tetap seperti sebelumnya.
    // Jurnal guru lain dijawab {"data": null}, sama seperti id yang tidak ada.
    $guru_token = guru_id_dari_token($conn);
    $sql = "SELECT j.*, g.nama_guru FROM jurnal_harian j JOIN guru g ON j.guru_id = g.id WHERE j.id = ?";
    if ($guru_token !== null) {
        $sql .= " AND j.guru_id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Jika prepare gagal, artinya ada nama tabel/kolom yang salah
        throw new Exception("SQL Prepare Error: " . $conn->error);
    }
    
    if ($guru_token === null) {
        $stmt->bind_param("i", $jurnal_id);
    } else {
        $stmt->bind_param("ii", $jurnal_id, $guru_token);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("SQL Execute Error: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    http_response_code(200);
    echo json_encode(['data' => $data]);

} catch (Exception $e) {
    // Kirim pesan error yang lebih detail
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