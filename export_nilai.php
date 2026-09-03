<?php
// export_nilai.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'includes/db.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['message' => 'Koneksi gagal']);
    exit();
}

try {
    // Ambil filter dari GET request
    $guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
    $mapel = isset($_GET['mata_pelajaran']) ? $_GET['mata_pelajaran'] : '';
    $semester = isset($_GET['semester']) ? $_GET['semester'] : '';
    $tahun_ajaran = isset($_GET['tahun_ajaran']) ? $_GET['tahun_ajaran'] : '';

    if ($guru_id === 0 || empty($mapel) || empty($semester) || empty($tahun_ajaran)) {
        http_response_code(400);
        throw new Exception("Semua filter (guru, mapel, semester, tahun ajaran) wajib diisi.");
    }

    $sql = "SELECT 
                s.nama_siswa, 
                s.nisn,
                s.kelas,
                p.jenis_penilaian, 
                p.nilai, 
                p.keterangan
            FROM penilaian_siswa p
            JOIN siswa s ON p.siswa_id = s.id
            WHERE 
                p.guru_id = ? AND
                p.mata_pelajaran = ? AND
                p.semester = ? AND
                p.tahun_ajaran = ?
            ORDER BY s.kelas, s.nama_siswa, p.jenis_penilaian";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $guru_id, $mapel, $semester, $tahun_ajaran);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();

    http_response_code(200);
    echo json_encode(['data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>