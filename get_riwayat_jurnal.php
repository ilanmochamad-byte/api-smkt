<?php
// get_riwayat_jurnal.php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'includes/db.php';

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi gagal: " . $conn->connect_error);
    }

    // 1. Ambil Parameter Filter dari Frontend
    $guru_id = $_GET['guru_id'] ?? 0;
    
    // Default ke bulan & tahun saat ini jika tidak dikirim
    $bulan   = $_GET['bulan'] ?? date('n'); 
    $tahun   = $_GET['tahun'] ?? date('Y');

    // 2. Query SQL Gabungan (Mengajar & BK)
    // Menggunakan LEFT JOIN agar data BK (yang tidak punya jadwal_mengajar) tetap masuk
    // Menggunakan COALESCE untuk memetakan kolom BK agar terbaca sama seperti Jurnal Mengajar di frontend
    $sql = "SELECT 
                a.id,
                a.waktu_absensi,
                a.tipe_absensi,
                COALESCE(a.materi_pokok, jbk.topik_tema, '-') AS materi_pokok,
                COALESCE(a.tujuan_pembelajaran, jbk.fungsi_layanan, '-') AS tujuan_pembelajaran,
                COALESCE(a.kegiatan_pembelajaran, jbk.materi_layanan, '-') AS kegiatan_pembelajaran,
                COALESCE(a.catatan_refleksi, 'Lihat form konseling lanjutan') AS catatan_refleksi,
                COALESCE(a.penilaian_evaluasi, jbk.metode_teknik, '-') AS penilaian_evaluasi,
                COALESCE(jm.mata_pelajaran, jbk.komponen_layanan, 'Layanan BK') AS mata_pelajaran, 
                COALESCE(jm.kelas, jbk.sasaran_layanan, '-') AS kelas
            FROM absensi a
            LEFT JOIN jadwal_mengajar jm ON a.jadwal_id = jm.id AND a.tipe_absensi = 'mengajar'
            LEFT JOIN jurnal_bk jbk ON a.id = jbk.absensi_guru_id AND a.tipe_absensi = 'bimbingan'
            WHERE a.guru_id = ? 
              AND a.tipe_absensi IN ('mengajar', 'bimbingan')
              AND MONTH(a.waktu_absensi) = ? 
              AND YEAR(a.waktu_absensi) = ?
            ORDER BY a.waktu_absensi DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Gagal Prepare SQL: " . $conn->error);
    }

    // Bind parameter: i (guru_id), i (bulan), i (tahun)
    $stmt->bind_param("iii", $guru_id, $bulan, $tahun);
    
    if (!$stmt->execute()) {
        throw new Exception("Gagal Execute SQL: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Pembersihan (Null Safety) untuk memastikan React Native tidak crash
        $row['materi_pokok'] = $row['materi_pokok'] ?? '-';
        $row['tujuan_pembelajaran'] = $row['tujuan_pembelajaran'] ?? '-';
        $row['kegiatan_pembelajaran'] = $row['kegiatan_pembelajaran'] ?? '-';
        $row['catatan_refleksi'] = $row['catatan_refleksi'] ?? '-';
        $row['penilaian_evaluasi'] = $row['penilaian_evaluasi'] ?? '-';
        
        $data[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
?>