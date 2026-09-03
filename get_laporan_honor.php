<?php
require_once 'includes/db.php';
require_once 'keuangan_helper.php';

try {
    $filter_bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
    $filter_tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

    // Ambil Tarif Dinamis dari Helper
    $tarif = getPengaturanHonor($conn);

    $guru_list = $conn->query("SELECT id, nama_guru FROM guru ORDER BY nama_guru ASC");
    $data = [];

    // Looping setiap guru, lalu tembak fungsi "Otak Mesin" kita
    while($guru = $guru_list->fetch_assoc()) {
        $guru_id = $guru['id'];
        
        // Panggil fungsi perhitungan dari keuangan_helper.php
        $rincian_honor = hitungHonorBulan($conn, $guru_id, $filter_bulan, $filter_tahun, $tarif);
        
        // Tambahkan nama guru ke dalam array hasil
        $rincian_honor['nama_guru'] = $guru['nama_guru'];
        
        // Masukkan ke wadah utama
        $data[] = $rincian_honor;
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()]);
}
?>