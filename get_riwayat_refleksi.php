<?php
// get_riwayat_refleksi.php
ini_set('display_errors', 1); error_reporting(E_ALL);
header("Content-Type: application/json; charset=UTF-8"); header("Access-Control-Allow-Origin: *");

require_once 'includes/db.php';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

try {
    $guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
    if ($guru_id === 0) throw new Exception("Guru ID diperlukan.");

    // Ambil nama guru juga untuk keperluan kop surat PDF
    $sql = "SELECT r.*, g.nama_guru, g.nip FROM refleksi_guru r JOIN guru g ON r.guru_id = g.id WHERE r.guru_id = ? ORDER BY r.tanggal DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $guru_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) { $data[] = $row; }
    
    echo json_encode(['data' => $data]);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally { if ($conn) $conn->close(); }
?>