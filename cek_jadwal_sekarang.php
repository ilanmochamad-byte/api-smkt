<?php
// Menambahkan error reporting untuk debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// --- INFORMASI DATABASE ANDA ---
require_once 'includes/db.php';
// ----------------------------------------

try {
    // 1. KONEKSI KE DATABASE 
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    // 2. FUNGSI DIPINDAHKAN KE ATAS AGAR BISA DIPANGGIL
    function getNamaHariIndonesia($day) {
        $hari = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        return $hari[$day] ?? 'Tidak Diketahui';
    }

    // Ambil parameter dari aplikasi
    $guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
    $tipe = isset($_GET['tipe']) ? $_GET['tipe'] : 'mengajar';

    if ($guru_id === 0) {
        throw new Exception("Parameter guru_id dibutuhkan.");
    }
    
    // 3. VARIABEL HARI_INI DAN JAM_SEKARANG
    $hari_ini = getNamaHariIndonesia(date('l'));
    $jam_sekarang = date('H:i:s');

    $sql = "";
    $stmt = null;
    $data = null;

    // PERBAIKAN: Menambahkan kondisi AND status_jadwal = 'Aktif' di setiap kueri
    switch ($tipe) {
        case 'mengajar':
            $sql = "SELECT id, mata_pelajaran, kelas FROM jadwal_mengajar WHERE guru_id = ? AND hari = ? AND ? BETWEEN jam_mulai AND jam_selesai AND status_jadwal = 'Aktif' LIMIT 1";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("SQL Prepare Error (mengajar): " . $conn->error);
            $stmt->bind_param("iss", $guru_id, $hari_ini, $jam_sekarang);
            break;
        case 'piket':
            // Jadwal piket berlaku seharian, jadi tidak perlu cek jam
            $sql = "SELECT id, sesi FROM jadwal_piket WHERE guru_id = ? AND hari = ? AND status_jadwal = 'Aktif' LIMIT 1";
            $stmt = $conn->prepare($sql);
            if ($stmt) $stmt->bind_param("is", $guru_id, $hari_ini);
            break;
        case 'ekskul':
            $sql = "SELECT id, nama_ekskul FROM jadwal_ekskul WHERE guru_id = ? AND hari = ? AND ? BETWEEN jam_mulai AND jam_selesai AND status_jadwal = 'Aktif' LIMIT 1";
            $stmt = $conn->prepare($sql);
            if ($stmt) $stmt->bind_param("iss", $guru_id, $hari_ini, $jam_sekarang);
            break;
        default:
            throw new Exception("Tipe jadwal tidak valid.");
            break;
    }

    if ($stmt) {
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if ($data) {
        http_response_code(200);
        echo json_encode(['status' => 'ada_jadwal', 'jadwal' => $data]);
    } else {
        http_response_code(200);
        echo json_encode(['status' => 'tidak_ada_jadwal']);
    }
    
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?>