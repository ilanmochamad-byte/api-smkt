<?php
// Mengatur header agar outputnya adalah JSON dan mengizinkan request dari mana saja (CORS)
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// --- GANTI DENGAN INFORMASI DATABASE ANDA ---
require_once 'includes/db.php';
// -----------------------------------------

// Membuat koneksi
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// // Cek koneksi
if ($conn->connect_error) {
    http_response_code(500); // Server Error
    echo json_encode(["message" => "Koneksi database gagal: " . $conn->connect_error]);
    exit();
}

// Mengambil JSON body yang dikirim dari aplikasi React Native
$json_data = file_get_contents("php://input");
$data = json_decode($json_data);

// Validasi input
if (!isset($data->nip) || !isset($data->password)) {
    http_response_code(400); // Bad Request
    echo json_encode(["message" => "NIP dan Password harus diisi."]);
    exit();
}

$nip = $data->nip;
$password = $data->password;

// --- DITAMBAHKAN: is_bk pada baris SELECT di bawah ini ---
$stmt = $conn->prepare("SELECT id, nama_guru, password, foto_profil, is_bk FROM guru WHERE nip = ?");
$stmt->bind_param("s", $nip);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    // Verifikasi password yang di-hash (yang diawali $2a$)
    if (password_verify($password, $user['password'])) {
        // Jika password cocok
        http_response_code(200); // OK
        echo json_encode([
            "message" => "Login Berhasil!",
            "user" => [
                "id" => $user['id'],
                "nama" => $user['nama_guru'],
                "nip" => $nip,
                "foto_profil" => $user['foto_profil'],
                "is_bk" => $user['is_bk'] // --- DITAMBAHKAN: Mengirim status is_bk ke aplikasi ---
            ]
        ]);
    } else {
        // Jika password salah
        http_response_code(401); // Unauthorized
        echo json_encode(["message" => "NIP atau Password salah."]);
    }
} else {
    // Jika NIP tidak ditemukan
    http_response_code(401); // Unauthorized
    echo json_encode(["message" => "NIP atau Password salah."]);
}

$stmt->close();
$conn->close();
?>