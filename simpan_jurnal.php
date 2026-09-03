<?php
// Error reporting untuk debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// --- INFORMASI DATABASE ANDA ---
require_once 'includes/db.php';
// -----------------------------------------

$conn = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    // Ambil data JSON dari aplikasi
    $json_data = file_get_contents("php://input");
    $data = json_decode($json_data);

    // Validasi data
    if (empty($data->guru_id) || empty($data->mata_pelajaran) || empty($data->kelas) || empty($data->tanggal)) {
        throw new Exception("Data wajib (guru_id, mata_pelajaran, kelas, tanggal) tidak boleh kosong.");
    }

    $stmt = $conn->prepare("INSERT INTO jurnal_harian (guru_id, mata_pelajaran, kelas, semester, tahun_ajaran, tujuan_pembelajaran, materi, penilaian, tanggal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("SQL Prepare Error: " . $conn->error);
    }
    
    $stmt->bind_param("issssssss",
        $data->guru_id,
        $data->mata_pelajaran,
        $data->kelas,
        $data->semester,
        $data->tahun_ajaran,
        $data->tujuan_pembelajaran,
        $data->materi,
        $data->penilaian,
        $data->tanggal
    );

    if (!$stmt->execute()) {
        throw new Exception("SQL Execute Error: " . $stmt->error);
    }
    
    $stmt->close();
    
    http_response_code(201);
    echo json_encode(['message' => 'Jurnal harian berhasil disimpan.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>