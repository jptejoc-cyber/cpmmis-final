-- =============================================================================
-- Migration 025: System Improvements
-- Description: Adds session_version columns, updated_at timestamps, performance
--              indexes, notification_type column, and CHECK constraints.
-- =============================================================================

USE cpmmis_db;

-- -----------------------------------------------------------------------------
-- Helper procedure: add a column only if it does not already exist.
-- Usage: CALL add_column_if_not_exists('table_name', 'column_def');
-- The column_def must be the full definition (e.g. "col_name VARCHAR(64) DEFAULT NULL").
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS add_column_if_not_exists;
DELIMITER //
CREATE PROCEDURE add_column_if_not_exists(
    IN p_table  VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_def    VARCHAR(512)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND COLUMN_NAME  = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_column, ' ', p_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- Helper procedure: create an index only if it does not already exist.
DROP PROCEDURE IF EXISTS create_index_if_not_exists;
DELIMITER //
CREATE PROCEDURE create_index_if_not_exists(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_cols  VARCHAR(512)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND INDEX_NAME   = p_index
    ) THEN
        SET @sql = CONCAT('CREATE INDEX `', p_index, '` ON `', p_table, '` (', p_cols, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- Helper procedure: add a CHECK constraint only if it does not already exist.
DROP PROCEDURE IF EXISTS add_check_if_not_exists;
DELIMITER //
CREATE PROCEDURE add_check_if_not_exists(
    IN p_table VARCHAR(64),
    IN p_name  VARCHAR(64),
    IN p_check VARCHAR(512)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME        = p_table
          AND CONSTRAINT_NAME   = p_name
          AND CONSTRAINT_TYPE   = 'CHECK'
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD CONSTRAINT `', p_name, '` CHECK (', p_check, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;


-- =============================================================================
-- 1. Add session_version VARCHAR(64) to group, adviser, admin
--    Used for password-reset session invalidation: bumping this value forces
--    any previously-issued session JWT / token to become stale.
-- =============================================================================

CALL add_column_if_not_exists('group',     'session_version', "VARCHAR(64) DEFAULT NULL");
CALL add_column_if_not_exists('adviser',   'session_version', "VARCHAR(64) DEFAULT NULL");
CALL add_column_if_not_exists('admin',     'session_version', "VARCHAR(64) DEFAULT NULL");


-- =============================================================================
-- 2. Add updated_at TIMESTAMP columns (DEFAULT CURRENT_TIMESTAMP
--    ON UPDATE CURRENT_TIMESTAMP) so writes are automatically tracked.
-- =============================================================================

-- adviser
CALL add_column_if_not_exists(
    'adviser',
    'updated_at',
    "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
);

-- admin
CALL add_column_if_not_exists(
    'admin',
    'updated_at',
    "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
);

-- adviser_tasks
CALL add_column_if_not_exists(
    'adviser_tasks',
    'updated_at',
    "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
);

-- adviser_requests
CALL add_column_if_not_exists(
    'adviser_requests',
    'updated_at',
    "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
);

-- meeting_requests
CALL add_column_if_not_exists(
    'meeting_requests',
    'updated_at',
    "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
);

-- admin_letters
CALL add_column_if_not_exists(
    'admin_letters',
    'updated_at',
    "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
);

-- group_submissions
CALL add_column_if_not_exists(
    'group_submissions',
    'updated_at',
    "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
);

-- messages
CALL add_column_if_not_exists(
    'messages',
    'updated_at',
    "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
);


-- =============================================================================
-- 3. Add missing indexes for common query patterns.
-- =============================================================================

-- meeting_requests: filter by group
CALL create_index_if_not_exists('meeting_requests', 'idx_meetings_group', 'group_id');

-- meeting_requests: filter by adviser
CALL create_index_if_not_exists('meeting_requests', 'idx_meetings_adviser', 'adviser_id');

-- meeting_requests: reminder / scheduling queries
CALL create_index_if_not_exists(
    'meeting_requests',
    'idx_meetings_status_time',
    'status, reminder_sent, meeting_time'
);

-- group_submissions: look up by task
CALL create_index_if_not_exists('group_submissions', 'idx_submissions_task', 'task_id');

-- messages: chronological ordering / pagination
CALL create_index_if_not_exists('messages', 'idx_messages_created', 'created_at');

-- messages: inbox by sender
CALL create_index_if_not_exists('messages', 'idx_messages_sender', 'sender_id, sender_role');

-- messages: inbox by receiver
CALL create_index_if_not_exists('messages', 'idx_messages_receiver', 'receiver_id, receiver_role');


-- =============================================================================
-- 4. Add notification_type VARCHAR(50) to notifications.
--    Allows categorising notifications (e.g. 'task_assigned', 'meeting_reminder').
-- =============================================================================

CALL add_column_if_not_exists('notifications', 'notification_type', "VARCHAR(50) DEFAULT NULL");


-- =============================================================================
-- 5. Add CHECK constraints to group_submissions.
--    chapter_number must be between 1 and 6; version must be >= 1.
-- =============================================================================

CALL add_check_if_not_exists(
    'group_submissions',
    'chk_chapter_num',
    'chapter_number BETWEEN 1 AND 6'
);

CALL add_check_if_not_exists(
    'group_submissions',
    'chk_version',
    'version >= 1'
);


-- =============================================================================
-- Cleanup helper procedures.
-- =============================================================================

DROP PROCEDURE IF EXISTS add_column_if_not_exists;
DROP PROCEDURE IF EXISTS create_index_if_not_exists;
DROP PROCEDURE IF EXISTS add_check_if_not_exists;

-- =============================================================================
-- End of migration 025.
-- =============================================================================
