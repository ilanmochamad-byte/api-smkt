<?php
// get_siswa_by_kelas.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'includes/db.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Koneksi database gagal: " . $conn->connect_error]);
    exit();
}

try {
    $kelas = $_GET['kelas'] ?? '';
    if (empty($kelas)) {
        http_response_code(400);
        throw new Exception("Parameter kelas wajib diisi.");
    }

    $sql = "SELECT id, nama_siswa FROM siswa WHERE kelas = ? ORDER BY nama_siswa ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $kelas);
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