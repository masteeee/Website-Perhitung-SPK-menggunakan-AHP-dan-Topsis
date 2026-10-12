<?php
if (!isset($page_title)) {
    $page_title = 'Sistem Pendukung Keputusan AHP & TOPSIS';
}
$admin_name = $_SESSION['admin_nama'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - SPK Beasiswa</title>
    <!-- Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Lucide Icons (CDN) -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
<div class="app-layout">
