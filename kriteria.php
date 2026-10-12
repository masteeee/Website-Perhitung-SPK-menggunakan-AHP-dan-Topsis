<?php
require_once 'config/database.php';
check_login();

$page_title = 'Data Kriteria Penilaian';

// Handle Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $kode_kriteria = strtoupper(trim($_POST['kode_kriteria'] ?? ''));
        $nama_kriteria = trim($_POST['nama_kriteria'] ?? '');
        $atribut = ucfirst(strtolower(trim($_POST['atribut'] ?? 'Benefit')));

        if (empty($kode_kriteria) || empty($nama_kriteria)) {
            set_flash('error', 'Kode kriteria dan nama kriteria wajib diisi!');
        } else {
            try {
                // Cek duplikasi kode
                $stmt = $pdo->prepare("SELECT id_kriteria FROM kriteria WHERE kode_kriteria = :k LIMIT 1");
                $stmt->execute([':k' => $kode_kriteria]);
                if ($stmt->fetch()) {
                    set_flash('error', "Kode kriteria '{$kode_kriteria}' sudah ada!");
                } else {
                    $insert = $pdo->prepare("INSERT INTO kriteria (kode_kriteria, nama_kriteria, atribut, bobot) VALUES (:k, :nm, :a, 0)");
                    $insert->execute([
                        ':k'  => $kode_kriteria,
                        ':nm' => $nama_kriteria,
                        ':a'  => $atribut
                    ]);
                    set_flash('success', "Kriteria '{$nama_kriteria}' ({$kode_kriteria}) berhasil ditambahkan.");
                }
            } catch (Exception $e) {
                set_flash('error', 'Gagal menambah kriteria: ' . $e->getMessage());
            }
        }
        header('Location: kriteria.php');
        exit;
    }

    if ($action === 'edit') {
        $id_kriteria = (int)($_POST['id_kriteria'] ?? 0);
        $kode_kriteria = strtoupper(trim($_POST['kode_kriteria'] ?? ''));
        $nama_kriteria = trim($_POST['nama_kriteria'] ?? '');
        $atribut = ucfirst(strtolower(trim($_POST['atribut'] ?? 'Benefit')));

        if ($id_kriteria <= 0 || empty($kode_kriteria) || empty($nama_kriteria)) {
            set_flash('error', 'Data input tidak valid!');
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id_kriteria FROM kriteria WHERE kode_kriteria = :k AND id_kriteria != :id LIMIT 1");
                $stmt->execute([':k' => $kode_kriteria, ':id' => $id_kriteria]);
                if ($stmt->fetch()) {
                    set_flash('error', "Kode kriteria '{$kode_kriteria}' telah digunakan oleh kriteria lain!");
                } else {
                    $update = $pdo->prepare("UPDATE kriteria SET kode_kriteria = :k, nama_kriteria = :nm, atribut = :a WHERE id_kriteria = :id");
                    $update->execute([
                        ':k'  => $kode_kriteria,
                        ':nm' => $nama_kriteria,
                        ':a'  => $atribut,
                        ':id' => $id_kriteria
                    ]);
                    set_flash('success', "Kriteria '{$nama_kriteria}' berhasil diperbarui.");
                }
            } catch (Exception $e) {
                set_flash('error', 'Gagal memperbarui kriteria: ' . $e->getMessage());
            }
        }
        header('Location: kriteria.php');
        exit;
    }

    if ($action === 'hapus') {
        $id_kriteria = (int)($_POST['id_kriteria'] ?? 0);
        if ($id_kriteria > 0) {
            try {
                $delete = $pdo->prepare("DELETE FROM kriteria WHERE id_kriteria = :id");
                $delete->execute([':id' => $id_kriteria]);
                set_flash('success', 'Kriteria berhasil dihapus.');
            } catch (Exception $e) {
                set_flash('error', 'Gagal menghapus kriteria: ' . $e->getMessage());
            }
        }
        header('Location: kriteria.php');
        exit;
    }

    // Reset ke 3 Kriteria Standar Skripsi
    if ($action === 'reset_standar') {
        try {
            $pdo->beginTransaction();
            // Kosongkan penilaian, ahp, topsis, dan kriteria
            $pdo->exec("DELETE FROM penilaian");
            $pdo->exec("DELETE FROM ahp");
            $pdo->exec("DELETE FROM ahp_perbandingan");
            $pdo->exec("DELETE FROM topsis");
            $pdo->exec("DELETE FROM hasil_perankingan");
            $pdo->exec("DELETE FROM kriteria");

            // Masukkan 3 kriteria standar skripsi
            $standar = [
                ['C1', 'Penghasilan Orang Tua', 'Cost', 0.45],
                ['C2', 'Jumlah Tanggungan Orang Tua', 'Benefit', 0.30],
                ['C3', 'Nilai Rata-rata Siswa', 'Benefit', 0.25],
            ];
            $stmt = $pdo->prepare("INSERT INTO kriteria (kode_kriteria, nama_kriteria, atribut, bobot) VALUES (?, ?, ?, ?)");
            foreach ($standar as $row) {
                $stmt->execute($row);
            }

            // Inisialisasi perbandingan berpasangan default seimbang
            $kriteria_all = $pdo->query("SELECT id_kriteria FROM kriteria ORDER BY id_kriteria ASC")->fetchAll(PDO::FETCH_COLUMN);
            $ins_perb = $pdo->prepare("INSERT INTO ahp_perbandingan (id_kriteria_1, id_kriteria_2, nilai) VALUES (?, ?, ?)");
            foreach ($kriteria_all as $k1) {
                foreach ($kriteria_all as $k2) {
                    $ins_perb->execute([$k1, $k2, ($k1 == $k2 ? 1.0 : 1.0)]);
                }
            }

            $pdo->commit();
            set_flash('success', 'Berhasil mereset ke 3 Kriteria Standar Skripsi (C1: Penghasilan [Cost], C2: Tanggungan [Benefit], C3: Nilai Rata-rata [Benefit]).');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Gagal mereset kriteria: ' . $e->getMessage());
        }
        header('Location: kriteria.php');
        exit;
    }
}

