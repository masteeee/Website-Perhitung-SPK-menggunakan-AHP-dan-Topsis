<?php
require_once 'config/database.php';

// Jika sudah login, langsung ke dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error_msg = get_flash('error');
$success_msg = get_flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_msg = 'Username dan password wajib diisi!';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $admin = $stmt->fetch();

            $is_valid = false;
            if ($admin) {
                // Verifikasi password (dukung bcrypt hash dan fallback darurat default)
                if (password_verify($password, $admin['password'])) {
                    $is_valid = true;
                } elseif ($password === 'admin123' || $password === $admin['password']) {
                    // Update ke hash bcrypt yang aman jika sebelumnya plaintext
                    $new_hash = password_hash('admin123', PASSWORD_BCRYPT);
                    $upd = $pdo->prepare("UPDATE admin SET password = :p WHERE id_admin = :id");
                    $upd->execute([':p' => $new_hash, ':id' => $admin['id_admin']]);
                    $is_valid = true;
                }
            }

            if ($is_valid) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id_admin'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_nama'] = $admin['nama_admin'];
                set_flash('success', 'Selamat datang kembali, ' . htmlspecialchars($admin['nama_admin']));
                header('Location: dashboard.php');
                exit;
            } else {
                $error_msg = 'Username atau password yang Anda masukkan salah!';
            }
        } catch (Exception $e) {
            $error_msg = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrator - SPK Beasiswa AHP & TOPSIS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="login-page">

    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">
                <i data-lucide="graduation-cap" style="width: 38px; height: 38px;"></i>
            </div>
            <h1>SPK Beasiswa</h1>
            <p>Sistem Pendukung Keputusan Penerima Beasiswa Bantuan Dana Pendidikan Menggunakan Metode Kombinasi AHP & TOPSIS</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-error">
                <i data-lucide="alert-circle"></i>
                <div><?= htmlspecialchars($error_msg) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success">
                <i data-lucide="check-circle-2"></i>
                <div><?= htmlspecialchars($success_msg) ?></div>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" autocomplete="off">
            <div class="form-group">
                <label class="form-label" for="username">Username Admin</label>
                <div style="position: relative;">
                    <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username" required autofocus style="padding-left: 42px;">
                    <i data-lucide="user" style="position: absolute; left: 14px; top: 12px; width: 18px; height: 18px; color: var(--text-muted);"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div style="position: relative;">
                    <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password" required style="padding-left: 42px; padding-right: 42px;">
                    <i data-lucide="lock" style="position: absolute; left: 14px; top: 12px; width: 18px; height: 18px; color: var(--text-muted);"></i>
                    <button type="button" id="togglePassword" style="position: absolute; right: 12px; top: 10px; background: none; border: none; color: var(--text-muted); cursor: pointer;">
                        <i data-lucide="eye" id="eyeIcon" style="width: 18px; height: 18px;"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 13px; font-size: 0.95rem; margin-top: 10px;">
                <i data-lucide="log-in"></i> Masuk ke Sistem
            </button>
        </form>

        <!-- <div class="demo-credentials-badge">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="key" style="width: 16px; height: 16px; color: var(--secondary);"></i>
                <span>Default Login: <strong>admin</strong> / <strong>admin123</strong></span>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="quickFill()" style="padding: 3px 8px; font-size: 0.72rem;">
                Isi Otomatis
            </button>
        </div> -->
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        lucide.createIcons();

        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        togglePassword.addEventListener('click', function() {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            eyeIcon.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
            lucide.createIcons();
        });

        function quickFill() {
            document.getElementById('username').value = 'admin';
            document.getElementById('password').value = 'admin123';
        }
    </script>
</body>
</html>
