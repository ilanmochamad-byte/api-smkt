<?php
// keuangan_helper.php - Pusat Kalkulasi Finansial Classync

if (!isset($JAM_ISTIRAHAT)) {
    $JAM_ISTIRAHAT = [
        ['mulai' => '10:10:00', 'selesai' => '10:25:00', 'durasi' => 15],
        ['mulai' => '11:45:00', 'selesai' => '12:05:00', 'durasi' => 20]
    ];
}

if (!function_exists('getPengaturanHonor')) {
    function getPengaturanHonor($conn) {
        $q_set = $conn->query("SELECT nama_pengaturan, nilai_pengaturan FROM pengaturan WHERE nama_pengaturan LIKE 'honor_%'");
        $tarif = [];
        if ($q_set) {
            while($row = $q_set->fetch_assoc()) {
                $tarif[$row['nama_pengaturan']] = (int)$row['nilai_pengaturan'];
            }
        }
        return [
            'honor_per_jp' => $tarif['honor_per_jp'] ?? 10000,
            'honor_ekskul' => $tarif['honor_ekskul'] ?? 25000,
            'honor_piket'  => $tarif['honor_piket'] ?? 25000,
            'honor_bk'     => $tarif['honor_bk'] ?? 25000
        ];
    }
}

if (!function_exists('hitungJP')) {
    function hitungJP($jam_mulai, $jam_selesai) { 
        global $JAM_ISTIRAHAT;
        if (empty($jam_mulai) || empty($jam_selesai)) return 0;

        $mulai = new DateTime($jam_mulai);
        $selesai = new DateTime($jam_selesai);
        $diff = $selesai->diff($mulai);
        $total_menit_kotor = ($diff->h * 60) + $diff->i;
        
        $menit_pengurang = 0;
        foreach ($JAM_ISTIRAHAT as $istirahat) {
            $mulai_istirahat = new DateTime($istirahat['mulai']);
            $selesai_istirahat = new DateTime($istirahat['selesai']);
            if ($mulai < $mulai_istirahat && $selesai > $selesai_istirahat) {
                $menit_pengurang += $istirahat['durasi'];
            }
        }

        $menit_efektif = $total_menit_kotor - $menit_pengurang;
        if ($menit_efektif <= 0) return 0;
        return round($menit_efektif / 40); 
    }
}

if (!function_exists('cekAbsenBerturutTurut')) {
    function cekAbsenBerturutTurut($conn, $guru_id, $bulan, $tahun) { 
        return false; 
    }
}

