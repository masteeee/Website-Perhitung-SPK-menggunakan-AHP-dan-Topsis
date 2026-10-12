<?php
require_once 'config/database.php';
check_login();

$page_title = 'Perhitungan Metode AHP';

// Ambil Kriteria
$kriteria = $pdo->query("SELECT * FROM kriteria ORDER BY kode_kriteria ASC")->fetchAll();
$n = count($kriteria);

// Ambil perbandingan berpasangan dari tabel ahp_perbandingan jika ada
$perbandingan_saved = [];
try {
    $rows = $pdo->query("SELECT id_kriteria_1, id_kriteria_2, nilai FROM ahp_perbandingan")->fetchAll();
    foreach ($rows as $r) {
        $perbandingan_saved[$r['id_kriteria_1']][$r['id_kriteria_2']] = (float)$r['nilai'];
    }
} catch (Exception $e) {
    //
}

// Inisialisasi Matriks A (n x n)
$matrix_A = [];
foreach ($kriteria as $i => $row_i) {
    $id_i = $row_i['id_kriteria'];
    foreach ($kriteria as $j => $row_j) {
        $id_j = $row_j['id_kriteria'];
        if ($id_i == $id_j) {
            $matrix_A[$id_i][$id_j] = 1.0;
        } elseif (isset($perbandingan_saved[$id_i][$id_j])) {
            $matrix_A[$id_i][$id_j] = (float)$perbandingan_saved[$id_i][$id_j];
        } elseif (isset($perbandingan_saved[$id_j][$id_i]) && $perbandingan_saved[$id_j][$id_i] > 0) {
            $matrix_A[$id_i][$id_j] = 1.0 / (float)$perbandingan_saved[$id_j][$id_i];
        } else {
            // Default 1 jika belum pernah diset
            $matrix_A[$id_i][$id_j] = 1.0;
        }
    }
}

