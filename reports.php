<?php
session_start();

// Define APP_ACCESS constant to allow config access
define('APP_ACCESS', true);

// Include config and functions
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/functions.php';

// Get database connection
$conn = getDB();

// Get date range from query params
// Default: 1 bulan terakhir (bukan bulan ini) agar mencakup lebih banyak data
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'overview';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Lab Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">
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
            --lab-danger: #ef4444;
            --lab-danger-light: #fef2f2;

            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;

            /* Legacy support */
            --cream: var(--lab-bg);
            --warm-white: var(--lab-white);
            --text-dark: var(--text-primary);
            --text-mid: var(--text-secondary);
            --border: var(--lab-border);
            --cyan: var(--lab-success);
            --cyan-light: var(--lab-success-light);
            --pink: var(--lab-pink);
            --pink-light: var(--lab-pink-light);
            --amber: var(--lab-warning);
            --amber-light: var(--lab-warning-light);
            --blue: var(--lab-primary);
            --blue-light: var(--lab-primary-light);
            --violet: var(--lab-purple);
            --violet-light: var(--lab-purple-light);
            --coral: var(--lab-danger);
            --coral-light: var(--lab-danger-light);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--lab-bg);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
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
            max-width: 1400px;
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

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--lab-bg);
            border: 1px solid var(--lab-border);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            background: var(--lab-white);
            border-color: var(--lab-primary);
            color: var(--lab-primary);
        }

        /* ═══ Main Container ═══ */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* ═══ Hero Section ═══ */
        .hero {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-content h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .hero-content p {
            font-size: 0.95rem;
            opacity: 0.9;
        }

        /* ═══ Filter Panel ═══ */
        .filter-panel {
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 14px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
        }

        .form-group label svg {
            flex-shrink: 0;
        }

        .form-group input,
        .form-group select {
            padding: 0.625rem 0.875rem;
            border: 1px solid var(--lab-border);
            border-radius: 8px;
            font-size: 0.875rem;
            color: var(--text-primary);
            background: var(--lab-white);
            transition: all 0.3s ease;
            font-family: inherit;
            background-image: url("data:image/svg+xml,%3Csvg width='12' height='12' viewBox='0 0 12 12' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M2 4l4 4 4-4' stroke='%236b7280' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            padding-right: 2.5rem;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--lab-primary);
            box-shadow: 0 0 0 3px var(--lab-primary-light);
        }

        .filter-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--lab-primary);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-export {
            background: var(--lab-success);
            color: white;
        }

        .btn-export:hover {
            background: #059669;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-secondary {
            background: var(--lab-bg);
            color: var(--text-secondary);
            border: 1px solid var(--lab-border);
        }

        .btn-secondary:hover {
            background: var(--lab-white);
            border-color: var(--text-secondary);
            color: var(--text-primary);
        }

        /* ═══ Stats Grid ═══ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
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

        .stat-icon.purple {
            background: var(--lab-purple-light);
        }

        .stat-info {
            flex: 1;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .stat-change {
            font-size: 0.75rem;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            margin-top: 0.3rem;
            display: inline-block;
        }

        .stat-change.up {
            background: var(--lab-success-light);
            color: var(--lab-success);
        }

        .stat-change.down {
            background: var(--lab-danger-light);
            color: var(--lab-danger);
        }

        /* ═══ Content Card ═══ */
        .content-card {
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 14px;
            padding: 1.8rem;
            margin-bottom: 2rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--lab-border);
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        /* ═══ Chart Container ═══ */
        .chart-container {
            position: relative;
            height: 350px;
            margin: 1.5rem 0;
        }

        /* ═══ Table ═══ */
        .table-wrapper {
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 14px;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--lab-bg);
        }

        th {
            padding: 1rem;
            text-align: left;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--lab-border);
        }

        td {
            padding: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--lab-bg);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: var(--lab-bg);
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
            display: inline-block;
        }

        .badge-success {
            background: var(--lab-success-light);
            color: var(--lab-success);
        }

        .badge-warning {
            background: var(--lab-warning-light);
            color: var(--lab-warning);
        }

        .badge-danger {
            background: var(--lab-danger-light);
            color: var(--lab-danger);
        }

        .badge-info {
            background: var(--lab-primary-light);
            color: var(--lab-primary);
        }

        /* ═══ Ranking Badges ═══ */
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 0.875rem;
        }

        .rank-badge.gold {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #854d0e;
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.4);
        }

        .rank-badge.silver {
            background: linear-gradient(135deg, #c0c0c0, #e8e8e8);
            color: #52525b;
            box-shadow: 0 2px 8px rgba(192, 192, 192, 0.4);
        }

        .rank-badge.bronze {
            background: linear-gradient(135deg, #cd7f32, #e6a57e);
            color: #78350f;
            box-shadow: 0 2px 8px rgba(205, 127, 50, 0.4);
        }

        .rank-badge.default {
            background: var(--lab-bg);
            color: var(--text-secondary);
            font-weight: 600;
        }

        /* ═══ Table Headers with Icons ═══ */
        th svg {
            opacity: 0.7;
        }

        /* ═══ Responsive ═══ */
        @media (max-width: 968px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-grid {
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

            .hero {
                padding: 1.5rem;
            }

            .hero-content h2 {
                font-size: 1.4rem;
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .stat-card {
                padding: 1rem;
                aspect-ratio: 1;
                flex-direction: column;
                justify-content: center;
                text-align: center;
            }

            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
            }

            .stat-number {
                font-size: 1.25rem;
            }

            .stat-label {
                font-size: 0.7rem;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .table-wrapper {
                overflow-x: auto;
            }

            table {
                min-width: 600px;
            }
        }

        @media print {

            .header,
            .filter-panel,
            .btn {
                display: none;
            }

            .container {
                max-width: 100%;
            }

            .content-card {
                page-break-inside: avoid;
            }
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
                    <h1>Laporan & Statistik</h1>
                    <p>Analisis Penggunaan Laboratorium</p>
                </div>
            </div>
            <div class="header-right">
                <a href="dashboard.php" class="back-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7" />
                    </svg>
                    Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- ═══ Main Container ═══ -->
    <div class="container">

        <!-- Hero Section -->
        <div class="hero">
            <div class="hero-content">
                <h2>
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="20" x2="12" y2="10"></line>
                        <line x1="18" y1="20" x2="18" y2="4"></line>
                        <line x1="6" y1="20" x2="6" y2="16"></line>
                    </svg>
                    Laporan Penggunaan Lab
                </h2>
                <p>Lihat statistik dan analisis lengkap penggunaan laboratorium berdasarkan berbagai kategori</p>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="filter-panel">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="form-group">
                        <label>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.25rem;">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                            </svg>
                            Jenis Laporan
                        </label>
                        <select name="report_type" id="reportType">
                            <option value="overview" <?= $report_type === 'overview' ? 'selected' : '' ?>>📋 Ringkasan Umum</option>
                            <option value="by_organization" <?= $report_type === 'by_organization' ? 'selected' : '' ?>>🏢 Per Lembaga</option>
                            <option value="by_class" <?= $report_type === 'by_class' ? 'selected' : '' ?>>🎓 Per Kelas</option>
                            <option value="by_teacher" <?= $report_type === 'by_teacher' ? 'selected' : '' ?>>👨‍🏫 Per Guru</option>
                            <option value="by_lab" <?= $report_type === 'by_lab' ? 'selected' : '' ?>>🧪 Per Laboratorium</option>
                            <option value="by_booking" <?= $report_type === 'by_booking' ? 'selected' : '' ?>>🔖 Booking</option>
                            <option value="by_subject" <?= $report_type === 'by_subject' ? 'selected' : '' ?>>📚 Per Mata Pelajaran</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.25rem;">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            Dari Tanggal
                        </label>
                        <input type="date" name="start_date" value="<?= $start_date ?>">
                    </div>
                    <div class="form-group">
                        <label>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.25rem;">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            Sampai Tanggal
                        </label>
                        <input type="date" name="end_date" value="<?= $end_date ?>">
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        Tampilkan Laporan
                    </button>
                    <a href="export_report.php?type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&format=excel" class="btn btn-export">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        Export Excel
                    </a>
                    <a href="export_report.php?type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&format=pdf" class="btn btn-export">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                        Export PDF
                    </a>
                    <button type="button" class="btn btn-secondary" onclick="window.print()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                            <rect x="6" y="14" width="12" height="8"></rect>
                        </svg>
                        Cetak
                    </button>
                </div>
            </form>
        </div>

        <!-- Dynamic Content Based on Report Type -->
        <div id="reportContent">
            <?php
            // Example Stats Overview - Replace with actual data from database
            // This section will be shown for 'overview' report type
            if ($report_type === 'overview') {
            ?>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number">156</div>
                            <div class="stat-label">Total Jadwal</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon cyan">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number">24</div>
                            <div class="stat-label">Kelas Terlibat</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon green">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 3h6v7.5L21 21a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2l6-10.5V3z" />
                                <circle cx="12" cy="17" r="1" fill="currentColor" />
                            </svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number">5</div>
                            <div class="stat-label">Lab Digunakan</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number">18</div>
                            <div class="stat-label">Guru Aktif</div>
                        </div>
                    </div>
                </div>

                <!-- Booking Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number">89</div>
                            <div class="stat-label">Total Booking</div>
                            <span class="stat-change up">↑ 12% bulan ini</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon green">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number">76</div>
                            <div class="stat-label">Booking Disetujui</div>
                            <span class="stat-change up">85% approval rate</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon cyan">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number">10</div>
                            <div class="stat-label">Booking Pending</div>
                            <span class="stat-change">Menunggu review</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number">3</div>
                            <div class="stat-label">Booking Ditolak</div>
                            <span class="stat-change down">↓ 50% vs bulan lalu</span>
                        </div>
                    </div>
                </div>

                <!-- Lab Rankings -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.5rem;">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                            </svg>
                            Peringkat Lab Berdasarkan Penggunaan
                        </h3>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.25rem;">
                                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                        </svg>
                                        Peringkat
                                    </th>
                                    <th>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.25rem;">
                                            <path d="M9 3h6v7.5L21 21a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2l6-10.5V3z" />
                                        </svg>
                                        Nama Lab
                                    </th>
                                    <th>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.25rem;">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                        </svg>
                                        Total Jadwal
                                    </th>
                                    <th>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.25rem;">
                                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                        </svg>
                                        Total Booking
                                    </th>
                                    <th>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.25rem;">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <path d="M12 6v6l4 2"></path>
                                        </svg>
                                        Jam Penggunaan
                                    </th>
                                    <th>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.25rem;">
                                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                            <polyline points="17 6 23 6 23 12"></polyline>
                                        </svg>
                                        Tingkat Penggunaan
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: linear-gradient(135deg, #ffd700, #ffed4e); border-radius: 50%; font-weight: 700; color: #854d0e;">1</div>
                                    </td>
                                    <td><strong>Lab Komputer 1</strong></td>
                                    <td>45</td>
                                    <td>32</td>
                                    <td>180 jam</td>
                                    <td><span class="badge badge-success">95%</span></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: linear-gradient(135deg, #c0c0c0, #e8e8e8); border-radius: 50%; font-weight: 700; color: #52525b;">2</div>
                                    </td>
                                    <td><strong>Lab IPA</strong></td>
                                    <td>38</td>
                                    <td>28</td>
                                    <td>152 jam</td>
                                    <td><span class="badge badge-success">87%</span></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: linear-gradient(135deg, #cd7f32, #e6a57e); border-radius: 50%; font-weight: 700; color: #78350f;">3</div>
                                    </td>
                                    <td><strong>Lab Komputer 2</strong></td>
                                    <td>35</td>
                                    <td>25</td>
                                    <td>140 jam</td>
                                    <td><span class="badge badge-info">80%</span></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: var(--lab-bg); border-radius: 50%; font-weight: 600; color: var(--text-secondary);">4</div>
                                    </td>
                                    <td><strong>Lab Multimedia</strong></td>
                                    <td>28</td>
                                    <td>19</td>
                                    <td>112 jam</td>
                                    <td><span class="badge badge-info">64%</span></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: var(--lab-bg); border-radius: 50%; font-weight: 600; color: var(--text-secondary);">5</div>
                                    </td>
                                    <td><strong>Lab Bahasa</strong></td>
                                    <td>20</td>
                                    <td>15</td>
                                    <td>80 jam</td>
                                    <td><span class="badge badge-warning">46%</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php
            } else {
                // Load appropriate report content based on type
                $report_file = 'reports/' . $report_type . '.php';
                if (file_exists($report_file)) {
                    include $report_file;
                } else {
                    echo '<div class="content-card">';
                    echo '<p style="text-align: center; padding: 2rem; color: var(--text-secondary);">Pilih jenis laporan dan rentang tanggal, kemudian klik "Tampilkan Laporan"</p>';
                    echo '</div>';
                }
            }
            ?>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Chart color palette - Updated to match lab theme
        const colors = {
            primary: '#2563eb',
            secondary: '#06b6d4',
            success: '#10b981',
            warning: '#f59e0b',
            purple: '#8b5cf6',
            pink: '#ec4899',

            // Legacy support
            cyan: '#10b981',
            amber: '#f59e0b',
            blue: '#2563eb',
            violet: '#8b5cf6',
            coral: '#ef4444'
        };

        // Initialize charts will be done in individual report files
    </script>

</body>

</html>