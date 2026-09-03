<?php
// ajukan_absensi.php
ini_set('display_errors', 1); error_reporting(E_ALL);
header("Content-Type: application/json; charset=UTF-8"); header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }
require_once 'includes/db.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (empty($data['guru_id']) || empty($data['jenis']) || empty($data['tanggal'])) {
        throw new Exception("Data tidak lengkap.");
    }

    $stmt = $conn->prepare("INSERT INTO pengajuan_absensi (guru_id, jenis_absensi, tanggal, jam_mulai, jam_selesai, keterangan) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $data['guru_id'], $data['jenis'], $data['tanggal'], $data['jam_mulai'], $data['jam_selesai'], $data['keterangan']);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Pengajuan berhasil dikirim. Menunggu persetujuan Admin.']);
    } else {
        throw new Exception("Gagal menyimpan: " . $stmt->error);
    }
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
?>