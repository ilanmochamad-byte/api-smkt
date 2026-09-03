<?php
header("Content-Type: application/json; charset=UTF-8");
require_once 'includes/db.php';

// Ambil parameter
$guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
$kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$mapel = isset($_GET['mapel']) ? $_GET['mapel'] : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : '';
$ta = isset($_GET['ta']) ? $_GET['ta'] : '2025/2026';

if ($guru_id === 0 || empty($kelas) || empty($mapel) || empty($semester)) {
    http_response_code(400);
    echo json_encode(['data' => [], 'message' => 'Parameter tidak lengkap.']);
    exit();
}

$data = [];

try {
    // Query ini melakukan PIVOT data nilai berdasarkan 'keterangan'
    // Logika CASE WHEN ini meniru apa yang Anda lakukan di 'detail_siswa.tsx'
    $sql = "SELECT
                s.nisn,
                s.nama_siswa,
                MAX(CASE WHEN p.keterangan = 'Sumatif Tengah Semester Ganjil' THEN p.nilai ELSE NULL END) as sts_ganjil,
                MAX(CASE WHEN p.keterangan = 'Sumatif Semester' THEN p.nilai ELSE NULL END) as sas_ganjil,
                MAX(CASE WHEN p.keterangan = 'Sumatif Tengah Semester Genap' THEN p.nilai ELSE NULL END) as sts_genap,
                MAX(CASE WHEN p.keterangan = 'Sumatif Akhir Semester' THEN p.nilai ELSE NULL END) as sas_genap
            FROM penilaian_siswa p
            JOIN siswa s ON p.siswa_id = s.id
            WHERE
                p.guru_id = ?
                AND s.kelas = ?
                AND p.mata_pelajaran = ?
                AND p.semester = ?
                AND p.tahun_ajaran = ?
            GROUP BY s.id, s.nisn, s.nama_siswa
            ORDER BY s.nama_siswa ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $guru_id, $kelas, $mapel, $semester, $ta);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
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