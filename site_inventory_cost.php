<?php
require 'includes/config.php';
session_name('oss_portal');
session_start();

$sql = "SELECT sm.site_name, SUM(di.device_price) AS total_cost
        FROM device_inventory di
        LEFT JOIN site_master sm ON di.site_id = sm.site_id
        GROUP BY sm.site_id
        ORDER BY total_cost DESC";

$result = $conn->query($sql);

$site_costs = [];
$total_cost = 0;
while ($row = $result->fetch_assoc()) {
    $site_costs[] = [
        'name' => $row['site_name'] ?? 'Unknown',
        'cost' => floatval($row['total_cost'])
    ];
    $total_cost += $row['total_cost'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Site-wise Inventory Cost</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .chart-wrapper {
            max-width: 520px;
            margin: 0 auto 30px auto;
        }
        .table thead th {
            background-color: #e6f0ff;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <h4 class="mb-4 text-center text-primary">Total Inventory Cost by Site</h4>

    <!-- Chart at the top -->
    <div class="chart-wrapper">
        <canvas id="siteChart" height="300"></canvas>
    </div>

    <!-- Table below -->
    <div class="table-responsive mt-4">
        <table id="siteCostTable" class="table table-bordered table-striped table-sm">
            <thead class="table-primary">
                <tr>
                    <th>Site</th>
                    <th>Cost (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($site_costs as $site): ?>
                <tr>
                    <td><?= htmlspecialchars($site['name']) ?></td>
                    <td><?= number_format($site['cost'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = $('#siteCostTable').DataTable({
        pageLength: 10,
        ordering: true,
        searching: true,
        info: true
    });

    const ctx = document.getElementById('siteChart').getContext('2d');

    function generateChartDataFromTable() {
        let data = [];
        table.rows({ search: 'applied' }).every(function () {
            const rowData = this.data();
            const name = rowData[0];
            const cost = parseFloat(rowData[1].replace(/,/g, '')) || 0;
            data.push({ name, cost });
        });

        data.sort((a, b) => b.cost - a.cost);
        const top10 = data.slice(0, 10);
        const others = data.slice(10);
        const otherTotal = others.reduce((sum, item) => sum + item.cost, 0);

        const labels = top10.map(item => item.name);
        const values = top10.map(item => item.cost);

        if (otherTotal > 0) {
            labels.push('Others');
            values.push(otherTotal);
        }

        return { labels, values };
    }

    const chartColors = [
        '#3366cc', '#dc3912', '#ff9900', '#109618', '#990099',
        '#0099c6', '#dd4477', '#66aa00', '#b82e2e', '#316395', '#aaaaaa'
    ];

    let chartData = generateChartDataFromTable();

    const chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Cost ₹',
                data: chartData.values,
                backgroundColor: chartColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'right' },
                tooltip: {
                    callbacks: {
                        label: context => {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            return `${label}: ₹${value.toLocaleString()}`;
                        }
                    }
                }
            }
        }
    });

    // Live chart update on search
    $('#siteCostTable_filter input').on('keyup', function () {
        setTimeout(() => {
            const newData = generateChartDataFromTable();
            chart.data.labels = newData.labels;
            chart.data.datasets[0].data = newData.values;
            chart.update();
        }, 300);
    });
});
</script>
</body>
</html>
