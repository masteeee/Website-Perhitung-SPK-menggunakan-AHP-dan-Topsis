<?php
require_once 'config/database.php';
check_login();

$page_title = 'Data Siswa (Alternatif)';

// Handle Form Action (Tambah, Edit, Hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $nisn = trim($_POST['nisn'] ?? '');
        $nama_siswa = trim($_POST['nama_siswa'] ?? '');
        $kelas = trim($_POST['kelas'] ?? '');
        $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');

        if (empty($nisn) || empty($nama_siswa) || empty($kelas)) {
            set_flash('error', 'NISN, Nama Siswa, dan Kelas wajib diisi!');
        } else {
            try {
                // Cek NISN unik
                $stmt = $pdo->prepare("SELECT id_siswa FROM siswa WHERE nisn = :n LIMIT 1");
                $stmt->execute([':n' => $nisn]);
                if ($stmt->fetch()) {
                    set_flash('error', "NISN '{$nisn}' sudah terdaftar dalam sistem!");
                } else {
                    $insert = $pdo->prepare("INSERT INTO siswa (nisn, nama_siswa, kelas, jenis_kelamin, alamat) VALUES (:n, :nm, :k, :jk, :a)");
                    $insert->execute([
                        ':n'  => $nisn,
                        ':nm' => $nama_siswa,
                        ':k'  => $kelas,
                        ':jk' => $jenis_kelamin,
                        ':a'  => $alamat
                    ]);
                    set_flash('success', "Data siswa '{$nama_siswa}' berhasil ditambahkan.");
                }
            } catch (Exception $e) {
                set_flash('error', 'Gagal menambah data: ' . $e->getMessage());
            }
        }
        header('Location: siswa.php');
        exit;
    }

    if ($action === 'edit') {
        $id_siswa = (int)($_POST['id_siswa'] ?? 0);
        $nisn = trim($_POST['nisn'] ?? '');
        $nama_siswa = trim($_POST['nama_siswa'] ?? '');
        $kelas = trim($_POST['kelas'] ?? '');
        $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');

        if ($id_siswa <= 0 || empty($nisn) || empty($nama_siswa)) {
            set_flash('error', 'Data perubahan tidak valid!');
        } else {
            try {
                // Cek NISN unik selain siswa ini
                $stmt = $pdo->prepare("SELECT id_siswa FROM siswa WHERE nisn = :n AND id_siswa != :id LIMIT 1");
                $stmt->execute([':n' => $nisn, ':id' => $id_siswa]);
                if ($stmt->fetch()) {
                    set_flash('error', "NISN '{$nisn}' sudah digunakan oleh siswa lain!");
                } else {
                    $update = $pdo->prepare("UPDATE siswa SET nisn = :n, nama_siswa = :nm, kelas = :k, jenis_kelamin = :jk, alamat = :a WHERE id_siswa = :id");
                    $update->execute([
                        ':n'  => $nisn,
                        ':nm' => $nama_siswa,
                        ':k'  => $kelas,
                        ':jk' => $jenis_kelamin,
                        ':a'  => $alamat,
                        ':id' => $id_siswa
                    ]);
                    set_flash('success', "Data siswa '{$nama_siswa}' berhasil diperbarui.");
                }
            } catch (Exception $e) {
                set_flash('error', 'Gagal memperbarui data: ' . $e->getMessage());
            }
        }
        header('Location: siswa.php');
        exit;
    }

    if ($action === 'hapus') {
        $id_siswa = (int)($_POST['id_siswa'] ?? 0);
        if ($id_siswa > 0) {
            try {
                $delete = $pdo->prepare("DELETE FROM siswa WHERE id_siswa = :id");
                $delete->execute([':id' => $id_siswa]);
                set_flash('success', 'Data siswa beserta penilaian terkait berhasil dihapus.');
            } catch (Exception $e) {
                set_flash('error', 'Gagal menghapus data: ' . $e->getMessage());
            }
        }
        header('Location: siswa.php');
        exit;
    }
    // Import Excel Action
    if ($action === 'import_excel') {
        if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
            $fileName = $_FILES['excel_file']['name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if ($fileExt !== 'xlsx') {
                set_flash('error', 'Hanya file Excel (.xlsx) yang diperbolehkan.');
                header('Location: siswa.php');
                exit;
            }

            $tmpPath = $_FILES['excel_file']['tmp_name'];
            $zip = new ZipArchive();
            if ($zip->open($tmpPath) !== TRUE) {
                set_flash('error', 'Gagal membuka file Excel. Pastikan format file .xlsx valid.');
                header('Location: siswa.php');
                exit;
            }

            // Baca shared strings
            $sharedStrings = [];
            $sharedXmlStr = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedXmlStr !== false) {
                $xmlStrings = simplexml_load_string($sharedXmlStr);
                if ($xmlStrings) {
                    foreach ($xmlStrings->si as $si) {
                        if (isset($si->t)) {
                            $sharedStrings[] = (string)$si->t;
                        } elseif (isset($si->r)) {
                            $t = '';
                            foreach ($si->r as $r) {
                                $t .= (string)$r->t;
                            }
                            $sharedStrings[] = $t;
                        } else {
                            $sharedStrings[] = '';
                        }
                    }
                }
            }

            // Ambil mapping kriteria dari database (C1, C2, C3)
            $kriteriaRows = $pdo->query("SELECT id_kriteria, kode_kriteria FROM kriteria")->fetchAll();
            $criteriaMap = [];
            foreach ($kriteriaRows as $kr) {
                $criteriaMap[$kr['kode_kriteria']] = (int)$kr['id_kriteria'];
            }

            $totalImported = 0;
            $pdo->beginTransaction();

            try {
                // Loop semua worksheet (sheet1.xml, sheet2.xml, dst)
                for ($sheetIdx = 1; $sheetIdx <= 30; $sheetIdx++) {
                    $sheetXmlStr = $zip->getFromName("xl/worksheets/sheet{$sheetIdx}.xml");
                    if ($sheetXmlStr === false) continue;

                    $sheetXml = simplexml_load_string($sheetXmlStr);
                    if (!$sheetXml || !isset($sheetXml->sheetData->row)) continue;

                    foreach ($sheetXml->sheetData->row as $row) {
                        $rowNum = (int)$row['r'];
                        if ($rowNum < 2) continue; // Lewati baris header

                        $cols = [];
                        foreach ($row->c as $c) {
                            $ref = (string)$c['r'];
                            preg_match('/^([A-Z]+)/', $ref, $m);
                            $colLetters = $m[1] ?? 'A';
                            $colIdx = 0;
                            $len = strlen($colLetters);
                            for ($k = 0; $k < $len; $k++) {
                                $colIdx = $colIdx * 26 + (ord($colLetters[$k]) - ord('A') + 1);
                            }

                            $type = (string)$c['t'];
                            $val = isset($c->v) ? (string)$c->v : '';
                            if ($type === 's') {
                                $val = $sharedStrings[(int)$val] ?? '';
                            } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                                $val = (string)$c->is->t;
                            }
                            $cols[$colIdx] = trim($val);
                        }

                        $nama = $cols[2] ?? '';
                        if ($nama === '' || strtolower($nama) === 'nama lengkap') continue;

                        $nisn = $cols[3] ?? '';
                        $kelas = $cols[7] ?? '';
                        $jk = $cols[10] ?? 'Laki-laki';
                        $alamat = $cols[11] ?? '';
                        $nilaiRaw = str_replace(',', '.', $cols[16] ?? '0');
                        $penghasilanRaw = preg_replace('/[^0-9.]/', '', str_replace(',', '', $cols[20] ?? '0'));
                        $tanggunganRaw = preg_replace('/[^0-9.]/', '', str_replace(',', '', $cols[21] ?? '0'));

                        $nilai = (float)$nilaiRaw;
                        $penghasilan = (float)$penghasilanRaw;
                        $tanggungan = (float)$tanggunganRaw;

                        // Cek apakah siswa dengan NISN ini sudah ada
                        $id_siswa = null;
                        if (!empty($nisn)) {
                            $cekStmt = $pdo->prepare("SELECT id_siswa FROM siswa WHERE nisn = :n LIMIT 1");
                            $cekStmt->execute([':n' => $nisn]);
                            $id_siswa = $cekStmt->fetchColumn();
                        }

                        if ($id_siswa) {
                            $up = $pdo->prepare("UPDATE siswa SET nama_siswa = :nm, kelas = :k, jenis_kelamin = :jk, alamat = :a WHERE id_siswa = :id");
                            $up->execute([':nm' => $nama, ':k' => $kelas, ':jk' => $jk, ':a' => $alamat, ':id' => $id_siswa]);
                        } else {
                            $ins = $pdo->prepare("INSERT INTO siswa (nisn, nama_siswa, kelas, jenis_kelamin, alamat) VALUES (:n, :nm, :k, :jk, :a)");
                            $ins->execute([':n' => $nisn, ':nm' => $nama, ':k' => $kelas, ':jk' => $jk, ':a' => $alamat]);
                            $id_siswa = $pdo->lastInsertId();
                        }

                        if ($id_siswa) {
                            $insPen = $pdo->prepare("INSERT INTO penilaian (id_siswa, id_kriteria, nilai) VALUES (:sid, :kid, :val) ON DUPLICATE KEY UPDATE nilai = :val");
                            if (isset($criteriaMap['C1'])) {
                                $insPen->execute([':sid' => $id_siswa, ':kid' => $criteriaMap['C1'], ':val' => $penghasilan]);
                            }
                            if (isset($criteriaMap['C2'])) {
                                $insPen->execute([':sid' => $id_siswa, ':kid' => $criteriaMap['C2'], ':val' => $tanggungan]);
                            }
                            if (isset($criteriaMap['C3'])) {
                                $insPen->execute([':sid' => $id_siswa, ':kid' => $criteriaMap['C3'], ':val' => $nilai]);
                            }
                            $totalImported++;
                        }
                    }
                }

                $zip->close();
                $pdo->commit();
                set_flash('success', "Berhasil mengimpor {$totalImported} data siswa dari file Excel beserta data penilaian kriteria!");
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $zip->close();
                set_flash('error', 'Gagal memproses file Excel: ' . $e->getMessage());
            }
        } else {
            set_flash('error', 'Silakan pilih file Excel (.xlsx) untuk diunggah.');
        }

        header('Location: siswa.php');
        exit;
    }
}

