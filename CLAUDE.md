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

Menguji dengan `curl`: LiteSpeed menolak User-Agent bawaan curl dengan 403
halaman HTML, sebelum PHP sempat jalan — baik dari laptop maupun dari cPanel
Terminal. Selalu pakai `-A 'Mozilla/5.0'`. Uji dari Terminal Mac (zsh, ada
`jq`); cPanel Terminal memakai bash dan tidak punya `jq`.

Menguji dari aplikasi: dashboard membaca SecureStore `userData` hanya sekali
saat tab dibuat (`dashboard.tsx:417-430`), jadi kembali dari layar lain
menampilkan salinan lama di memori. Perubahan yang sampai ke `userData`
baru terlihat setelah aplikasi **ditutup paksa lalu dibuka ulang**, atau di
layar tumpukan yang membaca ulang setiap dibuka (mis. Buat Jurnal).

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

**Saat ini API ini belum menegakkan autentikasi.** Sejak `1b9b068`
`login.php` menerbitkan token (fase A), dan fase B sedang berjalan. 38
endpoint mengidentifikasi pemanggil semata dari `guru_id` kiriman klien —
siapa pun yang mengganti angka itu bisa membaca dan menulis data guru mana
pun. 15 lainnya menyentuh data guru tanpa identitas pemanggil sama sekali
(lihat inventaris di fase B).

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

  **Selesai — `1b9b068`, di-deploy dan teruji di produksi 26 September
  2026.** `grep -c auth_token` di `login.php` server = 1. Login benar
  menjawab 200 dengan `user` berfield sama seperti sebelumnya (`id`, `nama`,
  `nip`, `foto_profil`, `is_bk`) plus `token` 64 hex di tingkat atas;
  password salah 401 tanpa token. Setelah tiap login tepat satu baris
  `guru` bertoken, dan awal tokennya berganti di setiap login (terlihat dua
  kali pergantian di phpMyAdmin). Login dari ClassyncApp versi yang beredar
  tetap normal.

  Keputusan yang diambil 25 September 2026:
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
    dicari dulu. Hasil 26 September 2026 pukul 07.33, sebelum deploy: **0**
    — tidak ada penulis lain; sejak itu `login.php` satu-satunya sumber
    token.
  - Sejak `983ee19` kolom `auth_token` menyimpan `hash('sha256', token)`,
    bukan token mentah — isi tabel yang bocor (phpMyAdmin, cadangan, dump
    `.sql`) tidak bisa dipakai sebagai token. Diubah sebelum fase C karena
    saat itu belum ada klien yang memakai token.
