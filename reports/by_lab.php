<?php
// Get usage by lab/resource
try {
    $stmt = $conn->prepare("
        SELECT r.id, r.name, r.type, r.capacity, r.location,
               COUNT(DISTINCT s.id) as total_usage,
               COUNT(DISTINCT s.class_id) as classes_served,
               COUNT(DISTINCT s.user_id) as teachers_count,
               COUNT(DISTINCT DATE(s.created_at)) as days_used,
               GROUP_CONCAT(DISTINCT ts.name ORDER BY ts.slot_order SEPARATOR ', ') as time_slots_used
        FROM resources r
        LEFT JOIN schedules s ON r.id = s.resource_id
            AND s.created_at BETWEEN ? AND ?
            AND s.deleted_at IS NULL
        LEFT JOIN time_slots ts ON s.time_slot_id = ts.id
        WHERE r.deleted_at IS NULL
        AND r.type IN ('lab_komputer', 'lab_ipa', 'lab')
        GROUP BY r.id, r.name, r.type, r.capacity, r.location
        ORDER BY total_usage DESC
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $labs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get lab utilization rate
    foreach ($labs as &$lab) {
        // Calculate utilization percentage (assuming 40 slots per week as max)
        $max_slots = 40; // 10 slots per day * 4 days (estimation)
        $lab['utilization_rate'] = ($lab['total_usage'] / $max_slots) * 100;
    }

    // Get inventory summary per lab
    $stmt = $conn->prepare("
        SELECT li.resource_id,
               SUM(li.quantity) as total_items,
               SUM(li.quantity_good) as working_items,
               SUM(li.quantity_broken) as broken_items,
               COUNT(*) as item_types
        FROM lab_inventory li
        WHERE li.deleted_at IS NULL
        GROUP BY li.resource_id
    ");
    $stmt->execute();
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $inventory_map = [];
    foreach ($inventory as $inv) {
        $inventory_map[$inv['resource_id']] = $inv;
    }

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}
?>

<!-- Summary Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--blue-light);">🧪</div>
        <div class="stat-info">
            <div class="stat-number"><?= count($labs) ?></div>
            <div class="stat-label">Total Laboratorium</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--cyan-light);">📊</div>
        <div class="stat-info">
            <div class="stat-number"><?= number_format(array_sum(array_column($labs, 'total_usage'))) ?></div>
            <div class="stat-label">Total Penggunaan</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--amber-light);">⚡</div>
        <div class="stat-info">
            <?php 
            $avg_util = count($labs) > 0 ? array_sum(array_column($labs, 'utilization_rate')) / count($labs) : 0;
            ?>
            <div class="stat-number"><?= number_format($avg_util, 1) ?>%</div>
            <div class="stat-label">Avg. Utilization</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: var(--pink-light);">📦</div>
        <div class="stat-info">
            <?php 
            $total_inventory = array_sum(array_column($inventory, 'total_items'));
            ?>
            <div class="stat-number"><?= number_format($total_inventory) ?></div>
            <div class="stat-label">Total Inventaris</div>
        </div>
    </div>
</div>

<!-- Lab Usage Table -->
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Statistik Penggunaan Per Laboratorium</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Lab</th>
                    <th>Tipe</th>
                    <th>Lokasi</th>
                    <th>Kapasitas</th>
                    <th>Total Penggunaan</th>
                    <th>Kelas</th>
                    <th>Guru</th>
                    <th>Hari Digunakan</th>
                    <th>Utilization</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($labs as $lab): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($lab['name']) ?></strong></td>
                    <td>
                        <span class="badge badge-info">
                            <?= htmlspecialchars($lab['type']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($lab['location'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($lab['capacity'] ?: '-') ?></td>
                    <td><strong><?= number_format($lab['total_usage']) ?></strong> kali</td>
                    <td><?= number_format($lab['classes_served']) ?></td>
                    <td><?= number_format($lab['teachers_count']) ?></td>
                    <td><?= number_format($lab['days_used']) ?> hari</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="flex: 1; background: var(--border); height: 8px; border-radius: 4px; overflow: hidden;">
                                <?php 
                                $util_color = $lab['utilization_rate'] > 80 ? 'var(--coral)' : 
                                             ($lab['utilization_rate'] > 50 ? 'var(--amber)' : 'var(--cyan)');
                                ?>
                                <div style="width: <?= min($lab['utilization_rate'], 100) ?>%; height: 100%; background: <?= $util_color ?>; border-radius: 4px;"></div>
                            </div>
                            <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-mid); min-width: 45px;">
                                <?= number_format($lab['utilization_rate'], 1) ?>%
                            </span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Lab Inventory Status -->
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Status Inventaris Per Lab</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Laboratorium</th>
                    <th>Total Item</th>
                    <th>Item Berfungsi</th>
                    <th>Item Rusak</th>
                    <th>Jenis Item</th>
                    <th>Health Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($labs as $lab): 
                    $inv = $inventory_map[$lab['id']] ?? null;
                    if (!$inv) continue;
                    $health_score = $inv['total_items'] > 0 ? ($inv['working_items'] / $inv['total_items']) * 100 : 0;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($lab['name']) ?></strong></td>
                    <td><?= number_format($inv['total_items']) ?></td>
                    <td>
                        <span class="badge badge-success">
                            <?= number_format($inv['working_items']) ?> unit
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-danger">
                            <?= number_format($inv['broken_items']) ?> unit
                        </span>
                    </td>
                    <td><?= number_format($inv['item_types']) ?> tipe</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="flex: 1; background: var(--border); height: 8px; border-radius: 4px; overflow: hidden;">
                                <?php 
                                $health_color = $health_score > 80 ? 'var(--cyan)' : 
                                               ($health_score > 60 ? 'var(--amber)' : 'var(--coral)');
                                ?>
                                <div style="width: <?= $health_score ?>%; height: 100%; background: <?= $health_color ?>; border-radius: 4px;"></div>
                            </div>
                            <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-mid); min-width: 45px;">
                                <?= number_format($health_score, 1) ?>%
                            </span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Time Slots Usage per Lab -->
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Slot Waktu yang Digunakan</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Laboratorium</th>
                    <th>Slot Waktu yang Digunakan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($labs as $lab): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($lab['name']) ?></strong></td>
                    <td style="color: var(--text-mid); font-size: 0.9rem;">
                        <?= htmlspecialchars($lab['time_slots_used'] ?: 'Belum ada penggunaan') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Utilization Chart -->
<div class="content-card">
    <div class="card-header">
        <h3 class="card-title">Tingkat Utilisasi Laboratorium</h3>
    </div>
    <div class="chart-container">
        <canvas id="utilizationChart"></canvas>
    </div>
</div>

<script>
const labData = <?= json_encode($labs) ?>;
const labNames = labData.map(l => l.name);
const labUtilization = labData.map(l => parseFloat(l.utilization_rate));

new Chart(document.getElementById('utilizationChart'), {
    type: 'bar',
    data: {
        labels: labNames,
        datasets: [{
            label: 'Utilization Rate (%)',
            data: labUtilization,
            backgroundColor: labUtilization.map(u => 
                u > 80 ? colors.coral : 
                u > 50 ? colors.amber : colors.cyan
            ),
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
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }
        }
    }
});
</script>