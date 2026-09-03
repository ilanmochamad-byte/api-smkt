<?php
// export_rekap_bulanan_siswa.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'includes/db.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

try {
    $kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
    $bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 0;
    $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
    
    // Ambil jam masuk dari pengaturan untuk menghitung keterlambatan
    $pengaturan_query = $conn->query("SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'jam_masuk'");
    $jam_masuk_sekolah = $pengaturan_query->fetch_assoc()['nilai_pengaturan'] ?? '07:30:00';

    if (empty($kelas) || $bulan == 0 || $tahun == 0) {
        throw new Exception("Filter kelas, bulan, dan tahun wajib diisi.");
    }

    // Query Agregasi: Mengambil semua siswa di kelas tersebut dan menghitung status absennya
    $sql = "
        SELECT 
            s.nisn, 
            s.nama_siswa,
            COUNT(CASE WHEN a.waktu_masuk IS NOT NULL THEN 1 END) as total_hadir,
            COUNT(CASE WHEN TIME(a.waktu_masuk) > '$jam_masuk_sekolah' THEN 1 END) as total_terlambat,
            -- Asumsi: Jika ada kolom status (S/I/A) di tabel absensi_siswa atau tabel izin terpisah.
            -- Jika sistem Anda mencatat S/I/A di tabel absensi_siswa (misal kolom 'status_kehadiran' atau similar):
            -- Sesuaikan logika COUNT di bawah ini dengan struktur tabel Anda yang sebenarnya.
            -- Contoh standar jika ada kolom 'keterangan':
            COUNT(CASE WHEN a.keterangan = 'Sakit' THEN 1 END) as total_sakit,
            COUNT(CASE WHEN a.keterangan = 'Izin' THEN 1 END) as total_izin,
            COUNT(CASE WHEN a.keterangan = 'Alpa' THEN 1 END) as total_alpa
        FROM siswa s
        LEFT JOIN absensi_siswa a ON s.id = a.siswa_id 
            AND MONTH(a.tanggal) = ? 
            AND YEAR(a.tanggal) = ?
        WHERE s.kelas = ?
        GROUP BY s.id
        ORDER BY s.nama_siswa ASC
    ";

    // CATATAN PENTING: Jika data Sakit/Izin/Alpa disimpan di tabel lain (misal 'perizinan'),
    // Anda perlu melakukan JOIN dengan tabel tersebut atau menyesuaikan query di atas.
    // Query di atas mengasumsikan semua data ada di 'absensi_siswa'.

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $bulan, $tahun, $kelas);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'periode' => date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun))
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>