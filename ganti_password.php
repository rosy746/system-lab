<?php
define('APP_ACCESS', true);
require_once './app/config.php';

// Untuk demo, tidak perlu login. Untuk production, uncomment baris berikut:
// requireAdmin();

$success = '';
$error = '';
$users = [];

// Get all users
try {
    $pdo = getDB();
    $sql = "SELECT id, username, email, full_name, role, is_active 
            FROM users 
            WHERE deleted_at IS NULL 
            ORDER BY username ASC";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading users: " . $e->getMessage();
}

// Process password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $userId = $_POST['user_id'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($userId) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Semua field harus diisi';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Password dan konfirmasi password tidak sama';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password minimal 6 karakter';
    } else {
        try {
            $pdo = getDB();

            // Get user info first
            $sql = "SELECT username, full_name FROM users WHERE id = :id LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = 'User tidak ditemukan';
            } else {
                // Hash password baru
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update password
                $sql = "UPDATE users 
                        SET password_hash = :password_hash,
                            failed_login_attempts = 0,
                            locked_until = NULL,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id";

                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    ':password_hash' => $passwordHash,
                    ':id' => $userId
                ]);

                if ($result) {
                    $success = "Password untuk user <strong>{$user['username']}</strong> ({$user['full_name']}) berhasil diubah!";

                    // Log activity
                    logActivity(
                        'password_changed',
                        'user',
                        $userId,
                        'Password changed via admin panel',
                        $_SESSION['user_id'] ?? null,
                        $_SESSION['full_name'] ?? 'System'
                    );
                } else {
                    $error = 'Gagal mengubah password';
                }
            }
        } catch (PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - <?= APP_NAME ?></title>
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
                        'blue': '#4a8cff',
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
    </style>
</head>

<body class="bg-cream">

    <div class="min-h-screen py-12 px-4">
        <div class="max-w-4xl mx-auto">

            <!-- Header -->
            <div class="mb-8">
                <h1 class="font-sora text-4xl font-extrabold text-text-dark mb-2">
                    🔐 Change User Password
                </h1>
                <p class="text-text-mid">Admin panel untuk mengubah password user</p>
            </div>

            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <p class="text-sm text-red-700"><?= $error ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <p class="text-sm text-green-700"><?= $success ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid lg:grid-cols-2 gap-6">

                <!-- Form Change Password -->
                <div class="bg-warm-white rounded-2xl shadow-lg p-8 border border-border-color">
                    <h2 class="font-sora text-2xl font-bold text-text-dark mb-6">
                        Ubah Password User
                    </h2>

                    <form method="POST" action="" class="space-y-5">

                        <!-- Select User -->
                        <div>
                            <label for="user_id" class="block text-sm font-semibold text-text-dark mb-2">
                                Pilih User
                            </label>
                            <select
                                name="user_id"
                                id="user_id"
                                required
                                class="w-full px-4 py-3 border-2 border-border-color rounded-xl focus:border-cyan focus:outline-none focus:ring-2 focus:ring-cyan focus:ring-opacity-20 transition-all">
                                <option value="">-- Pilih User --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>">
                                        <?= htmlspecialchars($user['username']) ?>
                                        (<?= htmlspecialchars($user['full_name']) ?>)
                                        - <?= ucfirst($user['role']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- New Password -->
                        <div>
                            <label for="new_password" class="block text-sm font-semibold text-text-dark mb-2">
                                Password Baru
                            </label>
                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                required
                                minlength="6"
                                class="w-full px-4 py-3 border-2 border-border-color rounded-xl focus:border-cyan focus:outline-none focus:ring-2 focus:ring-cyan focus:ring-opacity-20 transition-all"
                                placeholder="Minimal 6 karakter">
                            <p class="mt-1 text-xs text-text-light">Minimal 6 karakter</p>
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label for="confirm_password" class="block text-sm font-semibold text-text-dark mb-2">
                                Konfirmasi Password
                            </label>
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                required
                                minlength="6"
                                class="w-full px-4 py-3 border-2 border-border-color rounded-xl focus:border-cyan focus:outline-none focus:ring-2 focus:ring-cyan focus:ring-opacity-20 transition-all"
                                placeholder="Ulangi password baru">
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            name="change_password"
                            class="w-full gradient-primary text-white py-3 px-6 rounded-xl font-semibold hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
                            🔑 Ubah Password
                        </button>

                    </form>
                </div>

                <!-- User List -->
                <div class="bg-warm-white rounded-2xl shadow-lg p-8 border border-border-color">
                    <h2 class="font-sora text-2xl font-bold text-text-dark mb-6">
                        Daftar User
                    </h2>

                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        <?php foreach ($users as $user): ?>
                            <div class="flex items-center justify-between p-4 bg-cream rounded-xl border border-border-color hover:border-cyan transition-colors">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-semibold text-text-dark">
                                            <?= htmlspecialchars($user['username']) ?>
                                        </span>
                                        <span class="px-2 py-0.5 bg-blue text-white text-xs rounded-full">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                        <?php if (!$user['is_active']): ?>
                                            <span class="px-2 py-0.5 bg-red-500 text-white text-xs rounded-full">
                                                Inactive
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm text-text-mid">
                                        <?= htmlspecialchars($user['full_name']) ?>
                                    </p>
                                    <p class="text-xs text-text-light">
                                        <?= htmlspecialchars($user['email']) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <!-- Info Box -->
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-2xl p-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-blue" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-semibold text-text-dark mb-2">Informasi Penting</h4>
                        <ul class="text-sm text-text-mid space-y-1">
                            <li>• Password akan di-hash menggunakan bcrypt untuk keamanan</li>
                            <li>• User akan otomatis di-unlock jika sebelumnya terkunci</li>
                            <li>• Failed login attempts akan di-reset ke 0</li>
                            <li>• Semua perubahan akan dicatat di activity log</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="mt-6 flex gap-4">
                <a href="login.php" class="text-cyan hover:text-blue font-medium transition-colors">
                    ← Kembali ke Login
                </a>
                <a href="dashboard.php" class="text-cyan hover:text-blue font-medium transition-colors">
                    Dashboard →
                </a>
            </div>

        </div>
    </div>

    <script>
        // Validate password match
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;

            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('Password dan konfirmasi password tidak sama!');
                return false;
            }

            if (newPass.length < 6) {
                e.preventDefault();
                alert('Password minimal 6 karakter!');
                return false;
            }
        });

        // Auto-hide alerts
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