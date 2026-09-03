<?php
require_once 'includes/db.php';

// Ambil input JSON dari React Native (Axios)
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['absensi_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    exit();
}

$absensi_id = (int)$input['absensi_id'];
$action = $input['action'];

// Konversi tindakan Kepala Sekolah ke status Database
$status_db = ($action === 'Setujui') ? 'Hadir' : 'Ditolak';

$stmt = $conn->prepare("UPDATE absensi SET status = ? WHERE id = ? AND tipe_absensi = 'piket'");
$stmt->bind_param("si", $status_db, $absensi_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Status berhasil diupdate']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mengubah database']);
}
$stmt->close();
?>