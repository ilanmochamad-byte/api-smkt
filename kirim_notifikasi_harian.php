<?php
// kirim_notifikasi_harian.php
// (Dirancang untuk CRON JOB)

ini_set('display_errors', 1); 
error_reporting(E_ALL);

// --- KONFIGURASI PUSAT KONEKSI ---
require_once 'includes/db.php';

// Path ke file Kunci Akun Layanan (Service Account Key) JSON Anda
$serviceAccountKeyPath = '/DATA/k1807225/credentials/classyncapp-9a6b6-firebase-adminsdk-fbsvc-a059a16151.json';
// ID Proyek Firebase Anda
$projectId = 'classyncapp-9a6b6';
// --------------------

// Sertakan autoloader dari Composer
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Mendapatkan Access Token OAuth 2.0 dari Google.
 */
function getAccessToken($keyFilePath) {
    $client = new \Google\Client();
    $client->setAuthConfig($keyFilePath);
    $client->addScope('https://www.googleapis.com/auth/cloud-platform');
    $client->fetchAccessTokenWithAssertion();
    $accessToken = $client->getAccessToken();
    if (!isset($accessToken['access_token'])) {
        throw new Exception("Gagal mendapatkan Access Token.");
    }
    return $accessToken['access_token'];
}

/**
 * Mengirim notifikasi push menggunakan FCM v1 API.
 */
function sendPushNotification($token, $title, $body, $accessToken, $projectId) {
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    
    $data = [
        'message' => [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body
            ],
            'android' => [
                'priority' => 'high', // Prioritas tinggi untuk Android
                'notification' => [
                    'sound' => 'default',
                    'channel_id' => 'default' // Pastikan ini ada di app
                ]
            ],
            'data' => [ // Payload data netral untuk keandalan
                'source' => 'server'
            ]
        ]
    ];
    
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $result = curl_exec($ch);
    if ($result === FALSE) {
        echo 'Curl failed: ' . curl_error($ch) . "\n";
    }
    curl_close($ch);
    return $result;
}

/**
 * Mendapatkan nama hari dalam Bahasa Indonesia.
 */
function getNamaHariIndonesia() {
    $day_map = ['Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'];
    return $day_map[date('l')];
}

// --- Mulai Logika Utama Skrip ---
try {
    echo "Mendapatkan Access Token...\n";
    $accessToken = getAccessToken($serviceAccountKeyPath);
    echo "Access Token berhasil didapat.\n";

    $hari_ini = getNamaHariIndonesia();
    $pesan_per_guru = [];
    echo "Mulai skrip notifikasi untuk hari: $hari_ini \n";

    // 1. Kumpulkan jadwal mengajar (PERBAIKAN SINTAKS SQL)
    $sql_mengajar = "SELECT g.id, g.push_token, jm.mata_pelajaran, jm.kelas 
                     FROM jadwal_mengajar jm 
                     JOIN guru g ON jm.guru_id = g.id 
                     WHERE jm.hari = ? AND jm.status_jadwal = 'Aktif' AND g.push_token IS NOT NULL AND g.push_token != ''";
    $stmt = $conn->prepare($sql_mengajar);
    $stmt->bind_param("s", $hari_ini);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pesan_per_guru[$row['id']]['token'] = $row['push_token'];
        $pesan_per_guru[$row['id']]['pesan'][] = "Mengajar {$row['mata_pelajaran']} (Kelas {$row['kelas']})";
    }
    $stmt->close();

    // 2. Kumpulkan jadwal piket (PERBAIKAN SINTAKS SQL)
    $sql_piket = "SELECT g.id, g.push_token, jp.sesi 
                  FROM jadwal_piket jp 
                  JOIN guru g ON jp.guru_id = g.id 
                  WHERE jp.hari = ? AND jp.status_jadwal = 'Aktif' AND g.push_token IS NOT NULL AND g.push_token != ''";
    $stmt = $conn->prepare($sql_piket);
    $stmt->bind_param("s", $hari_ini);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pesan_per_guru[$row['id']]['token'] = $row['push_token'];
        $pesan_per_guru[$row['id']]['pesan'][] = "Piket Sesi {$row['sesi']}";
    }
    $stmt->close();

    // 3. Kumpulkan jadwal ekskul (PERBAIKAN SINTAKS SQL)
    $sql_ekskul = "SELECT g.id, g.push_token, je.nama_ekskul 
                   FROM jadwal_ekskul je 
                   JOIN guru g ON je.guru_id = g.id 
                   WHERE je.hari = ? AND je.status_jadwal = 'Aktif' AND g.push_token IS NOT NULL AND g.push_token != ''";
    $stmt = $conn->prepare($sql_ekskul);
    $stmt->bind_param("s", $hari_ini);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pesan_per_guru[$row['id']]['token'] = $row['push_token'];
        $pesan_per_guru[$row['id']]['pesan'][] = "Ekskul {$row['nama_ekskul']}";
    }
    $stmt->close();

    // 4. Kirim Notifikasi
    if (empty($pesan_per_guru)) {
        echo "Tidak ada guru dengan jadwal aktif hari ini yang memiliki push token.\n";
    }

    foreach ($pesan_per_guru as $guru_id => $info) {
        $title = "Pengingat Jadwal Classync";
        $body = "Jadwal Anda hari ini: " . implode(', ', $info['pesan']) . ".";
        $token = $info['token'];
        
        // --- Simpan notifikasi ke DB ---
        $stmt_simpan = $conn->prepare("INSERT INTO notifikasi (guru_id, judul, isi) VALUES (?, ?, ?)");
        $stmt_simpan->bind_param("iss", $guru_id, $title, $body);
        $stmt_simpan->execute();
        $stmt_simpan->close();
        
        echo "Mengirim ke Guru ID $guru_id: $body \n";
        $kirim_hasil = sendPushNotification($token, $title, $body, $accessToken, $projectId);
        echo "Hasil: $kirim_hasil \n";
    }

    echo "Skrip notifikasi selesai.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
} finally {
    // Karena kita memakai file db.php, pastikan menutup koneksi jika objeknya ada
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>