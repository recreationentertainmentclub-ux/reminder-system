-- SmartSender Reminder System Database Schema
-- Run this script to initialize the database

CREATE DATABASE IF NOT EXISTS reminder_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE reminder_system;

CREATE TABLE IF NOT EXISTS reminders (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(255)        NOT NULL,
    description   TEXT,
    email         VARCHAR(255)        NOT NULL,
    phone         VARCHAR(50),
    event_datetime DATETIME           NOT NULL,
    remind_24_sent TINYINT(1)         NOT NULL DEFAULT 0,
    remind_12_sent TINYINT(1)         NOT NULL DEFAULT 0,
    status        ENUM('active','cancelled','completed') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_event_datetime (event_datetime),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reminder_logs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reminder_id   INT UNSIGNED        NOT NULL,
    tag           VARCHAR(50)         NOT NULL COMMENT 'SmartSender tag, e.g. REMIND_24 or REMIND_12',
    success       TINYINT(1)          NOT NULL DEFAULT 0,
    response      TEXT,
    sent_at       TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reminder_id) REFERENCES reminders(id) ON DELETE CASCADE,
    INDEX idx_reminder_id (reminder_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
