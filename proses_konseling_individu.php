<?php
// proses_konseling_individu.php
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
    
    // Step 1
    $nama_konseli = $data['nama_konseli'] ?? '';
    $kelas_konseli = $data['kelas_konseli'] ?? '';
    $tanggal_konseling = $data['tanggal_konseling'] ?? '';
    $jam_konseling = $data['jam_konseling'] ?? '';
    $latar_belakang = $data['latar_belakang'] ?? '';
    $deskripsi_masalah = $data['deskripsi_masalah'] ?? '';
    
    // Step 2
    $asesmen_pribadi = $data['asesmen_pribadi'] ?? '';
    $asesmen_sosial = $data['asesmen_sosial'] ?? '';
    $asesmen_belajar = $data['asesmen_belajar'] ?? '';
    $asesmen_karir = $data['asesmen_karir'] ?? '';
    $analisis_masalah = $data['analisis_masalah'] ?? '';
    
    // Step 3
    $tujuan_pendek = $data['tujuan_pendek'] ?? '';
    $tujuan_panjang = $data['tujuan_panjang'] ?? '';
    $pendekatan_teknik = $data['pendekatan_teknik'] ?? '';
    
    // Step 4
    $proses_awal = $data['proses_awal'] ?? '';
    $proses_inti = $data['proses_inti'] ?? '';
    $proses_akhir = $data['proses_akhir'] ?? '';
    $hasil_konseling = $data['hasil_konseling'] ?? '';
    $evaluasi_proses = $data['evaluasi_proses'] ?? '';
    $evaluasi_hasil = $data['evaluasi_hasil'] ?? '';
    $rtl_konselor = $data['rtl_konselor'] ?? '';

    $sql = "INSERT INTO konseling_individu 
            (jurnal_bk_id, nama_konseli, kelas_konseli, tanggal_konseling, jam_konseling, latar_belakang, deskripsi_masalah, 
            asesmen_pribadi, asesmen_sosial, asesmen_belajar, asesmen_karir, analisis_masalah, 
            tujuan_pendek, tujuan_panjang, pendekatan_teknik, 
            proses_awal, proses_inti, proses_akhir, hasil_konseling, evaluasi_proses, evaluasi_hasil, rtl_konselor) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssssssssssssssssss", 
        $jurnal_bk_id, $nama_konseli, $kelas_konseli, $tanggal_konseling, $jam_konseling, $latar_belakang, $deskripsi_masalah,
        $asesmen_pribadi, $asesmen_sosial, $asesmen_belajar, $asesmen_karir, $analisis_masalah,
        $tujuan_pendek, $tujuan_panjang, $pendekatan_teknik,
        $proses_awal, $proses_inti, $proses_akhir, $hasil_konseling, $evaluasi_proses, $evaluasi_hasil, $rtl_konselor
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Gagal menyimpan data konseling: " . $stmt->error);
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Laporan Konseling Individu berhasil disimpan.']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>