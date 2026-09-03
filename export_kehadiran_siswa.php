<?php
// export_kehadiran_siswa.php
ini_set('display_errors', 1); error_reporting(E_ALL);
header("Content-Type: application/json; charset=UTF-8"); header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS"); // Tambahkan GET dan OPTIONS
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Database Connection Details ---
require_once 'includes/db.php';
// ------------------------------------

$conn = null; // Initialize connection variable

try {
    // Establish database connection
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        // Throw an exception if connection fails
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    // <-- HAPUS 'try {' YANG ADA DI SINI

    $guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
    $mapel = isset($_GET['mata_pelajaran']) ? $_GET['mata_pelajaran'] : '';
    $kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
    $semester = isset($_GET['semester']) ? $_GET['semester'] : '';
    $tahun_ajaran = isset($_GET['tahun_ajaran']) ? $_GET['tahun_ajaran'] : '';
    
    $months = [];
    if ($semester === 'Ganjil') $months = [7, 8, 9, 10, 11, 12];
    else if ($semester === 'Genap') $months = [1, 2, 3, 4, 5, 6];
    $month_list = implode(',', $months);

    if ($guru_id === 0 || empty($mapel) || empty($kelas) || empty($semester) || empty($tahun_ajaran) || empty($months)) {
        http_response_code(400); // Bad Request
        throw new Exception("Filter tidak lengkap.");
    }

    // Ambil daftar siswa di kelas tersebut
    $sql_siswa = "SELECT id, nisn, nama_siswa FROM siswa WHERE kelas = ? ORDER BY nama_siswa ASC";
    $stmt_siswa = $conn->prepare($sql_siswa);
    $stmt_siswa->bind_param("s", $kelas);
    $stmt_siswa->execute();
    $result_siswa = $stmt_siswa->get_result();
    $siswa_list = [];
    while ($row = $result_siswa->fetch_assoc()) {
         $siswa_list[$row['id']] = [
             'nisn' => $row['nisn'],
             'nama_siswa' => $row['nama_siswa'],
             'total' => 0, 'hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpa' => 0
         ];
    }
    $stmt_siswa->close();

    // Ambil data absensi detail
    $sql_absensi = "SELECT das.siswa_id, das.status_kehadiran
                    FROM detail_absensi_siswa das
                    JOIN absensi a ON das.absensi_guru_id = a.id
                    JOIN jadwal_mengajar jm ON a.jadwal_id = jm.id AND a.tipe_absensi = 'mengajar'
                    WHERE a.guru_id = ?
                      AND jm.mata_pelajaran = ?
                      AND jm.kelas = ?
                      AND YEAR(a.waktu_absensi) = ?
                      AND MONTH(a.waktu_absensi) IN ($month_list)";

    list($tahun1, $tahun2) = explode('/', $tahun_ajaran);
    $year_filter = ($semester === 'Ganjil') ? (int)$tahun1 : (int)$tahun2;

    $stmt_absensi = $conn->prepare($sql_absensi);
    $stmt_absensi->bind_param("issi", $guru_id, $mapel, $kelas, $year_filter);
    $stmt_absensi->execute();
    $result_absensi = $stmt_absensi->get_result();

    while ($row = $result_absensi->fetch_assoc()) {
        $siswa_id = $row['siswa_id'];
        if (isset($siswa_list[$siswa_id])) {
            $siswa_list[$siswa_id]['total']++;
            $status = strtolower($row['status_kehadiran']);
            if (isset($siswa_list[$siswa_id][$status])) {
                $siswa_list[$siswa_id][$status]++;
            }
        }
    }
    $stmt_absensi->close();

    $final_data = array_values($siswa_list);

    http_response_code(200);
    echo json_encode(['data' => $final_data]);

} catch (Exception $e) { 
    // Tangani error dari koneksi atau logika
    if (http_response_code() === 200) {
        http_response_code(500); // Internal Server Error
    }
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally { 
    // Selalu tutup koneksi
    if ($conn) $conn->close(); 
}
?>