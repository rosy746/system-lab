<?php
/**
 * Schedule Add Page
 * Halaman untuk menambah jadwal rutin laboratorium
 */
define('APP_ACCESS', true);
require_once 'config.php';

// Initialize variables
$errors = [];
$success = '';
$formData = [
    'resource_id' => '',
    'time_slot_id' => '',
    'class_id' => '',
    'organization_id' => '',
    'day_of_week' => '',
    'teacher_name' => '',
    'subject_name' => '',
    'notes' => '',
    'academic_year' => '',
    'semester' => '',
    'start_date' => '',
    'end_date' => ''
];

// Get data for form dropdowns
$labs = getActiveLabs();
$timeSlots = getActiveTimeSlots();
$organizations = getActiveOrganizations();
$classes = getClassesByOrganization();

// Days of week - HARUS dalam format English karena database menggunakan format English
$daysOfWeek = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get and sanitize input
        $formData = [
            'resource_id' => sanitize($_POST['resource_id'] ?? ''),
            'time_slot_id' => sanitize($_POST['time_slot_id'] ?? ''),
            'class_id' => sanitize($_POST['class_id'] ?? ''),
            'organization_id' => sanitize($_POST['organization_id'] ?? ''),
            'day_of_week' => sanitize($_POST['day_of_week'] ?? ''),
            'teacher_name' => sanitize($_POST['teacher_name'] ?? ''),
            'subject_name' => sanitize($_POST['subject_name'] ?? ''),
            'notes' => sanitize($_POST['notes'] ?? ''),
            'academic_year' => sanitize($_POST['academic_year'] ?? ''),
            'semester' => sanitize($_POST['semester'] ?? ''),
            'start_date' => sanitize($_POST['start_date'] ?? ''),
            'end_date' => sanitize($_POST['end_date'] ?? '')
        ];

        // Validation
        if (empty($formData['resource_id'])) {
            $errors[] = 'Laboratorium harus dipilih';
        }
        if (empty($formData['time_slot_id'])) {
            $errors[] = 'Slot waktu harus dipilih';
        }
        if (empty($formData['class_id'])) {
            $errors[] = 'Kelas harus dipilih';
        }
        if (empty($formData['day_of_week'])) {
            $errors[] = 'Hari harus dipilih';
        }
        if (empty($formData['teacher_name'])) {
            $errors[] = 'Nama guru harus diisi';
        }
        if (empty($formData['subject_name'])) {
            $errors[] = 'Nama mata pelajaran harus diisi';
        }

        // Validate date range if provided
        if (!empty($formData['start_date']) && !empty($formData['end_date'])) {
            if (!validateDateRange($formData['start_date'], $formData['end_date'])) {
                $errors[] = 'Tanggal selesai harus lebih besar dari tanggal mulai';
            }
        }

        // Check for conflicts
        if (empty($errors)) {
            $conflict = checkScheduleConflict(
                $formData['resource_id'],
                $formData['time_slot_id'],
                $formData['day_of_week']
            );

            if ($conflict) {
                $errors[] = sprintf(
                    'Jadwal bentrok dengan: %s - %s pada %s',
                    $conflict['class_name'],
                    $conflict['teacher_name'],
                    $conflict['time_slot_name']
                );
            }
        }

        // If no errors, insert to database
        if (empty($errors)) {
            $pdo = getDB();
            $pdo->beginTransaction();

            try {
                // Insert schedule (TANPA kolom 'code' karena tidak ada di database)
                $sql = "INSERT INTO schedules (
                    resource_id, time_slot_id, class_id,
                    day_of_week, teacher_name, subject_name, notes,
                    academic_year, semester, start_date, end_date,
                    status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $formData['resource_id'],
                    $formData['time_slot_id'],
                    $formData['class_id'],
                    $formData['day_of_week'],
                    $formData['teacher_name'],
                    $formData['subject_name'],
                    $formData['notes'],
                    $formData['academic_year'] ?: null,
                    $formData['semester'] ?: null,
                    $formData['start_date'] ?: null,
                    $formData['end_date'] ?: null
                ]);

                $scheduleId = $pdo->lastInsertId();

                // Log activity
                logActivity(
                    null,
                    'System',
                    'create',
                    'schedule',
                    $scheduleId,
                    "Jadwal baru ditambahkan: {$formData['teacher_name']} - {$formData['subject_name']}"
                );

                $pdo->commit();

                $success = "Jadwal berhasil ditambahkan! ID: {$scheduleId}";
                
                // Reset form
                $formData = [
                    'resource_id' => '',
                    'time_slot_id' => '',
                    'class_id' => '',
                    'organization_id' => '',
                    'day_of_week' => '',
                    'teacher_name' => '',
                    'subject_name' => '',
                    'notes' => '',
                    'academic_year' => '',
                    'semester' => '',
                    'start_date' => '',
                    'end_date' => ''
                ];

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Gagal menyimpan jadwal: ' . $e->getMessage();
            }
        }

    } catch (Exception $e) {
        $errors[] = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Jadwal - <?php echo APP_NAME; ?></title>
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
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .card-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .card-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .card-body {
            padding: 40px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-danger {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border-left: 4px solid #3c3;
        }

        .alert ul {
            list-style-position: inside;
            margin-top: 8px;
        }

        .form-grid {
            display: grid;
            gap: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        label .required {
            color: #e74c3c;
            margin-left: 2px;
        }

        input[type="text"],
        input[type="date"],
        select,
        textarea {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
        }

        input[type="text"]:focus,
        input[type="date"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        select {
            cursor: pointer;
            background-color: white;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex: 1;
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

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-title:first-child {
            margin-top: 0;
        }

        .helper-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .card-body {
                padding: 25px;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }

        .back-link {
            display: inline-block;
            color: white;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
            opacity: 0.9;
            transition: opacity 0.3s;
        }

        .back-link:hover {
            opacity: 1;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-box strong {
            color: #1976D2;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Kembali ke Dashboard</a>
        
        <div class="card">
            <div class="card-header">
                <h1>📅 Tambah Jadwal Rutin</h1>
                <p>Tambahkan jadwal rutin penggunaan laboratorium</p>
            </div>

            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <strong>❌ Terjadi kesalahan:</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <strong>✅ Berhasil!</strong><br>
                        <?php echo htmlspecialchars($success); ?>
                        <br><br>
                        <a href="index.php" style="color: #3c3; text-decoration: underline;">Lihat di Dashboard</a>
                    </div>
                <?php endif; ?>

                <div class="info-box">
                    <strong>ℹ️ Catatan:</strong> Jadwal rutin akan otomatis tampil di tabel jadwal setiap minggu sesuai hari yang dipilih.
                </div>

                <form method="POST" action="">
                    <div class="form-grid">
                        <!-- Schedule Information -->
                        <div class="section-title">Informasi Jadwal</div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="day_of_week">
                                    Hari <span class="required">*</span>
                                </label>
                                <select name="day_of_week" id="day_of_week" required>
                                    <option value="">-- Pilih Hari --</option>
                                    <?php foreach ($daysOfWeek as $engDay => $indoDay): ?>
                                        <option value="<?php echo $engDay; ?>" 
                                            <?php echo ($formData['day_of_week'] === $engDay) ? 'selected' : ''; ?>>
                                            <?php echo $indoDay; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="time_slot_id">
                                    Slot Waktu <span class="required">*</span>
                                </label>
                                <select name="time_slot_id" id="time_slot_id" required>
                                    <option value="">-- Pilih Slot Waktu --</option>
                                    <?php foreach ($timeSlots as $slot): ?>
                                        <?php if ($slot['is_break'] != 1): ?>
                                            <option value="<?php echo $slot['id']; ?>"
                                                <?php echo ($formData['time_slot_id'] == $slot['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($slot['name']); ?> 
                                                (<?php echo substr($slot['start_time'], 0, 5); ?> - <?php echo substr($slot['end_time'], 0, 5); ?>)
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="resource_id">
                                Laboratorium <span class="required">*</span>
                            </label>
                            <select name="resource_id" id="resource_id" required>
                                <option value="">-- Pilih Laboratorium --</option>
                                <?php foreach ($labs as $lab): ?>
                                    <option value="<?php echo $lab['id']; ?>"
                                        <?php echo ($formData['resource_id'] == $lab['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lab['name']); ?> 
                                        (Kapasitas: <?php echo $lab['capacity']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Class and Teacher Information -->
                        <div class="section-title">Informasi Kelas & Pengajar</div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="organization_id">Institusi</label>
                                <select name="organization_id" id="organization_id">
                                    <option value="">-- Semua Institusi --</option>
                                    <?php foreach ($organizations as $org): ?>
                                        <option value="<?php echo $org['id']; ?>"
                                            <?php echo ($formData['organization_id'] == $org['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($org['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="helper-text">Filter untuk memudahkan pemilihan kelas</span>
                            </div>

                            <div class="form-group">
                                <label for="class_id">
                                    Kelas <span class="required">*</span>
                                </label>
                                <select name="class_id" id="class_id" required>
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>"
                                            data-org="<?php echo $class['organization_id']; ?>"
                                            <?php echo ($formData['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['name']); ?> 
                                            (<?php echo htmlspecialchars($class['organization_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="teacher_name">
                                    Nama Guru <span class="required">*</span>
                                </label>
                                <input type="text" name="teacher_name" id="teacher_name" 
                                    value="<?php echo htmlspecialchars($formData['teacher_name']); ?>" 
                                    placeholder="Contoh: Budi Santoso, S.Pd" required>
                            </div>

                            <div class="form-group">
                                <label for="subject_name">
                                    Mata Pelajaran <span class="required">*</span>
                                </label>
                                <input type="text" name="subject_name" id="subject_name" 
                                    value="<?php echo htmlspecialchars($formData['subject_name']); ?>" 
                                    placeholder="Contoh: Pemrograman Web" required>
                            </div>
                        </div>

                        <!-- Academic Information -->
                        <div class="section-title">Informasi Akademik (Opsional)</div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="academic_year">Tahun Ajaran</label>
                                <input type="text" name="academic_year" id="academic_year" 
                                    value="<?php echo htmlspecialchars($formData['academic_year']); ?>" 
                                    placeholder="Contoh: 2025/2026">
                            </div>

                            <div class="form-group">
                                <label for="semester">Semester</label>
                                <select name="semester" id="semester">
                                    <option value="">-- Pilih Semester --</option>
                                    <option value="1" <?php echo ($formData['semester'] === '1') ? 'selected' : ''; ?>>Semester 1 (Ganjil)</option>
                                    <option value="2" <?php echo ($formData['semester'] === '2') ? 'selected' : ''; ?>>Semester 2 (Genap)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_date">Tanggal Mulai</label>
                                <input type="date" name="start_date" id="start_date" 
                                    value="<?php echo htmlspecialchars($formData['start_date']); ?>">
                                <span class="helper-text">Tanggal mulai berlaku jadwal</span>
                            </div>

                            <div class="form-group">
                                <label for="end_date">Tanggal Selesai</label>
                                <input type="date" name="end_date" id="end_date" 
                                    value="<?php echo htmlspecialchars($formData['end_date']); ?>">
                                <span class="helper-text">Tanggal berakhir jadwal</span>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="form-group full-width">
                            <label for="notes">Catatan</label>
                            <textarea name="notes" id="notes" 
                                placeholder="Catatan tambahan tentang jadwal ini..."><?php echo htmlspecialchars($formData['notes']); ?></textarea>
                        </div>

                        <!-- Buttons -->
                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">
                                💾 Simpan Jadwal
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                ❌ Batal
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Filter classes by organization
        document.getElementById('organization_id').addEventListener('change', function() {
            const orgId = this.value;
            const classSelect = document.getElementById('class_id');
            const options = classSelect.querySelectorAll('option');

            options.forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                    return;
                }

                const optionOrg = option.getAttribute('data-org');
                if (!orgId || optionOrg === orgId) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });

            // Reset class selection if current selection is now hidden
            const currentOption = classSelect.querySelector('option:checked');
            if (currentOption && currentOption.style.display === 'none') {
                classSelect.value = '';
            }
        });

        // Validate date range
        document.getElementById('start_date').addEventListener('change', validateDates);
        document.getElementById('end_date').addEventListener('change', validateDates);

        function validateDates() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;

            if (startDate && endDate && startDate > endDate) {
                alert('Tanggal selesai harus lebih besar dari tanggal mulai');
                document.getElementById('end_date').value = '';
            }
        }

        // Auto-hide success message after 5 seconds
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.transition = 'opacity 0.5s';
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.remove(), 500);
            }, 5000);
        }
    </script>
</body>
</html>