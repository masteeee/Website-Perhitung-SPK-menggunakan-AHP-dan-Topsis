<?php
require_once 'config/database.php';
check_login();

$page_title = 'Perhitungan Metode TOPSIS';

// 1. Ambil Kriteria Aktif beserta Bobot AHP
$kriteria = $pdo->query("SELECT * FROM kriteria ORDER BY kode_kriteria ASC")->fetchAll();
$total_kriteria = count($kriteria);

// Ambil bobot terbaru dari tabel ahp jika ada
$bobot_map = [];
foreach ($kriteria as $k) {
    $ahp_row = $pdo->prepare("SELECT bobot_kriteria FROM ahp WHERE id_kriteria = :id ORDER BY id_ahp DESC LIMIT 1");
    $ahp_row->execute([':id' => $k['id_kriteria']]);
    $res = $ahp_row->fetch();
    $w = ($res && $res['bobot_kriteria'] > 0) ? (float)$res['bobot_kriteria'] : (float)$k['bobot'];
    // Jika bobot masih 0, berikan bobot rata-rata
    $bobot_map[$k['id_kriteria']] = ($w > 0) ? $w : (1.0 / ($total_kriteria ?: 1));
}

// 2. Ambil Siswa yang Memiliki Nilai Lengkap
$siswa_list = $pdo->query("SELECT * FROM siswa ORDER BY id_siswa ASC")->fetchAll();
$total_siswa = count($siswa_list);

// Matriks Keputusan X: map[id_siswa][id_kriteria] => nilai
$nilai_map = [];
$raw_penilaian = $pdo->query("SELECT id_siswa, id_kriteria, nilai FROM penilaian")->fetchAll();
foreach ($raw_penilaian as $np) {
    $nilai_map[$np['id_siswa']][$np['id_kriteria']] = (float)$np['nilai'];
}

// Filter hanya siswa yang semua nilainya terisi
$siswa_valid = [];
foreach ($siswa_list as $s) {
    $lengkap = true;
    foreach ($kriteria as $k) {
        if (!isset($nilai_map[$s['id_siswa']][$k['id_kriteria']])) {
            $lengkap = false;
            break;
        }
    }
    if ($lengkap) {
        $siswa_valid[] = $s;
    }
}
$m = count($siswa_valid); // Jumlah alternatif valid

