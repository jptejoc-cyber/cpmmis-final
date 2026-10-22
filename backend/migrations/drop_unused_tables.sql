-- CPMMIS: drop tables with zero references in application code.
-- Run this against your database (e.g. cpmmis_db) in phpMyAdmin.
-- All statements guard with IF EXISTS, so missing tables are simply skipped.

DROP TABLE IF EXISTS `panelist`;
DROP TABLE IF EXISTS `projects`;

-- Sanity check: list tables remaining in the current database.
SHOW TABLES;