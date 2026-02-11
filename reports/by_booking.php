<?php
// Get booking statistics
try {
    $stmt = $conn->prepare("
        SELECT b.id, b.booking_code, b.title, b.booking_date,
               b.status, b.purpose, b.participant_count,
               r.name as lab_name,
               ts.name as time_slot,
               u.full_name as requester,
               a.full_name as approver,
               b.created_at, b.approved_at
        FROM bookings b
        LEFT JOIN resources r ON b.resource_id = r.id
        LEFT JOIN time_slots ts ON b.time_slot_id = ts.id
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN users a ON b.approved_by = a.id
        WHERE b.booking_date BETWEEN ? AND ?
        AND b.deleted_at IS NULL
        ORDER BY b.booking_date DESC, b.created_at DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get status summary
    $status_counts = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'cancelled' => 0
    ];
    
    $total_participants = 0;
    foreach ($bookings as $booking) {
        $status_counts[$booking['status']] = ($status_counts[$booking['status']] ?? 0) + 1;
        $total_participants += $booking['participant_count'] ?? 0;
    }

    // Bookings by purpose
    $stmt = $conn->prepare("
        SELECT purpose, COUNT(*) as count
        FROM bookings
        WHERE booking_date BETWEEN ? AND ?
        AND deleted_at IS NULL
        GROUP BY purpose
        ORDER BY count DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $purposes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Bookings by lab
    $stmt = $conn->prepare("
        SELECT r.name, COUNT(b.id) as booking_count
        FROM bookings b
        JOIN resources r ON b.resource_id = r.id
        WHERE b.booking_date BETWEEN ? AND ?
        AND b.deleted_at IS NULL
        GROUP BY r.id, r.name
        ORDER BY booking_count DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $booking_by_lab = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Approval rate over time
    $stmt = $conn->prepare("
        SELECT DATE(booking_date) as date,
               COUNT(*) as total,
               SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
        FROM bookings
        WHERE booking_date BETWEEN ? AND ?
        AND deleted_at IS NULL
        GROUP BY DATE(booking_date)
        ORDER BY date
    ");
    $stmt->execute([$start_date, $end_date]);
    $approval_timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}
?>

<!-- Summary Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--blue-light);">🔖</div>
        <div class="stat-info">
            <div class="stat-number"><?= count($bookings) ?></div>
            <div class="stat-label">Total Booking</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--cyan-light);">✅</div>
        <div class="stat-info">
            <div class="stat-number"><?= $status_counts['approved'] ?></div>
            <div class="stat-label">Disetujui</div>
            <?php 
            $approval_rate = count($bookings) > 0 ? ($status_counts['approved'] / count($bookings)) * 100 : 0;
            ?>
            <span class="stat-change up">▲ <?= number_format($approval_rate, 1) ?>%</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--amber-light);">⏳</div>
        <div class="stat-info">
            <div class="stat-number"><?= $status_counts['pending'] ?></div>
            <div class="stat-label">Menunggu</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--pink-light);">👥</div>
        <div class="stat-info">
            <div class="stat-number"><?= number_format($total_participants) ?></div>
            <div class="stat-label">Total Peserta</div>
        </div>
    </div>
</div>

<!-- Status Breakdown -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--cyan-light);">✅</div>
        <div class="stat-info">
            <div class="stat-number"><?= $status_counts['approved'] ?></div>
            <div class="stat-label">Approved</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--amber-light);">⏳</div>
        <div class="stat-info">
            <div class="stat-number"><?= $status_counts['pending'] ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--coral-light);">❌</div>
        <div class="stat-info">
            <div class="stat-number"><?= $status_counts['rejected'] ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--violet-light);">🚫</div>
        <div class="stat-info">
            <div class="stat-number"><?= $status_counts['cancelled'] ?></div>
            <div class="stat-label">Cancelled</div>
        </div>
    </div>
</div>

<!-- Booking List -->
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Daftar Booking</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Kode Booking</th>
                    <th>Judul</th>
                    <th>Tanggal</th>
                    <th>Lab</th>
                    <th>Waktu</th>
                    <th>Pemohon</th>
                    <th>Peserta</th>
                    <th>Status</th>
                    <th>Tujuan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($booking['booking_code']) ?></strong></td>
                    <td><?= htmlspecialchars($booking['title']) ?></td>
                    <td><?= date('d M Y', strtotime($booking['booking_date'])) ?></td>
                    <td><?= htmlspecialchars($booking['lab_name']) ?></td>
                    <td><?= htmlspecialchars($booking['time_slot']) ?></td>
                    <td><?= htmlspecialchars($booking['requester']) ?></td>
                    <td><?= number_format($booking['participant_count'] ?? 0) ?> orang</td>
                    <td>
                        <?php
                        $status_badges = [
                            'pending' => 'badge-warning',
                            'approved' => 'badge-success',
                            'rejected' => 'badge-danger',
                            'cancelled' => 'badge-secondary'
                        ];
                        $badge_class = $status_badges[$booking['status']] ?? 'badge-info';
                        ?>
                        <span class="badge <?= $badge_class ?>">
                            <?= ucfirst($booking['status']) ?>
                        </span>
                    </td>
                    <td style="font-size: 0.85rem; color: var(--text-mid);">
                        <?= htmlspecialchars($booking['purpose']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Charts Row -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem;">
    <!-- Booking by Purpose -->
    <div class="content-card">
        <div class="card-header">
            <h3 class="card-title">Booking Berdasarkan Tujuan</h3>
        </div>
        <div class="chart-container">
            <canvas id="purposeChart"></canvas>
        </div>
    </div>

    <!-- Booking by Lab -->
    <div class="content-card">
        <div class="card-header">
            <h3 class="card-title">Booking Berdasarkan Lab</h3>
        </div>
        <div class="chart-container">
            <canvas id="labChart"></canvas>
        </div>
    </div>
</div>

<!-- Approval Timeline -->
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Timeline Persetujuan Booking</h3>
    </div>
    <div class="chart-container">
        <canvas id="approvalChart"></canvas>
    </div>
</div>

<script>
// Purpose Chart
const purposeData = <?= json_encode($purposes) ?>;
new Chart(document.getElementById('purposeChart'), {
    type: 'doughnut',
    data: {
        labels: purposeData.map(p => p.purpose || 'Tidak disebutkan'),
        datasets: [{
            data: purposeData.map(p => p.count),
            backgroundColor: [
                colors.cyan,
                colors.pink,
                colors.amber,
                colors.blue,
                colors.violet,
                colors.coral
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Lab Chart
const labBookingData = <?= json_encode($booking_by_lab) ?>;
new Chart(document.getElementById('labChart'), {
    type: 'bar',
    data: {
        labels: labBookingData.map(l => l.name),
        datasets: [{
            label: 'Jumlah Booking',
            data: labBookingData.map(l => l.booking_count),
            backgroundColor: colors.blue,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// Approval Timeline
const timelineData = <?= json_encode($approval_timeline) ?>;
const timelineLabels = timelineData.map(d => {
    const date = new Date(d.date);
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
});

new Chart(document.getElementById('approvalChart'), {
    type: 'line',
    data: {
        labels: timelineLabels,
        datasets: [
            {
                label: 'Total Booking',
                data: timelineData.map(d => d.total),
                borderColor: colors.blue,
                backgroundColor: 'rgba(74, 140, 255, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            },
            {
                label: 'Approved',
                data: timelineData.map(d => d.approved),
                borderColor: colors.cyan,
                backgroundColor: 'rgba(0, 201, 167, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>