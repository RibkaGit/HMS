<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - PATIENT MANAGEMENT
// ============================================================================

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

// Get user info
$userName = $_SESSION['full_name'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'Staff';
$userInitial = strtoupper(substr($userName, 0, 1));

// Handle different actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$error = '';

// ============================================================================
// CREATE PATIENT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $primaryDoctorId = null;
    
    $data = [
        'patient_code' => generatePatientCode($conn),
        'uhid_date' => !empty($_POST['uhid_date']) ? $_POST['uhid_date'] : date('Y-m-d'),
        'new_born' => isset($_POST['new_born']) ? 1 : 0,
        'title' => sanitizeInput($_POST['title']),
        'first_name' => sanitizeInput($_POST['first_name']),
        'middle_name' => sanitizeInput($_POST['middle_name']),
        'last_name' => sanitizeInput($_POST['last_name']),
        'date_of_birth' => !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null,
        'gender_id' => !empty($_POST['gender']) ? getLookupId($conn, 'lookup_genders', 'name', $_POST['gender']) : null,
        'marital_status' => sanitizeInput($_POST['marital_status']),
        'occupation' => sanitizeInput($_POST['occupation']),
        'language' => sanitizeInput($_POST['language']),
        'religion' => sanitizeInput($_POST['religion']),
        'nationality' => sanitizeInput($_POST['nationality']),
        'phone' => sanitizeInput($_POST['phone']),
        'email' => sanitizeInput($_POST['email']),
        'loyalty_name' => sanitizeInput($_POST['loyalty_name']),
        'loyalty_card_no' => sanitizeInput($_POST['loyalty_card_no']),
        'loyalty_expiry_date' => !empty($_POST['loyalty_expiry_date']) ? $_POST['loyalty_expiry_date'] : null,
        'national_id' => sanitizeInput($_POST['national_id']),
        'blood_group' => sanitizeInput($_POST['blood_group']),
        'identity_type' => sanitizeInput($_POST['identity_type']),
        'visa_validity' => !empty($_POST['visa_validity']) ? $_POST['visa_validity'] : null,
        'address' => sanitizeInput($_POST['address_village']),
        'address_village' => sanitizeInput($_POST['address_village']),
        'province' => sanitizeInput($_POST['province']),
        'district_khan' => sanitizeInput($_POST['district_khan']),
        'commune_sangkat' => sanitizeInput($_POST['commune_sangkat']),
        'postal_code' => sanitizeInput($_POST['postal_code']),
        'postal_address_same' => isset($_POST['postal_address_same']) ? 1 : 0,
        'telephone_2' => sanitizeInput($_POST['telephone_2']),
        'emergency_contact_name' => sanitizeInput($_POST['relative_name']),
        'emergency_contact_phone' => sanitizeInput($_POST['relative_phone']),
        'relative_same_address' => isset($_POST['relative_same_address']) ? 1 : 0,
        'relative_relationship' => sanitizeInput($_POST['relative_relationship']),
        'relative_name' => sanitizeInput($_POST['relative_name']),
        'relative_phone' => sanitizeInput($_POST['relative_phone']),
        'relative_address_village' => sanitizeInput($_POST['relative_address_village']),
        'relative_province' => sanitizeInput($_POST['relative_province']),
        'relative_district_khan' => sanitizeInput($_POST['relative_district_khan']),
        'relative_commune_sangkat' => sanitizeInput($_POST['relative_commune_sangkat']),
        'relative_postal_code' => sanitizeInput($_POST['relative_postal_code']),
        'relative_telephone_2' => sanitizeInput($_POST['relative_telephone_2']),
        'primary_doctor_id' => $primaryDoctorId
    ];
    
    $query = "INSERT INTO patients (
              patient_code, uhid_date, new_born, title, first_name, middle_name, last_name, date_of_birth, 
              gender_id, marital_status, occupation, language, religion, nationality, phone, email, 
              loyalty_name, loyalty_card_no, loyalty_expiry_date, national_id, blood_group, identity_type, 
              visa_validity, address, address_village, province, district_khan, commune_sangkat, postal_code, 
              postal_address_same, telephone_2, emergency_contact_name, emergency_contact_phone, 
              relative_same_address, relative_relationship, relative_name, relative_phone, relative_address_village, 
              relative_province, relative_district_khan, relative_commune_sangkat, relative_postal_code, relative_telephone_2, 
              primary_doctor_id) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssisssssissssssssssssssssssssisssisssssssssi',
        $data['patient_code'],
        $data['uhid_date'],
        $data['new_born'],
        $data['title'],
        $data['first_name'],
        $data['middle_name'],
        $data['last_name'],
        $data['date_of_birth'],
        $data['gender_id'],
        $data['marital_status'],
        $data['occupation'],
        $data['language'],
        $data['religion'],
        $data['nationality'],
        $data['phone'],
        $data['email'],
        $data['loyalty_name'],
        $data['loyalty_card_no'],
        $data['loyalty_expiry_date'],
        $data['national_id'],
        $data['blood_group'],
        $data['identity_type'],
        $data['visa_validity'],
        $data['address'],
        $data['address_village'],
        $data['province'],
        $data['district_khan'],
        $data['commune_sangkat'],
        $data['postal_code'],
        $data['postal_address_same'],
        $data['telephone_2'],
        $data['emergency_contact_name'],
        $data['emergency_contact_phone'],
        $data['relative_same_address'],
        $data['relative_relationship'],
        $data['relative_name'],
        $data['relative_phone'],
        $data['relative_address_village'],
        $data['relative_province'],
        $data['relative_district_khan'],
        $data['relative_commune_sangkat'],
        $data['relative_postal_code'],
        $data['relative_telephone_2'],
        $data['primary_doctor_id']
    );
    
    if ($stmt->execute()) {
        $patientId = $conn->insert_id;
        $visitData = [
            'visit_code' => generateVisitCode($conn),
            'patient_id' => $patientId,
            'visit_type_id' => getLookupId($conn, 'lookup_visit_types', 'name', 'OPD'),
            'department_id' => getLookupId($conn, 'lookup_departments', 'code', 'OPD'),
            'attending_doctor_id' => null,
            'visit_status_id' => getLookupId($conn, 'lookup_visit_statuses', 'name', 'Awaiting Billing'),
            'notes' => 'Automatically created during patient registration'
        ];
        $visitId = createVisit($conn, $visitData);
        if (!$visitId || !addInvoiceCharge($conn, $visitId, 'OPD registration and consultation', 'Consultation', 1, 50.00)) {
            $error = 'Patient was created, but the automatic OPD visit or billing record could not be created.';
        }
        logUserActivity($conn, $_SESSION['user_id'], 'Created Patient', "Created patient: {$data['first_name']} {$data['last_name']}");
        if (!$error) {
            $message = 'Patient registered as OPD and added to billing successfully!';
            header('Location: patients.php?message=' . urlencode($message));
            exit();
        }
    } else {
        $error = 'Failed to create patient. Please try again: ' . $stmt->error;
    }
}

