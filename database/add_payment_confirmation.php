<?php
// ============================================================================
// ADD PAYMENT CONFIRMATION FIELDS
// ============================================================================

require_once __DIR__ . '/../config/database.php';

// Add payment confirmation field to patients table
$alterPatients = "ALTER TABLE patients ADD COLUMN payment_confirmed TINYINT(1) DEFAULT 0 AFTER is_active";
if ($conn->query($alterPatients)) {
    echo "SUCCESS: Added payment_confirmed field to patients table<br>";
} else {
    echo "ERROR: Failed to add payment_confirmed to patients: " . $conn->error . "<br>";
}

// Add sample_id field to lab_orders table
$alterLabOrders = "ALTER TABLE lab_orders ADD COLUMN sample_id VARCHAR(30) AFTER order_status_id";
if ($conn->query($alterLabOrders)) {
    echo "SUCCESS: Added sample_id field to lab_orders table<br>";
} else {
    echo "ERROR: Failed to add sample_id to lab_orders: " . $conn->error . "<br>";
}

// Add mr_id field to visits table (Medical Record ID)
$alterVisits = "ALTER TABLE visits ADD COLUMN mr_id VARCHAR(30) AFTER visit_code";
if ($conn->query($alterVisits)) {
    echo "SUCCESS: Added mr_id field to visits table<br>";
} else {
    echo "ERROR: Failed to add mr_id to visits: " . $conn->error . "<br>";
}

// Add needs_pharmacy field to medical_records table
$checkPharmacyField = "SHOW COLUMNS FROM medical_records LIKE 'needs_pharmacy'";
$result = $conn->query($checkPharmacyField);
if ($result->num_rows == 0) {
    $alterMedicalRecords = "ALTER TABLE medical_records ADD COLUMN needs_pharmacy TINYINT(1) DEFAULT 0 AFTER needs_bed";
    if ($conn->query($alterMedicalRecords)) {
        echo "SUCCESS: Added needs_pharmacy field to medical_records table<br>";
    } else {
        echo "ERROR: Failed to add needs_pharmacy to medical_records: " . $conn->error . "<br>";
    }
} else {
    echo "INFO: needs_pharmacy field already exists in medical_records table<br>";
}

// Add lab_results_ready field to medical_records table
$checkLabResultsField = "SHOW COLUMNS FROM medical_records LIKE 'lab_results_ready'";
$result = $conn->query($checkLabResultsField);
if ($result->num_rows == 0) {
    $alterMedicalRecords2 = "ALTER TABLE medical_records ADD COLUMN lab_results_ready TINYINT(1) DEFAULT 0 AFTER needs_pharmacy, 
                              ADD COLUMN lab_results_ready_at DATETIME AFTER lab_results_ready";
    if ($conn->query($alterMedicalRecords2)) {
        echo "SUCCESS: Added lab_results_ready fields to medical_records table<br>";
    } else {
        echo "ERROR: Failed to add lab_results_ready to medical_records: " . $conn->error . "<br>";
    }
} else {
    echo "INFO: lab_results_ready field already exists in medical_records table<br>";
}

// Add sub_category field to lookup_test_types table
$checkSubCategory = "SHOW COLUMNS FROM lookup_test_types LIKE 'sub_category'";
$result = $conn->query($checkSubCategory);
if ($result->num_rows == 0) {
    $alterTestTypes = "ALTER TABLE lookup_test_types ADD COLUMN sub_category VARCHAR(100) AFTER category";
    if ($conn->query($alterTestTypes)) {
        echo "SUCCESS: Added sub_category field to lookup_test_types table<br>";
    } else {
        echo "ERROR: Failed to add sub_category to lookup_test_types: " . $conn->error . "<br>";
    }
} else {
    echo "INFO: sub_category field already exists in lookup_test_types table<br>";
}

// Add dispensed_by and dispensed_at to prescriptions table
$checkDispensed = "SHOW COLUMNS FROM prescriptions LIKE 'dispensed_by'";
$result = $conn->query($checkDispensed);
if ($result->num_rows == 0) {
    $alterPrescriptions = "ALTER TABLE prescriptions ADD COLUMN dispensed_by INT(10) UNSIGNED AFTER status, 
                           ADD COLUMN dispensed_at DATETIME AFTER dispensed_by";
    if ($conn->query($alterPrescriptions)) {
        echo "SUCCESS: Added dispensed fields to prescriptions table<br>";
    } else {
        echo "ERROR: Failed to add dispensed fields to prescriptions: " . $conn->error . "<br>";
    }
} else {
    echo "INFO: dispensed fields already exist in prescriptions table<br>";
}

echo "<br>Database migration completed!";
