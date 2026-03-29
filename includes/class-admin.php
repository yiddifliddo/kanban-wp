<?php
if (!defined('ABSPATH')) exit;

class CWDS_Kanban_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
    }

    public function add_menu() {
        add_menu_page(
            'CWDS Kanban',
            'Kanban Boards',
            'manage_options',
            'cwds-kanban',
            array($this, 'render_boards_page'),
            'dashicons-columns',
            30
        );

        add_submenu_page(
            'cwds-kanban',
            'All Boards',
            'All Boards',
            'manage_options',
            'cwds-kanban',
            array($this, 'render_boards_page')
        );

        add_submenu_page(
            'cwds-kanban',
            'Add New Board',
            'Add New Board',
            'manage_options',
            'cwds-kanban-new',
            array($this, 'render_new_board_page')
        );

        add_submenu_page(
            'cwds-kanban',
            'Settings',
            'Settings',
            'manage_options',
            'cwds-kanban-settings',
            array($this, 'render_settings_page')
        );
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'cwds-kanban') === false) return;

        wp_enqueue_style('cwds-kanban-admin', CWDS_KANBAN_URL . 'assets/css/admin.css', array(), CWDS_KANBAN_VERSION);
        wp_enqueue_script('cwds-kanban-admin', CWDS_KANBAN_URL . 'assets/js/admin.js', array('jquery'), CWDS_KANBAN_VERSION, true);

        wp_localize_script('cwds-kanban-admin', 'cwdsKanbanAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('cwds-kanban/v1/'),
            'nonce' => wp_create_nonce('wp_rest')
        ));
    }

    /**
     * Handle admin form submissions
     */
    public function handle_admin_actions() {
        if (!current_user_can('manage_options')) return;

        // Create board
        if (isset($_POST['cwds_kanban_create_board']) && wp_verify_nonce($_POST['_wpnonce'], 'cwds_kanban_create_board')) {
            $this->create_board();
        }

        // Add member to board
        if (isset($_POST['cwds_kanban_add_member']) && wp_verify_nonce($_POST['_wpnonce'], 'cwds_kanban_add_member')) {
            $this->add_member();
        }

        // Send / resend magic link
        if (isset($_GET['action']) && $_GET['action'] === 'send_invite' && isset($_GET['member_id'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'send_invite_' . $_GET['member_id'])) {
                $this->send_invite((int) $_GET['member_id']);
            }
        }

        // Add label
        if (isset($_POST['cwds_kanban_add_label']) && wp_verify_nonce($_POST['_wpnonce'], 'cwds_kanban_add_label')) {
            $this->add_label();
        }

        // Add column
        if (isset($_POST['cwds_kanban_add_column']) && wp_verify_nonce($_POST['_wpnonce'], 'cwds_kanban_add_column')) {
            $this->add_column();
        }

        // Setup page
        if (isset($_POST['cwds_kanban_setup_page']) && wp_verify_nonce($_POST['_wpnonce'], 'cwds_kanban_setup_page')) {
            $this->setup_page();
        }
    }

    private function create_board() {
        global $wpdb;

        $title = sanitize_text_field($_POST['board_title']);
        $slug = bin2hex(random_bytes(6)); // 12-char random hex — unguessable
        $description = sanitize_textarea_field($_POST['board_description'] ?? '');

        // Ensure unique slug (extremely unlikely collision but safe)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . CWDS_KANBAN_TABLE_BOARDS . " WHERE slug = %s", $slug
        ));
        if ($existing) $slug = bin2hex(random_bytes(6));

        $wpdb->insert(CWDS_KANBAN_TABLE_BOARDS, array(
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'created_by' => get_current_user_id()
        ), array('%s', '%s', '%s', '%d'));

        $board_id = $wpdb->insert_id;

        // Create default columns
        $defaults = array('To-Do', 'In Progress', 'Review', 'Done');
        foreach ($defaults as $pos => $col_title) {
            $wpdb->insert(CWDS_KANBAN_TABLE_COLUMNS, array(
                'board_id' => $board_id,
                'title' => $col_title,
                'position' => $pos
            ), array('%d', '%s', '%d'));
        }

        // Add admin as a member
        $user = wp_get_current_user();
        $wpdb->insert(CWDS_KANBAN_TABLE_MEMBERS, array(
            'board_id' => $board_id,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar_color' => '#E3FF04',
            'role' => 'admin'
        ), array('%d', '%s', '%s', '%s', '%s'));

        wp_redirect(admin_url('admin.php?page=cwds-kanban&action=edit&board_id=' . $board_id . '&msg=created'));
        exit;
    }

    private function add_member() {
        global $wpdb;

        $board_id = (int) $_POST['board_id'];
        $name = sanitize_text_field($_POST['member_name']);
        $email = sanitize_email($_POST['member_email']);

        // Random avatar color from brand-friendly palette
        $colors = array('#E3FF04', '#04FFE3', '#FF04E3', '#04E3FF', '#FFE304', '#E304FF');
        $color = $colors[array_rand($colors)];

        $wpdb->insert(CWDS_KANBAN_TABLE_MEMBERS, array(
            'board_id' => $board_id,
            'name' => $name,
            'email' => $email,
            'avatar_color' => $color,
            'role' => 'client'
        ), array('%d', '%s', '%s', '%s', '%s'));

        $member_id = $wpdb->insert_id;

        // Auto-send invite
        $this->send_invite($member_id, false);

        wp_redirect(admin_url('admin.php?page=cwds-kanban&action=edit&board_id=' . $board_id . '&msg=member_added'));
        exit;
    }

    private function send_invite($member_id, $redirect = true) {
        global $wpdb;

        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, b.title as board_title FROM " . CWDS_KANBAN_TABLE_MEMBERS . " m
             JOIN " . CWDS_KANBAN_TABLE_BOARDS . " b ON m.board_id = b.id
             WHERE m.id = %d", $member_id
        ));

        if (!$member) return;

        $magic_link = cwds_kanban()->auth->generate_magic_link($member->id, $member->board_id);
        cwds_kanban()->email->send_invite($member->email, $member->name, $member->board_title, $magic_link);

        if ($redirect) {
            wp_redirect(admin_url('admin.php?page=cwds-kanban&action=edit&board_id=' . $member->board_id . '&msg=invite_sent'));
            exit;
        }
    }

    private function add_label() {
        global $wpdb;
        $board_id = (int) $_POST['board_id'];
        $title = sanitize_text_field($_POST['label_title']);
        $color = sanitize_hex_color($_POST['label_color']);

        $wpdb->insert(CWDS_KANBAN_TABLE_LABELS, array(
            'board_id' => $board_id,
            'title' => $title,
            'color' => $color
        ), array('%d', '%s', '%s'));

        wp_redirect(admin_url('admin.php?page=cwds-kanban&action=edit&board_id=' . $board_id . '&msg=label_added'));
        exit;
    }

    private function add_column() {
        global $wpdb;
        $board_id = (int) $_POST['board_id'];
        $title = sanitize_text_field($_POST['column_title']);

        $max_pos = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(position) FROM " . CWDS_KANBAN_TABLE_COLUMNS . " WHERE board_id = %d", $board_id
        ));

        $wpdb->insert(CWDS_KANBAN_TABLE_COLUMNS, array(
            'board_id' => $board_id,
            'title' => $title,
            'position' => $max_pos + 1
        ), array('%d', '%s', '%d'));

        wp_redirect(admin_url('admin.php?page=cwds-kanban&action=edit&board_id=' . $board_id . '&msg=column_added'));
        exit;
    }

    private function setup_page() {
        // Create or find the Kanban board page
        $page_id = get_option('cwds_kanban_page_id');

        if (!$page_id || !get_post($page_id)) {
            $page_id = wp_insert_post(array(
                'post_title' => 'Project Board',
                'post_content' => '[cwds_kanban]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => get_current_user_id()
            ));
            update_option('cwds_kanban_page_id', $page_id);
        }

        wp_redirect(admin_url('admin.php?page=cwds-kanban-settings&msg=page_created'));
        exit;
    }

    // ─────────────────────────────────────────────
    // RENDER PAGES
    // ─────────────────────────────────────────────

    public function render_boards_page() {
        global $wpdb;

        // Edit single board view
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['board_id'])) {
            $this->render_edit_board_page((int) $_GET['board_id']);
            return;
        }

        $boards = $wpdb->get_results(
            "SELECT b.*, COUNT(DISTINCT m.id) as member_count, COUNT(DISTINCT c.id) as card_count
             FROM " . CWDS_KANBAN_TABLE_BOARDS . " b
             LEFT JOIN " . CWDS_KANBAN_TABLE_MEMBERS . " m ON b.id = m.board_id AND m.role = 'client'
             LEFT JOIN " . CWDS_KANBAN_TABLE_CARDS . " c ON b.id = c.board_id
             WHERE b.status = 'active'
             GROUP BY b.id
             ORDER BY b.created_at DESC"
        );

        $msg = isset($_GET['msg']) ? $_GET['msg'] : '';
        ?>
        <div class="wrap cwds-kanban-admin">
            <h1 class="wp-heading-inline">Kanban Boards</h1>
            <a href="<?php echo admin_url('admin.php?page=cwds-kanban-new'); ?>" class="page-title-action">Add New Board</a>

            <?php if ($msg === 'created'): ?>
                <div class="notice notice-success is-dismissible"><p>Board created successfully!</p></div>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>Board</th>
                        <th>Slug</th>
                        <th>Clients</th>
                        <th>Cards</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($boards)): ?>
                        <tr><td colspan="6">No boards yet. <a href="<?php echo admin_url('admin.php?page=cwds-kanban-new'); ?>">Create your first board</a>.</td></tr>
                    <?php else: ?>
                        <?php foreach ($boards as $board): ?>
                            <tr>
                                <td><strong><a href="<?php echo admin_url('admin.php?page=cwds-kanban&action=edit&board_id=' . $board->id); ?>"><?php echo esc_html($board->title); ?></a></strong></td>
                                <td><code><?php echo esc_html($board->slug); ?></code></td>
                                <td><?php echo $board->member_count; ?></td>
                                <td><?php echo $board->card_count; ?></td>
                                <td><?php echo date('M j, Y', strtotime($board->created_at)); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=cwds-kanban&action=edit&board_id=' . $board->id); ?>">Manage</a>
                                    <?php
                                    $page_id = get_option('cwds_kanban_page_id');
                                    if (!$page_id || !get_post($page_id)) {
                                        // Auto-create the board page if missing
                                        $page_id = wp_insert_post(array(
                                            'post_title' => 'Project Board',
                                            'post_content' => '[cwds_kanban]',
                                            'post_status' => 'publish',
                                            'post_type' => 'page',
                                            'post_author' => get_current_user_id()
                                        ));
                                        update_option('cwds_kanban_page_id', $page_id);
                                    }
                                    $url = get_permalink($page_id) . '?cwds_board=' . $board->slug;
                                    ?>
                                    | <a href="<?php echo esc_url($url); ?>" target="_blank">View Board</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_edit_board_page($board_id) {
        global $wpdb;

        $board = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_BOARDS . " WHERE id = %d", $board_id
        ));

        if (!$board) {
            echo '<div class="wrap"><h1>Board not found</h1></div>';
            return;
        }

        $columns = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_COLUMNS . " WHERE board_id = %d ORDER BY position ASC", $board_id
        ));

        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_MEMBERS . " WHERE board_id = %d ORDER BY role DESC, name ASC", $board_id
        ));

        $labels = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_LABELS . " WHERE board_id = %d ORDER BY id ASC", $board_id
        ));

        $msg = isset($_GET['msg']) ? $_GET['msg'] : '';

        $page_id = get_option('cwds_kanban_page_id');
        if (!$page_id || !get_post($page_id)) {
            $page_id = wp_insert_post(array(
                'post_title' => 'Project Board',
                'post_content' => '[cwds_kanban]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => get_current_user_id()
            ));
            update_option('cwds_kanban_page_id', $page_id);
        }
        $board_url = get_permalink($page_id) . '?cwds_board=' . $board->slug;
        ?>
        <div class="wrap cwds-kanban-admin">
            <h1>
                <a href="<?php echo admin_url('admin.php?page=cwds-kanban'); ?>">&larr; All Boards</a> &nbsp;/&nbsp;
                <?php echo esc_html($board->title); ?>
            </h1>

            <?php if ($msg === 'member_added'): ?>
                <div class="notice notice-success is-dismissible"><p>Member added and invite sent!</p></div>
            <?php elseif ($msg === 'invite_sent'): ?>
                <div class="notice notice-success is-dismissible"><p>Invite sent!</p></div>
            <?php elseif ($msg === 'label_added'): ?>
                <div class="notice notice-success is-dismissible"><p>Label added!</p></div>
            <?php elseif ($msg === 'column_added'): ?>
                <div class="notice notice-success is-dismissible"><p>Column added!</p></div>
            <?php endif; ?>

            <p>Board URL: <a href="<?php echo esc_url($board_url); ?>" target="_blank"><code><?php echo esc_url($board_url); ?></code></a> (admin view) &nbsp; <a href="<?php echo esc_url($board_url); ?>" target="_blank" class="button">View Board &rarr;</a></p>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">

                <!-- Members Section -->
                <div class="postbox">
                    <h2 class="hndle" style="padding: 12px;">Members</h2>
                    <div class="inside">
                        <table class="wp-list-table widefat fixed striped">
                            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:<?php echo esc_attr($member->avatar_color); ?>;margin-right:6px;vertical-align:middle;"></span><?php echo esc_html($member->name); ?></td>
                                        <td><?php echo esc_html($member->email); ?></td>
                                        <td><?php echo esc_html(ucfirst($member->role)); ?></td>
                                        <td>
                                            <?php if ($member->role === 'client'): ?>
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cwds-kanban&action=send_invite&member_id=' . $member->id), 'send_invite_' . $member->id); ?>">Resend Invite</a>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <h4 style="margin-top:16px;">Add Client</h4>
                        <form method="post" style="display:flex;gap:8px;align-items:end;">
                            <?php wp_nonce_field('cwds_kanban_add_member'); ?>
                            <input type="hidden" name="board_id" value="<?php echo $board_id; ?>">
                            <div><label>Name</label><br><input type="text" name="member_name" required style="width:160px;"></div>
                            <div><label>Email</label><br><input type="email" name="member_email" required style="width:200px;"></div>
                            <button type="submit" name="cwds_kanban_add_member" class="button button-primary">Add & Send Invite</button>
                        </form>
                    </div>
                </div>

                <!-- Labels Section -->
                <div class="postbox">
                    <h2 class="hndle" style="padding: 12px;">Labels</h2>
                    <div class="inside">
                        <?php if ($labels): ?>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
                                <?php foreach ($labels as $label): ?>
                                    <span style="display:inline-block;padding:4px 12px;border-radius:4px;background:<?php echo esc_attr($label->color); ?>;color:#000;font-size:12px;font-weight:600;"><?php echo esc_html($label->title); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <h4>Add Label</h4>
                        <form method="post" style="display:flex;gap:8px;align-items:end;">
                            <?php wp_nonce_field('cwds_kanban_add_label'); ?>
                            <input type="hidden" name="board_id" value="<?php echo $board_id; ?>">
                            <div><label>Title</label><br><input type="text" name="label_title" required style="width:160px;"></div>
                            <div><label>Color</label><br><input type="color" name="label_color" value="#E3FF04" style="width:50px;height:30px;"></div>
                            <button type="submit" name="cwds_kanban_add_label" class="button">Add Label</button>
                        </form>
                    </div>
                </div>

                <!-- Columns Section -->
                <div class="postbox" style="grid-column: 1 / -1;">
                    <h2 class="hndle" style="padding: 12px;">Columns</h2>
                    <div class="inside">
                        <div style="display:flex;gap:12px;margin-bottom:16px;">
                            <?php foreach ($columns as $col): ?>
                                <div style="padding:10px 20px;background:#f0f0f0;border-radius:4px;border-left:3px solid <?php echo esc_attr($col->color); ?>;">
                                    <strong><?php echo esc_html($col->title); ?></strong>
                                    <br><small>Position: <?php echo $col->position; ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <form method="post" style="display:flex;gap:8px;align-items:end;">
                            <?php wp_nonce_field('cwds_kanban_add_column'); ?>
                            <input type="hidden" name="board_id" value="<?php echo $board_id; ?>">
                            <div><label>Column Title</label><br><input type="text" name="column_title" required style="width:200px;"></div>
                            <button type="submit" name="cwds_kanban_add_column" class="button">Add Column</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_new_board_page() {
        ?>
        <div class="wrap cwds-kanban-admin">
            <h1>Create New Board</h1>
            <form method="post" style="max-width: 600px;">
                <?php wp_nonce_field('cwds_kanban_create_board'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="board_title">Board Title</label></th>
                        <td><input type="text" name="board_title" id="board_title" class="regular-text" required placeholder="e.g., Acme Corp Website Redesign"></td>
                    </tr>
                    <tr>
                        <th><label for="board_description">Description</label></th>
                        <td><textarea name="board_description" id="board_description" rows="3" class="large-text" placeholder="Brief description of this project board..."></textarea></td>
                    </tr>
                </table>
                <p class="description">Default columns (To-Do, In Progress, Review, Done) will be created automatically. You can customize them after creation.</p>
                <p><button type="submit" name="cwds_kanban_create_board" class="button button-primary button-hero">Create Board</button></p>
            </form>
        </div>
        <?php
    }

    public function render_settings_page() {
        $page_id = get_option('cwds_kanban_page_id');
        $page = $page_id ? get_post($page_id) : null;
        $msg = isset($_GET['msg']) ? $_GET['msg'] : '';
        ?>
        <div class="wrap cwds-kanban-admin">
            <h1>Kanban Board Settings</h1>

            <?php if ($msg === 'page_created'): ?>
                <div class="notice notice-success is-dismissible"><p>Board page created!</p></div>
            <?php endif; ?>

            <div class="postbox" style="max-width:600px;margin-top:20px;">
                <h2 class="hndle" style="padding:12px;">Board Display Page</h2>
                <div class="inside">
                    <?php if ($page): ?>
                        <p>Board page: <a href="<?php echo get_permalink($page_id); ?>" target="_blank"><strong><?php echo esc_html($page->post_title); ?></strong></a></p>
                        <p class="description">This page contains the <code>[cwds_kanban]</code> shortcode. Clients access their boards through this page.</p>
                    <?php else: ?>
                        <p>No board page has been created yet. Click below to create one.</p>
                        <form method="post">
                            <?php wp_nonce_field('cwds_kanban_setup_page'); ?>
                            <button type="submit" name="cwds_kanban_setup_page" class="button button-primary">Create Board Page</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="postbox" style="max-width:600px;margin-top:20px;">
                <h2 class="hndle" style="padding:12px;">Shortcode</h2>
                <div class="inside">
                    <p>Use this shortcode on any page:</p>
                    <code style="display:block;padding:10px;background:#f5f5f5;font-size:14px;">[cwds_kanban]</code>
                    <p class="description" style="margin-top:8px;">Clients will see their board based on their magic link session. Admins will see all boards.</p>
                </div>
            </div>
        </div>
        <?php
    }
}
