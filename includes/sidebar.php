<?php
// Hitung data ringkas untuk badge navigasi jika PDO tersedia
$badge_siswa = 0;
$badge_kriteria = 0;
if (isset($pdo)) {
    try {
        $badge_siswa = $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
        $badge_kriteria = $pdo->query("SELECT COUNT(*) FROM kriteria")->fetchColumn();
    } catch (Exception $e) {
        // Abaikan jika error
    }
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i data-lucide="award"></i>
        </div>
        <div class="brand-text">
            <h2>SPK BEASISWA</h2>
            <span>METODE AHP & TOPSIS</span>
        </div>
    </div>

    <div class="sidebar-menu">
        <div class="menu-category">Utama</div>
        <a href="dashboard.php" class="nav-link <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i>
            <span>Dashboard</span>
        </a>

        <div class="menu-category">Master Data</div>
        <a href="siswa.php" class="nav-link <?= ($current_page == 'siswa.php') ? 'active' : '' ?>">
            <i data-lucide="users"></i>
            <span>Data Siswa</span>
            <span class="nav-badge"><?= $badge_siswa ?></span>
        </a>
        <a href="kriteria.php" class="nav-link <?= ($current_page == 'kriteria.php') ? 'active' : '' ?>">
            <i data-lucide="sliders"></i>
            <span>Data Kriteria</span>
            <span class="nav-badge"><?= $badge_kriteria ?></span>
        </a>
        <a href="penilaian.php" class="nav-link <?= ($current_page == 'penilaian.php') ? 'active' : '' ?>">
            <i data-lucide="clipboard-check"></i>
            <span>Data Penilaian</span>
        </a>

        <div class="menu-category">Kalkulasi Metode</div>
        <a href="ahp.php" class="nav-link <?= ($current_page == 'ahp.php') ? 'active' : '' ?>">
            <i data-lucide="git-merge"></i>
            <span>Perhitungan AHP</span>
        </a>
        <a href="topsis.php" class="nav-link <?= ($current_page == 'topsis.php') ? 'active' : '' ?>">
            <i data-lucide="calculator"></i>
            <span>Perhitungan TOPSIS</span>
        </a>

        <div class="menu-category">Keputusan Akhir</div>
        <a href="ranking.php" class="nav-link <?= ($current_page == 'ranking.php') ? 'active' : '' ?>">
            <i data-lucide="trophy"></i>
            <span>Hasil Perangkingan</span>
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($_SESSION['admin_nama'] ?? 'Admin Beasiswa') ?></div>
                <div class="user-role">Administrator SPK</div>
            </div>
            <a href="logout.php" class="btn-logout-mini" title="Logout" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?')">
                <i data-lucide="log-out"></i>
            </a>
        </div>
    </div>
</aside>
