-- =====================================================================
-- Smart Study Scheduler — Database Schema (MySQL 5.7+ / MariaDB 10.3+)
-- Import ผ่าน phpMyAdmin: Import → เลือกไฟล์นี้ → Go
-- =====================================================================

CREATE DATABASE IF NOT EXISTS smart_study_scheduler
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_study_scheduler;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS study_progress;
DROP TABLE IF EXISTS schedule_items;
DROP TABLE IF EXISTS schedules;
DROP TABLE IF EXISTS unavailable_times;
DROP TABLE IF EXISTS availability;
DROP TABLE IF EXISTS topics;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1) users : ข้อมูลผู้ใช้ + การตั้งค่าการอ่าน
-- ---------------------------------------------------------------------
CREATE TABLE users (
  user_id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name               VARCHAR(100)  NOT NULL,
  email              VARCHAR(150)  NOT NULL UNIQUE,
  password           VARCHAR(255)  NOT NULL,
  daily_target_hours DECIMAL(4,2)  NOT NULL DEFAULT 3.00,  -- ชั่วโมงที่อยากอ่านต่อวัน
  daily_max_hours    DECIMAL(4,2)  NOT NULL DEFAULT 4.00,  -- ชั่วโมงสูงสุดต่อวัน
  session_minutes    SMALLINT      NOT NULL DEFAULT 60,    -- ความยาว 1 session
  break_minutes      SMALLINT      NOT NULL DEFAULT 15,    -- เวลาพักระหว่าง session
  max_consecutive    TINYINT       NOT NULL DEFAULT 2,     -- อ่านวิชาเดียวติดกันได้กี่ session
  created_at         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2) subjects : รายวิชา
