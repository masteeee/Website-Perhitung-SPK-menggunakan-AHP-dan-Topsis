<?php
require_once 'config/database.php';
check_login();

$page_title = 'Data Penilaian Siswa';

// Ambil Kriteria Aktif
$kriteria_list = $pdo->query("SELECT * FROM kriteria ORDER BY kode_kriteria ASC")->fetchAll();
$total_kriteria = count($kriteria_list);

// Handle Simpan Penilaian Per Siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'simpan_penilaian') {
        $id_siswa = (int)($_POST['id_siswa'] ?? 0);
        $nilai_kriteria = $_POST['nilai'] ?? []; // array [id_kriteria => nilai]

        if ($id_siswa <= 0 || empty($nilai_kriteria)) {
            set_flash('error', 'Pilih siswa dan masukkan nilai kriteria dengan benar.');
        } else {
            try {
                $pdo->beginTransaction();

                // Hapus nilai lama siswa ini
                $del = $pdo->prepare("DELETE FROM penilaian WHERE id_siswa = :s");
                $del->execute([':s' => $id_siswa]);

                // Insert nilai baru
                $ins = $pdo->prepare("INSERT INTO penilaian (id_siswa, id_kriteria, nilai) VALUES (:s, :k, :v)");
                foreach ($nilai_kriteria as $id_k => $val) {
                    // Bersihkan pemisah ribuan rupiah jika ada
                    $clean_val = str_replace(['Rp', '.', ' '], '', $val);
                    $clean_val = str_replace(',', '.', $clean_val);
                    $numeric_val = (float)$clean_val;

                    $ins->execute([
                        ':s' => $id_siswa,
                        ':k' => (int)$id_k,
                        ':v' => $numeric_val
                    ]);
                }

                $pdo->commit();
                set_flash('success', 'Data penilaian berhasil disimpan.');
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('error', 'Gagal menyimpan penilaian: ' . $e->getMessage());
            }
        }
        header('Location: penilaian.php');
        exit;
    }

    // Fitur Isi Nilai Sampel Otomatis untuk Data Siswa yang Masih Kosong
    if ($action === 'isi_sampel') {
        try {
            $siswa_all = $pdo->query("SELECT id_siswa FROM siswa")->fetchAll(PDO::FETCH_COLUMN);
            $pdo->beginTransaction();

            $del = $pdo->prepare("DELETE FROM penilaian");
            $del->execute();

            $ins = $pdo->prepare("INSERT INTO penilaian (id_siswa, id_kriteria, nilai) VALUES (?, ?, ?)");

            // Pola realistis berdasarkan kriteria
            // C1: Penghasilan (1.000.000 s/d 5.000.000)
            // C2: Tanggungan (1 s/d 5 anak)
            // C3: Nilai Rata-rata (75 s/d 94)
            $sample_data = [
                1 => ['Penghasilan' => 1500000, 'Tanggungan' => 4, 'Nilai' => 88.5, 'Default' => 4],
                2 => ['Penghasilan' => 1200000, 'Tanggungan' => 5, 'Nilai' => 91.0, 'Default' => 5],
                3 => ['Penghasilan' => 2500000, 'Tanggungan' => 3, 'Nilai' => 84.0, 'Default' => 3],
                4 => ['Penghasilan' => 1000000, 'Tanggungan' => 4, 'Nilai' => 87.0, 'Default' => 4],
                5 => ['Penghasilan' => 3800000, 'Tanggungan' => 2, 'Nilai' => 82.5, 'Default' => 2],
                6 => ['Penghasilan' => 1800000, 'Tanggungan' => 3, 'Nilai' => 89.0, 'Default' => 4],
                7 => ['Penghasilan' => 2200000, 'Tanggungan' => 3, 'Nilai' => 85.0, 'Default' => 3],
                8 => ['Penghasilan' => 1100000, 'Tanggungan' => 4, 'Nilai' => 93.5, 'Default' => 5],
            ];

            foreach ($siswa_all as $idx => $s_id) {
                $pattern = $sample_data[($idx % 8) + 1];
                foreach ($kriteria_list as $k) {
                    $nm = strtolower($k['nama_kriteria']);
                    if (strpos($nm, 'penghasilan') !== false) {
                        $val = $pattern['Penghasilan'];
                    } elseif (strpos($nm, 'tanggungan') !== false) {
                        $val = $pattern['Tanggungan'];
                    } elseif (strpos($nm, 'nilai') !== false || strpos($nm, 'rapor') !== false) {
                        $val = $pattern['Nilai'];
                    } else {
                        $val = $pattern['Default'];
                    }
                    $ins->execute([$s_id, $k['id_kriteria'], $val]);
                }
            }

            $pdo->commit();
            set_flash('success', 'Data penilaian sampel realistis berhasil diisikan ke seluruh siswa.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Gagal membuat data sampel: ' . $e->getMessage());
        }
        header('Location: penilaian.php');
        exit;
    }
}

