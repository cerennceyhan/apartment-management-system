<?php
// login.php - Armiya Teknoloji Giriş sayfası
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';

// Eğer zaten giriş yapmışsa ana sayfaya yönlendir
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'sakin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_type = $_POST['login_type'] ?? 'sakin';
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    $result = loginUser($username, $password, $login_type);
    
    if ($result['success']) {
        header('Location: index.php');
        exit();
    } elseif (isset($result['needs_password'])) {
        $_SESSION['temp_username'] = $result['username'];
        header('Location: create_password.php');
        exit();
    } else {
        $error = $result['message'];
        $active_tab = $login_type;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - Apartman Yönetimi | Armiya Teknoloji</title>
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

        /* Animated Background */
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

        /* Tab Navigation */
        .tab-nav {
            display: flex;
            margin-bottom: 32px;
            background: #f3f4f6;
            border-radius: 12px;
            padding: 4px;
            position: relative;
        }

        .tab-button {
            flex: 1;
            background: none;
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #6b7280;
            position: relative;
            z-index: 2;
        }

        .tab-button.active {
            color: #1e3a8a;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .tab-button:hover:not(.active) {
            color: #374151;
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(30, 58, 138, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .login-btn:hover::before {
            left: 100%;
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

        /* Tab Icons */
        .tab-icon {
            margin-right: 8px;
            font-size: 16px;
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

            .login-btn {
                padding: 14px;
                font-size: 15px;
            }

            .tab-button {
                padding: 10px 12px;
                font-size: 13px;
            }
        }

        /* Loading State */
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
            <h1>Apartman Yönetimi</h1>
            <p>Sisteme giriş yapın</p>
        </div>
        
        <!-- Tab Navigation -->
        <div class="tab-nav">
            <button class="tab-button <?= $active_tab === 'sakin' ? 'active' : '' ?>" 
                    onclick="switchTab('sakin')" id="sakinTab">
                <span class="tab-icon">🏠</span>
                Apartman Sakini
            </button>
            <button class="tab-button <?= $active_tab === 'uye' ? 'active' : '' ?>" 
                    onclick="switchTab('uye')" id="uyeTab">
                <span class="tab-icon">👤</span>
                Üye Girişi
            </button>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>⚠️ Hata:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>✅ Başarılı:</strong> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <!-- Apartman Sakini Giriş -->
        <div class="tab-content <?= $active_tab === 'sakin' ? 'active' : '' ?>" id="sakinContent">
            <form method="POST" id="sakinForm">
                <input type="hidden" name="login_type" value="sakin">
                
                <div class="form-group">
                    <label for="sakin_username" class="form-label">Daire Numarası</label>
                    <input type="text" 
                           id="sakin_username" 
                           name="username" 
                           class="form-input" 
                           placeholder="Daire numaranızı girin" 
                           required 
                           autocomplete="username"
                           value="<?= $active_tab === 'sakin' && isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="sakin_password" class="form-label">Şifre</label>
                    <input type="password" 
                           id="sakin_password" 
                           name="password" 
                           class="form-input" 
                           placeholder="Şifrenizi girin" 
                           required 
                           autocomplete="current-password">
                </div>
                
                <button type="submit" class="login-btn" id="sakinBtn">
                    <span>🏠 Apartman Sakini Girişi</span>
                </button>
            </form>
        </div>

        <!-- Üye Giriş -->
        <div class="tab-content <?= $active_tab === 'uye' ? 'active' : '' ?>" id="uyeContent">
            <form method="POST" id="uyeForm">
                <input type="hidden" name="login_type" value="uye">
                
                <div class="form-group">
                    <label for="uye_username" class="form-label">Ad Soyad</label>
                    <input type="text" 
                           id="uye_username" 
                           name="username" 
                           class="form-input" 
                           placeholder="Ad soyadınızı girin" 
                           required 
                           autocomplete="name"
                           value="<?= $active_tab === 'uye' && isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="uye_password" class="form-label">Şifre</label>
                    <input type="password" 
                           id="uye_password" 
                           name="password" 
                           class="form-input" 
                           placeholder="Şifrenizi girin" 
                           required 
                           autocomplete="current-password">
                </div>
                
                <button type="submit" class="login-btn" id="uyeBtn">
                    <span>👤 Üye Girişi</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        // Tab switching functionality
        function switchTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            document.getElementById(tabName + 'Tab').classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabName + 'Content').classList.add('active');
            
            // Focus first input
            setTimeout(() => {
                const firstInput = document.querySelector(`#${tabName}Content input[type="text"]`);
                if (firstInput) {
                    firstInput.focus();
                }
            }, 100);
        }

        // Form handling
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const btn = this.querySelector('.login-btn');
                btn.classList.add('loading');
                btn.disabled = true;
            });
        });

        // Auto-focus first empty field on page load
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = '<?= $active_tab ?>';
            const firstInput = document.querySelector(`#${activeTab}Content input[type="text"]`);
            if (firstInput && !firstInput.value) {
                firstInput.focus();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt + 1 for apartman sakini
            if (e.altKey && e.key === '1') {
                e.preventDefault();
                switchTab('sakin');
            }
            
            // Alt + 2 for üye girişi
            if (e.altKey && e.key === '2') {
                e.preventDefault();
                switchTab('uye');
            }
        });

        // Add ripple effect to buttons
        document.querySelectorAll('.login-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                    background: rgba(255, 255, 255, 0.3);
                    border-radius: 50%;
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    pointer-events: none;
                `;
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Add ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>