<?php
// get_status_absensi_harian.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'includes/db.php';

$conn = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    $guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
    if ($guru_id === 0) {
        http_response_code(400);
        throw new Exception("Parameter guru_id wajib diisi.");
    }

    $sql = "SELECT jam_masuk, jam_pulang FROM absensi_harian WHERE guru_id = ? AND tanggal = CURDATE()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $guru_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Belum ada record untuk hari ini
        echo json_encode(['status' => 'belum_absen']);
    } else {
        $row = $result->fetch_assoc();
        if ($row['jam_pulang'] !== null) {
            // Sudah absen masuk DAN pulang
            echo json_encode([
                'status' => 'selesai',
                'jam_masuk' => date('H:i', strtotime($row['jam_masuk'])),
                'jam_pulang' => date('H:i', strtotime($row['jam_pulang']))
            ]);
        } else if ($row['jam_masuk'] !== null) {
            // Sudah absen masuk, TAPI belum pulang
            echo json_encode([
                'status' => 'sudah_masuk',
                'jam_masuk' => date('H:i', strtotime($row['jam_masuk']))
            ]);
        }
    }
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>