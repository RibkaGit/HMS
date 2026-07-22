<?php
// reception.php - Reception Dashboard
session_start();

// Check if user is logged in and has reception role
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'reception') {
    header('Location: login.php');
    exit();
}

// Sample data for demonstration
$todayArrivals = [
    ['name' => 'Sarah Chen', 'id' => 'P-1024', 'type' => 'OPD'],
    ['name' => 'Michael Okafor', 'id' => 'P-2101', 'type' => 'ER'],
    ['name' => 'Emma Dubois', 'id' => 'P-0876', 'type' => 'IPD'],
];

$appointments = [
    ['time' => '09:30', 'name' => 'Liam Torres', 'doctor' => 'Dr. Gupta'],
    ['time' => '10:15', 'name' => 'Aisha Khan', 'doctor' => 'Dr. Lee'],
    ['time' => '11:00', 'name' => 'James Miller', 'doctor' => 'Dr. Okafor'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>HMS · Reception Dashboard</title>
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        body {
            background: #f4f7fc;
            padding: 24px;
        }
        .dashboard {
            max-width: 1360px;
            margin: 0 auto;
            background: #f8faff;
            border-radius: 40px;
            padding: 28px 32px 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }
        .dash-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }
        .dash-header h2 {
            font-size: 26px;
            font-weight: 700;
            color: #0b2a3e;
        }
        .dash-header h2 i {
            color: #1f8b8b;
            margin-right: 10px;
        }
        .dash-role-badge {
            background: #dce8f5;
            padding: 6px 20px;
            border-radius: 30px;
            font-weight: 600;
            color: #0b3b5c;
            font-size: 14px;
        }
        .dash-role-badge i {
            margin-right: 6px;
        }
        .dash-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 22px;
        }
        .dash-card {
            background: white;
            border-radius: 28px;
            padding: 22px 24px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
            border: 1px solid #edf2f9;
            transition: 0.2s;
        }
        .dash-card:hover {
            border-color: #cbdae9;
        }
        .card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: #1f3d5a;
            margin-bottom: 12px;
            font-size: 16px;
        }
        .card-title i {
            color: #1f8b8b;
            width: 24px;
        }
        .patient-list {
            list-style: none;
        }
        .patient-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eef4fc;
            font-size: 15px;
        }
        .patient-list li:last-child {
            border-bottom: none;
        }
        .patient-name {
            font-weight: 600;
            color: #0b2a3e;
        }
        .patient-id {
            color: #6f8aa3;
            font-size: 13px;
            margin: 0 8px;
        }
        .badge {
            background: #d7e6f5;
            padding: 2px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            color: #12456b;
        }
        .badge.emergency {
            background: #fde3e0;
            color: #b13a32;
        }
        .badge.ipd {
            background: #e1eefb;
            color: #1d5b8e;
        }
        .stat-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        .stat-chip {
            background: #ecf3fa;
            padding: 6px 18px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 500;
            color: #1f3d5a;
        }
        .stat-chip i {
            margin-right: 6px;
            color: #2e7a7a;
        }
        .quick-action {
            margin-top: 16px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .quick-action .btn-sm {
            background: #eaf1fa;
            border: none;
            padding: 8px 20px;
            border-radius: 40px;
            font-weight: 500;
            font-size: 14px;
            color: #12456b;
            cursor: pointer;
            transition: 0.2s;
        }
        .quick-action .btn-sm:hover {
            background: #d4e2f2;
        }
        .quick-action .btn-sm i {
            margin-right: 6px;
        }
        .back-link {
            margin-top: 28px;
            display: inline-block;
            color: #3d6b8a;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
        }
        .back-link i {
            margin-right: 8px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .logout-btn {
            background: #eef2f7;
            border: none;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 600;
            color: #5a6f84;
            cursor: pointer;
            font-size: 14px;
            transition: 0.2s;
        }
        .logout-btn:hover {
            background: #dce5f0;
        }
        .logout-btn i {
            margin-right: 6px;
        }
        @media (max-width: 680px) {
            .dashboard { padding: 16px; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <div class="dash-header">
        <h2><i class="fas fa-clipboard-list"></i> Reception dashboard</h2>
        <div>
            <span class="dash-role-badge"><i class="fas fa-id-badge"></i> RECEPTION</span>
            <a href="logout.php" class="logout-btn" style="margin-left: 12px; text-decoration: none;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="dash-grid">
        <!-- Today's Arrivals -->
        <div class="dash-card">
            <div class="card-title"><i class="fas fa-user-plus"></i> Today's arrivals</div>
            <ul class="patient-list">
                <?php foreach ($todayArrivals as $patient): ?>
                    <li>
                        <span class="patient-name"><?php echo htmlspecialchars($patient['name']); ?></span>
                        <span class="patient-id">#<?php echo htmlspecialchars($patient['id']); ?></span>
                        <span class="badge <?php echo strtolower($patient['type']) === 'er' ? 'emergency' : (strtolower($patient['type']) === 'ipd' ? 'ipd' : ''); ?>">
                            <?php echo htmlspecialchars($patient['type']); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="quick-action">
                <button class="btn-sm" onclick="alert('📋 New patient registration form (simulated)')"><i class="fas fa-user-plus"></i> Register</button>
                <button class="btn-sm" onclick="alert('🔍 Search patient by ID/name')"><i class="fas fa-search"></i> Find</button>
            </div>
        </div>

        <!-- Appointments -->
        <div class="dash-card">
            <div class="card-title"><i class="fas fa-calendar-check"></i> Appointments</div>
            <ul class="patient-list">
                <?php foreach ($appointments as $apt): ?>
                    <li>
                        <span><?php echo htmlspecialchars($apt['time']); ?> – <?php echo htmlspecialchars($apt['name']); ?></span>
                        <span class="badge"><?php echo htmlspecialchars($apt['doctor']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="stat-row">
                <span class="stat-chip"><i class="fas fa-clock"></i> 3 scheduled</span>
                <span class="stat-chip"><i class="fas fa-user-check"></i> 2 checked in</span>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="dash-card">
            <div class="card-title"><i class="fas fa-hospital-user"></i> Quick stats</div>
            <div>
                <span class="stat-chip"><i class="fas fa-procedures"></i> 8 OPD visits</span>
                <span class="stat-chip"><i class="fas fa-bed"></i> 4 IPD</span>
            </div>
            <div style="margin-top:12px;">
                <span class="stat-chip"><i class="fas fa-ambulance"></i> 2 ER active</span>
                <span class="stat-chip"><i class="fas fa-file-invoice"></i> 5 bills</span>
            </div>
            <div style="margin-top:16px; background:#eef5fc; padding:10px 14px; border-radius:30px; font-size:14px; color:#12456b;">
                <i class="fas fa-phone-alt"></i> +251 911 234 567 (reception)
            </div>
            <div style="margin-top:12px; background:#eef5fc; padding:10px 14px; border-radius:30px; font-size:14px; color:#12456b;">
                <i class="fas fa-user"></i> Logged in as: <?php echo htmlspecialchars($_SESSION['username'] ?? 'Reception User'); ?>
            </div>
        </div>
    </div>

    <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to home</a>
</div>
</body>
</html>