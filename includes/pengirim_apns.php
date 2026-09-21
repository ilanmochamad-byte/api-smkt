<?php
// pengirim_apns.php — mengirim notifikasi langsung ke APNs.
//
// Kenapa berkas ini ada: ClassyncApp memanggil getDevicePushTokenAsync(), yang
// mengembalikan token ASLI platform. Di Android itu token registrasi FCM, di
// iOS itu token perangkat APNs — dan token APNs tidak akan pernah diterima
// FCM v1. Akibatnya notifikasi ke seluruh guru pengguna iPhone mati sejak
// 13 Juli 2026, tanpa gejala, sampai pemeriksaan respons ditambahkan.
//
// Selama aplikasi belum beralih ke satu bentuk token, server yang memilah.
// Begitu aplikasi beralih, seluruh berkas ini tinggal dicabut.

if (!function_exists('apnsB64Url')) {
    function apnsB64Url($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

// Baca konfigurasi dari luar webroot. Mengembalikan null kalau tidak lengkap —
// pemanggil harus memperlakukan itu sebagai "lewati, catat", bukan sebagai
// galat fatal. Notifikasi tidak sepadan dengan mematikan approval.
function apnsKonfigurasi() {
    static $cache = false;
    if ($cache !== false) return $cache;

    $berkas = '/DATA/k1807225/config/apns-classync.php';
    if (!is_readable($berkas)) {
        error_log("APNs: konfigurasi tidak terbaca di " . $berkas);
        $cache = null;
        return null;
    }
    require $berkas;

    $cfg = [
        'key_path'  => isset($apns_key_path)  ? $apns_key_path  : '',
        'key_id'    => isset($apns_key_id)    ? $apns_key_id    : '',
        'team_id'   => isset($apns_team_id)   ? $apns_team_id   : '',
        'bundle_id' => isset($apns_bundle_id) ? $apns_bundle_id : '',
        'env'       => isset($apns_env)       ? $apns_env       : 'production',
    ];

    foreach (['key_path', 'key_id', 'team_id', 'bundle_id'] as $wajib) {
        if ($cfg[$wajib] === '') {
            error_log("APNs: konfigurasi tidak lengkap, \$apns_" . $wajib . " kosong.");
            $cache = null;
            return null;
        }
    }
    // Berkas 0 byte adalah gejala yang khas: tempat penampung yang belum diisi.
    if (!is_readable($cfg['key_path']) || filesize($cfg['key_path']) < 100) {
        error_log("APNs: berkas kunci .p8 tidak terbaca atau kosong di " . $cfg['key_path']);
        $cache = null;
        return null;
    }

    $cache = $cfg;
    return $cfg;
}

// openssl_sign menghasilkan tanda tangan ECDSA berformat DER, sedangkan JWT
// menuntut R dan S mentah, masing-masing 32 byte. Tanpa konversi ini Apple
// menolak dengan InvalidProviderToken — yang bunyinya seolah kuncinya salah,
// padahal kuncinya benar. Jebakan paling sering pada APNs di PHP.
function apnsDerKeRaw($der) {
    $pos = 0;
    $panjang = strlen($der);
    if ($panjang < 8 || ord($der[$pos++]) !== 0x30) return false;

    $len = ord($der[$pos++]);
    if ($len & 0x80) { $pos += ($len & 0x7f); }   // panjang bentuk panjang

    $ambil = function () use ($der, &$pos, $panjang) {
        if ($pos + 2 > $panjang || ord($der[$pos++]) !== 0x02) return false;
        $l = ord($der[$pos++]);
        if ($pos + $l > $panjang) return false;
        $nilai = substr($der, $pos, $l);
        $pos += $l;
        $nilai = ltrim($nilai, "\x00");           // buang padding tanda
        if (strlen($nilai) > 32) return false;
        return str_pad($nilai, 32, "\x00", STR_PAD_LEFT);
    };

    $r = $ambil();
    $s = $ambil();
    if ($r === false || $s === false) return false;
    return $r . $s;
}

// Apple menolak dengan TooManyProviderTokenUpdates kalau token provider dibuat
// ulang lebih sering daripada tiap 20 menit. kirim_notifikasi_harian.php
// mengirim ke belasan guru dalam satu perulangan — tanpa cache, yang pertama
// lolos dan sisanya ditolak. Disimpan 50 menit; Apple menuntut penyegaran
// sekurang-kurangnya tiap 60 menit.
function apnsJwt($cfg) {
    $berkas_cache = sys_get_temp_dir() . '/apns-jwt-' . md5($cfg['key_id'] . '|' . $cfg['team_id']) . '.txt';

    if (is_readable($berkas_cache) && (time() - filemtime($berkas_cache)) < 3000) {
        $tersimpan = trim((string)@file_get_contents($berkas_cache));
        if ($tersimpan !== '') return $tersimpan;
    }

    $header  = ['alg' => 'ES256', 'kid' => $cfg['key_id']];
    $klaim   = ['iss' => $cfg['team_id'], 'iat' => time()];
    $segmen  = apnsB64Url(json_encode($header)) . '.' . apnsB64Url(json_encode($klaim));

    $isi_kunci = @file_get_contents($cfg['key_path']);
    if ($isi_kunci === false) {
        error_log("APNs: gagal membaca berkas kunci .p8.");
        return null;
    }
    $kunci = openssl_pkey_get_private($isi_kunci);
    if ($kunci === false) {
        error_log("APNs: berkas .p8 bukan kunci privat yang sah — " . openssl_error_string());
        return null;
    }

    $der = '';
    if (!openssl_sign($segmen, $der, $kunci, OPENSSL_ALGO_SHA256)) {
        error_log("APNs: openssl_sign gagal — " . openssl_error_string());
        return null;
    }

    $mentah = apnsDerKeRaw($der);
    if ($mentah === false) {
        error_log("APNs: gagal mengubah tanda tangan DER menjadi R||S.");
        return null;
    }

    $jwt = $segmen . '.' . apnsB64Url($mentah);

    // Tulis lewat berkas sementara lalu rename, supaya dua permintaan yang
    // bersamaan tidak saling membaca berkas setengah jadi.
    $sementara = $berkas_cache . '.' . getmypid();
    if (@file_put_contents($sementara, $jwt) !== false) {
        @chmod($sementara, 0600);
        @rename($sementara, $berkas_cache);
    }

    return $jwt;
}

// Mengembalikan ['ok' => bool, 'http' => int, 'reason' => string].
// 'reason' diisi kode alasan dari Apple (BadDeviceToken, Unregistered, dst).
function kirimApns($token, $title, $body, $screen = '') {
    $cfg = apnsKonfigurasi();
    if ($cfg === null) {
        return ['ok' => false, 'http' => 0, 'reason' => 'KonfigurasiTidakSiap'];
    }

    $jwt = apnsJwt($cfg);
    if ($jwt === null) {
        return ['ok' => false, 'http' => 0, 'reason' => 'GagalMembuatToken'];
    }

    // 'screen' WAJIB ditaruh di dalam kunci 'body', bukan di tingkat atas.
    // app/_layout.tsx membaca response.notification.request.content.data?.screen,
    // dan untuk notifikasi jarak jauh expo-notifications mengisi content.data
    // HANYA dari userInfo["body"] — lihat NotificationRecords.swift,
    // serializedNotificationData(). Itu konvensi Expo Push, yang menaruh data
    // khusus di bawah 'body'; APNs langsung harus menirunya.
    //
    // Versi pertama berkas ini menaruh 'screen' di tingkat atas dengan
    // anggapan content.data diisi dari semua kunci selain 'aps'. Anggapan itu
    // keliru, dan sempat tampak benar karena uji pertama dilakukan saat
    // aplikasi kebetulan sudah terbuka di halaman tujuan. Tautan-dalam harus
    // diuji dari halaman LAIN, dan juga dengan aplikasi ditutup total.
    $payload = [
        'aps' => [
            'alert' => ['title' => $title, 'body' => $body],
            'sound' => 'default',
        ],
    ];
    if ($screen !== '') {
        $payload['body'] = ['screen' => $screen];
    }

    $utama    = ($cfg['env'] === 'sandbox') ? 'sandbox' : 'production';
    $cadangan = ($utama === 'sandbox') ? 'production' : 'sandbox';

    $hasil = apnsKirimSekali($utama, $cfg, $jwt, $token, $payload);
    if ($hasil['ok']) {
        return $hasil;
    }

    // BadDeviceToken berarti token dan LINGKUNGANNYA tidak cocok — bukan
    // token yang rusak; token rusak dijawab Unregistered. Aplikasi dari App
    // Store menghasilkan token production, build pengembangan menghasilkan
    // token sandbox, dan keduanya beredar bersamaan di sekolah ini. Satu
    // nilai $apns_env tidak bisa melayani keduanya, jadi coba yang satunya.
    if ($hasil['reason'] !== 'BadDeviceToken') {
        error_log("APNs: ditolak di " . $utama . " HTTP " . $hasil['http'] . " — " . $hasil['reason']);
        return $hasil;
    }

    $hasil_cadangan = apnsKirimSekali($cadangan, $cfg, $jwt, $token, $payload);
    if ($hasil_cadangan['ok']) {
        // Dicatat dengan sengaja: kalau ternyata SELURUH guru ada di satu
        // lingkungan, $apns_env bisa dikunci ke sana nanti berdasarkan bukti
        // dari log ini, bukan berdasarkan tebakan.
        error_log("APNs: berhasil di " . $cadangan . " setelah " . $utama . " menjawab BadDeviceToken.");
        return $hasil_cadangan;
    }

    error_log("APNs: ditolak di kedua lingkungan — " . $utama . ": " . $hasil['reason']
              . ", " . $cadangan . ": " . $hasil_cadangan['reason']);
    return $hasil_cadangan;
}

// Satu kali kirim ke satu lingkungan. Dipisahkan supaya kirimApns() bisa
// mencoba lingkungan kedua tanpa menyusun ulang payload maupun JWT — JWT
// yang sama berlaku di production dan sandbox.
function apnsKirimSekali($env, $cfg, $jwt, $token, $payload) {
    $pangkalan = ($env === 'sandbox')
        ? 'https://api.sandbox.push.apple.com'
        : 'https://api.push.apple.com';

    $ch = curl_init($pangkalan . '/3/device/' . rawurlencode($token));
    curl_setopt_array($ch, [
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,   // APNs hanya HTTP/2
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'authorization: bearer ' . $jwt,
            'apns-topic: ' . $cfg['bundle_id'],   // wajib bundle ID, bukan nama lain
            'apns-push-type: alert',
            'apns-priority: 10',
            'content-type: application/json',
        ],
    ]);

    $hasil = curl_exec($ch);
    $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $galat = curl_error($ch);
    curl_close($ch);

    if ($hasil === false) {
        error_log("APNs: panggilan ke " . $env . " gagal — " . $galat);
        return ['ok' => false, 'http' => 0, 'reason' => 'GagalMenghubungi'];
    }

    if ($http === 200) {
        return ['ok' => true, 'http' => 200, 'reason' => ''];
    }

    $jawaban = json_decode($hasil, true);
    $alasan  = is_array($jawaban) && isset($jawaban['reason']) ? $jawaban['reason'] : 'TidakDiketahui';

    return ['ok' => false, 'http' => $http, 'reason' => $alasan];
}
