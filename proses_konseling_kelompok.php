<?php
// proses_konseling_kelompok.php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200); exit();
}

require_once 'includes/db.php';

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $jurnal_bk_id = $data['jurnal_bk_id'] ?? 0;
    
    // Step 1: Identitas Kegiatan
    $nama_kegiatan = $data['nama_kegiatan'] ?? '';
    $tanggal_pelaksanaan = $data['tanggal_pelaksanaan'] ?? '';
    $waktu = $data['waktu'] ?? '';
    $tempat = $data['tempat'] ?? '';
    $latar_belakang = $data['latar_belakang'] ?? '';
    
    // Step 2: Anggota & Tujuan
    $anggota_kelompok = $data['anggota_kelompok'] ?? '';
    $tujuan_umum = $data['tujuan_umum'] ?? '';
    $tujuan_khusus = $data['tujuan_khusus'] ?? '';
    
    // Step 3: Tahapan & Proses
    $tahap_pembentukan = $data['tahap_pembentukan'] ?? '';
    $tahap_peralihan = $data['tahap_peralihan'] ?? '';
    $tahap_kegiatan = $data['tahap_kegiatan'] ?? '';
    $tahap_pengakhiran = $data['tahap_pengakhiran'] ?? '';
    $proses_pelaksanaan = $data['proses_pelaksanaan'] ?? '';
    
    // Step 4: Hasil & Evaluasi
    $hasil_kegiatan = $data['hasil_kegiatan'] ?? '';
    $evaluasi_proses = $data['evaluasi_proses'] ?? '';
    $evaluasi_hasil = $data['evaluasi_hasil'] ?? '';
    $rtl_konselor = $data['rtl_konselor'] ?? '';

    $sql = "INSERT INTO konseling_kelompok 
            (jurnal_bk_id, nama_kegiatan, tanggal_pelaksanaan, waktu, tempat, anggota_kelompok, latar_belakang, 
             tujuan_umum, tujuan_khusus, tahap_pembentukan, tahap_peralihan, tahap_kegiatan, tahap_pengakhiran, 
             proses_pelaksanaan, hasil_kegiatan, evaluasi_proses, evaluasi_hasil, rtl_konselor) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssssssssssssss", 
        $jurnal_bk_id, $nama_kegiatan, $tanggal_pelaksanaan, $waktu, $tempat, $anggota_kelompok, $latar_belakang,
        $tujuan_umum, $tujuan_khusus, $tahap_pembentukan, $tahap_peralihan, $tahap_kegiatan, $tahap_pengakhiran,
        $proses_pelaksanaan, $hasil_kegiatan, $evaluasi_proses, $evaluasi_hasil, $rtl_konselor
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Gagal menyimpan data konseling kelompok: " . $stmt->error);
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Laporan Konseling Kelompok berhasil disimpan.']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>