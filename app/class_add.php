<?php
/**
 * Halaman Tambah Kelas
 * File: class_add.php
 * Updated for new database schema
 */

// Include configuration
require_once 'config.php';

// Start session untuk autentikasi (optional, sesuaikan dengan sistem auth Anda)
session_start();

// Check if user is logged in (uncomment jika ada sistem auth)
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }

$db = getDB();
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $code = strtoupper(sanitize($_POST['code']));
        $name = sanitize($_POST['name']);
        $organization_id = (int)$_POST['organization_id'];
        $grade_level = sanitize($_POST['grade_level'] ?? '');
        $major = sanitize($_POST['major'] ?? '');
        $academic_year = sanitize($_POST['academic_year'] ?? '');
        $semester = sanitize($_POST['semester'] ?? '');
        
        // Validate required fields
        if (empty($code) || empty($name) || empty($organization_id)) {
            throw new Exception("Semua field wajib diisi!");
        }
        
        // Check if class code already exists for this organization
        $checkSql = "SELECT * FROM classes 
                     WHERE code = :code 
                     AND organization_id = :organization_id
                     AND deleted_at IS NULL";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([
            ':code' => $code,
            ':organization_id' => $organization_id
        ]);
        
        if ($checkStmt->rowCount() > 0) {
            throw new Exception("Kode kelas sudah digunakan untuk institusi ini!");
        }
        
        // Insert class
        $sql = "INSERT INTO classes (
                    code, name, organization_id, grade_level, major, 
                    academic_year, semester, is_active
                ) VALUES (
                    :code, :name, :organization_id, :grade_level, :major,
                    :academic_year, :semester, 1
                )";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':code' => $code,
            ':name' => $name,
            ':organization_id' => $organization_id,
            ':grade_level' => $grade_level,
            ':major' => $major,
            ':academic_year' => $academic_year,
            ':semester' => $semester
        ]);
        
        $classId = $db->lastInsertId();
        
        // Log activity
        logActivity(
            $_SESSION['user_id'] ?? null,
            $_SESSION['user_name'] ?? 'System',
            'CREATE',
            'class',
            $classId,
            "Kelas baru ditambahkan: {$code} - {$name}"
        );
        
        $message = "Kelas berhasil ditambahkan!";
        $messageType = "success";
        
        // Reset form
        $_POST = array();
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Fetch organizations for dropdown
try {
    $organizationsStmt = $db->query("
        SELECT * FROM organizations 
        WHERE is_active = 1 
        AND deleted_at IS NULL 
        ORDER BY name
    ");
    $organizations = $organizationsStmt->fetchAll();
    
    // Get existing classes with organization info
    $classesStmt = $db->query("
        SELECT c.*, o.name as organization_name, o.type as organization_type
        FROM classes c 
        JOIN organizations o ON c.organization_id = o.id 
        WHERE c.deleted_at IS NULL
        ORDER BY o.name, c.grade_level, c.name
    ");
    $existingClasses = $classesStmt->fetchAll();
    
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Get current academic year
$currentYear = date('Y');
$nextYear = $currentYear + 1;
$defaultAcademicYear = $currentYear . '/' . $nextYear;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kelas - <?= APP_NAME ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .form-container {
            padding: 40px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group label .required {
            color: #dc3545;
            margin-left: 3px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group select {
            cursor: pointer;
            background: white;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6c757d;
            font-size: 12px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .btn-container {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            flex: 1;
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            color: #0c5460;
        }
        
        .class-examples {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .class-examples h4 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .example-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .example-item {
            background: white;
            padding: 10px 15px;
            border-radius: 6px;
            border-left: 3px solid #667eea;
            font-size: 13px;
        }
        
        .example-item strong {
            color: #667eea;
        }
        
        .class-list-section {
            padding: 40px;
        }
        
        .class-list-section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }
        
        .filter-group {
            margin-bottom: 20px;
        }
        
        .filter-group select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            min-width: 250px;
        }
        
        .class-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .class-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .class-card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .class-code {
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .class-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .class-info {
            color: #6c757d;
            font-size: 13px;
            margin-top: 10px;
        }
        
        .class-info-item {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 5px;
        }
        
        .class-institution {
            color: #6c757d;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .institution-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #e9ecef;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            color: #495057;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-right: 5px;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .form-row,
            .form-row-3 {
                grid-template-columns: 1fr;
            }
            
            .form-container,
            .class-list-section {
                padding: 25px;
            }
            
            .btn-container {
                flex-direction: column;
            }
            
            .class-grid {
                grid-template-columns: 1fr;
            }
            
            .example-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Form Card -->
        <div class="card">
            <div class="header">
                <h1>🎓 Tambah Kelas</h1>
                <p>Tambahkan kelas baru untuk institusi</p>
            </div>
            
            <div class="form-container">
                <?php if ($message): ?>
                    <div class="alert <?= $messageType ?>">
                        <span><?= $message ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="info-box">
                    ℹ️ <strong>Petunjuk:</strong> Kode kelas harus unik untuk setiap institusi. Gunakan format yang konsisten untuk memudahkan pengelolaan.
                </div>
                
                <form method="POST" action="" id="classForm">
                    <div class="form-group">
                        <label for="organization_id">
                            Institusi <span class="required">*</span>
                        </label>
                        <select name="organization_id" id="organization_id" required>
                            <option value="">Pilih Institusi</option>
                            <?php foreach ($organizations as $organization): ?>
                                <option value="<?= $organization['id'] ?>" 
                                        data-type="<?= $organization['type'] ?>"
                                        <?= (isset($_POST['organization_id']) && $_POST['organization_id'] == $organization['id']) ? 'selected' : '' ?>>
                                    <?= $organization['name'] ?> <?= $organization['type'] ? '(' . $organization['type'] . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="code">
                                Kode Kelas <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   name="code" 
                                   id="code" 
                                   placeholder="Contoh: X-IPA-1"
                                   value="<?= $_POST['code'] ?? '' ?>"
                                   required
                                   style="text-transform: uppercase;">
                            <small>Gunakan format: X-IPA-1, XI-IPS-2, XII-A-3</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="name">
                                Nama Kelas <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   name="name" 
                                   id="name" 
                                   placeholder="Contoh: Kelas X IPA 1"
                                   value="<?= $_POST['name'] ?? '' ?>"
                                   required>
                            <small>Nama lengkap kelas yang mudah dibaca</small>
                        </div>
                    </div>
                    
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="grade_level">
                                Tingkat Kelas
                            </label>
                            <select name="grade_level" id="grade_level">
                                <option value="">Pilih Tingkat</option>
                                <option value="X">X (10)</option>
                                <option value="XI">XI (11)</option>
                                <option value="XII">XII (12)</option>
                                <option value="VII">VII (7)</option>
                                <option value="VIII">VIII (8)</option>
                                <option value="IX">IX (9)</option>
                            </select>
                            <small>Tingkat / level kelas</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="major">
                                Jurusan / Peminatan
                            </label>
                            <input type="text" 
                                   name="major" 
                                   id="major" 
                                   placeholder="Contoh: IPA, TKJ, RPL"
                                   value="<?= $_POST['major'] ?? '' ?>">
                            <small>Jurusan atau peminatan (opsional)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="semester">
                                Semester
                            </label>
                            <select name="semester" id="semester">
                                <option value="">Pilih Semester</option>
                                <option value="1">Semester 1 (Ganjil)</option>
                                <option value="2">Semester 2 (Genap)</option>
                            </select>
                            <small>Semester saat ini (opsional)</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="academic_year">
                            Tahun Ajaran
                        </label>
                        <input type="text" 
                               name="academic_year" 
                               id="academic_year" 
                               placeholder="Contoh: <?= $defaultAcademicYear ?>"
                               value="<?= $_POST['academic_year'] ?? $defaultAcademicYear ?>">
                        <small>Format: YYYY/YYYY (contoh: <?= $defaultAcademicYear ?>)</small>
                    </div>
                    
                    <div class="class-examples">
                        <h4>💡 Contoh Format Kelas:</h4>
                        <div class="example-grid">
                            <div class="example-item">
                                <strong>SMA/MA:</strong> X-IPA-1, XI-IPS-2, XII-A-3
                            </div>
                            <div class="example-item">
                                <strong>SMK:</strong> X-TKJ-1, XI-RPL-2, XII-MM-1
                            </div>
                            <div class="example-item">
                                <strong>SMP/MTS:</strong> VII-A, VIII-B, IX-C
                            </div>
                        </div>
                    </div>
                    
                    <div class="btn-container">
                        <button type="submit" class="btn btn-primary">
                            ✓ Simpan Kelas
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            ↺ Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Class List Card -->
        <div class="card">
            <div class="class-list-section">
                <h2>📋 Daftar Kelas Terdaftar</h2>
                
                <div class="filter-group">
                    <select id="filterOrganization">
                        <option value="">Semua Institusi</option>
                        <?php foreach ($organizations as $organization): ?>
                            <option value="<?= $organization['id'] ?>">
                                <?= $organization['name'] ?> <?= $organization['type'] ? '(' . $organization['type'] . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="class-grid" id="classList">
                    <?php if (empty($existingClasses)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📚</div>
                            <h3>Belum Ada Kelas</h3>
                            <p>Silakan tambahkan kelas pertama menggunakan form di atas</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($existingClasses as $class): ?>
                            <div class="class-card" data-organization="<?= $class['organization_id'] ?>">
                                <div class="class-card-header">
                                    <div>
                                        <div class="class-name"><?= $class['name'] ?></div>
                                        <div class="class-institution">
                                            📍 <?= $class['organization_name'] ?>
                                            <?php if ($class['organization_type']): ?>
                                                <span class="institution-badge"><?= $class['organization_type'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="class-code"><?= $class['code'] ?></div>
                                </div>
                                
                                <div class="class-info">
                                    <?php if ($class['grade_level']): ?>
                                        <div class="class-info-item">
                                            <span class="badge badge-info">Tingkat: <?= $class['grade_level'] ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($class['major']): ?>
                                        <div class="class-info-item">
                                            <span class="badge badge-info">Jurusan: <?= $class['major'] ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($class['academic_year']): ?>
                                        <div class="class-info-item">
                                            📅 Tahun Ajaran: <?= $class['academic_year'] ?>
                                            <?php if ($class['semester']): ?>
                                                - Semester <?= $class['semester'] ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($class['student_count'] > 0): ?>
                                        <div class="class-info-item">
                                            👥 Jumlah Siswa: <?= $class['student_count'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-generate class name from code
        document.getElementById('code').addEventListener('input', function(e) {
            const code = e.target.value.toUpperCase();
            const nameInput = document.getElementById('name');
            
            // Only auto-fill if name is empty
            if (!nameInput.value || nameInput.dataset.autoFilled === 'true') {
                // Convert code to readable name
                // X-IPA-1 -> Kelas X IPA 1
                const readable = code.replace(/-/g, ' ');
                nameInput.value = 'Kelas ' + readable;
                nameInput.dataset.autoFilled = 'true';
            }
        });
        
        // Remove auto-fill flag when user manually edits name
        document.getElementById('name').addEventListener('input', function(e) {
            if (e.target.value !== '') {
                e.target.dataset.autoFilled = 'false';
            }
        });
        
        // Auto-extract grade level from code
        document.getElementById('code').addEventListener('input', function(e) {
            const code = e.target.value.toUpperCase();
            const gradeSelect = document.getElementById('grade_level');
            const majorInput = document.getElementById('major');
            
            // Extract grade level (X, XI, XII, VII, VIII, IX)
            const gradeMatch = code.match(/^(X{1,2}I{0,2}|I{1,3}X?|V{0,1}I{1,3})/);
            if (gradeMatch && !gradeSelect.value) {
                gradeSelect.value = gradeMatch[1];
            }
            
            // Extract major (e.g., IPA, IPS, TKJ, RPL)
            const majorMatch = code.match(/-(IPA|IPS|TKJ|RPL|MM|OTKP|AKL|BDP|[A-Z]{2,4})-/);
            if (majorMatch && !majorInput.value) {
                majorInput.value = majorMatch[1];
            }
        });
        
        // Filter classes by organization
        document.getElementById('filterOrganization').addEventListener('change', function() {
            const organizationId = this.value;
            const classCards = document.querySelectorAll('.class-card');
            
            classCards.forEach(card => {
                if (!organizationId || card.dataset.organization === organizationId) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Form validation
        document.getElementById('classForm').addEventListener('submit', function(e) {
            const code = document.getElementById('code').value.trim();
            const name = document.getElementById('name').value.trim();
            const organization = document.getElementById('organization_id').value;
            
            if (!code || !name || !organization) {
                e.preventDefault();
                alert('Mohon lengkapi semua field yang wajib diisi!');
                return false;
            }
            
            // Validate code format (alphanumeric and dash only)
            if (!/^[A-Z0-9-]+$/.test(code)) {
                e.preventDefault();
                alert('Kode kelas hanya boleh mengandung huruf, angka, dan tanda hubung (-)');
                return false;
            }
            
            // Validate academic year format (optional)
            const academicYear = document.getElementById('academic_year').value.trim();
            if (academicYear && !/^\d{4}\/\d{4}$/.test(academicYear)) {
                e.preventDefault();
                alert('Format tahun ajaran harus YYYY/YYYY (contoh: 2024/2025)');
                return false;
            }
        });
        
        // Convert code input to uppercase
        document.getElementById('code').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        // Auto-format academic year input
        document.getElementById('academic_year').addEventListener('blur', function(e) {
            const value = e.target.value.trim();
            if (value && !value.includes('/')) {
                // If only year is entered, auto-format to YYYY/YYYY+1
                if (/^\d{4}$/.test(value)) {
                    const year = parseInt(value);
                    e.target.value = year + '/' + (year + 1);
                }
            }
        });
    </script>
</body>
</html>