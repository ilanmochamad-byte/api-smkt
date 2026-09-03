<?php
// save_token.php
header("Content-Type: application/json; charset=UTF-8"); 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); 
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require_once 'includes/db.php';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!empty($data['guru_id']) && !empty($data['token'])) {
    $stmt = $conn->prepare("UPDATE guru SET expo_push_token = ? WHERE id = ?");
    $stmt->bind_param("si", $data['token'], $data['guru_id']);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
}
?>