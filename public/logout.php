<?php
// logout.php - Çıkış işlemi
session_start();
require_once __DIR__ . '/../src/auth.php';

logoutUser();
header('Location: login.php?message=cikis_yapildi');
exit();
?>