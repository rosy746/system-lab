<?php
define('APP_ACCESS', true);
require_once __DIR__ . '/app/config.php';

// Get filter parameters (same as inventory_list.php)
$lab_filter = $_GET['lab'] ?? 'all';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$condition_filter = $_GET['condition'] ?? '';
$search = $_GET['search'] ?? '';
$export_format = $_GET['format'] ?? 'xlsx'; // xlsx, csv, pdf, docx

try {
    // Get database connection
    $db = getDB();
    
    // Build query (same as inventory_list.php)
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

    // Add filters
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

    // Execute query
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $inventories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get lab name for filename
    $lab_name = 'Semua Lab';
    if (!empty($lab_filter) && $lab_filter !== 'all') {
        $lab_stmt = $db->prepare("SELECT name FROM resources WHERE id = :id");
        $lab_stmt->execute([':id' => $lab_filter]);
        $lab_result = $lab_stmt->fetch(PDO::FETCH_ASSOC);
        if ($lab_result) {
            $lab_name = $lab_result['name'];
        }
    }

    // Generate filename
    $filename = 'Inventaris_' . str_replace(' ', '_', $lab_name) . '_' . date('Y-m-d_His');

    // Export based on format
    if ($export_format === 'csv') {
        exportToCSV($inventories, $filename);
    } else if ($export_format === 'pdf') {
        exportToPDF($inventories, $filename, $lab_name);
    } else if ($export_format === 'docx') {
        exportToWord($inventories, $filename, $lab_name);
    } else {
        exportToExcel($inventories, $filename, $lab_name);
    }

} catch (PDOException $e) {
    error_log("Database error in inventory_export.php: " . $e->getMessage());
    die("Error: Tidak dapat mengambil data. Silakan coba lagi.");
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    die("Error: " . $e->getMessage());
}

/**
 * Export to Word (.docx) format
 */
function exportToWord($data, $filename, $lab_name) {
    // Prepare data for Node.js script
    $exportData = [
        'inventories' => $data,
        'labName' => $lab_name
    ];
    
    $jsonData = json_encode($exportData, JSON_UNESCAPED_UNICODE);
    $scriptPath = __DIR__ . '/generate_word.js';
    
    // Check if Node.js is available
    $nodeCheck = shell_exec('which node 2>&1');
    if (empty($nodeCheck)) {
        // Fallback to HTML if Node.js not available
        error_log("Node.js not available, falling back to HTML print");
        exportToHTMLPrint($data, $filename, $lab_name, true); // true = with signature
        return;
    }
    
    // Check if script exists
    if (!file_exists($scriptPath)) {
        error_log("generate_word.js not found at: " . $scriptPath);
        exportToHTMLPrint($data, $filename, $lab_name, true);
        return;
    }
    
    // Check if docx package is installed
    $packageCheck = shell_exec('npm list -g docx 2>&1');
    if (strpos($packageCheck, 'empty') !== false || strpos($packageCheck, '(empty)') !== false) {
        error_log("docx npm package not installed");
        exportToHTMLPrint($data, $filename, $lab_name, true);
        return;
    }
    
    // Generate Word document using Node.js
    $tempFile = tempnam(sys_get_temp_dir(), 'word_');
    $escapedJson = escapeshellarg($jsonData);
    $command = "node " . escapeshellarg($scriptPath) . " $escapedJson > " . escapeshellarg($tempFile) . " 2>&1";
    
    exec($command, $output, $return_code);
    
    if ($return_code !== 0 || !file_exists($tempFile) || filesize($tempFile) === 0) {
        error_log("Failed to generate Word document. Return code: $return_code");
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        exportToHTMLPrint($data, $filename, $lab_name, true);
        return;
    }
    
    // Send file to browser
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '.docx"');
    header('Cache-Control: max-age=0');
    header('Content-Length: ' . filesize($tempFile));
    
    readfile($tempFile);
    unlink($tempFile);
    exit;
}

/**
 * Export to Excel using PhpSpreadsheet
 */
