<?php
// Menambahkan error reporting untuk debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); // BARU: Tambahkan Origin
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight request for CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- INFORMASI DATABASE ANDA ---
require_once 'includes/db.php';
// -----------------------------------------

$conn = null;

// --- BARU: Definisikan Base Path Upload ---
// Sesuaikan path ini jika base direktori web Anda berbeda
$base_upload_path_absolute = "/DATA/k1807225/public_html/smkt.alhasan.co.id/classync/"; // Path absolut ke folder classync

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }

    $guru_id = $_POST['guru_id'] ?? 0;
    if ($guru_id <= 0) { // Cek jika 0 atau kurang
        http_response_code(400); // Bad Request
        throw new Exception("Guru ID tidak valid.");
    }

    // Ambil data lain
    $nama_guru = $_POST['nama_guru'] ?? '';
    $nip = $_POST['nip'] ?? '';
    $tempat_lahir = $_POST['tempat_lahir'] ?? '';
    $tanggal_lahir_input = $_POST['tanggal_lahir'] ?? ''; // Ambil input tanggal
    $kontak = $_POST['kontak'] ?? '';
    $pendidikan_s1 = $_POST['pendidikan_s1'] ?? '';
    $pendidikan_s2 = $_POST['pendidikan_s2'] ?? '';
    $pendidikan_s3 = $_POST['pendidikan_s3'] ?? '';
    $tugas_tambahan = $_POST['tugas_tambahan'] ?? '';
    $foto_lama_relative = $_POST['foto_lama'] ?? ''; // Path relatif foto lama dari DB/POST

    // --- PERBAIKAN 1: Konversi Tanggal Lahir ---
    $tanggal_lahir_db = null; // Default null jika input kosong atau salah format
    if (!empty($tanggal_lahir_input)) {
        try {
            // Coba parsing dengan format 'd F Y' (misal: 01 Januari 1987)
            $date_obj = DateTime::createFromFormat('d F Y', $tanggal_lahir_input);
            if ($date_obj) {
                 $tanggal_lahir_db = $date_obj->format('Y-m-d');
            } else {
                 // Jika format di atas gagal, coba parsing format Y-m-d (jika sudah benar)
                 $date_obj = DateTime::createFromFormat('Y-m-d', $tanggal_lahir_input);
                 if ($date_obj) {
                     $tanggal_lahir_db = $date_obj->format('Y-m-d');
                 } else {
                     // Jika semua gagal, log warning atau biarkan null
                     error_log("Format tanggal lahir tidak dikenali: " . $tanggal_lahir_input);
                 }
            }
        } catch (Exception $date_ex) {
             error_log("Error parsing tanggal lahir: " . $date_ex->getMessage());
        }
    }
    // --- AKHIR PERBAIKAN 1 ---

    $foto_path_db = $foto_lama_relative; // Defaultnya tetap pakai foto lama

    // Proses upload foto baru jika ada
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == UPLOAD_ERR_OK) {
        // --- PERBAIKAN 2: Gunakan Base Path untuk Direktori Target ---
        $target_dir_absolute = $base_upload_path_absolute . "uploads/guru/"; // Path absolut folder upload guru
        $save_path_relative = "uploads/guru/"; // Path relatif untuk disimpan di DB

        // Buat direktori jika belum ada
        if (!is_dir($target_dir_absolute)) {
            if (!mkdir($target_dir_absolute, 0775, true)) {
                 throw new Exception("Gagal membuat direktori upload: " . $target_dir_absolute);
            }
        }

        // Pastikan direktori bisa ditulis
        if (!is_writable($target_dir_absolute)) {
             throw new Exception("Direktori upload tidak bisa ditulis: " . $target_dir_absolute);
        }

        // Buat nama file unik
        $file_extension = pathinfo($_FILES["foto_profil"]["name"], PATHINFO_EXTENSION);
        $file_name = "guru-" . $guru_id . "-" . time() . "." . $file_extension;
        $target_file_absolute = $target_dir_absolute . $file_name;

        // Pindahkan file yang diupload
        if (move_uploaded_file($_FILES["foto_profil"]["tmp_name"], $target_file_absolute)) {
            // --- PERBAIKAN 3: Hapus foto lama dengan path absolut ---
            if (!empty($foto_lama_relative)) {
                $foto_lama_absolute = $base_upload_path_absolute . $foto_lama_relative;
                if (file_exists($foto_lama_absolute) && is_file($foto_lama_absolute)) {
                    unlink($foto_lama_absolute);
                }
            }
            // --- AKHIR PERBAIKAN 3 ---
            $foto_path_db = $save_path_relative . $file_name; // Update path DB ke foto baru
        } else {
             throw new Exception("Gagal memindahkan file upload. Error code: " . $_FILES['foto_profil']['error']);
        }
    } elseif (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] != UPLOAD_ERR_NO_FILE) {
         // Handle error upload lainnya jika perlu
         throw new Exception("Error saat upload foto: " . $_FILES['foto_profil']['error']);
    }


    // Update data ke database
    $stmt = $conn->prepare("UPDATE guru SET nama_guru=?, nip=?, tempat_lahir=?, tanggal_lahir=?, kontak=?, pendidikan_s1=?, pendidikan_s2=?, pendidikan_s3=?, tugas_tambahan=?, foto_profil=? WHERE id=?");
    // Bind tanggal_lahir_db yang sudah diformat
    $stmt->bind_param("ssssssssssi", $nama_guru, $nip, $tempat_lahir, $tanggal_lahir_db, $kontak, $pendidikan_s1, $pendidikan_s2, $pendidikan_s3, $tugas_tambahan, $foto_path_db, $guru_id);

    if ($stmt->execute()) {
        // Ambil data guru yang sudah ter-update (lebih aman pakai prepared statement)
        $stmt_get = $conn->prepare("SELECT * FROM guru WHERE id = ?");
        $stmt_get->bind_param("i", $guru_id);
        $stmt_get->execute();
        $result = $stmt_get->get_result();
        $updated_guru = $result->fetch_assoc();
        $stmt_get->close();

        http_response_code(200); // OK
        echo json_encode(['status' => 'success', 'message' => 'Profil berhasil diperbarui.', 'user' => $updated_guru]);
    } else {
        throw new Exception("Gagal mengeksekusi update: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    // Set 500 hanya jika belum di set (misal: 400 Bad Request)
    if (http_response_code() === 200) {
        http_response_code(500);
    }
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>