// Handle Simpan Hasil Perhitungan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'proses_simpan_topsis' && $m > 0 && $total_kriteria > 0) {
        try {
            $pdo->beginTransaction();

            // Hitung TOPSIS
            // Pembagi / Akar Kuadrat Penjumlahan Kuadrat
            $pembagi = [];
            foreach ($kriteria as $k) {
                $kid = $k['id_kriteria'];
                $sum_sq = 0;
                foreach ($siswa_valid as $s) {
                    $val = $nilai_map[$s['id_siswa']][$kid];
                    $sum_sq += ($val * $val);
                }
                $pembagi[$kid] = sqrt($sum_sq) ?: 1.0;
            }

            // Matriks Normalisasi (R) & Normalisasi Terbobot (Y)
            $matrix_R = [];
            $matrix_Y = [];
            foreach ($siswa_valid as $s) {
                $sid = $s['id_siswa'];
                foreach ($kriteria as $k) {
                    $kid = $k['id_kriteria'];
                    $r = $nilai_map[$sid][$kid] / $pembagi[$kid];
                    $y = $r * $bobot_map[$kid];
                    $matrix_R[$sid][$kid] = $r;
                    $matrix_Y[$sid][$kid] = $y;
                }
            }

            // Solusi Ideal Positif (A+) dan Solusi Ideal Negatif (A-)
            $solusi_A_plus = [];
            $solusi_A_min = [];
            foreach ($kriteria as $k) {
                $kid = $k['id_kriteria'];
                $is_benefit = strtolower($k['atribut']) === 'benefit';
                $all_y = [];
                foreach ($siswa_valid as $s) {
                    $all_y[] = $matrix_Y[$s['id_siswa']][$kid];
                }

                if ($is_benefit) {
                    $solusi_A_plus[$kid] = max($all_y);
                    $solusi_A_min[$kid] = min($all_y);
                } else { // Cost
                    $solusi_A_plus[$kid] = min($all_y);
                    $solusi_A_min[$kid] = max($all_y);
                }
            }

            // Jarak Solusi Ideal Positif (D+) & Negatif (D-) & Preferensi (V)
            $jarak_D_plus = [];
            $jarak_D_min = [];
            $preferensi_V = [];
            foreach ($siswa_valid as $s) {
                $sid = $s['id_siswa'];
                $sum_diff_sq_plus = 0;
                $sum_diff_sq_min = 0;

                foreach ($kriteria as $k) {
                    $kid = $k['id_kriteria'];
                    $diff_plus = $matrix_Y[$sid][$kid] - $solusi_A_plus[$kid];
                    $diff_min = $matrix_Y[$sid][$kid] - $solusi_A_min[$kid];

                    $sum_diff_sq_plus += ($diff_plus * $diff_plus);
                    $sum_diff_sq_min += ($diff_min * $diff_min);
                }

                $d_plus = sqrt($sum_diff_sq_plus);
                $d_min = sqrt($sum_diff_sq_min);
                $v = ($d_plus + $d_min) > 0 ? ($d_min / ($d_plus + $d_min)) : 0.0;

                $jarak_D_plus[$sid] = $d_plus;
                $jarak_D_min[$sid] = $d_min;
                $preferensi_V[$sid] = $v;
            }

            // Simpan ke tabel topsis
            $pdo->exec("DELETE FROM topsis");
            $ins_topsis = $pdo->prepare("
                INSERT INTO topsis (id_siswa, nilai_normalisasi, nilai_terbobot, solusi_ideal_positif, solusi_ideal_negatif, jarak_positif, jarak_negatif, nilai_preferensi)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($siswa_valid as $s) {
                $sid = $s['id_siswa'];
                // Nilai rata-rata representasi untuk kolom single record
                $avg_r = array_sum($matrix_R[$sid]) / count($matrix_R[$sid]);
                $avg_y = array_sum($matrix_Y[$sid]) / count($matrix_Y[$sid]);
                $ins_topsis->execute([
                    $sid,
                    $avg_r,
                    $avg_y,
                    $solusi_A_plus[$kriteria[0]['id_kriteria']],
                    $solusi_A_min[$kriteria[0]['id_kriteria']],
                    $jarak_D_plus[$sid],
                    $jarak_D_min[$sid],
                    $preferensi_V[$sid]
                ]);
            }

            // Simpan ke tabel hasil_perankingan
            // Urutkan preferensi desc
            arsort($preferensi_V);

            $pdo->exec("DELETE FROM hasil_perankingan");
            // Sinkronkan juga jika ada tabel hasil_perangkingan
            try { $pdo->exec("DELETE FROM hasil_perangkingan"); } catch (Exception $e) {}

            $ins_ranking = $pdo->prepare("
                INSERT INTO hasil_perankingan (id_siswa, nilai_preferensi, peringkat, status)
                VALUES (?, ?, ?, ?)
            ");
            
            $ins_ranking_alt = null;
            try {
                $ins_ranking_alt = $pdo->prepare("INSERT INTO hasil_perangkingan (id_siswa, nilai_preferensi, peringkat, status) VALUES (?, ?, ?, ?)");
            } catch (Exception $e) {}

            $rank = 1;
            // Kuota default 3 penerima teratas atau nilai preferensi >= 0.5
            $kuota = min(3, count($preferensi_V));
            foreach ($preferensi_V as $sid => $v_score) {
                $status = ($rank <= $kuota) ? 'Diterima' : 'Tidak Diterima';
                $ins_ranking->execute([$sid, $v_score, $rank, $status]);
                if ($ins_ranking_alt) {
                    try { $ins_ranking_alt->execute([$sid, $v_score, $rank, $status]); } catch (Exception $e) {}
                }
                $rank++;
            }

            $pdo->commit();
            set_flash('success', 'Perhitungan TOPSIS dan penentuan ranking penerima beasiswa berhasil diproses dan disimpan ke basis data!');
            header('Location: ranking.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Gagal memproses TOPSIS: ' . $e->getMessage());
        }
    }
}

// Hitung kalkulasi untuk ditampilkan pada halaman jika data lengkap
$pembagi = [];
$matrix_R = [];
$matrix_Y = [];
$solusi_A_plus = [];
$solusi_A_min = [];
$jarak_D_plus = [];
$jarak_D_min = [];
$preferensi_V = [];