// Ambil daftar kriteria
$kriteria_list = $pdo->query("
    SELECT k.*
    FROM kriteria k
    ORDER BY k.kode_kriteria ASC
")->fetchAll();

// =====================================================================
// HITUNG BOBOT LIVE DARI MATRIKS (sama persis dengan logika di ahp.php)
// agar nilai bobot di halaman ini KONSISTEN dengan halaman AHP
// =====================================================================
$live_weights = [];
$n_krit = count($kriteria_list);

if ($n_krit > 0) {
    // Ambil perbandingan berpasangan dari DB
    $perb_saved = [];
    try {
        $rows_perb = $pdo->query("SELECT id_kriteria_1, id_kriteria_2, nilai FROM ahp_perbandingan")->fetchAll();
        foreach ($rows_perb as $r) {
            $perb_saved[$r['id_kriteria_1']][$r['id_kriteria_2']] = (float)$r['nilai'];
        }
    } catch (Exception $e) { /* tabel belum ada */ }

    // Bangun matriks A
    $mat_A = [];
    foreach ($kriteria_list as $ki) {
        $ii = $ki['id_kriteria'];
        foreach ($kriteria_list as $kj) {
            $jj = $kj['id_kriteria'];
            if ($ii == $jj) {
                $mat_A[$ii][$jj] = 1.0;
            } elseif (isset($perb_saved[$ii][$jj])) {
                $mat_A[$ii][$jj] = (float)$perb_saved[$ii][$jj];
            } elseif (isset($perb_saved[$jj][$ii]) && $perb_saved[$jj][$ii] > 0) {
                $mat_A[$ii][$jj] = 1.0 / (float)$perb_saved[$jj][$ii];
            } else {
                $mat_A[$ii][$jj] = 1.0;
            }
        }
    }

    // Jumlah kolom
    $col_sums_k = [];
    foreach ($kriteria_list as $kj) {
        $jj = $kj['id_kriteria'];
        $s = 0;
        foreach ($kriteria_list as $ki) {
            $s += $mat_A[$ki['id_kriteria']][$jj];
        }
        $col_sums_k[$jj] = $s;
    }

    // Normalisasi & bobot (eigen vector) — identik dengan ahp.php
    foreach ($kriteria_list as $ki) {
        $ii = $ki['id_kriteria'];
        $row_sum = 0;
        foreach ($kriteria_list as $kj) {
            $jj = $kj['id_kriteria'];
            $row_sum += $mat_A[$ii][$jj] / ($col_sums_k[$jj] ?: 1);
        }
        $live_weights[$ii] = $row_sum / ($n_krit ?: 1);
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
                <h1>Data Kriteria Penilaian</h1>
                <p>Kelola kriteria, atribut (Cost/Benefit), dan bobot prioritas beasiswa</p>
            </div>
        </div>
        <div class="topbar-right">
            <button class="btn btn-secondary" onclick="resetStandarSkripsi()" style="font-size: 0.8rem;">
                <i data-lucide="rotate-ccw"></i> Reset 3 Kriteria Skripsi
            </button>
            <button class="btn btn-primary" onclick="openModal('modalTambahKriteria')">
                <i data-lucide="plus-circle"></i> Tambah Kriteria
            </button>
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

        <!-- Info Card Panduan Kriteria Skripsi -->
        <div class="grid-cols-2" style="margin-bottom: 24px;">
            <div class="glass-card" style="padding: 20px;">
                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(239, 68, 68, 0.15); display: flex; align-items: center; justify-content: center; color: #f87171; flex-shrink: 0;">
                        <i data-lucide="trending-down"></i>
                    </div>
                    <div>
                        <h4 style="font-size: 0.96rem; font-weight: 700; color: #f87171; margin-bottom: 4px;">Kriteria Cost (Biaya / Pengeluaran)</h4>
                        <p style="font-size: 0.82rem; color: var(--text-sub);">
                            Semakin <strong>kecil</strong> nilai yang dimiliki alternatif, semakin tinggi peluang untuk diprioritaskan. Contoh: <em>Penghasilan Orang Tua</em> (keluarga berpenghasilan rendah lebih diprioritaskan menerima beasiswa).
                        </p>
                    </div>
                </div>
            </div>

            <div class="glass-card" style="padding: 20px;">
                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); display: flex; align-items: center; justify-content: center; color: #34d399; flex-shrink: 0;">
                        <i data-lucide="trending-up"></i>
                    </div>
                    <div>
                        <h4 style="font-size: 0.96rem; font-weight: 700; color: #34d399; margin-bottom: 4px;">Kriteria Benefit (Keuntungan / Manfaat)</h4>
                        <p style="font-size: 0.82rem; color: var(--text-sub);">
                            Semakin <strong>besar</strong> nilai yang dimiliki alternatif, semakin tinggi nilai prioritasnya. Contoh: <em>Jumlah Tanggungan Orang Tua</em> dan <em>Nilai Rata-rata Prestasi Siswa</em>.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        <i data-lucide="sliders"></i> Daftar Kriteria Penilaian Beasiswa (<?= count($kriteria_list) ?> Kriteria)
                    </h3>
                    <p class="card-desc">Kriteria yang digunakan dalam perhitungan matriks perbandingan AHP dan normalisasi terbobot TOPSIS</p>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="custom-table" id="tabelKriteria">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Kode</th>
                                <th>Nama Kriteria</th>
                                <th style="width: 140px;">Jenis Atribut</th>
                                <th style="width: 160px;">Bobot Saat Ini (W)</th>
                                <th>Keterangan Operasional</th>
                                <th style="width: 130px; text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($kriteria_list) > 0): ?>
                                <?php foreach ($kriteria_list as $row): 
                                    // Gunakan bobot live (dihitung dari matriks) agar konsisten dengan halaman AHP
                                    $bobot = isset($live_weights[$row['id_kriteria']])
                                        ? $live_weights[$row['id_kriteria']]
                                        : (float)$row['bobot'];
                                    $is_cost = strtolower($row['atribut']) === 'cost';
                                ?>
                                    <tr>
                                        <td>
                                            <span style="font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 0.95rem; color: var(--secondary);">
                                                <?= htmlspecialchars($row['kode_kriteria']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['nama_kriteria']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge <?= $is_cost ? 'badge-cost' : 'badge-benefit' ?>">
                                                <i data-lucide="<?= $is_cost ? 'arrow-down-right' : 'arrow-up-right' ?>"></i>
                                                <?= htmlspecialchars(ucfirst($row['atribut'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <strong style="font-family: 'JetBrains Mono', monospace; color: #a5b4fc;">
                                                    <?= number_format($bobot, 4) ?>
                                                </strong>
                                                <span style="font-size: 0.76rem; color: var(--text-muted);">
                                                    (<?= number_format($bobot * 100, 1) ?>%)
                                                </span>
                                            </div>
                                        </td>
                                        <td style="font-size: 0.82rem; color: var(--text-sub);">
                                            <?php
                                            if (stripos($row['nama_kriteria'], 'penghasilan') !== false) {
                                                echo 'Format Nominal Rupiah (Cost: semakin kecil semakin diprioritaskan)';
                                            } elseif (stripos($row['nama_kriteria'], 'tanggungan') !== false) {
                                                echo 'Jumlah Jiwa Tanggungan (Benefit: semakin banyak tanggungan semakin diprioritaskan)';
                                            } elseif (stripos($row['nama_kriteria'], 'nilai') !== false || stripos($row['nama_kriteria'], 'rapor') !== false) {
                                                echo 'Skala Akademik 0 - 100 (Benefit: semakin tinggi nilai semakin diprioritaskan)';
                                            } else {
                                                echo 'Skala Penilaian Objektif';
                                            }
                                            ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: inline-flex; gap: 6px;">
                                                <button type="button" class="btn btn-sm btn-secondary" onclick='editKriteria(<?= json_encode($row) ?>)' title="Edit Kriteria">
                                                    <i data-lucide="edit-2"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="hapusKriteria(<?= $row['id_kriteria'] ?>, '<?= htmlspecialchars(addslashes($row['nama_kriteria'])) ?>')" title="Hapus Kriteria">
                                                    <i data-lucide="trash-2"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        Belum ada data kriteria. Silakan klik <strong>Reset 3 Kriteria Skripsi</strong> atau tombol <strong>Tambah Kriteria</strong>.
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

<!-- Modal Tambah Kriteria -->
<div class="modal-backdrop" id="modalTambahKriteria">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">
                <i data-lucide="plus-circle" style="vertical-align: middle; color: var(--primary-light);"></i> Tambah Kriteria Penilaian
            </h3>
            <button class="modal-close" onclick="closeModal('modalTambahKriteria')">&times;</button>
        </div>
        <form action="kriteria.php" method="POST">
            <input type="hidden" name="action" value="tambah">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="kode_kriteria_tambah">Kode Kriteria *</label>
                    <input type="text" id="kode_kriteria_tambah" name="kode_kriteria" class="form-control" placeholder="Contoh: C1, C2, C3" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="nama_kriteria_tambah">Nama Kriteria *</label>
                    <input type="text" id="nama_kriteria_tambah" name="nama_kriteria" class="form-control" placeholder="Contoh: Penghasilan Orang Tua" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="atribut_tambah">Jenis Atribut *</label>
                    <select id="atribut_tambah" name="atribut" class="form-control" required>
                        <option value="Benefit">Benefit (Semakin besar nilai, semakin diutamakan)</option>
                        <option value="Cost">Cost (Semakin kecil nilai, semakin diutamakan)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambahKriteria')">Batal</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Simpan Kriteria</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Kriteria -->
<div class="modal-backdrop" id="modalEditKriteria">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">
                <i data-lucide="edit-3" style="vertical-align: middle; color: var(--secondary);"></i> Edit Kriteria Penilaian
            </h3>
            <button class="modal-close" onclick="closeModal('modalEditKriteria')">&times;</button>
        </div>
        <form action="kriteria.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_kriteria" id="edit_id_kriteria">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_kode_kriteria">Kode Kriteria *</label>
                    <input type="text" id="edit_kode_kriteria" name="kode_kriteria" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_nama_kriteria">Nama Kriteria *</label>
                    <input type="text" id="edit_nama_kriteria" name="nama_kriteria" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_atribut">Jenis Atribut *</label>
                    <select id="edit_atribut" name="atribut" class="form-control" required>
                        <option value="Benefit">Benefit</option>
                        <option value="Cost">Cost</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditKriteria')">Batal</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="check"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Form Hapus & Reset -->
<form id="formHapusKriteria" action="kriteria.php" method="POST" style="display: none;">
    <input type="hidden" name="action" value="hapus">
    <input type="hidden" name="id_kriteria" id="hapus_id_kriteria">
</form>

<form id="formResetStandar" action="kriteria.php" method="POST" style="display: none;">
    <input type="hidden" name="action" value="reset_standar">
</form>

<script>
function editKriteria(data) {
    document.getElementById('edit_id_kriteria').value = data.id_kriteria;
    document.getElementById('edit_kode_kriteria').value = data.kode_kriteria;
    document.getElementById('edit_nama_kriteria').value = data.nama_kriteria;
    document.getElementById('edit_atribut').value = data.atribut.charAt(0).toUpperCase() + data.atribut.slice(1).toLowerCase();
    openModal('modalEditKriteria');
}

function hapusKriteria(id, nama) {
    if (confirm(`Apakah Anda yakin ingin menghapus kriteria "${nama}"? Semua data penilaian dan kalkulasi terkait kriteria ini akan direset.`)) {
        document.getElementById('hapus_id_kriteria').value = id;
        document.getElementById('formHapusKriteria').submit();
    }
}

function resetStandarSkripsi() {
    if (confirm('Apakah Anda ingin mereset ke 3 Kriteria Standar Skripsi (C1: Penghasilan Orang Tua [Cost], C2: Tanggungan [Benefit], C3: Nilai Rata-rata [Benefit])? Perhatian: Penilaian sebelumnya akan disesuaikan.')) {
        document.getElementById('formResetStandar').submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