- **Fase B** — `includes/auth.php` membaca header `Authorization`,
  mencocokkan ke `auth_token`, dan di setiap endpoint satu baris: pakai
  identitas dari token bila ada, kalau tidak jatuhkan ke `guru_id` lama
  **sambil dicatat**. Saat token ada, `guru_id` dari klien diabaikan
  sepenuhnya.

  **Sedang berjalan.** Keputusan 26 September 2026:
  - `guru_id_pemanggil($conn, $guru_id_kiriman)` di `includes/auth.php`
    (bukan di akar, supaya tidak menjadi endpoint). Pemakaian di endpoint:
    `$guru_id = guru_id_pemanggil($conn, $_GET['guru_id'] ?? 0);` — sumber
    kirimannya berbeda-beda (`$_GET`, `$_POST`, `$data[...]`, `$data->...`),
    jadi "satu baris" berarti satu panggilan fungsi, bukan baris yang sama.
    Kirimannya di-cast `(int)` di dalam fungsi.
  - Token yang dikirim tapi tidak cocok **tidak ditolak**: jatuh ke
    `guru_id` kiriman. Kasus sahnya setelah fase C: HP yang tokennya
    ditimpa login di HP lain.
  - Dicatat di tabel `catatan_autentikasi`, dijumlah per `(tanggal,
    endpoint, cara, guru_id)`. `cara`: `token`, `guru_id` (tanpa header),
    `token_tidak_sah`, `token_beda_guru_id` (token sah tapi `guru_id`
    kiriman lain — tanda bug aplikasi atau coba-coba). Tanggal dari PHP
    (Asia/Jakarta), bukan `CURDATE()`. Gagal mencatat tidak menggagalkan
    permintaan. Tabel dibuat manual di phpMyAdmin:
    ```sql
    CREATE TABLE catatan_autentikasi (
      tanggal  DATE         NOT NULL,
      endpoint VARCHAR(64)  NOT NULL,
      cara     VARCHAR(20)  NOT NULL,
      guru_id  INT          NOT NULL,
      jumlah   INT UNSIGNED NOT NULL DEFAULT 0,
      pertama  DATETIME     NOT NULL,
      terakhir DATETIME     NOT NULL,
      PRIMARY KEY (tanggal, endpoint, cara, guru_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ```
  - Diluncurkan bergelombang; tiap gelombang di-deploy dan diuji sebelum
    lanjut.

  Gelombang:
  1. `983ee19` — `includes/auth.php`, hash token di `login.php`, dan
     `get_unread_count.php` (dipanggil setiap dashboard dibuka; sekaligus
     membuktikan LiteSpeed meneruskan header `Authorization`). **Teruji di
     produksi 26 September 2026:** dengan token, tanpa header, dan dengan
     token palsu ketiganya 200 dengan jawaban sama, dan tercatat sebagai
     `token`, `guru_id`, `token_tidak_sah` untuk guru 9. Baris `token` itu
     juga membuktikan kolomnya berisi hash — `auth.php` hanya mencari
     `hash('sha256', token)`. Header sampai ke PHP tanpa aturan `.htaccess`.
  2. Gelombang 2 — 22 endpoint K1 yang membaca `guru_id` lewat `$_GET`
     (semua K1 `$_GET` di inventaris selain `get_unread_count`). Semuanya
     sudah punya `$conn` sebelum baris itu. Tiga yang dulu tanpa `(int)`
     (`export_rekap_absen_harian`, `get_riwayat_jurnal`,
     `get_riwayat_pengajuan`) sudah mengikat `bind_param("i")`, jadi cast
     di dalam fungsi tidak mengubah perilakunya. Uji: tiap endpoint dipanggil
     tanpa header dengan `guru_id` guru uji dan dengan token dengan
     `guru_id=0`; kedua jawaban harus identik. **Belum di-deploy.**

  **Inventaris** (26 September 2026, semua 70 berkas di akar dibaca):

  - **K1 — `guru_id` = pemanggil (37), sasaran pola fase B.** Lewat
    `$_GET`: `cek_jadwal_sekarang`, lima `export_*` (`kehadiran_guru`,
    `kehadiran_siswa`, `nilai`, `penilaian_siswa`, `rekap_absen_harian`),
    `get_gallery` (hanya untuk tanda like; 0 diterima), `get_honor`,
    `get_jadwal_guru_unik`, `get_jadwal_harian`, `get_jurnal_harian`,
    `get_mapel_guru`, `get_notifikasi`, `get_profil_guru`,
    `get_progress_mengajar`, `get_rekap_absensi_kelas`,
    `get_rekap_penilaian_kelas`, `get_riwayat_absensi`,
    `get_riwayat_jurnal`, `get_riwayat_pengajuan`, `get_riwayat_refleksi`,
    `get_status_absensi_harian`, `get_unread_count`. Lewat `$_POST`:
    `handle_like`, `handle_dislike`, `proses_absen_bk`,
    `proses_absen_mengajar`, `proses_absen_sederhana`,
    `update_profil_guru`. Lewat body JSON: `ajukan_absensi`,
    `proses_absen_harian`, `proses_absen_hp` (guru = petugas pencatat),
    `simpan_jurnal` (`$data->guru_id`), `simpan_push_token`,
    `simpan_refleksi`, `update_jurnal`, `save_token`.
    `ajukan_absensi`, `simpan_jurnal`, dan `save_token` membaca `guru_id`
    dua kali (validasi lalu bind) — ganti keduanya dengan satu variabel.
  - **K2 — `guru_id` per butir (1):** `post_nilai.php:46`, di dalam
    `foreach`. Tiap butir ditimpa dengan identitas pemanggil.
  - **K3 — bertindak atas guru lain (9), butuh pemeriksaan peran, bukan
    pola fase B:** `admin_notifikasi`, `proses_approval_absensi`,
    `proses_action_piket`, `proses_approval`, `get_approval_absen`,
    `get_approval_piket`, `get_pengajuan_pending`, `get_laporan_honor`,
    `get_riwayat_absen_harian`. Tidak satu pun menerima identitas
    pemanggil; pembatasannya hanya di menu aplikasi (`restrictedNip` di
    `constants/menuGuru.js`). Baru bisa dikunci setelah token ditegakkan.
  - **K4 — mengubah/membaca data orang lewat id rekaman (6), tanpa
    pemeriksaan pemilik:** `delete_jurnal` (`jurnal_id`),
    `hapus_notifikasi` dan `tandai_baca` (`notifikasi_id`),
    `get_detail_jurnal` (`jurnal_id`), `proses_konseling_individu` dan
    `proses_konseling_kelompok` (`jurnal_bk_id`, isinya `absensi.id`).
    Aplikasi tidak mengirim `guru_id` ke keenamnya, jadi di fase B cukup
    tambahkan `AND guru_id = ?` **hanya bila token ada**.
  - **K5 — tidak terkait identitas (17):** `login`, `send_fcm_api`,
    `kirim_notifikasi_harian`, `keuangan_helper`, `generate_modul_ajar`,
    `post_comment` (stub), `get_comments`, `get_schedules`, `get_siswa`,
    `get_siswa_by_kelas`, `get_absen_hp`, `get_monitoring_absensi`,
    `export_rekap_absen_hp`, `export_rekap_bulanan_siswa`,
    `get_detail_absensi_siswa`, `get_riwayat_penilaian`,
    `get_buku_pribadi_bk` (data siswa; butuh pemeriksaan peran BK yang kini
    hanya di klien).

  Tanpa pemanggil di ClassyncApp (HEAD maupun riwayat): `admin_notifikasi`,
  `generate_modul_ajar`, `get_comments`, `get_jadwal_harian`,
  `post_comment`, `save_token`.
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

