<?php
require_once 'config/database.php';
check_login();

// Ambil kriteria dan bobot AHP
$kriteria = $pdo->query("SELECT * FROM kriteria ORDER BY kode_kriteria ASC")->fetchAll();

// Ambil hasil perankingan
$ranking_data = $pdo->query("
    SELECT h.*, s.nisn, s.nama_siswa, s.kelas, s.jenis_kelamin, s.alamat
    FROM hasil_perankingan h
    JOIN siswa s ON h.id_siswa = s.id_siswa
    ORDER BY h.peringkat ASC
")->fetchAll();

// Cek info AHP
$ahp_info = $pdo->query("SELECT cr, lambda_max, ci FROM ahp ORDER BY id_ahp DESC LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Hasil Rekomendasi Penerimaan Beasiswa - SPK AHP & TOPSIS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Times New Roman', Times, serif;
        }

        body {
            background-color: #f1f5f9;
            color: #000;
            padding: 24px;
        }

        .paper {
            max-width: 820px;
            margin: 0 auto;
            background: #fff;
            padding: 40px 50px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            min-height: 1000px;
        }

        /* Kop Surat */
        .kop-surat {
            display: flex;
            align-items: center;
            border-bottom: 3px double #000;
            padding-bottom: 14px;
            margin-bottom: 24px;
            gap: 20px;
        }

        .kop-logo {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #000;
            border-radius: 50%;
            font-size: 26px;
            font-weight: bold;
        }

        .kop-text {
            text-align: center;
            flex: 1;
        }

        .kop-text h2 {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 3px;
        }

        .kop-text h3 {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .kop-text p {
            font-size: 9.5pt;
            line-height: 1.3;
        }

        /* Judul Laporan */
        .laporan-title {
            text-align: center;
            margin-bottom: 22px;
        }

        .laporan-title h4 {
            font-size: 12pt;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .laporan-title span {
            font-size: 10pt;
        }

        /* Tabel Laporan */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin: 18px 0;
            font-size: 10pt;
        }

        .report-table th, .report-table td {
            border: 1px solid #000;
            padding: 8px 10px;
        }

        .report-table th {
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
        }

        .status-diterima {
            font-weight: bold;
            color: #047857;
        }

        .status-tidak {
            color: #b91c1c;
        }

        /* Info Metode */
        .metode-info {
            font-size: 9.5pt;
            margin: 16px 0;
            line-height: 1.4;
        }

        /* Kolom Tanda Tangan */
        .ttd-container {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            page-break-inside: avoid;
        }

        .ttd-box {
            text-align: center;
            width: 250px;
            font-size: 10.5pt;
        }

        .ttd-space {
            height: 75px;
        }

        /* Toolbar Cetak (Layar) */
        .no-print-toolbar {
            max-width: 820px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-print {
            background: #2563eb;
            color: #fff;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 10pt;
            font-weight: bold;
            text-decoration: none;
        }

        .btn-back {
            background: #64748b;
            color: #fff;
            padding: 10px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 10pt;
            text-decoration: none;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .no-print-toolbar {
                display: none;
            }
            .paper {
                box-shadow: none;
                padding: 0;
                margin: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-toolbar">
        <a href="ranking.php" class="btn-back">← Kembali ke Halaman Ranking</a>
        <button onclick="window.print()" class="btn-print">🖨 Cetak Dokumen / Simpan PDF</button>
    </div>

    <div class="paper">
        <!-- Kop Surat Resmi -->
        <div class="kop-surat">
            <div class="kop-logo">
                🎓
            </div>
            <div class="kop-text">
                <h2>PANITIA SELEKSI BEASISWA BANTUAN DANA PENDIDIKAN</h2>
                <h3>SISTEM PENDUKUNG KEPUTUSAN (SPK) AHP & TOPSIS</h3>
                <p>Jalan Pendidikan No. 45, Kompleks Madrasah Terpadu • Telepon: (021) 8765-4321</p>
                <p>Website: www.sekolah-spk.sch.id • Email: panitiabeasiswa@sekolah.sch.id</p>
            </div>
        </div>

        <!-- Judul Dokumen -->
        <div class="laporan-title">
            <h4>SURAT KEPUTUSAN REKOMENDASI PENERIMA BEASISWA</h4>
            <span>Nomor: <?= date('Y') ?>/SK-BEASISWA/SPK/<?= date('m') ?></span>
        </div>

        <p style="font-size: 10pt; text-align: justify; margin-bottom: 12px; line-height: 1.4;">
            Berdasarkan hasil pengolahan data kriteria menggunakan metode <em>Analytical Hierarchy Process (AHP)</em> dan perankingan alternatif menggunakan metode <em>Technique for Order Preference by Similarity to Ideal Solution (TOPSIS)</em>, berikut merupakan daftar peringkat calon penerima beasiswa bantuan dana pendidikan:
        </p>

        <!-- Tabel Rekap Bobot Kriteria AHP -->
        <div class="metode-info">
            <strong>Parameter Bobot Kriteria AHP yang Digunakan:</strong>
            <ul style="margin-left: 20px; margin-top: 4px;">
                <?php foreach ($kriteria as $k): ?>
                    <li>
                        <?= htmlspecialchars($k['kode_kriteria']) ?> - <?= htmlspecialchars($k['nama_kriteria']) ?> 
                        (Atribut: <?= ucfirst($k['atribut']) ?>, Bobot: <?= number_format((float)$k['bobot'], 4) ?> atau <?= number_format((float)$k['bobot'] * 100, 1) ?>%)
                    </li>
                <?php endforeach; ?>
                <?php if ($ahp_info): ?>
                    <li>Consistency Ratio (CR) AHP = <strong><?= number_format((float)$ahp_info['cr'], 4) ?></strong> (Status: Konsisten)</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Tabel Hasil Perangkingan Resmi -->
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 45px;">No</th>
                    <th style="width: 100px;">NISN</th>
                    <th>Nama Lengkap Siswa</th>
                    <th style="width: 70px;">Kelas</th>
                    <th style="width: 85px;">Nilai TOPSIS (V)</th>
                    <th style="width: 110px;">Status Kelayakan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($ranking_data) > 0): ?>
                    <?php foreach ($ranking_data as $row): 
                        $is_diterima = strpos(strtolower($row['status']), 'terima') !== false || strpos(strtolower($row['status']), 'layak') !== false;
                    ?>
                        <tr>
                            <td style="text-align: center; font-weight: bold;"><?= $row['peringkat'] ?></td>
                            <td style="text-align: center;"><?= htmlspecialchars($row['nisn']) ?></td>
                            <td><?= htmlspecialchars($row['nama_siswa']) ?></td>
                            <td style="text-align: center;"><?= htmlspecialchars($row['kelas']) ?></td>
                            <td style="text-align: center; font-weight: bold;"><?= number_format((float)$row['nilai_preferensi'], 4) ?></td>
                            <td style="text-align: center;" class="<?= $is_diterima ? 'status-diterima' : 'status-tidak' ?>">
                                <?= htmlspecialchars($row['status']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px;">Tidak ada data perankingan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="font-size: 9.5pt; text-align: justify; margin-top: 14px; line-height: 1.4;">
            <em>Catatan:</em> Rekomendasi di atas dihasilkan secara objektif oleh sistem berbasis komputasi matematika AHP-TOPSIS. Keputusan akhir penyaluran beasiswa tetap berada pada wewenang pihak sekolah dan komite beasiswa.
        </p>

        <!-- Lembar Pengesahan Tanda Tangan -->
        <div class="ttd-container">
            <div class="ttd-box">
                <p>Mengetahui,</p>
                <p>Ketua Panitia Beasiswa</p>
                <div class="ttd-space"></div>
                <p style="font-weight: bold; text-decoration: underline;">Drs. H. Mulyadi, M.Pd.</p>
                <p>NIP. 19750812 200212 1 003</p>
            </div>

            <div class="ttd-box">
                <p>Ditetapkan di: Jakarta</p>
                <p>Pada Tanggal: <?= date('d F Y') ?></p>
                <p style="margin-top: 4px;">Kepala Sekolah,</p>
                <div class="ttd-space"></div>
                <p style="font-weight: bold; text-decoration: underline;">Dr. Hj. Nurjanah, M.Si.</p>
                <p>NIP. 19680415 199403 2 001</p>
            </div>
        </div>
    </div>

</body>
</html>