if ($m > 0 && $total_kriteria > 0) {
    // Pembagi
    foreach ($kriteria as $k) {
        $kid = $k['id_kriteria'];
        $sum_sq = 0;
        foreach ($siswa_valid as $s) {
            $val = $nilai_map[$s['id_siswa']][$kid];
            $sum_sq += ($val * $val);
        }
        $pembagi[$kid] = sqrt($sum_sq) ?: 1.0;
    }

    // Normalisasi (R) & Normalisasi Terbobot (Y)
    foreach ($siswa_valid as $s) {
        $sid = $s['id_siswa'];
        foreach ($kriteria as $k) {
            $kid = $k['id_kriteria'];
            $r = $nilai_map[$sid][$kid] / $pembagi[$kid];
            $y = $r * $bobot_map[$kid];
            $matrix_R[$sid][$kid] = $r;
            $matrix_Y[$sid][$kid] = $y;
        }
    }

    // Solusi Ideal Positif & Negatif
    foreach ($kriteria as $k) {
        $kid = $k['id_kriteria'];
        $is_benefit = strtolower($k['atribut']) === 'benefit';
        $all_y = [];
        foreach ($siswa_valid as $s) {
            $all_y[] = $matrix_Y[$s['id_siswa']][$kid];
        }

        if ($is_benefit) {
            $solusi_A_plus[$kid] = max($all_y);
            $solusi_A_min[$kid] = min($all_y);
        } else {
            $solusi_A_plus[$kid] = min($all_y);
            $solusi_A_min[$kid] = max($all_y);
        }
    }

    // Jarak & Preferensi
    foreach ($siswa_valid as $s) {
        $sid = $s['id_siswa'];
        $sum_diff_sq_plus = 0;
        $sum_diff_sq_min = 0;

        foreach ($kriteria as $k) {
            $kid = $k['id_kriteria'];
            $diff_plus = $matrix_Y[$sid][$kid] - $solusi_A_plus[$kid];
            $diff_min = $matrix_Y[$sid][$kid] - $solusi_A_min[$kid];

            $sum_diff_sq_plus += ($diff_plus * $diff_plus);
            $sum_diff_sq_min += ($diff_min * $diff_min);
        }

        $d_plus = sqrt($sum_diff_sq_plus);
        $d_min = sqrt($sum_diff_sq_min);
        $v = ($d_plus + $d_min) > 0 ? ($d_min / ($d_plus + $d_min)) : 0.0;

        $jarak_D_plus[$sid] = $d_plus;
        $jarak_D_min[$sid] = $d_min;
        $preferensi_V[$sid] = $v;
    }
}

