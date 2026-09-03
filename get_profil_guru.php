<?php
header("Content-Type: application/json; charset=UTF-8");
// (Sertakan koneksi database Anda seperti di file API lainnya)
require_once 'includes/db.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { throw new Exception("Koneksi database gagal: " . $conn->connect_error); exit(); }

$guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
if ($guru_id === 0) { echo json_encode(['data' => []]); exit(); }

// 1. Ambil data utama guru
$stmt_guru = $conn->prepare("SELECT * FROM guru WHERE id = ?");
$stmt_guru->bind_param("i", $guru_id);
$stmt_guru->execute();
$guru = $stmt_guru->get_result()->fetch_assoc();

// 2. Ambil semua jadwal (mengajar, piket, ekskul)
$jadwal_mengajar = [];
$result_mengajar = $conn->query("SELECT * FROM jadwal_mengajar WHERE guru_id = $guru_id AND status_jadwal = 'Aktif'");
while($row = $result_mengajar->fetch_assoc()) { $jadwal_mengajar[] = $row; }

$jadwal_piket = [];
$result_piket = $conn->query("SELECT * FROM jadwal_piket WHERE guru_id = $guru_id AND status_jadwal = 'Aktif'");
while($row = $result_piket->fetch_assoc()) { $jadwal_piket[] = $row; }

$jadwal_ekskul = [];
$result_ekskul = $conn->query("SELECT * FROM jadwal_ekskul WHERE guru_id = $guru_id AND status_jadwal = 'Aktif'");
while($row = $result_ekskul->fetch_assoc()) { $jadwal_ekskul[] = $row; }

// Gabungkan semua data ke dalam satu respons JSON
$response = [
    'profil' => $guru,
    'jadwal' => [
        'mengajar' => $jadwal_mengajar,
        'piket' => $jadwal_piket,
        'ekskul' => $jadwal_ekskul
    ]
];

echo json_encode($response);
$conn->close();
?>