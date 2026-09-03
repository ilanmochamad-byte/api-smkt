<?php
// Error reporting untuk debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'includes/db.php';

$conn = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    $absensi_id = isset($_GET['absensi_id']) ? (int)$_GET['absensi_id'] : 0;

    if ($absensi_id === 0) {
        http_response_code(400);
        throw new Exception("Parameter absensi_id wajib diisi.");
    }

    $sql = "
        SELECT 
            c.id, 
            c.comment_text, 
            c.created_at, 
            g.nama_guru
        FROM comments c
        JOIN guru g ON c.guru_id = g.id
        WHERE c.absensi_id = ?
        ORDER BY c.created_at ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $absensi_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $comments = [];
    while($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    $stmt->close();

    http_response_code(200);
    echo json_encode(['data' => $comments]);

} catch (Exception $e) {
    if (http_response_code() === 200) http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>