$flash_success = get_flash('success');
$flash_error = get_flash('error');

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="main-wrapper">
    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-toggle" id="mobileToggle">
                <i data-lucide="menu"></i>
            </button>
            <div class="page-title-box">
                <h1>Perhitungan Metode TOPSIS</h1>
                <p>Kalkulasi preferensi alternatif berdasarkan kedekatan geometris terhadap solusi ideal</p>
            </div>
        </div>
        <div class="topbar-right">
            <?php if ($m > 0): ?>
                <form action="topsis.php" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="proses_simpan_topsis">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 22px;">
                        <i data-lucide="save"></i> Simpan & Update Ranking
                    </button>
                </form>
            <?php endif; ?>
            <a href="ranking.php" class="btn btn-success">
                <i data-lucide="trophy"></i> Buka Hasil Perangkingan
            </a>
        </div>
    </header>

    <main class="content-body">
        <?php if ($flash_success): ?>
            <div class="alert alert-success">
                <i data-lucide="check-circle-2"></i>
                <div><?= htmlspecialchars($flash_success) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
            <div class="alert alert-error">
                <i data-lucide="alert-circle"></i>
                <div><?= htmlspecialchars($flash_error) ?></div>
            </div>
        <?php endif; ?>

        <!-- Bobot AHP yang Digunakan Banner -->
        <div class="glass-card" style="margin-bottom: 24px; padding: 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h4 style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">
                        <i data-lucide="git-merge" style="color: var(--secondary); vertical-align: middle;"></i> Bobot Kriteria yang Diterapkan (Sumber: Metode AHP)
                    </h4>
                    <p style="font-size: 0.82rem; color: var(--text-muted);">
                        Nilai bobot prioritas diperoleh dari hasil uji konsistensi berpasangan Analytical Hierarchy Process
                    </p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php foreach ($kriteria as $k): 
                        $w = $bobot_map[$k['id_kriteria']];
                    ?>
                        <div style="padding: 6px 14px; border-radius: 20px; background: #f1f5f9; border: 1px solid var(--border-glass); font-size: 0.82rem;">
                            <span style="color: var(--secondary); font-weight: 700;"><?= htmlspecialchars($k['kode_kriteria']) ?>:</span>
                            <span style="color: var(--text-main); font-family: 'JetBrains Mono', monospace; font-weight: 600;"><?= number_format($w, 4) ?></span>
                            <span style="font-size: 0.72rem; color: var(--text-muted);">(<?= number_format($w * 100, 1) ?>%)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if ($m === 0): ?>
            <div class="glass-card" style="padding: 40px; text-align: center;">
                <i data-lucide="alert-circle" style="width: 56px; height: 56px; color: var(--warning); margin-bottom: 14px;"></i>
                <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 6px;">Data Penilaian Siswa Belum Lengkap</h3>
                <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto 20px;">
                    Untuk menjalankan kalkulasi metode TOPSIS, setiap siswa harus memiliki nilai pada seluruh kriteria yang aktif.
                </p>
                <a href="penilaian.php" class="btn btn-primary">
                    <i data-lucide="clipboard-check"></i> Lengkapi Nilai di Halaman Penilaian
                </a>
            </div>
        <?php else: ?>

            <!-- Tahap 1: Matriks Keputusan (X) -->
            <div class="step-accordion">
                <div class="accordion-header">
                    <div class="accordion-title">
                        <span class="accordion-step-num">1</span>
                        <span>Tahap 1: Matriks Keputusan Alternatif (X) & Nilai Pembagi (Akar Kuadrat)</span>
                    </div>
                    <i data-lucide="chevron-down" class="accordion-chevron"></i>
                </div>
                <div class="accordion-body">
                    <div class="table-responsive">
                        <table class="matrix-table">
                            <thead>
                                <tr>
                                    <th>Alternatif Siswa</th>
                                    <?php foreach ($kriteria as $k): ?>
                                        <th>
                                            <?= htmlspecialchars($k['kode_kriteria']) ?>
                                            <div style="font-size: 0.7rem; color: var(--text-muted);">(<?= ucfirst($k['atribut']) ?>)</div>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($siswa_valid as $s): 
                                    $sid = $s['id_siswa'];
                                ?>
                                    <tr>
                                        <th style="text-align: left;"><?= htmlspecialchars($s['nama_siswa']) ?></th>
                                        <?php foreach ($kriteria as $k): 
                                            $kid = $k['id_kriteria'];
                                            $val = $nilai_map[$sid][$kid];
                                        ?>
                                            <td><?= number_format($val, 2) ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td style="text-align: left;">NILAI PEMBAGI (√∑ x_ij²)</td>
                                    <?php foreach ($kriteria as $k): 
                                        $kid = $k['id_kriteria'];
                                    ?>
                                        <td style="color: #38bdf8; font-weight: 700;"><?= number_format($pembagi[$kid], 4) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tahap 2: Matriks Normalisasi (R) -->
            <div class="step-accordion">
                <div class="accordion-header">
                    <div class="accordion-title">
                        <span class="accordion-step-num">2</span>
                        <span>Tahap 2: Matriks Keputusan Ternormalisasi (R = x_ij / √∑ x_ij²)</span>
                    </div>
                    <i data-lucide="chevron-down" class="accordion-chevron"></i>
                </div>
                <div class="accordion-body">
                    <div class="table-responsive">
                        <table class="matrix-table">
                            <thead>
                                <tr>
                                    <th>Alternatif Siswa</th>
                                    <?php foreach ($kriteria as $k): ?>
                                        <th><?= htmlspecialchars($k['kode_kriteria']) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($siswa_valid as $s): 
                                    $sid = $s['id_siswa'];
                                ?>
                                    <tr>
                                        <th style="text-align: left;"><?= htmlspecialchars($s['nama_siswa']) ?></th>
                                        <?php foreach ($kriteria as $k): 
                                            $kid = $k['id_kriteria'];
                                        ?>
                                            <td><?= number_format($matrix_R[$sid][$kid], 4) ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tahap 3: Matriks Ternormalisasi Terbobot (Y) -->
            <div class="step-accordion">
                <div class="accordion-header">
                    <div class="accordion-title">
                        <span class="accordion-step-num">3</span>
                        <span>Tahap 3: Matriks Ternormalisasi Terbobot (Y = w_j × r_ij)</span>
                    </div>
                    <i data-lucide="chevron-down" class="accordion-chevron"></i>
                </div>
                <div class="accordion-body">
                    <div class="table-responsive">
                        <table class="matrix-table">
                            <thead>
                                <tr>
                                    <th>Alternatif Siswa</th>
                                    <?php foreach ($kriteria as $k): ?>
                                        <th><?= htmlspecialchars($k['kode_kriteria']) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($siswa_valid as $s): 
                                    $sid = $s['id_siswa'];
                                ?>
                                    <tr>
                                        <th style="text-align: left;"><?= htmlspecialchars($s['nama_siswa']) ?></th>
                                        <?php foreach ($kriteria as $k): 
                                            $kid = $k['id_kriteria'];
                                        ?>
                                            <td style="color: #a5b4fc;"><?= number_format($matrix_Y[$sid][$kid], 4) ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tahap 4: Solusi Ideal Positif & Negatif -->
            <div class="step-accordion">
                <div class="accordion-header">
                    <div class="accordion-title">
                        <span class="accordion-step-num">4</span>
                        <span>Tahap 4: Solusi Ideal Positif (A+) & Solusi Ideal Negatif (A-)</span>
                    </div>
                    <i data-lucide="chevron-down" class="accordion-chevron"></i>
                </div>
                <div class="accordion-body">
                    <div class="table-responsive">
                        <table class="matrix-table">
                            <thead>
                                <tr>
                                    <th>Solusi Ideal</th>
                                    <?php foreach ($kriteria as $k): ?>
                                        <th>
                                            <?= htmlspecialchars($k['kode_kriteria']) ?>
                                            <div style="font-size: 0.7rem; color: var(--text-muted);">(<?= ucfirst($k['atribut']) ?>)</div>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th style="color: #34d399; text-align: left;">SOLUSI IDEAL POSITIF (A+)</th>
                                    <?php foreach ($kriteria as $k): 
                                        $kid = $k['id_kriteria'];
                                    ?>
                                        <td style="color: #34d399; font-weight: 700;"><?= number_format($solusi_A_plus[$kid], 4) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <th style="color: #f87171; text-align: left;">SOLUSI IDEAL NEGATIF (A-)</th>
                                    <?php foreach ($kriteria as $k): 
                                        $kid = $k['id_kriteria'];
                                    ?>
                                        <td style="color: #f87171; font-weight: 700;"><?= number_format($solusi_A_min[$kid], 4) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tahap 5 & 6: Jarak Solusi Ideal & Nilai Preferensi (V) -->
            <div class="step-accordion">
                <div class="accordion-header">
                    <div class="accordion-title">
                        <span class="accordion-step-num">5 & 6</span>
                        <span>Tahap 5 & 6: Jarak Pemisahan (D+, D-) dan Nilai Preferensi Akhir (V_i)</span>
                    </div>
                    <i data-lucide="chevron-down" class="accordion-chevron"></i>
                </div>
                <div class="accordion-body">
                    <div class="table-responsive">
                        <table class="matrix-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Alternatif Siswa</th>
                                    <th>Jarak Solusi Positif (D+)</th>
                                    <th>Jarak Solusi Negatif (D-)</th>
                                    <th style="background: rgba(14, 165, 233, 0.12); color: var(--secondary); font-weight: 700;">Nilai Preferensi (V_i = D- / (D+ + D-))</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                foreach ($siswa_valid as $s): 
                                    $sid = $s['id_siswa'];
                                    $v = $preferensi_V[$sid];
                                ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <th style="text-align: left;"><?= htmlspecialchars($s['nama_siswa']) ?></th>
                                        <td style="color: #f87171;"><?= number_format($jarak_D_plus[$sid], 4) ?></td>
                                        <td style="color: #34d399;"><?= number_format($jarak_D_min[$sid], 4) ?></td>
                                        <td style="background: rgba(14, 165, 233, 0.15); font-weight: 800; font-size: 1rem; color: #38bdf8;">
                                            <?= number_format($v, 4) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 20px; text-align: right;">
                        <form action="topsis.php" method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="proses_simpan_topsis">
                            <button type="submit" class="btn btn-primary" style="padding: 12px 28px;">
                                <i data-lucide="check-circle-2"></i> Simpan Hasil & Lihat Perangkingan Resmi
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>
