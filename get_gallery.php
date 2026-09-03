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

$current_guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;

$sql = "
    SELECT 
        a.id as absensi_id,
        a.foto_bukti,
        a.materi_pokok,             /* <-- TAMBAHKAN INI */
        a.tujuan_pembelajaran,      /* <-- TAMBAHKAN INI */
        a.catatan_refleksi,         /* <-- TAMBAHKAN INI */
        g.nama_guru,
        g.foto_profil,
        a.waktu_absensi,
        a.status,
        COALESCE(
            CONCAT(jm.mata_pelajaran, ' - Kelas ', jm.kelas), 
            CONCAT('Piket Sesi ', jp.sesi), 
            je.nama_ekskul
        ) as keterangan_jadwal,
        (SELECT COUNT(*) FROM likes WHERE absensi_id = a.id) as total_likes,
        (SELECT COUNT(*) FROM comments WHERE absensi_id = a.id) as total_comments,
        (SELECT COUNT(*) FROM likes WHERE absensi_id = a.id AND guru_id = ?) as user_has_liked,
        (SELECT COUNT(*) FROM dislikes WHERE absensi_id = a.id) as total_dislikes,
        (SELECT COUNT(*) FROM dislikes WHERE absensi_id = a.id AND guru_id = ?) as user_has_disliked
    FROM absensi a
    JOIN guru g ON a.guru_id = g.id
    LEFT JOIN jadwal_mengajar jm ON a.jadwal_id = jm.id AND a.tipe_absensi = 'mengajar'
    LEFT JOIN jadwal_piket jp ON a.jadwal_id = jp.id AND a.tipe_absensi = 'piket'
    LEFT JOIN jadwal_ekskul je ON a.jadwal_id = je.id AND a.tipe_absensi = 'ekskul'
    WHERE DATE(a.waktu_absensi) = CURDATE()
      AND a.foto_bukti IS NOT NULL 
      AND a.foto_bukti != ''
      AND (a.tipe_absensi != 'piket' OR a.status = 'Hadir')
    ORDER BY a.waktu_absensi DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $current_guru_id, $current_guru_id);
$stmt->execute();
$result = $stmt->get_result();

    if (!$result) {
        throw new Exception("Eksekusi query gagal: " . $conn->error);
    }
    
    $data = [];
    // --- PERUBAHAN DI SINI (1): Definisikan Base URL ---
    // Pastikan URL ini adalah folder tempat gambar Anda bisa diakses dari internet
    $base_url = "https://smkt.alhasan.co.id/classync/"; 

    while($row = $result->fetch_assoc()) {
        // Buat URL lengkap untuk foto bukti
        if ($row['foto_bukti']) {
            $row['foto_bukti'] = $base_url . $row['foto_bukti'];
        }

        // --- 2. TAMBAHKAN BLOK KODE DI BAWAH INI ---
        // Buat URL lengkap untuk foto profil jika ada
        if ($row['foto_profil']) {
            $row['foto_profil'] = $base_url . $row['foto_profil'];
        }
        // -----------------------------------------

        $data[] = $row;
    }

    http_response_code(200);
    // --- PERUBAHAN DI SINI (3): Tambahkan JSON_UNESCAPED_SLASHES ---
    echo json_encode(['data' => $data], JSON_UNESCAPED_SLASHES);

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