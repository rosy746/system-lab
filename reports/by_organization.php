<?php
// Get usage by organization
try {
    $stmt = $conn->prepare("
        SELECT o.id, o.name, o.type,
               COUNT(DISTINCT s.id) as total_schedules,
               COUNT(DISTINCT s.class_id) as total_classes,
               COUNT(DISTINCT s.user_id) as total_teachers,
               COUNT(DISTINCT s.resource_id) as total_labs
        FROM organizations o
        LEFT JOIN classes c ON o.id = c.organization_id
        LEFT JOIN schedules s ON c.id = s.class_id
            AND s.created_at BETWEEN ? AND ?
            AND s.deleted_at IS NULL
        WHERE o.deleted_at IS NULL
        GROUP BY o.id, o.name, o.type
        ORDER BY total_schedules DESC
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $organizations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get organization names for chart
    $org_names = array_column($organizations, 'name');
    $org_schedules = array_column($organizations, 'total_schedules');

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}
?>

<!-- Organization Stats -->
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Statistik Penggunaan Per Lembaga</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Lembaga</th>
                    <th>Tipe</th>
                    <th>Total Jadwal</th>
                    <th>Kelas Terlibat</th>
                    <th>Guru Aktif</th>
                    <th>Lab Digunakan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($organizations as $index => $org): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><strong><?= htmlspecialchars($org['name']) ?></strong></td>
                    <td>
                        <span class="badge badge-info">
                            <?= htmlspecialchars($org['type'] ?: 'N/A') ?>
                        </span>
                    </td>
                    <td><?= number_format($org['total_schedules']) ?></td>
                    <td><?= number_format($org['total_classes']) ?></td>
                    <td><?= number_format($org['total_teachers']) ?></td>
                    <td><?= number_format($org['total_labs']) ?></td>
                    <td>
                        <a href="?report_type=organization_detail&org_id=<?= $org['id'] ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
                           class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">
                            📊 Detail
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart -->
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Perbandingan Penggunaan Antar Lembaga</h3>
    </div>
    <div class="chart-container">
        <canvas id="orgChart"></canvas>
    </div>
</div>

<script>
const orgData = {
    labels: <?= json_encode($org_names) ?>,
    datasets: [{
        label: 'Total Jadwal',
        data: <?= json_encode($org_schedules) ?>,
        backgroundColor: [
            colors.cyan,
            colors.pink,
            colors.amber,
            colors.blue,
            colors.violet,
            colors.coral
        ],
        borderRadius: 8
    }]
};

new Chart(document.getElementById('orgChart'), {
    type: 'bar',
    data: orgData,
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
</script>