if (!function_exists('hitungHonorBulan')) {
    function hitungHonorBulan($conn, $guru_id, $bulan, $tahun, $tarif) {
        $honor_mengajar = 0; $total_jp = 0;
        $honor_piket = 0; $jumlah_piket = 0;
        $honor_ekskul = 0; $jumlah_ekskul = 0;
        $honor_bk = 0; $jumlah_bk = 0;
        $uang_transport = 0;

        // A. Tunjangan Tetap
        $tunjangan_data = [];
        $res_tunjangan = $conn->query("SELECT * FROM tunjangan_guru WHERE guru_id = $guru_id");
        if ($res_tunjangan && $res_tunjangan->num_rows > 0) {
            $tunjangan_data = $res_tunjangan->fetch_assoc();
        }

        // B. Hitung Honor Mengajar
        $sql_mengajar = "SELECT a.status, jm.jam_mulai, jm.jam_selesai FROM absensi a JOIN jadwal_mengajar jm ON a.jadwal_id = jm.id WHERE a.guru_id = ? AND a.tipe_absensi = 'mengajar' AND MONTH(a.waktu_absensi) = ? AND YEAR(a.waktu_absensi) = ?";
        $stmt_mengajar = $conn->prepare($sql_mengajar);
        if ($stmt_mengajar) {
            $stmt_mengajar->bind_param("iii", $guru_id, $bulan, $tahun);
            $stmt_mengajar->execute();
            $result_mengajar = $stmt_mengajar->get_result();
            while($absen = $result_mengajar->fetch_assoc()) {
                $jp = hitungJP($absen['jam_mulai'], $absen['jam_selesai']);
                $honor_basis = $jp * $tarif['honor_per_jp'];
                if ($absen['status'] === 'Hadir' || $absen['status'] === 'Sakit') {
                    $honor_mengajar += $honor_basis; $total_jp += $jp;
                } elseif ($absen['status'] === 'Izin') {
                    $honor_mengajar += ($honor_basis * 0.75); $total_jp += $jp;
                }
            }
            $stmt_mengajar->close();
        }

        // C. Hitung Honor Piket, Ekskul & BK
        $sql_lain = "SELECT status, tipe_absensi FROM absensi WHERE guru_id = ? AND tipe_absensi IN ('piket', 'ekskul', 'bimbingan') AND MONTH(waktu_absensi) = ? AND YEAR(waktu_absensi) = ?";
        $stmt_lain = $conn->prepare($sql_lain);
        if ($stmt_lain) {
            $stmt_lain->bind_param("iii", $guru_id, $bulan, $tahun);
            $stmt_lain->execute();
            $result_lain = $stmt_lain->get_result();
            while($absen_lain = $result_lain->fetch_assoc()) {
                $honor_diterima = 0;
                $tipe = trim($absen_lain['tipe_absensi']);
                $status = trim($absen_lain['status']);
                
                $honor_basis = 0;
                if ($tipe == 'piket') $honor_basis = $tarif['honor_piket'];
                elseif ($tipe == 'ekskul') $honor_basis = $tarif['honor_ekskul'];
                elseif ($tipe == 'bimbingan') $honor_basis = $tarif['honor_bk'];

                if ($status === 'Hadir' || $status === 'Sakit') { $honor_diterima = $honor_basis; }
                elseif ($status === 'Izin') { $honor_diterima = $honor_basis * 0.75; }
                
                if ($honor_diterima > 0) {
                    if ($tipe == 'piket') { $jumlah_piket++; $honor_piket += $honor_diterima; } 
                    elseif ($tipe == 'ekskul') { $jumlah_ekskul++; $honor_ekskul += $honor_diterima; }
                    elseif ($tipe == 'bimbingan') { $jumlah_bk++; $honor_bk += $honor_diterima; } 
                }
            }
            $stmt_lain->close();
        }

        // D. Hitung Akumulasi Uang Transportasi Harian
        $sql_transport = "SELECT SUM(bonus) as total_transport FROM absensi_harian WHERE guru_id = $guru_id AND MONTH(tanggal) = $bulan AND YEAR(tanggal) = $tahun";
        $res_transport = $conn->query($sql_transport);
        if ($res_transport && $res_transport->num_rows > 0) {
            $row_transport = $res_transport->fetch_assoc();
            $uang_transport = (int)$row_transport['total_transport'];
        }

        // E. Kalkulasi Total Tunjangan (PERHATIAN: 'transportasi' dihapus dari sini agar tidak dobel hitung)
        $total_tunjangan = ($tunjangan_data['masa_kerja'] ?? 0) + ($tunjangan_data['jabatan'] ?? 0) + ($tunjangan_data['suami_istri'] ?? 0) + ($tunjangan_data['anak'] ?? 0) + ($tunjangan_data['wali_kelas'] ?? 0);
        
        // Ambil Potongan
        $potongan_data = [];
        $res_potongan = $conn->query("SELECT * FROM potongan_guru WHERE guru_id = $guru_id AND bulan = $bulan AND tahun = $tahun");
        if($res_potongan && $res_potongan->num_rows > 0) {
            $potongan_data = $res_potongan->fetch_assoc();
        }
        $potongan_arisan = $potongan_data['arisan'] ?? 0;
        $potongan_tabungan = $potongan_data['tabungan'] ?? 0;

        // F. Kalkulasi Final 
        $subtotal_pendapatan = $total_tunjangan + $honor_mengajar + $honor_piket + $honor_ekskul + $honor_bk + $uang_transport;
        $total_potongan = $potongan_arisan + $potongan_tabungan;
        $total_diterima = $subtotal_pendapatan - $total_potongan;

        return [
            'id' => $guru_id,
            'total_tunjangan' => $total_tunjangan,
            'honor_mengajar' => $honor_mengajar,
            'total_jp' => $total_jp,
            'honor_piket' => $honor_piket,
            'jumlah_piket' => $jumlah_piket,
            'honor_ekskul' => $honor_ekskul,
            'jumlah_ekskul' => $jumlah_ekskul,
            'honor_bk' => $honor_bk,
            'jumlah_bk' => $jumlah_bk,
            'uang_transport' => $uang_transport, // NAMA VARIABEL DIUBAH
            'subtotal_pendapatan' => $subtotal_pendapatan,
            'potongan_arisan' => $potongan_arisan,
            'potongan_tabungan' => $potongan_tabungan,
            'total_potongan' => $total_potongan,
            'total_diterima' => $total_diterima
        ];
    }
}
?>