// Handle Form Submit Simpan Perbandingan & Hitung AHP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'simpan_matrix' || $action === 'reset_default_ahp') {
        try {
            $pdo->beginTransaction();

            if ($action === 'reset_default_ahp') {
                // Skala perbandingan ideal yang konsisten
                // Misal: C1 > C2 (skala 2), C1 > C3 (skala 3), C2 > C3 (skala 2)
                $matrix_input = [];
                for ($i = 0; $i < $n; $i++) {
                    for ($j = 0; $j < $n; $j++) {
                        $id_i = $kriteria[$i]['id_kriteria'];
                        $id_j = $kriteria[$j]['id_kriteria'];
                        if ($i == $j) {
                            $matrix_input[$id_i][$id_j] = 1.0;
                        } elseif ($i < $j) {
                            $scale = ($j - $i + 1); // C1 vs C2 = 2, C1 vs C3 = 3
                            $matrix_input[$id_i][$id_j] = (float)$scale;
                            $matrix_input[$id_j][$id_i] = 1.0 / $scale;
                        }
                    }
                }
            } else {
                $matrix_input = [];
                $post_pairs = $_POST['pair'] ?? [];
                
                // Set diagonal 1.0
                foreach ($kriteria as $ki) {
                    $matrix_input[$ki['id_kriteria']][$ki['id_kriteria']] = 1.0;
                }

                // Ambil pasangan segitiga atas
                for ($i = 0; $i < $n; $i++) {
                    for ($j = $i + 1; $j < $n; $j++) {
                        $id_i = $kriteria[$i]['id_kriteria'];
                        $id_j = $kriteria[$j]['id_kriteria'];
                        $val = (float)($post_pairs[$id_i][$id_j] ?? 1.0);
                        if ($val <= 0) $val = 1.0;

                        $matrix_input[$id_i][$id_j] = $val;
                        $matrix_input[$id_j][$id_i] = 1.0 / $val;
                    }
                }
            }

            // Simpan ke tabel ahp_perbandingan
            $del = $pdo->prepare("DELETE FROM ahp_perbandingan");
            $del->execute();

            $ins = $pdo->prepare("INSERT INTO ahp_perbandingan (id_kriteria_1, id_kriteria_2, nilai) VALUES (?, ?, ?)");
            foreach ($matrix_input as $k1 => $cols) {
                foreach ($cols as $k2 => $v) {
                    $ins->execute([$k1, $k2, $v]);
                }
            }

            // HITUNG AHP LENGKAP
            // 1. Total Kolom
            $col_sums = [];
            foreach ($kriteria as $col) {
                $c_id = $col['id_kriteria'];
                $sum = 0;
                foreach ($kriteria as $row) {
                    $r_id = $row['id_kriteria'];
                    $sum += $matrix_input[$r_id][$c_id];
                }
                $col_sums[$c_id] = $sum;
            }

            // 2. Normalisasi Matriks & Bobot Prioritas (Eigen Vector w)
            $norm_matrix = [];
            $weights = [];
            foreach ($kriteria as $row) {
                $r_id = $row['id_kriteria'];
                $row_sum = 0;
                foreach ($kriteria as $col) {
                    $c_id = $col['id_kriteria'];
                    $norm_val = $matrix_input[$r_id][$c_id] / ($col_sums[$c_id] ?: 1);
                    $norm_matrix[$r_id][$c_id] = $norm_val;
                    $row_sum += $norm_val;
                }
                $weights[$r_id] = $row_sum / ($n ?: 1);
            }

            // 3. Perkalian Matriks A * w
            $lambda_values = [];
            foreach ($kriteria as $row) {
                $r_id = $row['id_kriteria'];
                $aw = 0;
                foreach ($kriteria as $col) {
                    $c_id = $col['id_kriteria'];
                    $aw += $matrix_input[$r_id][$c_id] * $weights[$c_id];
                }
                $lambda_values[$r_id] = $aw / ($weights[$r_id] ?: 1);
            }

            // 4. Lambda Max, CI, CR
            $lambda_max = array_sum($lambda_values) / ($n ?: 1);
            $ci = ($n > 1) ? ($lambda_max - $n) / ($n - 1) : 0.0;
            $ri = get_saaty_ri($n);
            $cr = ($ri > 0) ? ($ci / $ri) : 0.0;

            // Simpan ke tabel ahp
            $pdo->exec("DELETE FROM ahp");
            $ins_ahp = $pdo->prepare("INSERT INTO ahp (id_kriteria, nilai_perbandingan, bobot_kriteria, lambda_max, ci, cr) VALUES (?, ?, ?, ?, ?, ?)");
            $upd_kriteria = $pdo->prepare("UPDATE kriteria SET bobot = ? WHERE id_kriteria = ?");

            foreach ($kriteria as $k) {
                $kid = $k['id_kriteria'];
                $w = $weights[$kid];
                $ins_ahp->execute([$kid, 1.0, $w, $lambda_max, $ci, $cr]);
                $upd_kriteria->execute([$w, $kid]);
            }

            $pdo->commit();
            set_flash('success', 'Perhitungan AHP berhasil diperbarui dan disimpan! Nilai CR: ' . number_format($cr, 4));
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Gagal memproses AHP: ' . $e->getMessage());
        }

        header('Location: ahp.php');
        exit;
    }
}

// Lakukan kalkulasi saat menampilkan halaman
$col_sums = [];
$norm_matrix = [];
$weights = [];
$lambda_values = [];
$lambda_max = 0;
$ci = 0;
$cr = 0;
$ri = get_saaty_ri($n);

