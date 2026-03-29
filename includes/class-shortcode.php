<?php
if (!defined('ABSPATH')) exit;

class CWDS_Kanban_Shortcode {

    public function __construct() {
        add_shortcode('cwds_kanban', array($this, 'render'));
        // Register custom query var so WordPress doesn't strip or redirect it
        add_filter('query_vars', array($this, 'register_query_vars'));
        // Override page template to blank (no header/footer/hero)
        add_filter('template_include', array($this, 'override_template'));
    }

    /**
     * Force blank template on the Kanban board page
     */
    public function override_template($template) {
        $page_id = get_option('cwds_kanban_page_id');
        if ($page_id && is_page($page_id)) {
            $blank = CWDS_KANBAN_PATH . 'templates/blank-kanban.php';
            if (file_exists($blank)) {
                return $blank;
            }
        }
        return $template;
    }

    /**
     * Register cwds_board as an allowed query variable
     * This prevents WordPress from stripping or redirecting it
     */
    public function register_query_vars($vars) {
        $vars[] = 'cwds_board';
        return $vars;
    }

    public function render($atts) {
        // Determine which board to show
        $auth = cwds_kanban()->auth->get_auth_context();
        $board_id = null;
        $board_slug = null;

        // Check for board slug in query string — use cwds_board (unique, won't conflict with WP)
        if (isset($_GET['cwds_board'])) {
            $board_slug = sanitize_text_field($_GET['cwds_board']);
        }
        // Fallback: also accept 'board' param for backwards compat
        if (!$board_slug && isset($_GET['board'])) {
            $board_slug = sanitize_text_field($_GET['board']);
        }

        if (!$auth) {
            return $this->render_no_access();
        }

        global $wpdb;

        // If client, force their board (ignore any slug in URL)
        if ($auth->type === 'client') {
            $board_id = $auth->board_id;
        } elseif ($board_slug) {
            // Admin viewing a specific board
            $board = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM " . CWDS_KANBAN_TABLE_BOARDS . " WHERE slug = %s AND status = 'active'", $board_slug
            ));
            if ($board) {
                $board_id = $board->id;
            }
        }

        if (!$board_id) {
            // Admin with no board specified — direct them to wp-admin
            // NO board names or slugs are ever exposed on the frontend
            if ($auth->type === 'admin') {
                return $this->render_admin_no_board();
            }
            return $this->render_no_access();
        }

        // Enqueue frontend assets
        $this->enqueue_assets($board_id);

        return '<div id="cwds-kanban-app" data-board-id="' . esc_attr($board_id) . '"></div>';
    }

    private function enqueue_assets($board_id) {
        // Google Fonts - Unbounded
        wp_enqueue_style('cwds-kanban-fonts', 'https://fonts.googleapis.com/css2?family=Unbounded:wght@300;400;500;600;700&display=swap', array(), null);

        // SortableJS for drag and drop
        wp_enqueue_script('sortablejs', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js', array(), '1.15.0', true);

        // Plugin assets
        wp_enqueue_style('cwds-kanban-frontend', CWDS_KANBAN_URL . 'assets/css/frontend.css', array(), CWDS_KANBAN_VERSION);
        wp_enqueue_script('cwds-kanban-frontend', CWDS_KANBAN_URL . 'assets/js/frontend.js', array('sortablejs'), CWDS_KANBAN_VERSION, true);

        wp_localize_script('cwds-kanban-frontend', 'cwdsKanban', array(
            'restUrl' => rest_url('cwds-kanban/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'boardId' => $board_id,
            'maxUploadSize' => wp_max_upload_size()
        ));
    }

    /**
     * Non-authenticated visitor — no info leaked
     */
    private function render_no_access() {
        return '<div class="cwds-kanban-no-access" style="font-family:Unbounded,sans-serif;text-align:center;padding:80px 20px;background:#f4f5f7;color:#172b4d;border-radius:8px;border:1px solid #dfe1e6;">'
            . '<h2 style="font-size:22px;margin:0 0 12px;font-weight:700;">Access Required</h2>'
            . '<p style="color:#5e6c84;font-size:14px;margin:0;">Please use the access link sent to your email to view your project board.</p>'
            . '</div>';
    }

    /**
     * Admin landed on the page without specifying a board — direct them to wp-admin
     * NO board names or slugs are exposed on the frontend
     */
    private function render_admin_no_board() {
        $admin_url = admin_url('admin.php?page=cwds-kanban');
        return '<div style="font-family:Unbounded,sans-serif;text-align:center;padding:80px 20px;background:#f4f5f7;color:#172b4d;border-radius:8px;border:1px solid #dfe1e6;">'
            . '<h2 style="font-size:22px;margin:0 0 12px;font-weight:700;">No Board Selected</h2>'
            . '<p style="color:#5e6c84;font-size:14px;margin:0 0 24px;">Use the wp-admin dashboard to select and view a board.</p>'
            . '<a href="' . esc_url($admin_url) . '" style="display:inline-block;padding:12px 28px;background:#E3FF04;color:#000;text-decoration:none;font-family:Unbounded,sans-serif;font-size:12px;font-weight:700;border-radius:4px;letter-spacing:1px;text-transform:uppercase;">Go to Dashboard &rarr;</a>'
            . '</div>';
    }
}