function exportToExcel($data, $filename, $lab_name) {
    // Load autoload
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        // If vendor not available, fallback to CSV
        exportToCSV($data, $filename);
        return;
    }
    
    require_once $autoload;
    
    // Check if PhpSpreadsheet is available
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // If not available, fallback to CSV
        exportToCSV($data, $filename);
        return;
    }

    // Use fully qualified class names instead of use statements inside function
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator(APP_NAME)
        ->setTitle('Inventaris Lab Komputer')
        ->setSubject('Laporan Inventaris')
        ->setDescription('Laporan inventaris ' . $lab_name);

    // Title
    $sheet->mergeCells('A1:M1');
    $sheet->setCellValue('A1', 'LAPORAN INVENTARIS LAB KOMPUTER');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->mergeCells('A2:M2');
    $sheet->setCellValue('A2', $lab_name);
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->mergeCells('A3:M3');
    $sheet->setCellValue('A3', 'Tanggal: ' . date('d/m/Y H:i:s'));
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    // Headers - updated to match actual database columns
    $headers = [
        'Kode Item',
        'Nama Item',
        'Lab',
        'Kategori',
        'Brand',
        'Model',
        'Serial Number',
        'Spesifikasi',
        'Total Unit',
        'Unit Baik',
        'Unit Rusak',
        'Unit Cadangan',
        'Kondisi',
        'Status',
        'Catatan'
    ];
    
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '5', $header);
        $col++;
    }
    
    // Style header
    $sheet->getStyle('A5:O5')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '667eea']
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]
        ],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);

    // Data
    $row = 6;
    foreach ($data as $item) {
        $sheet->setCellValue('A' . $row, $item['item_code']);
        $sheet->setCellValue('B' . $row, $item['item_name']);
        $sheet->setCellValue('C' . $row, $item['lab_name']);
        $sheet->setCellValue('D' . $row, ucfirst($item['category']));
        $sheet->setCellValue('E' . $row, $item['brand'] ?? '-');
        $sheet->setCellValue('F' . $row, $item['model'] ?? '-');
        $sheet->setCellValue('G' . $row, $item['serial_number'] ?? '-');
        $sheet->setCellValue('H' . $row, $item['specifications'] ?? '-');
        $sheet->setCellValue('I' . $row, $item['quantity']);
        $sheet->setCellValue('J' . $row, $item['quantity_good']);
        $sheet->setCellValue('K' . $row, $item['quantity_broken']);
        $sheet->setCellValue('L' . $row, $item['quantity_backup']);
        $sheet->setCellValue('M' . $row, ucfirst($item['condition']));
        $sheet->setCellValue('N' . $row, ucfirst($item['status']));
        $sheet->setCellValue('O' . $row, $item['notes'] ?? '-');
        
        $row++;
    }

    // Auto-size columns
    foreach (range('A', 'O') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Borders for data
    $sheet->getStyle('A5:O' . ($row - 1))->applyFromArray([
        'borders' => [
            'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]
        ]
    ]);

    // Summary at bottom
    $row += 2;
    $total_items = count($data);
    $total_quantity = array_sum(array_column($data, 'quantity'));
    $total_good = array_sum(array_column($data, 'quantity_good'));
    $total_broken = array_sum(array_column($data, 'quantity_broken'));
    $total_backup = array_sum(array_column($data, 'quantity_backup'));
    
    $sheet->setCellValue('A' . $row, 'RINGKASAN:');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Total Item:');
    $sheet->setCellValue('B' . $row, $total_items);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Total Unit:');
    $sheet->setCellValue('B' . $row, $total_quantity);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Unit Baik:');
    $sheet->setCellValue('B' . $row, $total_good);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Unit Rusak:');
    $sheet->setCellValue('B' . $row, $total_broken);
    $row++;
    
    $sheet->setCellValue('A' . $row, 'Unit Cadangan:');
    $sheet->setCellValue('B' . $row, $total_backup);
    
    $sheet->getStyle('A' . ($row - 5) . ':B' . $row)->getFont()->setBold(true);

    // Output
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Export to CSV
 */
function exportToCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers - updated to match actual database columns
    fputcsv($output, [
        'Kode Item',
        'Nama Item',
        'Lab',
        'Kategori',
        'Brand',
        'Model',
        'Serial Number',
        'Spesifikasi',
        'Total Unit',
        'Unit Baik',
        'Unit Rusak',
        'Unit Cadangan',
        'Kondisi',
        'Status',
        'Catatan'
    ]);

    // Data
    foreach ($data as $item) {
        fputcsv($output, [
            $item['item_code'],
            $item['item_name'],
            $item['lab_name'],
            ucfirst($item['category']),
            $item['brand'] ?? '-',
            $item['model'] ?? '-',
            $item['serial_number'] ?? '-',
            $item['specifications'] ?? '-',
            $item['quantity'],
            $item['quantity_good'],
            $item['quantity_broken'],
            $item['quantity_backup'],
            ucfirst($item['condition']),
            ucfirst($item['status']),
            $item['notes'] ?? '-'
        ]);
    }

    fclose($output);
    exit;
}

/**
 * Export to PDF
 */
