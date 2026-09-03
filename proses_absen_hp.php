<?php
// proses_absen_hp.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// PERBAIKAN: SET ZONA WAKTU KE INDONESIA
date_default_timezone_set('Asia/Jakarta');

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require_once 'includes/db.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => 'Database connection failed']);
    exit();
}

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $nisn = $data['nisn'] ?? '';
    $guru_id = $data['guru_id'] ?? 0;
    $tanggal_request = $data['tanggal'] ?? null; 

    if (empty($nisn) || empty($guru_id)) {
        throw new Exception("NISN atau ID Guru tidak terbaca.");
    }

    $today = date('Y-m-d');
    
    if ($tanggal_request !== null && $tanggal_request !== $today) {
        throw new Exception("Proses absen HP hanya bisa dilakukan untuk hari ini ($today). Tanggal yang dikirim: $tanggal_request");
    }
    
    $tanggal_proses = $today;

    $stmt_siswa = $conn->prepare("SELECT id, nama_siswa, kelas FROM siswa WHERE nisn = ?");
    $stmt_siswa->bind_param("s", $nisn);
    $stmt_siswa->execute();
    $res_siswa = $stmt_siswa->get_result();
    $siswa = $res_siswa->fetch_assoc();
    $stmt_siswa->close();
    
    if (!$siswa) {
        throw new Exception("Siswa dengan NISN $nisn tidak ditemukan dalam database.");
    }

    $stmt_cek = $conn->prepare("SELECT id, jam_pengambilan, jam_pengembalian, status FROM absensi_hp WHERE siswa_id = ? AND tanggal = ?");
    $stmt_cek->bind_param("is", $siswa['id'], $tanggal_proses);
    $stmt_cek->execute();
    $res_cek = $stmt_cek->get_result();
    $record = $res_cek->fetch_assoc();
    $stmt_cek->close();

    if (!$record) {
        $stmt_insert = $conn->prepare("INSERT INTO absensi_hp (siswa_id, guru_id, tanggal, jam_pengambilan, status) VALUES (?, ?, ?, NOW(), 'Diambil')");
        $stmt_insert->bind_param("iis", $siswa['id'], $guru_id, $tanggal_proses);
        
        if ($stmt_insert->execute()) {
            $jam_ambil = date('H:i');
            echo json_encode([
                'status' => 'success', 
                'type' => 'ambil', 
                'message' => "✅ HP diserahkan ke {$siswa['nama_siswa']} ({$siswa['kelas']}) pukul $jam_ambil",
                'data' => [
                    'nisn' => $nisn,
                    'nama_siswa' => $siswa['nama_siswa'],
                    'kelas' => $siswa['kelas'],
                    'jam_pengambilan' => $jam_ambil,
                    'tanggal' => $tanggal_proses
                ]
            ]);
        } else {
            throw new Exception("Gagal menyimpan data pengambilan HP.");
        }
        $stmt_insert->close();
        
    } else {
        if ($record['jam_pengembalian'] == null || $record['status'] === 'Diambil') {
            $stmt_update = $conn->prepare("UPDATE absensi_hp SET jam_pengembalian = NOW(), status = 'Dikembalikan', guru_id = ? WHERE id = ?");
            $stmt_update->bind_param("ii", $guru_id, $record['id']);
            
            if ($stmt_update->execute()) {
                $jam_kembali = date('H:i');
                $jam_ambil = date('H:i', strtotime($record['jam_pengambilan']));
                echo json_encode([
                    'status' => 'success', 
                    'type' => 'kembali', 
                    'message' => "✅ HP diterima dari {$siswa['nama_siswa']} ({$siswa['kelas']}) pukul $jam_kembali",
                    'data' => [
                        'nisn' => $nisn,
                        'nama_siswa' => $siswa['nama_siswa'],
                        'kelas' => $siswa['kelas'],
                        'jam_pengambilan' => $jam_ambil,
                        'jam_pengembalian' => $jam_kembali,
                        'tanggal' => $tanggal_proses
                    ]
                ]);
            } else {
                throw new Exception("Gagal menyimpan data pengembalian HP.");
            }
            $stmt_update->close();
            
        } else {
            $jam_ambil = date('H:i', strtotime($record['jam_pengambilan']));
            $jam_kembali = date('H:i', strtotime($record['jam_pengembalian']));
            throw new Exception("⚠️ {$siswa['nama_siswa']} sudah menyelesaikan siklus HP hari ini.\n\n📱 Diambil: $jam_ambil\n📥 Dikembalikan: $jam_kembali");
        }
    }

} catch (Exception $e) {
    http_response_code(400); 
    echo json_encode([
        'error' => true, 
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>