<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// --- MASUKKAN KUNCI API ANDA DI SINI ---
require_once_ __DIR__ . '/../../config-api.php';
$apiKey = GEMINI_API_KEY;
// ------------------------------------

// 1. Ambil data JSON dari aplikasi
$json_data = file_get_contents("php://input");
$data = json_decode($json_data);

if (!$data) {
    http_response_code(400);
    echo json_encode(['message' => 'Data input tidak valid.']);
    exit();
}

// 2. Susun Prompt yang Detail untuk AI
$prompt = "
Anda adalah seorang ahli perancang kurikulum untuk Sekolah Menengah Kejuruan (SMK) di Jawa Barat yang memahami prinsip Kurikulum Merdeka dan filosofi 'Gapura Panca Waluya' (Cageur, Bageur, Bener, Pinter, Singer).

Berdasarkan data modul ajar berikut:
- Nama Penyusun: " . ($data->namaPenyusun ?? 'Guru') . "
- Institusi: " . ($data->institusi ?? 'SMK') . "
- Tahun Penyusunan: " . ($data->tahunPenyusunan ?? '') . "
- Program Keahlian: " . ($data->programKeahlian ?? '') . "
- Mata Pelajaran: " . ($data->mataPelajaran ?? '') . "
- Kelas: " . ($data->kelas ?? '') . "
- Alokasi Waktu: " . ($data->alokasiWaktu ?? '') . "
- Kompetensi Awal: " . ($data->kompetensiAwal ?? '') . "
- Model Pembelajaran: " . (is_array($data->modelPembelajaran) ? implode(', ', $data->modelPembelajaran) : '') . "

Tolong buatkan konten untuk bagian-bagian berikut dalam format JSON yang valid, tanpa tambahan teks pembuka atau penutup. Ikuti contoh dan integrasikan nilai Panca Waluya:

1.  **tujuan_pembelajaran**: (Rumuskan tujuan spesifik dan terukur dengan struktur 'Pinter', 'Singer', 'Bener')
2.  **pemahaman_bermakna**: (Jelaskan manfaat pembelajaran dalam kehidupan nyata dan kaitkan dengan nilai Panca Waluya seperti 'Cageur' dan 'Bageur')
3.  **pertanyaan_pemantik**: (Buat 2-3 pertanyaan yang memancing rasa ingin tahu terkait mata pelajaran dan etika profesi)
4.  **kegiatan_pembelajaran**: (Rancang alur kegiatan Pendahuluan, Inti, dan Penutup. Integrasikan nilai Panca Waluya secara eksplisit di setiap tahap)
5.  **asesmen**: (Rancang 3 jenis asesmen: Diagnostik, Formatif, dan Sumatif yang mengukur aspek teknis dan karakter)
6.  **pengayaan_remedial**: (Berikan masing-masing satu contoh kegiatan pengayaan dan remedial)
7.  **refleksi**: (Buat masing-masing dua pertanyaan refleksi untuk siswa dan guru)
";

// 3. Siapkan data untuk dikirim ke Google AI API
$api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $apiKey;
$request_body = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ]
];

// 4. Kirim request menggunakan cURL
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));

$response_ai = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 5. Proses respons dari AI dan kirim kembali ke aplikasi
if ($http_code == 200) {
    $response_data = json_decode($response_ai, true);
    $generated_text = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    // Hapus backtick dan 'json' jika ada
    $cleaned_json_string = str_replace(['```json', '```'], '', $generated_text);
    
    // Kirim JSON yang sudah bersih ke aplikasi
    http_response_code(200);
    header('Content-Type: application/json');
    echo $cleaned_json_string;
} else {
    http_response_code($http_code);
    echo json_encode(['message' => 'Gagal berkomunikasi dengan AI.', 'details' => json_decode($response_ai)]);
}
?>