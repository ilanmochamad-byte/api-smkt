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

// Terjemahkan kode galat unggah PHP jadi kalimat yang bisa ditindaklanjuti guru.
// Dibedakan dari "tidak ada foto" dengan sengaja: dalam kasus ini guru melihat
// fotonya terlampir di layar, jadi pesan "wajib diupload" hanya membingungkan.
function pesanGagalUnggah($kode) {
    switch ($kode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "Foto bukti gagal diunggah: ukuran berkasnya terlalu besar.";
        case UPLOAD_ERR_PARTIAL:
            return "Foto bukti gagal diunggah: pengiriman terputus. Silakan coba lagi.";
        case UPLOAD_ERR_NO_FILE:
            return "Foto bukti wajib diupload.";
        default:
            return "Foto bukti gagal diunggah. Silakan coba lagi atau hubungi admin.";
    }
}

// $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
// if ($conn->connect_error) {
//     http_response_code(500);
//     echo json_encode(['error' => true, 'message' => "Koneksi database gagal."]);
//     exit();
// }

try {
    $guru_id = (int)($_POST['guru_id'] ?? 0);
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

    // Foto bukti wajib. Blok ini dulunya tidak punya else, sehingga galat
    // seperti UPLOAD_ERR_INI_SIZE lewat diam-diam dan barisnya tetap tersimpan
    // dengan foto_bukti kosong. proses_absen_mengajar.php dan
    // proses_absen_sederhana.php sudah melempar Exception dalam keadaan sama.
    if (!isset($_FILES['foto_bukti'])) {
        throw new Exception("Foto bukti wajib diupload.");
    }
    if ($_FILES['foto_bukti']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(pesanGagalUnggah($_FILES['foto_bukti']['error']));
    }

    $foto_path_db = null;
    $foto_absolute = null;

    $target_dir_relative = "uploads/";
    $target_dir_absolute = $base_upload_path_absolute . $target_dir_relative;

    if (!file_exists($target_dir_absolute)) mkdir($target_dir_absolute, 0775, true);

    $info = @getimagesize($_FILES["foto_bukti"]["tmp_name"]);
    $ekstensi_izin = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png',
                      IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if ($info === false || !isset($ekstensi_izin[$info[2]])) {
        throw new Exception("Foto bukti harus berupa gambar JPG, PNG, WEBP, atau GIF.");
    }
    $file_extension = $ekstensi_izin[$info[2]];
    $file_name = "bk-jurnal-" . $guru_id . "-" . time() . "." . $file_extension;

    if (move_uploaded_file($_FILES["foto_bukti"]["tmp_name"], $target_dir_absolute . $file_name)) {
        $foto_path_db = $target_dir_relative . $file_name;
        $foto_absolute = $target_dir_absolute . $file_name;
    } else {
        throw new Exception("Gagal mengunggah foto bukti.");
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

    // Foto sudah pindah ke uploads/ sebelum transaksi dibuka, jadi rollback
    // tidak menyentuhnya. Kalau INSERT gagal, berkas itu tidak dirujuk baris
    // mana pun — hapus, jangan biarkan menumpuk di folder yang sudah 1,8 GB.
    if (!empty($foto_absolute) && is_file($foto_absolute)) {
        @unlink($foto_absolute);
    }

    http_response_code(400);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
$conn->close();
?>