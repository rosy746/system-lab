<?php
define('APP_ACCESS', true);
require_once './app/config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        try {
            $pdo = getDB();

            // Get user by username
            $sql = "SELECT * FROM users 
                    WHERE username = :username 
                    AND deleted_at IS NULL 
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Check if account is locked
                if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $error = 'Akun Anda terkunci. Silakan coba lagi nanti.';
                } elseif (!$user['is_active']) {
                    $error = 'Akun Anda tidak aktif. Hubungi administrator.';
                } elseif (password_verify($password, $user['password_hash'])) {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['organization_id'] = $user['organization_id'];

                    // Update last login
                    $updateSql = "UPDATE users 
                                  SET last_login_at = CURRENT_TIMESTAMP,
                                      last_login_ip = :ip,
                                      failed_login_attempts = 0,
                                      locked_until = NULL
                                  WHERE id = :id";

                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([
                        ':ip' => getClientIP(),
                        ':id' => $user['id']
                    ]);

                    // Log activity
                    logActivity(
                        'login',
                        'user',
                        $user['id'],
                        'User logged in successfully',
                        $user['id'],
                        $user['full_name']
                    );

                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit;
                } else {
                    // Password incorrect - increment failed attempts
                    $failedAttempts = $user['failed_login_attempts'] + 1;
                    $lockUntil = null;

                    // Lock account after 5 failed attempts for 15 minutes
                    if ($failedAttempts >= 5) {
                        $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                        $error = 'Terlalu banyak percobaan login. Akun dikunci selama 15 menit.';
                    } else {
                        $error = 'Username atau password salah. Percobaan ke-' . $failedAttempts . ' dari 5.';
                    }

                    $updateSql = "UPDATE users 
                                  SET failed_login_attempts = :attempts,
                                      locked_until = :lock_until
                                  WHERE id = :id";

                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([
                        ':attempts' => $failedAttempts,
                        ':lock_until' => $lockUntil,
                        ':id' => $user['id']
                    ]);

                    // Log failed attempt
                    logActivity(
                        'failed_login',
                        'user',
                        $user['id'],
                        'Failed login attempt',
                        null,
                        'System'
                    );
                }
            } else {
                $error = 'Username atau password salah';

                // Log failed attempt with unknown username
                logActivity(
                    'failed_login',
                    'user',
                    0,
                    'Failed login attempt with username: ' . $username,
                    null,
                    'System'
                );
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sora': ['Sora', 'sans-serif'],
                        'inter': ['Inter', 'sans-serif'],
                    },
                    colors: {
                        'cream': '#faf8f5',
                        'warm-white': '#ffffff',
                        'text-dark': '#1e3a5f',
                        'text-mid': '#5a5a6e',
                        'text-light': '#9a9aad',
                        'border-color': '#eae8e3',
                        'cyan': '#00c9a7',
                        'cyan-light': '#e6faf5',
                        'blue': '#4a8cff',
                        'blue-light': '#eaf1ff',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .gradient-primary {
            background: linear-gradient(135deg, #1e3a5f 0%, #1a2e4a 60%, #16243b 100%);
        }

        .gradient-cyan-blue {
            background: linear-gradient(135deg, #00c9a7, #4a8cff);
        }

        .login-pattern {
            background-image:
                radial-gradient(circle at 20% 30%, rgba(0, 201, 167, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(74, 140, 255, 0.08) 0%, transparent 50%);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slideUp {
            animation: slideUp 0.6s ease-out forwards;
        }

        .input-field:focus {
            border-color: #00c9a7;
            box-shadow: 0 0 0 3px rgba(0, 201, 167, 0.1);
        }
    </style>
</head>

<body class="bg-cream login-pattern">

    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">

            <!-- Logo & Header -->
            <div class="text-center mb-8 animate-slideUp">
                <div class="inline-flex items-center justify-center w-16 h-16 gradient-cyan-blue rounded-2xl mb-4">
                    <span class="text-3xl">🧪</span>
                </div>
                <h1 class="font-sora text-3xl font-extrabold text-text-dark mb-2">
                    Selamat Datang
                </h1>
                <p class="text-text-mid">
                    Login ke Lab Management System
                </p>
            </div>

            <!-- Login Form -->
            <div class="bg-warm-white rounded-3xl shadow-2xl p-8 border border-border-color animate-slideUp" style="animation-delay: 0.1s;">

                <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <p class="text-sm text-red-700"><?= htmlspecialchars($error) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-4">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <p class="text-sm text-green-700"><?= htmlspecialchars($success) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-6">

                    <!-- Username Field -->
                    <div>
                        <label for="username" class="block text-sm font-semibold text-text-dark mb-2">
                            Username
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-text-light" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                required
                                autofocus
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                class="input-field w-full pl-10 pr-4 py-3 border-2 border-border-color rounded-xl focus:outline-none transition-all duration-200"
                                placeholder="Masukkan username Anda">
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-semibold text-text-dark mb-2">
                            Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-text-light" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                class="input-field w-full pl-10 pr-12 py-3 border-2 border-border-color rounded-xl focus:outline-none transition-all duration-200"
                                placeholder="Masukkan password Anda">
                            <button
                                type="button"
                                onclick="togglePassword()"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-text-light hover:text-text-mid transition-colors">
                                <svg id="eye-open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg id="eye-closed" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input
                                id="remember"
                                name="remember"
                                type="checkbox"
                                class="w-4 h-4 rounded border-border-color text-cyan focus:ring-cyan focus:ring-2">
                            <label for="remember" class="ml-2 text-sm text-text-mid">
                                Ingat saya
                            </label>
                        </div>
                        <a href="#" class="text-sm font-medium text-cyan hover:text-blue transition-colors">
                            Lupa password?
                        </a>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        name="login"
                        class="w-full gradient-primary text-white py-3 px-6 rounded-xl font-semibold text-lg hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 flex items-center justify-center gap-2">
                        <span>Masuk</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </button>

                </form>

                <!-- Divider -->
                <div class="mt-6 pt-6 border-t border-border-color">
                    <p class="text-center text-sm text-text-mid">
                        Belum punya akun?
                        <a href="register.php" class="font-semibold text-cyan hover:text-blue transition-colors">
                            Daftar sekarang
                        </a>
                    </p>
                </div>

            </div>

            <!-- Info Box -->
            <div class="mt-6 bg-blue-light border border-blue-200 rounded-2xl p-4 animate-slideUp" style="animation-delay: 0.2s;">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">
                        <svg class="w-5 h-5 text-blue" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-semibold text-text-dark mb-1">Demo Account</h4>
                        <p class="text-xs text-text-mid">
                            Username: <span class="font-mono font-semibold">admin</span> |
                            Password: <span class="font-mono font-semibold">password</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Back to Home -->
            <div class="mt-6 text-center animate-slideUp" style="animation-delay: 0.3s;">
                <a href="index.php" class="inline-flex items-center gap-2 text-text-mid hover:text-cyan transition-colors text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Kembali ke Beranda
                </a>
            </div>

        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeOpen = document.getElementById('eye-open');
            const eyeClosed = document.getElementById('eye-closed');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('[class*="bg-red-50"], [class*="bg-green-50"]');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>

</body>

</html>