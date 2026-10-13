<?php
require_once 'config/database.php';
check_login();

$page_title = 'Dashboard Utama';

// Query Ringkasan Data
$total_siswa = $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$total_kriteria = $pdo->query("SELECT COUNT(*) FROM kriteria")->fetchColumn();

// Cek status siswa yang sudah memiliki nilai lengkap
$kriteria_ids = $pdo->query("SELECT id_kriteria FROM kriteria")->fetchAll(PDO::FETCH_COLUMN);
$total_kriteria_count = count($kriteria_ids);

$siswa_lengkap_count = 0;
if ($total_kriteria_count > 0) {
    $stmt = $pdo->prepare("SELECT id_siswa, COUNT(DISTINCT id_kriteria) as c FROM penilaian GROUP BY id_siswa HAVING c = :cnt");
    $stmt->execute([':cnt' => $total_kriteria_count]);
    $siswa_lengkap_count = count($stmt->fetchAll());
}

// Cek status AHP
$ahp_info = $pdo->query("SELECT cr FROM ahp WHERE cr IS NOT NULL ORDER BY id_ahp DESC LIMIT 1")->fetch();
$cr_value = $ahp_info ? (float)$ahp_info['cr'] : null;

// Ambil 5 Besar Ranking Terkini
$top_ranking = [];
try {
    $top_ranking = $pdo->query("
        SELECT h.peringkat, h.nilai_preferensi, h.status, s.nisn, s.nama_siswa, s.kelas
        FROM hasil_perankingan h
        JOIN siswa s ON h.id_siswa = s.id_siswa
        ORDER BY h.peringkat ASC
        LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {
    // Jika belum ada hasil perangkingan
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
                <h1>Dashboard SPK</h1>
                <p>Selamat datang di Sistem Pendukung Keputusan Seleksi Beasiswa AHP & TOPSIS</p>
            </div>
        </div>
        <div class="topbar-right">
            <div class="date-pill">
                <i data-lucide="calendar"></i>
                <span><?= date('l, d F Y') ?></span>
            </div>
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

        <!-- Stat Cards Grid -->
        <div class="grid-cols-4">
            <div class="stat-card">
                <div class="stat-glow glow-blue"></div>
                <div class="stat-top">
                    <span class="stat-label">Total Calon Siswa</span>
                    <div class="stat-icon" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8;">
                        <i data-lucide="users"></i>
                    </div>
                </div>
                <div class="stat-number"><?= $total_siswa ?></div>
                <div class="stat-footer">
                    <i data-lucide="user-check"></i> <?= $siswa_lengkap_count ?> siswa nilai lengkap
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-glow glow-indigo"></div>
                <div class="stat-top">
                    <span class="stat-label">Total Kriteria</span>
                    <div class="stat-icon" style="background: rgba(99, 102, 241, 0.15); color: #818cf8;">
                        <i data-lucide="sliders"></i>
                    </div>
                </div>
                <div class="stat-number"><?= $total_kriteria ?></div>
                <div class="stat-footer">
                    <i data-lucide="check"></i> Multi-kriteria (Cost & Benefit)
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-glow glow-purple"></div>
                <div class="stat-top">
                    <span class="stat-label">Konsistensi AHP (CR)</span>
                    <div class="stat-icon" style="background: rgba(168, 85, 247, 0.15); color: #c084fc;">
                        <i data-lucide="git-merge"></i>
                    </div>
                </div>
                <div class="stat-number" style="font-size: 1.65rem;">
                    <?= $cr_value !== null ? number_format($cr_value, 4) : 'Belum Ada' ?>
                </div>
                <div class="stat-footer">
                    <?php if ($cr_value === null): ?>
                        <span class="badge badge-warning">Perlu Dihitung</span>
                    <?php elseif ($cr_value <= 0.1): ?>
                        <span class="badge badge-success">✓ Konsisten (CR ≤ 0.1)</span>
                    <?php else: ?>
                        <span class="badge badge-danger">✗ Tidak Konsisten</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-glow glow-emerald"></div>
                <div class="stat-top">
                    <span class="stat-label">Status Perangkingan</span>
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
                        <i data-lucide="trophy"></i>
                    </div>
                </div>
                <div class="stat-number"><?= count($top_ranking) > 0 ? count($top_ranking) . '+' : '0' ?></div>
                <div class="stat-footer">
                    <?php if (count($top_ranking) > 0): ?>
                        <span class="badge badge-success">Selesai Dihitung</span>
                    <?php else: ?>
                        <span class="badge badge-info">Siap Diproses</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Pipeline -->
        <div class="glass-card" style="margin-bottom: 28px;">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        <i data-lucide="route"></i> Alur Kerja Sistem Pendukung Keputusan
                    </h3>
                    <p class="card-desc">Ikuti 6 langkah sistematis untuk menghasilkan keputusan beasiswa yang objektif dan akurat</p>
                </div>
            </div>
            <div class="card-body">
                <div class="step-tracker">
                    <div class="step-item <?= $total_siswa > 0 ? 'completed' : 'active' ?>">
                        <div class="step-circle"><i data-lucide="user-plus" style="width: 18px;"></i></div>
                        <span class="step-label">1. Data Siswa</span>
                    </div>
                    <div class="step-item <?= $total_kriteria > 0 ? 'completed' : 'active' ?>">
                        <div class="step-circle"><i data-lucide="sliders" style="width: 18px;"></i></div>
                        <span class="step-label">2. Kriteria</span>
                    </div>
                    <div class="step-item <?= $siswa_lengkap_count > 0 ? 'completed' : 'active' ?>">
                        <div class="step-circle"><i data-lucide="edit-3" style="width: 18px;"></i></div>
                        <span class="step-label">3. Input Nilai</span>
                    </div>
                    <div class="step-item <?= $cr_value !== null ? 'completed' : 'active' ?>">
                        <div class="step-circle"><i data-lucide="git-merge" style="width: 18px;"></i></div>
                        <span class="step-label">4. Hitung AHP</span>
                    </div>
                    <div class="step-item <?= count($top_ranking) > 0 ? 'completed' : 'active' ?>">
                        <div class="step-circle"><i data-lucide="calculator" style="width: 18px;"></i></div>
                        <span class="step-label">5. Hitung TOPSIS</span>
                    </div>
                    <div class="step-item <?= count($top_ranking) > 0 ? 'completed' : '' ?>">
                        <div class="step-circle"><i data-lucide="award" style="width: 18px;"></i></div>
                        <span class="step-label">6. Perangkingan</span>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; margin-top: 10px;">
                    <a href="siswa.php" class="btn btn-secondary">
                        <i data-lucide="user-plus"></i> Kelola Siswa
                    </a>
                    <a href="penilaian.php" class="btn btn-secondary">
                        <i data-lucide="clipboard-check"></i> Input Nilai
                    </a>
                    <a href="ahp.php" class="btn btn-secondary">
                        <i data-lucide="git-merge"></i> Hitung Bobot AHP
                    </a>
                    <a href="topsis.php" class="btn btn-primary">
                        <i data-lucide="calculator"></i> Jalankan TOPSIS
                    </a>
                    <a href="ranking.php" class="btn btn-success">
                        <i data-lucide="trophy"></i> Lihat Hasil Akhir
                    </a>
                </div>
            </div>
        </div>

        <!-- 2 Column Section: Top Candidates & About Methods -->
        <div class="grid-cols-2">
            <!-- Top Candidates -->
            <div class="glass-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i data-lucide="star"></i> Calon Penerima Beasiswa Teratas
                    </h3>
                    <a href="ranking.php" class="btn btn-sm btn-secondary">Lihat Semua</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (count($top_ranking) > 0): ?>
                        <div class="table-responsive">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Nama Siswa</th>
                                        <th>Kelas</th>
                                        <th>Nilai (V)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_ranking as $r): ?>
                                        <tr>
                                            <td>
                                                <span class="rank-badge <?= $r['peringkat'] <= 3 ? 'rank-' . $r['peringkat'] : 'rank-other' ?>">
                                                    <?= $r['peringkat'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($r['nama_siswa']) ?></strong>
                                                <div style="font-size: 0.74rem; color: var(--text-muted);"><?= htmlspecialchars($r['nisn']) ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($r['kelas']) ?></td>
                                            <td>
                                                <strong style="color: var(--secondary);">
                                                    <?= number_format((float)$r['nilai_preferensi'], 4) ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <span class="badge <?= ($r['status'] === 'Diterima') ? 'badge-success' : 'badge-danger' ?>">
                                                    <?= htmlspecialchars($r['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div style="padding: 36px; text-align: center; color: var(--text-muted);">
                            <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 12px; opacity: 0.5;"></i>
                            <p>Belum ada data hasil perhitungan TOPSIS.</p>
                            <a href="topsis.php" class="btn btn-sm btn-primary" style="margin-top: 12px;">
                                <i data-lucide="play"></i> Mulai Perhitungan
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Informasi Metode Kombinasi -->
            <div class="glass-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i data-lucide="book-open"></i> Integrasi Metode AHP & TOPSIS
                    </h3>
                </div>
                <div class="card-body">
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <div style="padding: 16px; border-radius: 12px; background: rgba(79, 70, 229, 0.08); border: 1px solid rgba(79, 70, 229, 0.2);">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                                <span class="badge badge-info" style="font-size: 0.8rem; font-weight: 700;">1. AHP (Analytical Hierarchy Process)</span>
                            </div>
                            <p style="font-size: 0.84rem; color: var(--text-sub);">
                                Digunakan untuk <strong>menentukan bobot prioritas kriteria</strong> secara hierarki dan konsisten melalui matriks perbandingan berpasangan (pairwise comparison) skala Saaty 1-9.
                            </p>
                        </div>

                        <div style="padding: 16px; border-radius: 12px; background: rgba(14, 165, 233, 0.08); border: 1px solid rgba(14, 165, 233, 0.2);">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                                <span class="badge badge-benefit" style="font-size: 0.8rem; font-weight: 700;">2. TOPSIS (Technique for Order Preference)</span>
                            </div>
                            <p style="font-size: 0.84rem; color: var(--text-sub);">
                                Digunakan untuk <strong>merangking alternatif siswa</strong> berdasarkan kedekatan geometris terhadap Solusi Ideal Positif (A+) dan jarak terjauh dari Solusi Ideal Negatif (A-), dengan menerapkan bobot dari metode AHP.
                            </p>
                        </div>

                        <div style="font-size: 0.82rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="info" style="width: 16px; height: 16px; color: var(--warning);"></i>
                            Hasil perangkingan memberikan rekomendasi objektif dan transparan bagi pihak pengambil keputusan sekolah.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>
