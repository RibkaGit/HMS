-- Add lab result notification columns to medical_records table
ALTER TABLE `medical_records`
ADD COLUMN `lab_results_ready` tinyint(1) NOT NULL DEFAULT 0 AFTER `needs_bed`,
ADD COLUMN `lab_results_ready_at` datetime DEFAULT NULL AFTER `lab_results_ready`;
