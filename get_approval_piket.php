<?php
require_once 'includes/db.php';

$sql = "SELECT a.id, a.guru_id, a.waktu_absensi, a.foto_bukti, a.latitude, a.longitude, g.nama_guru 
        FROM absensi a 
        JOIN guru g ON a.guru_id = g.id 
        WHERE a.tipe_absensi = 'piket' AND a.status = 'Pending' 
        ORDER BY a.waktu_absensi DESC";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode(['success' => true, 'data' => $data]);
?>