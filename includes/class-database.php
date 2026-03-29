<?php
if (!defined('ABSPATH')) exit;

class CWDS_Kanban_Database {

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Boards
        $sql = "CREATE TABLE " . CWDS_KANBAN_TABLE_BOARDS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        dbDelta($sql);

        // Columns
        $sql = "CREATE TABLE " . CWDS_KANBAN_TABLE_COLUMNS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id bigint(20) UNSIGNED NOT NULL,
            title varchar(255) NOT NULL,
            position int(11) NOT NULL DEFAULT 0,
            color varchar(7) DEFAULT '#E3FF04',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY board_id (board_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Cards
        $sql = "CREATE TABLE " . CWDS_KANBAN_TABLE_CARDS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            column_id bigint(20) UNSIGNED NOT NULL,
            board_id bigint(20) UNSIGNED NOT NULL,
            title varchar(255) NOT NULL,
            description longtext,
            position int(11) NOT NULL DEFAULT 0,
            due_date datetime DEFAULT NULL,
            is_complete tinyint(1) DEFAULT 0,
            created_by varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY column_id (column_id),
            KEY board_id (board_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Labels
        $sql = "CREATE TABLE " . CWDS_KANBAN_TABLE_LABELS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id bigint(20) UNSIGNED NOT NULL,
            title varchar(100) NOT NULL,
            color varchar(7) NOT NULL DEFAULT '#E3FF04',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY board_id (board_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Card-Labels pivot
        $sql = "CREATE TABLE " . CWDS_KANBAN_TABLE_CARD_LABELS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            card_id bigint(20) UNSIGNED NOT NULL,
            label_id bigint(20) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY card_label (card_id, label_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Members (client contacts for a board - NOT WP users)
        $sql = "CREATE TABLE " . CWDS_KANBAN_TABLE_MEMBERS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            avatar_color varchar(7) DEFAULT '#E3FF04',
            role varchar(20) DEFAULT 'client',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY board_id (board_id),
            UNIQUE KEY board_email (board_id, email)
        ) $charset_collate;";
        dbDelta($sql);

        // Card-Members pivot
        $sql = "CREATE TABLE " . CWDS_KANBAN_TABLE_CARD_MEMBERS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            card_id bigint(20) UNSIGNED NOT NULL,
            member_id bigint(20) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY card_member (card_id, member_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Checklists
        $sql = "CREATE TABLE " . CWDS_KANBAN_TABLE_CHECKLISTS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            card_id bigint(20) UNSIGNED NOT NULL,
            title varchar(255) NOT NULL DEFAULT 'Checklist',
            position int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY card_id (card_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Checklist Items
        $sql = "CREATE TABLE " . CWDS_KANBAN_TABLE_CHECKLIST_ITEMS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            checklist_id bigint(20) UNSIGNED NOT NULL,
            text varchar(500) NOT NULL,
            is_checked tinyint(1) DEFAULT 0,
            position int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY checklist_id (checklist_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Attachments
        $sql = "CREATE TABLE " . CWDS_KANBAN_TABLE_ATTACHMENTS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            card_id bigint(20) UNSIGNED NOT NULL,
            file_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_url varchar(500) NOT NULL,
            file_type varchar(100) DEFAULT '',
            file_size bigint(20) UNSIGNED DEFAULT 0,
            uploaded_by varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY card_id (card_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Activity Log
        $sql = "CREATE TABLE " . CWDS_KANBAN_TABLE_ACTIVITY . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id bigint(20) UNSIGNED NOT NULL,
            card_id bigint(20) UNSIGNED DEFAULT NULL,
            actor_name varchar(255) NOT NULL,
            actor_type varchar(20) DEFAULT 'admin',
            action varchar(50) NOT NULL,
            details text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY board_id (board_id),
            KEY card_id (card_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Auth Tokens (magic links)
        $sql = "CREATE TABLE " . CWDS_KANBAN_TABLE_TOKENS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id bigint(20) UNSIGNED NOT NULL,
            board_id bigint(20) UNSIGNED NOT NULL,
            token varchar(64) NOT NULL,
            session_token varchar(64) DEFAULT NULL,
            expires_at datetime NOT NULL,
            used tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY member_id (member_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Comments
        $sql = "CREATE TABLE " . CWDS_KANBAN_TABLE_COMMENTS . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            card_id bigint(20) UNSIGNED NOT NULL,
            board_id bigint(20) UNSIGNED NOT NULL,
            author_name varchar(255) NOT NULL,
            author_type varchar(20) DEFAULT 'client',
            author_avatar_color varchar(7) DEFAULT '#E3FF04',
            body text NOT NULL,
            mentions text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY card_id (card_id),
            KEY board_id (board_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Store DB version
        update_option('cwds_kanban_db_version', CWDS_KANBAN_VERSION);
    }
}
