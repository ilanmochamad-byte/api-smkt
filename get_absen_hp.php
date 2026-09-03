<?php
// get_absen_hp.php
ini_set('display_errors', 1); 
error_reporting(E_ALL);

// PERBAIKAN: SET ZONA WAKTU KE INDONESIA
date_default_timezone_set('Asia/Jakarta');

header("Content-Type: application/json; charset=UTF-8"); 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require_once 'includes/db.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
}

$today = date('Y-m-d');
$tanggal = $_GET['tanggal'] ?? $today; 
$kelas = $_GET['kelas'] ?? '';
$search = $_GET['search'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $tanggal = $today;
}

if ($tanggal > $today) {
    $tanggal = $today;
}

$sql = "SELECT s.nisn, s.nama_siswa, s.kelas, 
               a.jam_pengambilan, a.jam_pengembalian, a.status,
               a.tanggal
        FROM absensi_hp a 
        JOIN siswa s ON a.siswa_id = s.id 
        WHERE a.tanggal = ?";
$params = [$tanggal];
$types = "s";

if (!empty($kelas)) {
    $sql .= " AND s.kelas = ?";
    $params[] = $kelas;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (s.nama_siswa LIKE ? OR s.nisn LIKE ?)";
    $searchWildcard = "%$search%";
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $types .= "ss";
}

$sql .= " ORDER BY a.jam_pengambilan DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
$count_diambil = 0;
$count_dikembalikan = 0;

while ($row = $result->fetch_assoc()) {
    $row['jam_pengambilan'] = $row['jam_pengambilan'] ? date('H:i', strtotime($row['jam_pengambilan'])) : '-';
    $row['jam_pengembalian'] = $row['jam_pengembalian'] ? date('H:i', strtotime($row['jam_pengembalian'])) : '-';
    
    if ($row['status'] === 'Diambil') {
        $count_diambil++;
    } else if ($row['status'] === 'Dikembalikan') {
        $count_dikembalikan++;
    }
    
    $data[] = $row;
}
$stmt->close();

$sql_belum = "SELECT s.nisn, s.nama_siswa, s.kelas 
              FROM siswa s
              LEFT JOIN absensi_hp a ON s.id = a.siswa_id AND a.tanggal = ?
              WHERE a.id IS NULL";
$params_belum = [$tanggal];
$types_belum = "s";

if (!empty($kelas)) {
    $sql_belum .= " AND s.kelas = ?";
    $params_belum[] = $kelas;
    $types_belum .= "s";
}

if (!empty($search)) {
    $sql_belum .= " AND (s.nama_siswa LIKE ? OR s.nisn LIKE ?)";
    $searchWildcard = "%$search%";
    $params_belum[] = $searchWildcard;
    $params_belum[] = $searchWildcard;
    $types_belum .= "ss";
}

$sql_belum .= " ORDER BY s.kelas ASC, s.nama_siswa ASC";

$stmt_belum = $conn->prepare($sql_belum);
$stmt_belum->bind_param($types_belum, ...$params_belum);
$stmt_belum->execute();
$result_belum = $stmt_belum->get_result();

$data_belum = [];
while ($row = $result_belum->fetch_assoc()) {
    $data_belum[] = $row;
}
$stmt_belum->close();

$conn->close();

echo json_encode([
    'status' => 'success',
    'tanggal' => $tanggal,
    'is_today' => ($tanggal === $today), 
    'stats' => [
        'total_tercatat' => count($data),
        'diambil' => $count_diambil, 
        'dikembalikan' => $count_dikembalikan, 
        'belum_ambil' => count($data_belum)
    ],
    'data' => $data, 
    'data_belum' => $data_belum, 
    'message' => $tanggal === $today 
        ? 'Data absensi HP hari ini' 
        : "Data riwayat absensi HP tanggal $tanggal (mode lihat saja)"
]);
?>