<?php
// proses_absen_mengajar.php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// --- KONFIGURASI DATABASE ---
require_once 'includes/db.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => "Koneksi database gagal: " . $conn->connect_error]);
    exit();
}

try {
    // 1. Ambil Data Utama
    $guru_id = $_POST['guru_id'] ?? 0;
    $jadwal_id = $_POST['jadwal_id'] ?? 0;
    $absensi_siswa_json = $_POST['absensi_siswa'] ?? '[]';
    $absensi_siswa = json_decode($absensi_siswa_json, true);

    // 2. Ambil Data Jurnal Mengajar (Fitur Baru)
    $materi_pokok = $_POST['materi_pokok'] ?? '';
    $tujuan_pembelajaran = $_POST['tujuan_pembelajaran'] ?? '';
    $kegiatan_pembelajaran = $_POST['kegiatan_pembelajaran'] ?? '';
    $catatan_refleksi = $_POST['catatan_refleksi'] ?? '';
    $penilaian_evaluasi = $_POST['penilaian_evaluasi'] ?? '';

    // Validasi Sederhana Backend (Opsional, karena frontend sudah handle)
    if (empty($materi_pokok) || empty($tujuan_pembelajaran)) {
         throw new Exception("Materi Pokok dan Tujuan Pembelajaran wajib diisi.");
    }

    // 3. Cek Absen Ganda
    $stmt_cek = $conn->prepare("SELECT id FROM absensi WHERE guru_id = ? AND jadwal_id = ? AND tipe_absensi = 'mengajar' AND DATE(waktu_absensi) = CURDATE()");
    $stmt_cek->bind_param("ii", $guru_id, $jadwal_id);
    $stmt_cek->execute();
    if ($stmt_cek->get_result()->num_rows > 0) {
        throw new Exception("Anda sudah melakukan absensi untuk jadwal ini hari ini.");
    }
    $stmt_cek->close();

    // 4. Proses Upload Foto
    $foto_path_db = null;
    if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] == 0) {
        $target_dir_absolute = "/DATA/k1807225/public_html/smkt.alhasan.co.id/classync/uploads/";
        $save_path_relative = "uploads/";
        
        if (!is_dir($target_dir_absolute)) { mkdir($target_dir_absolute, 0775, true); }
        
        $file_name = time() . '-' . basename($_FILES["foto_bukti"]["name"]);
        
        if (move_uploaded_file($_FILES["foto_bukti"]["tmp_name"], $target_dir_absolute . $file_name)) {
            $foto_path_db = $save_path_relative . $file_name;
        } else {
            throw new Exception("Gagal memindahkan file foto.");
        }
    } else {
        throw new Exception("Foto bukti wajib diupload.");
    }

    $conn->begin_transaction();

    // 5. Simpan absensi guru BESERTA DATA JURNAL
    // Query diperbarui untuk memasukkan 5 kolom baru
    $sql = "INSERT INTO absensi (
                guru_id, jadwal_id, tipe_absensi, waktu_absensi, status, foto_bukti,
                materi_pokok, tujuan_pembelajaran, kegiatan_pembelajaran, catatan_refleksi, penilaian_evaluasi
            ) VALUES (?, ?, 'mengajar', NOW(), 'Hadir', ?, ?, ?, ?, ?, ?)";
            
    $stmt_guru = $conn->prepare($sql);
    if (!$stmt_guru) throw new Exception("SQL Prepare Gagal (absensi guru): " . $conn->error);
    
    // Format bind: i (int), i (int), s (str), s (str), s (str), s (str), s (str), s (str)
    // Total: iis sssss
    $stmt_guru->bind_param("iissssss", 
        $guru_id, 
        $jadwal_id, 
        $foto_path_db,
        $materi_pokok,
        $tujuan_pembelajaran,
        $kegiatan_pembelajaran,
        $catatan_refleksi,
        $penilaian_evaluasi
    );
    
    if (!$stmt_guru->execute()) throw new Exception("SQL Execute Gagal (absensi guru): " . $stmt_guru->error);
    
    $absensi_guru_id = $conn->insert_id;
    $stmt_guru->close();

    // 6. Simpan detail absensi siswa
    if (!empty($absensi_siswa)) {
        $stmt_siswa = $conn->prepare("INSERT INTO detail_absensi_siswa (absensi_guru_id, siswa_id, status_kehadiran) VALUES (?, ?, ?)");
        if (!$stmt_siswa) throw new Exception("SQL Prepare Gagal (detail siswa): " . $conn->error);
        
        foreach ($absensi_siswa as $absen) {
            $stmt_siswa->bind_param("iis", $absensi_guru_id, $absen['siswa_id'], $absen['status']);
            if (!$stmt_siswa->execute()) throw new Exception("SQL Execute Gagal (detail siswa ID ". $absen['siswa_id'] ."): " . $stmt_siswa->error);
        }
        $stmt_siswa->close();
    }

    $conn->commit();
    http_response_code(200);
    echo json_encode(['message' => 'Absensi dan Jurnal berhasil disimpan.']);

} catch (Exception $e) {
    if ($conn->ping()) { $conn->rollback(); }
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn->ping()) { $conn->close(); }
}
?>