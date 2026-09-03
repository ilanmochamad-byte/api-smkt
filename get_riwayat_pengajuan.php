<?php
// get_riwayat_pengajuan.php
ini_set('display_errors', 1); error_reporting(E_ALL);
header("Content-Type: application/json; charset=UTF-8"); header("Access-Control-Allow-Origin: *");

require_once 'includes/db.php';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

$guru_id = $_GET['guru_id'] ?? 0;
$stmt = $conn->prepare("SELECT * FROM pengajuan_absensi WHERE guru_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) { $data[] = $row; }

echo json_encode(['data' => $data]);
?>