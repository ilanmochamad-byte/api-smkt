<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'includes/db.php';

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    $siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;
    if ($siswa_id === 0) throw new Exception("Parameter siswa_id wajib diisi.");

    // 1. Ambil Data Identitas Utama (Tabel Siswa)
    $stmt_siswa = $conn->prepare("SELECT nisn, nama_siswa, jenis_kelamin, kelas, kontak_ortu FROM siswa WHERE id = ?");
    $stmt_siswa->bind_param("i", $siswa_id);
    $stmt_siswa->execute();
    $siswa = $stmt_siswa->get_result()->fetch_assoc();
    $stmt_siswa->close();

    if (!$siswa) throw new Exception("Data siswa tidak ditemukan.");

    // 2. Ambil Profil BK
    $stmt_profil = $conn->prepare("SELECT * FROM profil_bk_siswa WHERE siswa_id = ?");
    $stmt_profil->bind_param("i", $siswa_id);
    $stmt_profil->execute();
    $profil_bk = $stmt_profil->get_result()->fetch_assoc() ?: null;
    $stmt_profil->close();

    // 3. Ambil Riwayat Layanan BK (Deteksi Nama ATAU Kelas)
    $nama_siswa = $siswa['nama_siswa'];
    $kelas_siswa = $siswa['kelas']; // <-- TAMBAHAN: Mengambil nama kelas siswa

    // Query diperbarui dengan OR j.sasaran_layanan LIKE ? untuk mencocokkan kelas
   $sql_riwayat = "SELECT 
                        a.waktu_absensi, 
                        j.komponen_layanan, 
                        j.topik_tema, 
                        j.materi_layanan, 
                        g.nama_guru,
                        COALESCE(ki.id, kk.id) AS konseling_id,
                        COALESCE(ki.hasil_konseling, kk.hasil_kegiatan) AS hasil_konseling,
                        COALESCE(ki.rtl_konselor, kk.rtl_konselor) AS rtl_konselor
                    FROM jurnal_bk j 
                    JOIN absensi a ON j.absensi_guru_id = a.id
                    JOIN guru g ON a.guru_id = g.id
                    LEFT JOIN konseling_individu ki ON ki.jurnal_bk_id = a.id 
                    LEFT JOIN konseling_kelompok kk ON kk.jurnal_bk_id = a.id
                    WHERE j.sasaran_layanan LIKE ? 
                       OR j.sasaran_layanan LIKE ? 
                       OR ki.nama_konseli LIKE ?
                       OR kk.anggota_kelompok LIKE ?
                    ORDER BY a.waktu_absensi DESC";
                    
    $stmt_riwayat = $conn->prepare($sql_riwayat);
    
    $search_nama = "%" . $nama_siswa . "%";
    $search_kelas = "%" . $kelas_siswa . "%";
    
    // Bind 3 parameter: (1) Nama di jurnal, (2) Kelas di jurnal, (3) Nama di form konseling
    $stmt_riwayat->bind_param("ssss", $search_nama, $search_kelas, $search_nama, $search_nama);
    $stmt_riwayat->execute();
    $result_riwayat = $stmt_riwayat->get_result();
    
    $riwayat_layanan = [];
    while($row = $result_riwayat->fetch_assoc()) {
        $riwayat_layanan[] = $row;
    }
    $stmt_riwayat->close();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'identitas' => $siswa,
            'profil_bk' => $profil_bk,
            'riwayat' => $riwayat_layanan
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'error' => true, 
        'message' => $e->getMessage()
    ]);
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>