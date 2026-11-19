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

// Hanya POST. Sebelumnya GET, HEAD, atau POST kosong lolos ke INSERT dengan
// jurnal_bk_id 0 dan semua isian kosong: sensus 19 November 2026 menemukan
// 33 baris seperti itu di sini (dan 25 di konseling_individu), semuanya dari
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

    // Laporan yang seluruh isiannya kosong tidak disimpan.
    $isian = [$nama_kegiatan, $tanggal_pelaksanaan, $waktu, $tempat, $anggota_kelompok, $latar_belakang,
        $tujuan_umum, $tujuan_khusus, $tahap_pembentukan, $tahap_peralihan, $tahap_kegiatan, $tahap_pengakhiran,
        $proses_pelaksanaan, $hasil_kegiatan, $evaluasi_proses, $evaluasi_hasil, $rtl_konselor];
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
    // individu mengunci baris yang sama.
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
    $conn->commit();
    $dalam_transaksi = false;

    echo json_encode(['status' => 'success', 'message' => 'Laporan Konseling Kelompok berhasil disimpan.']);

} catch (Exception $e) {
    if (!empty($dalam_transaksi)) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>