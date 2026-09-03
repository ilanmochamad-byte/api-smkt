<?php
// FILE INI HANYA UNTUK DEBUGGING
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Ambil data POST yang dikirim dari aplikasi
$absensi_id = isset($_POST['absensi_id']) ? $_POST['absensi_id'] : 'Tidak ada';
$guru_id = isset($_POST['guru_id']) ? $_POST['guru_id'] : 'Tidak ada';
$comment_text = isset($_POST['comment_text']) ? $_POST['comment_text'] : 'Tidak ada';

// Kirim kembali data yang diterima sebagai respons sukses
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => 'Server berhasil menerima data.',
    'data_diterima' => [
        'absensi_id' => $absensi_id,
        'guru_id' => $guru_id,
        'comment_text' => $comment_text
    ]
]);
?>