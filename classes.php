<?php
session_start();
// Redirect jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Database connection
$host = 'localhost';
$dbname = 'lab-masucup';
$username_db = 'root';
$password_db = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Ambil username dari session
$username = $_SESSION['username'] ?? 'super_admin';
$initial = strtoupper(substr($username, 0, 1));

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $pdo->prepare("INSERT INTO classes (name, organization_id, grade_level, major, student_count, academic_year, semester, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['organization_id'],
                    $_POST['grade_level'],
                    $_POST['major'],
                    $_POST['student_count'],
                    $_POST['academic_year'],
                    $_POST['semester']
                ]);
                header("Location: classes.php?success=added");
                exit;
                break;

            case 'edit':
                $stmt = $pdo->prepare("UPDATE classes SET name=?, organization_id=?, grade_level=?, major=?, student_count=?, academic_year=?, semester=? WHERE id=?");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['organization_id'],
                    $_POST['grade_level'],
                    $_POST['major'],
                    $_POST['student_count'],
                    $_POST['academic_year'],
                    $_POST['semester'],
                    $_POST['id']
                ]);
                header("Location: classes.php?success=updated");
                exit;
                break;

            case 'delete':
                $stmt = $pdo->prepare("UPDATE classes SET deleted_at=NOW() WHERE id=?");
                $stmt->execute([$_POST['id']]);
                header("Location: classes.php?success=deleted");
                exit;
                break;

            case 'toggle_status':
                $stmt = $pdo->prepare("UPDATE classes SET is_active=? WHERE id=?");
                $stmt->execute([$_POST['is_active'], $_POST['id']]);
                header("Location: classes.php?success=status_updated");
                exit;
                break;
        }
    }
}

// Fetch organizations from database
$organizations_query = "SELECT id, name, type FROM organizations WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name";
$organizations = $pdo->query($organizations_query)->fetchAll(PDO::FETCH_ASSOC);

