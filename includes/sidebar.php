<?php
// Get user info for sidebar
$userName = $_SESSION['full_name'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'Staff';
$userInitial = strtoupper(substr($userName, 0, 1));

// Get current page for active class
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="fas fa-hospital"></i>
        </div>
        <div class="brand-text">
            <h2>HMS</h2>
            <span>Hospital Management</span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul>
            <li class="<?php echo $currentPage == 'index.php' ? 'active' : ''; ?>">
                <a href="index.php">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="<?php echo $currentPage == 'patients.php' ? 'active' : ''; ?>">
                <a href="patients.php">
                    <i class="fas fa-user-injured"></i>
                    <span>Patients</span>
                </a>
            </li>
            <li class="<?php echo $currentPage == 'visits.php' ? 'active' : ''; ?>">
                <a href="visits.php">
                    <i class="fas fa-notes-medical"></i>
                    <span>Visits</span>
                </a>
            </li>
            <li class="<?php echo $currentPage == 'vital_signs.php' ? 'active' : ''; ?>">
                <a href="vital_signs.php">
                    <i class="fas fa-heartbeat"></i>
                    <span>Vital Signs</span>
                </a>
            </li>
            <li class="<?php echo $currentPage == 'appointments.php' ? 'active' : ''; ?>">
                <a href="appointments.php">
                    <i class="fas fa-calendar-check"></i>
                    <span>Appointments</span>
                </a>
            </li>
            <li class="<?php echo $currentPage == 'doctors.php' ? 'active' : ''; ?>">
                <a href="doctors.php">
                    <i class="fas fa-user-md"></i>
                    <span>Doctors</span>
                </a>
            </li>
            <li class="<?php echo $currentPage == 'medical_records.php' ? 'active' : ''; ?>">
                <a href="medical_records.php">
                    <i class="fas fa-notes-medical"></i>
                    <span>Medical Records</span>
                </a>
            </li>
            <li class="<?php echo $currentPage == 'lab.php' ? 'active' : ''; ?>">
                <a href="lab.php">
                    <i class="fas fa-flask"></i>
                    <span>Laboratory</span>
                </a>
            </li>
            <li class="<?php echo $currentPage == 'radiology.php' ? 'active' : ''; ?>">
                <a href="radiology.php">
                    <i class="fas fa-x-ray"></i>
                    <span>Radiology</span>
                </a>
            </li>
            <li class="<?php echo $currentPage == 'bed_management.php' ? 'active' : ''; ?>">
                <a href="bed_management.php">
                    <i class="fas fa-bed"></i>
                    <span>Bed Management</span>
                </a>
            </li>
            <li class="<?php echo $currentPage == 'pharmacy.php' ? 'active' : ''; ?>">
                <a href="pharmacy.php">
                    <i class="fas fa-prescription-bottle"></i>
                    <span>Pharmacy</span>
                </a>
            </li>
            <li class="<?php echo $currentPage == 'billing.php' ? 'active' : ''; ?>">
                <a href="billing.php">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Billing</span>
                </a>
            </li>
            <li class="<?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>">
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="<?php echo $currentPage == 'message.php' ? 'active' : ''; ?>">
                <a href="message.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>SMS Message</span>
                </a>
            </li>
            <li class="<?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>">
                <a href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar" style="background: <?php echo getUserColor($userName); ?>; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; font-size: 14px;">
                <?php echo $userInitial; ?>
            </div>
            <div>
                <h4><?php echo htmlspecialchars($userName); ?></h4>
                <span><?php echo htmlspecialchars($userRole); ?></span>
            </div>
        </div>
        <a href="logout.php" class="logout-btn" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</aside>