<?php
session_start();
// Redirect jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Ambil username dari session
$username = $_SESSION['username'] ?? 'super_admin';
$initial = strtoupper(substr($username, 0, 1));
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Lab Theme Colors */
            --lab-white: #ffffff;
            --lab-bg: #f8f9fa;
            --lab-border: #e5e7eb;

            --lab-primary: #2563eb;
            --lab-primary-light: #eff6ff;
            --lab-secondary: #06b6d4;
            --lab-secondary-light: #ecfeff;
            --lab-success: #10b981;
            --lab-success-light: #f0fdf4;
            --lab-warning: #f59e0b;
            --lab-warning-light: #fffbeb;
            --lab-purple: #8b5cf6;
            --lab-purple-light: #f5f3ff;
            --lab-pink: #ec4899;
            --lab-pink-light: #fdf2f8;

            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            -webkit-text-size-adjust: 100%;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--lab-bg);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ═══ Header ═══ */
        .header {
            background: var(--lab-white);
            border-bottom: 1px solid var(--lab-border);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--lab-primary), var(--lab-secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
            position: relative;
            overflow: hidden;
        }

        .logo::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.3), transparent);
        }

        .logo svg {
            position: relative;
            z-index: 1;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }

        .logo-text h1 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.15rem;
        }

        .logo-text p {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .header-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .time-display {
            text-align: right;
        }

        .time-display .date {
            font-size: 0.85rem;
            color: var(--text-primary);
            font-weight: 600;
        }

        .time-display .time {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--lab-purple), var(--lab-pink));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }

        /* User Dropdown */
        .user-dropdown-wrapper {
            position: relative;
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 0.75rem);
            right: 0;
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            min-width: 220px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-dropdown::before {
            content: '';
            position: absolute;
            top: -6px;
            right: 12px;
            width: 12px;
            height: 12px;
            background: var(--lab-white);
            border-left: 1px solid var(--lab-border);
            border-top: 1px solid var(--lab-border);
            transform: rotate(45deg);
        }

        .dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid var(--lab-border);
        }

        .dropdown-user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .dropdown-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--lab-purple), var(--lab-pink));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }

        .dropdown-user-text h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.1rem;
        }

        .dropdown-user-text p {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .dropdown-menu {
            padding: 0.5rem;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            cursor: pointer;
            min-height: 44px;
            /* iOS touch target recommendation */
            -webkit-tap-highlight-color: transparent;
        }

        .dropdown-item:hover {
            background: var(--lab-bg);
        }

        .dropdown-item svg {
            width: 18px;
            height: 18px;
            color: var(--text-secondary);
        }

        .dropdown-item.danger {
            color: var(--lab-danger);
        }

        .dropdown-item.danger:hover {
            background: #fee2e2;
        }

        .dropdown-item.danger svg {
            color: var(--lab-danger);
        }

        .dropdown-divider {
            height: 1px;
            background: var(--lab-border);
            margin: 0.5rem 0;
        }

        /* ═══ Main Container ═══ */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* ═══ Welcome Section ═══ */
        .welcome-card {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }

        .welcome-content h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-content p {
            font-size: 0.95rem;
            opacity: 0.9;
        }

        /* ═══ Section Header ═══ */
        .section-header {
            margin: 2.5rem 0 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-header h3 {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
        }

        .section-header::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--lab-border);
        }

        /* ═══ Stats Grid ═══ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 12px;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: var(--lab-primary);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .stat-icon.blue {
            background: var(--lab-primary-light);
        }

        .stat-icon.cyan {
            background: var(--lab-secondary-light);
        }

        .stat-icon.green {
            background: var(--lab-success-light);
        }

        .stat-icon.orange {
            background: var(--lab-warning-light);
        }

        .stat-content .number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
        }

        .stat-content .label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* ═══ Menu Grid ═══ */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
        }

        .menu-item {
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 14px;
            padding: 1.75rem 1.5rem;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .menu-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--item-color);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .menu-item:hover::before {
            transform: scaleX(1);
        }

        .menu-item:hover {
            border-color: var(--item-color);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            transform: translateY(-4px);
        }

        .menu-item[data-color="blue"] {
            --item-color: var(--lab-primary);
        }

        .menu-item[data-color="cyan"] {
            --item-color: var(--lab-secondary);
        }

        .menu-item[data-color="green"] {
            --item-color: var(--lab-success);
        }

        .menu-item[data-color="orange"] {
            --item-color: var(--lab-warning);
        }

        .menu-item[data-color="purple"] {
            --item-color: var(--lab-purple);
        }

        .menu-item[data-color="pink"] {
            --item-color: var(--lab-pink);
        }

        .menu-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }

        .menu-icon-wrapper {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            flex-shrink: 0;
        }

        .menu-item[data-color="blue"] .menu-icon-wrapper {
            background: var(--lab-primary-light);
        }

        .menu-item[data-color="cyan"] .menu-icon-wrapper {
            background: var(--lab-secondary-light);
        }

        .menu-item[data-color="green"] .menu-icon-wrapper {
            background: var(--lab-success-light);
        }

        .menu-item[data-color="orange"] .menu-icon-wrapper {
            background: var(--lab-warning-light);
        }

        .menu-item[data-color="purple"] .menu-icon-wrapper {
            background: var(--lab-purple-light);
        }

        .menu-item[data-color="pink"] .menu-icon-wrapper {
            background: var(--lab-pink-light);
        }

        .menu-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-new {
            background: var(--lab-success-light);
            color: var(--lab-success);
        }

        .badge-popular {
            background: var(--lab-warning-light);
            color: var(--lab-warning);
        }

        .menu-body h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .menu-body p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .menu-arrow {
            font-size: 0.9rem;
            color: var(--text-light);
            transition: all 0.3s ease;
            align-self: flex-end;
        }

        .menu-item:hover .menu-arrow {
            color: var(--item-color);
            transform: translateX(4px);
        }

        /* ═══ Info Banner ═══ */
        .info-banner {
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-left: 4px solid var(--lab-primary);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-top: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .info-icon {
            width: 44px;
            height: 44px;
            background: var(--lab-primary-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .info-text {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .info-text strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        /* ═══ Footer ═══ */
        footer {
            text-align: center;
            padding: 2rem;
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 2rem;
        }

        /* ═══ Responsive ═══ */
        @media (max-width: 968px) {
            .menu-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .container {
                padding: 1.5rem 1rem;
            }

            .header {
                padding: 1rem;
            }

            .header-content {
                gap: 0.75rem;
            }

            .header-info {
                gap: 1rem;
            }

            /* Logo Section Mobile */
            .logo {
                width: 40px;
                height: 40px;
                border-radius: 10px;
            }

            .logo svg {
                width: 22px;
                height: 22px;
            }

            .logo-text h1 {
                font-size: 1rem;
            }

            .logo-text p {
                font-size: 0.7rem;
            }

            /* Hide time display on mobile */
            .time-display {
                display: none;
            }

            /* User Avatar Mobile */
            .user-avatar {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }

            /* Dropdown Mobile Adjustments */
            .user-dropdown {
                min-width: 200px;
                right: -10px;
            }

            .user-dropdown::before {
                right: 20px;
            }

            .dropdown-header {
                padding: 0.875rem;
            }

            .dropdown-avatar {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }

            .dropdown-user-text h4 {
                font-size: 0.85rem;
            }

            .dropdown-user-text p {
                font-size: 0.7rem;
            }

            .dropdown-item {
                padding: 0.65rem 0.875rem;
                font-size: 0.85rem;
            }

            .dropdown-item svg {
                width: 16px;
                height: 16px;
            }

            /* Content Mobile */
            .menu-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .welcome-card {
                padding: 1.5rem;
            }

            .welcome-content h2 {
                font-size: 1.4rem;
            }

            .welcome-content p {
                font-size: 0.85rem;
            }

            /* Menu Items Mobile */
            .menu-item {
                padding: 1.5rem 1.25rem;
            }

            .menu-icon-wrapper {
                width: 48px;
                height: 48px;
            }

            .menu-icon-wrapper svg {
                width: 24px;
                height: 24px;
            }

            .menu-badge {
                font-size: 0.65rem;
                padding: 0.2rem 0.6rem;
            }

            .menu-body h4 {
                font-size: 1rem;
            }

            .menu-body p {
                font-size: 0.8rem;
            }

            /* Stats Mobile */
            .stat-card {
                padding: 1rem;
            }

            .stat-icon {
                width: 40px;
                height: 40px;
            }

            .stat-icon svg {
                width: 20px;
                height: 20px;
            }

            .stat-content .number {
                font-size: 1.3rem;
            }

            .stat-content .label {
                font-size: 0.75rem;
            }

            /* Info Banner Mobile */
            .info-banner {
                padding: 1rem;
                flex-direction: column;
                text-align: center;
            }

            .info-icon {
                width: 40px;
                height: 40px;
            }

            .info-text {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 375px) {

            /* Extra small devices */
            .header {
                padding: 0.875rem;
            }

            .logo {
                width: 36px;
                height: 36px;
            }

            .logo svg {
                width: 20px;
                height: 20px;
            }

            .logo-text h1 {
                font-size: 0.9rem;
            }

            .logo-text p {
                display: none;
            }

            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 0.85rem;
            }

            .user-dropdown {
                min-width: 180px;
                right: -5px;
            }

            .container {
                padding: 1rem 0.75rem;
            }

            .welcome-card {
                padding: 1.25rem;
            }

            .welcome-content h2 {
                font-size: 1.2rem;
            }

            .menu-item {
                padding: 1.25rem 1rem;
            }
        }

        /* ═══ Animations ═══ */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .menu-item {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }

        .menu-item:nth-child(1) {
            animation-delay: 0.05s;
        }

        .menu-item:nth-child(2) {
            animation-delay: 0.1s;
        }

        .menu-item:nth-child(3) {
            animation-delay: 0.15s;
        }

        .menu-item:nth-child(4) {
            animation-delay: 0.2s;
        }

        .menu-item:nth-child(5) {
            animation-delay: 0.25s;
        }

        .menu-item:nth-child(6) {
            animation-delay: 0.3s;
        }
    </style>
</head>

<body>

    <!-- ═══ Header ═══ -->
    <div class="header">
        <div class="header-content">
            <div class="logo-section">
                <div class="logo">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 3h6v7.5L21 21a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2l6-10.5V3z" />
                        <circle cx="12" cy="17" r="1" fill="white" />
                        <circle cx="9" cy="19" r="0.5" fill="white" opacity="0.6" />
                        <circle cx="15" cy="19" r="0.5" fill="white" opacity="0.6" />
                    </svg>
                </div>
                <div class="logo-text">
                    <h1>Lab Management</h1>
                    <p>Sistem Manajemen Laboratorium</p>
                </div>
            </div>
            <div class="header-info">
                <div class="time-display">
                    <div class="date" id="js-date">—</div>
                    <div class="time" id="js-time">—</div>
                </div>
                <div class="user-dropdown-wrapper">
                    <div class="user-avatar" id="userAvatar"><?php echo $initial; ?></div>

                    <!-- Dropdown Menu -->
                    <div class="user-dropdown" id="userDropdown">
                        <div class="dropdown-header">
                            <div class="dropdown-user-info">
                                <div class="dropdown-avatar"><?php echo $initial; ?></div>
                                <div class="dropdown-user-text">
                                    <h4><?php echo htmlspecialchars($username); ?></h4>
                                    <p>Administrator</p>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-menu">
                            <a href="login.php" class="dropdown-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                <span>Switch User</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="logout.php" class="dropdown-item danger">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                    <polyline points="16 17 21 12 16 7"></polyline>
                                    <line x1="21" y1="12" x2="9" y2="12"></line>
                                </svg>
                                <span>Log Out</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ Main Container ═══ -->
    <div class="container">

        <!-- Welcome Section -->
        <div class="welcome-card">
            <div class="welcome-content">
                <h2>Selamat Datang di Sistem Lab</h2>
                <p>Kelola laboratorium Anda dengan mudah dan efisien melalui dashboard ini</p>
            </div>
        </div>

        <!-- Menu Section -->
        <div class="section-header">
            <h3>Menu Utama</h3>
        </div>
        <div class="menu-grid">

            <a href="schedules.php" class="menu-item" data-color="cyan">
                <div class="menu-header">
                    <div class="menu-icon-wrapper">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#06b6d4" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                    </div>
                    <span class="menu-badge badge-popular">Popular</span>
                </div>
                <div class="menu-body">
                    <h4>Jadwal Lab</h4>
                    <p>Lihat dan kelola jadwal penggunaan laboratorium komputer dan IPA</p>
                </div>
                <span class="menu-arrow">Klik Selengkapnya →</span>
            </a>

            <a href="inventory_list.php" class="menu-item" data-color="green">
                <div class="menu-header">
                    <div class="menu-icon-wrapper">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                    </div>
                </div>
                <div class="menu-body">
                    <h4>Inventaris</h4>
                    <p>Pantau semua peralatan dan aset laboratorium dalam satu tempat</p>
                </div>
                <span class="menu-arrow">Klik Selengkapnya →</span>
            </a>

            <a href="schedules.php" class="menu-item" data-color="orange">
                <div class="menu-header">
                    <div class="menu-icon-wrapper">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                    <span class="menu-badge badge-popular">Popular</span>
                </div>
                <div class="menu-body">
                    <h4>Booking Lab</h4>
                    <p>Reservasi ruang laboratorium untuk acara atau kegiatan khusus</p>
                </div>
                <span class="menu-arrow">Klik Selengkapnya →</span>
            </a>

            <a href="classes.php" class="menu-item" data-color="blue">
                <div class="menu-header">
                    <div class="menu-icon-wrapper">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
                            <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                            <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                        </svg>
                    </div>
                </div>
                <div class="menu-body">
                    <h4>Kelas</h4>
                    <p>Kelola data kelas dan siswa yang menggunakan laboratorium</p>
                </div>
                <span class="menu-arrow">Klik Selengkapnya →</span>
            </a>

            <a href="maintenance.php" class="menu-item" data-color="purple">
                <div class="menu-header">
                    <div class="menu-icon-wrapper">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2">
                            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                        </svg>
                    </div>
                    <span class="menu-badge badge-new">Baru</span>
                </div>
                <div class="menu-body">
                    <h4>Maintenance</h4>
                    <p>Catat dan pantau status perawatan dan perbaikan peralatan lab</p>
                </div>
                <span class="menu-arrow">Klik Selengkapnya →</span>
            </a>

            <a href="reports.php" class="menu-item" data-color="pink">
                <div class="menu-header">
                    <div class="menu-icon-wrapper">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="2">
                            <line x1="12" y1="20" x2="12" y2="10"></line>
                            <line x1="18" y1="20" x2="18" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="16"></line>
                        </svg>
                    </div>
                </div>
                <div class="menu-body">
                    <h4>Laporan</h4>
                    <p>Lihat statistik dan laporan penggunaan laboratorium secara lengkap</p>
                </div>
                <span class="menu-arrow">Klik Selengkapnya →</span>
            </a>

        </div>

        <!-- Stats Section -->
        <div class="section-header">
            <h3>Ringkasan Hari Ini</h3>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                        <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="number">24</div>
                    <div class="label">Total Kelas</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon cyan">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="number">33</div>
                    <div class="label">Jadwal Aktif</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="9" y1="15" x2="15" y2="15"></line>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="number">2</div>
                    <div class="label">Laboratorium</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="number">100%</div>
                    <div class="label">Uptime</div>
                </div>
            </div>
        </div>

        <!-- Info Banner -->
        <div class="info-banner">
            <div class="info-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
            </div>
            <div class="info-text">
                <strong>Panduan Cepat:</strong> Gunakan menu <strong>Booking Lab</strong> untuk memesan ruangan, dan <strong>Jadwal Lab</strong> untuk melihat jadwal yang sudah ada. Untuk bantuan lebih lanjut, hubungi tim IT.
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer>
        © 2026 Lab Management System · PP Nurul Islam Jember
    </footer>

    <!-- Script -->
    <script>
        // Live clock
        function updateClock() {
            const now = new Date();
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

            document.getElementById('js-date').textContent =
                days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()];

            document.getElementById('js-time').textContent =
                now.getHours().toString().padStart(2, '0') + ':' +
                now.getMinutes().toString().padStart(2, '0');
        }

        updateClock();
        setInterval(updateClock, 1000);

        // User Dropdown Toggle
        const userAvatar = document.getElementById('userAvatar');
        const userDropdown = document.getElementById('userDropdown');
        let isDropdownOpen = false;

        // Toggle dropdown on click/tap
        userAvatar.addEventListener('click', function(e) {
            e.stopPropagation();
            isDropdownOpen = !isDropdownOpen;

            if (isDropdownOpen) {
                userDropdown.classList.add('show');
            } else {
                userDropdown.classList.remove('show');
            }
        });

        // Close dropdown when clicking/tapping outside
        document.addEventListener('click', function(e) {
            if (!userDropdown.contains(e.target) && e.target !== userAvatar) {
                userDropdown.classList.remove('show');
                isDropdownOpen = false;
            }
        });

        // Prevent dropdown from closing when clicking inside
        userDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Touch support for mobile devices
        let touchStartY = 0;
        let touchEndY = 0;

        userDropdown.addEventListener('touchstart', function(e) {
            touchStartY = e.changedTouches[0].screenY;
        }, {
            passive: true
        });

        userDropdown.addEventListener('touchend', function(e) {
            touchEndY = e.changedTouches[0].screenY;
            // Prevent accidental closes on scroll
            if (Math.abs(touchEndY - touchStartY) < 10) {
                e.stopPropagation();
            }
        }, {
            passive: true
        });

        // Handle orientation change on mobile
        window.addEventListener('orientationchange', function() {
            userDropdown.classList.remove('show');
            isDropdownOpen = false;
        });

        // Prevent body scroll when dropdown is open on mobile
        const body = document.body;
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    const isOpen = userDropdown.classList.contains('show');
                    if (isOpen && window.innerWidth <= 640) {
                        // Optionally prevent scroll on small screens
                        // body.style.overflow = 'hidden';
                    } else {
                        // body.style.overflow = '';
                    }
                }
            });
        });

        observer.observe(userDropdown, {
            attributes: true
        });
    </script>

</body>

</html>