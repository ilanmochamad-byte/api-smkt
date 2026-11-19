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

// Hanya POST. Sebelumnya GET, HEAD, atau POST kosong lolos ke INSERT dengan
// jurnal_bk_id 0 dan semua isian kosong: sensus 19 November 2026 menemukan
// 25 baris seperti itu di sini (dan 33 di konseling_kelompok), semuanya dari
// pemindai atau URL yang dibuka, bukan dari aplikasi. Semua versi aplikasi
// mengirim POST JSON dan hanya membaca `status` serta `message`.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST, OPTIONS');
    echo json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan.']);
    exit();
}

require_once 'includes/db.php';
require_once __DIR__ . '/includes/auth.php';

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // jurnal_bk_id berisi absensi.id — riwayat_jurnal.tsx mengirim item.id
    // dari get_riwayat_jurnal.php (string dari parameter rute). Body kosong,
    // bukan JSON, 0, atau bukan bilangan ditolak sebelum menyentuh tabel.
    $jurnal_bk_id = is_array($data)
        ? filter_var($data['jurnal_bk_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    if ($jurnal_bk_id === false) {
        throw new Exception("Jurnal BK tidak ditemukan.");
    }

    // Fase B (inventaris K4): dengan token, absensi itu harus milik pemilik
    // token — diperiksa di dalam transaksi di bawah. Dipanggil di sini, di
    // luar transaksi, supaya catatan_autentikasi tidak ikut di-rollback.
    $guru_token = guru_id_dari_token($conn);

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

    // Laporan yang seluruh isiannya kosong tidak disimpan.
    $isian = [$nama_konseli, $kelas_konseli, $tanggal_konseling, $jam_konseling, $latar_belakang, $deskripsi_masalah,
        $asesmen_pribadi, $asesmen_sosial, $asesmen_belajar, $asesmen_karir, $analisis_masalah,
        $tujuan_pendek, $tujuan_panjang, $pendekatan_teknik,
        $proses_awal, $proses_inti, $proses_akhir, $hasil_konseling, $evaluasi_proses, $evaluasi_hasil, $rtl_konselor];
    $ada_isi = false;
    foreach ($isian as $nilai) {
        if (is_scalar($nilai) && trim((string)$nilai) !== '') {
            $ada_isi = true;
            break;
        }
    }
    if (!$ada_isi) {
        throw new Exception("Laporan masih kosong. Isi laporan sebelum menyimpan.");
    }

    // Satu jurnal hanya boleh punya satu laporan, individu ATAU kelompok
    // (keputusan kepala sekolah, 19 November 2026). Baris absensi dikunci
    // lewat PK supaya dua kiriman serentak — ketukan ganda, atau laporan
    // individu dan kelompok bersamaan — tidak sama-sama lolos; endpoint
    // kelompok mengunci baris yang sama.
    $conn->begin_transaction();
    $dalam_transaksi = true;

    $stmt_jurnal = $conn->prepare("SELECT guru_id FROM absensi WHERE id = ? AND tipe_absensi = 'bimbingan' FOR UPDATE");
    $stmt_jurnal->bind_param("i", $jurnal_bk_id);
    $stmt_jurnal->execute();
    $jurnal = $stmt_jurnal->get_result()->fetch_assoc();
    $stmt_jurnal->close();
    if (!$jurnal || ($guru_token !== null && (int)$jurnal['guru_id'] !== $guru_token)) {
        throw new Exception("Jurnal BK tidak ditemukan.");
    }

    $stmt_ada = $conn->prepare(
        "SELECT (SELECT COUNT(*) FROM konseling_individu WHERE jurnal_bk_id = ?) AS individu,
                (SELECT COUNT(*) FROM konseling_kelompok WHERE jurnal_bk_id = ?) AS kelompok");
    $stmt_ada->bind_param("ii", $jurnal_bk_id, $jurnal_bk_id);
    $stmt_ada->execute();
    $ada = $stmt_ada->get_result()->fetch_assoc();
    $stmt_ada->close();
    if ((int)$ada['individu'] > 0) {
        throw new Exception("Jurnal ini sudah punya laporan konseling individu. Satu jurnal hanya boleh punya satu laporan.");
    }
    if ((int)$ada['kelompok'] > 0) {
        throw new Exception("Jurnal ini sudah punya laporan konseling kelompok. Satu jurnal hanya boleh punya satu laporan.");
    }

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
    $conn->commit();
    $dalam_transaksi = false;

    echo json_encode(['status' => 'success', 'message' => 'Laporan Konseling Individu berhasil disimpan.']);

} catch (Exception $e) {
    if (!empty($dalam_transaksi)) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>