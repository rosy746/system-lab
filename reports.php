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
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">
    <style>
        :root {
            --cream: #faf8f5;
            --warm-white: #ffffff;
            --text-dark: #1e1e2e;
            --text-mid: #5a5a6e;
            --text-light: #9a9aad;
            --border: #eae8e3;

            --cyan: #00c9a7;
            --cyan-light: #e6faf5;
            --pink: #e8609a;
            --pink-light: #fde9f1;
            --amber: #f5a623;
            --amber-light: #fef3e0;
            --blue: #4a8cff;
            --blue-light: #eaf1ff;
            --violet: #8b6bea;
            --violet-light: #f0ecfd;
            --coral: #ff6f61;
            --coral-light: #fff0ee;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* ─── Top Bar ─── */
        .topbar {
            background: var(--warm-white);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .back-btn {
            width: 36px;
            height: 36px;
            background: var(--blue-light);
            border: none;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: var(--blue);
            font-size: 1.2rem;
        }

        .back-btn:hover {
            background: var(--blue);
            color: white;
            transform: translateX(-2px);
        }

        .topbar-logo {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--coral), var(--pink));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .topbar-text h1 {
            font-family: 'Sora', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .topbar-text span {
            font-size: 0.78rem;
            color: var(--text-light);
        }

        /* ─── Main ─── */
        .main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* ─── Page Header ─── */
        .page-header {
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .page-header h2 {
            font-family: 'Sora', sans-serif;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .page-header p {
            opacity: 0.8;
            font-size: 0.9rem;
            position: relative;
            z-index: 1;
        }

        /* ─── Filter Panel ─── */
        .filter-panel {
            background: var(--warm-white);
            border: 1.5px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-mid);
        }

        .form-group input,
        .form-group select {
            padding: 0.7rem;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: inherit;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px var(--blue-light);
        }

        .filter-actions {
            display: flex;
            gap: 0.8rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--blue);
            color: white;
        }

        .btn-primary:hover {
            background: #3a7cef;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(74, 140, 255, 0.3);
        }

        .btn-export {
            background: var(--cyan);
            color: white;
        }

        .btn-export:hover {
            background: #00b896;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 201, 167, 0.3);
        }

        .btn-secondary {
            background: var(--border);
            color: var(--text-mid);
        }

        .btn-secondary:hover {
            background: #dbd9d4;
        }

        /* ─── Report Type Tabs ─── */
        .report-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }

        .report-tab {
            padding: 0.8rem 1.5rem;
            background: var(--warm-white);
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            text-decoration: none;
            color: var(--text-mid);
        }

        .report-tab:hover {
            border-color: var(--blue);
            color: var(--blue);
        }

        .report-tab.active {
            background: var(--blue);
            border-color: var(--blue);
            color: white;
        }

        /* ─── Stats Cards ─── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--warm-white);
            border: 1.5px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .stat-info {
            flex: 1;
        }

        .stat-number {
            font-family: 'Sora', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 0.3rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-light);
            font-weight: 500;
        }

        .stat-change {
            font-size: 0.75rem;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            margin-top: 0.3rem;
            display: inline-block;
        }

        .stat-change.up {
            background: var(--cyan-light);
            color: var(--cyan);
        }

        .stat-change.down {
            background: var(--coral-light);
            color: var(--coral);
        }

        /* ─── Content Card ─── */
        .content-card {
            background: var(--warm-white);
            border: 1.5px solid var(--border);
            border-radius: 16px;
            padding: 1.8rem;
            margin-bottom: 2rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .card-title {
            font-family: 'Sora', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        /* ─── Chart Container ─── */
        .chart-container {
            position: relative;
            height: 350px;
            margin: 1.5rem 0;
        }

        /* ─── Table ─── */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: var(--cream);
            padding: 1rem;
            text-align: left;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-mid);
            border-bottom: 2px solid var(--border);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
        }

        tr:hover {
            background: var(--cream);
        }

        .badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: var(--cyan-light);
            color: var(--cyan);
        }

        .badge-warning {
            background: var(--amber-light);
            color: var(--amber);
        }

        .badge-danger {
            background: var(--coral-light);
            color: var(--coral);
        }

        .badge-info {
            background: var(--blue-light);
            color: var(--blue);
        }

        /* ─── Responsive ─── */
        @media (max-width: 768px) {
            .main {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .report-tabs {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>

<!-- ─── Top Bar ─── -->
<div class="topbar">
    <div class="topbar-left">
        <a href="index.php" class="back-btn">←</a>
        <div class="topbar-logo">📊</div>
        <div class="topbar-text">
            <h1>Laporan & Statistik</h1>
            <span>Analisis Penggunaan Laboratorium</span>
        </div>
    </div>
</div>

<!-- ─── Main ─── -->
<div class="main">

    <!-- Page Header -->
    <div class="page-header">
        <h2>📊 Laporan Penggunaan Lab</h2>
        <p>Lihat statistik dan analisis lengkap penggunaan laboratorium berdasarkan berbagai kategori</p>
    </div>

    <!-- Filter Panel -->
    <div class="filter-panel">
        <form method="GET" action="">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Jenis Laporan</label>
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
                    <label>Dari Tanggal</label>
                    <input type="date" name="start_date" value="<?= $start_date ?>">
                </div>
                <div class="form-group">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="end_date" value="<?= $end_date ?>">
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    🔍 Tampilkan Laporan
                </button>
                <a href="export_report.php?type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&format=excel" class="btn btn-export">
                    📥 Export Excel
                </a>
                <a href="export_report.php?type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&format=pdf" class="btn btn-export">
                    📄 Export PDF
                </a>
                <button type="button" class="btn btn-secondary" onclick="window.print()">
                    🖨️ Cetak
                </button>
            </div>
        </form>
    </div>

    <!-- Dynamic Content Based on Report Type -->
    <div id="reportContent">
        <?php
        // Load appropriate report content based on type
        $report_file = 'reports/' . $report_type . '.php';
        if (file_exists($report_file)) {
            include $report_file;
        } else {
            include 'reports/overview.php';
        }
        ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Chart color palette
    const colors = {
        cyan: '#00c9a7',
        pink: '#e8609a',
        amber: '#f5a623',
        blue: '#4a8cff',
        violet: '#8b6bea',
        coral: '#ff6f61'
    };

    // Initialize charts will be done in individual report files
</script>

</body>
</html>