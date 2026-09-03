<?php
// get_progress_mengajar.php
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

    // Fungsi helper untuk menghitung JP dari durasi
    function hitungJP($jam_mulai, $jam_selesai) {
        $mulai = new DateTime($jam_mulai);
        $selesai = new DateTime($jam_selesai);
        $diff_minutes = ($selesai->getTimestamp() - $mulai->getTimestamp()) / 60;
        return $diff_minutes / 40; // Asumsi 1 JP = 45 menit
    }

    // --- LOGIKA BARU UNTUK TARGET YANG AKURAT ---

    // 1. Ambil semua jadwal mingguan guru dan kelompokkan berdasarkan hari
    $sql_jadwal = "SELECT hari, jam_mulai, jam_selesai FROM jadwal_mengajar WHERE guru_id = ? AND status_jadwal = 'Aktif'";
    $stmt_jadwal = $conn->prepare($sql_jadwal);
    $stmt_jadwal->bind_param("i", $guru_id);
    $stmt_jadwal->execute();
    $result_jadwal = $stmt_jadwal->get_result();
    
    $jadwal_by_hari = [];
    while ($jadwal = $result_jadwal->fetch_assoc()) {
        $jadwal_by_hari[$jadwal['hari']][] = $jadwal;
    }
    $stmt_jadwal->close();

    // 2. Iterasi setiap hari dalam bulan ini untuk menghitung total JP target
    $target_bulanan_jp = 0;
    $current_year = date('Y');
    $current_month = date('m');
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
    
    // Mapping nama hari dari Inggris ke Indonesia (sesuai format database Anda)
    $day_map = [
        'Monday'    => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis', 'Friday'  => 'Jumat', 'Saturday'  => 'Sabtu',
        'Sunday'    => 'Minggu'
    ];

    for ($day = 1; $day <= $days_in_month; $day++) {
        $date = new DateTime("$current_year-$current_month-$day");
        $day_of_week_english = $date->format('l');
        $hari_indonesia = $day_map[$day_of_week_english];

        // Jika ada jadwal pada hari ini, tambahkan JP-nya ke target
        if (isset($jadwal_by_hari[$hari_indonesia])) {
            foreach ($jadwal_by_hari[$hari_indonesia] as $jadwal_hari_ini) {
                $target_bulanan_jp += hitungJP($jadwal_hari_ini['jam_mulai'], $jadwal_hari_ini['jam_selesai']);
            }
        }
    }
    // --- AKHIR LOGIKA BARU ---

    // 3. Hitung total JP realisasi dari absensi (logika ini sudah benar dan tidak berubah)
    $sql_absensi = "SELECT jm.jam_mulai, jm.jam_selesai
                    FROM absensi a
                    JOIN jadwal_mengajar jm ON a.jadwal_id = jm.id
                    WHERE a.guru_id = ? 
                      AND a.tipe_absensi = 'mengajar'
                      AND jm.status_jadwal = 'Aktif'
                      AND MONTH(a.waktu_absensi) = MONTH(CURDATE()) 
                      AND YEAR(a.waktu_absensi) = YEAR(CURDATE())";
    $stmt_absensi = $conn->prepare($sql_absensi);
    $stmt_absensi->bind_param("i", $guru_id);
    $stmt_absensi->execute();
    $result_absensi = $stmt_absensi->get_result();

    $realisasi_bulanan_jp = 0;
    while ($absen = $result_absensi->fetch_assoc()) {
        $realisasi_bulanan_jp += hitungJP($absen['jam_mulai'], $absen['jam_selesai']);
    }
    $stmt_absensi->close();

    // 4. Hitung persentase
    $persentase = 0;
    if ($target_bulanan_jp > 0) {
        $persentase = round(($realisasi_bulanan_jp / $target_bulanan_jp) * 100);
    }
    if ($persentase > 100) $persentase = 100;

    $response = [
        'target' => round($target_bulanan_jp),
        'realisasi' => round($realisasi_bulanan_jp),
        'persentase' => $persentase
    ];

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>