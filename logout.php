<?php
require_once 'config/database.php';

session_unset();
session_destroy();
session_start();
$_SESSION['flash_success'] = 'Anda telah berhasil logout dari sistem.';
header('Location: login.php');
exit;