function exportToPDF($data, $filename, $lab_name) {
    // Load autoload
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        // Fallback to HTML print view
        exportToHTMLPrint($data, $filename, $lab_name, true);
        return;
    }
    
    require_once $autoload;
    
    // Check if TCPDF or similar library is available
    if (!class_exists('TCPDF')) {
        // Fallback to HTML print view
        exportToHTMLPrint($data, $filename, $lab_name, true);
        return;
    }
    
    $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator(APP_NAME);
    $pdf->SetAuthor(APP_NAME);
    $pdf->SetTitle('Inventaris Lab Komputer');
    $pdf->SetSubject('Laporan Inventaris');

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Set margins
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 10);

    // Add a page
    $pdf->AddPage();

    // Title
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'LAPORAN INVENTARIS LAB KOMPUTER', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, $lab_name, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, 'Tanggal: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    $pdf->Ln(3);

    // Table
    $pdf->SetFont('helvetica', '', 7);
    
    // Build HTML table with inline styles
    $html = '<style>
        table { border-collapse: collapse; width: 100%; }
        th { background-color: #667eea; color: #ffffff; font-weight: bold; padding: 5px; border: 1px solid #333; text-align: center; font-size: 7pt; }
        td { padding: 4px; border: 1px solid #cccccc; font-size: 7pt; }
    </style>
    <table cellpadding="4" cellspacing="0">
        <thead>
            <tr>
                <th width="7%"><b>Kode</b></th>
                <th width="12%"><b>Nama Item</b></th>
                <th width="8%"><b>Lab</b></th>
                <th width="7%"><b>Kategori</b></th>
                <th width="8%"><b>Brand</b></th>
                <th width="8%"><b>Model</b></th>
                <th width="10%"><b>Serial No</b></th>
                <th width="5%"><b>Total</b></th>
                <th width="5%"><b>Baik</b></th>
                <th width="5%"><b>Rusak</b></th>
                <th width="5%"><b>Cadang</b></th>
                <th width="7%"><b>Kondisi</b></th>
                <th width="7%"><b>Status</b></th>
                <th width="6%"><b>Catatan</b></th>
            </tr>
        </thead>
        <tbody>';

    if (empty($data)) {
        $html .= '<tr><td colspan="14" style="text-align: center; padding: 20px;">Tidak ada data inventaris</td></tr>';
    } else {
        foreach ($data as $item) {
            $notes = $item['notes'] ?? '-';
            if (strlen($notes) > 30) {
                $notes = substr($notes, 0, 27) . '...';
            }
            
            $html .= '<tr>
                <td>' . htmlspecialchars($item['item_code']) . '</td>
                <td>' . htmlspecialchars($item['item_name']) . '</td>
                <td>' . htmlspecialchars($item['lab_name']) . '</td>
                <td>' . htmlspecialchars(ucfirst($item['category'])) . '</td>
                <td>' . htmlspecialchars($item['brand'] ?? '-') . '</td>
                <td>' . htmlspecialchars($item['model'] ?? '-') . '</td>
                <td>' . htmlspecialchars($item['serial_number'] ?? '-') . '</td>
                <td align="center">' . htmlspecialchars($item['quantity']) . '</td>
                <td align="center">' . htmlspecialchars($item['quantity_good']) . '</td>
                <td align="center">' . htmlspecialchars($item['quantity_broken']) . '</td>
                <td align="center">' . htmlspecialchars($item['quantity_backup']) . '</td>
                <td>' . htmlspecialchars(ucfirst($item['condition'])) . '</td>
                <td>' . htmlspecialchars(ucfirst($item['status'])) . '</td>
                <td>' . htmlspecialchars($notes) . '</td>
            </tr>';
        }
    }

    $html .= '</tbody></table>';
    
    // Add summary
    $total_items = count($data);
    $total_quantity = array_sum(array_column($data, 'quantity'));
    $total_good = array_sum(array_column($data, 'quantity_good'));
    $total_broken = array_sum(array_column($data, 'quantity_broken'));
    $total_backup = array_sum(array_column($data, 'quantity_backup'));
    
    $html .= '<br><br><table style="width: 40%;">
        <tr><td><b>RINGKASAN:</b></td></tr>
        <tr><td><b>Total Item:</b> ' . $total_items . '</td></tr>
        <tr><td><b>Total Unit:</b> ' . $total_quantity . '</td></tr>
        <tr><td><b>Unit Baik:</b> ' . $total_good . '</td></tr>
        <tr><td><b>Unit Rusak:</b> ' . $total_broken . '</td></tr>
        <tr><td><b>Unit Cadangan:</b> ' . $total_backup . '</td></tr>
    </table>';

    // Write HTML
    $pdf->writeHTML($html, true, false, true, false, '');

    // Output
    $pdf->Output($filename . '.pdf', 'D');
    exit;
}

/**
 * Export to HTML Print View (fallback for PDF and Word)
 */
function exportToHTMLPrint($data, $filename, $lab_name, $with_signature = false) {
    // Calculate summary
    $total_items = count($data);
    $total_quantity = array_sum(array_column($data, 'quantity'));
    $total_good = array_sum(array_column($data, 'quantity_good'));
    $total_broken = array_sum(array_column($data, 'quantity_broken'));
    $total_backup = array_sum(array_column($data, 'quantity_backup'));
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cetak Inventaris - <?= htmlspecialchars($lab_name) ?></title>
        <style>
            @media print {
                .no-print { display: none; }
                @page { 
                    size: landscape;
                    margin: 1cm;
                }
            }
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
            }
            h1, h2 { text-align: center; margin: 5px 0; }
            h1 { font-size: 18px; }
            h2 { font-size: 14px; }
            .date { text-align: center; font-size: 12px; margin-bottom: 20px; }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
                font-size: 10px;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 6px;
                text-align: left;
            }
            th {
                background-color: #667eea;
                color: white;
                font-weight: bold;
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .btn {
                padding: 10px 20px;
                margin: 10px 5px;
                background: #667eea;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
            }
            .summary {
                margin-top: 20px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 5px;
            }
            .summary table {
                width: auto;
                font-size: 12px;
            }
            .summary td {
                border: none;
                padding: 5px 15px;
            }
            .text-center { text-align: center; }
            
            /* Signature section */
            .signature-section {
                margin-top: 40px;
                text-align: right;
            }
            .signature-box {
                display: inline-block;
                text-align: center;
                min-width: 200px;
            }
            .signature-line {
                border-top: 1px solid #000;
                margin-top: 60px;
                padding-top: 5px;
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="text-align: center; margin-bottom: 20px;">
            <button class="btn" onclick="window.print()">🖨️ Cetak / Save as PDF</button>
            <button class="btn" onclick="window.close()" style="background: #6c757d;">✖️ Tutup</button>
        </div>
        
        <h1>LAPORAN INVENTARIS LAB KOMPUTER</h1>
        <h2><?= htmlspecialchars($lab_name) ?></h2>
        <p class="date">Tanggal: <?= date('d/m/Y H:i:s') ?></p>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 7%;">Kode</th>
                    <th style="width: 12%;">Nama Item</th>
                    <th style="width: 8%;">Lab</th>
                    <th style="width: 7%;">Kategori</th>
                    <th style="width: 8%;">Brand</th>
                    <th style="width: 8%;">Model</th>
                    <th style="width: 10%;">Serial No</th>
                    <th style="width: 5%;">Total</th>
                    <th style="width: 5%;">Baik</th>
                    <th style="width: 5%;">Rusak</th>
                    <th style="width: 5%;">Cadang</th>
                    <th style="width: 7%;">Kondisi</th>
                    <th style="width: 7%;">Status</th>
                    <th style="width: 6%;">Catatan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="14" class="text-center">Tidak ada data inventaris</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['item_code']) ?></td>
                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                        <td><?= htmlspecialchars($item['lab_name']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($item['category'])) ?></td>
                        <td><?= htmlspecialchars($item['brand'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($item['model'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($item['serial_number'] ?? '-') ?></td>
                        <td class="text-center"><?= htmlspecialchars($item['quantity']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($item['quantity_good']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($item['quantity_broken']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($item['quantity_backup']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($item['condition'])) ?></td>
                        <td><?= htmlspecialchars(ucfirst($item['status'])) ?></td>
                        <td><?= htmlspecialchars($item['notes'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="summary">
            <strong>RINGKASAN:</strong>
            <table>
                <tr>
                    <td><strong>Total Item:</strong></td>
                    <td><?= $total_items ?></td>
                </tr>
                <tr>
                    <td><strong>Total Unit:</strong></td>
                    <td><?= $total_quantity ?></td>
                </tr>
                <tr>
                    <td><strong>Unit Baik:</strong></td>
                    <td><?= $total_good ?></td>
                </tr>
                <tr>
                    <td><strong>Unit Rusak:</strong></td>
                    <td><?= $total_broken ?></td>
                </tr>
                <tr>
                    <td><strong>Unit Cadangan:</strong></td>
                    <td><?= $total_backup ?></td>
                </tr>
            </table>
        </div>
        
        <?php if ($with_signature): ?>
        <div class="signature-section">
            <div class="signature-box">
                <p>Surabaya, <?= date('d F Y') ?></p>
                <p>Penanggung Jawab,</p>
                <div class="signature-line">
                    ( ............................... )
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <script>
            // Auto print when opened in new window/tab
            window.onload = function() {
                // Uncomment if you want auto-print
                // setTimeout(function() { window.print(); }, 500);
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>