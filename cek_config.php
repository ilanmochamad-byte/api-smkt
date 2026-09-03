<?php
echo "<h1>Tes Diagnostik File Konfigurasi</h1>";

// Path yang kita gunakan di skrip generate_modul_ajar.php
$config_path = __DIR__ . '/../../config-api.php';

echo "<p>Mencoba mengakses file di path: <strong>" . realpath(dirname($config_path)) . '/' . basename($config_path) . "</strong></p>";

echo "<hr>";

if (file_exists($config_path)) {
    echo "<p style='color:green; font-weight:bold;'>HASIL: File config.php DITEMUKAN.</p>";

    if (is_readable($config_path)) {
        echo "<p style='color:green; font-weight:bold;'>STATUS: File bisa dibaca.</p>";
        echo "<p><strong>KESIMPULAN:</strong> Masalah kemungkinan besar bukan pada file config.php, tetapi pada saat cURL menghubungi API Google. Pastikan server Anda diizinkan untuk membuat koneksi keluar (outbound connection).</p>";
    } else {
        echo "<p style='color:red; font-weight:bold;'>STATUS: File ditemukan, TAPI TIDAK BISA DIBACA.</p>";
        echo "<p><strong>SOLUSI:</strong> Buka File Manager, cari file 'config.php' di direktori utama (di atas public_html), klik kanan > 'Change Permissions', dan pastikan izinnya adalah <strong>644</strong>.</p>";
    }
} else {
    echo "<p style='color:red; font-weight:bold;'>HASIL: File config.php TIDAK DITEMUKAN di path tersebut.</p>";
    echo "<p><strong>SOLUSI:</strong> Pastikan file 'config.php' benar-benar ada di direktori utama hosting Anda (di atas public_html) dan bukan di dalam folder lain.</p>";
}
?>