<?php
// export_rekap_absen_hp.php
ini_set('display_errors', 1); error_reporting(E_ALL);
header("Content-Type: application/json; charset=UTF-8"); header("Access-Control-Allow-Origin: *");
// ... (Header sama seperti di atas) ...

require_once 'includes/db.php';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

try {
    $kelas = $_GET['kelas'] ?? '';
    $bulan = $_GET['bulan'] ?? 0;
    $tahun = $_GET['tahun'] ?? 0;

    if (empty($kelas) || $bulan == 0 || $tahun == 0) throw new Exception("Filter tidak lengkap.");

    $sql = "SELECT a.tanggal, s.nama_siswa, s.nisn, a.jam_pengambilan, a.jam_pengembalian, a.status
            FROM absensi_hp a
            JOIN siswa s ON a.siswa_id = s.id
            WHERE s.kelas = ? AND MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?
            ORDER BY a.tanggal ASC, s.nama_siswa ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $kelas, $bulan, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $row['jam_pengambilan'] = $row['jam_pengambilan'] ? date('H:i', strtotime($row['jam_pengambilan'])) : '-';
        $row['jam_pengembalian'] = $row['jam_pengembalian'] ? date('H:i', strtotime($row['jam_pengembalian'])) : '-';
        $data[] = $row;
    }
    
    echo json_encode(['data' => $data]);

} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
?>