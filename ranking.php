<?php
require_once 'config/database.php';
check_login();

$page_title = 'Hasil Perangkingan Akhir';

// Handle update kuota penerima beasiswa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_kuota') {
        $kuota = max(1, (int)($_POST['kuota'] ?? 3));
        try {
            $pdo->beginTransaction();
            // Ambil semua data ranking terurut
            $ranks = $pdo->query("SELECT id_hasil, peringkat FROM hasil_perankingan ORDER BY peringkat ASC")->fetchAll();
            $upd = $pdo->prepare("UPDATE hasil_perankingan SET status = :st WHERE id_hasil = :id");

            $upd_alt = null;
            try { $upd_alt = $pdo->prepare("UPDATE hasil_perangkingan SET status = :st WHERE id_hasil = :id"); } catch (Exception $e) {}

            foreach ($ranks as $r) {
                $status = ($r['peringkat'] <= $kuota) ? 'Diterima' : 'Tidak Diterima';
                $upd->execute([':st' => $status, ':id' => $r['id_hasil']]);
                if ($upd_alt) {
                    try { $upd_alt->execute([':st' => $status, ':id' => $r['id_hasil']]); } catch (Exception $e) {}
                }
            }

            $pdo->commit();
            set_flash('success', "Kuota penerima beasiswa berhasil diperbarui menjadi {$kuota} siswa teratas!");
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Gagal memperbarui kuota: ' . $e->getMessage());
        }
        header('Location: ranking.php');
        exit;
    }
}

