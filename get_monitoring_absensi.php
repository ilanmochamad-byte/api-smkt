<?php
// get_monitoring_absensi.php
ini_set('display_errors', '0');
error_reporting(E_ALL);

// PERBAIKAN: SET ZONA WAKTU KE INDONESIA AGAR AKURAT
date_default_timezone_set('Asia/Jakarta');

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'includes/db.php';

// Koneksi $conn disediakan includes/db.php, lengkap dengan set_charset("utf8mb4").

try {
    // 1. Ambil Parameter Filter
    $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
    $kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    // 2. Ambil Pengaturan Jam Masuk/Pulang dari DB
    $pengaturan_query = $conn->query("SELECT nama_pengaturan, nilai_pengaturan FROM pengaturan WHERE nama_pengaturan IN ('jam_masuk','jam_pulang')");
    $pengaturan = [];
    while ($r = $pengaturan_query->fetch_assoc()) {
        $pengaturan[$r['nama_pengaturan']] = $r['nilai_pengaturan'];
    }
    $jam_masuk_sekolah = isset($pengaturan['jam_masuk']) ? $pengaturan['jam_masuk'] : '07:30:00';
    $jam_pulang_sekolah = isset($pengaturan['jam_pulang']) ? $pengaturan['jam_pulang'] : '13:50:00';

    // 3. Query Siswa yang SUDAH Absen (Hadir)
    $sql_hadir = "SELECT 
                    s.nisn, s.nama_siswa, s.kelas, 
                    a.waktu_masuk, a.waktu_pulang,
                    CASE WHEN TIME(a.waktu_masuk) <= ? THEN 'Tepat Waktu' ELSE 'Terlambat' END as status_masuk,
                    CASE WHEN a.waktu_pulang IS NOT NULL THEN 
                        (CASE WHEN TIME(a.waktu_pulang) >= ? THEN 'Pulang Normal' ELSE 'Pulang Cepat' END)
                    ELSE 'Belum Pulang' END as status_pulang
                  FROM absensi_siswa a
                  JOIN siswa s ON a.siswa_id = s.id
                  WHERE a.tanggal = ? AND a.waktu_masuk IS NOT NULL AND s.kelas != 'Lulus / Alumni'";

    // Urutan nilai harus mengikuti urutan tanda tanya di dalam query.
    $params_hadir = [$jam_masuk_sekolah, $jam_pulang_sekolah, $tanggal];

    if (!empty($kelas)) {
        $sql_hadir .= " AND s.kelas = ?";
        $params_hadir[] = $kelas;
    }
    if (!empty($search)) {
        $sql_hadir .= " AND (s.nama_siswa LIKE ? OR s.nisn LIKE ?)";
        $params_hadir[] = '%' . $search . '%';
        $params_hadir[] = '%' . $search . '%';
    }

    $sql_hadir .= " ORDER BY a.waktu_masuk DESC";

    $stmt_hadir = $conn->prepare($sql_hadir);
    if (!$stmt_hadir) {
        throw new Exception("Gagal menyiapkan query kehadiran.");
    }
    $stmt_hadir->bind_param(str_repeat('s', count($params_hadir)), ...$params_hadir);
    $stmt_hadir->execute();
    $result_hadir = $stmt_hadir->get_result();

    $data_hadir = [];
    while ($row = $result_hadir->fetch_assoc()) {
        // Format jam agar lebih rapi (HH:mm)
        $row['jam_masuk'] = date('H:i', strtotime($row['waktu_masuk']));
        $row['jam_pulang'] = $row['waktu_pulang'] ? date('H:i', strtotime($row['waktu_pulang'])) : '-';
        $data_hadir[] = $row;
    }
    $stmt_hadir->close();

    // 4. Query Siswa yang BELUM Absen
    $sql_belum = "SELECT s.nisn, s.nama_siswa, s.kelas 
                  FROM siswa s
                  LEFT JOIN absensi_siswa a ON s.id = a.siswa_id AND a.tanggal = ?
                  WHERE a.id IS NULL AND s.kelas != 'Lulus / Alumni'";

    $params_belum = [$tanggal];

    if (!empty($kelas)) {
        $sql_belum .= " AND s.kelas = ?";
        $params_belum[] = $kelas;
    }
    if (!empty($search)) {
        $sql_belum .= " AND (s.nama_siswa LIKE ? OR s.nisn LIKE ?)";
        $params_belum[] = '%' . $search . '%';
        $params_belum[] = '%' . $search . '%';
    }

    $sql_belum .= " ORDER BY s.kelas ASC, s.nama_siswa ASC";

    $stmt_belum = $conn->prepare($sql_belum);
    if (!$stmt_belum) {
        throw new Exception("Gagal menyiapkan query siswa belum absen.");
    }
    $stmt_belum->bind_param(str_repeat('s', count($params_belum)), ...$params_belum);
    $stmt_belum->execute();
    $result_belum = $stmt_belum->get_result();

    $data_belum = [];
    while ($row = $result_belum->fetch_assoc()) {
        $data_belum[] = $row;
    }
    $stmt_belum->close();

    echo json_encode([
        'status' => 'success',
        'data_hadir' => $data_hadir,
        'data_belum' => $data_belum,
        'statistik' => [
            'hadir' => count($data_hadir),
            'belum' => count($data_belum)
        ]
    ]);

} catch (Exception $e) {
    error_log('get_monitoring_absensi: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => 'Terjadi gangguan pada server.']);
} finally {
    if ($conn) $conn->close();
}
?>