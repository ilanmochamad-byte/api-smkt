<?php
// get_riwayat_penilaian.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'includes/db.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    exit();
}

try {
    $siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;
    if ($siswa_id === 0) {
        http_response_code(400);
        throw new Exception("Parameter siswa_id wajib diisi.");
    }

    $sql = "SELECT p.mata_pelajaran, p.jenis_penilaian, p.nilai, p.keterangan, p.tanggal_penilaian, g.nama_guru, p.guru_id, p.tahun_ajaran 
            FROM penilaian_siswa p
            JOIN guru g ON p.guru_id = g.id
            WHERE p.siswa_id = ?
            ORDER BY p.tanggal_penilaian DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $siswa_id);
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
    if ($conn) $conn->close();
}
?>