// ============================================================================
// UPDATE PATIENT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $patientId = intval($_POST['patient_id']);
    $primaryDoctorId = null;
    
    $data = [
        'uhid_date' => !empty($_POST['uhid_date']) ? $_POST['uhid_date'] : date('Y-m-d'),
        'new_born' => isset($_POST['new_born']) ? 1 : 0,
        'title' => sanitizeInput($_POST['title']),
        'first_name' => sanitizeInput($_POST['first_name']),
        'middle_name' => sanitizeInput($_POST['middle_name']),
        'last_name' => sanitizeInput($_POST['last_name']),
        'date_of_birth' => !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null,
        'gender_id' => !empty($_POST['gender']) ? getLookupId($conn, 'lookup_genders', 'name', $_POST['gender']) : null,
        'marital_status' => sanitizeInput($_POST['marital_status']),
        'occupation' => sanitizeInput($_POST['occupation']),
        'language' => sanitizeInput($_POST['language']),
        'religion' => sanitizeInput($_POST['religion']),
        'nationality' => sanitizeInput($_POST['nationality']),
        'phone' => sanitizeInput($_POST['phone']),
        'email' => sanitizeInput($_POST['email']),
        'loyalty_name' => sanitizeInput($_POST['loyalty_name']),
        'loyalty_card_no' => sanitizeInput($_POST['loyalty_card_no']),
        'loyalty_expiry_date' => !empty($_POST['loyalty_expiry_date']) ? $_POST['loyalty_expiry_date'] : null,
        'national_id' => sanitizeInput($_POST['national_id']),
        'blood_group' => sanitizeInput($_POST['blood_group']),
        'identity_type' => sanitizeInput($_POST['identity_type']),
        'visa_validity' => !empty($_POST['visa_validity']) ? $_POST['visa_validity'] : null,
        'address' => sanitizeInput($_POST['address_village']),
        'address_village' => sanitizeInput($_POST['address_village']),
        'province' => sanitizeInput($_POST['province']),
        'district_khan' => sanitizeInput($_POST['district_khan']),
        'commune_sangkat' => sanitizeInput($_POST['commune_sangkat']),
        'postal_code' => sanitizeInput($_POST['postal_code']),
        'postal_address_same' => isset($_POST['postal_address_same']) ? 1 : 0,
        'telephone_2' => sanitizeInput($_POST['telephone_2']),
        'emergency_contact_name' => sanitizeInput($_POST['relative_name']),
        'emergency_contact_phone' => sanitizeInput($_POST['relative_phone']),
        'relative_same_address' => isset($_POST['relative_same_address']) ? 1 : 0,
        'relative_relationship' => sanitizeInput($_POST['relative_relationship']),
        'relative_name' => sanitizeInput($_POST['relative_name']),
        'relative_phone' => sanitizeInput($_POST['relative_phone']),
        'relative_address_village' => sanitizeInput($_POST['relative_address_village']),
        'relative_province' => sanitizeInput($_POST['relative_province']),
        'relative_district_khan' => sanitizeInput($_POST['relative_district_khan']),
        'relative_commune_sangkat' => sanitizeInput($_POST['relative_commune_sangkat']),
        'relative_postal_code' => sanitizeInput($_POST['relative_postal_code']),
        'relative_telephone_2' => sanitizeInput($_POST['relative_telephone_2']),
        'primary_doctor_id' => $primaryDoctorId
    ];
    
    $query = "UPDATE patients SET 
              uhid_date = ?, new_born = ?, title = ?, first_name = ?, middle_name = ?, last_name = ?, date_of_birth = ?, 
              gender_id = ?, marital_status = ?, occupation = ?, language = ?, religion = ?, nationality = ?, phone = ?, email = ?, 
              loyalty_name = ?, loyalty_card_no = ?, loyalty_expiry_date = ?, national_id = ?, blood_group = ?, identity_type = ?, 
              visa_validity = ?, address = ?, address_village = ?, province = ?, district_khan = ?, commune_sangkat = ?, postal_code = ?, 
              postal_address_same = ?, telephone_2 = ?, emergency_contact_name = ?, emergency_contact_phone = ?, 
              relative_same_address = ?, relative_relationship = ?, relative_name = ?, relative_phone = ?, relative_address_village = ?, 
              relative_province = ?, relative_district_khan = ?, relative_commune_sangkat = ?, relative_postal_code = ?, relative_telephone_2 = ?, 
              primary_doctor_id = ?
              WHERE patient_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sisssssissssssssssssssssssssisssisssssssssii',
        $data['uhid_date'],
        $data['new_born'],
        $data['title'],
        $data['first_name'],
        $data['middle_name'],
        $data['last_name'],
        $data['date_of_birth'],
        $data['gender_id'],
        $data['marital_status'],
        $data['occupation'],
        $data['language'],
        $data['religion'],
        $data['nationality'],
        $data['phone'],
        $data['email'],
        $data['loyalty_name'],
        $data['loyalty_card_no'],
        $data['loyalty_expiry_date'],
        $data['national_id'],
        $data['blood_group'],
        $data['identity_type'],
        $data['visa_validity'],
        $data['address'],
        $data['address_village'],
        $data['province'],
        $data['district_khan'],
        $data['commune_sangkat'],
        $data['postal_code'],
        $data['postal_address_same'],
        $data['telephone_2'],
        $data['emergency_contact_name'],
        $data['emergency_contact_phone'],
        $data['relative_same_address'],
        $data['relative_relationship'],
        $data['relative_name'],
        $data['relative_phone'],
        $data['relative_address_village'],
        $data['relative_province'],
        $data['relative_district_khan'],
        $data['relative_commune_sangkat'],
        $data['relative_postal_code'],
        $data['relative_telephone_2'],
        $data['primary_doctor_id'],
        $patientId
    );
    
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Updated Patient', "Updated patient ID: {$patientId}");
        $message = 'Patient updated successfully!';
        header('Location: patients.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to update patient. Please try again: ' . $stmt->error;
    }
}

