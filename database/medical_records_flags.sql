-- Add lab/bed referral flags to medical records
ALTER TABLE `medical_records`
  ADD COLUMN IF NOT EXISTS `needs_lab` tinyint(1) NOT NULL DEFAULT 0 AFTER `clinical_notes`,
  ADD COLUMN IF NOT EXISTS `needs_bed` tinyint(1) NOT NULL DEFAULT 0 AFTER `needs_lab`;
