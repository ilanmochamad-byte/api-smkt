<?php
// Error reporting untuk debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// --- INFORMASI DATABASE ANDA ---
require_once 'includes/db.php';
// -----------------------------------------

$conn = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    $siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;

    if ($siswa_id === 0) {
        throw new Exception("Parameter siswa_id dibutuhkan.");
    }

    // Query untuk mengambil riwayat absensi siswa
    $sql = "SELECT 
                das.status_kehadiran,
                a.waktu_absensi,
                a.guru_id,
                jm.mata_pelajaran
            FROM detail_absensi_siswa das
            JOIN absensi a ON das.absensi_guru_id = a.id
            LEFT JOIN jadwal_mengajar jm ON a.jadwal_id = jm.id AND a.tipe_absensi = 'mengajar'
            WHERE das.siswa_id = ?
            ORDER BY a.waktu_absensi DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL Prepare Error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $siswa_id);
    
    if (!$stmt->execute()) {
        throw new Exception("SQL Execute Error: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $data = [];
    while($row = $result->fetch_assoc()) {
        if ($row['mata_pelajaran'] === null) {
            // Memberi keterangan jika bukan absen mengajar (misal piket/ekskul)
            $row['mata_pelajaran'] = 'Kegiatan Non-Pelajaran';
        }
        $data[] = $row;
    }
    $stmt->close();

    http_response_code(200);
    echo json_encode(['data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>