<?php
// Get usage by class
try {
    $stmt = $conn->prepare("
        SELECT c.id, c.name, c.grade_level, c.major,
               o.name as organization_name,
               COUNT(s.id) as total_usage,
               COUNT(DISTINCT s.resource_id) as labs_used,
               COUNT(DISTINCT s.user_id) as teachers_count,
               COUNT(DISTINCT s.time_slot_id) as time_slots_used
        FROM classes c
        LEFT JOIN organizations o ON c.organization_id = o.id
        LEFT JOIN schedules s ON c.id = s.class_id
            AND s.created_at BETWEEN ? AND ?
            AND s.deleted_at IS NULL
        WHERE c.deleted_at IS NULL
        GROUP BY c.id, c.name, c.grade_level, c.major, o.name
        ORDER BY total_usage DESC
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get top 10 for chart
    $top_classes = array_slice($classes, 0, 10);
    $class_names = array_column($top_classes, 'name');
    $class_usage = array_column($top_classes, 'total_usage');

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}
?>

<!-- Summary Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--blue-light);">🎓</div>
        <div class="stat-info">
            <div class="stat-number"><?= count($classes) ?></div>
            <div class="stat-label">Total Kelas</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--cyan-light);">📊</div>
        <div class="stat-info">
            <div class="stat-number"><?= number_format(array_sum(array_column($classes, 'total_usage'))) ?></div>
            <div class="stat-label">Total Penggunaan</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--pink-light);">📈</div>
        <div class="stat-info">
            <?php 
            $avg_usage = count($classes) > 0 ? array_sum(array_column($classes, 'total_usage')) / count($classes) : 0;
            ?>
            <div class="stat-number"><?= number_format($avg_usage, 1) ?></div>
            <div class="stat-label">Rata-rata / Kelas</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--amber-light);">⭐</div>
        <div class="stat-info">
            <?php 
            $most_active = !empty($classes) ? $classes[0] : null;
            ?>
            <div class="stat-number" style="font-size: 1.2rem;">
                <?= $most_active ? htmlspecialchars($most_active['name']) : 'N/A' ?>
            </div>
            <div class="stat-label">Kelas Paling Aktif</div>
        </div>
    </div>
</div>

<!-- Class Usage Table -->
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Statistik Penggunaan Per Kelas</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Peringkat</th>
                    <th>Kelas</th>
                    <th>Tingkat</th>
                    <th>Jurusan</th>
                    <th>Lembaga</th>
                    <th>Total Penggunaan</th>
                    <th>Lab Digunakan</th>
                    <th>Guru</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($classes as $index => $class): ?>
                <tr>
                    <td>
                        <?php if($index < 3): ?>
                            <?php if($index === 0): ?>🥇
                            <?php elseif($index === 1): ?>🥈
                            <?php else: ?>🥉
                            <?php endif; ?>
                        <?php else: ?>
                            #<?= $index + 1 ?>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($class['name']) ?></strong></td>
                    <td><?= htmlspecialchars($class['grade_level'] ?: '-') ?></td>
                    <td>
                        <?php if($class['major']): ?>
                            <span class="badge badge-info"><?= htmlspecialchars($class['major']) ?></span>
                        <?php else: ?>
                            <span style="color: var(--text-light);">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($class['organization_name']) ?></td>
                    <td><strong><?= number_format($class['total_usage']) ?></strong> kali</td>
                    <td><?= number_format($class['labs_used']) ?> lab</td>
                    <td><?= number_format($class['teachers_count']) ?> guru</td>
                    <td>
                        <?php if($class['total_usage'] > 20): ?>
                            <span class="badge badge-success">Sangat Aktif</span>
                        <?php elseif($class['total_usage'] > 10): ?>
                            <span class="badge badge-warning">Aktif</span>
                        <?php elseif($class['total_usage'] > 0): ?>
                            <span class="badge badge-info">Kurang Aktif</span>
                        <?php else: ?>
                            <span class="badge" style="background: #f0f0f0; color: #999;">Tidak Ada</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Top 10 Chart -->
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Top 10 Kelas Paling Aktif</h3>
    </div>
    <div class="chart-container">
        <canvas id="classChart"></canvas>
    </div>
</div>

<script>
new Chart(document.getElementById('classChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($class_names) ?>,
        datasets: [{
            label: 'Total Penggunaan',
            data: <?= json_encode($class_usage) ?>,
            backgroundColor: colors.blue,
            borderRadius: 8
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>