<?php
// update_jurnal.php
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

// Database credentials
require_once 'includes/db.php';

$conn = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi gagal: " . $conn->connect_error);
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Ambil semua data termasuk ID jurnal yang akan diupdate
    $jurnal_id = isset($data['id']) ? (int)$data['id'] : 0;
    $guru_id = isset($data['guru_id']) ? (int)$data['guru_id'] : 0; // Sebaiknya sertakan untuk verifikasi
    $tanggal = isset($data['tanggal']) ? $data['tanggal'] : '';
    $mata_pelajaran = isset($data['mata_pelajaran']) ? $data['mata_pelajaran'] : '';
    $kelas = isset($data['kelas']) ? $data['kelas'] : '';
    $semester = isset($data['semester']) ? $data['semester'] : '';
    $tahun_ajaran = isset($data['tahun_ajaran']) ? $data['tahun_ajaran'] : '';
    $tujuan_pembelajaran = isset($data['tujuan_pembelajaran']) ? $data['tujuan_pembelajaran'] : '';
    $materi = isset($data['materi']) ? $data['materi'] : '';
    $penilaian = isset($data['penilaian']) ? $data['penilaian'] : '';

    if ($jurnal_id === 0 || $guru_id === 0 || empty($tanggal) || empty($mata_pelajaran) || empty($kelas)) {
        http_response_code(400);
        throw new Exception("Data tidak lengkap.");
    }

    $sql = "UPDATE jurnal_harian SET 
                tanggal = ?, 
                mata_pelajaran = ?, 
                kelas = ?, 
                semester = ?, 
                tahun_ajaran = ?, 
                tujuan_pembelajaran = ?, 
                materi = ?, 
                penilaian = ? 
            WHERE id = ? AND guru_id = ?"; // Pastikan hanya guru ybs yg bisa update

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssii", 
        $tanggal, $mata_pelajaran, $kelas, $semester, $tahun_ajaran, 
        $tujuan_pembelajaran, $materi, $penilaian, 
        $jurnal_id, $guru_id
    );

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Modul ajar berhasil diperbarui.']);
        } else {
             http_response_code(404); // Atau 304 Not Modified jika data sama
            throw new Exception("Modul ajar tidak ditemukan atau tidak ada perubahan.");
        }
    } else {
        throw new Exception("Gagal memperbarui modul ajar: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    if (http_response_code() === 200) http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>