// Fetch filters
$search = $_GET['search'] ?? '';
$grade_filter = $_GET['grade'] ?? '';
$org_filter = $_GET['org'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$query = "SELECT c.*, o.name as org_name, o.type as org_type
            FROM classes c 
            LEFT JOIN organizations o ON c.organization_id = o.id
            WHERE c.deleted_at IS NULL";

$params = [];

if ($search) {
    $query .= " AND (c.name LIKE ? OR c.grade_level LIKE ? OR c.major LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($grade_filter) {
    $query .= " AND c.grade_level = ?";
    $params[] = $grade_filter;
}

if ($org_filter) {
    $query .= " AND c.organization_id = ?";
    $params[] = $org_filter;
}

if ($status_filter !== '') {
    $query .= " AND c.is_active = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY c.organization_id, c.grade_level, c.name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN organization_id = 1 THEN 1 ELSE 0 END) as ma,
        SUM(CASE WHEN organization_id = 2 THEN 1 ELSE 0 END) as mts
        FROM classes WHERE deleted_at IS NULL";
$stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);

// Get unique grade levels for filter
$grades_query = "SELECT DISTINCT grade_level FROM classes WHERE deleted_at IS NULL AND grade_level != '' ORDER BY grade_level";
$grades = $pdo->query($grades_query)->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kelas - Lab Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
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
            --lab-danger: #ef4444;
            --lab-danger-light: #fef2f2;
            --lab-purple: #8b5cf6;
            --lab-purple-light: #f5f3ff;
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

        /* Header - Same as dashboard */
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
        }

        .logo-text h1 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .logo-text p {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 8px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .back-button:hover {
            border-color: var(--lab-primary);
            color: var(--lab-primary);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--lab-purple), #ec4899);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Page Header */
        .page-header {
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .page-title-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .page-title h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .page-title p {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--bg-from), var(--bg-to));
            border-radius: 12px;
            padding: 1.25rem;
            color: white;
        }

        .stat-card.blue {
            --bg-from: #3b82f6;
            --bg-to: #2563eb;
        }

        .stat-card.green {
            --bg-from: #10b981;
            --bg-to: #059669;
        }

        .stat-card.purple {
            --bg-from: #8b5cf6;
            --bg-to: #7c3aed;
        }

        .stat-card.cyan {
            --bg-from: #06b6d4;
            --bg-to: #0891b2;
        }

        .stat-label {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
        }

        /* Filters */
        .filters-section {
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .filter-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .filter-input,
        .filter-select {
            width: 100%;
            padding: 0.625rem 1rem;
            border: 1px solid var(--lab-border);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--lab-primary);
            box-shadow: 0 0 0 3px var(--lab-primary-light);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--lab-primary);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary {
            background: var(--lab-white);
            color: var(--text-primary);
            border: 1px solid var(--lab-border);
        }

        .btn-secondary:hover {
            border-color: var(--lab-primary);
            color: var(--lab-primary);
        }

        .btn-success {
            background: var(--lab-success);
            color: white;
        }

        .btn-warning {
            background: var(--lab-warning);
            color: white;
        }

        .btn-danger {
            background: var(--lab-danger);
            color: white;
        }

        .btn-sm {
            padding: 0.4rem 0.875rem;
            font-size: 0.8rem;
        }

        /* Table */
        .table-container {
            background: var(--lab-white);
            border: 1px solid var(--lab-border);
            border-radius: 12px;
            overflow: hidden;
        }

        .table-wrapper {
            overflow-x: auto;
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
            border-bottom: 1px solid var(--lab-border);
            font-size: 0.875rem;
        }

        tbody tr:hover {
            background: var(--lab-bg);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: var(--lab-success-light);
            color: var(--lab-success);
        }

        .badge-danger {
            background: var(--lab-danger-light);
            color: var(--lab-danger);
        }

        .badge-primary {
            background: var(--lab-primary-light);
            color: var(--lab-primary);
        }

        .badge-warning {
            background: var(--lab-warning-light);
            color: var(--lab-warning);
        }

        .badge-purple {
            background: var(--lab-purple-light);
            color: var(--lab-purple);
        }

        .badge-secondary {
            background: var(--lab-secondary-light);
            color: var(--lab-secondary);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid var(--lab-border);
            background: var(--lab-white);
        }

        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-icon.edit:hover {
            border-color: var(--lab-primary);
            background: var(--lab-primary-light);
        }

        .btn-icon.delete:hover {
            border-color: var(--lab-danger);
            background: var(--lab-danger-light);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--lab-white);
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--lab-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: none;
            background: var(--lab-bg);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: var(--lab-danger-light);
            color: var(--lab-danger);
        }

        .modal-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-group label .required {
            color: var(--lab-danger);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--lab-border);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--lab-primary);
            box-shadow: 0 0 0 3px var(--lab-primary-light);
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--lab-border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* Alert */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: var(--lab-success-light);
            color: var(--lab-success);
            border: 1px solid var(--lab-success);
        }

        .alert-info {
            background: var(--lab-primary-light);
            color: var(--lab-primary);
            border: 1px solid var(--lab-primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            color: var(--text-light);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .table-wrapper {
                overflow-x: scroll;
            }
        }

        @media (max-width: 640px) {
            .container {
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-title-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions .btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>

<body>

    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="logo-section">
                <div class="logo">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <path d="M9 3h6v7.5L21 21a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2l6-10.5V3z" />
                        <circle cx="12" cy="17" r="1" fill="white" />
                    </svg>
                </div>
                <div class="logo-text">
                    <h1>Lab Management</h1>
                    <p>Manajemen Kelas</p>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <a href="dashboard.php" class="back-button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7" />
                    </svg>
                    Kembali ke Beranda
                </a>
                <div class="user-avatar"><?php echo $initial; ?></div>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">

        <!-- Alert Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span>
                    <?php
                    switch ($_GET['success']) {
                        case 'added':
                            echo 'Kelas berhasil ditambahkan!';
                            break;
                        case 'updated':
                            echo 'Kelas berhasil diperbarui!';
                            break;
                        case 'deleted':
                            echo 'Kelas berhasil dihapus!';
                            break;
                        case 'status_updated':
                            echo 'Status kelas berhasil diubah!';
                            break;
                    }
                    ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title-section">
                <div class="page-title">
                    <h2>Manajemen Kelas</h2>
                    <p>Kelola data kelas dan siswa yang menggunakan laboratorium</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Tambah Kelas
                    </button>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-label">Total Kelas</div>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                </div>
                <div class="stat-card green">
                    <div class="stat-label">Kelas Aktif</div>
                    <div class="stat-value"><?php echo $stats['active']; ?></div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-label">MA</div>
                    <div class="stat-value"><?php echo $stats['ma']; ?></div>
                </div>
                <div class="stat-card cyan">
                    <div class="stat-label">MTs</div>
                    <div class="stat-value"><?php echo $stats['mts']; ?></div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" action="classes.php">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Cari Kelas</label>
                        <input type="text" name="search" class="filter-input" placeholder="Nama kelas, tingkat, atau jurusan..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Tingkat</label>
                        <select name="grade" class="filter-select">
                            <option value="">Semua Tingkat</option>
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?php echo htmlspecialchars($grade); ?>" <?php echo $grade_filter === $grade ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Organisasi</label>
                        <select name="org" class="filter-select">
                            <option value="">Semua</option>
                            <?php foreach ($organizations as $org): ?>
                                <option value="<?php echo $org['id']; ?>" <?php echo $org_filter == $org['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($org['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" class="filter-select">
                            <option value="">Semua</option>
                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary" style="margin-top: 0;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="table-container">
            <div class="table-wrapper">
                <?php if (count($classes) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Kelas</th>
                                <th>Organisasi</th>
                                <th>Tingkat</th>
                                <th>Jurusan</th>
                                <th>Jumlah Siswa</th>
                                <th>Tahun Ajaran</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($class['name']); ?></strong></td>
                                    <td>
                                        <span class="badge <?php
                                                            // Badge color based on organization type
                                                            $orgType = $class['org_type'] ?? '';
                                                            if (in_array($orgType, ['MA', 'SMA'])) echo 'badge-primary';
                                                            elseif (in_array($orgType, ['MTS', 'SMP'])) echo 'badge-warning';
                                                            elseif ($orgType == 'SMK') echo 'badge-secondary';
                                                            elseif ($orgType == 'EXCUL') echo 'badge-purple';
                                                            else echo 'badge-primary';
                                                            ?>">
                                            <?php echo htmlspecialchars($class['org_name'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($class['grade_level'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($class['major'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($class['student_count']); ?></td>
                                    <td><?php echo htmlspecialchars($class['academic_year']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $class['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $class['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon edit" onclick='openEditModal(<?php echo json_encode($class); ?>)' title="Edit">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </button>
                                            <button class="btn-icon delete" onclick="confirmDelete(<?php echo $class['id']; ?>, '<?php echo htmlspecialchars($class['name']); ?>')" title="Hapus">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                            <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                        </svg>
                        <h3>Tidak ada kelas ditemukan</h3>
                        <p>Belum ada data kelas atau hasil pencarian tidak ditemukan</p>
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Tambah Kelas Pertama
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Add/Edit Modal -->
    <div class="modal" id="classModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah Kelas</h3>
                <button class="modal-close" onclick="closeModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <form method="POST" action="classes.php" id="classForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="classId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Kelas <span class="required">*</span></label>
                        <input type="text" name="name" id="className" class="form-control" required placeholder="Contoh: Kelas 8 A">
                    </div>
                    <div class="form-group">
                        <label>Organisasi <span class="required">*</span></label>
                        <select name="organization_id" id="organizationId" class="form-control" required>
                            <option value="">Pilih Organisasi</option>
                            <?php foreach ($organizations as $org): ?>
                                <option value="<?php echo $org['id']; ?>">
                                    <?php echo htmlspecialchars($org['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tingkat</label>
                        <select name="grade_level" id="gradeLevel" class="form-control">
                            <option value="">Pilih Tingkat</option>
                            <option value="VII">VII</option>
                            <option value="VIII">VIII</option>
                            <option value="IX">IX</option>
                            <option value="X">X</option>
                            <option value="XI">XI</option>
                            <option value="XII">XII</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jurusan</label>
                        <input type="text" name="major" id="major" class="form-control" placeholder="Contoh: IPA, IPS, atau kosongkan">
                    </div>
                    <div class="form-group">
                        <label>Jumlah Siswa</label>
                        <input type="number" name="student_count" id="studentCount" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>Tahun Ajaran</label>
                        <input type="text" name="academic_year" id="academicYear" class="form-control" placeholder="Contoh: 2026/2027" value="2026/2027">
                    </div>
                    <div class="form-group">
                        <label>Semester</label>
                        <select name="semester" id="semester" class="form-control">
                            <option value="">Pilih Semester</option>
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation -->
    <form method="POST" action="classes.php" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script>
        // Pass organizations data to JavaScript
        const organizations = <?php echo json_encode($organizations); ?>;

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Tambah Kelas';
            document.getElementById('formAction').value = 'add';
            document.getElementById('classForm').reset();
            document.getElementById('classId').value = '';
            document.getElementById('submitBtn').textContent = 'Simpan';
            document.getElementById('classModal').classList.add('active');
        }

        function openEditModal(classData) {
            document.getElementById('modalTitle').textContent = 'Edit Kelas';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('classId').value = classData.id;
            document.getElementById('className').value = classData.name;
            document.getElementById('organizationId').value = classData.organization_id;
            document.getElementById('gradeLevel').value = classData.grade_level || '';
            document.getElementById('major').value = classData.major || '';
            document.getElementById('studentCount').value = classData.student_count;
            document.getElementById('academicYear').value = classData.academic_year;
            document.getElementById('semester').value = classData.semester || '';
            document.getElementById('submitBtn').textContent = 'Perbarui';
            document.getElementById('classModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('classModal').classList.remove('active');
        }

        function confirmDelete(id, name) {
            if (confirm(`Apakah Anda yakin ingin menghapus kelas "${name}"?\n\nData yang dihapus tidak dapat dikembalikan.`)) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modal when clicking outside
        document.getElementById('classModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Keyboard shortcut to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>

</body>

</html>