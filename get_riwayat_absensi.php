<?php
// --- BARIS BARU UNTUK MENAMPILKAN ERROR ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// --- INFORMASI DATABASE ANDA ---
require_once 'includes/db.php';
// -----------------------------------------

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    $guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
    $tipe = isset($_GET['tipe']) ? $_GET['tipe'] : 'mengajar';
    $bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
    $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

    if ($guru_id === 0) {
        throw new Exception("Parameter guru_id dibutuhkan.");
    }

    // Adaptasi dari query riwayat di index-guru.php
    // PERBAIKAN: Menambahkan kondisi status_jadwal = 'Aktif' pada LEFT JOIN
    $sql = "SELECT 
                a.waktu_absensi, a.tipe_absensi, a.status,
                COALESCE(
                    CONCAT(jm.mata_pelajaran, ' - Kelas ', jm.kelas), 
                    CONCAT('Piket Sesi ', jp.sesi), 
                    je.nama_ekskul
                ) as keterangan_jadwal
            FROM absensi a
            LEFT JOIN jadwal_mengajar jm ON a.jadwal_id = jm.id AND a.tipe_absensi = 'mengajar' AND jm.status_jadwal = 'Aktif'
            LEFT JOIN jadwal_piket jp ON a.jadwal_id = jp.id AND a.tipe_absensi = 'piket' AND jp.status_jadwal = 'Aktif'
            LEFT JOIN jadwal_ekskul je ON a.jadwal_id = je.id AND a.tipe_absensi = 'ekskul' AND je.status_jadwal = 'Aktif'
            WHERE a.guru_id = ? AND a.tipe_absensi = ? AND MONTH(a.waktu_absensi) = ? AND YEAR(a.waktu_absensi) = ?
            ORDER BY a.waktu_absensi DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL Prepare Error: " . $conn->error);
    }
    
    $stmt->bind_param("isii", $guru_id, $tipe, $bulan, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    http_response_code(200);
    echo json_encode(['data' => $data]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    // --- JIKA TERJADI ERROR, TANGKAP DAN KIRIM SEBAGAI JSON ---
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
    // --------------------------------------------------------
}
?>