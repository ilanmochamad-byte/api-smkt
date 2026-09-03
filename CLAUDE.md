# API Classync — REST untuk aplikasi mobile

Backend yang melayani aplikasi ClassyncApp (React Native / Expo) milik guru
SMK Terpadu Al Hasan. Dilayani di `https://api.smkt.alhasan.co.id`.

73 berkas PHP datar di akar — satu berkas per aksi, tanpa router, tanpa
controller. Penamaan konsisten: `get_*` membaca, `proses_*` dan `simpan_*`
menulis, `export_*` menghasilkan berkas.

## SISTEM INI SEDANG DIPAKAI GURU SETIAP HARI

Aplikasi dirilis lewat **Play Store dan App Store**, keduanya melewati review,
dan **tidak ada pembaruan OTA** (`expo-updates` belum terpasang). Artinya:

- Versi lama beredar berminggu-minggu setelah versi baru rilis.
- Setiap perubahan di sini **wajib kompatibel mundur** dengan aplikasi lama.
- Kesalahan di sini butuh berhari-hari untuk diperbaiki sampai ke perangkat guru.

Ubah satu berkas, buka layarnya di aplikasi, pastikan jalan, baru lanjut.

## Cara perubahan sampai ke produksi

```
laptop  →  git push  →  GitHub  →  cPanel "Update from Remote"  →  "Deploy HEAD Commit"
```

`.cpanel.yml` menyalin ke `/DATA/k1807225/public_html/api.smkt.alhasan.co.id`.
**Penyalinan tidak pernah menghapus** — menghapus berkas dari repo tidak
menghapusnya dari server; itu harus manual lewat File Manager cPanel.

## Pustaka

`vendor/` di sini **composer murni** — 9 dari 9 paket tercatat di
`installed.json`. Satu-satunya dependensi langsung adalah `google/apiclient`,
dipakai lewat `new \Google\Client` di `send_fcm_api.php`,
`admin_notifikasi.php`, dan `kirim_notifikasi_harian.php` untuk mengirim push
notification via Firebase Cloud Messaging.

Aman dipulihkan kapan saja dengan `composer install`.

(Catatan: `vendor/` di repo `classync` BERBEDA — di sana ada pustaka yang
dimasukkan manual dan `composer install` justru akan merusaknya.)

## Tugas terbesar yang direncanakan: autentikasi

**Saat ini API ini tidak punya autentikasi sama sekali.** `login.php`
memverifikasi password lalu mengembalikan objek user tanpa menerbitkan token.
32 endpoint mengidentifikasi pemanggil semata dari parameter `guru_id` yang
dikirim klien — siapa pun yang mengganti angka itu bisa membaca dan menulis
data guru mana pun.

Polanya sudah ada di kode proyek ini sendiri: `classync/api/login_guru.php`
membuat `bin2hex(random_bytes(32))`, menyimpannya ke kolom `guru.auth_token`,
dan `logout_guru.php` menghapusnya. Kolomnya sudah ada di tabel.

Migrasi dilakukan **empat fase**, dan tidak boleh dipadatkan:

- **Fase A** — `login.php` menerbitkan token dan menyertakannya di respons.
  Endpoint lain belum berubah. Aplikasi lama mengabaikan field yang tidak
  dikenalnya, jadi tidak ada yang rusak.
- **Fase B** — satu berkas `auth.php` membaca header `Authorization`,
  mencocokkan ke `auth_token`, menyediakan `$auth_guru_id`. Di 32 endpoint,
  satu baris: pakai identitas dari token bila ada, kalau tidak jatuhkan ke
  `guru_id` lama **sambil dicatat**. Saat token ada, `guru_id` dari klien
  diabaikan sepenuhnya.
- **Fase C** — rilis aplikasi v3.0 dengan `services/api.ts` terpusat dan
  interceptor yang menyisipkan header. Sekalian pasang `expo-updates`.
- **Fase D** — baca catatan fase B, hubungi guru yang belum memperbarui, lalu
  tegakkan. Mulai dari 24 endpoint tulis dan dua endpoint tersensitif
  (`get_honor.php`, `get_buku_pribadi_bk.php`).

**Penegakan ditentukan oleh angka, bukan tanggal.** Jangan menolak permintaan
tanpa token sebelum catatan menunjukkan tidak ada lagi yang memakainya.

Rekomendasi: token acak opaque, bukan JWT — bisa dicabut seketika saat guru
kehilangan HP, dan tidak ada secret tambahan yang bisa bocor.

## Temuan audit yang masih terbuka

- **Kritis** — tidak ada autentikasi (lihat di atas). Endpoint paling terdampak:
  `get_profil_guru.php:9` (mengembalikan kolom `password`), `get_honor.php:5`,
  `post_nilai.php:42`, `proses_action_piket.php:18`, `save_token.php:16`.
- **Kritis** — `proxy.php` baris 26 dan 49 menulis body mentah setiap permintaan
  ke `proxy-log.txt`, termasuk NIP dan password saat login. Baris 33-38
  meneruskan permintaan tanpa daftar putih, berfungsi sebagai open relay.
- **Kritis** — unggahan foto tanpa daftar putih ekstensi di
  `update_profil_guru.php:96`, `proses_absen_sederhana.php:65`,
  `proses_absen_bk.php:55`.
- **Tinggi** — kredensial tertanam: password DB di `includes/db.php`, shared
  secret FCM di `send_fcm_api.php:11`, dan `classync-backend/.htaccess` memuat
  `jwt_secret` yang masih berupa teks placeholder.
- **Tinggi** — `generate_modul_ajar.php:8` tertulis `require_once_ __DIR__`
  (ada garis bawah berlebih) sehingga berkas ini pasti gagal parse.
- **Sedang** — `login.php` tanpa pembatasan percobaan.
- **Sedang** — 56 dari 73 berkas membuka koneksi database sendiri padahal
  `includes/db.php` sudah menyediakan `$conn`.

## `classync-backend/` akan dicabut

Express + JWT + bcrypt, terpasang lewat Passenger, tapi **tidak dipanggil
aplikasi mana pun**. `index.js` baris 32-34 mencetak NIP dan password ke
`console.log` yang berakhir di `stderr.log`. Sudah dikecualikan dari repo ini.
Cabut dari server setelah dipastikan lewat cPanel > Setup Node.js App.

## Jangan lakukan

- Jangan menegakkan autentikasi tanpa melewati keempat fase di atas.
- Jangan menghapus dukungan parameter `guru_id` sebelum fase D selesai.
- Jangan meng-commit berkas log, `.sql`, atau apa pun yang memuat kredensial —
  riwayat Git permanen.
- Jangan menjalankan perintah sinkronisasi yang menghapus di folder produksi.
