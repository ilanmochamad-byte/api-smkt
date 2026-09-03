<?php
// export_rekap_absen_harian.php
ini_set('display_errors', 1); error_reporting(E_ALL);
header("Content-Type: application/json; charset=UTF-8"); header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }
require_once 'includes/db.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

try {
    $guru_id = $_GET['guru_id'] ?? 0;
    $bulan = $_GET['bulan'] ?? 0;
    $tahun = $_GET['tahun'] ?? 0;

    if ($guru_id == 0 || $bulan == 0 || $tahun == 0) throw new Exception("Filter tidak lengkap.");

    $sql = "SELECT tanggal, jam_masuk, jam_pulang 
            FROM absensi_harian 
            WHERE guru_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? 
            ORDER BY tanggal ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $guru_id, $bulan, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Format jam agar rapi
        $row['jam_masuk'] = $row['jam_masuk'] ? date('H:i', strtotime($row['jam_masuk'])) : '-';
        $row['jam_pulang'] = $row['jam_pulang'] ? date('H:i', strtotime($row['jam_pulang'])) : '-';
        $data[] = $row;
    }
    
    echo json_encode(['data' => $data]);

} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
?>