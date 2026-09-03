<?php
// simpan_refleksi.php
ini_set('display_errors', 1); error_reporting(E_ALL);
header("Content-Type: application/json; charset=UTF-8"); header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require_once 'includes/db.php';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $guru_id = $data['guru_id'] ?? 0;
    $tanggal = $data['tanggal'] ?? date('Y-m-d');
    $mapel = $data['mata_pelajaran'] ?? '';

    if ($guru_id == 0 || empty($mapel)) throw new Exception("Data guru dan mata pelajaran wajib diisi.");

    $sql = "INSERT INTO refleksi_guru (guru_id, tanggal, mata_pelajaran, q1_indikator, q2_hasil_asesmen, q3_kesimpulan, q4_metode, q5_dukungan, q6_efektivitas, q7_kompetensi, q8_inovasi, q9_kebijakan) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssssssss", 
        $guru_id, $tanggal, $mapel,
        $data['q1_indikator'], $data['q2_hasil_asesmen'], $data['q3_kesimpulan'],
        $data['q4_metode'], $data['q5_dukungan'], $data['q6_efektivitas'],
        $data['q7_kompetensi'], $data['q8_inovasi'], $data['q9_kebijakan']
    );
    
    if ($stmt->execute()) {
        http_response_code(201); echo json_encode(['status' => 'success', 'message' => 'Refleksi berhasil disimpan.']);
    } else {
        throw new Exception("Gagal menyimpan: " . $stmt->error);
    }
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally { if ($conn) $conn->close(); }
?>