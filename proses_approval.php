<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 0);
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once 'includes/db.php';

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $id = $data['id'] ?? 0;
    $action = $data['action'] ?? ''; // 'approve' atau 'reject'
    $komentar = $data['komentar'] ?? '';

    if ($id === 0 || empty($action)) throw new Exception("Data tidak valid.");

    $status_db = ($action === 'approve') ? 'approved' : 'rejected';

    $stmt = $conn->prepare("UPDATE absensi SET status_approval = ?, komentar_approval = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status_db, $komentar, $id);
    $stmt->execute();

    echo json_encode(['status' => 'success', 'message' => 'Status approval berhasil diperbarui.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>