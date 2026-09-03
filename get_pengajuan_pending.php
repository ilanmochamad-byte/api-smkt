<?php
// get_pengajuan_pending.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// --- KONFIGURASI DATABASE (Langsung di-deklarasikan) ---
require_once 'includes/db.php';

$conn = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    $stmt = $conn->prepare("
        SELECT p.id, p.guru_id, g.nama_guru, p.jenis_absensi, p.tanggal, 
               p.jam_mulai, p.jam_selesai, p.keterangan, p.status, p.created_at
        FROM pengajuan_absensi p 
        JOIN guru g ON p.guru_id = g.id 
        WHERE p.status = 'Pending' 
        ORDER BY p.tanggal ASC
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare statement gagal: " . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>