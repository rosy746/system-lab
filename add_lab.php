<?php
// Set APP_ACCESS before including config
define('APP_ACCESS', true);

// Include configuration
require_once __DIR__ . '/app/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDB();
        
        // Validate input
        $name = sanitize($_POST['name'] ?? '');
        $type = sanitize($_POST['type'] ?? 'lab');
        $building = sanitize($_POST['building'] ?? '');
        $floor = !empty($_POST['floor']) ? (int)$_POST['floor'] : null;
        $room_number = sanitize($_POST['room_number'] ?? '');
        $capacity = !empty($_POST['capacity']) ? (int)$_POST['capacity'] : null;
        $organization_id = !empty($_POST['organization_id']) ? (int)$_POST['organization_id'] : null;
        $status = sanitize($_POST['status'] ?? 'active');
        
        // Validation
        if (empty($name)) {
            throw new Exception('Nama lab harus diisi');
        }
        
        if (empty($building)) {
            throw new Exception('Nama gedung harus diisi');
        }
        
        if ($capacity && $capacity < 1) {
            throw new Exception('Kapasitas harus lebih dari 0');
        }
        
        // Prepare metadata
        $metadata = [];
        if ($floor !== null) {
            $metadata['floor'] = $floor;
        }
        if (!empty($room_number)) {
            $metadata['room_number'] = $room_number;
        }
        
        $metadata_json = !empty($metadata) ? json_encode($metadata) : null;
        
        // Insert into database
        $sql = "INSERT INTO resources (name, type, building, floor, room_number, capacity, organization_id, status, metadata, created_at, updated_at) 
                VALUES (:name, :type, :building, :floor, :room_number, :capacity, :organization_id, :status, :metadata, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':type' => $type,
            ':building' => $building,
            ':floor' => $floor,
            ':room_number' => $room_number,
            ':capacity' => $capacity,
            ':organization_id' => $organization_id,
            ':status' => $status,
            ':metadata' => $metadata_json
        ]);
        
        $lab_id = $pdo->lastInsertId();
        
        // Log activity using the existing function
        logActivity(
            'CREATE',
            'resource',
            $lab_id,
            "Lab baru ditambahkan: {$name}",
            null,
            'System'
        );
        
        $success_message = "Lab '{$name}' berhasil ditambahkan dengan ID: {$lab_id}";
        
        // Clear form
        $_POST = [];
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get organizations for dropdown using existing function
$organizations = getActiveOrganizations();

// Get existing labs for reference
try {
    $pdo = getDB();
    $labs_stmt = $pdo->query("SELECT id, name, building, capacity, status FROM resources WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 5");
    $existing_labs = $labs_stmt->fetchAll();
} catch (Exception $e) {
    $existing_labs = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Lab Baru - <?php echo APP_NAME; ?></title>
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
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 0.95em;
        }
        
        .form-group label .required {
            color: #dc3545;
            margin-left: 3px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 0.85em;
        }
        
        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .recent-labs {
            margin-top: 20px;
        }
        
        .recent-labs h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        
        .lab-item {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .lab-item .lab-name {
            font-weight: 600;
            color: #333;
        }
        
        .lab-item .lab-details {
            color: #666;
            font-size: 0.9em;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .card {
                padding: 25px;
            }
            
            .header h1 {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔬 Tambah Lab Baru</h1>
            <p>Lab Management System - <?php echo APP_NAME; ?></p>
        </div>
        
        <div class="card">
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    ✓ <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    ✗ <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Nama Lab <span class="required">*</span></label>
                    <input type="text" id="name" name="name" placeholder="Contoh: Lab Komputer 9" 
                           value="<?php echo isset($_POST['name']) ? sanitize($_POST['name']) : ''; ?>" required>
                    <small>Nama yang jelas dan mudah diidentifikasi</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="type">Tipe Resource</label>
                        <select id="type" name="type">
                            <option value="lab" <?php echo (isset($_POST['type']) && $_POST['type'] === 'lab') || !isset($_POST['type']) ? 'selected' : ''; ?>>Lab</option>
                            <option value="classroom" <?php echo (isset($_POST['type']) && $_POST['type'] === 'classroom') ? 'selected' : ''; ?>>Ruang Kelas</option>
                            <option value="meeting_room" <?php echo (isset($_POST['type']) && $_POST['type'] === 'meeting_room') ? 'selected' : ''; ?>>Ruang Rapat</option>
                            <option value="workshop" <?php echo (isset($_POST['type']) && $_POST['type'] === 'workshop') ? 'selected' : ''; ?>>Workshop</option>
                            <option value="other" <?php echo (isset($_POST['type']) && $_POST['type'] === 'other') ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active" <?php echo (!isset($_POST['status']) || $_POST['status'] === 'active') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'selected' : ''; ?>>Tidak Aktif</option>
                            <option value="maintenance" <?php echo (isset($_POST['status']) && $_POST['status'] === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="building">Nama Gedung <span class="required">*</span></label>
                    <input type="text" id="building" name="building" placeholder="Contoh: Gedung SMA" 
                           value="<?php echo isset($_POST['building']) ? sanitize($_POST['building']) : ''; ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="floor">Lantai</label>
                        <input type="number" id="floor" name="floor" placeholder="Contoh: 2" min="1"
                               value="<?php echo isset($_POST['floor']) ? sanitize($_POST['floor']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="room_number">Nomor Ruangan</label>
                        <input type="text" id="room_number" name="room_number" placeholder="Contoh: 203"
                               value="<?php echo isset($_POST['room_number']) ? sanitize($_POST['room_number']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="capacity">Kapasitas</label>
                        <input type="number" id="capacity" name="capacity" placeholder="Contoh: 30" min="1"
                               value="<?php echo isset($_POST['capacity']) ? sanitize($_POST['capacity']) : ''; ?>">
                        <small>Jumlah maksimal orang yang dapat menggunakan</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="organization_id">Organisasi</label>
                        <select id="organization_id" name="organization_id">
                            <option value="">-- Pilih Organisasi (Opsional) --</option>
                            <?php foreach ($organizations as $org): ?>
                                <option value="<?php echo $org['id']; ?>" 
                                        <?php echo (isset($_POST['organization_id']) && $_POST['organization_id'] == $org['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($org['name'] . ' (' . $org['type'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">💾 Simpan Lab</button>
                </div>
            </form>
            
            <?php if (!empty($existing_labs)): ?>
                <div class="recent-labs">
                    <h3>📋 Lab Terakhir Ditambahkan</h3>
                    <?php foreach ($existing_labs as $lab): ?>
                        <div class="lab-item">
                            <div>
                                <div class="lab-name"><?php echo sanitize($lab['name']); ?></div>
                                <div class="lab-details">
                                    <?php echo sanitize($lab['building']); ?>
                                    <?php if ($lab['capacity']): ?>
                                        • Kapasitas: <?php echo $lab['capacity']; ?> orang
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="badge <?php echo $lab['status'] === 'active' ? 'badge-success' : 'badge-warning'; ?>">
                                <?php echo ucfirst($lab['status']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>