<?php
session_name('oss_portal');
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Complaints View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
        }
        .chart-container {
            height: 300px;
        }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid mt-3">
    <h3 class="mb-4">Customer Complaints</h3>

    <!-- Search bar -->
    <div class="mb-3">
        <input type="text" id="searchBox" class="form-control" placeholder="Search by Circuit ID or Status...">
    </div>

    <!-- Chart summary -->
    <div class="row chart-container mb-4">
        <canvas id="statusChart"></canvas>
    </div>

    <!-- Table container -->
    <div id="tableContainer" class="table-responsive"></div>

    <!-- Export buttons -->
    <div class="mt-3">
        <button class="btn btn-success" onclick="exportTable('excel')">Export as Excel</button>
        <button class="btn btn-info" onclick="exportTable('csv')">Export as CSV</button>
    </div>
</div>

<!-- Toast & Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
let chart;

function loadData(query = '') {
    $.ajax({
        url: 'complaints_data.php',
        type: 'GET',
        data: { q: query },
        success: function (response) {
            const data = JSON.parse(response);
            renderTable(data.rows);
            updateChart(data.stats);
        },
        error: function () {
            toastr.error('Failed to load data.');
        }
    });
}

function renderTable(rows) {
    let html = '<table class="table table-bordered table-hover table-sm bg-white">';
    html += '<thead class="table-dark"><tr>';
    html += '<th>ID</th><th>Circuit ID</th><th>Docket No</th><th>Status</th><th>Action</th>';
    html += '</tr></thead><tbody>';

    if (rows.length === 0) {
        html += '<tr><td colspan="5" class="text-center">No records found.</td></tr>';
    } else {
        rows.forEach(row => {
            html += `<tr>
                <td>${row.id}</td>
                <td>${row.circuit_id}</td>
                <td>${row.docket_no}</td>
                <td>${row.status}</td>
                <td><button class="btn btn-sm btn-outline-primary" onclick="viewHistory(${row.complaint_id})">View History</button></td>
            </tr>`;
        });
    }

    html += '</tbody></table>';
    $('#tableContainer').html(html);
}

function viewHistory(complaint_id) {
    $.ajax({
        url: 'complaint_history_view.php',
        type: 'GET',
        data: { complaint_id },
        success: function (data) {
            toastr.info(`History Loaded for Complaint ID: ${complaint_id}`);
            const w = window.open('', '_blank');
            w.document.write(data);
        },
        error: function () {
            toastr.error('Could not fetch complaint history.');
        }
    });
}

function updateChart(stats) {
    const labels = Object.keys(stats);
    const counts = Object.values(stats);

    const config = {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Complaints',
                data: counts,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            }
        }
    };

    if (chart) chart.destroy();
    chart = new Chart(document.getElementById('statusChart'), config);
}

function exportTable(type) {
    $.ajax({
        url: 'complaints_export.php',
        type: 'GET',
        data: { format: type },
        xhrFields: { responseType: 'blob' },
        success: function (blob) {
            const a = document.createElement('a');
            a.href = window.URL.createObjectURL(blob);
            a.download = `complaints.${type === 'csv' ? 'csv' : 'xlsx'}`;
            a.click();
        },
        error: function () {
            toastr.error('Export failed.');
        }
    });
}

$('#searchBox').on('keyup', function () {
    const val = $(this).val().trim();
    loadData(val);
});

// Initial load
loadData();
</script>
</body>
</html>
