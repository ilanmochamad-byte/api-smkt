<?php
require_once 'includes/db.php';
require_once 'keuangan_helper.php';

$guru_id = isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0;
$filter_bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$filter_tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

if ($guru_id === 0) {
    http_response_code(400);
    echo json_encode(["message" => "Parameter guru_id dibutuhkan."]);
    exit();
}

$tarif = getPengaturanHonor($conn);
$honor_bulan_ini = hitungHonorBulan($conn, $guru_id, $filter_bulan, $filter_tahun, $tarif);

$bulan_lalu = $filter_bulan - 1;
$tahun_lalu = $filter_tahun;
if ($bulan_lalu < 1) {
    $bulan_lalu = 12;
    $tahun_lalu = $filter_tahun - 1;
}
$honor_bulan_lalu = hitungHonorBulan($conn, $guru_id, $bulan_lalu, $tahun_lalu, $tarif);

$response = [
    'periode' => date('F Y', mktime(0, 0, 0, $filter_bulan, 1, $filter_tahun)),
    'pendapatan' => [
        'total_tunjangan' => $honor_bulan_ini['total_tunjangan'],
        'honor_mengajar' => $honor_bulan_ini['honor_mengajar'],
        'detail_mengajar' => $honor_bulan_ini['total_jp'] . " JP",
        'honor_piket' => $honor_bulan_ini['honor_piket'],
        'detail_piket' => $honor_bulan_ini['jumlah_piket'] . "x",
        'honor_ekskul' => $honor_bulan_ini['honor_ekskul'],
        'detail_ekskul' => $honor_bulan_ini['jumlah_ekskul'] . "x",
        'honor_bk' => $honor_bulan_ini['honor_bk'],
        'detail_bk' => $honor_bulan_ini['jumlah_bk'] . "x",
        'uang_transport' => $honor_bulan_ini['uang_transport'], // DIUBAH
        'subtotal' => $honor_bulan_ini['subtotal_pendapatan']
    ],
    'potongan' => [
        'arisan' => $honor_bulan_ini['potongan_arisan'],
        'tabungan' => $honor_bulan_ini['potongan_tabungan'],
        'total' => $honor_bulan_ini['total_potongan']
    ],
    'total_diterima' => $honor_bulan_ini['total_diterima'],
    
    'periode_lalu' => date('F Y', mktime(0, 0, 0, $bulan_lalu, 1, $tahun_lalu)),
    'total_bulan_lalu' => $honor_bulan_lalu['total_diterima'],
    'detail_bulan_lalu' => [
        'total_tunjangan' => $honor_bulan_lalu['total_tunjangan'],
        'honor_mengajar' => $honor_bulan_lalu['honor_mengajar'],
        'detail_mengajar' => $honor_bulan_lalu['total_jp'] . " JP",
        'honor_piket' => $honor_bulan_lalu['honor_piket'],
        'detail_piket' => $honor_bulan_lalu['jumlah_piket'] . "x",
        'honor_ekskul' => $honor_bulan_lalu['honor_ekskul'],
        'detail_ekskul' => $honor_bulan_lalu['jumlah_ekskul'] . "x",
        'honor_bk' => $honor_bulan_lalu['honor_bk'],
        'detail_bk' => $honor_bulan_lalu['jumlah_bk'] . "x",
        'uang_transport' => $honor_bulan_lalu['uang_transport'], // DIUBAH
        'subtotal' => $honor_bulan_lalu['subtotal_pendapatan'],
        'total_potongan' => $honor_bulan_lalu['total_potongan']
    ]
];

http_response_code(200);
echo json_encode($response);
?>