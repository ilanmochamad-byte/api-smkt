<?php
// get_jadwal_harian.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'includes/db.php';

$conn = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal.");
    }

    $guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
    if ($guru_id === 0) {
        http_response_code(400);
        throw new Exception("Parameter guru_id wajib diisi.");
    }

    // Fungsi untuk mendapatkan nama hari ini dalam Bahasa Indonesia
    function getNamaHariIndonesia() {
        $day_map = [
            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu'
        ];
        return $day_map[date('l')];
    }
    
    $hari_ini = getNamaHariIndonesia();
    $jadwal_hari_ini = [];

    // 1. Ambil jadwal mengajar
    $sql_mengajar = "SELECT mata_pelajaran, kelas, jam_mulai FROM jadwal_mengajar WHERE guru_id = ? AND hari = ?";
    $stmt_mengajar = $conn->prepare($sql_mengajar);
    $stmt_mengajar->bind_param("is", $guru_id, $hari_ini);
    $stmt_mengajar->execute();
    $result_mengajar = $stmt_mengajar->get_result();
    while($row = $result_mengajar->fetch_assoc()) {
        $jadwal_hari_ini[] = [
            'tipe' => 'Mengajar',
            'deskripsi' => "{$row['mata_pelajaran']} di kelas {$row['kelas']}",
            'waktu' => date('H:i', strtotime($row['jam_mulai']))
        ];
    }
    $stmt_mengajar->close();

    // 2. Ambil jadwal piket
    $sql_piket = "SELECT sesi FROM jadwal_piket WHERE guru_id = ? AND hari = ?";
    $stmt_piket = $conn->prepare($sql_piket);
    $stmt_piket->bind_param("is", $guru_id, $hari_ini);
    $stmt_piket->execute();
    $result_piket = $stmt_piket->get_result();
    while($row = $result_piket->fetch_assoc()) {
        $jadwal_hari_ini[] = [
            'tipe' => 'Piket',
            'deskripsi' => "Sesi {$row['sesi']}",
            'waktu' => 'Pagi' // Anda bisa sesuaikan ini
        ];
    }
    $stmt_piket->close();

    // 3. Ambil jadwal ekskul
    $sql_ekskul = "SELECT nama_ekskul, jam_mulai FROM jadwal_ekskul WHERE guru_id = ? AND hari = ?";
    $stmt_ekskul = $conn->prepare($sql_ekskul);
    $stmt_ekskul->bind_param("is", $guru_id, $hari_ini);
    $stmt_ekskul->execute();
    $result_ekskul = $stmt_ekskul->get_result();
    while($row = $result_ekskul->fetch_assoc()) {
        $jadwal_hari_ini[] = [
            'tipe' => 'Ekstrakurikuler',
            'deskripsi' => $row['nama_ekskul'],
            'waktu' => date('H:i', strtotime($row['jam_mulai']))
        ];
    }
    $stmt_ekskul->close();

    http_response_code(200);
    echo json_encode(['data' => $jadwal_hari_ini]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>