- **Kritis** — autentikasi belum ditegakkan (lihat di atas). `login.php`
  menerbitkan token sejak `1b9b068` (fase A, di produksi 26 September 2026);
  `includes/auth.php` membacanya sejak `983ee19` (fase B, bergelombang).
  Endpoint paling terdampak:
  `get_profil_guru.php:13` mengirim data profil guru mana pun;
  `get_honor.php:5`;
  `post_nilai.php:46` mengambil `guru_id` dari tiap butir kiriman;
  `proses_action_piket.php:18` mengubah status piket siapa pun tanpa memeriksa
  apa-apa, termasuk bahwa barisnya masih `Pending`; `save_token.php:16`.
  - Syarat "tidak ada endpoint yang mengirim `auth_token`" sebelum fase B
    sudah terpenuhi: `get_profil_guru.php` (`c5e8d0a`) dan
    `update_profil_guru.php` (`e686dfd`) — satu-satunya yang mengirim
    `SELECT *` tabel `guru`. Endpoint baru yang membaca tabel `guru` jangan
    memakai `SELECT *`.
- **Kritis** — honor dan uang transport **seluruh guru** terbuka ke siapa
  pun, tanpa parameter identitas apa pun. `get_laporan_honor.php` mengulang
  semua baris `guru` (baris 12) dan memanggil `hitungHonorBulan()` untuk
  masing-masing; `get_riwayat_absen_harian.php:26-29` mengirim jam masuk,
  jam pulang, dan `bonus` semua guru per bulan. Lebih parah daripada
  `get_honor.php`, karena tidak perlu menebak `guru_id`. Pemanggilnya layar
  kepala sekolah (`laporan_honor.tsx`, `riwayat_absen_admin.tsx`), yang
  tidak mengirim identitas; pembatasan hanya di menu aplikasi. Baru bisa
  dikunci setelah token ditegakkan — masuk gelombang pertama fase D.
  Terverifikasi dari kode; endpoint-nya sengaja tidak dipanggil untuk uji.
