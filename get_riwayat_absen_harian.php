<?php
// api/get_riwayat_absen_harian.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once 'includes/db.php';

$conn = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    // Ambil parameter bulan dan tahun (default bulan & tahun saat ini)
    $bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
    $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

    // Query untuk mengambil riwayat absen harian beserta nama guru
    $sql = "SELECT a.id, a.tanggal, a.jam_masuk, a.jam_pulang, a.bonus, g.nama_guru 
            FROM absensi_harian a
            JOIN guru g ON a.guru_id = g.id
            WHERE MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?
            ORDER BY a.tanggal DESC, a.jam_masuk DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $bulan, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    $total_bonus_bulan_ini = 0;

    while ($row = $result->fetch_assoc()) {
        $bonus = (int)$row['bonus'];
        $total_bonus_bulan_ini += $bonus;
        
        $data[] = [
            'id' => $row['id'],
            'tanggal' => $row['tanggal'],
            'nama_guru' => $row['nama_guru'],
            'jam_masuk' => $row['jam_masuk'] ? date('H:i', strtotime($row['jam_masuk'])) : '-',
            'jam_pulang' => $row['jam_pulang'] ? date('H:i', strtotime($row['jam_pulang'])) : 'Belum Pulang',
            'bonus' => $bonus
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true, 
        'data' => $data,
        'summary' => [
            'total_riwayat' => count($data),
            'total_bonus_dikeluarkan' => $total_bonus_bulan_ini
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>