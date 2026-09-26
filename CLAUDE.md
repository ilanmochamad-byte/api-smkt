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

**Saat ini API ini tidak punya autentikasi sama sekali.** Sejak `1b9b068`
`login.php` menerbitkan token (fase A), tapi belum ada endpoint yang
membacanya. 32 endpoint mengidentifikasi pemanggil semata dari parameter
`guru_id` yang dikirim klien — siapa pun yang mengganti angka itu bisa membaca
dan menulis data guru mana pun.

Polanya diambil dari `classync/api/login_guru.php`, yang membuat
`bin2hex(random_bytes(32))` dan menyimpannya ke `guru.auth_token`. Berkas itu
sudah dihapus dari repo dan dipindah dari server di classync `ea7900f`
(22 September 2026); isinya terbaca lewat `git show ea7900f^:api/login_guru.php`.
`logout_guru.php` di akar classync hanya `session_destroy()` dan tidak pernah
menyentuh `auth_token`. Kolomnya `varchar(255)` dengan indeks **UNIQUE** —
terverifikasi di produksi lewat `SHOW INDEX` 25 September 2026.

Migrasi dilakukan **empat fase**, dan tidak boleh dipadatkan:

- **Fase A** — `login.php` menerbitkan token dan menyertakannya di respons.
  Endpoint lain belum berubah. Aplikasi lama mengabaikan field yang tidak
  dikenalnya, jadi tidak ada yang rusak.

  **Ditulis di `1b9b068`, belum di-deploy dan belum teruji.** Keputusan yang
  diambil 25 September 2026:
  - `"token"` di tingkat atas respons, sejajar `message` dan `user`; objek
    `user` tidak berubah. Satu-satunya pemanggil, `ClassyncApp/app/index.tsx`,
    hanya membaca `response.data.user` dan `.message`.
  - Satu sesi per guru: login di perangkat lain menimpa token sebelumnya.
    Tidak mengunci apa pun — token opaque, jadi pindah ke tabel `guru_token`
    untuk banyak perangkat tidak mengubah kontrak API. Putuskan sebelum
    fase C, karena penanganan 401 di v3.0 bergantung padanya.
  - Kalau token gagal disimpan, login tetap 200 tanpa `"token"` dan galatnya
    ke `error_log`.
  - **Token sisa.** Pada 25 September 2026, 11 dari 23 baris `guru` sudah
    berisi `auth_token` — sisa `login_guru.php` lama, berformat sama persis
    dengan token fase A dan akan sah begitu fase B jalan. Diputuskan:
    `UPDATE guru SET auth_token = NULL`, lalu `SELECT COUNT(auth_token)`
    diperiksa lagi keesokan harinya **sebelum** deploy. Kalau tidak 0, masih
    ada penulis lain yang hidup (kandidat: `classync-backend/`) dan harus
    dicari dulu.
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

## Temuan audit

Diperiksa ulang butir per butir ke kode pada 25 September 2026, di `98d6691`.
"Terverifikasi di kode" artinya berkasnya dibuka dan barisnya dibaca — **bukan**
bahwa perilakunya diuji di produksi. Tanggal uji produksi di bawah dikutip dari
`CLAUDE.md` repo classync, yang mencatat pengujian itu; yang tidak tercatat di
sana, tidak disebut teruji.

### Masih terbuka

- **Kritis** — tidak ada autentikasi (lihat di atas). Belum ada satu berkas pun
  yang membaca header `Authorization` atau kolom `auth_token`; `login.php`
  menerbitkan token sejak `1b9b068` (fase A, belum di-deploy). Endpoint
  paling terdampak:
  `get_profil_guru.php:13` mengirim `SELECT *` tabel `guru` utuh sebagai
  `profil`, termasuk hash `password` dan `push_token`; `get_honor.php:5`;
  `post_nilai.php:46` mengambil `guru_id` dari tiap butir kiriman;
  `proses_action_piket.php:18` mengubah status piket siapa pun tanpa memeriksa
  apa-apa, termasuk bahwa barisnya masih `Pending`; `save_token.php:16`.
  - **Wajib ditutup sebelum fase B.** Sejak fase A, `SELECT *` itu juga
    mengirim `auth_token` yang sedang berlaku: `get_profil_guru.php:13`
    (sebagai `profil`, ke siapa pun yang menebak `guru_id`) dan
    `update_profil_guru.php:151` (sebagai `user` di respons sukses). Selama
    belum ada penegakan tidak ada kerugian tambahan — `guru_id` saja sudah
    cukup — tapi di fase B pemakaian token curian tercatat sebagai
    pemanggil sah, dan di fase D token itu membuka akses penuh. Keluarkan
    `password`, `auth_token`, `push_token`, dan `expo_push_token` dari kedua
    respons, setelah memeriksa field mana yang dibaca `mengajar.tsx`,
    `(tabs)/todo.tsx`, `(tabs)/profil.tsx`, dan layar edit profil di
    ClassyncApp.
