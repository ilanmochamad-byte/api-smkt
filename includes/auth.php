<?php
// auth.php - Fase B migrasi autentikasi (lihat CLAUDE.md).
//
// guru_id_pemanggil() menentukan siapa pemanggil sebuah endpoint:
//   - Header "Authorization: Bearer <token>" yang cocok dengan guru.auth_token
//     → guru dari token. guru_id kiriman klien diabaikan sepenuhnya.
//   - Tanpa header → guru_id kiriman klien, seperti sebelum fase B.
//   - Header ada tapi token tidak cocok → juga jatuh ke guru_id kiriman.
//     Kasus sahnya: HP yang tokennya sudah ditimpa login di HP lain.
//
// BELUM ADA YANG DITOLAK. Setiap pemakaian dijumlahkan per hari di tabel
// catatan_autentikasi; penegakan (fase D) menunggu angka di tabel itu
// menunjukkan tidak ada lagi yang memanggil tanpa token.
//
// guru.auth_token menyimpan hash('sha256', token), bukan token mentah —
// pasangannya di login.php.

function token_dari_header(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? null;

    if ($header === null && function_exists('getallheaders')) {
        foreach (getallheaders() as $nama => $nilai) {
            if (strcasecmp($nama, 'Authorization') === 0) {
                $header = $nilai;
                break;
            }
        }
    }

    if ($header === null || trim($header) === '') {
        return null;
    }
    if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $m)) {
        return $m[1];
    }
    // Header ada tapi bukan Bearer: dianggap token yang tidak sah.
    return '';
}

function guru_id_pemanggil(mysqli $conn, $guru_id_kiriman): int
{
    static $hasil = null;
    if ($hasil !== null) {
        return $hasil;
    }

    $guru_id_kiriman = (int)$guru_id_kiriman;
    $token = token_dari_header();

    if ($token === null) {
        $hasil = $guru_id_kiriman;
        catat_autentikasi($conn, 'guru_id', $hasil);
        return $hasil;
    }

    $guru_id_token = cari_guru_dari_token($conn, $token);

    if ($guru_id_token === null) {
        $hasil = $guru_id_kiriman;
        catat_autentikasi($conn, 'token_tidak_sah', $hasil);
        return $hasil;
    }

    $hasil = $guru_id_token;
    $cara = ($guru_id_kiriman !== 0 && $guru_id_kiriman !== $guru_id_token)
        ? 'token_beda_guru_id'
        : 'token';
    catat_autentikasi($conn, $cara, $hasil);
    return $hasil;
}

// Untuk endpoint yang tidak menerima guru_id sama sekali (inventaris K4)
// atau menerimanya per butir (K2): identitas HANYA dari token.
//   - null → tidak ada token yang sah; endpoint berjalan persis seperti
//     sebelum fase B. Dicatat sebagai 'tanpa_token' atau 'token_tidak_sah'.
//   - int  → guru pemilik token; endpoint membatasi diri ke data guru itu.
// Penolakan hanya pernah terjadi pada permintaan BERTOKEN yang menyentuh data
// guru lain — aplikasi yang beredar belum mengirim token, jadi tidak terkena.
function guru_id_dari_token(mysqli $conn): ?int
{
    static $sudah = false, $hasil = null;
    if ($sudah) {
        return $hasil;
    }
    $sudah = true;

    $token = token_dari_header();
    if ($token === null) {
        catat_autentikasi($conn, 'tanpa_token', 0);
        return $hasil = null;
    }

    $hasil = cari_guru_dari_token($conn, $token);
    if ($hasil === null) {
        catat_autentikasi($conn, 'token_tidak_sah', 0);
    } else {
        catat_autentikasi($conn, 'token', $hasil);
    }
    return $hasil;
}

function cari_guru_dari_token(mysqli $conn, string $token): ?int
{
    // Token terbitan login.php selalu 64 karakter hex; yang lain tidak perlu
    // sampai ke basis data.
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    try {
        $stmt = $conn->prepare("SELECT id FROM guru WHERE auth_token = ? LIMIT 1");
        $token_hash = hash('sha256', $token);
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $baris = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $baris ? (int)$baris['id'] : null;
    } catch (Throwable $e) {
        error_log("auth.php: pencarian token gagal: " . $e->getMessage());
        return null;
    }
}

// Pencatatan tidak boleh menggagalkan permintaan: galat apa pun cukup ke
// error_log. Tanggal dari PHP (Asia/Jakarta, disetel includes/db.php), bukan
// CURDATE(), karena zona waktu sesi MySQL tidak disetel.
function catat_autentikasi(mysqli $conn, string $cara, int $guru_id): void
{
    try {
        $endpoint = basename($_SERVER['SCRIPT_NAME'] ?? 'tidak_diketahui');
        $tanggal = date('Y-m-d');
        $sekarang = date('Y-m-d H:i:s');
        $stmt = $conn->prepare(
            "INSERT INTO catatan_autentikasi (tanggal, endpoint, cara, guru_id, jumlah, pertama, terakhir)
             VALUES (?, ?, ?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE jumlah = jumlah + 1, terakhir = ?"
        );
        $stmt->bind_param("sssisss", $tanggal, $endpoint, $cara, $guru_id, $sekarang, $sekarang, $sekarang);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
    } catch (Throwable $e) {
        error_log("auth.php: gagal mencatat ($cara, guru $guru_id): " . $e->getMessage());
    }
}
