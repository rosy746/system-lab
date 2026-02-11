<?php
// Get usage by subject
try {
    $stmt = $conn->prepare("
        SELECT s.subject,
               COUNT(s.id) as total_schedules,
               COUNT(DISTINCT s.class_id) as classes_count,
               COUNT(DISTINCT s.user_id) as teachers_count,
               COUNT(DISTINCT s.resource_id) as labs_count,
               COUNT(DISTINCT DATE(s.created_at)) as days_count
        FROM schedules s
        WHERE s.created_at BETWEEN ? AND ?
        AND s.deleted_at IS NULL
        AND s.subject IS NOT NULL
        AND s.subject != ''
        GROUP BY s.subject
        ORDER BY total_schedules DESC
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Subject distribution by day
    $stmt = $conn->prepare("
        SELECT s.subject,
               DAYNAME(s.created_at) as day_name,
               COUNT(*) as count
        FROM schedules s
        WHERE s.created_at BETWEEN ? AND ?
        AND s.deleted_at IS NULL
        AND s.subject IS NOT NULL
        AND s.subject != ''
        GROUP BY s.subject, DAYNAME(s.created_at)
        ORDER BY s.subject, FIELD(DAYNAME(s.created_at), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $subject_by_day = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top subjects
    $top_subjects = array_slice($subjects, 0, 10);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}
?>

<!-- Summary Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--violet-light);">📚</div>
        <div class="stat-info">
            <div class="stat-number"><?= count($subjects) ?></div>
            <div class="stat-label">Total Mata Pelajaran</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--cyan-light);">📊</div>
        <div class="stat-info">
            <div class="stat-number"><?= number_format(array_sum(array_column($subjects, 'total_schedules'))) ?></div>
            <div class="stat-label">Total Jadwal</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--pink-light);">⭐</div>
        <div class="stat-info">
            <?php $most_taught = !empty($subjects) ? $subjects[0] : null; ?>
            <div class="stat-number" style="font-size: 1.1rem;">
                <?= $most_taught ? htmlspecialchars($most_taught['subject']) : 'N/A' ?>
            </div>
            <div class="stat-label">Paling Banyak Diajar</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--amber-light);">📈</div>
        <div class="stat-info">
            <?php 
            $avg_schedules = count($subjects) > 0 ? array_sum(array_column($subjects, 'total_schedules')) / count($subjects) : 0;
            ?>
            <div class="stat-number"><?= number_format($avg_schedules, 1) ?></div>
            <div class="stat-label">Avg. Jadwal / Mapel</div>
        </div>
    </div>
</div>

<!-- Subject Usage Table -->
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Statistik Penggunaan Lab Per Mata Pelajaran</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Peringkat</th>
                    <th>Mata Pelajaran</th>
                    <th>Total Jadwal</th>
                    <th>Kelas</th>
                    <th>Guru</th>
                    <th>Lab</th>
                    <th>Hari Aktif</th>
                    <th>Intensitas</th>
                    <th>Persentase</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_all = array_sum(array_column($subjects, 'total_schedules'));
                foreach ($subjects as $index => $subject): 
                    $percentage = $total_all > 0 ? ($subject['total_schedules'] / $total_all) * 100 : 0;
                ?>
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
                    <td><strong><?= htmlspecialchars($subject['subject']) ?></strong></td>
                    <td><strong><?= number_format($subject['total_schedules']) ?></strong></td>
                    <td><?= number_format($subject['classes_count']) ?></td>
                    <td><?= number_format($subject['teachers_count']) ?></td>
                    <td><?= number_format($subject['labs_count']) ?></td>
                    <td><?= number_format($subject['days_count']) ?> hari</td>
                    <td>
                        <?php if($subject['total_schedules'] >= 30): ?>
                            <span class="badge badge-danger">Sangat Tinggi</span>
                        <?php elseif($subject['total_schedules'] >= 20): ?>
                            <span class="badge badge-warning">Tinggi</span>
                        <?php elseif($subject['total_schedules'] >= 10): ?>
                            <span class="badge badge-info">Sedang</span>
                        <?php else: ?>
                            <span class="badge" style="background: var(--cyan-light); color: var(--cyan);">Rendah</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="flex: 1; background: var(--border); height: 8px; border-radius: 4px; overflow: hidden;">
                                <div style="width: <?= $percentage ?>%; height: 100%; background: var(--blue); border-radius: 4px;"></div>
                            </div>
                            <span style="font-size: 0.85rem; color: var(--text-mid); min-width: 45px;">
                                <?= number_format($percentage, 1) ?>%
                            </span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Charts Row -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem;">
    <!-- Top Subjects Chart -->
    <div class="content-card">
        <div class="card-header">
            <h3 class="card-title">Top 10 Mata Pelajaran</h3>
        </div>
        <div class="chart-container">
            <canvas id="subjectChart"></canvas>
        </div>
    </div>

    <!-- Subject Distribution Pie -->
    <div class="content-card">
        <div class="card-header">
            <h3 class="card-title">Distribusi Mata Pelajaran</h3>
        </div>
        <div class="chart-container">
            <canvas id="distributionChart"></canvas>
        </div>
    </div>
</div>

<script>
// Top Subjects Bar Chart
const subjectData = <?= json_encode($top_subjects) ?>;
new Chart(document.getElementById('subjectChart'), {
    type: 'bar',
    data: {
        labels: subjectData.map(s => s.subject),
        datasets: [{
            label: 'Jumlah Jadwal',
            data: subjectData.map(s => s.total_schedules),
            backgroundColor: colors.violet,
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

// Distribution Pie Chart
const allSubjects = <?= json_encode($subjects) ?>;
new Chart(document.getElementById('distributionChart'), {
    type: 'pie',
    data: {
        labels: allSubjects.slice(0, 8).map(s => s.subject),
        datasets: [{
            data: allSubjects.slice(0, 8).map(s => s.total_schedules),
            backgroundColor: [
                colors.cyan,
                colors.pink,
                colors.amber,
                colors.blue,
                colors.violet,
                colors.coral,
                '#00a896',
                '#d65db1'
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