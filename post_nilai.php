<?php
// post_nilai.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Ganti dengan informasi database Anda
require_once 'includes/db.php';

$conn = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi gagal: " . $conn->connect_error);
    }

    // Mengambil data JSON dari body request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $nilai_data = $data['nilai_data'] ?? [];

    if (empty($nilai_data)) {
        http_response_code(400);
        throw new Exception("Tidak ada data nilai yang dikirim.");
    }
    
    // Siapkan query untuk dieksekusi berulang kali
    $sql = "INSERT INTO penilaian_siswa (siswa_id, guru_id, mata_pelajaran, jenis_penilaian, nilai, keterangan, semester, tahun_ajaran, tanggal_penilaian) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())";
    $stmt = $conn->prepare($sql);

    foreach ($nilai_data as $nilai_item) {
        $stmt->bind_param(
            "iissdsss",
            $nilai_item['siswa_id'],
            $nilai_item['guru_id'],
            $nilai_item['mata_pelajaran'],
            $nilai_item['jenis_penilaian'],
            $nilai_item['nilai'],
            $nilai_item['keterangan'],
            $nilai_item['semester'],
            $nilai_item['tahun_ajaran']
        );
        $stmt->execute();
    }
    
    $stmt->close();

    http_response_code(201);
    echo json_encode(['status' => 'success', 'message' => 'Nilai berhasil disimpan.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>