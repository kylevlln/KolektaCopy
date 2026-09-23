-- ============================================================================
--  KOLEKTA
--  Barangay-Level Waste Collection Dispatch Notification
--  and Missed Pickup Reporting System
--
--  Engine:  MariaDB 10.4 / MySQL 5.7+  (XAMPP)
--  Import via phpMyAdmin or:  mysql -u root < db/kolekta.sql
-- ============================================================================

DROP DATABASE IF EXISTS kolekta;
CREATE DATABASE kolekta
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE kolekta;

-- ----------------------------------------------------------------------------
-- ZONES : barangay puroks / collection areas
-- ----------------------------------------------------------------------------
CREATE TABLE zones (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(60)  NOT NULL,
  code        VARCHAR(12)  NOT NULL,
  description VARCHAR(255) NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_zone_code (code),
  KEY idx_zone_active (is_active)
) ENGINE = InnoDB;

-- ----------------------------------------------------------------------------
-- WASTE TYPES : the accepted waste streams (codes are stable identifiers and
--   are referenced by schedules / dispatches / reports)
-- ----------------------------------------------------------------------------
CREATE TABLE waste_types (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       VARCHAR(32)  NOT NULL,
  name       VARCHAR(60)  NOT NULL,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wt_code (code),
  KEY idx_wt_active (is_active, sort_order)
) ENGINE = InnoDB;

-- ----------------------------------------------------------------------------
-- USERS : residents and barangay staff
-- ----------------------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(90)  NOT NULL,
  email         VARCHAR(160) NOT NULL,
  username      VARCHAR(60)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('resident','admin') NOT NULL DEFAULT 'resident',
  phone         VARCHAR(24)  NULL,
  household     VARCHAR(120) NULL,
  zone_id       INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_username (username),
  KEY idx_users_zone (zone_id),
  CONSTRAINT fk_users_zone FOREIGN KEY (zone_id) REFERENCES zones (id) ON DELETE SET NULL
) ENGINE = InnoDB;

-- ----------------------------------------------------------------------------
-- PASSWORD RESETS : one-time, expiring tokens for the forgotten-password flow
--   token_hash is a SHA-256 of the random token sent to the user by email
-- ----------------------------------------------------------------------------
CREATE TABLE password_resets (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  expires_at  DATETIME     NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pr_user (user_id),
  KEY idx_pr_token (token_hash)
) ENGINE = InnoDB;

