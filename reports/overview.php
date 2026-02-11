<?php
// Overview Report - Ringkasan Umum
// File ini dipanggil dari reports.php, jadi $conn dan $start_date/$end_date sudah tersedia

// Initialize default values
$overview = [
    'total_schedules' => 0,
    'total_classes_used' => 0,
    'total_labs_used' => 0,
    'total_teachers' => 0
];

$bookings_stat = [
    'total_bookings' => 0,
    'approved_bookings' => 0,
    'pending_bookings' => 0,
    'rejected_bookings' => 0
];

$usage_by_date = [];
$top_labs = [];
$usage_by_slot = [];

// Get overview statistics
try {
    // Total schedules in date range
    $sql = "SELECT COUNT(DISTINCT s.id) as total_schedules,
                   COUNT(DISTINCT s.class_id) as total_classes_used,
                   COUNT(DISTINCT s.resource_id) as total_labs_used,
                   COUNT(DISTINCT s.user_id) as total_teachers
            FROM schedules s
            WHERE s.created_at BETWEEN :start_date AND :end_date
            AND s.deleted_at IS NULL";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $overview = $result;
    }

} catch (PDOException $e) {
    error_log("Error getting overview stats: " . $e->getMessage());
}

// Bookings statistics
try {
    $sql = "SELECT COUNT(*) as total_bookings,
                   SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_bookings,
                   SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
                   SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_bookings
            FROM bookings
            WHERE booking_date BETWEEN :start_date AND :end_date
            AND deleted_at IS NULL";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $bookings_stat = $result;
    }

} catch (PDOException $e) {
    error_log("Error getting bookings stats: " . $e->getMessage());
}

// Lab usage by day
try {
    $sql = "SELECT DATE(s.created_at) as usage_date,
                   COUNT(*) as total_usage
            FROM schedules s
            WHERE s.created_at BETWEEN :start_date AND :end_date
            AND s.deleted_at IS NULL
            GROUP BY DATE(s.created_at)
            ORDER BY usage_date";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    $usage_by_date = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error getting usage by date: " . $e->getMessage());
}

// Top 5 most used labs
try {
    $sql = "SELECT r.name, r.type, COUNT(s.id) as usage_count
            FROM schedules s
            JOIN resources r ON s.resource_id = r.id
            WHERE s.created_at BETWEEN :start_date AND :end_date
            AND s.deleted_at IS NULL
            GROUP BY r.id, r.name, r.type
            ORDER BY usage_count DESC
            LIMIT 5";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    $top_labs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error getting top labs: " . $e->getMessage());
}

// Usage by time slot
try {
    $sql = "SELECT ts.name, COUNT(s.id) as usage_count
            FROM schedules s
            JOIN time_slots ts ON s.time_slot_id = ts.id
            WHERE s.created_at BETWEEN :start_date AND :end_date
            AND s.deleted_at IS NULL
            GROUP BY ts.id, ts.name, ts.slot_order
            ORDER BY ts.slot_order";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    $usage_by_slot = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error getting usage by slot: " . $e->getMessage());
}
?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--blue-light);">📅</div>
        <div class="stat-info">
            <div class="stat-number"><?= number_format($overview['total_schedules'] ?? 0) ?></div>
            <div class="stat-label">Total Jadwal</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--cyan-light);">🎓</div>
        <div class="stat-info">
            <div class="stat-number"><?= number_format($overview['total_classes_used'] ?? 0) ?></div>
            <div class="stat-label">Kelas Terlibat</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--pink-light);">🧪</div>
        <div class="stat-info">
            <div class="stat-number"><?= number_format($overview['total_labs_used'] ?? 0) ?></div>
            <div class="stat-label">Lab Digunakan</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--amber-light);">👨‍🏫</div>
        <div class="stat-info">
            <div class="stat-number"><?= number_format($overview['total_teachers'] ?? 0) ?></div>
            <div class="stat-label">Guru Aktif</div>
        </div>
    </div>
</div>

<!-- Booking Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--violet-light);">🔖</div>
        <div class="stat-info">
            <div class="stat-number"><?= number_format($bookings_stat['total_bookings'] ?? 0) ?></div>
            <div class="stat-label">Total Booking</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--cyan-light);">✅</div>
        <div class="stat-info">
            <div class="stat-number"><?= number_format($bookings_stat['approved_bookings'] ?? 0) ?></div>
            <div class="stat-label">Booking Disetujui</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--amber-light);">⏳</div>
        <div class="stat-info">
            <div class="stat-number"><?= number_format($bookings_stat['pending_bookings'] ?? 0) ?></div>
            <div class="stat-label">Booking Pending</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--coral-light);">❌</div>
        <div class="stat-info">
            <div class="stat-number"><?= number_format($bookings_stat['rejected_bookings'] ?? 0) ?></div>
            <div class="stat-label">Booking Ditolak</div>
        </div>
    </div>
</div>

