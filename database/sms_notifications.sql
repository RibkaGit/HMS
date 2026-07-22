-- Run once in phpMyAdmin after selecting the HMS database.
-- Appointment SMS messages are queued here until an SMS provider sends them.

CREATE TABLE IF NOT EXISTS sms_notifications (
  sms_id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  appointment_id int(10) UNSIGNED NOT NULL,
  patient_id int(10) UNSIGNED NOT NULL,
  phone_number varchar(30) NOT NULL,
  message varchar(500) NOT NULL,
  status enum('Pending','Sent','Failed') NOT NULL DEFAULT 'Pending',
  provider_message_id varchar(100) DEFAULT NULL,
  error_message varchar(255) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  sent_at datetime DEFAULT NULL,
  PRIMARY KEY (sms_id),
  KEY idx_sms_status (status),
  KEY idx_sms_appointment (appointment_id),
  CONSTRAINT fk_sms_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (appointment_id),
  CONSTRAINT fk_sms_patient FOREIGN KEY (patient_id) REFERENCES patients (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;