- **Sedang** — enam endpoint mengubah atau membaca data milik guru lewat id
  rekaman tanpa memeriksa pemiliknya: `delete_jurnal.php`,
  `hapus_notifikasi.php` (versi berpemeriksa dikomentari di baris 37-40),
  `tandai_baca.php`, `get_detail_jurnal.php`,
  `proses_konseling_individu.php`, `proses_konseling_kelompok.php`. Siapa
  pun bisa menghapus jurnal atau notifikasi orang lain dengan menebak
  id-nya. Rencana: `AND guru_id = ?` bila token ada (inventaris K4).
- **Rendah** — tombol "tandai semua dibaca" rusak sejak ClassyncApp
  `7923553` (13 Juli 2026): `notifikasi.tsx:136` memanggil
  `tandai_baca_semua.php`, yang tidak ada di repo ini maupun di riwayat
  Git-nya. Produksi menjawab 404 (26 September 2026).
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

- ~~`user` dari `update_profil_guru.php` tanpa `nama`~~ — commit `773e0a8`.
  `edit_profil.tsx:116` menimpa SecureStore `userData` dengan baris tabel
  `guru`, yang punya `nama_guru` tapi tidak punya `nama` seperti objek dari
  `login.php`. Delapan layar membaca `userData.nama`: sapaan dashboard dan
  `ProfileCard` kosong, kolom nama di Buat Jurnal kosong, dan PDF riwayat
  jurnal, penilaian, ekspor, serta export laporan mencetak `undefined` atau
  "Guru" — sampai guru login ulang. Semuanya tampilan; tidak ada yang
  mengirim `nama` ke server. `nama` kini salinan `nama_guru` (baris 164-168).
  Teruji di produksi 26 September 2026: setelah simpan profil dan aplikasi
  ditutup paksa lalu dibuka ulang, sapaan dashboard menampilkan nama dan
  kolom nama di Buat Jurnal terisi. `AsyncStorage 'nama_guru'` dari login
  tetap tidak diperbarui setelah simpan profil; itu perlu perubahan
  aplikasi.

- ~~`update_profil_guru.php` mengirim kredensial dalam `user`~~ — commit
  `e686dfd`. Respons sukses mengirim `SELECT *` tabel `guru` (baris 151),
  termasuk hash `password`, `push_token`, `expo_push_token`, dan sejak
  fase A `auth_token` yang sedang berlaku, ke siapa pun yang mengirim
  `guru_id` orang lain. Keempatnya kini di-`unset` (baris 162-164); field
  lain tidak berubah. `edit_profil.tsx:116` menyimpan `user` ini utuh ke
  SecureStore `userData`, yang dibaca sekitar 15 layar dan `_layout.tsx`;
  tidak satu pun versi dalam riwayat Git ClassyncApp membaca keempat field
  itu dari objek mana pun, dan `userData` tidak pernah diteruskan utuh ke
  permintaan lain. Teruji di produksi 26 September 2026 lewat aplikasi yang
  beredar: simpan profil tanpa perubahan berhasil, aplikasi dibuka ulang
  langsung ke dashboard, dan Mengajar, Riwayat, Absen Harian, serta Input
  Nilai memuat data. Isi respons sendiri tidak diperiksa — tidak diuji
  dengan curl karena endpoint ini menulis.
- ~~`get_profil_guru.php` mengirim kredensial dalam `profil`~~ — commit
  `c5e8d0a`. `SELECT *` tabel `guru` dikirim utuh ke siapa pun yang menebak
  `guru_id`, termasuk hash `password`, `push_token`, `expo_push_token`, dan
  sejak fase A `auth_token` yang sedang berlaku. Keempatnya kini di-`unset`
  (baris 22-24); field lain dan `jadwal` tidak berubah. Pemanggilnya hanya
  `mengajar.tsx` dan `(tabs)/todo.tsx` (membaca `jadwal`) serta
  `(tabs)/profil.tsx`, dan tidak satu pun versi dalam riwayat Git
  ClassyncApp membaca keempat field itu dari `profil`. Teruji di produksi
  26 September 2026: keempat nama field hilang dari `profil`, field lain
  dan jumlah jadwal tetap, dan layar Profil, Edit Profil, Todo, serta
  Mengajar di aplikasi yang beredar tampil normal.
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
