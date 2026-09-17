<?php
// proses_approval_absensi.php
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- KONFIGURASI DATABASE ---
require_once 'includes/db.php';

$conn = null;

// Helper: Menembak API FCM yang berada di Server B
function panggilApiFCMServerB($token, $title, $body, $screenTarget) {
    $url = 'https://api.smkt.alhasan.co.id/send_fcm_api.php';
    
    $data = [
        'secret' => 'SMKTAH_Classync_2026_Secure!',
        'token' => $token,
        'title' => $title,
        'body' => $body,
        'screen' => $screenTarget
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data)
        ]
    ];
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    return $result;
}

// Helper: Konversi Nama Hari (Inggris -> Indonesia)
function getHariIndonesia($date) {
    $days = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
    ];
    return $days[date('l', strtotime($date))];
}

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['id']) || !isset($input['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parameter id dan action diperlukan.']);
        exit;
    }

    $id = intval($input['id']);
    $status = $input['action']; // 'Disetujui' atau 'Ditolak'
    $komentar = $input['komentar_admin'] ?? '';

    if (!in_array($status, ['Disetujui', 'Ditolak'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action tidak valid.']);
        exit;
    }

    // Kunci baris pengajuan sampai transaksi selesai. Tanpa ini dua ketukan
    // cepat di aplikasi bisa sama-sama membaca status 'Pending', lalu sama-sama
    // menyisipkan baris absensi — dan honor dibayar dua kali.
    $conn->begin_transaction();
    $transaksi_sukses = false;

    // 1. Ambil data pengajuan
    $stmt_req = $conn->prepare("SELECT * FROM pengajuan_absensi WHERE id = ? FOR UPDATE");
    $stmt_req->bind_param("i", $id);
    $stmt_req->execute();
    $req = $stmt_req->get_result()->fetch_assoc();
    $stmt_req->close();

    if (!$req) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Data pengajuan tidak ditemukan.']);
        exit;
    }

    if ($req['status'] !== 'Pending') {
        $conn->rollback();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Pengajuan ini sudah diproses sebelumnya.']);
        exit;
    }

    $notif_title = "";
    $notif_body = "";
    $send_notif = false;
    $guru_id = $req['guru_id'];

if ($status === 'Disetujui') {
        $tanggal = $req['tanggal'];
        $hari_ini = getHariIndonesia($tanggal);
        $jam_mulai_aju = $req['jam_mulai'];
        $berhasil_insert = false;
        $error_msg = "";

        if ($req['jenis_absensi'] == 'Mengajar') {
            $stmt_jadwal = $conn->prepare("
                SELECT id 
                FROM jadwal_mengajar 
                WHERE guru_id = ? 
                AND hari = ? 
                AND (? BETWEEN jam_mulai AND jam_selesai)
                AND status_jadwal = 'Aktif'
                LIMIT 1
            ");
            $stmt_jadwal->bind_param("iss", $guru_id, $hari_ini, $jam_mulai_aju);
            $stmt_jadwal->execute();
            $jadwal = $stmt_jadwal->get_result()->fetch_assoc();
            $stmt_jadwal->close();

            if ($jadwal) {
                $jadwal_id = $jadwal['id'];
                $waktu_absensi = $tanggal . ' ' . $jam_mulai_aju;
                $ket = "Susulan: " . $req['keterangan'];
                
                // --- CEK DUPLIKASI ABSENSI MENGAJAR ---
                $stmt_cek = $conn->prepare("SELECT id FROM absensi WHERE guru_id = ? AND jadwal_id = ? AND tipe_absensi = 'mengajar' AND DATE(waktu_absensi) = ?");
                $stmt_cek->bind_param("iis", $guru_id, $jadwal_id, $tanggal);
                $stmt_cek->execute();
                $is_duplicate = $stmt_cek->get_result()->num_rows > 0;
                $stmt_cek->close();

                if ($is_duplicate) {
                    $error_msg = "Pengajuan gagal disetujui: Absensi mengajar untuk jadwal dan tanggal tersebut sudah ada.";
                } else {
                    $stmt_ins = $conn->prepare("INSERT INTO absensi (guru_id, jadwal_id, tipe_absensi, waktu_absensi, status, keterangan) VALUES (?, ?, 'mengajar', ?, 'Hadir', ?)");
                    $stmt_ins->bind_param("iiss", $guru_id, $jadwal_id, $waktu_absensi, $ket);
                    
                    if ($stmt_ins->execute()) $berhasil_insert = true;
                    $stmt_ins->close();
                }
            } else {
                $error_msg = "Tidak ditemukan jadwal mengajar Aktif pada hari/jam tersebut.";
            }

        } elseif ($req['jenis_absensi'] == 'Piket' || $req['jenis_absensi'] == 'Ekstrakurikuler') {
            $tipe_db = ($req['jenis_absensi'] == 'Piket') ? 'piket' : 'ekskul';
            $waktu_absensi = $tanggal . ' ' . $jam_mulai_aju;
            $ket = "Susulan: " . $req['keterangan'];
            $jadwal_id = 0;
            $nama_ekskul = '';

            // Cari ID Jadwal Aktif berdasarkan tipe
            if ($tipe_db === 'piket') {
                $stmt_jadwal = $conn->prepare("SELECT id FROM jadwal_piket WHERE guru_id = ? AND hari = ? AND status_jadwal = 'Aktif' LIMIT 1");
                $stmt_jadwal->bind_param("is", $guru_id, $hari_ini);
                $stmt_jadwal->execute();
                $jadwal = $stmt_jadwal->get_result()->fetch_assoc();
                $stmt_jadwal->close();
                if ($jadwal) $jadwal_id = $jadwal['id'];
            } else { // ekskul
                // Satu guru bisa membina dua ekskul di hari yang sama dengan jam
                // yang bertumpang tindih. BETWEEN akan cocok ke keduanya, lalu
                // LIMIT 1 tanpa ORDER BY memilih salah satunya secara kebetulan —
                // dua pengajuan berbeda jatuh ke jadwal_id yang sama, dan yang
                // kedua ditolak keliru sebagai duplikat. Utamakan jadwal yang jam
                // mulainya sama persis dengan pengajuan.
                $stmt_jadwal = $conn->prepare("SELECT id, nama_ekskul FROM jadwal_ekskul WHERE guru_id = ? AND hari = ? AND (? BETWEEN jam_mulai AND jam_selesai) AND status_jadwal = 'Aktif' ORDER BY (jam_mulai = ?) DESC, id ASC LIMIT 1");
                $stmt_jadwal->bind_param("isss", $guru_id, $hari_ini, $jam_mulai_aju, $jam_mulai_aju);
                $stmt_jadwal->execute();
                $jadwal = $stmt_jadwal->get_result()->fetch_assoc();
                $stmt_jadwal->close();
                if ($jadwal) {
                    $jadwal_id = $jadwal['id'];
                    $nama_ekskul = $jadwal['nama_ekskul'] ?? '';
                }
            }

            if ($jadwal_id > 0) {
                // --- CEK DUPLIKASI ABSENSI PIKET/EKSKUL ---
                // Kuncinya berbeda per jenis, dan perbedaan itu disengaja:
                //   piket  — satu hari satu bayar. Label sesi Pagi/Siang tidak
                //            menentukan waktu, jadi jadwal_id TIDAK dipakai.
                //   ekskul — satu guru boleh membina dua ekskul berbeda di hari
                //            yang sama dan dibayar dua kali, jadi jadwal_id wajib.
                if ($tipe_db === 'piket') {
                    $stmt_cek = $conn->prepare("SELECT id FROM absensi WHERE guru_id = ? AND tipe_absensi = 'piket' AND DATE(waktu_absensi) = ?");
                    $stmt_cek->bind_param("is", $guru_id, $tanggal);
                } else {
                    $stmt_cek = $conn->prepare("SELECT id FROM absensi WHERE guru_id = ? AND jadwal_id = ? AND tipe_absensi = 'ekskul' AND DATE(waktu_absensi) = ?");
                    $stmt_cek->bind_param("iis", $guru_id, $jadwal_id, $tanggal);
                }
                $stmt_cek->execute();
                $is_duplicate = $stmt_cek->get_result()->num_rows > 0;
                $stmt_cek->close();

                if ($is_duplicate) {
                    $error_msg = "Pengajuan gagal disetujui: Absensi " . $req['jenis_absensi']
                               . ($nama_ekskul !== '' ? " (" . $nama_ekskul . ")" : "")
                               . " untuk tanggal tersebut sudah ada.";
                } else {
                    $stmt_ins = $conn->prepare("INSERT INTO absensi (guru_id, jadwal_id, tipe_absensi, waktu_absensi, status, keterangan) VALUES (?, ?, ?, ?, 'Hadir', ?)");
                    $stmt_ins->bind_param("iisss", $guru_id, $jadwal_id, $tipe_db, $waktu_absensi, $ket);
                    
                    if ($stmt_ins->execute()) {
                        $berhasil_insert = true;
                        // Sinkronisasi data harian khusus Piket
                        if ($tipe_db == 'piket') {
                            $stmt_daily = $conn->prepare("INSERT INTO absensi_harian (guru_id, tanggal, jam_masuk, jam_pulang) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE jam_masuk = VALUES(jam_masuk)");
                            $stmt_daily->bind_param("isss", $guru_id, $tanggal, $req['jam_mulai'], $req['jam_selesai']);
                            $stmt_daily->execute();
                        }
                    }
                    $stmt_ins->close();
                }
            } else {
                $error_msg = "Tidak ditemukan jadwal {$req['jenis_absensi']} Aktif pada hari/jam tersebut.";
            }
        }

        // Eksekusi Update Status Pengajuan jika Insert Berhasil
        if ($berhasil_insert) {
            // Syarat status = 'Pending' adalah pengaman kedua setelah FOR UPDATE.
            $stmt_setuju = $conn->prepare("UPDATE pengajuan_absensi SET status = 'Disetujui' WHERE id = ? AND status = 'Pending'");
            $stmt_setuju->bind_param("i", $id);
            $stmt_setuju->execute();
            $transaksi_sukses = ($stmt_setuju->affected_rows === 1);
            $stmt_setuju->close();

            if (!$transaksi_sukses) {
                $conn->rollback();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Pengajuan ini sudah diproses sebelumnya.']);
                exit;
            }

            $notif_title = "✅ Pengajuan Disetujui!";
            $notif_body = "Pengajuan absensi " . $req['jenis_absensi'] . " tanggal " . date('d M Y', strtotime($req['tanggal'])) . " telah disetujui.";
            $send_notif = true;

            $response_msg = "Pengajuan disetujui. Honor telah diperbarui.";
        } else {
            $conn->rollback();
            $response_msg = !empty($error_msg) ? $error_msg : "Terjadi kesalahan saat menyimpan data absensi.";
            echo json_encode(['success' => false, 'message' => $response_msg]);
            exit;
        }

    } else {
        // Ditolak
        $stmt_reject = $conn->prepare("UPDATE pengajuan_absensi SET status = 'Ditolak', komentar_admin = ? WHERE id = ?");
        $stmt_reject->bind_param("si", $komentar, $id);

        if ($stmt_reject->execute()) {
            $alasan = !empty($komentar) ? "\nAlasan: " . $komentar : "";
            $notif_title = "❌ Pengajuan Ditolak";
            $notif_body = "Silakan perbaiki pengajuan " . $req['jenis_absensi'] . " Anda." . $alasan;
            $send_notif = true;
            $response_msg = "Pengajuan ditolak.";
            $transaksi_sukses = true;
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Gagal memproses penolakan.']);
            exit;
        }
        $stmt_reject->close();
    }

    // Commit sebelum memanggil FCM: panggilan jaringan itu bisa menggantung
    // beberapa detik, dan kunci baris tidak boleh ikut menunggu selama itu.
    if ($transaksi_sukses) {
        $conn->commit();
    } else {
        $conn->rollback();
    }

    // Kirim Push Notification
    if ($send_notif) {
        try {
            $res_token = $conn->query("SELECT push_token FROM guru WHERE id = " . intval($guru_id));
            if ($res_token && $res_token->num_rows > 0) {
                $token = $res_token->fetch_assoc()['push_token'];
                
                if (!empty($token)) {
                    $stmt_simpan = $conn->prepare("INSERT INTO notifikasi (guru_id, judul, isi) VALUES (?, ?, ?)");
                    $stmt_simpan->bind_param("iss", $guru_id, $notif_title, $notif_body);
                    $stmt_simpan->execute();
                    $stmt_simpan->close();

                    $screenTarget = '/pengajuan_absensi';
                    panggilApiFCMServerB($token, $notif_title, $notif_body, $screenTarget);
                }
            }
        } catch (Exception $e) {
            error_log("Gagal mengirim notifikasi absensi: " . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'message' => $response_msg]);

} catch (Exception $e) {
    if ($conn) { @$conn->rollback(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>