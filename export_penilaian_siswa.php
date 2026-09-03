<?php
// export_penilaian_siswa.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
// Allow GET method (since parameters are sent via URL)
header("Access-Control-Allow-Methods: GET, OPTIONS"); 
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Database Connection Details ---
require_once 'includes/db.php';
// ------------------------------------

$conn = null; // Initialize connection variable

try {
    // Establish database connection
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        // Throw an exception if connection fails
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    // --- Get and Validate Filters ---
    $guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
    $mapel = isset($_GET['mata_pelajaran']) ? trim($_GET['mata_pelajaran']) : '';
    $semester = isset($_GET['semester']) ? trim($_GET['semester']) : '';
    $tahun_ajaran = isset($_GET['tahun_ajaran']) ? trim($_GET['tahun_ajaran']) : '';

    // Check if required filters are provided
    if ($guru_id === 0 || empty($mapel) || empty($semester) || empty($tahun_ajaran)) {
        http_response_code(400); // Bad Request
        throw new Exception("Semua filter (guru_id, mata_pelajaran, semester, tahun_ajaran) wajib diisi.");
    }

    // --- Prepare and Execute SQL Query ---
    $sql = "SELECT 
                s.nama_siswa, 
                s.nisn,
                s.kelas,
                p.jenis_penilaian, 
                p.nilai, 
                p.keterangan,
                p.tanggal_penilaian -- Optional: include date if needed
            FROM penilaian_siswa p
            JOIN siswa s ON p.siswa_id = s.id
            WHERE 
                p.guru_id = ? AND
                p.mata_pelajaran = ? AND
                p.semester = ? AND
                p.tahun_ajaran = ?
            ORDER BY 
                s.kelas ASC,        -- Order by class first
                s.nama_siswa ASC,   -- Then by student name
                p.tanggal_penilaian ASC, -- Then by assessment date or type
                p.jenis_penilaian ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
         throw new Exception("Gagal mempersiapkan statement SQL: " . $conn->error);
    }
    
    // Bind parameters to the prepared statement
    $stmt->bind_param("isss", $guru_id, $mapel, $semester, $tahun_ajaran);
    
    // Execute the statement
    if (!$stmt->execute()) {
        throw new Exception("Gagal mengeksekusi statement SQL: " . $stmt->error);
    }
    
    // Get the result set
    $result = $stmt->get_result();
    
    // --- Fetch Data ---
    $data = [];
    while($row = $result->fetch_assoc()) {
        // Add each row to the data array
        $data[] = $row;
    }
    
    // Close the statement
    $stmt->close();

    // --- Send Response ---
    http_response_code(200); // OK
    // Output the data in JSON format, wrapped in a 'data' key
    echo json_encode(['data' => $data]);

} catch (Exception $e) {
    // --- Error Handling ---
    // Set HTTP status code (use 500 if not already set, e.g., by 400 validation)
    if (http_response_code() === 200) {
        http_response_code(500); // Internal Server Error
    }
    // Output error message in JSON format
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
} finally {
    // --- Close Connection ---
    // Always close the database connection if it was opened
    if ($conn) {
        $conn->close();
    }
}
?>