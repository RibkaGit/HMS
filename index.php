<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Get dashboard statistics
$stats = getDashboardStats($conn);
$recentPatients = getRecentPatients($conn);
$todayAppointments = getTodayAppointments($conn);
$pendingLabOrders = getPendingLabOrders($conn);
$lowStockMedications = getLowStockMedications($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>Dashboard</h1>
                </div>
                <div class="top-bar-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search patients, visits...">
                    </div>
                    <div class="notifications">
                        <i class="fas fa-bell"></i>
                        <span class="badge">5</span>
                    </div>
                    <div class="date-display">
                        <i class="far fa-calendar-alt"></i>
                        <span><?php echo date('F j, Y'); ?></span>
                    </div>
                </div>
            </header>

            <!-- Stats Cards -->
            <section class="stats-grid">
                <div class="stat-card stat-patients">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_patients']); ?></h3>
                        <p>Total Patients</p>
                        <span class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> <?php echo $stats['patients_growth']; ?>%
                        </span>
                    </div>
                </div>

                <div class="stat-card stat-visits">
                    <div class="stat-icon">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['today_visits']); ?></h3>
                        <p>Today's Visits</p>
                        <span class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> <?php echo $stats['visits_growth']; ?>%
                        </span>
                    </div>
                </div>

                <div class="stat-card stat-appointments">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['today_appointments']); ?></h3>
                        <p>Today's Appointments</p>
                        <span class="stat-change <?php echo $stats['appointments_change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo $stats['appointments_change'] >= 0 ? 'up' : 'down'; ?>"></i> 
                            <?php echo abs($stats['appointments_change']); ?>%
                        </span>
                    </div>
                </div>

                <div class="stat-card stat-revenue">
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($stats['today_revenue'], 2); ?></h3>
                        <p>Today's Revenue</p>
                        <span class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> <?php echo $stats['revenue_growth']; ?>%
                        </span>
                    </div>
                </div>
            </section>

            <!-- Charts and Recent Activity -->
            <section class="dashboard-grid">
                <!-- Chart Card -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3>Patient Visits Overview</h3>
                        <div class="card-actions">
                            <select id="chartPeriod">
                                <option value="week">This Week</option>
                                <option value="month" selected>This Month</option>
                                <option value="year">This Year</option>
                            </select>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="visitsChart"></canvas>
                    </div>
                </div>

                <!-- Recent Patients -->
                <div class="table-card">
                    <div class="card-header">
                        <h3>Recent Patients</h3>
                        <a href="patients.php" class="view-all">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Visit Type</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPatients as $patient): ?>
                                <tr>
                                    <td>
                                        <div class="patient-info">
                                            <div class="avatar">
                                                <?php echo strtoupper(substr($patient['first_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <span class="patient-name"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></span>
                                                <small><?php echo htmlspecialchars($patient['patient_code']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($patient['visit_type'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo getStatusColor($patient['visit_status'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($patient['visit_status'] ?? 'Unknown'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $patient['admitted_at'] ? date('M d, Y', strtotime($patient['admitted_at'])) : 'N/A'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Alerts and Quick Actions -->
            <section class="alert-grid">
                <!-- Pending Lab Orders -->
                <div class="alert-card">
                    <div class="alert-icon">
                        <i class="fas fa-flask"></i>
                    </div>
                    <div class="alert-content">
                        <h4>Pending Lab Orders</h4>
                        <p><?php echo $pendingLabOrders; ?> tests awaiting processing</p>
                    </div>
                    <a href="lab.php" class="alert-action">View</a>
                </div>

                <!-- Low Stock Medications -->
                <div class="alert-card warning">
                    <div class="alert-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="alert-content">
                        <h4>Low Stock Medications</h4>
                        <p><?php echo $lowStockMedications; ?> items need reorder</p>
                    </div>
                    <a href="pharmacy.php" class="alert-action">Reorder</a>
                </div>

                <!-- Today's Appointments -->
                <div class="alert-card info">
                    <div class="alert-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="alert-content">
                        <h4>Today's Appointments</h4>
                        <p><?php echo $todayAppointments; ?> appointments scheduled</p>
                    </div>
                    <a href="appointments.php" class="alert-action">Schedule</a>
                </div>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>