// Ambil semua data siswa dan nilai matriksnya
$siswa_list = $pdo->query("SELECT * FROM siswa ORDER BY id_siswa ASC")->fetchAll();

// Ambil semua nilai penilaian yang tersimpan: map[id_siswa][id_kriteria] => nilai
$nilai_map = [];
$raw_penilaian = $pdo->query("SELECT id_siswa, id_kriteria, nilai FROM penilaian")->fetchAll();
foreach ($raw_penilaian as $np) {
    $nilai_map[$np['id_siswa']][$np['id_kriteria']] = (float)$np['nilai'];
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
                <h1>Data Penilaian Alternatif</h1>
                <p>Input dan kelola matriks keputusan nilai setiap siswa berdasarkan seluruh kriteria</p>
            </div>
        </div>
        <div class="topbar-right">
            <form action="penilaian.php" method="POST" style="display: inline;" onsubmit="return confirm('Isi otomatis data penilaian sampel realistis untuk seluruh calon siswa?')">
                <input type="hidden" name="action" value="isi_sampel">
                <button type="submit" class="btn btn-secondary" style="font-size: 0.8rem;">
                    <i data-lucide="sparkles"></i> Isi Nilai Sampel Otomatis
                </button>
            </form>
            <a href="topsis.php" class="btn btn-primary">
                <i data-lucide="calculator"></i> Lanjut ke Perhitungan TOPSIS
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

        <?php if ($total_kriteria === 0): ?>
            <div class="alert alert-error">
                <i data-lucide="alert-triangle"></i>
                <div>Kriteria penilaian belum ada! Silakan tambahkan kriteria terlebih dahulu pada menu <strong>Data Kriteria</strong>.</div>
            </div>
        <?php endif; ?>

        <div class="glass-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        <i data-lucide="clipboard-check"></i> Matriks Keputusan Penilaian Siswa (X)
                    </h3>
                    <p class="card-desc">Tabel nilai dasar siswa yang akan dihitung melalui tahapan normalisasi TOPSIS</p>
                </div>
                <div style="width: 260px;">
                    <input type="text" data-table-search="tabelPenilaian" class="form-control" placeholder="Cari siswa di tabel..." style="padding: 8px 14px; font-size: 0.82rem;">
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="custom-table" id="tabelPenilaian">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>NISN</th>
                                <th>Nama Lengkap Siswa</th>
                                <th>Kelas</th>
                                <?php foreach ($kriteria_list as $k): ?>
                                    <th style="text-align: right;">
                                        <div style="font-weight: 700; color: var(--secondary);">
                                            <?= htmlspecialchars($k['kode_kriteria']) ?>
                                        </div>
                                        <div style="font-size: 0.7rem; font-weight: 500; text-transform: none; color: var(--text-muted);">
                                            <?= htmlspecialchars($k['nama_kriteria']) ?>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                                <th style="width: 100px; text-align: center;">Status</th>
                                <th style="width: 90px; text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($siswa_list) > 0): ?>
                                <?php $no = 1; foreach ($siswa_list as $s): 
                                    $s_id = $s['id_siswa'];
                                    $is_lengkap = true;
                                    $current_values = [];
                                    foreach ($kriteria_list as $k) {
                                        if (!isset($nilai_map[$s_id][$k['id_kriteria']])) {
                                            $is_lengkap = false;
                                        }
                                        $current_values[$k['id_kriteria']] = $nilai_map[$s_id][$k['id_kriteria']] ?? null;
                                    }
                                ?>
                                    <tr>
                                        <td><strong><?= $no++ ?></strong></td>
                                        <td>
                                            <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.82rem; color: var(--secondary);">
                                                <?= htmlspecialchars($s['nisn']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($s['nama_siswa']) ?></strong>
                                        </td>
                                        <td><span class="badge badge-info"><?= htmlspecialchars($s['kelas']) ?></span></td>

                                        <?php foreach ($kriteria_list as $k): 
                                            $val = $current_values[$k['id_kriteria']];
                                            $is_rupiah = stripos($k['nama_kriteria'], 'penghasilan') !== false;
                                        ?>
                                            <td style="text-align: right; font-family: 'JetBrains Mono', monospace;">
                                                <?php if ($val !== null): ?>
                                                    <span style="color: #cbd5e1; font-weight: 600;">
                                                        <?= $is_rupiah ? format_rupiah($val) : number_format($val, 2) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: var(--danger); font-size: 0.78rem;">- Belum -</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>

                                        <td style="text-align: center;">
                                            <?php if ($is_lengkap): ?>
                                                <span class="badge badge-success">Lengkap</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Belum</span>
                                            <?php endif; ?>
                                        </td>

                                        <td style="text-align: center;">
                                            <button type="button" class="btn btn-sm btn-primary" onclick='inputNilaiSiswa(<?= json_encode($s) ?>, <?= json_encode($current_values) ?>)' title="Input / Edit Nilai">
                                                <i data-lucide="edit-3"></i> Nilai
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?= 5 + $total_kriteria ?>" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        Belum ada data siswa. Silakan tambahkan siswa terlebih dahulu di menu <strong>Data Siswa</strong>.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Input Penilaian Siswa -->
<div class="modal-backdrop" id="modalInputNilai">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">
                <i data-lucide="clipboard-check" style="vertical-align: middle; color: var(--primary-light);"></i> Input Penilaian Siswa
            </h3>
            <button class="modal-close" onclick="closeModal('modalInputNilai')">&times;</button>
        </div>
        <form action="penilaian.php" method="POST">
            <input type="hidden" name="action" value="simpan_penilaian">
            <input type="hidden" name="id_siswa" id="modal_id_siswa">
            <div class="modal-body">
                <div style="padding: 14px; border-radius: 10px; background: #f8fafc; margin-bottom: 20px; border: 1px solid var(--border-glass);">
                    <div style="font-size: 0.78rem; color: var(--text-muted);">Nama Alternatif Siswa:</div>
                    <div id="modal_nama_siswa" style="font-size: 1.1rem; font-weight: 700; color: var(--text-main);"></div>
                    <div id="modal_nisn_kelas" style="font-size: 0.82rem; color: var(--secondary);"></div>
                </div>

                <?php foreach ($kriteria_list as $k): 
                    $is_cost = strtolower($k['atribut']) === 'cost';
                    $is_rupiah = stripos($k['nama_kriteria'], 'penghasilan') !== false;
                ?>
                    <div class="form-group">
                        <label class="form-label" for="kriteria_input_<?= $k['id_kriteria'] ?>">
                            <?= htmlspecialchars($k['nama_kriteria']) ?> (<?= htmlspecialchars($k['kode_kriteria']) ?>)
                            <span class="badge <?= $is_cost ? 'badge-cost' : 'badge-benefit' ?>" style="margin-left: 6px; font-size: 0.7rem;">
                                <?= htmlspecialchars(ucfirst($k['atribut'])) ?>
                            </span>
                        </label>

                        <?php if ($is_rupiah): ?>
                            <input type="text" id="kriteria_input_<?= $k['id_kriteria'] ?>" name="nilai[<?= $k['id_kriteria'] ?>]" class="form-control input-rupiah" placeholder="Contoh: Rp 1.500.000" required>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">Masukkan nominal penghasilan orang tua per bulan</span>
                        <?php else: ?>
                            <input type="number" step="any" id="kriteria_input_<?= $k['id_kriteria'] ?>" name="nilai[<?= $k['id_kriteria'] ?>]" class="form-control" placeholder="Contoh: <?= stripos($k['nama_kriteria'], 'tanggungan') !== false ? '4' : '85.50' ?>" required>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">
                                <?= stripos($k['nama_kriteria'], 'tanggungan') !== false ? 'Masukkan jumlah jiwa tanggungan (orang)' : 'Masukkan nilai akademik (skala 0 - 100)' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalInputNilai')">Batal</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Simpan Penilaian</button>
            </div>
        </form>
    </div>
</div>

<script>
function inputNilaiSiswa(siswa, values) {
    document.getElementById('modal_id_siswa').value = siswa.id_siswa;
    document.getElementById('modal_nama_siswa').textContent = siswa.nama_siswa;
    document.getElementById('modal_nisn_kelas').textContent = 'NISN: ' + siswa.nisn + ' • Kelas: ' + siswa.kelas;

    // Isi nilai inputan
    <?php foreach ($kriteria_list as $k): 
        $k_id = $k['id_kriteria'];
        $is_rupiah = stripos($k['nama_kriteria'], 'penghasilan') !== false;
    ?>
        var val = values[<?= $k_id ?>];
        var el = document.getElementById('kriteria_input_<?= $k_id ?>');
        if (val !== null && val !== undefined) {
            <?php if ($is_rupiah): ?>
                el.value = 'Rp ' + Number(val).toLocaleString('id-ID');
            <?php else: ?>
                el.value = val;
            <?php endif; ?>
        } else {
            el.value = '';
        }
    <?php endforeach; ?>

    openModal('modalInputNilai');
}
</script>

<?php require_once 'includes/footer.php'; ?>
