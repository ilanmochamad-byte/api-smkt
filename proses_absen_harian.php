<?php
// proses_absen_harian.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Jakarta');

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('SCHOOL_LATITUDE', -7.323761598031887);
define('SCHOOL_LONGITUDE', 108.3618642337998);
define('ALLOWED_RADIUS_METERS', 100); 

require_once 'includes/db.php';

$conn = null;

function getDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371e3; 
    $phi1 = $lat1 * M_PI / 180;
    $phi2 = $lat2 * M_PI / 180;
    $deltaPhi = ($lat2 - $lat1) * M_PI / 180;
    $deltaLambda = ($lon2 - $lon1) * M_PI / 180;
    $a = sin($deltaPhi / 2) * sin($deltaPhi / 2) + cos($phi1) * cos($phi2) * sin($deltaLambda / 2) * sin($deltaLambda / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $guru_id = $data['guru_id'] ?? 0;
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;

    if ($guru_id === 0 || $latitude === null || $longitude === null) {
        http_response_code(400);
        throw new Exception("Data tidak lengkap (guru_id atau lokasi).");
    }

    $distance = getDistance(SCHOOL_LATITUDE, SCHOOL_LONGITUDE, $latitude, $longitude);
    if ($distance > ALLOWED_RADIUS_METERS) {
        http_response_code(403); 
        throw new Exception("Lokasi Anda terlalu jauh (" . round($distance) . "m). Absensi harus di dalam radius " . ALLOWED_RADIUS_METERS . "m dari sekolah.");
    }

    $stmt_cek = $conn->prepare("SELECT id, jam_masuk, jam_pulang FROM absensi_harian WHERE guru_id = ? AND tanggal = CURDATE()");
    $stmt_cek->bind_param("i", $guru_id);
    $stmt_cek->execute();
    $result = $stmt_cek->get_result();
    $today_record = $result->fetch_assoc();
    $stmt_cek->close();

    $message = "";

    if (!$today_record) {
        $sql = "INSERT INTO absensi_harian (guru_id, tanggal, jam_masuk, latitude_masuk, longitude_masuk) VALUES (?, CURDATE(), NOW(), ?, ?)";
        $stmt_insert = $conn->prepare($sql);
        $stmt_insert->bind_param("idd", $guru_id, $latitude, $longitude);
        $stmt_insert->execute();
        $stmt_insert->close();
        
        $message = "Absen Masuk berhasil dicatat untuk mendapat Uang Transportasi hari ini!";
        
    } else if ($today_record['jam_masuk'] && !$today_record['jam_pulang']) {
        
        $waktu_masuk = date('H:i:s', strtotime($today_record['jam_masuk']));
        $waktu_pulang = date('H:i:s'); 
        
        // SYARAT TRANSPORT: Masuk <= 07:35:59 DAN Pulang >= 13:25:00
        // $waktu_masuk <= '07:35:59' && 
        // $is_disiplin = ($waktu_pulang >= '13:25:00');
        $uang_transport = 0;

        // if ($is_disiplin) {
            // Ambil nominal transportasi harian guru dari tabel tunjangan_guru
            $stmt_tj = $conn->prepare("SELECT transportasi FROM tunjangan_guru WHERE guru_id = ?");
            $stmt_tj->bind_param("i", $guru_id);
            $stmt_tj->execute();
            $res_tj = $stmt_tj->get_result();
            if ($row_tj = $res_tj->fetch_assoc()) {
                $uang_transport = (int)$row_tj['transportasi'];
            }
            $stmt_tj->close();
        // }

        // Simpan Uang Transport ke kolom 'bonus' di tabel absensi_harian
        $sql = "UPDATE absensi_harian SET jam_pulang = NOW(), latitude_pulang = ?, longitude_pulang = ?, bonus = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql);
        $stmt_update->bind_param("ddii", $latitude, $longitude, $uang_transport, $today_record['id']);
        $stmt_update->execute();
        $stmt_update->close();
        
        $message = "Absen Pulang berhasil dicatat.";
        if ($uang_transport > 0) {
            $message .= "\n\nSelamat! Anda mendapatkan Uang Transportasi hari ini sebesar Rp " . number_format($uang_transport, 0, ',', '.');
        } else {
            if (!$is_disiplin) {
                $message .= "\n\n(Anda tidak mendapat uang transportasi hari ini karena jam kedatangan/kepulangan di luar batas waktu disiplin).";
            } else {
                $message .= "\n\n(Uang transportasi harian Anda diatur Rp 0).";
            }
        }
        
    } else {
        http_response_code(409);
        throw new Exception("Anda sudah melakukan absensi masuk dan pulang hari ini.");
    }

    http_response_code(201);
    echo json_encode(['status' => 'success', 'message' => $message]);

} catch (Exception $e) {
    if (http_response_code() === 200 || http_response_code() === 201) { http_response_code(500); }
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>