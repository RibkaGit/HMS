-- Create lookup_materials table
CREATE TABLE IF NOT EXISTS `lookup_materials` (
  `material_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `category` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample materials
INSERT INTO `lookup_materials` (`name`, `description`, `unit`, `unit_price`, `category`, `is_active`) VALUES
('Surgical Gloves', 'Disposable latex surgical gloves, size M', 'pair', 5.00, 'Surgical', 1),
('Surgical Masks', 'Medical grade disposable face masks', 'piece', 2.50, 'Surgical', 1),
('Sterile Gauze Pads', '4x4 inch sterile gauze pads', 'piece', 1.50, 'General', 1),
('Alcohol Swabs', '70% isopropyl alcohol swabs', 'piece', 0.50, 'General', 1),
('Syringes 5ml', 'Disposable syringes with needle, 5ml', 'piece', 3.00, 'Surgical', 1),
('IV Catheter 20G', 'Intravenous catheter, 20 gauge', 'piece', 8.00, 'Surgical', 1),
('Blood Collection Tubes', 'Vacutainer blood collection tubes, EDTA', 'piece', 1.00, 'Lab', 1),
('Thermometer Digital', 'Digital medical thermometer', 'piece', 15.00, 'Equipment', 1),
('Blood Pressure Cuff', 'Adult size blood pressure cuff', 'piece', 25.00, 'Equipment', 1),
('Bandage Roll', 'Cotton elastic bandage roll, 4 inch', 'roll', 4.00, 'General', 1),
('Antiseptic Solution', 'Povidone-iodine antiseptic solution 500ml', 'bottle', 12.00, 'General', 1),
('Sutures Absorbable', 'Absorbable surgical sutures, 3-0', 'pack', 45.00, 'Surgical', 1);
