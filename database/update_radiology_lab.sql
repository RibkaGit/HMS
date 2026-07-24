USE hms_db;

-- Add attachment_path to lab_results for Radiology uploads
ALTER TABLE lab_results ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255) NULL AFTER result_notes;

-- Add sub_category to lookup_test_types for grouping
ALTER TABLE lookup_test_types ADD COLUMN IF NOT EXISTS sub_category VARCHAR(60) NULL AFTER category;

-- Deactivate old generic tests to make way for detailed ones (optional, but keeps UI clean)
UPDATE lookup_test_types SET is_active = 0;

-- Insert Radiology Tests (X-Ray)
INSERT INTO lookup_test_types (name, category, sub_category, price, is_active) VALUES
('Chest X-Ray', 'Radiology', 'X-Ray', 100.00, 1),
('Leg X-Ray (Tibia/Fibula)', 'Radiology', 'X-Ray', 90.00, 1),
('Hand/Wrist X-Ray', 'Radiology', 'X-Ray', 80.00, 1),
('Spine X-Ray', 'Radiology', 'X-Ray', 120.00, 1),
('Pelvis X-Ray', 'Radiology', 'X-Ray', 110.00, 1),
('Abdomen X-Ray', 'Radiology', 'X-Ray', 100.00, 1);

-- Insert Radiology Tests (CT Scan)
INSERT INTO lookup_test_types (name, category, sub_category, price, is_active) VALUES
('Head CT Scan', 'Radiology', 'CT Scan', 350.00, 1),
('Chest CT Scan', 'Radiology', 'CT Scan', 400.00, 1),
('Abdomen/Pelvis CT Scan', 'Radiology', 'CT Scan', 450.00, 1),
('Spine CT Scan', 'Radiology', 'CT Scan', 400.00, 1);

-- Insert Radiology Tests (MRI)
INSERT INTO lookup_test_types (name, category, sub_category, price, is_active) VALUES
('Brain MRI', 'Radiology', 'MRI', 600.00, 1),
('Spine MRI', 'Radiology', 'MRI', 650.00, 1),
('Knee MRI', 'Radiology', 'MRI', 550.00, 1),
('Shoulder MRI', 'Radiology', 'MRI', 550.00, 1);

-- Insert Radiology Tests (Ultrasound)
INSERT INTO lookup_test_types (name, category, sub_category, price, is_active) VALUES
('Abdominal Ultrasound', 'Radiology', 'Ultrasound', 150.00, 1),
('Pelvic Ultrasound', 'Radiology', 'Ultrasound', 150.00, 1),
('Obstetric Ultrasound', 'Radiology', 'Ultrasound', 180.00, 1),
('Thyroid Ultrasound', 'Radiology', 'Ultrasound', 130.00, 1);

-- Insert Laboratory Tests (Panels)
INSERT INTO lookup_test_types (name, category, sub_category, price, is_active) VALUES
('Basic Metabolic Panel SST', 'Laboratory', 'Panels', 45.00, 1),
('Comprehensive Metabolic Panel SST', 'Laboratory', 'Panels', 60.00, 1),
('Electrolyte Panel SST', 'Laboratory', 'Panels', 35.00, 1),
('Hepatic Panel SST', 'Laboratory', 'Panels', 50.00, 1),
('Lipid Panel SST', 'Laboratory', 'Panels', 40.00, 1),
('Renal Panel SST', 'Laboratory', 'Panels', 45.00, 1);

-- Insert Laboratory Tests (Individual Tests)
INSERT INTO lookup_test_types (name, category, sub_category, price, is_active) VALUES
('Albumin SST', 'Laboratory', 'Individual Tests', 20.00, 1),
('ALT (SGPT) SST', 'Laboratory', 'Individual Tests', 25.00, 1),
('AST (SGOT) SST', 'Laboratory', 'Individual Tests', 25.00, 1),
('Bilirubin, Total SST', 'Laboratory', 'Individual Tests', 20.00, 1),
('Calcium SST', 'Laboratory', 'Individual Tests', 15.00, 1),
('Cholesterol, Total SST', 'Laboratory', 'Individual Tests', 20.00, 1),
('Glucose SST', 'Laboratory', 'Individual Tests', 15.00, 1),
('Magnesium SST', 'Laboratory', 'Individual Tests', 20.00, 1),
('Potassium SST', 'Laboratory', 'Individual Tests', 15.00, 1),
('TSH SST', 'Laboratory', 'Individual Tests', 35.00, 1),
('Free T4 SST', 'Laboratory', 'Individual Tests', 40.00, 1),
('Vitamin B12 SST', 'Laboratory', 'Individual Tests', 45.00, 1),
('Vitamin D, 1, 25-Dihydroxy SST', 'Laboratory', 'Individual Tests', 55.00, 1),
('Iron, Total SST', 'Laboratory', 'Individual Tests', 25.00, 1),
('Hemoglobin A1c PRPL', 'Laboratory', 'Individual Tests', 30.00, 1);

-- Insert Laboratory Tests (Microbiology)
INSERT INTO lookup_test_types (name, category, sub_category, price, is_active) VALUES
('Urine Culture', 'Laboratory', 'Microbiology', 40.00, 1),
('Blood Culture', 'Laboratory', 'Microbiology', 50.00, 1),
('Wound Culture', 'Laboratory', 'Microbiology', 45.00, 1),
('Strep Screen SWAB', 'Laboratory', 'Microbiology', 25.00, 1),
('Influenza Screen SWAB', 'Laboratory', 'Microbiology', 30.00, 1);

-- Insert Laboratory Tests (Cytology)
INSERT INTO lookup_test_types (name, category, sub_category, price, is_active) VALUES
('PAP Smear SUREPATH', 'Laboratory', 'Cytology', 70.00, 1),
('HPV Reflex', 'Laboratory', 'Cytology', 85.00, 1);