- **Kritis** — endpoint absen aplikasi tidak memeriksa jadwal di sisi server.
  Ditemukan saat pemeriksaan ulang ini; terpisah dari butir autentikasi dan
  **tetap ada setelah token ditegakkan**, karena guru yang sah pun bisa
  mengirim `jadwal_id` sembarang.
  - `proses_absen_sederhana.php:36-37` menerima `jadwal_id` dan `tipe_absensi`
    apa adanya. `tipe_absensi` tidak disaring: nilai selain `piket` tersimpan
    langsung `Hadir` (baris 101). Karena `hitungHonorBulan()` menghitung
    mengajar lewat `JOIN jadwal_mengajar ON a.jadwal_id = jm.id`, kiriman
    `tipe_absensi=mengajar` dengan `jadwal_id` mengajar mana pun dibayar
    sebagai jam mengajar — tanpa lewat `proses_absen_mengajar.php`.
  - `proses_absen_mengajar.php` hanya menjaga duplikat (baris 44); tidak ada
    pemeriksaan bahwa jadwalnya milik guru itu, `Aktif`, dan harinya cocok
    dengan hari ini.

  Padanannya di panel web sudah ditutup di classync `2325e0a`. Di sini
  memperbaikinya butuh memeriksa dulu nilai apa saja yang dikirim tiap versi
  aplikasi yang masih beredar.
- **Tinggi** — `classync-backend/.htaccess` memuat `jwt_secret` placeholder.
  Folder itu dikecualikan dari repo (`.gitignore:27`), jadi keadaannya di
  server **perlu diverifikasi** lewat cPanel; salinan lokal bertanggal Oktober
  2025 masih memuat barisnya. Tertutup bersamaan dengan pencabutan di bagian
  `classync-backend/` di bawah.
- **Sedang** — verifikasi TLS dimatikan di dua pengirim FCM:
  `send_fcm_api.php:150` dan `admin_notifikasi.php:72` memasang
  `CURLOPT_SSL_VERIFYPEER false` — bearer token Google dikirim tanpa
  memeriksa sertifikat lawan bicara. `dd7774b` menyalakannya kembali hanya di
  `kirim_notifikasi_harian.php`. Apakah `admin_notifikasi.php` di repo ini
  masih dipakai **perlu diverifikasi**.
- **Sedang** — notifikasi iOS berjalan lewat penanganan sementara
  `includes/pengirim_apns.php` (`196f452`, `181d5d4`). Ia ditulis untuk
  dibuang setelah putusan A/B di aplikasi; rinciannya di `CLAUDE.md` classync.
- **Sedang** — `login.php` tanpa pembatasan percobaan. (`connect_error`
  mentah di baris 18 lama hilang di `1b9b068` bersama koneksi keduanya;
  `includes/db.php` menjawab dengan pesan generik.)
- **Sedang** — 55 dari 70 berkas di akar membuka koneksi sendiri dengan
  `new mysqli`, padahal semuanya juga memuat `includes/db.php` yang sudah
  menyediakan `$conn` — dua koneksi per permintaan.
- **Rendah** — `generate_modul_ajar.php:8` tertulis `require_once_ __DIR__`
  dan gagal `php -l`, pecah sejak commit pertama. Semula berbobot Tinggi;
  diturunkan karena tidak ada pemanggil di ClassyncApp, baik di HEAD maupun
  di seluruh riwayat Git-nya (`git log --all -S`). Kandidat penghapusan.
- **Rendah** — blok `catch` di keempat endpoint unggah mengirim
  `$e->getMessage()` mentah ke aplikasi (`proses_absen_mengajar.php:131`,
  `proses_absen_sederhana.php:125`, `proses_absen_bk.php:152`,
  `update_profil_guru.php:170`). Biasanya pesan aplikasi yang berguna, tapi
  eksepsi basis data bocor lewat jalur yang sama.