-- ----------------------------------------------------------------------------
-- SCHEDULES : recurring weekly collection per zone and waste type
--   weekday: 1 = Monday ... 7 = Sunday
-- ----------------------------------------------------------------------------
CREATE TABLE schedules (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  zone_id     INT UNSIGNED NOT NULL,
  weekday     TINYINT UNSIGNED NOT NULL,
  waste_type  VARCHAR(16)  NOT NULL,
  window_start VARCHAR(5) NULL,
  window_end   VARCHAR(5) NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sched (zone_id, weekday, waste_type),
  KEY idx_sched_zone (zone_id),
  KEY idx_sched_waste (waste_type),
  KEY idx_sched_active (is_active, weekday),
  CONSTRAINT fk_sched_zone FOREIGN KEY (zone_id) REFERENCES zones (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- ----------------------------------------------------------------------------
-- ANNOUNCEMENTS : temporary notices / cancellations (zone_id NULL = all zones)
-- ----------------------------------------------------------------------------
CREATE TABLE announcements (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  zone_id      INT UNSIGNED NULL,
  title        VARCHAR(160) NOT NULL,
  body         VARCHAR(600) NOT NULL,
  kind         ENUM('notice','cancellation') NOT NULL DEFAULT 'notice',
  is_published TINYINT(1)   NOT NULL DEFAULT 1,
  expires_at   DATETIME NULL,
  created_by   INT UNSIGNED NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ann_zone (zone_id),
  KEY idx_ann_publish (is_published, expires_at),
  CONSTRAINT fk_ann_zone FOREIGN KEY (zone_id) REFERENCES zones (id) ON DELETE CASCADE,
  CONSTRAINT fk_ann_by   FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE = InnoDB;

-- ----------------------------------------------------------------------------
-- DISPATCHES : admin-triggered "truck inbound" events per zone
--   status: active -> cancelled / archived
-- ----------------------------------------------------------------------------
CREATE TABLE dispatches (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  zone_id      INT UNSIGNED NOT NULL,
  waste_type   VARCHAR(16) NULL,
  message      VARCHAR(300) NULL,
  status       ENUM('active','cancelled','archived') NOT NULL DEFAULT 'active',
  triggered_by INT UNSIGNED NOT NULL,
  dispatched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dispatch_zone (zone_id, dispatched_at),
  KEY idx_dispatch_waste (waste_type),
  KEY idx_dispatch_status (status, dispatched_at),
  CONSTRAINT fk_dispatch_zone FOREIGN KEY (zone_id) REFERENCES zones (id) ON DELETE CASCADE,
  CONSTRAINT fk_dispatch_by   FOREIGN KEY (triggered_by) REFERENCES users (id)
) ENGINE = InnoDB;

-- ----------------------------------------------------------------------------
-- REPORTS : missed pickup reports from residents
--   status: pending -> investigating -> resolved / archived
-- ----------------------------------------------------------------------------
CREATE TABLE reports (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id            INT UNSIGNED NOT NULL,
  zone_id            INT UNSIGNED NOT NULL,
  address            VARCHAR(160) NOT NULL,
  waste_type         VARCHAR(16)  NOT NULL DEFAULT 'mixed',
  photo_path         VARCHAR(255) NULL,
  notes              VARCHAR(500) NULL,
  status             ENUM('pending','investigating','resolved','archived') NOT NULL DEFAULT 'pending',
  secondary_dispatch TINYINT(1) NOT NULL DEFAULT 0,
  resolved_at        DATETIME NULL,
  resolved_by        INT UNSIGNED NULL,
  resolution_note    VARCHAR(500) NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_report_zone (zone_id, status),
  KEY idx_report_user (user_id),
  KEY idx_report_waste (waste_type),
  KEY idx_report_status (status),
  CONSTRAINT fk_report_user     FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_report_zone     FOREIGN KEY (zone_id) REFERENCES zones (id) ON DELETE CASCADE,
  CONSTRAINT fk_report_resolved FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE = InnoDB;

-- ----------------------------------------------------------------------------
-- NOTIFICATIONS : per-resident inbox (dispatch blasts, announcements,
--                 report status updates)
-- ----------------------------------------------------------------------------
CREATE TABLE notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  kind       ENUM('dispatch','announcement','report_update') NOT NULL,
  title      VARCHAR(160) NOT NULL,
  body       VARCHAR(400) NOT NULL,
  link       VARCHAR(160) NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id, is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- ============================================================================
-- SEED DATA
-- ============================================================================

-- 8 barangay puroks, each a dispatch zone -------------------------------
INSERT INTO zones (id, name, code, description) VALUES
  (1, 'Purok 1',  'P1', 'Zone 1 - sitios A and B'),
  (2, 'Purok 2',  'P2', 'Zone 2 - riverbank area'),
  (3, 'Purok 3',  'P3', 'Zone 3 - market block'),
  (4, 'Purok 4',  'P4', 'Zone 4 - school vicinity'),
  (5, 'Purok 5',  'P5', 'Zone 5 - chapel area'),
  (6, 'Purok 6',  'P6', 'Zone 6 - hilltop resettlement'),
  (7, 'Purok 7',  'P7', 'Zone 7 - barangay hall side'),
  (8, 'Purok 8',  'P8', 'Zone 8 - farm lots');

-- Waste streams (codes are the stable keys used across the schema) ----------
INSERT INTO waste_types (id, code, name, is_active, sort_order) VALUES
  (1, 'biodegradable',     'Biodegradable',             1, 1),
  (2, 'non_biodegradable', 'Non-biodegradable',         1, 2),
  (3, 'recyclable',        'Recyclable',                1, 3),
  (4, 'mixed',             'Mixed household waste',     1, 4);

-- Administration accounts ------------------------------------------------
--   usernames : jayvie / spencer / pau
--   emails    : jayvie@kolekta.ph / spencer@kolekta.ph / pau@kolekta.ph
--   password  : kolekta-admin-2026
INSERT INTO users (id, name, email, username, password_hash, role, phone) VALUES
  (1, 'Jayvie Mendoza',  'jayvie@kolekta.ph',  'jayvie',   '$2y$10$c7OybuhaJAD.klhYq4cR6uULSEiCiN4ELOYRD1RkDS.rCCVuKCWR.', 'admin', '0917 000 0001'),
  (2, 'Spencer Reyes',   'spencer@kolekta.ph', 'spencer',  '$2y$10$c7OybuhaJAD.klhYq4cR6uULSEiCiN4ELOYRD1RkDS.rCCVuKCWR.', 'admin', '0917 000 0002'),
  (3, 'Pau dela Cruz',   'pau@kolekta.ph',     'pau',      '$2y$10$c7OybuhaJAD.klhYq4cR6uULSEiCiN4ELOYRD1RkDS.rCCVuKCWR.', 'admin', '0917 000 0003');

-- Resident demo households (one purok each) ------------------------------
INSERT INTO users (id, name, email, username, password_hash, role, phone, household, zone_id) VALUES
  (4,  'Daniel Ahmadi',  'daniel@kolekta.ph',  'daniel',   '$2y$10$c7OybuhaJAD.klhYq4cR6uULSEiCiN4ELOYRD1RkDS.rCCVuKCWR.', 'resident', '0917 123 4501', '11 Rizal St, Sitio Proper', 1),
  (5,  'Maria Santos',   'maria@kolekta.ph',   'maria',    '$2y$10$c7OybuhaJAD.klhYq4cR6uULSEiCiN4ELOYRD1RkDS.rCCVuKCWR.', 'resident', '0917 123 4502', '42 Bonifacio St', 1),
  (6,  'Jose Ramirez',   'jose@kolekta.ph',    'jose',     '$2y$10$c7OybuhaJAD.klhYq4cR6uULSEiCiN4ELOYRD1RkDS.rCCVuKCWR.', 'resident', '0917 123 4503', '8 Mabini St', 2),
  (7,  'Ana Villanueva', 'ana@kolekta.ph',     'ana',      '$2y$10$c7OybuhaJAD.klhYq4cR6uULSEiCiN4ELOYRD1RkDS.rCCVuKCWR.', 'resident', '0917 123 4504', '17 Del Pilar St', 3),
  (8,  'Pedro Garcia',   'pedro@kolekta.ph',   'pedro',    '$2y$10$c7OybuhaJAD.klhYq4cR6uULSEiCiN4ELOYRD1RkDS.rCCVuKCWR.', 'resident', '0917 123 4505', '23 Luna St', 4),
  (9,  'Liza Ramos',     'liza@kolekta.ph',    'liza',     '$2y$10$c7OybuhaJAD.klhYq4cR6uULSEiCiN4ELOYRD1RkDS.rCCVuKCWR.', 'resident', '0917 123 4506', '5 Quirino St', 5);

-- Recurring weekly collection schedules ----------------------------------
-- Pattern A (zones 1,5):   Bio Mon Thu | NonBio Tue Fri | Recyc Wed Sat
-- Pattern B (zones 2,6):   Bio Tue Fri | NonBio Wed Sat | Recyc Mon Thu
-- Pattern C (zones 3,7):   Bio Wed Sat | NonBio Thu Sun | Recyc Tue Fri
-- Pattern D (zones 4,8):   Bio Thu Sun | NonBio Mon Fri | Recyc Wed Sat
-- (collections run 05:00–07:00)

INSERT INTO schedules (zone_id, weekday, waste_type, window_start, window_end) VALUES
  -- Pattern A: zones 1 and 5
  (1, 1, 'biodegradable',     '05:00','07:00'),
  (1, 4, 'biodegradable',     '05:00','07:00'),
  (1, 2, 'non_biodegradable', '05:00','07:00'),
  (1, 5, 'non_biodegradable', '05:00','07:00'),
  (1, 3, 'recyclable',        '05:00','07:00'),
  (1, 6, 'recyclable',        '05:00','07:00'),
  (5, 1, 'biodegradable',     '05:00','07:00'),
  (5, 4, 'biodegradable',     '05:00','07:00'),
  (5, 2, 'non_biodegradable', '05:00','07:00'),
  (5, 5, 'non_biodegradable', '05:00','07:00'),
  (5, 3, 'recyclable',        '05:00','07:00'),
  (5, 6, 'recyclable',        '05:00','07:00'),
  -- Pattern B: zones 2 and 6
  (2, 2, 'biodegradable',     '05:00','07:00'),
  (2, 5, 'biodegradable',     '05:00','07:00'),
  (2, 3, 'non_biodegradable', '05:00','07:00'),
  (2, 6, 'non_biodegradable', '05:00','07:00'),
  (2, 1, 'recyclable',        '05:00','07:00'),
  (2, 4, 'recyclable',        '05:00','07:00'),
  (6, 2, 'biodegradable',     '05:00','07:00'),
  (6, 5, 'biodegradable',     '05:00','07:00'),
  (6, 3, 'non_biodegradable', '05:00','07:00'),
  (6, 6, 'non_biodegradable', '05:00','07:00'),
  (6, 1, 'recyclable',        '05:00','07:00'),
  (6, 4, 'recyclable',        '05:00','07:00'),
  -- Pattern C: zones 3 and 7
  (3, 3, 'biodegradable',     '05:00','07:00'),
  (3, 6, 'biodegradable',     '05:00','07:00'),
  (3, 4, 'non_biodegradable', '05:00','07:00'),
  (3, 7, 'non_biodegradable', '05:00','07:00'),
  (3, 2, 'recyclable',        '05:00','07:00'),
  (3, 5, 'recyclable',        '05:00','07:00'),
  (7, 3, 'biodegradable',     '05:00','07:00'),
  (7, 6, 'biodegradable',     '05:00','07:00'),
  (7, 4, 'non_biodegradable', '05:00','07:00'),
  (7, 7, 'non_biodegradable', '05:00','07:00'),
  (7, 2, 'recyclable',        '05:00','07:00'),
  (7, 5, 'recyclable',        '05:00','07:00'),
  -- Pattern D: zones 4 and 8
  (4, 4, 'biodegradable',     '05:00','07:00'),
  (4, 7, 'biodegradable',     '05:00','07:00'),
  (4, 1, 'non_biodegradable', '05:00','07:00'),
  (4, 5, 'non_biodegradable', '05:00','07:00'),
  (4, 3, 'recyclable',        '05:00','07:00'),
  (4, 6, 'recyclable',        '05:00','07:00'),
  (8, 4, 'biodegradable',     '05:00','07:00'),
  (8, 7, 'biodegradable',     '05:00','07:00'),
  (8, 1, 'non_biodegradable', '05:00','07:00'),
  (8, 5, 'non_biodegradable', '05:00','07:00'),
  (8, 3, 'recyclable',        '05:00','07:00'),
  (8, 6, 'recyclable',        '05:00','07:00');

-- Sample announcement ------------------------------------------------------
INSERT INTO announcements (zone_id, title, body, kind, created_by)
VALUES (NULL, 'Collection advisory',
        'Please set waste out only on your scheduled collection days. Observe "no segregation, no collection".',
        'notice', 1);

-- Sample dispatch history --------------------------------------------------
INSERT INTO dispatches (zone_id, waste_type, triggered_by, dispatched_at)
VALUES (1, 'biodegradable', 1, NOW());

-- Sample reports (for the triage board) ------------------------------------
INSERT INTO reports (user_id, zone_id, address, waste_type, notes, status, created_at)
VALUES
  (5, 1, '42 Bonifacio St', 'biodegradable', 'Household skipped during Thursday run.', 'pending', NOW() - INTERVAL 1 DAY),
  (4, 1, '11 Rizal St, Sitio Proper', 'mixed', 'No collection yesterday.', 'resolved', NOW() - INTERVAL 5 DAY),
  (7, 3, '17 Del Pilar St', 'recyclable', 'Missed recyclables on Tuesday.', 'investigating', NOW() - INTERVAL 12 HOUR);

UPDATE reports SET resolved_at = NOW() - INTERVAL 4 DAY, resolved_by = 1,
  resolution_note = 'Secondary collection completed for Rizal St.'
WHERE id = 2;