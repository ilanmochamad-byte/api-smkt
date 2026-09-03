<?php
// proses_absen_sederhana.php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- INFORMASI DATABASE ANDA ---
require_once 'includes/db.php';
// -----------------------------------------

// --- PATH UPLOAD (Sesuaikan jika perlu) ---
// Ini adalah path absolut di server Anda ke folder 'classync'
$base_upload_path_absolute = "/DATA/k1807225/public_html/smkt.alhasan.co.id/classync/"; 
// ----------------------------------------------------

$conn = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }
    
    $guru_id = $_POST['guru_id'] ?? 0;
    $jadwal_id = $_POST['jadwal_id'] ?? 0;
    $tipe_absensi = $_POST['tipe_absensi'] ?? '';
    // BARU: Ambil data latitude dan longitude
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    if ($guru_id == 0 || $jadwal_id == 0 || empty($tipe_absensi)) {
        http_response_code(400);
        throw new Exception("Data tidak lengkap (guru/jadwal/tipe).");
    }

    // Cek Absen Ganda
    $stmt_cek = $conn->prepare("SELECT id FROM absensi WHERE guru_id = ? AND jadwal_id = ? AND tipe_absensi = ? AND DATE(waktu_absensi) = CURDATE()");
    $stmt_cek->bind_param("iis", $guru_id, $jadwal_id, $tipe_absensi);
    $stmt_cek->execute();
    if ($stmt_cek->get_result()->num_rows > 0) {
        http_response_code(409); // 409 Conflict
        throw new Exception("Anda sudah absen untuk jadwal ini hari ini.");
    }
    $stmt_cek->close();

    // Proses Upload Foto
    $foto_path_db = null;
    if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] == 0) {
        // PERBAIKAN: Path folder upload spesifik untuk absensi
        $save_path_relative = "uploads/absensi/"; 
        $target_dir_absolute = $base_upload_path_absolute . $save_path_relative;
        
        if (!is_dir($target_dir_absolute)) { mkdir($target_dir_absolute, 0775, true); }
        
        $file_extension = pathinfo($_FILES["foto_bukti"]["name"], PATHINFO_EXTENSION);
        $file_name = "absen-" . $guru_id . "-" . time() . "." . $file_extension;
        
        if (move_uploaded_file($_FILES["foto_bukti"]["tmp_name"], $target_dir_absolute . $file_name)) {
            $foto_path_db = $save_path_relative . $file_name;
        } else {
            throw new Exception("Gagal memindahkan file foto.");
        }
    } else {
        http_response_code(400);
        throw new Exception("Foto bukti wajib diupload.");
    }

// Jika tipe absen adalah piket, maka berikan status Pending agar menunggu Kepala Sekolah.
// Selain piket (seperti ekskul), statusnya langsung Hadir.
$status = ($tipe_absensi === 'piket') ? 'Pending' : 'Hadir';

    // BARU: Query INSERT diperbarui dengan kolom latitude dan longitude
    $sql = "INSERT INTO absensi (guru_id, jadwal_id, tipe_absensi, waktu_absensi, status, foto_bukti, latitude, longitude) 
            VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)";
            
    $stmt_guru = $conn->prepare($sql);
    // BARU: bind_param diperbarui (iissdd)
    $stmt_guru->bind_param("iisssdd", $guru_id, $jadwal_id, $tipe_absensi, $status, $foto_path_db, $latitude, $longitude);
    
    $stmt_guru->execute();
    $stmt_guru->close();

    http_response_code(201); // 201 Created
    $pesan_sukses = ($tipe_absensi === 'piket') 
    ? 'Absen piket berhasil dikirim dan menunggu persetujuan Kepala Sekolah.' 
    : 'Absensi berhasil disimpan.';
    echo json_encode(['status' => 'success', 'message' => $pesan_sukses]);
    // echo json_encode(['status' => 'success', 'message' => 'Absensi berhasil disimpan.']);

} catch (Exception $e) {
    if (http_response_code() === 200 || http_response_code() === 201) {
        http_response_code(500); // Set 500 jika belum di-set (misal oleh 400/409)
    }
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>