- **Rendah** — `save_token.php` menulis ke `expo_push_token`, bukan
  `push_token`, tanpa autentikasi. Tidak ada pemanggil di ClassyncApp, baik di
  HEAD maupun di seluruh riwayatnya. Layak dihapus.
- **Rendah** — nama berkas unggahan tanpa komponen acak di tiga endpoint:
  `proses_absen_sederhana.php:86`, `proses_absen_bk.php:78`, dan
  `update_profil_guru.php:115` memakai `guru_id` + `time()` saja, sehingga dua
  unggahan guru yang sama pada detik yang sama saling menimpa. `caa1fcf` hanya
  mengganti `rand()` di `proses_absen_mengajar.php` — satu-satunya yang
  memakainya; klaim bahwa keempat endpoint kini memakai `random_bytes()`
  keliru.

### Sudah ditutup

- ~~`proxy.php` mencatat password dan meneruskan tanpa saringan~~ — commit
  `a6822aa`. Ia meneruskan permintaan apa pun ke domain API sendiri dengan
  CORS `*`, tanpa daftar putih endpoint, dan menulis body mentah setiap
  permintaan ke `proxy-log.txt` — termasuk NIP dan password saat login. Dua
  halaman diagnostik ikut dihapus: `cek_config.php` dan `cek_path.php`, yang
  menampilkan jalur direktori server dan status berkas konfigurasi ke siapa
  pun. Tidak ada pemanggil di ketiga repo, juga tidak di seluruh riwayat Git
  ClassyncApp dan classync.

  Log server sebelum dihapus: 189 panggilan pada 13 dan 24 September 2026,
  semuanya `GET /proxy.php` atau `//proxy.php` tanpa endpoint tujuan, dan nol
  baris `Data:` — pemindai, bukan klien. Log sebelum 13 September sudah
  terhapus dan, sebelum blok `FilesMatch` dipasang hari itu, bisa diunduh
  siapa pun. Apakah password pernah tercatat di sana **tidak terverifikasi**;
  tidak ada versi aplikasi atau panel web yang pernah memanggil proxy.

  Keempat berkas dihapus manual dari server 25 September 2026. Terverifikasi:
  ketiga alamat menjawab 404, `login.php` tetap 400.

- ~~Kunci duplikat piket di `proses_absen_sederhana.php`~~ — commit `1e0194f`.
  Piket kini dicek per guru per hari tanpa `jadwal_id` (baris 52-53), ekskul
  tetap per jadwal (baris 56); penolakan tetap HTTP 409. Teruji di produksi
  24 September 2026 menurut `CLAUDE.md` classync.
- ~~`kirim_notifikasi_harian.php` bisa dipicu lewat URL dan buta terhadap
  iOS~~ — commit `dd7774b`, penjaganya dibetulkan di `548f199`. Penjaga cron
  memeriksa `isset($_SERVER['REQUEST_METHOD'])` (baris 21), bukan
  `php_sapi_name()`, karena `/usr/bin/php` di server ini `php-cgi`. Token
  dipilah lewat `includes/pengirim_apns.php`, kegagalan dicatat per guru
  (baris 216), dan tidak ada lagi `CURLOPT_SSL_VERIFYPEER false` di berkas
  ini. Teruji di produksi 22 September 2026 menurut `CLAUDE.md` classync.
- ~~Tautan-dalam notifikasi iOS tidak terbaca~~ — commit `2a4d1a5`. `screen`
  kini di bawah kunci `body` (`includes/pengirim_apns.php:173-174`), karena
  `expo-notifications` hanya mengisi `content.data` dari sana. Teruji di
  iPhone 22 September 2026 menurut `CLAUDE.md` classync. Android dan
  peluncuran dari keadaan mati belum — yang kedua perlu rilis aplikasi.
- ~~Tidak ada batas ukuran unggahan~~ — commit `4ee3eb3`.
  `includes/pesan_unggah.php` membatasi 8 MB dan dipanggil keempat endpoint
  sebelum `getimagesize()`; galat unggah kini dibedakan dari "tidak ada foto".
  Lapis servernya (`upload_max_filesize` 8M, `post_max_size` 16M) ada di
  MultiPHP INI Editor, bukan di repo.
