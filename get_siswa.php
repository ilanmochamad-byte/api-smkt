<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// --- INFORMASI DATABASE ANDA ---
require_once 'includes/db.php';
// -----------------------------------------

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Koneksi database gagal: " . $conn->connect_error]);
    exit();
}

try {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    $filter_kelas = $_GET['kelas'] ?? '';
    $filter_jenis_kelamin = $_GET['jenis_kelamin'] ?? '';
    $search_nama = $_GET['nama'] ?? '';

    $sql_base = "FROM siswa";
    $conditions = ["kelas != 'Lulus / Alumni'"];
    $params = [];
    $types = '';

    if (!empty($search_nama)) {
        $conditions[] = "nama_siswa LIKE ?";
        $params[] = "%" . $search_nama . "%";
        $types .= 's';
    }
    if (!empty($filter_kelas)) {
        $conditions[] = "kelas LIKE ?";
        $params[] = "%" . $filter_kelas . "%";
        $types .= 's';
    }
    if (!empty($filter_jenis_kelamin)) {
        $conditions[] = "jenis_kelamin = ?";
        $params[] = $filter_jenis_kelamin;
        $types .= 's';
    }

    $where_clause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

    // Query untuk total data
    $sql_total = "SELECT COUNT(id) as total " . $sql_base . $where_clause;
    $stmt_total = $conn->prepare($sql_total);
    if (!empty($params)) {
        $stmt_total->bind_param($types, ...$params);
    }
    $stmt_total->execute();
    $total_rows = $stmt_total->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);
    $stmt_total->close();

    // Query untuk mengambil data
    $sql_data = "SELECT id, nisn, nama_siswa, jenis_kelamin, kelas, kontak_ortu, foto_siswa " . $sql_base . $where_clause . " ORDER BY kelas, nama_siswa ASC LIMIT ?, ?";
    
    // Tambahkan parameter untuk LIMIT dan OFFSET
    $params[] = $offset;
    $params[] = $limit;
    $types .= 'ii';
    
    $stmt_list = $conn->prepare($sql_data);
    $stmt_list->bind_param($types, ...$params);
    $stmt_list->execute();
    $result_data = $stmt_list->get_result();
    
    $data = [];
    while($row = $result_data->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt_list->close();

    $response = [
        'data' => $data,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $total_pages,
            'totalItems' => (int)$total_rows
        ]
    ];

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>