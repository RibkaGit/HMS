<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - REPORTS
// ============================================================================

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$userName = $_SESSION['full_name'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'Staff';
$userInitial = strtoupper(substr($userName, 0, 1));

// Get date range
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// ============================================================================
// PATIENT STATISTICS
// ============================================================================
$patientStats = [];
$patientQuery = "SELECT 
                  COUNT(*) as total,
                  SUM(CASE WHEN gender_id = (SELECT gender_id FROM lookup_genders WHERE name = 'Male') THEN 1 ELSE 0 END) as male,
                  SUM(CASE WHEN gender_id = (SELECT gender_id FROM lookup_genders WHERE name = 'Female') THEN 1 ELSE 0 END) as female,
                  SUM(CASE WHEN YEAR(registered_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as this_year
                  FROM patients WHERE is_active = 1";
$patientResult = $conn->query($patientQuery);
$patientStats = $patientResult->fetch_assoc();

// ============================================================================
// VISIT STATISTICS
// ============================================================================
$visitStats = [];
$visitQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN visit_type_id = (SELECT visit_type_id FROM lookup_visit_types WHERE name = 'OPD') THEN 1 ELSE 0 END) as opd,
                SUM(CASE WHEN visit_type_id = (SELECT visit_type_id FROM lookup_visit_types WHERE name = 'IPD') THEN 1 ELSE 0 END) as ipd,
                SUM(CASE WHEN visit_type_id = (SELECT visit_type_id FROM lookup_visit_types WHERE name = 'Emergency') THEN 1 ELSE 0 END) as emergency
                FROM visits 
                WHERE DATE(admitted_at) BETWEEN ? AND ?";
$stmt = $conn->prepare($visitQuery);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$visitStats = $stmt->get_result()->fetch_assoc();

// ============================================================================
// REVENUE STATISTICS
// ============================================================================
$revenueStats = [];
$revenueQuery = "SELECT 
                   COALESCE(SUM(total), 0) as total_revenue,
                   COALESCE(SUM(CASE WHEN invoice_status_id = (SELECT invoice_status_id FROM lookup_invoice_statuses WHERE name = 'Paid') THEN total ELSE 0 END), 0) as collected,
                   COALESCE(SUM(CASE WHEN invoice_status_id = (SELECT invoice_status_id FROM lookup_invoice_statuses WHERE name = 'Unpaid') THEN total ELSE 0 END), 0) as outstanding
                   FROM invoices 
                   WHERE DATE(created_at) BETWEEN ? AND ?";
$stmt = $conn->prepare($revenueQuery);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$revenueStats = $stmt->get_result()->fetch_assoc();

// ============================================================================
// MONTHLY TRENDS
// ============================================================================
$monthlyTrends = [];
$trendQuery = "SELECT 
                 DATE_FORMAT(admitted_at, '%Y-%m') as month,
                 COUNT(*) as visits,
                 COUNT(DISTINCT patient_id) as unique_patients
                 FROM visits 
                 WHERE DATE(admitted_at) BETWEEN ? AND ?
                 GROUP BY DATE_FORMAT(admitted_at, '%Y-%m')
                 ORDER BY month";
$stmt = $conn->prepare($trendQuery);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$monthlyTrends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// TOP DOCTORS
// ============================================================================
$topDoctors = [];
$doctorQuery = "SELECT 
                  CONCAT(s.first_name, ' ', s.last_name) as doctor_name,
                  COUNT(v.visit_id) as visit_count,
                  COUNT(DISTINCT v.patient_id) as patient_count
                  FROM visits v
                  JOIN staff s ON v.attending_doctor_id = s.staff_id
                  WHERE v.attending_doctor_id IS NOT NULL
                  AND DATE(v.admitted_at) BETWEEN ? AND ?
                  GROUP BY v.attending_doctor_id
                  ORDER BY visit_count DESC
                  LIMIT 10";
$stmt = $conn->prepare($doctorQuery);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$topDoctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// DEPARTMENT STATISTICS
// ============================================================================
$deptStats = [];
$deptQuery = "SELECT 
                d.name as department,
                COUNT(v.visit_id) as visit_count
                FROM visits v
                JOIN lookup_departments d ON v.department_id = d.department_id
                WHERE DATE(v.admitted_at) BETWEEN ? AND ?
                GROUP BY v.department_id
                ORDER BY visit_count DESC";
$stmt = $conn->prepare($deptQuery);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$deptStats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// MEDICATION USAGE - FIXED VERSION
// ============================================================================
$medicationStats = [];
$medQuery = "SELECT 
               m.name as medication_name,
               SUM(pi.quantity) as total_dispensed
               FROM prescription_items pi
               JOIN medications m ON pi.medication_id = m.medication_id
               JOIN prescriptions p ON pi.prescription_id = p.prescription_id
               WHERE p.status = 'Dispensed'
               AND DATE(p.prescribed_at) BETWEEN ? AND ?
               GROUP BY pi.medication_id
               ORDER BY total_dispensed DESC
               LIMIT 10";
$stmt = $conn->prepare($medQuery);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$medicationStats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// GENDER DISTRIBUTION
// ============================================================================
$genderDistribution = [];
$genderQuery = "SELECT 
                  g.name as gender,
                  COUNT(p.patient_id) as count
                  FROM patients p
                  JOIN lookup_genders g ON p.gender_id = g.gender_id
                  WHERE p.is_active = 1
                  GROUP BY p.gender_id";
$genderResult = $conn->query($genderQuery);
$genderDistribution = $genderResult->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// VISIT STATUS DISTRIBUTION
// ============================================================================
$visitStatusDistribution = [];
$statusQuery = "SELECT 
                  vs.name as status,
                  COUNT(v.visit_id) as count
                  FROM visits v
                  JOIN lookup_visit_statuses vs ON v.visit_status_id = vs.visit_status_id
                  WHERE DATE(v.admitted_at) BETWEEN ? AND ?
                  GROUP BY v.visit_status_id";
$stmt = $conn->prepare($statusQuery);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$visitStatusDistribution = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// MONTHLY REVENUE TRENDS
// ============================================================================
$monthlyRevenue = [];
$revTrendQuery = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COALESCE(SUM(total), 0) as revenue
                    FROM invoices 
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                    ORDER BY month";
$stmt = $conn->prepare($revTrendQuery);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$monthlyRevenue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - HMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .report-card {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .report-card .report-title {
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .report-card .report-value {
            font-size: 32px;
            font-weight: 700;
            color: #0f172a;
        }
        .report-card .report-sub {
            font-size: 14px;
            color: #64748b;
            margin-top: 4px;
        }
        .report-card .report-detail {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }
        .report-card .report-detail .detail-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            padding: 4px 0;
        }
        .report-card .report-detail .detail-row .label {
            color: #64748b;
        }
        .report-card .report-detail .detail-row .value {
            font-weight: 500;
            color: #0f172a;
        }
        .filter-bar {
            display: flex;
            gap: 12px;
            align-items: end;
            flex-wrap: wrap;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 28px;
        }
        .filter-bar .form-group {
            margin-bottom: 0;
        }
        .filter-bar label {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            display: block;
            margin-bottom: 4px;
        }
        .filter-bar input {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        .filter-bar input:focus {
            outline: none;
            border-color: #2563eb;
        }
        .filter-bar .btn-filter {
            padding: 8px 24px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
        }
        .filter-bar .btn-filter:hover {
            background: #1d4ed8;
        }
        .report-section {
            margin-bottom: 32px;
        }
        .report-section h2 {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 16px;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .report-table th {
            background: #f8fafc;
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }
        .report-table td {
            padding: 10px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        .report-table tr:last-child td {
            border-bottom: none;
        }
        .report-table tr:hover td {
            background: #f8fafc;
        }
        .chart-container-report {
            height: 300px;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 20px;
            margin-bottom: 20px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
            .grid-3 {
                grid-template-columns: 1fr;
            }
            .report-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 480px) {
            .report-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
<?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                    <h1>Reports & Analytics</h1>
                </div>
                <div class="top-bar-right">
                    <div class="date-display">
                        <i class="far fa-calendar-alt"></i>
                        <span><?php echo date('F j, Y'); ?></span>
                    </div>
                </div>
            </header>

            <!-- Date Filter -->
            <div class="filter-bar">
                <form method="GET" action="" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: end;">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                </form>
            </div>

            <!-- Overview Stats -->
            <div class="report-grid">
                <div class="report-card">
                    <div class="report-title">Total Patients</div>
                    <div class="report-value"><?php echo number_format($patientStats['total'] ?? 0); ?></div>
                    <div class="report-sub">
                        <i class="fas fa-user-plus"></i> <?php echo $patientStats['this_year'] ?? 0; ?> new this year
                    </div>
                    <div class="report-detail">
                        <div class="detail-row">
                            <span class="label">Male</span>
                            <span class="value"><?php echo $patientStats['male'] ?? 0; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Female</span>
                            <span class="value"><?php echo $patientStats['female'] ?? 0; ?></span>
                        </div>
                    </div>
                </div>

                <div class="report-card">
                    <div class="report-title">Total Visits</div>
                    <div class="report-value"><?php echo number_format($visitStats['total'] ?? 0); ?></div>
                    <div class="report-sub">
                        <i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?>
                    </div>
                    <div class="report-detail">
                        <div class="detail-row">
                            <span class="label">OPD</span>
                            <span class="value"><?php echo $visitStats['opd'] ?? 0; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">IPD</span>
                            <span class="value"><?php echo $visitStats['ipd'] ?? 0; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Emergency</span>
                            <span class="value"><?php echo $visitStats['emergency'] ?? 0; ?></span>
                        </div>
                    </div>
                </div>

                <div class="report-card">
                    <div class="report-title">Revenue</div>
                    <div class="report-value">Birr <?php echo number_format($revenueStats['total_revenue'] ?? 0, 2); ?></div>
                    <div class="report-sub">Total billed amount</div>
                    <div class="report-detail">
                        <div class="detail-row">
                            <span class="label">Collected</span>
                            <span class="value" style="color: #059669;">Birr <?php echo number_format($revenueStats['collected'] ?? 0, 2); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Outstanding</span>
                            <span class="value" style="color: #dc2626;">Birr <?php echo number_format($revenueStats['outstanding'] ?? 0, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid-2">
                <div class="report-section">
                    <h2>Monthly Trends - Visits</h2>
                    <div class="chart-container-report">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>
                <div class="report-section">
                    <h2>Monthly Revenue</h2>
                    <div class="chart-container-report">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Gender & Status Distribution -->
            <div class="grid-3">
                <div class="report-section">
                    <h2>Gender Distribution</h2>
                    <div class="chart-container-report" style="height: 250px;">
                        <canvas id="genderChart"></canvas>
                    </div>
                </div>
                <div class="report-section">
                    <h2>Visit Status</h2>
                    <div class="chart-container-report" style="height: 250px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                <div class="report-section">
                    <h2>Department Distribution</h2>
                    <div class="chart-container-report" style="height: 250px;">
                        <canvas id="deptChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Doctors & Medications -->
            <div class="grid-2">
                <div class="report-section">
                    <h2>Top Doctors</h2>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Doctor</th>
                                <th>Visits</th>
                                <th>Patients</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topDoctors)): ?>
                                <tr><td colspan="3" style="text-align: center; color: #94a3b8;">No data available</td></tr>
                            <?php else: ?>
                                <?php foreach ($topDoctors as $doctor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($doctor['doctor_name']); ?></td>
                                    <td><?php echo $doctor['visit_count']; ?></td>
                                    <td><?php echo $doctor['patient_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="report-section">
                    <h2>Top Medications Dispensed</h2>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Medication</th>
                                <th>Total Dispensed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($medicationStats)): ?>
                                <tr><td colspan="2" style="text-align: center; color: #94a3b8;">No data available</td></tr>
                            <?php else: ?>
                                <?php foreach ($medicationStats as $med): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($med['medication_name']); ?></td>
                                    <td><?php echo $med['total_dispensed']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Monthly Trends Chart - Visits
        const trendsData = <?php echo json_encode($monthlyTrends); ?>;
        const ctx1 = document.getElementById('trendsChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: trendsData.map(d => d.month),
                datasets: [{
                    label: 'Total Visits',
                    data: trendsData.map(d => d.visits),
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Unique Patients',
                    data: trendsData.map(d => d.unique_patients),
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { size: 11 }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });

        // Monthly Revenue Chart
        const revenueData = <?php echo json_encode($monthlyRevenue); ?>;
        const ctx2 = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: revenueData.map(d => d.month),
                datasets: [{
                    label: 'Revenue ($)',
                    data: revenueData.map(d => d.revenue),
                    backgroundColor: 'rgba(37, 99, 235, 0.7)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 2,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { size: 11 }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(0);
                            }
                        }
                    }
                }
            }
        });

        // Gender Distribution Chart
        const genderData = <?php echo json_encode($genderDistribution); ?>;
        const ctx3 = document.getElementById('genderChart').getContext('2d');
        new Chart(ctx3, {
            type: 'doughnut',
            data: {
                labels: genderData.map(d => d.gender),
                datasets: [{
                    data: genderData.map(d => d.count),
                    backgroundColor: ['#2563eb', '#ec4899', '#8b5cf6'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: { size: 12 }
                        }
                    }
                }
            }
        });

        // Visit Status Distribution Chart
        const statusData = <?php echo json_encode($visitStatusDistribution); ?>;
        const ctx4 = document.getElementById('statusChart').getContext('2d');
        new Chart(ctx4, {
            type: 'doughnut',
            data: {
                labels: statusData.map(d => d.status),
                datasets: [{
                    data: statusData.map(d => d.count),
                    backgroundColor: ['#2563eb', '#f59e0b', '#8b5cf6', '#22c55e', '#ef4444', '#64748b'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: { size: 11 }
                        }
                    }
                }
            }
        });

        // Department Distribution Chart
        const deptData = <?php echo json_encode($deptStats); ?>;
        const ctx5 = document.getElementById('deptChart').getContext('2d');
        new Chart(ctx5, {
            type: 'bar',
            data: {
                labels: deptData.map(d => d.department),
                datasets: [{
                    label: 'Visits',
                    data: deptData.map(d => d.visit_count),
                    backgroundColor: [
                        'rgba(37, 99, 235, 0.7)',
                        'rgba(139, 92, 246, 0.7)',
                        'rgba(34, 197, 94, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(6, 182, 212, 0.7)',
                        'rgba(236, 72, 153, 0.7)'
                    ],
                    borderColor: [
                        '#2563eb', '#8b5cf6', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899'
                    ],
                    borderWidth: 2,
                    borderRadius: 4
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
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    </script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>