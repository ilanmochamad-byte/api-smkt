<?php
header("Content-Type: application/json; charset=UTF-8");
require_once 'includes/db.php';

// Ambil parameter dari frontend (ekspor.tsx)
$guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
$kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$mapel = isset($_GET['mapel']) ? $_GET['mapel'] : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : '';
$ta = isset($_GET['ta']) ? $_GET['ta'] : '2025/2026'; // Ambil dari filter

if ($guru_id === 0 || empty($kelas) || empty($mapel) || empty($semester)) {
    http_response_code(400);
    echo json_encode(['data' => [], 'message' => 'Parameter tidak lengkap.']);
    exit();
}

$data = [];

try {
    // Query ini mengasumsikan tabel 'jadwal_mengajar' memiliki kolom 'semester' dan 'tahun_ajaran'
    // Ini penting untuk memfilter absensi berdasarkan periode yang dipilih
    $sql = "SELECT
                s.nisn,
                s.nama_siswa,
                COUNT(das.id) as total_pertemuan,
                SUM(CASE WHEN das.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN das.status_kehadiran = 'Sakit' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN das.status_kehadiran = 'Izin' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN das.status_kehadiran = 'Alpa' THEN 1 ELSE 0 END) as alpa
            FROM detail_absensi_siswa das
            JOIN siswa s ON das.siswa_id = s.id
            JOIN absensi a ON das.absensi_guru_id = a.id
            JOIN jadwal_mengajar jm ON a.jadwal_id = jm.id
            WHERE
                a.guru_id = ?
                AND s.kelas = ?
                AND jm.mata_pelajaran = ?
                AND jm.semester = ?
                AND jm.tahun_ajaran = ?
                AND a.tipe_absensi = 'mengajar'
            GROUP BY s.id, s.nisn, s.nama_siswa
            ORDER BY s.nama_siswa ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $guru_id, $kelas, $mapel, $semester, $ta);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        $hadir = (int)$row['hadir'];
        $total = (int)$row['total_pertemuan'];
        $row['persentase'] = ($total > 0) ? round(($hadir / $total) * 100) : 0;
        $data[] = $row;
    }
    
    $stmt->close();
    echo json_encode(['data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>