if ($n > 0) {
    // 1. Jumlah Kolom
    foreach ($kriteria as $col) {
        $c_id = $col['id_kriteria'];
        $sum = 0;
        foreach ($kriteria as $row) {
            $r_id = $row['id_kriteria'];
            $sum += $matrix_A[$r_id][$c_id];
        }
        $col_sums[$c_id] = $sum;
    }

    // 2. Normalisasi Matriks & Bobot
    foreach ($kriteria as $row) {
        $r_id = $row['id_kriteria'];
        $row_sum = 0;
        foreach ($kriteria as $col) {
            $c_id = $col['id_kriteria'];
            $norm_val = $matrix_A[$r_id][$c_id] / ($col_sums[$c_id] ?: 1);
            $norm_matrix[$r_id][$c_id] = $norm_val;
            $row_sum += $norm_val;
        }
        $weights[$r_id] = $row_sum / $n;
    }

    // 3. Uji Konsistensi (Lambda)
    foreach ($kriteria as $row) {
        $r_id = $row['id_kriteria'];
        $aw = 0;
        foreach ($kriteria as $col) {
            $c_id = $col['id_kriteria'];
            $aw += $matrix_A[$r_id][$c_id] * $weights[$c_id];
        }
        $lambda_values[$r_id] = $aw / ($weights[$r_id] ?: 1);
    }

    $lambda_max = array_sum($lambda_values) / $n;
    $ci = ($n > 1) ? ($lambda_max - $n) / ($n - 1) : 0.0;
    $cr = ($ri > 0) ? ($ci / $ri) : 0.0;
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
                <h1>Perhitungan Metode AHP</h1>
                <p>Penentuan bobot prioritas kriteria melalui matriks perbandingan berpasangan (Pairwise Comparison)</p>
            </div>
        </div>
        <div class="topbar-right">
            <form action="ahp.php" method="POST" style="display: inline;" onsubmit="return confirm('Terapkan nilai perbandingan berpasangan standar yang konsisten (CR ≤ 0.1)?')">
                <input type="hidden" name="action" value="reset_default_ahp">
                <button type="submit" class="btn btn-secondary" style="font-size: 0.8rem;">
                    <i data-lucide="wand-2"></i> Perbandingan Standar Konsisten
                </button>
            </form>
            <a href="topsis.php" class="btn btn-primary">
                <i data-lucide="arrow-right"></i> Lanjut ke TOPSIS
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

        <!-- Indikator Konsistensi AHP Card -->
        <div class="glass-card" style="margin-bottom: 24px; padding: 22px; border-left: 5px solid <?= $cr <= 0.1 ? '#10b981' : '#ef4444' ?>;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: gap: 20px;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="width: 50px; height: 50px; border-radius: 14px; background: <?= $cr <= 0.1 ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; display: flex; align-items: center; justify-content: center; color: <?= $cr <= 0.1 ? '#34d399' : '#f87171' ?>; font-size: 1.5rem;">
                        <i data-lucide="<?= $cr <= 0.1 ? 'check-check' : 'alert-octagon' ?>"></i>
                    </div>
                    <div>
                        <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 3px;">
                            Status Konsistensi Matriks: 
                            <span style="color: <?= $cr <= 0.1 ? '#34d399' : '#f87171' ?>;">
                                <?= $cr <= 0.1 ? 'KONSISTEN (VALID)' : 'TIDAK KONSISTEN' ?>
                            </span>
                        </h3>
                        <p style="font-size: 0.83rem; color: var(--text-sub);">
                            <?php if ($cr <= 0.1): ?>
                                Nilai Consistency Ratio (CR = <?= number_format($cr, 4) ?>) memenuhi syarat Saaty (CR ≤ 0.1), sehingga bobot prioritas valid dan siap diterapkan pada metode TOPSIS.
                            <?php else: ?>
                                Nilai Consistency Ratio (CR = <?= number_format($cr, 4) ?>) melebihi batas 0.1. Disarankan untuk menyesuaikan perbandingan atau menggunakan tombol <em>Perbandingan Standar Konsisten</em>.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div style="display: flex; gap: 24px; text-align: center;">
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">λ Maksimum</div>
                        <strong style="font-size: 1.15rem; font-family: 'JetBrains Mono', monospace; color: var(--text-main);"><?= number_format($lambda_max, 4) ?></strong>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Index CI</div>
                        <strong style="font-size: 1.15rem; font-family: 'JetBrains Mono', monospace; color: var(--text-main);"><?= number_format($ci, 4) ?></strong>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Random Index (RI)</div>
                        <strong style="font-size: 1.15rem; font-family: 'JetBrains Mono', monospace; color: var(--text-main);"><?= number_format($ri, 2) ?></strong>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Rasio CR</div>
                        <strong style="font-size: 1.25rem; font-family: 'JetBrains Mono', monospace; color: <?= $cr <= 0.1 ? '#059669' : '#dc2626' ?>;"><?= number_format($cr, 4) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Pengaturan Matriks Perbandingan Berpasangan -->
        <div class="glass-card" style="margin-bottom: 28px;">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        <i data-lucide="sliders-horizontal"></i> Form Perbandingan Berpasangan Antar Kriteria
                    </h3>
                    <p class="card-desc">Tentukan skala kepentingan relatif (1 = Sama Penting, 3 = Sedikit Lebih Penting, 5 = Lebih Penting, 7 = Sangat Penting, 9 = Mutlak Lebih Penting)</p>
                </div>
            </div>
            <div class="card-body">
                <form action="ahp.php" method="POST">
                    <input type="hidden" name="action" value="simpan_matrix">
                    
                    <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px;">
                        <?php for ($i = 0; $i < $n; $i++): ?>
                            <?php for ($j = $i + 1; $j < $n; $j++): 
                                $id_i = $kriteria[$i]['id_kriteria'];
                                $id_j = $kriteria[$j]['id_kriteria'];
                                $val = $matrix_A[$id_i][$id_j] ?? 1.0;
                            ?>
                                <div style="display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; border-radius: 12px; background: rgba(15, 23, 42, 0.5); border: 1px solid var(--border-glass); flex-wrap: wrap; gap: 14px;">
                                    <div style="min-width: 220px;">
                                        <strong style="color: var(--secondary); font-size: 0.95rem;">
                                            [<?= htmlspecialchars($kriteria[$i]['kode_kriteria']) ?>] <?= htmlspecialchars($kriteria[$i]['nama_kriteria']) ?>
                                        </strong>
                                    </div>

                                    <div style="display: flex; align-items: center; gap: 12px; flex: 1; max-width: 320px;">
                                        <span style="font-size: 0.8rem; color: var(--text-muted);">Tingkat:</span>
                                        <select name="pair[<?= $id_i ?>][<?= $id_j ?>]" class="form-control" style="font-family: 'JetBrains Mono', monospace; font-size: 0.88rem;">
                                            <?php
                                            $saaty_options = [
                                                '9.0' => '9 - Mutlak lebih penting dari',
                                                '8.0' => '8 - Mendekati mutlak lebih penting dari',
                                                '7.0' => '7 - Sangat lebih penting dari',
                                                '6.0' => '6 - Mendekati sangat penting dari',
                                                '5.0' => '5 - Jelas lebih penting dari',
                                                '4.0' => '4 - Mendekati jelas lebih penting dari',
                                                '3.0' => '3 - Sedikit lebih penting dari',
                                                '2.0' => '2 - Mendekati sedikit lebih penting dari',
                                                '1.0' => '1 - Sama pentingnya dengan',
                                                '0.5' => '1/2 - Sedikit kurang penting dari',
                                                '0.3333' => '1/3 - Kurang penting dari',
                                                '0.25' => '1/4 - Mendekati jelas kurang penting dari',
                                                '0.2' => '1/5 - Jelas kurang penting dari',
                                                '0.1429' => '1/7 - Sangat kurang penting dari',
                                                '0.1111' => '1/9 - Mutlak kurang penting dari'
                                            ];
                                            foreach ($saaty_options as $opt_val => $opt_label) {
                                                $selected = abs((float)$opt_val - $val) < 0.05 ? 'selected' : '';
                                                echo "<option value='{$opt_val}' {$selected}>{$opt_label}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div style="min-width: 220px; text-align: right;">
                                        <strong style="color: #cbd5e1; font-size: 0.95rem;">
                                            [<?= htmlspecialchars($kriteria[$j]['kode_kriteria']) ?>] <?= htmlspecialchars($kriteria[$j]['nama_kriteria']) ?>
                                        </strong>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        <?php endfor; ?>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 12px;">
                        <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">
                            <i data-lucide="calculator"></i> Hitung & Simpan Bobot AHP
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Accordion Tahapan Perhitungan AHP Transparan -->
        <!-- Tahap 1: Matriks Perbandingan Berpasangan -->
        <div class="step-accordion">
            <div class="accordion-header">
                <div class="accordion-title">
                    <span class="accordion-step-num">1</span>
                    <span>Tabel Matriks Perbandingan Berpasangan (A) & Jumlah Kolom</span>
                </div>
                <i data-lucide="chevron-down" class="accordion-chevron"></i>
            </div>
            <div class="accordion-body">
                <div class="table-responsive">
                    <table class="matrix-table">
                        <thead>
                            <tr>
                                <th>Kriteria</th>
                                <?php foreach ($kriteria as $col): ?>
                                    <th><?= htmlspecialchars($col['kode_kriteria']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kriteria as $row): 
                                $r_id = $row['id_kriteria'];
                            ?>
                                <tr>
                                    <th><?= htmlspecialchars($row['kode_kriteria']) ?> - <?= htmlspecialchars($row['nama_kriteria']) ?></th>
                                    <?php foreach ($kriteria as $col): 
                                        $c_id = $col['id_kriteria'];
                                        $v = $matrix_A[$r_id][$c_id];
                                    ?>
                                        <td class="<?= $r_id == $c_id ? 'highlight' : '' ?>">
                                            <?= number_format($v, 4) ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>TOTAL KOLOM (∑)</td>
                                <?php foreach ($kriteria as $col): 
                                    $c_id = $col['id_kriteria'];
                                ?>
                                    <td><strong><?= number_format($col_sums[$c_id], 4) ?></strong></td>
                                <?php endforeach; ?>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tahap 2: Normalisasi Matriks & Bobot Prioritas -->
        <div class="step-accordion">
            <div class="accordion-header">
                <div class="accordion-title">
                    <span class="accordion-step-num">2</span>
                    <span>Tabel Matriks Normalisasi & Bobot Prioritas (Eigen Vector w)</span>
                </div>
                <i data-lucide="chevron-down" class="accordion-chevron"></i>
            </div>
            <div class="accordion-body">
                <div class="table-responsive">
                    <table class="matrix-table">
                        <thead>
                            <tr>
                                <th>Kriteria</th>
                                <?php foreach ($kriteria as $col): ?>
                                    <th><?= htmlspecialchars($col['kode_kriteria']) ?></th>
                                <?php endforeach; ?>
                                <th style="background: rgba(79, 70, 229, 0.1); color: var(--primary); font-weight: 700;">Bobot Prioritas (w)</th>
                                <th style="background: rgba(16, 185, 129, 0.1); color: var(--success); font-weight: 700;">Persentase (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_w = 0;
                            foreach ($kriteria as $row): 
                                $r_id = $row['id_kriteria'];
                                $w = $weights[$r_id];
                                $total_w += $w;
                            ?>
                                <tr>
                                    <th><?= htmlspecialchars($row['kode_kriteria']) ?> - <?= htmlspecialchars($row['nama_kriteria']) ?></th>
                                    <?php foreach ($kriteria as $col): 
                                        $c_id = $col['id_kriteria'];
                                    ?>
                                        <td><?= number_format($norm_matrix[$r_id][$c_id], 4) ?></td>
                                    <?php endforeach; ?>
                                    <td style="background: rgba(79, 70, 229, 0.15); font-weight: 700; color: #818cf8;">
                                        <?= number_format($w, 4) ?>
                                    </td>
                                    <td style="background: rgba(16, 185, 129, 0.1); font-weight: 700; color: #34d399;">
                                        <?= number_format($w * 100, 2) ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="<?= $n + 1 ?>">TOTAL BOBOT (∑w)</td>
                                <td style="color: #818cf8;"><strong><?= number_format($total_w, 4) ?></strong></td>
                                <td style="color: #34d399;"><strong>100.00%</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tahap 3: Uji Konsistensi Saaty -->
        <div class="step-accordion">
            <div class="accordion-header">
                <div class="accordion-title">
                    <span class="accordion-step-num">3</span>
                    <span>Tabel Perhitungan Konsistensi Matriks (λ Maks, CI, dan CR)</span>
                </div>
                <i data-lucide="chevron-down" class="accordion-chevron"></i>
            </div>
            <div class="accordion-body">
                <div class="table-responsive">
                    <table class="matrix-table">
                        <thead>
                            <tr>
                                <th>Kriteria</th>
                                <th>Bobot (w)</th>
                                <th>(A × w)</th>
                                <th>λ = (A × w) / w</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kriteria as $row): 
                                $r_id = $row['id_kriteria'];
                                $w = $weights[$r_id];
                                $aw = $w * $lambda_values[$r_id];
                            ?>
                                <tr>
                                    <th><?= htmlspecialchars($row['kode_kriteria']) ?> - <?= htmlspecialchars($row['nama_kriteria']) ?></th>
                                    <td><?= number_format($w, 4) ?></td>
                                    <td><?= number_format($aw, 4) ?></td>
                                    <td style="font-weight: 700; color: var(--secondary);"><?= number_format($lambda_values[$r_id], 4) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3">LAMBDA MAKSIMUM (λ_max = Rata-rata λ)</td>
                                <td style="color: var(--text-main); font-size: 1rem;"><strong><?= number_format($lambda_max, 4) ?></strong></td>
                            </tr>
                            <tr>
                                <td colspan="3">CONSISTENCY INDEX (CI = (λ_max - n) / (n - 1))</td>
                                <td style="color: var(--text-main);"><strong><?= number_format($ci, 4) ?></strong></td>
                            </tr>
                            <tr>
                                <td colspan="3">RANDOM INDEX (RI) untuk n = <?= $n ?></td>
                                <td style="color: var(--text-main);"><strong><?= number_format($ri, 2) ?></strong></td>
                            </tr>
                            <tr>
                                <td colspan="3">CONSISTENCY RATIO (CR = CI / RI)</td>
                                <td style="color: <?= $cr <= 0.1 ? '#059669' : '#dc2626' ?>; font-size: 1.1rem;">
                                    <strong><?= number_format($cr, 4) ?></strong>
                                    (<?= $cr <= 0.1 ? 'Konsisten ≤ 0.1' : 'Tidak Konsisten > 0.1' ?>)
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>
