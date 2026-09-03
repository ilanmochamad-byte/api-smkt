<?php
// Pastikan zona waktu Indonesia (sinkron dengan web)
date_default_timezone_set('Asia/Jakarta');

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'includes/db.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Koneksi database gagal: " . $conn->connect_error]);
    exit();
}

$scheduleType = isset($_GET['type']) ? $_GET['type'] : 'mengajar';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
// Biarkan limit 8 di aplikasi mobile agar pengguna tidak terlalu sering pindah halaman
$limit = 8; 
$offset = ($page - 1) * $limit;

function getNamaHariIndonesia($day) {
    $hari = array('Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu');
    return $hari[$day];
}
$hari_ini = getNamaHariIndonesia(date('l'));

$data = [];
$total_rows = 0;

switch ($scheduleType) {
    case 'piket':
        // 1. Hitung Total Piket (Prepared Statement seperti index.php)
        $stmt_total = $conn->prepare("SELECT COUNT(id) as total FROM jadwal_piket WHERE hari = ? AND status_jadwal = 'Aktif'");
        $stmt_total->bind_param("s", $hari_ini);
        $stmt_total->execute();
        $total_rows = $stmt_total->get_result()->fetch_assoc()['total'];

        // 2. Ambil Data Piket (Sama dengan index.php: ambil jp.* dan ORDER BY g.nama_guru)
        $stmt_data = $conn->prepare("SELECT jp.*, g.nama_guru FROM jadwal_piket jp JOIN guru g ON jp.guru_id = g.id WHERE jp.hari = ? AND jp.status_jadwal = 'Aktif' ORDER BY g.nama_guru ASC LIMIT ?, ?");
        $stmt_data->bind_param("sii", $hari_ini, $offset, $limit);
        $stmt_data->execute();
        $result_data = $stmt_data->get_result();
        while($row = $result_data->fetch_assoc()) {
            $data[] = $row;
        }
        break;
        
    case 'ekskul':
        // 1. Hitung Total Ekskul
        $stmt_total = $conn->prepare("SELECT COUNT(id) as total FROM jadwal_ekskul WHERE hari = ? AND status_jadwal = 'Aktif'");
        $stmt_total->bind_param("s", $hari_ini);
        $stmt_total->execute();
        $total_rows = $stmt_total->get_result()->fetch_assoc()['total'];

        // 2. Ambil Data Ekskul (Sama dengan index.php: ambil je.* dan alias nama_guru menjadi nama_pembina untuk frontend)
        $stmt_data = $conn->prepare("SELECT je.*, g.nama_guru, g.nama_guru as nama_pembina FROM jadwal_ekskul je JOIN guru g ON je.guru_id = g.id WHERE je.hari = ? AND je.status_jadwal = 'Aktif' ORDER BY je.jam_mulai ASC LIMIT ?, ?");
        $stmt_data->bind_param("sii", $hari_ini, $offset, $limit);
        $stmt_data->execute();
        $result_data = $stmt_data->get_result();
        while($row = $result_data->fetch_assoc()) {
            $data[] = $row;
        }
        break;
        
    case 'mengajar':
    default:
        // 1. Hitung Total Jadwal Mengajar
        $stmt_total = $conn->prepare("SELECT COUNT(jm.id) as total FROM jadwal_mengajar jm WHERE jm.hari = ? AND status_jadwal = 'Aktif'");
        $stmt_total->bind_param("s", $hari_ini);
        $stmt_total->execute();
        $total_rows = $stmt_total->get_result()->fetch_assoc()['total'];

        // 2. Ambil Data Jadwal Mengajar (Logika sama dengan index.php dan membiarkan format jam dengan detik)
        $stmt_data = $conn->prepare("SELECT jm.id, jm.jam_mulai, jm.jam_selesai, jm.mata_pelajaran, jm.kelas, g.nama_guru FROM jadwal_mengajar jm JOIN guru g ON jm.guru_id = g.id WHERE jm.hari = ? AND jm.status_jadwal = 'Aktif' ORDER BY jm.jam_mulai ASC LIMIT ?, ?");
        $stmt_data->bind_param("sii", $hari_ini, $offset, $limit);
        $stmt_data->execute();
        $result_data = $stmt_data->get_result();
        while($row = $result_data->fetch_assoc()) {
            $data[] = $row;
        }
        break;
}

$total_pages = $total_rows > 0 ? ceil($total_rows / $limit) : 1;

$response = [
    'data' => $data,
    'pagination' => [
        'currentPage' => $page,
        'totalPages' => $total_pages,
        'totalItems' => (int)$total_rows
    ]
];

http_response_code(200);
echo json_encode($response);
$conn->close();
?>