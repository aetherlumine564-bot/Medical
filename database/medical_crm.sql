-- ============================================================
--  MedCRM v2 – Sistema de Administración Médica
--  Incluye: Google OAuth + WhatsApp CallMeBot + Recordatorios
--  Base de Datos: medical_crm
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `medical_crm`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `medical_crm`;

-- ----------------------------
--  Tabla: roles
-- ----------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(80)  NOT NULL,
  `slug`        VARCHAR(80)  NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `color`       VARCHAR(20)  DEFAULT '#0891B2',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`,`name`,`slug`,`description`,`color`) VALUES
(1, 'Administrador', 'admin',   'Acceso total al sistema',          '#083344'),
(2, 'Doctor',        'doctor',  'Gestión de citas y consultas',     '#0891B2'),
(3, 'Paciente',      'patient', 'Reserva y seguimiento de citas',   '#0D9488');

-- ----------------------------
--  Tabla: permissions
-- ----------------------------
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `slug`       VARCHAR(100) NOT NULL,
  `module`     VARCHAR(60)  NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`name`,`slug`,`module`) VALUES
('Ver usuarios',      'users.view',         'Usuarios'),
('Crear usuarios',    'users.create',       'Usuarios'),
('Editar usuarios',   'users.edit',         'Usuarios'),
('Eliminar usuarios', 'users.delete',       'Usuarios'),
('Ver roles',         'roles.view',         'Roles'),
('Crear roles',       'roles.create',       'Roles'),
('Editar roles',      'roles.edit',         'Roles'),
('Eliminar roles',    'roles.delete',       'Roles'),
('Ver pacientes',     'patients.view',      'Pacientes'),
('Crear pacientes',   'patients.create',    'Pacientes'),
('Editar pacientes',  'patients.edit',      'Pacientes'),
('Eliminar pacientes','patients.delete',    'Pacientes'),
('Ver doctores',      'doctors.view',       'Doctores'),
('Crear doctores',    'doctors.create',     'Doctores'),
('Editar doctores',   'doctors.edit',       'Doctores'),
('Eliminar doctores', 'doctors.delete',     'Doctores'),
('Ver citas',         'appointments.view',  'Citas'),
('Crear citas',       'appointments.create','Citas'),
('Editar citas',      'appointments.edit',  'Citas'),
('Eliminar citas',    'appointments.delete','Citas'),
('Ver horarios',      'schedules.view',     'Horarios'),
('Gestionar horarios','schedules.manage',   'Horarios');

-- ----------------------------
--  Tabla: role_permissions
-- ----------------------------
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_role`       FOREIGN KEY (`role_id`)       REFERENCES `roles`(`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `role_permissions` (`role_id`,`permission_id`)
  SELECT 1, `id` FROM `permissions`;

INSERT INTO `role_permissions` (`role_id`,`permission_id`)
  SELECT 2, `id` FROM `permissions`
  WHERE `slug` IN ('patients.view','appointments.view','appointments.create',
                   'appointments.edit','schedules.view','schedules.manage');

INSERT INTO `role_permissions` (`role_id`,`permission_id`)
  SELECT 3, `id` FROM `permissions`
  WHERE `slug` IN ('appointments.view','appointments.create');

-- ----------------------------
--  Tabla: users  (incluye google_id para OAuth)
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `role_id`    INT UNSIGNED  NOT NULL DEFAULT 3,
  `name`       VARCHAR(120)  NOT NULL,
  `email`      VARCHAR(160)  NOT NULL,
  `password`   VARCHAR(255)  NOT NULL DEFAULT '',
  `phone`      VARCHAR(20)   DEFAULT NULL,
  `avatar`     VARCHAR(255)  DEFAULT NULL,
  `google_id`  VARCHAR(64)   DEFAULT NULL COMMENT 'ID unico de Google OAuth',
  `status`     TINYINT(1)    NOT NULL DEFAULT 1,
  `last_login` TIMESTAMP     NULL DEFAULT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique`     (`email`),
  UNIQUE KEY `users_google_id_unique` (`google_id`),
  KEY `fk_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contrasena por defecto: password
INSERT INTO `users` (`role_id`,`name`,`email`,`password`,`phone`,`status`) VALUES
(1, 'Administrador Sistema', 'admin@medcrm.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0001', 1),
(2, 'Dr. Carlos Mendoza',    'doctor@medcrm.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0002', 1),
(3, 'Ana Garcia Lopez',      'paciente@medcrm.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0003', 1),
(2, 'Dra. Laura Perez',      'laura@medcrm.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0004', 1),
(3, 'Roberto Silva',         'roberto@medcrm.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0005', 1);

-- ----------------------------
--  Tabla: specialties
-- ----------------------------
DROP TABLE IF EXISTS `specialties`;
CREATE TABLE `specialties` (
  `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `specialties` (`name`) VALUES
('Medicina General'),('Cardiologia'),('Dermatologia'),('Neurologia'),
('Pediatria'),('Ginecologia'),('Traumatologia'),('Oftalmologia'),
('Otorrinolaringologia'),('Psiquiatria'),('Oncologia'),('Endocrinologia');

-- ----------------------------
--  Tabla: doctors
-- ----------------------------
DROP TABLE IF EXISTS `doctors`;
CREATE TABLE `doctors` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED  NOT NULL,
  `specialty_id`     INT UNSIGNED  NOT NULL,
  `license_number`   VARCHAR(60)   NOT NULL,
  `office`           VARCHAR(60)   DEFAULT NULL,
  `bio`              TEXT          DEFAULT NULL,
  `consultation_fee` DECIMAL(10,2) DEFAULT 0.00,
  `phone`            VARCHAR(20)   DEFAULT NULL,
  `status`           TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `doctors_user_unique` (`user_id`),
  KEY `fk_doctors_specialty` (`specialty_id`),
  CONSTRAINT `fk_doctors_user`      FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_doctors_specialty` FOREIGN KEY (`specialty_id`) REFERENCES `specialties`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `doctors` (`user_id`,`specialty_id`,`license_number`,`office`,`bio`,`consultation_fee`,`status`) VALUES
(2, 2, 'CMP-12345', 'Consultorio 101', 'Cardiologo con 15 anos de experiencia.', 80.00, 1),
(4, 1, 'CMP-67890', 'Consultorio 205', 'Medico general especialista en medicina familiar.', 50.00, 1);

-- ----------------------------
--  Tabla: patients
-- ----------------------------
DROP TABLE IF EXISTS `patients`;
CREATE TABLE `patients` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED  NOT NULL,
  `dni`               VARCHAR(20)   DEFAULT NULL,
  `birth_date`        DATE          DEFAULT NULL,
  `gender`            ENUM('M','F','O') DEFAULT NULL,
  `blood_type`        ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `address`           VARCHAR(255)  DEFAULT NULL,
  `city`              VARCHAR(80)   DEFAULT NULL,
  `occupation`        VARCHAR(100)  DEFAULT NULL,
  `emergency_contact` VARCHAR(120)  DEFAULT NULL,
  `emergency_phone`   VARCHAR(20)   DEFAULT NULL,
  `phone`             VARCHAR(20)   DEFAULT NULL,
  `allergies`         TEXT          DEFAULT NULL,
  `chronic_diseases`  TEXT          DEFAULT NULL,
  `notes`             TEXT          DEFAULT NULL,
  `created_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patients_user_unique` (`user_id`),
  CONSTRAINT `fk_patients_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `patients` (`user_id`,`dni`,`birth_date`,`gender`,`blood_type`,`address`,`city`,`emergency_contact`,`emergency_phone`) VALUES
(3, '12345678', '1990-06-15', 'F', 'O+', 'Av. Libertad 123',  'Lima', 'Juan Garcia',  '555-9999'),
(5, '87654321', '1985-03-22', 'M', 'A+', 'Jr. Las Flores 456','Lima', 'Maria Silva',  '555-8888');

-- ----------------------------
--  Tabla: schedules
-- ----------------------------
DROP TABLE IF EXISTS `schedules`;
CREATE TABLE `schedules` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id`     INT UNSIGNED NOT NULL,
  `day_of_week`   TINYINT(1)   NOT NULL COMMENT '0=Dom 1=Lun 2=Mar 3=Mie 4=Jue 5=Vie 6=Sab',
  `start_time`    TIME         NOT NULL,
  `end_time`      TIME         NOT NULL,
  `slot_duration` INT          NOT NULL DEFAULT 30,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_schedules_doctor` (`doctor_id`),
  CONSTRAINT `fk_schedules_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `schedules` (`doctor_id`,`day_of_week`,`start_time`,`end_time`,`slot_duration`) VALUES
(1,1,'08:00:00','13:00:00',30),(1,2,'08:00:00','13:00:00',30),(1,3,'08:00:00','13:00:00',30),
(1,4,'08:00:00','13:00:00',30),(1,5,'08:00:00','13:00:00',30),
(2,1,'14:00:00','18:00:00',30),(2,3,'14:00:00','18:00:00',30),(2,5,'14:00:00','18:00:00',30);

-- ----------------------------
--  Tabla: appointments
-- ----------------------------
DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `patient_id`       INT UNSIGNED  NOT NULL,
  `doctor_id`        INT UNSIGNED  NOT NULL,
  `appointment_date` DATE          NOT NULL,
  `appointment_time` TIME          NOT NULL,
  `status`           ENUM('pendiente','confirmada','en_curso','completada','cancelada','no_asistio')
                     NOT NULL DEFAULT 'pendiente',
  `reason`           VARCHAR(255)  DEFAULT NULL,
  `notes`            TEXT          DEFAULT NULL,
  `created_by`       INT UNSIGNED  DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_appt_patient` (`patient_id`),
  KEY `fk_appt_doctor`  (`doctor_id`),
  KEY `idx_appt_date`   (`appointment_date`),
  CONSTRAINT `fk_appt_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appt_doctor`  FOREIGN KEY (`doctor_id`)  REFERENCES `doctors`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `appointments` (`patient_id`,`doctor_id`,`appointment_date`,`appointment_time`,`status`,`reason`) VALUES
(1, 1, CURDATE(),                           '09:00:00', 'confirmada', 'Control cardiologico'),
(1, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:00:00', 'pendiente',  'Chequeo general'),
(2, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00:00', 'pendiente',  'Dolor en el pecho'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '15:00:00', 'completada', 'Resultados de laboratorio');

-- ----------------------------
--  Tabla: medical_consultations
-- ----------------------------
DROP TABLE IF EXISTS `medical_consultations`;
CREATE TABLE `medical_consultations` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id`   INT UNSIGNED NOT NULL,
  `diagnosis`        TEXT         DEFAULT NULL,
  `treatment`        TEXT         DEFAULT NULL,
  `prescription`     TEXT         DEFAULT NULL,
  `next_appointment` DATE         DEFAULT NULL,
  `weight_kg`        DECIMAL(5,2) DEFAULT NULL,
  `height_cm`        DECIMAL(5,1) DEFAULT NULL,
  `blood_pressure`   VARCHAR(20)  DEFAULT NULL,
  `temperature`      DECIMAL(4,1) DEFAULT NULL,
  `heart_rate`       INT          DEFAULT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_consult_appt` (`appointment_id`),
  CONSTRAINT `fk_consult_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
--  Tabla: settings  (configuración general del sistema)
-- ----------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT         DEFAULT NULL,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('company_name',   'MedCRM'),
('company_logo',   ''),
('company_slogan', 'Sistema Médico');

SET FOREIGN_KEY_CHECKS = 1;

