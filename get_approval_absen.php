<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 0);
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'includes/db.php';

try {
    $tab = $_GET['tab'] ?? 'pending'; // 'pending' atau 'riwayat'
    $bulan = $_GET['bulan'] ?? date('n');
    $tahun = $_GET['tahun'] ?? date('Y');

    $status_condition = ($tab === 'pending') ? "a.status_approval = 'pending'" : "a.status_approval != 'pending'";

    $sql = "SELECT 
                a.*, 
                g.nama_guru, 
                jm.mata_pelajaran, 
                jm.kelas,
                (SELECT COUNT(*) FROM detail_absensi_siswa WHERE absensi_guru_id = a.id AND status_kehadiran = 'Hadir') AS hadir,
                (SELECT COUNT(*) FROM detail_absensi_siswa WHERE absensi_guru_id = a.id AND status_kehadiran = 'Sakit') AS sakit,
                (SELECT COUNT(*) FROM detail_absensi_siswa WHERE absensi_guru_id = a.id AND status_kehadiran = 'Izin') AS izin,
                (SELECT COUNT(*) FROM detail_absensi_siswa WHERE absensi_guru_id = a.id AND status_kehadiran = 'Alpa') AS alpa
            FROM absensi a
            JOIN guru g ON a.guru_id = g.id
            JOIN jadwal_mengajar jm ON a.jadwal_id = jm.id
            WHERE a.tipe_absensi = 'mengajar' 
              AND $status_condition 
              AND MONTH(a.waktu_absensi) = ? 
              AND YEAR(a.waktu_absensi) = ?
            ORDER BY a.waktu_absensi DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $bulan, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>