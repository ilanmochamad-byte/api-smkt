<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
// --- PERBAIKAN: Izinkan metode POST ---
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// --- PERBAIKAN: Handle preflight request for CORS ---
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'includes/db.php';

$conn = null;

try {
    // --- PERBAIKAN 1: Membuat koneksi ke database ---
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    $absensi_id = isset($_POST['absensi_id']) ? (int)$_POST['absensi_id'] : 0;
    $guru_id = isset($_POST['guru_id']) ? (int)$_POST['guru_id'] : 0;

    // --- PERBAIKAN 2: Validasi input ---
    if ($absensi_id === 0 || $guru_id === 0) {
        http_response_code(400); // Bad Request
        throw new Exception("Parameter absensi_id dan guru_id wajib diisi.");
    }

    // Cek dulu apakah sudah ada like
    $check_sql = "SELECT id FROM likes WHERE absensi_id = ? AND guru_id = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("ii", $absensi_id, $guru_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $stmt_check->close();

    $new_total_likes = 0;

    if ($result->num_rows > 0) {
        // Jika sudah ada, hapus (unlike)
        $delete_sql = "DELETE FROM likes WHERE absensi_id = ? AND guru_id = ?";
        $stmt_delete = $conn->prepare($delete_sql);
        $stmt_delete->bind_param("ii", $absensi_id, $guru_id);
        $stmt_delete->execute();
        $stmt_delete->close();
        $status = 'unliked';
    } else {
        // Jika belum ada, tambahkan (like)
        $insert_sql = "INSERT INTO likes (absensi_id, guru_id) VALUES (?, ?)";
        $stmt_insert = $conn->prepare($insert_sql);
        $stmt_insert->bind_param("ii", $absensi_id, $guru_id);
        $stmt_insert->execute();
        $stmt_insert->close();
        $status = 'liked';
    }

    // --- PERBAIKAN 3: Ambil total like terbaru ---
    $count_sql = "SELECT COUNT(*) as total FROM likes WHERE absensi_id = ?";
    $stmt_count = $conn->prepare($count_sql);
    $stmt_count->bind_param("i", $absensi_id);
    $stmt_count->execute();
    $count_result = $stmt_count->get_result()->fetch_assoc();
    $new_total_likes = $count_result['total'];
    $stmt_count->close();

    http_response_code(200);
    echo json_encode(['status' => $status, 'total_likes' => $new_total_likes]);

} catch (Exception $e) {
    // --- PERBAIKAN 4: Blok catch untuk menangani semua error ---
    if (http_response_code() === 200) {
        http_response_code(500); // Set 500 jika belum di-set
    }
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
} finally {
    // --- PERBAIKAN 5: Pastikan koneksi selalu ditutup ---
    if ($conn) {
        $conn->close();
    }
}
?>