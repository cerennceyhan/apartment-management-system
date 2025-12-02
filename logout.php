<?php
// logout.php - Çıkış işlemi
session_start();
require_once 'includes/auth.php';

logoutUser();
header('Location: login.php?message=cikis_yapildi');
exit();
?>