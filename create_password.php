<?php
// create_password.php - Şifre oluşturma sayfası
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Eğer temp username yoksa login'e yönlendir
if (!isset($_SESSION['temp_username'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['temp_username'];
$error = '';
$success = '';


function formatUsername($username) {
    $parts = explode('_', $username);
    $formatted = array_map('ucfirst', $parts);
    return implode(' ', $formatted);
}

$displayName = formatUsername($username);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    $result = createPassword($username, $password, $confirm_password);

    if ($result['success']) {
        unset($_SESSION['temp_username']);
        $success = $result['message'];
        // 2 saniye sonra login sayfasına yönlendir
        header("Refresh: 2; url=login.php");
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Oluştur - Apartman Yönetimi | Armiya Teknoloji</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #374151 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

     
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" style="stop-color:rgba(255,255,255,0.08)"/><stop offset="100%" style="stop-color:rgba(255,255,255,0)"/></radialGradient></defs><circle cx="200" cy="200" r="100" fill="url(%23a)"/><circle cx="800" cy="300" r="150" fill="url(%23a)"/><circle cx="300" cy="700" r="120" fill="url(%23a)"/><circle cx="700" cy="800" r="80" fill="url(%23a)"/></svg>') no-repeat center center;
            background-size: cover;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 48px 40px;
            width: 100%;
            max-width: 480px;
            box-shadow: 
                0 20px 25px -5px rgba(0, 0, 0, 0.1),
                0 10px 10px -5px rgba(0, 0, 0, 0.04),
                0 0 0 1px rgba(255, 255, 255, 0.05);
            position: relative;
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo {
            width: 200px;
            height: auto;
            margin: 0 auto 24px;
            display: block;
            filter: drop-shadow(0 4px 8px rgba(30, 58, 138, 0.2));
        }

        .login-header h1 {
            color: #1f2937;
            margin-bottom: 8px;
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #1e3a8a, #374151);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-header p {
            color: #6b7280;
            font-size: 16px;
            font-weight: 400;
        }

        .welcome-card {
            background: linear-gradient(135deg, #1e3a8a, #374151);
            color: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 32px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(30, 58, 138, 0.2);
        }

        .welcome-card h3 {
            font-size: 18px;
            margin-bottom: 8px;
            opacity: 0.9;
        }

        .welcome-card .username {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
        }

        .form-group {
            margin-bottom: 24px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            color: #1f2937;
        }

        .form-input:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 4px rgba(30, 58, 138, 0.1);
            transform: translateY(-1px);
        }

        .form-input::placeholder {
            color: #9ca3af;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #1e3a8a 0%, #374151 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(30, 58, 138, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .btn-secondary {
            width: 100%;
            padding: 16px;
            background: transparent;
            color: #374151;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            transform: translateY(-1px);
        }

        /* Alert Styles */
        .alert {
            padding: 16px 20px;
            margin-bottom: 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 1px solid #f87171;
        }

        .alert-success {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #166534;
            border: 1px solid #4ade80;
        }

        .floating-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }

        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            animation: float-shape 15s infinite linear;
        }

        .shape:nth-child(1) {
            width: 60px;
            height: 60px;
            left: 10%;
            top: 20%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 40px;
            height: 40px;
            left: 80%;
            top: 60%;
            animation-delay: 5s;
        }

        .shape:nth-child(3) {
            width: 80px;
            height: 80px;
            left: 60%;
            top: 10%;
            animation-delay: 10s;
        }

        @keyframes float-shape {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }

        .company-info {
            margin-top: 32px;
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 16px;
            border: 1px solid #e2e8f0;
        }

        .company-info p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .company-info .version {
            color: #9ca3af;
            font-size: 12px;
            font-weight: 500;
        }

        .password-requirements {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 1px solid #0ea5e9;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 14px;
            color: #0c4a6e;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 32px 24px;
                margin: 20px;
                border-radius: 20px;
                max-width: 95vw;
            }

            .logo {
                width: 160px;
            }

            .login-header h1 {
                font-size: 24px;
            }

            .form-input {
                padding: 14px 16px;
                font-size: 16px;
            }

            .login-btn, .btn-secondary {
                padding: 14px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="login-container">
        <div class="login-header">
            <img src="https://armiya.com/wp-content/uploads/2023/03/Arm-3425234234234234revize-2048x441.png" 
                 alt="Armiya Teknoloji" 
                 class="logo">
            <h1>Şifre Oluştur</h1>
            <p>İlk girişiniz için şifre belirleyin</p>
        </div>



        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>⚠️ Hata:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>✅ Başarılı:</strong> <?= htmlspecialchars($success) ?><br>Giriş sayfasına yönlendiriliyorsunuz...
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <div class="password-requirements">
            <strong>📋 Şifre Gereksinimleri:</strong><br>
            Şifreniz en az 6 karakter uzunluğunda olmalı ve güçlü olmalıdır.
        </div>

        <form method="POST" id="passwordForm">
            <div class="form-group">
                <label for="password" class="form-label">Yeni Şifre</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="form-input" 
                       placeholder="Güçlü bir şifre girin" 
                       required>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Şifre Tekrar</label>
                <input type="password" 
                       id="confirm_password" 
                       name="confirm_password" 
                       class="form-input" 
                       placeholder="Şifreyi tekrar girin" 
                       required>
            </div>

            <button type="submit" class="login-btn" id="createBtn">
                <span>🔒 Şifre Oluştur</span>
            </button>
            
            <a href="login.php" class="btn-secondary">
                ← Geri Dön
            </a>
        </form>
        <?php endif; ?>


    </div>

    <script>
        // Şifre eşleşme kontrolü
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#f87171';
                this.style.background = '#fee2e2';
            } else {
                this.style.borderColor = '#e5e7eb';
                this.style.background = 'white';
            }
        });

        // Form submit kontrolü
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Şifreler eşleşmiyor!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Şifre en az 6 karakter olmalıdır!');
                return false;
            }


            const btn = document.getElementById('createBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('password').focus();
        });

        const style = document.createElement('style');
        style.textContent = `
            .loading {
                position: relative;
            }
            .loading::after {
                content: '';
                position: absolute;
                width: 20px;
                height: 20px;
                margin: auto;
                border: 2px solid transparent;
                border-top-color: #ffffff;
                border-radius: 50%;
                animation: spin 1s ease infinite;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
            }
            @keyframes spin {
                0% { transform: translate(-50%, -50%) rotate(0deg); }
                100% { transform: translate(-50%, -50%) rotate(360deg); }
            }
            .loading span {
                opacity: 0;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>