- ~~Dua peringatan PHP~~ — commit `139c9ea`. `includes/db.php` memakai
  `$_SERVER['REQUEST_METHOD'] ?? ''`; `proses_absen_harian.php:97` menyetel
  `$is_disiplin = true`, sehingga guru bertunjangan transport Rp 0 tidak lagi
  menerima tuduhan datang di luar jam disiplin.
- ~~Kunci rahasia FCM tertulis di kode~~ — commit `4958e4d`.
  `send_fcm_api.php:18` membaca `/DATA/k1807225/config/fcm-classync.php`;
  kunci dicocokkan dengan `hash_equals()` terhadap **daftar** kunci sah, dan
  daftar kosong menolak semuanya. Kunci lama tetap permanen di riwayat Git;
  rotasinya (21 September 2026) tercatat di `CLAUDE.md` classync dan tidak
  bisa diverifikasi dari kode.
- ~~`proses_absen_bk.php` tanpa penjaga duplikat~~ — commit `a6cd9d5`.
  Kuncinya guru + `CURDATE()` + `topik_tema` + `sasaran_layanan`
  (baris 89-114). Pemeriksaan biasa, bukan kuncian baris — celah untuk dua
  kiriman yang benar-benar bersamaan masih ada, dan itu disengaja; alasannya
  di komentar kodenya.
- ~~`proses_absen_bk.php` mencatat absensi walau unggah foto gagal~~ — commit
  `98bdfdb`. Foto wajib (baris 55-58), dan foto yang telanjur dipindah dihapus
  kalau `INSERT` gagal (baris 148).
- ~~`proses_approval_absensi.php` tidak idempoten~~ — commit `9cca477`.
  Transaksi, `SELECT ... FOR UPDATE` (baris 124), `UPDATE ... AND status =
  'Pending'` dengan `affected_rows` sebagai penentu (baris 274-277), penjaga
  duplikat berkunci per jenis, `status_jadwal = 'Aktif'` di ketiga pencarian
  jadwal, `ORDER BY (jam_mulai = ?) DESC, id ASC` untuk ekskul (baris 218),
  dan `commit()` sebelum panggilan FCM (baris 322). Batasan unik di basis
  data belum dipasang; lihat `CLAUDE.md` classync.
- ~~`guru_id` mentah di nama berkas~~ — commit `caa1fcf`. Di-cast `(int)` di
  titik masuk keempat endpoint unggah.
- ~~Regresi GIF~~ — commit `e81f6f9`. Keempat daftar putih kini memuat
  `IMAGETYPE_GIF`, sesuai yang diterima `absen_bk.tsx`.
- ~~Penghapusan berkas arbitrer di `update_profil_guru.php`~~ — commit
  `188c705`. `foto_lama` kiriman diabaikan; foto lama dibaca dari basis data
  (baris 59) dan dihapus hanya kalau `realpath()`-nya ada di dalam `uploads/`
  (baris 123-128).
- ~~Unggahan foto tanpa daftar putih ekstensi~~ — commit `6c77656`. Ekstensi
  diambil dari tipe yang terdeteksi `getimagesize()` (`$info[2]`), bukan dari
  nama kiriman, di keempat endpoint: `update_profil_guru.php:108`,
  `proses_absen_sederhana.php:79`, `proses_absen_bk.php:71`,
  `proses_absen_mengajar.php:62`.
- ~~`display_errors` menyala~~ — commit `6542b62`. Tidak ada lagi berkas yang
  menyetelnya ke `1`; 45 berkas memakai `'0'`, delapan lainnya `0`.
  `error_reporting(E_ALL)` sengaja dibiarkan supaya pencatatan ke log tetap
  jalan.
- ~~SQL injection di `get_monitoring_absensi.php`~~ — commit `e6a567f`.
  Kedua kueri berparameter memakai prepared statement (baris 58-62 dan
  95-99), dan galatnya ke `error_log`, bukan ke pemanggil. Sapuan ulang
  terbatas: semua `->query()` yang menyisipkan variabel di repo ini hanya
  menyisipkan nilai yang sudah di-cast `(int)`. Kueri yang dirakit lalu
  di-`prepare()` tidak ikut disapu.
- ~~Password basis data tertulis di `includes/db.php`~~ — commit `e625537`.
  Dibaca dari `/DATA/k1807225/config/db-classync.php` dengan pola
  `is_readable()` lebih dulu. Password lama tetap permanen di riwayat Git;
  penggantian penggunanya (September 2026) tercatat di `CLAUDE.md` classync
  dan tidak bisa diverifikasi dari kode.

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