<!-- Usage Chart -->
<?php if (!empty($usage_by_date)): ?>
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Trend Penggunaan Lab</h3>
    </div>
    <div class="chart-container">
        <canvas id="usageChart"></canvas>
    </div>
</div>
<?php else: ?>
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Trend Penggunaan Lab</h3>
    </div>
    <div style="padding: 2rem; text-align: center; color: var(--text-light);">
        <p>📊 Tidak ada data untuk rentang tanggal yang dipilih</p>
    </div>
</div>
<?php endif; ?>

<!-- Top Labs -->
<?php if (!empty($top_labs)): ?>
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Top 5 Laboratorium Paling Sering Digunakan</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Peringkat</th>
                    <th>Nama Lab</th>
                    <th>Tipe</th>
                    <th>Jumlah Penggunaan</th>
                    <th>Persentase</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_usage = array_sum(array_column($top_labs, 'usage_count'));
                foreach ($top_labs as $index => $lab): 
                    $percentage = $total_usage > 0 ? ($lab['usage_count'] / $total_usage) * 100 : 0;
                ?>
                <tr>
                    <td style="text-align: center;">
                        <?php if($index === 0): ?>
                            <span style="font-size: 1.5rem;">🥇</span>
                        <?php elseif($index === 1): ?>
                            <span style="font-size: 1.5rem;">🥈</span>
                        <?php elseif($index === 2): ?>
                            <span style="font-size: 1.5rem;">🥉</span>
                        <?php else: ?>
                            #<?= $index + 1 ?>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($lab['name'] ?? 'N/A') ?></strong></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($lab['type'] ?? 'N/A') ?></span></td>
                    <td><?= number_format($lab['usage_count'] ?? 0) ?> kali</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="flex: 1; background: var(--border); height: 8px; border-radius: 4px; overflow: hidden;">
                                <div style="width: <?= number_format($percentage, 1) ?>%; height: 100%; background: var(--blue); border-radius: 4px;"></div>
                            </div>
                            <span style="font-size: 0.85rem; color: var(--text-mid); min-width: 45px;"><?= number_format($percentage, 1) ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Top 5 Laboratorium Paling Sering Digunakan</h3>
    </div>
    <div style="padding: 2rem; text-align: center; color: var(--text-light);">
        <p>📊 Tidak ada data untuk rentang tanggal yang dipilih</p>
    </div>
</div>
<?php endif; ?>

<!-- Usage by Time Slot -->
<?php if (!empty($usage_by_slot)): ?>
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Penggunaan Lab Per Slot Waktu</h3>
    </div>
    <div class="chart-container">
        <canvas id="timeSlotChart"></canvas>
    </div>
</div>
<?php else: ?>
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Penggunaan Lab Per Slot Waktu</h3>
    </div>
    <div style="padding: 2rem; text-align: center; color: var(--text-light);">
        <p>📊 Tidak ada data untuk rentang tanggal yang dipilih</p>
    </div>
</div>
<?php endif; ?>

<script>
// Usage trend chart
<?php if (!empty($usage_by_date)): ?>
const usageData = <?= json_encode($usage_by_date) ?>;
const usageLabels = usageData.map(d => {
    const date = new Date(d.usage_date);
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
});
const usageValues = usageData.map(d => parseInt(d.total_usage));

new Chart(document.getElementById('usageChart'), {
    type: 'line',
    data: {
        labels: usageLabels,
        datasets: [{
            label: 'Jumlah Penggunaan',
            data: usageValues,
            borderColor: colors.blue,
            backgroundColor: 'rgba(74, 140, 255, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                titleFont: {
                    size: 14
                },
                bodyFont: {
                    size: 13
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0,
                    font: {
                        size: 12
                    }
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                }
            },
            x: {
                ticks: {
                    font: {
                        size: 12
                    }
                },
                grid: {
                    display: false
                }
            }
        }
    }
});
<?php endif; ?>

// Time slot chart
<?php if (!empty($usage_by_slot)): ?>
const slotData = <?= json_encode($usage_by_slot) ?>;
const slotLabels = slotData.map(d => d.name);
const slotValues = slotData.map(d => parseInt(d.usage_count));

new Chart(document.getElementById('timeSlotChart'), {
    type: 'bar',
    data: {
        labels: slotLabels,
        datasets: [{
            label: 'Jumlah Penggunaan',
            data: slotValues,
            backgroundColor: [
                colors.cyan,
                colors.blue,
                colors.violet,
                colors.pink,
                colors.amber,
                colors.coral,
                colors.cyan,
                colors.blue,
                colors.violet,
                colors.pink
            ],
            borderRadius: 8,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                titleFont: {
                    size: 14
                },
                bodyFont: {
                    size: 13
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0,
                    font: {
                        size: 12
                    }
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                }
            },
            x: {
                ticks: {
                    font: {
                        size: 11
                    }
                },
                grid: {
                    display: false
                }
            }
        }
    }
});
<?php endif; ?>
</script>