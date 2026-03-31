<?php
/**
 * Plugin Name: CWDS Kanban Board
 * Plugin URI: https://charlestonwebsitestudio.com/
 * Description: Client-facing Kanban project management boards with magic link authentication. No WordPress login required for clients.
 * Version: 1.2.5
 * Author: Dan Lee
 * Author URI: https://charlestonwebsitestudio.com/
 * License: GPL v2 or later
 * Text Domain: cwds-kanban
 */

if (!defined('ABSPATH')) exit;

define('CWDS_KANBAN_VERSION', '1.2.5');
define('CWDS_KANBAN_PATH', plugin_dir_path(__FILE__));
define('CWDS_KANBAN_URL', plugin_dir_url(__FILE__));
define('CWDS_KANBAN_BASENAME', plugin_basename(__FILE__));

// Database table names
global $wpdb;
define('CWDS_KANBAN_TABLE_BOARDS', $wpdb->prefix . 'cwds_kanban_boards');
define('CWDS_KANBAN_TABLE_COLUMNS', $wpdb->prefix . 'cwds_kanban_columns');
define('CWDS_KANBAN_TABLE_CARDS', $wpdb->prefix . 'cwds_kanban_cards');
define('CWDS_KANBAN_TABLE_LABELS', $wpdb->prefix . 'cwds_kanban_labels');
define('CWDS_KANBAN_TABLE_CARD_LABELS', $wpdb->prefix . 'cwds_kanban_card_labels');
define('CWDS_KANBAN_TABLE_MEMBERS', $wpdb->prefix . 'cwds_kanban_members');
define('CWDS_KANBAN_TABLE_CARD_MEMBERS', $wpdb->prefix . 'cwds_kanban_card_members');
define('CWDS_KANBAN_TABLE_CHECKLISTS', $wpdb->prefix . 'cwds_kanban_checklists');
define('CWDS_KANBAN_TABLE_CHECKLIST_ITEMS', $wpdb->prefix . 'cwds_kanban_checklist_items');
define('CWDS_KANBAN_TABLE_ATTACHMENTS', $wpdb->prefix . 'cwds_kanban_attachments');
define('CWDS_KANBAN_TABLE_ACTIVITY', $wpdb->prefix . 'cwds_kanban_activity');
define('CWDS_KANBAN_TABLE_TOKENS', $wpdb->prefix . 'cwds_kanban_tokens');
define('CWDS_KANBAN_TABLE_COMMENTS', $wpdb->prefix . 'cwds_kanban_comments');
define('CWDS_KANBAN_TABLE_WATCHERS', $wpdb->prefix . 'cwds_kanban_watchers');
define('CWDS_KANBAN_TABLE_NOTIFICATIONS', $wpdb->prefix . 'cwds_kanban_notifications');

// Include classes
require_once CWDS_KANBAN_PATH . 'includes/class-database.php';
require_once CWDS_KANBAN_PATH . 'includes/class-auth.php';
require_once CWDS_KANBAN_PATH . 'includes/class-email.php';
require_once CWDS_KANBAN_PATH . 'includes/class-api.php';
require_once CWDS_KANBAN_PATH . 'includes/class-admin.php';
require_once CWDS_KANBAN_PATH . 'includes/class-shortcode.php';

/**
 * Main Plugin Class
 */
class CWDS_Kanban {

    private static $instance = null;

    public $database;
    public $auth;
    public $email;
    public $api;
    public $admin;
    public $shortcode;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->database = new CWDS_Kanban_Database();
        $this->auth = new CWDS_Kanban_Auth();
        $this->email = new CWDS_Kanban_Email();
        $this->api = new CWDS_Kanban_API();
        $this->admin = new CWDS_Kanban_Admin();
        $this->shortcode = new CWDS_Kanban_Shortcode();

        // Activation hook
        register_activation_hook(__FILE__, array($this->database, 'create_tables'));

        // DB upgrade check — creates new tables on plugin update
        add_action('plugins_loaded', array($this, 'check_db_upgrade'));

        // Handle magic link auth on init
        add_action('init', array($this->auth, 'handle_magic_link'));

        // Create upload directory on activation
        register_activation_hook(__FILE__, array($this, 'create_upload_dir'));
    }

    public function check_db_upgrade() {
        $installed_version = get_option('cwds_kanban_db_version', '0');
        if (version_compare($installed_version, CWDS_KANBAN_VERSION, '<')) {
            $this->database->create_tables();
        }
    }

    public function create_upload_dir() {
        $upload_dir = wp_upload_dir();
        $kanban_dir = $upload_dir['basedir'] . '/cwds-kanban-attachments';
        if (!file_exists($kanban_dir)) {
            wp_mkdir_p($kanban_dir);
            // Protect directory
            file_put_contents($kanban_dir . '/.htaccess', 'Options -Indexes');
            file_put_contents($kanban_dir . '/index.php', '<?php // Silence is golden');
        }
    }
}

// Initialize
function cwds_kanban() {
    return CWDS_Kanban::instance();
}
cwds_kanban();