-- ---------------------------------------------------------------------
CREATE TABLE subjects (
  subject_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  subject_name VARCHAR(150) NOT NULL,
  difficulty   TINYINT      NOT NULL DEFAULT 3,         -- 1..5
  priority     ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
  exam_date    DATE         NOT NULL,
  total_hours  DECIMAL(5,1) NOT NULL DEFAULT 10.0,      -- ชั่วโมงที่ต้องอ่านทั้งหมด
  color        VARCHAR(7)   NOT NULL DEFAULT '#2a78d6',
  description  TEXT         NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_subjects_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_subjects_user (user_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3) topics : หัวข้อ/บทที่ต้องอ่านในแต่ละวิชา
-- ---------------------------------------------------------------------
CREATE TABLE topics (
  topic_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_id      INT UNSIGNED NOT NULL,
  topic_name      VARCHAR(150) NOT NULL,
  estimated_hours DECIMAL(4,1) NOT NULL DEFAULT 1.0,
  sort_order      INT          NOT NULL DEFAULT 0,
  status          ENUM('not_started','in_progress','done') NOT NULL DEFAULT 'not_started',
  CONSTRAINT fk_topics_subject FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
  INDEX idx_topics_subject (subject_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4) availability : ช่วงเวลาว่างประจำสัปดาห์ (1=Mon ... 7=Sun, ISO-8601)
-- ---------------------------------------------------------------------
CREATE TABLE availability (
  availability_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  day_of_week     TINYINT      NOT NULL,
  start_time      TIME         NOT NULL,
  end_time        TIME         NOT NULL,
  preference      TINYINT      NOT NULL DEFAULT 2,       -- 1=พอได้ 2=ดี 3=ดีที่สุด (ช่วงที่มีสมาธิสูง)
  CONSTRAINT fk_avail_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_avail_user (user_id, day_of_week)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5) unavailable_times : ช่วงเวลาที่ไม่สะดวกอ่าน (รายวันที่ หรือ ทุกสัปดาห์)
-- ---------------------------------------------------------------------
CREATE TABLE unavailable_times (
  unavailable_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  specific_date  DATE         NULL,                      -- ถ้าระบุ = เฉพาะวันนั้น
  day_of_week    TINYINT      NULL,                      -- ถ้าระบุ = ทุกสัปดาห์
  start_time     TIME         NOT NULL,
  end_time       TIME         NOT NULL,
  reason         VARCHAR(150) NULL,
  CONSTRAINT fk_unavail_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_unavail_user (user_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6) schedules : ตารางที่ได้จากการรัน GA แต่ละครั้ง (เก็บเป็น version)
-- ---------------------------------------------------------------------
CREATE TABLE schedules (
  schedule_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  start_date      DATE         NOT NULL,
  end_date        DATE         NOT NULL,
  fitness         DECIMAL(7,3) NOT NULL DEFAULT 0,
  generations     INT          NOT NULL DEFAULT 0,
  execution_ms    INT          NOT NULL DEFAULT 0,
  parameters      TEXT         NULL,                     -- JSON: GA parameters
  fitness_detail  TEXT         NULL,                     -- JSON: breakdown ของ fitness
  fitness_history MEDIUMTEXT   NULL,                     -- JSON: best/avg ต่อ generation
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sched_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_sched_user (user_id, is_active)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7) schedule_items : Study session แต่ละช่วง (= Gene ที่ถูก decode แล้ว)
-- ---------------------------------------------------------------------
CREATE TABLE schedule_items (
  schedule_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  schedule_id      INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  topic_id         INT UNSIGNED NULL,
  date             DATE         NOT NULL,
  start_time       TIME         NOT NULL,
  end_time         TIME         NOT NULL,
  status           ENUM('planned','done','skipped') NOT NULL DEFAULT 'planned',
  is_manual        TINYINT(1)   NOT NULL DEFAULT 0,      -- ผู้ใช้เพิ่ม/แก้ไขเอง
  is_locked        TINYINT(1)   NOT NULL DEFAULT 0,      -- ล็อกไว้ ไม่ให้ GA เปลี่ยนตอน regenerate
  CONSTRAINT fk_items_schedule FOREIGN KEY (schedule_id) REFERENCES schedules(schedule_id) ON DELETE CASCADE,
  CONSTRAINT fk_items_subject  FOREIGN KEY (subject_id)  REFERENCES subjects(subject_id)  ON DELETE CASCADE,
  CONSTRAINT fk_items_topic    FOREIGN KEY (topic_id)    REFERENCES topics(topic_id)      ON DELETE SET NULL,
  INDEX idx_items_schedule_date (schedule_id, date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8) study_progress : บันทึกการอ่านจริง (ใช้คำนวณความก้าวหน้า)
-- ---------------------------------------------------------------------
CREATE TABLE study_progress (
  progress_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  topic_id         INT UNSIGNED NULL,
  schedule_item_id INT UNSIGNED NULL,
  study_date       DATE         NOT NULL,
  minutes          SMALLINT     NOT NULL,
  note             VARCHAR(255) NULL,
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prog_user    FOREIGN KEY (user_id)          REFERENCES users(user_id)                   ON DELETE CASCADE,
  CONSTRAINT fk_prog_subject FOREIGN KEY (subject_id)       REFERENCES subjects(subject_id)             ON DELETE CASCADE,
  CONSTRAINT fk_prog_topic   FOREIGN KEY (topic_id)         REFERENCES topics(topic_id)                 ON DELETE SET NULL,
  CONSTRAINT fk_prog_item    FOREIGN KEY (schedule_item_id) REFERENCES schedule_items(schedule_item_id) ON DELETE SET NULL,
  INDEX idx_prog_user_subject (user_id, subject_id)
) ENGINE=InnoDB;

-- =====================================================================
-- Demo data  —  Login: demo@example.com / demo1234
-- วันสอบคำนวณจากวันที่ import เพื่อให้ข้อมูลตัวอย่างใช้ได้เสมอ
-- =====================================================================
INSERT INTO users (user_id, name, email, password, daily_target_hours, daily_max_hours, session_minutes, break_minutes, max_consecutive)
VALUES (1, 'Demo Student', 'demo@example.com',
        '$2y$10$5H2HY1zQuLEsbsKJhH/Ok.9F6XMrNkKkCu8mISrak27HIoD6jihlm', 3, 4, 60, 15, 2);

INSERT INTO subjects (subject_id, user_id, subject_name, difficulty, priority, exam_date, total_hours, color) VALUES
(1, 1, 'Data Structures',         4, 'High',   DATE_ADD(CURDATE(), INTERVAL 16 DAY), 15, '#2a78d6'),
(2, 1, 'Database Systems',        3, 'High',   DATE_ADD(CURDATE(), INTERVAL 12 DAY), 10, '#eb6834'),
(3, 1, 'Artificial Intelligence', 5, 'Medium', DATE_ADD(CURDATE(), INTERVAL 24 DAY), 12, '#1baf7a'),
(4, 1, 'Discrete Mathematics',    3, 'Medium', DATE_ADD(CURDATE(), INTERVAL 20 DAY),  8, '#4a3aa7'),
(5, 1, 'Software Engineering',    2, 'Low',    DATE_ADD(CURDATE(), INTERVAL 28 DAY),  6, '#e34948');

INSERT INTO topics (subject_id, topic_name, estimated_hours, sort_order) VALUES
(1, 'Array & Linked List', 3, 1), (1, 'Stack & Queue', 2, 2), (1, 'Tree', 4, 3), (1, 'Graph', 4, 4), (1, 'Sorting', 2, 5),
(2, 'ER Model', 2, 1), (2, 'Relational Algebra', 2, 2), (2, 'SQL', 4, 3), (2, 'Normalization', 2, 4),
(3, 'Search Algorithms', 3, 1), (3, 'Genetic Algorithm', 3, 2), (3, 'Machine Learning Basics', 4, 3), (3, 'Neural Networks', 2, 4),
(4, 'Logic & Proofs', 3, 1), (4, 'Sets & Relations', 2, 2), (4, 'Graph Theory', 3, 3),
(5, 'Requirement Engineering', 2, 1), (5, 'UML', 2, 2), (5, 'Testing', 2, 3);

INSERT INTO availability (user_id, day_of_week, start_time, end_time, preference) VALUES
(1, 1, '18:00', '21:00', 3),
(1, 2, '18:00', '21:00', 2),
(1, 4, '18:00', '22:00', 2),
(1, 5, '18:00', '20:00', 1),
(1, 6, '09:00', '12:00', 3),
(1, 6, '13:00', '16:00', 2),
(1, 7, '13:00', '17:00', 2);

INSERT INTO unavailable_times (user_id, specific_date, day_of_week, start_time, end_time, reason) VALUES
(1, NULL, 7, '15:00', '16:00', 'ออกกำลังกาย');
