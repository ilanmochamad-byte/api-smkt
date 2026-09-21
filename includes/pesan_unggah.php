<?php
// pesan_unggah.php — batas ukuran unggahan dan terjemahan kode galatnya.
//
// Dipakai keempat endpoint unggah repo ini. Dipisahkan ke sini karena
// menurunkan upload_max_filesize di server (100M -> 8M) membuat
// UPLOAD_ERR_INI_SIZE jauh lebih sering terjadi, dan tiga dari empat endpoint
// dulu menjawab SETIAP galat unggah dengan "Foto bukti wajib diupload." —
// pesan yang membingungkan guru yang fotonya jelas terlampir di layar.

// Batas dipilih dari sensus 5.036 foto produksi, 21 September 2026:
// median 0,03 MB, p95 2,60 MB, p99 4,13 MB, terbesar 23,54 MB. Hanya SATU
// berkas yang melewati 8 MB. Jadi batas ini memberi margin dua kali lipat di
// atas p99 tanpa memotong apa pun yang sah.
//
// Ini lapis kedua, bukan pertahanan utama: saat baris pemeriksanya jalan, PHP
// sudah menerima berkasnya. Yang menahan di depan adalah upload_max_filesize
// di MultiPHP INI Editor. Gunanya di sini memberi pesan yang bisa
// ditindaklanjuti, dan tetap menahan kalau batas server suatu saat berubah.
if (!defined('BATAS_UNGGAH_BYTE')) {
    define('BATAS_UNGGAH_BYTE', 8 * 1024 * 1024);
}

if (!function_exists('pesanGagalUnggah')) {
    // $label menyesuaikan konteks: "Foto bukti" untuk absensi, "Foto profil"
    // untuk update_profil_guru.php. Bentuk pesan UPLOAD_ERR_NO_FILE sengaja
    // dipertahankan persis seperti sebelumnya supaya aplikasi versi lama yang
    // membacanya tidak berubah perilakunya.
    function pesanGagalUnggah($kode, $label = 'Foto bukti') {
        switch ($kode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return $label . " gagal diunggah: ukuran berkasnya terlalu besar.";
            case UPLOAD_ERR_PARTIAL:
                return $label . " gagal diunggah: pengiriman terputus. Silakan coba lagi.";
            case UPLOAD_ERR_NO_FILE:
                return $label . " wajib diupload.";
            default:
                return $label . " gagal diunggah. Silakan coba lagi atau hubungi admin.";
        }
    }
}

if (!function_exists('periksaUkuranUnggah')) {
    // Melempar Exception kalau berkas melewati batas. Dipanggil SEBELUM
    // getimagesize(), karena getimagesize hanya membaca header dan tidak
    // peduli berapa besar sisa berkasnya.
    function periksaUkuranUnggah($berkas, $label = 'Foto bukti') {
        $ukuran = isset($berkas['size']) ? (int)$berkas['size'] : 0;
        if ($ukuran > BATAS_UNGGAH_BYTE) {
            throw new Exception(
                $label . " terlalu besar (" . round($ukuran / 1048576, 1)
                . " MB). Maksimal " . (BATAS_UNGGAH_BYTE / 1048576) . " MB."
            );
        }
    }
}
