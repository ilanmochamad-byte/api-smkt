<?php
// Mengatur header agar outputnya adalah JSON dan mengizinkan request dari mana saja (CORS)
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Menyediakan $conn; kalau koneksi gagal, db.php sendiri yang menjawab 500
require_once 'includes/db.php';

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
        $respons = [
            "message" => "Login Berhasil!",
            "user" => [
                "id" => $user['id'],
                "nama" => $user['nama_guru'],
                "nip" => $nip,
                "foto_profil" => $user['foto_profil'],
                "is_bk" => $user['is_bk'] // --- DITAMBAHKAN: Mengirim status is_bk ke aplikasi ---
            ]
        ];

        // Fase A migrasi autentikasi (lihat CLAUDE.md): terbitkan token dan
        // sertakan di tingkat atas respons. Aplikasi lama hanya membaca
        // "message" dan "user", jadi field tambahan ini diabaikannya.
        // Satu kolom = satu sesi per guru: login di perangkat lain menimpa
        // token perangkat sebelumnya.
        // Kalau token gagal disimpan, login tetap berhasil tanpa "token" —
        // belum ada endpoint yang mewajibkannya.
        // Yang disimpan hanya hash SHA-256-nya, supaya isi tabel yang bocor
        // (phpMyAdmin, cadangan, dump .sql) tidak bisa dipakai sebagai token.
        // includes/auth.php meng-hash token kiriman dengan cara yang sama.
        try {
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $stmt_token = $conn->prepare("UPDATE guru SET auth_token = ? WHERE id = ?");
            $stmt_token->bind_param("si", $token_hash, $user['id']);
            if (!$stmt_token->execute()) {
                throw new Exception($stmt_token->error);
            }
            $stmt_token->close();
            $respons["token"] = $token;
        } catch (Throwable $e) {
            error_log("login.php: token gagal disimpan untuk guru " . $user['id'] . ": " . $e->getMessage());
        }

        http_response_code(200); // OK
        echo json_encode($respons);
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