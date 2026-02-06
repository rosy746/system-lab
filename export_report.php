<?php
session_start();

// Define APP_ACCESS constant to allow config access
define('APP_ACCESS', true);

// Include config and functions
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/functions.php';

// Get database connection
$conn = getDB();

// Get parameters
$type = $_GET['type'] ?? 'overview';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'excel';

// Set headers based on format
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Laporan_' . $type . '_' . date('Ymd') . '.xls"');
} elseif ($format === 'pdf') {
    // For PDF, we'll use HTML to PDF conversion
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Laporan_' . $type . '_' . date('Ymd') . '.pdf"');
}

// Get data based on report type
$data = [];
$title = '';

try {
    switch ($type) {
        case 'overview':
            $title = 'Laporan Ringkasan Umum';
            // Get overview data
            $stmt = $conn->prepare("
                SELECT 
                    DATE(s.created_at) as tanggal,
                    COUNT(DISTINCT s.id) as total_jadwal,
                    COUNT(DISTINCT s.class_id) as total_kelas,
                    COUNT(DISTINCT s.user_id) as total_guru,
                    COUNT(DISTINCT s.resource_id) as total_lab
                FROM schedules s
                WHERE s.created_at BETWEEN ? AND ?
                AND s.deleted_at IS NULL
                GROUP BY DATE(s.created_at)
                ORDER BY tanggal
            ");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'by_organization':
            $title = 'Laporan Per Lembaga';
            $stmt = $conn->prepare("
                SELECT o.name as lembaga, o.type as tipe,
                       COUNT(DISTINCT s.id) as total_jadwal,
                       COUNT(DISTINCT s.class_id) as total_kelas,
                       COUNT(DISTINCT s.user_id) as total_guru
                FROM organizations o
                LEFT JOIN classes c ON o.id = c.organization_id
                LEFT JOIN schedules s ON c.id = s.class_id
                    AND s.created_at BETWEEN ? AND ?
                    AND s.deleted_at IS NULL
                WHERE o.deleted_at IS NULL
                GROUP BY o.id, o.name, o.type
                ORDER BY total_jadwal DESC
            ");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'by_class':
            $title = 'Laporan Per Kelas';
            $stmt = $conn->prepare("
                SELECT c.name as kelas, c.grade_level as tingkat, c.major as jurusan,
                       o.name as lembaga,
                       COUNT(s.id) as total_penggunaan,
                       COUNT(DISTINCT s.resource_id) as lab_digunakan,
                       COUNT(DISTINCT s.user_id) as jumlah_guru
                FROM classes c
                LEFT JOIN organizations o ON c.organization_id = o.id
                LEFT JOIN schedules s ON c.id = s.class_id
                    AND s.created_at BETWEEN ? AND ?
                    AND s.deleted_at IS NULL
                WHERE c.deleted_at IS NULL
                GROUP BY c.id, c.name, c.grade_level, c.major, o.name
                ORDER BY total_penggunaan DESC
            ");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'by_teacher':
            $title = 'Laporan Per Guru';
            $stmt = $conn->prepare("
                SELECT u.full_name as nama_guru, u.email,
                       COUNT(DISTINCT s.id) as total_jadwal,
                       COUNT(DISTINCT s.class_id) as total_kelas,
                       COUNT(DISTINCT s.resource_id) as total_lab,
                       COUNT(DISTINCT DATE(s.created_at)) as hari_aktif
                FROM users u
                LEFT JOIN schedules s ON u.id = s.user_id
                    AND s.created_at BETWEEN ? AND ?
                    AND s.deleted_at IS NULL
                WHERE u.deleted_at IS NULL
                GROUP BY u.id, u.full_name, u.email
                HAVING total_jadwal > 0
                ORDER BY total_jadwal DESC
            ");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'by_lab':
            $title = 'Laporan Per Laboratorium';
            $stmt = $conn->prepare("
                SELECT r.name as laboratorium, r.type as tipe, r.capacity as kapasitas,
                       COUNT(DISTINCT s.id) as total_penggunaan,
                       COUNT(DISTINCT s.class_id) as kelas_terlayani,
                       COUNT(DISTINCT s.user_id) as jumlah_guru,
                       COUNT(DISTINCT DATE(s.created_at)) as hari_digunakan
                FROM resources r
                LEFT JOIN schedules s ON r.id = s.resource_id
                    AND s.created_at BETWEEN ? AND ?
                    AND s.deleted_at IS NULL
                WHERE r.deleted_at IS NULL
                AND r.type IN ('lab_komputer', 'lab_ipa', 'lab')
                GROUP BY r.id, r.name, r.type, r.capacity
                ORDER BY total_penggunaan DESC
            ");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'by_booking':
            $title = 'Laporan Booking';
            $stmt = $conn->prepare("
                SELECT b.booking_code as kode_booking, b.title as judul,
                       b.booking_date as tanggal, b.status, b.purpose as tujuan,
                       b.participant_count as peserta,
                       r.name as laboratorium,
                       ts.name as slot_waktu,
                       u.full_name as pemohon
                FROM bookings b
                LEFT JOIN resources r ON b.resource_id = r.id
                LEFT JOIN time_slots ts ON b.time_slot_id = ts.id
                LEFT JOIN users u ON b.user_id = u.id
                WHERE b.booking_date BETWEEN ? AND ?
                AND b.deleted_at IS NULL
                ORDER BY b.booking_date DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'by_subject':
            $title = 'Laporan Per Mata Pelajaran';
            $stmt = $conn->prepare("
                SELECT s.subject as mata_pelajaran,
                       COUNT(s.id) as total_jadwal,
                       COUNT(DISTINCT s.class_id) as jumlah_kelas,
                       COUNT(DISTINCT s.user_id) as jumlah_guru,
                       COUNT(DISTINCT s.resource_id) as lab_digunakan
                FROM schedules s
                WHERE s.created_at BETWEEN ? AND ?
                AND s.deleted_at IS NULL
                AND s.subject IS NOT NULL
                AND s.subject != ''
                GROUP BY s.subject
                ORDER BY total_jadwal DESC
            ");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}

// Output based on format
if ($format === 'excel') {
    // Excel output
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #000; padding: 8px; text-align: left; }
            th { background-color: #4a8cff; color: white; font-weight: bold; }
            .header { margin-bottom: 20px; }
            .header h1 { color: #1e1e2e; }
            .header p { color: #5a5a6e; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?= $title ?></h1>
            <p>Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></p>
            <p>Dicetak: <?= date('d/m/Y H:i:s') ?></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <?php if (!empty($data)): ?>
                        <?php foreach (array_keys($data[0]) as $header): ?>
                            <th><?= ucwords(str_replace('_', ' ', $header)) ?></th>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <?php foreach ($row as $value): ?>
                            <td><?= htmlspecialchars($value ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 30px; color: #5a5a6e; font-size: 12px;">
            <p>Total Data: <?= count($data) ?> baris</p>
            <p>© <?= date('Y') ?> Lab Management System</p>
        </div>
    </body>
    </html>
    <?php
} elseif ($format === 'pdf') {
    // PDF output (using HTML to PDF via print)
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 2cm; }
            body { font-family: Arial, sans-serif; font-size: 12px; }
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 11px; }
            th { background-color: #4a8cff; color: white; font-weight: bold; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #4a8cff; padding-bottom: 10px; }
            .header h1 { color: #1e1e2e; margin: 0; }
            .header p { color: #5a5a6e; margin: 5px 0; }
            .footer { margin-top: 30px; text-align: center; color: #5a5a6e; font-size: 10px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>? <?= $title ?></h1>
            <p>Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></p>
            <p>Dicetak: <?= date('d/m/Y H:i:s') ?></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">No</th>
                    <?php if (!empty($data)): ?>
                        <?php foreach (array_keys($data[0]) as $header): ?>
                            <th><?= ucwords(str_replace('_', ' ', $header)) ?></th>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <?php foreach ($row as $value): ?>
                            <td><?= htmlspecialchars($value ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Total Data: <?= count($data) ?> baris</p>
            <p>© <?= date('Y') ?> Lab Management System - Laporan ini digenerate secara otomatis</p>
        </div>
        
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    </body>
    </html>
    <?php
}
?>