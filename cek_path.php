<?php
echo "<pre>"; // Agar output lebih mudah dibaca

// Path absolut ke direktori skrip ini
$script_dir = __DIR__;
echo "Direktori skrip API saat ini (__DIR__):\n$script_dir\n\n";

// Path tujuan upload yang kita coba di skrip absensi
$target_dir = "/DATA/k1807225/public_html/smkt.alhasan.co.id/classync/uploads/absensi/";
echo "Path tujuan upload yang dicoba:\n$target_dir\n\n";

echo "--- HASIL PENGECEKAN ---\n";

// Cek apakah direktori tujuan ada
if (is_dir($target_dir)) {
    echo "STATUS: Folder tujuan DITEMUKAN.\n";
    
    // Cek apakah direktori tujuan bisa ditulisi oleh PHP
    if (is_writable($target_dir)) {
        echo "IZIN: Folder tujuan BISA DITULISI (WRITABLE).\n\n";
        echo "KESIMPULAN: Seharusnya tidak ada masalah path atau izin. Masalahnya mungkin lebih kompleks (konfigurasi keamanan server seperti 'open_basedir'). Ini perlu ditanyakan ke support hosting.";
    } else {
        echo "IZIN: Folder tujuan TIDAK BISA DITULISI (NOT WRITABLE).\n\n";
        echo "KESIMPULAN: Masalah ada pada izin folder. Meskipun sudah 775, user server PHP (misalnya 'nobody') tidak diizinkan menulis di sana. Hubungi support hosting Anda dan tunjukkan pesan ini.";
    }
} else {
    echo "STATUS: Folder tujuan TIDAK DITEMUKAN.\n\n";
    echo "KESIMPULAN: Path absolut yang kita gunakan salah. Path yang benar kemungkinan besar bisa dibangun dari 'Direktori skrip API saat ini'. Coba samakan strukturnya.";
}
echo "</pre>";
?>