// Ambil data hasil perankingan
$ranking_data = [];
try {
    $ranking_data = $pdo->query("
        SELECT h.*, s.nisn, s.nama_siswa, s.kelas, s.jenis_kelamin, s.alamat
        FROM hasil_perankingan h
        JOIN siswa s ON h.id_siswa = s.id_siswa
        ORDER BY h.peringkat ASC
    ")->fetchAll();
} catch (Exception $e) {
    //
}

// Hitung statistik penerima beasiswa
$total_kandidat = count($ranking_data);
$total_diterima = 0;
$total_tidak = 0;
foreach ($ranking_data as $r) {
    if ($r['status'] === 'Diterima') {
        $total_diterima++;
    } else {
        $total_tidak++;
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
                <h1>Hasil Perangkingan Beasiswa</h1>
                <p>Urutan prioritas penerimaan beasiswa berdasarkan nilai preferensi metode AHP & TOPSIS</p>
            </div>
        </div>
        <div class="topbar-right">
            <a href="cetak.php" target="_blank" class="btn btn-secondary">
                <i data-lucide="printer"></i> Cetak Laporan Resmi
            </a>
            <a href="topsis.php" class="btn btn-primary">
                <i data-lucide="rotate-cw"></i> Hitung Ulang TOPSIS
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

        <!-- Stat Bar Rekap Kelayakan -->
        <div class="grid-cols-3">
            <div class="stat-card">
                <div class="stat-glow glow-blue"></div>
                <div class="stat-top">
                    <span class="stat-label">Total Peserta Seleksi</span>
                    <div class="stat-icon" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8;">
                        <i data-lucide="users"></i>
                    </div>
                </div>
                <div class="stat-number"><?= $total_kandidat ?></div>
                <div class="stat-footer">Calon Alternatif Terkalkulasi</div>
            </div>

            <div class="stat-card">
                <div class="stat-glow glow-emerald"></div>
                <div class="stat-top">
                    <span class="stat-label">Diterima / Layak Beasiswa</span>
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
                        <i data-lucide="check-circle"></i>
                    </div>
                </div>
                <div class="stat-number" style="color: #34d399;"><?= $total_diterima ?></div>
                <div class="stat-footer">Memenuhi kuota prioritas</div>
            </div>

            <div class="stat-card">
                <div class="stat-glow glow-amber"></div>
                <div class="stat-top">
                    <span class="stat-label">Tidak Diterima / Cadangan</span>
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
                        <i data-lucide="clock"></i>
                    </div>
                </div>
                <div class="stat-number" style="color: #fbbf24;"><?= $total_tidak ?></div>
                <div class="stat-footer">Peringkat di luar batas kuota</div>
            </div>
        </div>

        <!-- Atur Kuota Beasiswa Card -->
        <div class="glass-card" style="margin-bottom: 24px; padding: 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h4 style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">
                        <i data-lucide="settings" style="color: var(--secondary); vertical-align: middle;"></i> Pengaturan Kuota Penerima Beasiswa
                    </h4>
                    <p style="font-size: 0.82rem; color: var(--text-muted);">
                        Sesuaikan batas jumlah siswa yang berhak menerima bantuan dana pendidikan (Status Diterima)
                    </p>
                </div>
                <form action="ranking.php" method="POST" style="display: flex; align-items: center; gap: 12px;">
                    <input type="hidden" name="action" value="update_kuota">
                    <label style="font-size: 0.85rem; color: var(--text-sub);">Jumlah Kuota:</label>
                    <input type="number" name="kuota" value="<?= $total_diterima ?: 3 ?>" min="1" max="<?= max(1, $total_kandidat) ?>" class="form-control" style="width: 90px; text-align: center; font-weight: 700;">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 18px;">
                        <i data-lucide="check"></i> Terapkan
                    </button>
                </form>
            </div>
        </div>

        <!-- Tabel Hasil Perangkingan -->
        <div class="glass-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        <i data-lucide="trophy"></i> Tabel Hasil Akhir Perangkingan Beasiswa
                    </h3>
                    <p class="card-desc">Diurutkan berdasarkan nilai preferensi TOPSIS tertinggi (V_i mendekati 1)</p>
                </div>
                <div style="width: 260px;">
                    <input type="text" data-table-search="tabelRanking" class="form-control" placeholder="Cari nama atau NISN..." style="padding: 8px 14px; font-size: 0.82rem;">
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="custom-table" id="tabelRanking">
                        <thead>
                            <tr>
                                <th style="width: 80px; text-align: center;">Peringkat</th>
                                <th>NISN</th>
                                <th>Nama Lengkap Siswa</th>
                                <th>Kelas</th>
                                <th>Jenis Kelamin</th>
                                <th style="width: 220px;">Nilai Preferensi (V_i)</th>
                                <th style="width: 140px; text-align: center;">Status Kelayakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($ranking_data) > 0): ?>
                                <?php foreach ($ranking_data as $row): 
                                    $score = (float)$row['nilai_preferensi'];
                                    $percent = min(100, max(0, $score * 100));
                                    $is_diterima = ($row['status'] === 'Diterima');
                                ?>
                                    <tr>
                                        <td style="text-align: center;">
                                            <span class="rank-badge <?= $row['peringkat'] <= 3 ? 'rank-' . $row['peringkat'] : 'rank-other' ?>">
                                                <?= $row['peringkat'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: var(--secondary);">
                                                <?= htmlspecialchars($row['nisn']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="color: var(--text-main); font-size: 0.95rem;">
                                                <?= htmlspecialchars($row['nama_siswa']) ?>
                                            </strong>
                                            <?php if ($row['peringkat'] === 1): ?>
                                                <span class="badge badge-warning" style="margin-left: 8px; font-size: 0.68rem;">
                                                    <i data-lucide="crown"></i> Rekomendasi Utama
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge badge-info"><?= htmlspecialchars($row['kelas']) ?></span></td>
                                        <td><?= htmlspecialchars($row['jenis_kelamin']) ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                                                <strong style="font-family: 'JetBrains Mono', monospace; color: var(--secondary); font-size: 0.95rem;">
                                                    <?= number_format($score, 4) ?>
                                                </strong>
                                                <span style="font-size: 0.75rem; color: var(--text-muted);"><?= number_format($percent, 1) ?>%</span>
                                            </div>
                                            <div style="width: 100%; height: 6px; background: rgba(255, 255, 255, 0.08); border-radius: 10px; overflow: hidden;">
                                                <div style="width: <?= $percent ?>%; height: 100%; background: linear-gradient(90deg, var(--secondary), var(--primary-light)); border-radius: 10px;"></div>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="badge <?= $is_diterima ? 'badge-success' : 'badge-danger' ?>" style="padding: 6px 14px; font-size: 0.8rem;">
                                                <i data-lucide="<?= $is_diterima ? 'check-circle' : 'x-circle' ?>"></i>
                                                <?= htmlspecialchars($row['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        Belum ada hasil perankingan. Silakan jalankan perhitungan pada menu <strong>Perhitungan TOPSIS</strong>.
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

<?php require_once 'includes/footer.php'; ?>
