-- Migration: add job-related columns to alumni table
-- Run this SQL against your database (e.g., via phpMyAdmin, adminer, CLI).

ALTER TABLE alumni
  ADD COLUMN has_job TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = punya pekerjaan selama kuliah, 0 = tidak',
  ADD COLUMN job_title VARCHAR(100) NULL COMMENT 'Nama pekerjaan (opsional)',
  ADD COLUMN job_income INT UNSIGNED NULL COMMENT 'Penghasilan per bulan (rupiah), optional';

