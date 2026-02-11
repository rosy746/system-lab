<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/app/config.php';

// Get filter parameters
$lab_filter = $_GET['lab'] ?? 'all';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$condition_filter = $_GET['condition'] ?? '';
$search = $_GET['search'] ?? '';

// Initialize variables
$inventories = [];
$labs = [];
$summary = [
    'total_items' => 0,
    'total_quantity' => 0,
    'quantity_good' => 0,
    'quantity_broken' => 0,
    'quantity_backup' => 0,
    'active_items' => 0,
    'maintenance_items' => 0
];
$error_message = '';

try {
    $db = getDB();
    
    // Build query
    $sql = "SELECT 
                li.*,
                r.name AS lab_name,
                r.id AS lab_id,
                u1.full_name AS created_by_name,
                u2.full_name AS updated_by_name
            FROM lab_inventory li
            JOIN resources r ON li.resource_id = r.id
            LEFT JOIN users u1 ON li.created_by = u1.id
            LEFT JOIN users u2 ON li.updated_by = u2.id
            WHERE li.deleted_at IS NULL";

    $params = [];

    if (!empty($lab_filter) && $lab_filter !== 'all') {
        $sql .= " AND li.resource_id = :lab_id";
        $params[':lab_id'] = $lab_filter;
    }

    if (!empty($category_filter)) {
        $sql .= " AND li.category = :category";
        $params[':category'] = $category_filter;
    }

    if (!empty($status_filter)) {
        $sql .= " AND li.status = :status";
        $params[':status'] = $status_filter;
    }

    if (!empty($condition_filter)) {
        $sql .= " AND li.`condition` = :condition";
        $params[':condition'] = $condition_filter;
    }

    if (!empty($search)) {
        $sql .= " AND (li.item_code LIKE :search OR li.item_name LIKE :search OR li.brand LIKE :search OR li.model LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY r.name, li.category, li.item_code";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $inventories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get labs for tabs
    $labs_stmt = $db->query("SELECT id, name FROM resources WHERE type = 'lab' AND deleted_at IS NULL ORDER BY name");
    $labs = $labs_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary statistics
    $summary_sql = "SELECT 
                        COUNT(*) as total_items,
                        SUM(quantity) as total_quantity,
                        SUM(quantity_good) as quantity_good,
                        SUM(quantity_broken) as quantity_broken,
                        SUM(quantity_backup) as quantity_backup,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_items,
                        SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_items
                    FROM lab_inventory li
                    WHERE li.deleted_at IS NULL";
    
    if (!empty($lab_filter) && $lab_filter !== 'all') {
        $summary_sql .= " AND li.resource_id = :lab_id";
    }
    
    $summary_stmt = $db->prepare($summary_sql);
    if (!empty($lab_filter) && $lab_filter !== 'all') {
        $summary_stmt->bindValue(':lab_id', $lab_filter);
    }
    $summary_stmt->execute();
    $summary_result = $summary_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($summary_result) {
        $summary = array_merge($summary, $summary_result);
    }
    
} catch (PDOException $e) {
    error_log("Database error in inventory_list.php: " . $e->getMessage());
    $error_message = "Terjadi kesalahan saat mengambil data inventaris. Silakan coba lagi atau hubungi administrator.";
} catch (Exception $e) {
    error_log("General error in inventory_list.php: " . $e->getMessage());
    $error_message = "Terjadi kesalahan yang tidak terduga. Silakan coba lagi atau hubungi administrator.";
}

function buildTabUrl($lab_id) {
    $params = [];
    $params['lab'] = $lab_id;
    
    if (!empty($_GET['category'])) $params['category'] = $_GET['category'];
    if (!empty($_GET['status'])) $params['status'] = $_GET['status'];
    if (!empty($_GET['condition'])) $params['condition'] = $_GET['condition'];
    if (!empty($_GET['search'])) $params['search'] = $_GET['search'];
    
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventaris Lab – <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
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
            background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.3), transparent);
        }

        .logo svg {
            position: relative;
            z-index: 1;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
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
        }

        .hero-content p {
            font-size: 0.95rem;
            opacity: 0.9;
        }

        /* ═══ Alert ═══ */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid;
        }

        .alert-danger {
            background: var(--lab-danger-light);
            border-color: var(--lab-danger);
            color: var(--lab-danger);
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* ═══ Lab Tabs (Desktop) & Dropdown (Mobile) ═══ */
        .lab-selector-wrapper {
            margin-bottom: 1.5rem;
        }

        /* Desktop Tabs */
        .lab-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .lab-tab {
            padding: 0.75rem 1.25rem;
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .lab-tab:hover {
            border-color: var(--lab-primary);
            color: var(--lab-primary);
            background: var(--lab-primary-light);
        }

        .lab-tab.active {
            background: var(--lab-primary);
            border-color: var(--lab-primary);
            color: white;
            font-weight: 600;
        }

        /* Mobile Dropdown */
        .lab-dropdown {
            display: none;
            position: relative;
        }

        .lab-dropdown-button {
            width: 100%;
            padding: 0.875rem 1.25rem;
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }

        .lab-dropdown-button:hover {
            border-color: var(--lab-primary);
        }

        .lab-dropdown-button i {
            transition: transform 0.3s ease;
        }

        .lab-dropdown.open .lab-dropdown-button i {
            transform: rotate(180deg);
        }

        .lab-dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 0.5rem);
            left: 0;
            right: 0;
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-height: 300px;
            overflow-y: auto;
            z-index: 50;
        }

        .lab-dropdown.open .lab-dropdown-menu {
            display: block;
        }

        .lab-dropdown-item {
            padding: 0.75rem 1.25rem;
            text-decoration: none;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            border-bottom: 1px solid var(--lab-bg);
            transition: all 0.2s ease;
        }

        .lab-dropdown-item:last-child {
            border-bottom: none;
        }

        .lab-dropdown-item:hover {
            background: var(--lab-bg);
            color: var(--lab-primary);
        }

        .lab-dropdown-item.active {
            background: var(--lab-primary-light);
            color: var(--lab-primary);
            font-weight: 600;
        }

        /* ═══ Stats Grid ═══ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: var(--lab-primary);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        /* ═══ Filter Box ═══ */
        .filter-box {
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 14px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .filter-group select,
        .filter-group input[type="text"] {
            width: 100%;
            padding: 0.625rem 0.875rem;
            border: 1px solid var(--lab-border);
            border-radius: 8px;
            font-size: 0.875rem;
            color: var(--text-primary);
            background: var(--lab-white);
            transition: all 0.3s ease;
        }

        .filter-group select:focus,
        .filter-group input[type="text"]:focus {
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

        .btn-success {
            background: var(--lab-success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-info {
            background: var(--lab-secondary);
            color: white;
        }

        .btn-info:hover {
            background: #0891b2;
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
        }

        /* Dropdown for Export */
        .dropdown {
            position: relative;
        }

        .dropdown-toggle::after {
            content: '▼';
            margin-left: 0.5rem;
            font-size: 0.7rem;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            min-width: 180px;
            z-index: 100;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            padding: 0.75rem 1.25rem;
            text-decoration: none;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            border-bottom: 1px solid var(--lab-bg);
            transition: all 0.2s ease;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background: var(--lab-bg);
            color: var(--lab-primary);
        }

        /* ═══ Content Header ═══ */
        .content-header {
            margin-bottom: 1rem;
        }

        .content-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .count-badge {
            padding: 0.25rem 0.75rem;
            background: var(--lab-primary-light);
            color: var(--lab-primary);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
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

        /* ═══ Badges ═══ */
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
            display: inline-block;
        }

        /* Category Badges */
        .badge-cat-computer { background: var(--lab-primary-light); color: var(--lab-primary); }
        .badge-cat-peripheral { background: var(--lab-secondary-light); color: var(--lab-secondary); }
        .badge-cat-furniture { background: var(--lab-warning-light); color: var(--lab-warning); }
        .badge-cat-network { background: var(--lab-purple-light); color: var(--lab-purple); }
        .badge-cat-software { background: var(--lab-pink-light); color: var(--lab-pink); }
        .badge-cat-other { background: var(--lab-bg); color: var(--text-secondary); }

        /* Condition Badges */
        .badge-cond-excellent { background: var(--lab-success-light); color: var(--lab-success); }
        .badge-cond-good { background: var(--lab-secondary-light); color: var(--lab-secondary); }
        .badge-cond-fair { background: var(--lab-warning-light); color: var(--lab-warning); }
        .badge-cond-poor { background: var(--lab-danger-light); color: var(--lab-danger); }
        .badge-cond-broken { background: var(--lab-danger-light); color: var(--lab-danger); }

        /* Status Badges */
        .badge-st-active { background: var(--lab-success-light); color: var(--lab-success); }
        .badge-st-inactive { background: var(--lab-bg); color: var(--text-light); }
        .badge-st-maintenance { background: var(--lab-warning-light); color: var(--lab-warning); }
        .badge-st-retired { background: var(--lab-danger-light); color: var(--lab-danger); }

        /* ═══ Quantity Details ═══ */
        .qty-main {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .qty-detail {
            font-size: 0.75rem;
            line-height: 1.6;
        }

        .qty-good { color: var(--lab-success); }
        .qty-broken { color: var(--lab-danger); }
        .qty-backup { color: var(--lab-warning); }

        /* ═══ Empty State ═══ */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 14px;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        /* ═══ Responsive ═══ */
        @media (max-width: 968px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            /* Show dropdown, hide tabs */
            .lab-tabs {
                display: none;
            }
            
            .lab-dropdown {
                display: block;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }

            .table-wrapper {
                overflow-x: auto;
            }

            table {
                min-width: 1000px;
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
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .stat-card {
                padding: 1rem;
                aspect-ratio: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .stat-label {
                font-size: 0.7rem;
                margin-bottom: 0.5rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .filter-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
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
                    <path d="M9 3h6v7.5L21 21a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2l6-10.5V3z"/>
                    <circle cx="12" cy="17" r="1" fill="white"/>
                    <circle cx="9" cy="19" r="0.5" fill="white" opacity="0.6"/>
                    <circle cx="15" cy="19" r="0.5" fill="white" opacity="0.6"/>
                </svg>
            </div>
            <div class="logo-text">
                <h1>Inventaris Lab</h1>
                <p>Daftar Peralatan Laboratorium</p>
            </div>
        </div>
        <div class="header-right">
            <a href="dashboard.php" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Kembali ke Dashboard
            </a>
        </div>
    </div>
</div>

<!-- ═══ Main Container ═══ -->
<div class="container">

    <!-- Hero -->
    <div class="hero">
        <div class="hero-content">
            <h2>Inventaris Laboratorium</h2>
            <p>Kelola dan pantau semua peralatan laboratorium dengan mudah</p>
        </div>
    </div>

    <!-- Error Message -->
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <div><?= htmlspecialchars($error_message) ?></div>
        </div>
    <?php endif; ?>

    <!-- Lab Selector -->
    <div class="lab-selector-wrapper">
        <!-- Desktop: Tabs -->
        <div class="lab-tabs">
            <a href="<?= buildTabUrl('all') ?>" class="lab-tab <?= $lab_filter === 'all' ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                </svg>
                Semua Lab
            </a>
            <?php foreach($labs as $lab): ?>
                <a href="<?= buildTabUrl($lab['id']) ?>" class="lab-tab <?= $lab_filter == $lab['id'] ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 3h6v7.5L21 21a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2l6-10.5V3z"/>
                    </svg>
                    <?= htmlspecialchars($lab['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Mobile: Dropdown -->
        <div class="lab-dropdown" id="labDropdown">
            <button type="button" class="lab-dropdown-button" onclick="toggleLabDropdown()">
                <span>
                    <?php
                    if ($lab_filter === 'all') {
                        echo 'Semua Lab';
                    } else {
                        $current_lab = array_filter($labs, function($l) use ($lab_filter) { 
                            return $l['id'] == $lab_filter; 
                        });
                        $current_lab = reset($current_lab);
                        echo $current_lab ? htmlspecialchars($current_lab['name']) : 'Pilih Lab';
                    }
                    ?>
                </span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="lab-dropdown-menu">
                <a href="<?= buildTabUrl('all') ?>" class="lab-dropdown-item <?= $lab_filter === 'all' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    </svg>
                    Semua Lab
                </a>
                <?php foreach($labs as $lab): ?>
                    <a href="<?= buildTabUrl($lab['id']) ?>" class="lab-dropdown-item <?= $lab_filter == $lab['id'] ? 'active' : '' ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 3h6v7.5L21 21a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2l6-10.5V3z"/>
                        </svg>
                        <?= htmlspecialchars($lab['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Item</div>
            <div class="stat-number"><?= number_format($summary['total_items']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Unit</div>
            <div class="stat-number"><?= number_format($summary['total_quantity']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Unit Baik</div>
            <div class="stat-number"><?= number_format($summary['quantity_good']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Unit Rusak</div>
            <div class="stat-number"><?= number_format($summary['quantity_broken']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Unit Cadangan</div>
            <div class="stat-number"><?= number_format($summary['quantity_backup']) ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-box">
        <form method="GET" action="">
            <input type="hidden" name="lab" value="<?= htmlspecialchars($lab_filter) ?>">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Kategori</label>
                    <select name="category">
                        <option value="">Semua Kategori</option>
                        <option value="computer" <?= $category_filter == 'computer' ? 'selected' : '' ?>>Computer</option>
                        <option value="peripheral" <?= $category_filter == 'peripheral' ? 'selected' : '' ?>>Peripheral</option>
                        <option value="furniture" <?= $category_filter == 'furniture' ? 'selected' : '' ?>>Furniture</option>
                        <option value="network" <?= $category_filter == 'network' ? 'selected' : '' ?>>Network</option>
                        <option value="software" <?= $category_filter == 'software' ? 'selected' : '' ?>>Software</option>
                        <option value="other" <?= $category_filter == 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">Semua Status</option>
                        <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="maintenance" <?= $status_filter == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                        <option value="retired" <?= $status_filter == 'retired' ? 'selected' : '' ?>>Retired</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Kondisi</label>
                    <select name="condition">
                        <option value="">Semua Kondisi</option>
                        <option value="excellent" <?= $condition_filter == 'excellent' ? 'selected' : '' ?>>Excellent</option>
                        <option value="good" <?= $condition_filter == 'good' ? 'selected' : '' ?>>Good</option>
                        <option value="fair" <?= $condition_filter == 'fair' ? 'selected' : '' ?>>Fair</option>
                        <option value="poor" <?= $condition_filter == 'poor' ? 'selected' : '' ?>>Poor</option>
                        <option value="broken" <?= $condition_filter == 'broken' ? 'selected' : '' ?>>Broken</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Cari</label>
                    <input type="text" name="search" placeholder="Kode, nama, brand..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="inventory_list.php?lab=<?= htmlspecialchars($lab_filter) ?>" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
                <a href="inventory_add.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Tambah
                </a>
                <div class="dropdown">
                    <button type="button" class="btn btn-info dropdown-toggle" onclick="toggleDropdown(event)">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <div id="exportDropdown" class="dropdown-menu">
                        <a href="inventory_export.php?format=docx&<?= http_build_query($_GET) ?>" class="dropdown-item">
                            📘 Word (.docx)
                        </a>
                        <a href="inventory_export.php?format=xlsx&<?= http_build_query($_GET) ?>" class="dropdown-item">
                            📗 Excel (.xlsx)
                        </a>
                        <a href="inventory_export.php?format=csv&<?= http_build_query($_GET) ?>" class="dropdown-item">
                            📄 CSV
                        </a>
                        <a href="inventory_export.php?format=pdf&<?= http_build_query($_GET) ?>" class="dropdown-item" target="_blank">
                            📕 PDF / Print
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Content Header -->
    <div class="content-header">
        <h3>
            Daftar Inventaris
            <?php if ($lab_filter !== 'all'): ?>
                <?php 
                $current_lab = array_filter($labs, function($l) use ($lab_filter) { return $l['id'] == $lab_filter; });
                $current_lab = reset($current_lab);
                if ($current_lab): ?>
                    – <?= htmlspecialchars($current_lab['name']) ?>
                <?php endif; ?>
            <?php endif; ?>
            <span class="count-badge"><?= count($inventories) ?> item</span>
        </h3>
    </div>

    <!-- Table or Empty State -->
    <?php if (empty($inventories)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>
            </div>
            <h3>Tidak ada data inventaris</h3>
            <p>Silakan tambahkan inventaris baru atau ubah filter pencarian Anda</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Item</th>
                        <?php if ($lab_filter === 'all'): ?><th>Lab</th><?php endif; ?>
                        <th>Kategori</th>
                        <th>Brand/Model</th>
                        <th>Serial Number</th>
                        <th>Spesifikasi</th>
                        <th>Jumlah</th>
                        <th>Kondisi</th>
                        <th>Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventories as $item): ?>
                        <tr>
                            <td><strong style="color:var(--text-primary);"><?= htmlspecialchars($item['item_code']) ?></strong></td>
                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                            <?php if ($lab_filter === 'all'): ?>
                                <td><?= htmlspecialchars($item['lab_name']) ?></td>
                            <?php endif; ?>
                            <td><span class="badge badge-cat-<?= htmlspecialchars($item['category']) ?>"><?= ucfirst(htmlspecialchars($item['category'])) ?></span></td>
                            <td>
                                <?php if (!empty($item['brand']) || !empty($item['model'])): ?>
                                    <?= htmlspecialchars($item['brand'] ?? '-') ?><br>
                                    <small style="color:var(--text-light);"><?= htmlspecialchars($item['model'] ?? '-') ?></small>
                                <?php else: ?>
                                    –
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['serial_number'] ?? '–') ?></td>
                            <td><?= htmlspecialchars($item['specifications'] ?? '–') ?></td>
                            <td>
                                <div class="qty-main">Total: <?= number_format($item['quantity']) ?></div>
                                <div class="qty-detail">
                                    <span class="qty-good">✓ Baik: <?= number_format($item['quantity_good']) ?></span><br>
                                    <span class="qty-broken">✗ Rusak: <?= number_format($item['quantity_broken']) ?></span><br>
                                    <span class="qty-backup">◆ Cadangan: <?= number_format($item['quantity_backup']) ?></span>
                                </div>
                            </td>
                            <td><span class="badge badge-cond-<?= htmlspecialchars($item['condition']) ?>"><?= ucfirst(htmlspecialchars($item['condition'])) ?></span></td>
                            <td><span class="badge badge-st-<?= htmlspecialchars($item['status']) ?>"><?= ucfirst(htmlspecialchars($item['status'])) ?></span></td>
                            <td><?= htmlspecialchars($item['notes'] ?? '–') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<script>
// Toggle export dropdown
function toggleDropdown(event) {
    event.stopPropagation();
    document.getElementById('exportDropdown').classList.toggle('show');
}

// Toggle lab dropdown (mobile)
function toggleLabDropdown() {
    document.getElementById('labDropdown').classList.toggle('open');
}

// Close dropdowns when clicking outside
window.onclick = function(e) {
    if (!e.target.matches('.dropdown-toggle') && !e.target.matches('.lab-dropdown-button')) {
        // Close export dropdown
        const exportDrops = document.getElementsByClassName('dropdown-menu');
        for (let i = 0; i < exportDrops.length; i++) {
            if (exportDrops[i].classList.contains('show')) {
                exportDrops[i].classList.remove('show');
            }
        }
        
        // Close lab dropdown
        const labDropdown = document.getElementById('labDropdown');
        if (labDropdown && labDropdown.classList.contains('open')) {
            labDropdown.classList.remove('open');
        }
    }
}
</script>

</body>
</html>
