<?php
header("Content-Type: application/json; charset=UTF-8");
// include 'db.php'; // Asumsi Anda punya file koneksi database terpisah
require_once 'includes/db.php';
$guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;

if ($guru_id === 0) {
    echo json_encode(['mapel' => [], 'kelas' => []]);
    exit();
}

$mapel_list = [];
$kelas_list = [];

try {
    // Ambil daftar mata pelajaran unik yang diajar guru
    $stmt_mapel = $conn->prepare("SELECT DISTINCT mata_pelajaran FROM jadwal_mengajar WHERE guru_id = ? ORDER BY mata_pelajaran");
    $stmt_mapel->bind_param("i", $guru_id);
    $stmt_mapel->execute();
    $result_mapel = $stmt_mapel->get_result();
    while($row = $result_mapel->fetch_assoc()) {
        // Format agar sesuai dengan RNPickerSelect (label & value)
        $mapel_list[] = ['label' => $row['mata_pelajaran'], 'value' => $row['mata_pelajaran']];
    }
    $stmt_mapel->close();

    // Ambil daftar kelas unik yang diajar guru
    $stmt_kelas = $conn->prepare("SELECT DISTINCT kelas FROM jadwal_mengajar WHERE guru_id = ? ORDER BY kelas");
    $stmt_kelas->bind_param("i", $guru_id);
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
    while($row = $result_kelas->fetch_assoc()) {
        // Format agar sesuai dengan RNPickerSelect (label & value)
        $kelas_list[] = ['label' => $row['kelas'], 'value' => $row['kelas']];
    }
    $stmt_kelas->close();

    echo json_encode(['mapel' => $mapel_list, 'kelas' => $kelas_list]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>