// ============================================================================
// DELETE PATIENT
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $patientId = intval($_GET['id']);
    
    // Check if patient has visits
    $checkQuery = "SELECT COUNT(*) as count FROM visits WHERE patient_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param('i', $patientId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkRow = $checkResult->fetch_assoc();
    
    if ($checkRow['count'] > 0) {
        $error = 'Cannot delete patient with existing visits. Please delete visits first.';
    } else {
        $query = "UPDATE patients SET is_active = 0 WHERE patient_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $patientId);
        if ($stmt->execute()) {
            logUserActivity($conn, $_SESSION['user_id'], 'Deleted Patient', "Deleted patient ID: {$patientId}");
            $message = 'Patient deleted successfully!';
            header('Location: patients.php?message=' . urlencode($message));
            exit();
        } else {
            $error = 'Failed to delete patient. Please try again.';
        }
    }
}

// ============================================================================
// SEARCH PATIENTS
// ============================================================================
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
if ($searchTerm) {
    $patients = searchPatients($conn, $searchTerm);
} else {
    $query = "SELECT p.*, g.name as gender_name,
              (SELECT COUNT(*) FROM visits WHERE patient_id = p.patient_id) as total_visits
              FROM patients p
              LEFT JOIN lookup_genders g ON p.gender_id = g.gender_id
              WHERE p.is_active = 1
              ORDER BY p.patient_id DESC
              LIMIT 50";
    $patients = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

// Get patient for edit
$editPatient = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $patientId = intval($_GET['id']);
    $query = "SELECT p.*, g.name as gender_name
              FROM patients p
              LEFT JOIN lookup_genders g ON p.gender_id = g.gender_id
              WHERE p.patient_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $editPatient = $stmt->get_result()->fetch_assoc();
}

