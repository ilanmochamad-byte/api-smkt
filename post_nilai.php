<?php
// post_nilai.php
ini_set('display_errors', '0');
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
require_once __DIR__ . '/includes/auth.php';

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

    // Fase B (inventaris K2): guru_id ada di tiap butir. Dengan token, semua
    // butir ditulis atas nama pemilik token; tanpa token (aplikasi yang
    // beredar) guru_id per butir dipakai seperti sebelumnya.
    $guru_token = guru_id_dari_token($conn);

    foreach ($nilai_data as $nilai_item) {
        $guru_id_butir = $guru_token ?? $nilai_item['guru_id'];
        $stmt->bind_param(
            "iissdsss",
            $nilai_item['siswa_id'],
            $guru_id_butir,
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