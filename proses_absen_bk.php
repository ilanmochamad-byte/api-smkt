<?php
// proses_absen_bk.php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'includes/db.php';
$base_upload_path_absolute = "/DATA/k1807225/public_html/smkt.alhasan.co.id/classync/"; 

// $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
// if ($conn->connect_error) {
//     http_response_code(500);
//     echo json_encode(['error' => true, 'message' => "Koneksi database gagal."]);
//     exit();
// }

try {
    $guru_id = $_POST['guru_id'] ?? 0;
    $latitude = $_POST['latitude'] ?? 0;
    $longitude = $_POST['longitude'] ?? 0;

    // 10 Poin Isian Baru
    $komponen_layanan = $_POST['komponen_layanan'] ?? '';
    $bidang_layanan = $_POST['bidang_layanan'] ?? '';
    $topik_tema = $_POST['topik_tema'] ?? '';
    $fungsi_layanan = $_POST['fungsi_layanan'] ?? '';
    $sasaran_layanan = $_POST['sasaran_layanan'] ?? '';
    $materi_layanan = $_POST['materi_layanan'] ?? '';
    $waktu = $_POST['waktu'] ?? '';
    $sumber = $_POST['sumber'] ?? '';
    $metode_teknik = $_POST['metode_teknik'] ?? '';
    $media_alat = $_POST['media_alat'] ?? '';

    if (empty($guru_id) || empty($topik_tema) || empty($sasaran_layanan)) {
        throw new Exception("Data rencana bimbingan tidak lengkap. Harap isi form dengan benar.");
    }

    $foto_path_db = null;
    if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] === UPLOAD_ERR_OK) {
        $target_dir_relative = "uploads/";
        $target_dir_absolute = $base_upload_path_absolute . $target_dir_relative;
        
        if (!file_exists($target_dir_absolute)) mkdir($target_dir_absolute, 0775, true);
        
        $file_extension = pathinfo($_FILES["foto_bukti"]["name"], PATHINFO_EXTENSION);
        $file_name = "bk-jurnal-" . $guru_id . "-" . time() . "." . $file_extension;
        
        if (move_uploaded_file($_FILES["foto_bukti"]["tmp_name"], $target_dir_absolute . $file_name)) {
            $foto_path_db = $target_dir_relative . $file_name;
        } else {
            throw new Exception("Gagal mengunggah foto bukti.");
        }
    }

    $conn->begin_transaction();

    // 1. Simpan Absensi
    $stmt_absen = $conn->prepare("INSERT INTO absensi (guru_id, jadwal_id, tipe_absensi, waktu_absensi, status, foto_bukti, latitude, longitude) VALUES (?, 0, 'bimbingan', NOW(), 'Hadir', ?, ?, ?)");
    $stmt_absen->bind_param("isdd", $guru_id, $foto_path_db, $latitude, $longitude);
    if (!$stmt_absen->execute()) throw new Exception("Gagal menyimpan absensi utama.");
    
    $absensi_guru_id = $conn->insert_id;
    $stmt_absen->close();

    // 2. Simpan 10 Poin Jurnal BK
    $stmt_bk = $conn->prepare("INSERT INTO jurnal_bk (absensi_guru_id, komponen_layanan, bidang_layanan, topik_tema, fungsi_layanan, sasaran_layanan, materi_layanan, waktu, sumber, metode_teknik, media_alat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_bk->bind_param("issssssssss", $absensi_guru_id, $komponen_layanan, $bidang_layanan, $topik_tema, $fungsi_layanan, $sasaran_layanan, $materi_layanan, $waktu, $sumber, $metode_teknik, $media_alat);
    if (!$stmt_bk->execute()) throw new Exception("Gagal menyimpan detail jurnal BK.");
    $stmt_bk->close();

    $conn->commit();
    http_response_code(201);
    echo json_encode(['status' => 'success', 'message' => 'Layanan BK berhasil disimpan.']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
$conn->close();
?>