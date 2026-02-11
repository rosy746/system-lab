<?php
// Get usage by teacher
try {
    $stmt = $conn->prepare("
        SELECT u.id, u.full_name, u.email,
               COUNT(DISTINCT s.id) as total_schedules,
               COUNT(DISTINCT s.class_id) as total_classes,
               COUNT(DISTINCT s.resource_id) as total_labs,
               COUNT(DISTINCT DATE(s.created_at)) as total_days,
               GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') as labs_used
        FROM users u
        LEFT JOIN schedules s ON u.id = s.user_id
            AND s.created_at BETWEEN ? AND ?
            AND s.deleted_at IS NULL
        LEFT JOIN resources r ON s.resource_id = r.id
        WHERE u.deleted_at IS NULL
        GROUP BY u.id, u.full_name, u.email
        HAVING total_schedules > 0
        ORDER BY total_schedules DESC
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get schedule distribution by teacher
    $teacher_names = array_slice(array_column($teachers, 'full_name'), 0, 10);
    $teacher_schedules = array_slice(array_column($teachers, 'total_schedules'), 0, 10);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}
?>

<!-- Summary Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--violet-light);">👨‍🏫</div>
        <div class="stat-info">
            <div class="stat-number"><?= count($teachers) ?></div>
            <div class="stat-label">Total Guru Aktif</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--cyan-light);">📚</div>
        <div class="stat-info">
            <div class="stat-number"><?= number_format(array_sum(array_column($teachers, 'total_schedules'))) ?></div>
            <div class="stat-label">Total Jadwal</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--amber-light);">📊</div>
        <div class="stat-info">
            <?php 
            $avg_schedules = count($teachers) > 0 ? array_sum(array_column($teachers, 'total_schedules')) / count($teachers) : 0;
            ?>
            <div class="stat-number"><?= number_format($avg_schedules, 1) ?></div>
            <div class="stat-label">Rata-rata / Guru</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--pink-light);">⭐</div>
        <div class="stat-info">
            <?php 
            $most_active = !empty($teachers) ? $teachers[0] : null;
            ?>
            <div class="stat-number" style="font-size: 1rem;">
                <?= $most_active ? htmlspecialchars($most_active['full_name']) : 'N/A' ?>
            </div>
            <div class="stat-label">Guru Paling Aktif</div>
        </div>
    </div>
</div>

<!-- Teacher Usage Table -->
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Statistik Penggunaan Per Guru</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Peringkat</th>
                    <th>Nama Guru</th>
                    <th>Email</th>
                    <th>Total Jadwal</th>
                    <th>Kelas Diajar</th>
                    <th>Lab Digunakan</th>
                    <th>Hari Aktif</th>
                    <th>Intensitas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $index => $teacher): ?>
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
                    <td><strong><?= htmlspecialchars($teacher['full_name']) ?></strong></td>
                    <td style="color: var(--text-light); font-size: 0.85rem;">
                        <?= htmlspecialchars($teacher['email']) ?>
                    </td>
                    <td><strong><?= number_format($teacher['total_schedules']) ?></strong> jadwal</td>
                    <td><?= number_format($teacher['total_classes']) ?> kelas</td>
                    <td><?= number_format($teacher['total_labs']) ?> lab</td>
                    <td><?= number_format($teacher['total_days']) ?> hari</td>
                    <td>
                        <?php 
                        $intensity = $teacher['total_schedules'];
                        if($intensity >= 30): ?>
                            <span class="badge badge-danger">Sangat Tinggi</span>
                        <?php elseif($intensity >= 20): ?>
                            <span class="badge badge-warning">Tinggi</span>
                        <?php elseif($intensity >= 10): ?>
                            <span class="badge badge-info">Sedang</span>
                        <?php else: ?>
                            <span class="badge" style="background: var(--cyan-light); color: var(--cyan);">Rendah</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Teacher Distribution Chart -->
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Distribusi Beban Mengajar Top 10 Guru</h3>
    </div>
    <div class="chart-container">
        <canvas id="teacherChart"></canvas>
    </div>
</div>

<!-- Teacher Labs Breakdown -->
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Lab yang Digunakan oleh Setiap Guru</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Nama Guru</th>
                    <th>Lab yang Pernah Digunakan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $teacher): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($teacher['full_name']) ?></strong></td>
                    <td style="color: var(--text-mid);">
                        <?= htmlspecialchars($teacher['labs_used'] ?: 'Belum ada') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
new Chart(document.getElementById('teacherChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($teacher_names) ?>,
        datasets: [{
            data: <?= json_encode($teacher_schedules) ?>,
            backgroundColor: [
                colors.cyan,
                colors.pink,
                colors.amber,
                colors.blue,
                colors.violet,
                colors.coral,
                '#00a896',
                '#d65db1',
                '#e6a532',
                '#5a9cff'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right'
            }
        }
    }
});
</script>