// Ambil data semua siswa dengan status penilaian
$total_kriteria = (int)$pdo->query("SELECT COUNT(*) FROM kriteria")->fetchColumn();
$siswa_list = $pdo->query("
    SELECT s.*, COUNT(p.id_penilaian) as jml_penilaian
    FROM siswa s
    LEFT JOIN penilaian p ON s.id_siswa = p.id_siswa
    GROUP BY s.id_siswa
    ORDER BY s.id_siswa ASC
")->fetchAll();

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
                <h1>Data Siswa (Alternatif)</h1>
                <p>Kelola data identitas calon penerima beasiswa bantuan dana pendidikan</p>
            </div>
        </div>
        <div class="topbar-right">
            <button class="btn btn-primary" onclick="openModal('modalTambahSiswa')">
                <i data-lucide="user-plus"></i> Tambah Siswa
            </button>
            <button class="btn btn-success" onclick="openModal('modalImportExcel')">
                <i data-lucide="upload"></i> Import Excel
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

        <div class="glass-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">
                        <i data-lucide="list"></i> Daftar Calon Alternatif (<?= count($siswa_list) ?> Siswa)
                    </h3>
                    <p class="card-desc">Data siswa yang akan diuji melalui proses perankingan AHP & TOPSIS</p>
                </div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <div style="position: relative; width: 260px;">
                        <input type="text" data-table-search="tabelSiswa" class="form-control" placeholder="Cari nama, NISN, kelas..." style="padding-left: 36px;">
                        <i data-lucide="search" style="position: absolute; left: 12px; top: 12px; width: 16px; height: 16px; color: var(--text-muted);"></i>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="custom-table" id="tabelSiswa">
                        <thead>
                            <tr>
                                <th style="width: 60px;">No</th>
                                <th>NISN</th>
                                <th>Nama Lengkap</th>
                                <th>Kelas</th>
                                <th>Jenis Kelamin</th>
                                <th style="width: 140px; text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($siswa_list) > 0): ?>
                                <?php $no = 1; foreach ($siswa_list as $row): ?>
                                    <tr>
                                        <td><strong><?= $no++ ?></strong></td>
                                        <td>
                                            <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: var(--secondary);">
                                                <?= htmlspecialchars($row['nisn']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['nama_siswa']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?= htmlspecialchars($row['kelas']) ?></span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($row['jenis_kelamin']) ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: inline-flex; gap: 6px;">
                                                <button type="button" class="btn btn-sm btn-secondary" onclick='editSiswa(<?= json_encode($row) ?>)' title="Edit Siswa">
                                                    <i data-lucide="edit-2"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="hapusSiswa(<?= $row['id_siswa'] ?>, '<?= htmlspecialchars(addslashes($row['nama_siswa'])) ?>')" title="Hapus Siswa">
                                                    <i data-lucide="trash-2"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        Belum ada data siswa. Silakan klik tombol <strong>Tambah Siswa</strong> untuk memasukkan data baru.
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

<!-- Modal Tambah Siswa -->
<div class="modal-backdrop" id="modalTambahSiswa">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">
                <i data-lucide="user-plus" style="vertical-align: middle; color: var(--primary-light);"></i> Tambah Calon Siswa Baru
            </h3>
            <button class="modal-close" onclick="closeModal('modalTambahSiswa')">&times;</button>
        </div>
        <form action="siswa.php" method="POST">
            <input type="hidden" name="action" value="tambah">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="nisn_tambah">NISN (Nomor Induk Siswa Nasional) *</label>
                    <input type="text" id="nisn_tambah" name="nisn" class="form-control" placeholder="Contoh: 0071234509" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="nama_tambah">Nama Lengkap Siswa *</label>
                    <input type="text" id="nama_tambah" name="nama_siswa" class="form-control" placeholder="Nama lengkap siswa" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="kelas_tambah">Kelas *</label>
                    <input type="text" id="kelas_tambah" name="kelas" class="form-control" placeholder="Contoh: X-A, XI-MIPA 1, XII-IPS" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Jenis Kelamin *</label>
                    <div style="display: flex; gap: 20px; margin-top: 6px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="jenis_kelamin" value="Laki-laki" checked> Laki-laki
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="jenis_kelamin" value="Perempuan"> Perempuan
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="alamat_tambah">Alamat Lengkap</label>
                    <textarea id="alamat_tambah" name="alamat" class="form-control" placeholder="Alamat domisili siswa"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambahSiswa')">Batal</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Import Excel -->
<div class="modal-backdrop" id="modalImportExcel">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">
                <i data-lucide="file-spreadsheet" style="vertical-align: middle; color: #10b981;"></i> Import Data Siswa dari Excel
            </h3>
            <button class="modal-close" onclick="closeModal('modalImportExcel')">&times;</button>
        </div>
        <form action="siswa.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import_excel">
            <div class="modal-body">
                <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 8px; padding: 14px; margin-bottom: 18px; font-size: 0.88rem; line-height: 1.5;">
                    <div style="font-weight: 600; color: #059669; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <i data-lucide="info" style="width: 16px; height: 16px;"></i> Petunjuk Format File Excel (.xlsx):
                    </div>
                    <ul style="margin: 0; padding-left: 20px; color: var(--text-secondary, #475569);">
                        <li>Sistem membaca seluruh sheet (misal: Sheet 1, Sheet 2, Sheet 3).</li>
                        <li><strong>Kolom B:</strong> Nama Lengkap Siswa</li>
                        <li><strong>Kolom C:</strong> NISN</li>
                        <li><strong>Kolom G:</strong> Kelas / Rombel</li>
                        <li><strong>Kolom J:</strong> Jenis Kelamin</li>
                        <li><strong>Kolom K:</strong> Alamat</li>
                        <li><strong>Kolom P:</strong> Nilai Rata-rata (Kriteria C3)</li>
                        <li><strong>Kolom T:</strong> Penghasilan Orang Tua (Kriteria C1)</li>
                        <li><strong>Kolom U:</strong> Jumlah Tanggungan (Kriteria C2)</li>
                    </ul>
                </div>

                <div class="form-group">
                    <label class="form-label" for="excel_file">Pilih File Excel (.xlsx) *</label>
                    <input type="file" id="excel_file" name="excel_file" class="form-control" accept=".xlsx" required style="padding: 8px 12px;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalImportExcel')">Batal</button>
                <button type="submit" class="btn btn-success" style="background: #10b981; border-color: #10b981; color: white;">
                    <i data-lucide="upload"></i> Upload & Import
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Siswa -->
<div class="modal-backdrop" id="modalEditSiswa">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">
                <i data-lucide="edit-3" style="vertical-align: middle; color: var(--secondary);"></i> Edit Data Siswa
            </h3>
            <button class="modal-close" onclick="closeModal('modalEditSiswa')">&times;</button>
        </div>
        <form action="siswa.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_siswa" id="edit_id_siswa">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_nisn">NISN *</label>
                    <input type="text" id="edit_nisn" name="nisn" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_nama_siswa">Nama Lengkap Siswa *</label>
                    <input type="text" id="edit_nama_siswa" name="nama_siswa" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_kelas">Kelas *</label>
                    <input type="text" id="edit_kelas" name="kelas" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Jenis Kelamin *</label>
                    <div style="display: flex; gap: 20px; margin-top: 6px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="jenis_kelamin" id="edit_jk_l" value="Laki-laki"> Laki-laki
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="jenis_kelamin" id="edit_jk_p" value="Perempuan"> Perempuan
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_alamat">Alamat Lengkap</label>
                    <textarea id="edit_alamat" name="alamat" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditSiswa')">Batal</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="check"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Hapus Siswa Form -->
<form id="formHapusSiswa" action="siswa.php" method="POST" style="display: none;">
    <input type="hidden" name="action" value="hapus">
    <input type="hidden" name="id_siswa" id="hapus_id_siswa">
</form>

<script>
function editSiswa(data) {
    document.getElementById('edit_id_siswa').value = data.id_siswa;
    document.getElementById('edit_nisn').value = data.nisn;
    document.getElementById('edit_nama_siswa').value = data.nama_siswa;
    document.getElementById('edit_kelas').value = data.kelas;
    document.getElementById('edit_alamat').value = data.alamat || '';
    
    if (data.jenis_kelamin === 'Laki-laki') {
        document.getElementById('edit_jk_l').checked = true;
    } else {
        document.getElementById('edit_jk_p').checked = true;
    }
    
    openModal('modalEditSiswa');
}

function hapusSiswa(id, nama) {
    if (confirm(`Apakah Anda yakin ingin menghapus siswa "${nama}"? Semua data penilaian dan kalkulasi terkait siswa ini akan ikut terhapus.`)) {
        document.getElementById('hapus_id_siswa').value = id;
        document.getElementById('formHapusSiswa').submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