// Get all genders for dropdown
$genders = $conn->query("SELECT * FROM lookup_genders")->fetch_all(MYSQLI_ASSOC);

// Get messages
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - HMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .form-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: <?php echo ($editPatient || isset($_GET['action']) && $_GET['action'] === 'create') ? 'flex' : 'none'; ?>;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
        }
        .form-modal-content {
            background: #fff;
            border-radius: 16px;
            padding: 32px;
            max-width: 1150px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        .form-modal-content .close-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            background: none;
            border: none;
            font-size: 24px;
            color: #94a3b8;
            cursor: pointer;
        }
        .form-modal-content .close-btn:hover {
            color: #ef4444;
        }
        .form-section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin: 24px 0 16px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 4px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #2563eb;
        }
        .form-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        .form-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .form-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .form-checkbox-inline {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 32px;
        }
        .form-checkbox-inline input {
            width: auto;
            cursor: pointer;
        }
        .form-checkbox-inline label {
            margin-bottom: 0;
            cursor: pointer;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-submit:hover {
            background: #1d4ed8;
        }
        .btn-cancel {
            padding: 12px 24px;
            background: #f1f5f9;
            color: #475569;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
        }
        .btn-cancel:hover {
            background: #e2e8f0;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }
        .btn-group .btn-submit {
            flex: 1;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .action-buttons a {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
        }
        .btn-edit {
            background: #dbeafe;
            color: #2563eb;
        }
        .btn-edit:hover {
            background: #bfdbfe;
        }
        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }
        .btn-delete:hover {
            background: #fecaca;
        }
        .btn-assign {
            background: #d1fae5;
            color: #059669;
        }
        .btn-assign:hover {
            background: #a7f3d0;
        }
        .btn-create {
            padding: 10px 20px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-create:hover {
            background: #1d4ed8;
        }
        .search-box-patients {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .search-box-patients input {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            width: 250px;
        }
        .search-box-patients input:focus {
            outline: none;
            border-color: #2563eb;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .doctor-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: #dbeafe;
            color: #2563eb;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        .doctor-badge i {
            font-size: 12px;
        }
        .no-doctor {
            color: #94a3b8;
            font-size: 13px;
        }
        .quick-assign-form {
            display: inline-flex;
            gap: 6px;
            align-items: center;
        }
        .quick-assign-form select {
            padding: 4px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 12px;
            font-family: inherit;
            max-width: 150px;
        }
        .quick-assign-form button {
            padding: 4px 10px;
            background: #22c55e;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-family: inherit;
        }
        .quick-assign-form button:hover {
            background: #16a34a;
        }
        .doctor-cell {
            min-width: 150px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                    <h1>Patient Management</h1>
                </div>
                <div class="top-bar-right">
                    <div class="date-display">
                        <i class="far fa-calendar-alt"></i>
                        <span><?php echo date('F j, Y'); ?></span>
                    </div>
                </div>
            </header>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Actions Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
                <div class="search-box-patients">
                    <form method="GET" action="" style="display: flex; gap: 8px;">
                        <input type="text" name="search" placeholder="Search patients..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button type="submit" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer;">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($searchTerm): ?>
                            <a href="patients.php" style="padding: 8px 16px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <button class="btn-create" onclick="window.location.href='patients.php?action=create'">
                    <i class="fas fa-plus"></i> Add New Patient
                </button>
            </div>

            <!-- Patient Table -->
            <div class="table-card">
                <div class="table-responsive">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Patient Code</th>
                                <th>Name</th>
                                <th>Gender</th>
                                <th>Phone</th>
                                <th>Visits</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($patients)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: #94a3b8; padding: 40px;">
                                        <i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                        No patients found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($patient['patient_code']); ?></strong></td>
                                    <td>
                                        <div class="patient-info">
                                            <div class="avatar" style="background: <?php echo getUserColor($patient['first_name']); ?>; width: 32px; height: 32px; font-size: 12px;">
                                                <?php echo strtoupper(substr($patient['first_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <span class="patient-name"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($patient['gender_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo $patient['total_visits'] ?? 0; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="visits.php?action=create&patient_id=<?php echo $patient['patient_id']; ?>" class="btn-create-action">
                                                <i class="fas fa-calendar-plus"></i> New Visit
                                            </a>
                                            <a href="patients.php?action=edit&id=<?php echo $patient['patient_id']; ?>" class="btn-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="patients.php?action=delete&id=<?php echo $patient['patient_id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this patient?');">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Create/Edit Modal -->
    <div class="form-modal" id="patientModal" style="display: <?php echo ($editPatient || isset($_GET['action']) && $_GET['action'] === 'create') ? 'flex' : 'none'; ?>;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='patients.php'">&times;</button>
            <h2 style="margin-bottom: 24px; color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;"><?php echo $editPatient ? 'Edit Patient Details' : 'Patient Registration'; ?></h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $editPatient ? 'update' : 'create'; ?>">
                <?php if ($editPatient): ?>
                    <input type="hidden" name="patient_id" value="<?php echo $editPatient['patient_id']; ?>">
                <?php endif; ?>
                
                <!-- Top Row: UHID and New Born -->
                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="patient_code">UHID</label>
                        <input type="text" id="patient_code" name="patient_code" readonly value="<?php echo htmlspecialchars($editPatient['patient_code'] ?? 'Auto Generated'); ?>" style="background-color: #f8fafc; font-weight: 600;">
                    </div>
                    <div class="form-group">
                        <label for="uhid_date">UHID Date</label>
                        <input type="date" id="uhid_date" name="uhid_date" value="<?php echo htmlspecialchars($editPatient['uhid_date'] ?? date('Y-m-d')); ?>">
                    </div>
                    <div class="form-checkbox-inline">
                        <input type="checkbox" id="new_born" name="new_born" value="1" <?php echo (isset($editPatient['new_born']) && $editPatient['new_born']) ? 'checked' : ''; ?>>
                        <label for="new_born">New Born</label>
                    </div>
                </div>

                <!-- Personal Details -->
                <div class="form-section-title">
                    <i class="fas fa-user"></i> Personal Details
                </div>
                
                <div class="form-grid-4">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <select id="title" name="title">
                            <option value="">Select Title</option>
                            <?php 
                            $titles = ['Mr.', 'Mrs.', 'Ms.', 'Dr.', 'Sister', 'Brother', 'Master', 'Baby', 'Rev.', 'Hon.'];
                            foreach ($titles as $t) {
                                $selected = (isset($editPatient['title']) && $editPatient['title'] === $t) ? 'selected' : '';
                                echo "<option value=\"$t\" $selected>$t</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($editPatient['first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($editPatient['middle_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($editPatient['last_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid-4">
                    <div class="form-group">
                        <label for="date_of_birth">DOB</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($editPatient['date_of_birth'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Age (Y-M-D)</label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="age_y" readonly placeholder="Y" style="text-align: center; background-color: #f8fafc;">
                            <input type="text" id="age_m" readonly placeholder="M" style="text-align: center; background-color: #f8fafc;">
                            <input type="text" id="age_d" readonly placeholder="D" style="text-align: center; background-color: #f8fafc;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender">
                            <option value="">Select Gender</option>
                            <?php foreach ($genders as $gender): ?>
                                <option value="<?php echo $gender['name']; ?>" <?php echo (isset($editPatient['gender_name']) && $editPatient['gender_name'] === $gender['name']) ? 'selected' : ''; ?>>
                                    <?php echo $gender['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="marital_status">Marital Status</label>
                        <select id="marital_status" name="marital_status">
                            <option value="">Select Status</option>
                            <?php 
                            $statuses = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];
                            foreach ($statuses as $s) {
                                $selected = (isset($editPatient['marital_status']) && $editPatient['marital_status'] === $s) ? 'selected' : '';
                                echo "<option value=\"$s\" $selected>$s</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid-4">
                    <div class="form-group">
                        <label for="occupation">Occupation</label>
                        <input type="text" id="occupation" name="occupation" value="<?php echo htmlspecialchars($editPatient['occupation'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="language">Language</label>
                        <input type="text" id="language" name="language" value="<?php echo htmlspecialchars($editPatient['language'] ?? 'English'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="religion">Religion</label>
                        <select id="religion" name="religion">
                            <option value="">Select Religion</option>
                            <?php 
                            $religions = ['Christianity', 'Islam', 'Buddhism', 'Hinduism', 'Judaism', 'None', 'Other'];
                            foreach ($religions as $r) {
                                $selected = (isset($editPatient['religion']) && $editPatient['religion'] === $r) ? 'selected' : '';
                                echo "<option value=\"$r\" $selected>$r</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="nationality">Nationality</label>
                        <input type="text" id="nationality" name="nationality" value="<?php echo htmlspecialchars($editPatient['nationality'] ?? 'Ethiopia'); ?>">
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($editPatient['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email ID</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($editPatient['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="blood_group">Blood Group</label>
                        <input type="text" id="blood_group" name="blood_group" placeholder="e.g., A+, O-" value="<?php echo htmlspecialchars($editPatient['blood_group'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="loyalty_name">Loyalty Name</label>
                        <select id="loyalty_name" name="loyalty_name">
                            <option value="">Select Loyalty Tier</option>
                            <?php 
                            $tiers = ['Standard', 'Silver', 'Gold', 'Platinum'];
                            foreach ($tiers as $t) {
                                $selected = (isset($editPatient['loyalty_name']) && $editPatient['loyalty_name'] === $t) ? 'selected' : '';
                                echo "<option value=\"$t\" $selected>$t</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="loyalty_card_no">Loyalty Card No.</label>
                        <input type="text" id="loyalty_card_no" name="loyalty_card_no" value="<?php echo htmlspecialchars($editPatient['loyalty_card_no'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="loyalty_expiry_date">Expiry Date</label>
                        <input type="date" id="loyalty_expiry_date" name="loyalty_expiry_date" value="<?php echo htmlspecialchars($editPatient['loyalty_expiry_date'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="identity_type">Identity Type</label>
                        <select id="identity_type" name="identity_type">
                            <option value="">Select Identity Type</option>
                            <?php 
                            $idTypes = ['National ID', 'Passport', 'Driver License', 'Social Security Card'];
                            foreach ($idTypes as $idType) {
                                $selected = (isset($editPatient['identity_type']) && $editPatient['identity_type'] === $idType) ? 'selected' : '';
                                echo "<option value=\"$idType\" $selected>$idType</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="national_id">Identity No.</label>
                        <input type="text" id="national_id" name="national_id" value="<?php echo htmlspecialchars($editPatient['national_id'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="visa_validity">Visa Validity</label>
                        <input type="date" id="visa_validity" name="visa_validity" value="<?php echo htmlspecialchars($editPatient['visa_validity'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Home Address -->
                <div class="form-section-title">
                    <i class="fas fa-home"></i> Home Address
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="address_village">Address/Village</label>
                        <input type="text" id="address_village" name="address_village" value="<?php echo htmlspecialchars($editPatient['address_village'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="province">Province</label>
                        <input type="text" id="province" name="province" list="provinces-list" value="<?php echo htmlspecialchars($editPatient['province'] ?? ''); ?>">
                        <datalist id="provinces-list">
                            <option value="Phnom Penh">
                            <option value="Siem Reap">
                            <option value="Battambang">
                            <option value="Sihanoukville">
                            <option value="Kampot">
                            <option value="Kandal">
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label for="district_khan">District/Khan</label>
                        <input type="text" id="district_khan" name="district_khan" value="<?php echo htmlspecialchars($editPatient['district_khan'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="commune_sangkat">Commune/Sangkat</label>
                        <input type="text" id="commune_sangkat" name="commune_sangkat" value="<?php echo htmlspecialchars($editPatient['commune_sangkat'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="postal_code">Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($editPatient['postal_code'] ?? ''); ?>">
                    </div>
                    <div class="form-checkbox-inline">
                        <input type="checkbox" id="postal_address_same" name="postal_address_same" value="1" <?php echo (isset($editPatient['postal_address_same']) && $editPatient['postal_address_same']) ? 'checked' : ''; ?>>
                        <label for="postal_address_same">Postal Address same as Home</label>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="telephone_2">Telephone 2</label>
                        <input type="text" id="telephone_2" name="telephone_2" value="<?php echo htmlspecialchars($editPatient['telephone_2'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Nearest Relative -->
                <div class="form-section-title">
                    <i class="fas fa-users"></i> Nearest Relative
                </div>

                <div class="form-grid-3">
                    <div class="form-checkbox-inline" style="margin-top: 10px; margin-bottom: 16px; grid-column: span 3;">
                        <input type="checkbox" id="relative_same_address" name="relative_same_address" value="1" <?php echo (isset($editPatient['relative_same_address']) && $editPatient['relative_same_address']) ? 'checked' : ''; ?>>
                        <label for="relative_same_address"><strong>Same address as patient</strong></label>
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="relative_name">Relative Name</label>
                        <input type="text" id="relative_name" name="relative_name" value="<?php echo htmlspecialchars($editPatient['relative_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="relative_relationship">Relationship</label>
                        <select id="relative_relationship" name="relative_relationship">
                            <option value="">Select Relationship</option>
                            <?php 
                            $relations = ['Spouse', 'Parent', 'Child', 'Sibling', 'Grandparent', 'Uncle/Aunt', 'Cousin', 'Friend', 'Guardian', 'Other'];
                            foreach ($relations as $rel) {
                                $selected = (isset($editPatient['relative_relationship']) && $editPatient['relative_relationship'] === $rel) ? 'selected' : '';
                                echo "<option value=\"$rel\" $selected>$rel</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="relative_phone">Telephone</label>
                        <input type="text" id="relative_phone" name="relative_phone" value="<?php echo htmlspecialchars($editPatient['relative_phone'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="relative_address_village">Address/Village</label>
                        <input type="text" id="relative_address_village" name="relative_address_village" value="<?php echo htmlspecialchars($editPatient['relative_address_village'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="relative_province">Province</label>
                        <input type="text" id="relative_province" name="relative_province" value="<?php echo htmlspecialchars($editPatient['relative_province'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="relative_district_khan">District/Khan</label>
                        <input type="text" id="relative_district_khan" name="relative_district_khan" value="<?php echo htmlspecialchars($editPatient['relative_district_khan'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label for="relative_commune_sangkat">Commune/Sangkat</label>
                        <input type="text" id="relative_commune_sangkat" name="relative_commune_sangkat" value="<?php echo htmlspecialchars($editPatient['relative_commune_sangkat'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="relative_postal_code">Postal Code</label>
                        <input type="text" id="relative_postal_code" name="relative_postal_code" value="<?php echo htmlspecialchars($editPatient['relative_postal_code'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="relative_telephone_2">Telephone 2</label>
                        <input type="text" id="relative_telephone_2" name="relative_telephone_2" value="<?php echo htmlspecialchars($editPatient['relative_telephone_2'] ?? ''); ?>">
                    </div>
                </div>

                <div class="btn-group" style="margin-top: 24px;">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <?php echo $editPatient ? 'Update Patient' : 'Register Patient'; ?>
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='patients.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
    <script src="assets/js/patient_registration.js"></script>
</body>
</html>