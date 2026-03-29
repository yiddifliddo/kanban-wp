<?php
if (!defined('ABSPATH')) exit;

class CWDS_Kanban_API {

    private $namespace = 'cwds-kanban/v1';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Authenticate API request — returns auth context or WP_Error
     */
    private function authenticate($request) {
        $auth = cwds_kanban()->auth->get_auth_context();
        if (!$auth) {
            return new WP_Error('unauthorized', 'Authentication required', array('status' => 401));
        }
        return $auth;
    }

    /**
     * Check that a client has access to the requested board
     */
    private function verify_board_access($auth, $board_id) {
        if ($auth->type === 'admin') return true;
        return (int) $auth->board_id === (int) $board_id;
    }

    public function register_routes() {

        // ── Board Data (full board load) ──
        register_rest_route($this->namespace, '/board/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_board'),
            'permission_callback' => '__return_true'
        ));

        // ── Columns ──
        register_rest_route($this->namespace, '/columns', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_column'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace, '/columns/reorder', array(
            'methods' => 'POST',
            'callback' => array($this, 'reorder_columns'),
            'permission_callback' => '__return_true'
        ));

        // ── Cards ──
        register_rest_route($this->namespace, '/cards', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_card'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace, '/cards/(?P<id>\d+)', array(
            array('methods' => 'GET', 'callback' => array($this, 'get_card'), 'permission_callback' => '__return_true'),
            array('methods' => 'PUT', 'callback' => array($this, 'update_card'), 'permission_callback' => '__return_true'),
            array('methods' => 'DELETE', 'callback' => array($this, 'delete_card'), 'permission_callback' => '__return_true'),
        ));

        register_rest_route($this->namespace, '/cards/move', array(
            'methods' => 'POST',
            'callback' => array($this, 'move_card'),
            'permission_callback' => '__return_true'
        ));

        // ── Labels ──
        register_rest_route($this->namespace, '/labels', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_label'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace, '/labels/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_label'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace, '/cards/(?P<card_id>\d+)/labels', array(
            'methods' => 'POST',
            'callback' => array($this, 'toggle_card_label'),
            'permission_callback' => '__return_true'
        ));

        // ── Members ──
        register_rest_route($this->namespace, '/cards/(?P<card_id>\d+)/members', array(
            'methods' => 'POST',
            'callback' => array($this, 'toggle_card_member'),
            'permission_callback' => '__return_true'
        ));

        // ── Checklists ──
        register_rest_route($this->namespace, '/cards/(?P<card_id>\d+)/checklists', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_checklist'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace, '/checklist-items', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_checklist_item'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace, '/checklist-items/(?P<id>\d+)', array(
            array('methods' => 'PUT', 'callback' => array($this, 'update_checklist_item'), 'permission_callback' => '__return_true'),
            array('methods' => 'DELETE', 'callback' => array($this, 'delete_checklist_item'), 'permission_callback' => '__return_true'),
        ));

        // ── Attachments ──
        register_rest_route($this->namespace, '/cards/(?P<card_id>\d+)/attachments', array(
            'methods' => 'POST',
            'callback' => array($this, 'upload_attachment'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace, '/attachments/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_attachment'),
            'permission_callback' => '__return_true'
        ));

        // ── Activity ──
        register_rest_route($this->namespace, '/cards/(?P<card_id>\d+)/activity', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_card_activity'),
            'permission_callback' => '__return_true'
        ));

        // ── Comments ──
        register_rest_route($this->namespace, '/cards/(?P<card_id>\d+)/comments', array(
            array('methods' => 'GET', 'callback' => array($this, 'get_comments'), 'permission_callback' => '__return_true'),
            array('methods' => 'POST', 'callback' => array($this, 'create_comment'), 'permission_callback' => '__return_true'),
        ));

        register_rest_route($this->namespace, '/comments/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_comment'),
            'permission_callback' => '__return_true'
        ));

        // ── Watchers ──
        register_rest_route($this->namespace, '/cards/(?P<card_id>\d+)/watch', array(
            'methods' => 'POST',
            'callback' => array($this, 'toggle_watch'),
            'permission_callback' => '__return_true'
        ));

        // ── Notification Preferences ──
        register_rest_route($this->namespace, '/notifications/preferences', array(
            array('methods' => 'GET', 'callback' => array($this, 'get_notification_prefs'), 'permission_callback' => '__return_true'),
            array('methods' => 'PUT', 'callback' => array($this, 'update_notification_prefs'), 'permission_callback' => '__return_true'),
        ));
    }

    // ─────────────────────────────────────────────
    // BOARD
    // ─────────────────────────────────────────────

    public function get_board($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $board_id = (int) $request['id'];

        if (!$this->verify_board_access($auth, $board_id)) {
            return new WP_Error('forbidden', 'Access denied', array('status' => 403));
        }

        $board = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_BOARDS . " WHERE id = %d AND status = 'active'", $board_id
        ));

        if (!$board) {
            return new WP_Error('not_found', 'Board not found', array('status' => 404));
        }

        // Get columns with cards
        $columns = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_COLUMNS . " WHERE board_id = %d ORDER BY position ASC", $board_id
        ));

        foreach ($columns as &$col) {
            $col->cards = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM " . CWDS_KANBAN_TABLE_CARDS . " WHERE column_id = %d ORDER BY position ASC", $col->id
            ));

            foreach ($col->cards as &$card) {
                // Get card labels
                $card->labels = $wpdb->get_results($wpdb->prepare(
                    "SELECT l.* FROM " . CWDS_KANBAN_TABLE_LABELS . " l
                     JOIN " . CWDS_KANBAN_TABLE_CARD_LABELS . " cl ON l.id = cl.label_id
                     WHERE cl.card_id = %d", $card->id
                ));

                // Get card members
                $card->members = $wpdb->get_results($wpdb->prepare(
                    "SELECT m.id, m.name, m.email, m.avatar_color FROM " . CWDS_KANBAN_TABLE_MEMBERS . " m
                     JOIN " . CWDS_KANBAN_TABLE_CARD_MEMBERS . " cm ON m.id = cm.member_id
                     WHERE cm.card_id = %d", $card->id
                ));

                // Get checklist progress
                $card->checklist_total = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM " . CWDS_KANBAN_TABLE_CHECKLIST_ITEMS . " ci
                     JOIN " . CWDS_KANBAN_TABLE_CHECKLISTS . " c ON ci.checklist_id = c.id
                     WHERE c.card_id = %d", $card->id
                ));
                $card->checklist_done = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM " . CWDS_KANBAN_TABLE_CHECKLIST_ITEMS . " ci
                     JOIN " . CWDS_KANBAN_TABLE_CHECKLISTS . " c ON ci.checklist_id = c.id
                     WHERE c.card_id = %d AND ci.is_checked = 1", $card->id
                ));

                // Get attachment count
                $card->attachment_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM " . CWDS_KANBAN_TABLE_ATTACHMENTS . " WHERE card_id = %d", $card->id
                ));
            }
        }

        // Get all board labels
        $labels = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_LABELS . " WHERE board_id = %d ORDER BY id ASC", $board_id
        ));

        // Get all board members
        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, email, avatar_color, role FROM " . CWDS_KANBAN_TABLE_MEMBERS . " WHERE board_id = %d ORDER BY name ASC", $board_id
        ));

        return rest_ensure_response(array(
            'board' => $board,
            'columns' => $columns,
            'labels' => $labels,
            'members' => $members,
            'auth' => array(
                'type' => $auth->type,
                'name' => $auth->name
            )
        ));
    }

    // ─────────────────────────────────────────────
    // COLUMNS
    // ─────────────────────────────────────────────

    public function create_column($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;
        if ($auth->type !== 'admin') return new WP_Error('forbidden', 'Admin only', array('status' => 403));

        global $wpdb;
        $params = $request->get_json_params();
        $board_id = (int) $params['board_id'];
        $title = sanitize_text_field($params['title']);

        $max_pos = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(position) FROM " . CWDS_KANBAN_TABLE_COLUMNS . " WHERE board_id = %d", $board_id
        ));

        $wpdb->insert(CWDS_KANBAN_TABLE_COLUMNS, array(
            'board_id' => $board_id,
            'title' => $title,
            'position' => $max_pos + 1
        ), array('%d', '%s', '%d'));

        return rest_ensure_response(array('id' => $wpdb->insert_id, 'title' => $title, 'position' => $max_pos + 1));
    }

    public function reorder_columns($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $params = $request->get_json_params();
        $order = $params['order']; // array of column IDs in new order

        foreach ($order as $position => $column_id) {
            $wpdb->update(
                CWDS_KANBAN_TABLE_COLUMNS,
                array('position' => $position),
                array('id' => (int) $column_id),
                array('%d'),
                array('%d')
            );
        }

        return rest_ensure_response(array('success' => true));
    }

    // ─────────────────────────────────────────────
    // CARDS
    // ─────────────────────────────────────────────

    public function create_card($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $params = $request->get_json_params();
        $board_id = (int) $params['board_id'];

        if (!$this->verify_board_access($auth, $board_id)) {
            return new WP_Error('forbidden', 'Access denied', array('status' => 403));
        }

        $column_id = (int) $params['column_id'];
        $title = sanitize_text_field($params['title']);

        $max_pos = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(position) FROM " . CWDS_KANBAN_TABLE_CARDS . " WHERE column_id = %d", $column_id
        ));

        $wpdb->insert(CWDS_KANBAN_TABLE_CARDS, array(
            'column_id' => $column_id,
            'board_id' => $board_id,
            'title' => $title,
            'position' => $max_pos + 1,
            'created_by' => $auth->name
        ), array('%d', '%d', '%s', '%d', '%s'));

        $card_id = $wpdb->insert_id;

        // Log activity
        $this->log_activity($board_id, $card_id, $auth->name, $auth->type, 'created', "created card \"{$title}\"");

        return rest_ensure_response(array(
            'id' => $card_id,
            'title' => $title,
            'column_id' => $column_id,
            'position' => $max_pos + 1,
            'labels' => [],
            'members' => [],
            'checklist_total' => 0,
            'checklist_done' => 0,
            'attachment_count' => 0
        ));
    }

    public function get_card($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $card_id = (int) $request['id'];

        $card = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_CARDS . " WHERE id = %d", $card_id
        ));

        if (!$card) return new WP_Error('not_found', 'Card not found', array('status' => 404));
        if (!$this->verify_board_access($auth, $card->board_id)) {
            return new WP_Error('forbidden', 'Access denied', array('status' => 403));
        }

        // Labels
        $card->labels = $wpdb->get_results($wpdb->prepare(
            "SELECT l.* FROM " . CWDS_KANBAN_TABLE_LABELS . " l
             JOIN " . CWDS_KANBAN_TABLE_CARD_LABELS . " cl ON l.id = cl.label_id
             WHERE cl.card_id = %d", $card_id
        ));

        // Members
        $card->members = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id, m.name, m.email, m.avatar_color FROM " . CWDS_KANBAN_TABLE_MEMBERS . " m
             JOIN " . CWDS_KANBAN_TABLE_CARD_MEMBERS . " cm ON m.id = cm.member_id
             WHERE cm.card_id = %d", $card_id
        ));

        // Checklists with items
        $card->checklists = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_CHECKLISTS . " WHERE card_id = %d ORDER BY position ASC", $card_id
        ));
        foreach ($card->checklists as &$cl) {
            $cl->items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM " . CWDS_KANBAN_TABLE_CHECKLIST_ITEMS . " WHERE checklist_id = %d ORDER BY position ASC", $cl->id
            ));
        }

        // Attachments
        $card->attachments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_ATTACHMENTS . " WHERE card_id = %d ORDER BY created_at DESC", $card_id
        ));

        // Activity
        $card->activity = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_ACTIVITY . " WHERE card_id = %d ORDER BY created_at DESC LIMIT 20", $card_id
        ));

        // Comments
        $card->comments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_COMMENTS . " WHERE card_id = %d ORDER BY created_at DESC LIMIT 100", $card_id
        ));

        // Watchers
        $card->watchers = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id, m.name FROM " . CWDS_KANBAN_TABLE_WATCHERS . " w
             JOIN " . CWDS_KANBAN_TABLE_MEMBERS . " m ON w.member_id = m.id
             WHERE w.card_id = %d", $card_id
        ));
        $card->is_watching = false;
        if ($auth->type === 'client' && isset($auth->member_id)) {
            $card->is_watching = (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . CWDS_KANBAN_TABLE_WATCHERS . " WHERE card_id = %d AND member_id = %d",
                $card_id, $auth->member_id
            ));
        }

        return rest_ensure_response($card);
    }

    public function update_card($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $card_id = (int) $request['id'];
        $params = $request->get_json_params();

        $card = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_CARDS . " WHERE id = %d", $card_id
        ));
        if (!$card) return new WP_Error('not_found', 'Card not found', array('status' => 404));
        if (!$this->verify_board_access($auth, $card->board_id)) {
            return new WP_Error('forbidden', 'Access denied', array('status' => 403));
        }

        $update_data = array();
        $update_format = array();

        if (isset($params['title'])) {
            $update_data['title'] = sanitize_text_field($params['title']);
            $update_format[] = '%s';
        }
        if (isset($params['description'])) {
            $update_data['description'] = wp_kses_post($params['description']);
            $update_format[] = '%s';
        }
        if (isset($params['due_date'])) {
            $update_data['due_date'] = $params['due_date'] ? sanitize_text_field($params['due_date']) : null;
            $update_format[] = '%s';
        }
        if (isset($params['is_complete'])) {
            $update_data['is_complete'] = (int) $params['is_complete'];
            $update_format[] = '%d';
        }

        if (!empty($update_data)) {
            $wpdb->update(CWDS_KANBAN_TABLE_CARDS, $update_data, array('id' => $card_id), $update_format, array('%d'));
            $this->log_activity($card->board_id, $card_id, $auth->name, $auth->type, 'updated', "updated card \"{$card->title}\"");
        }

        return rest_ensure_response(array('success' => true));
    }

    public function delete_card($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;
        if ($auth->type !== 'admin') return new WP_Error('forbidden', 'Admin only', array('status' => 403));

        global $wpdb;
        $card_id = (int) $request['id'];

        // Cleanup related data
        $wpdb->delete(CWDS_KANBAN_TABLE_CARD_LABELS, array('card_id' => $card_id), array('%d'));
        $wpdb->delete(CWDS_KANBAN_TABLE_CARD_MEMBERS, array('card_id' => $card_id), array('%d'));

        // Delete checklist items
        $checklists = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM " . CWDS_KANBAN_TABLE_CHECKLISTS . " WHERE card_id = %d", $card_id
        ));
        foreach ($checklists as $cl_id) {
            $wpdb->delete(CWDS_KANBAN_TABLE_CHECKLIST_ITEMS, array('checklist_id' => $cl_id), array('%d'));
        }
        $wpdb->delete(CWDS_KANBAN_TABLE_CHECKLISTS, array('card_id' => $card_id), array('%d'));

        // Delete attachment files
        $attachments = $wpdb->get_results($wpdb->prepare(
            "SELECT file_path FROM " . CWDS_KANBAN_TABLE_ATTACHMENTS . " WHERE card_id = %d", $card_id
        ));
        foreach ($attachments as $att) {
            if (file_exists($att->file_path)) unlink($att->file_path);
        }
        $wpdb->delete(CWDS_KANBAN_TABLE_ATTACHMENTS, array('card_id' => $card_id), array('%d'));

        $wpdb->delete(CWDS_KANBAN_TABLE_CARDS, array('id' => $card_id), array('%d'));

        return rest_ensure_response(array('success' => true));
    }

    public function move_card($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $params = $request->get_json_params();
        $card_id = (int) $params['card_id'];
        $new_column_id = (int) $params['column_id'];
        $new_position = (int) $params['position'];

        $card = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, col.title as old_column_title FROM " . CWDS_KANBAN_TABLE_CARDS . " c
             JOIN " . CWDS_KANBAN_TABLE_COLUMNS . " col ON c.column_id = col.id
             WHERE c.id = %d", $card_id
        ));

        if (!$card) return new WP_Error('not_found', 'Card not found', array('status' => 404));
        if (!$this->verify_board_access($auth, $card->board_id)) {
            return new WP_Error('forbidden', 'Access denied', array('status' => 403));
        }

        $old_column_id = $card->column_id;

        // Update card position
        $wpdb->update(
            CWDS_KANBAN_TABLE_CARDS,
            array('column_id' => $new_column_id, 'position' => $new_position),
            array('id' => $card_id),
            array('%d', '%d'),
            array('%d')
        );

        // Reorder cards in affected columns
        if (isset($params['column_cards']) && is_array($params['column_cards'])) {
            foreach ($params['column_cards'] as $col_id => $card_ids) {
                foreach ($card_ids as $pos => $cid) {
                    $wpdb->update(
                        CWDS_KANBAN_TABLE_CARDS,
                        array('column_id' => (int) $col_id, 'position' => $pos),
                        array('id' => (int) $cid),
                        array('%d', '%d'),
                        array('%d')
                    );
                }
            }
        }

        // Log if column changed
        if ((int) $old_column_id !== (int) $new_column_id) {
            $new_col = $wpdb->get_row($wpdb->prepare(
                "SELECT title FROM " . CWDS_KANBAN_TABLE_COLUMNS . " WHERE id = %d", $new_column_id
            ));
            $this->log_activity(
                $card->board_id, $card_id, $auth->name, $auth->type, 'moved',
                "moved \"{$card->title}\" from {$card->old_column_title} to {$new_col->title}"
            );
        }

        return rest_ensure_response(array('success' => true));
    }

    // ─────────────────────────────────────────────
    // LABELS
    // ─────────────────────────────────────────────

    public function create_label($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $params = $request->get_json_params();
        $board_id = (int) $params['board_id'];

        if (!$this->verify_board_access($auth, $board_id)) {
            return new WP_Error('forbidden', 'Access denied', array('status' => 403));
        }

        $title = sanitize_text_field($params['title'] ?? '');
        $color = sanitize_hex_color($params['color']);

        if (empty($color)) {
            return new WP_Error('invalid_color', 'Color is required', array('status' => 400));
        }

        // Prevent duplicate labels (same title + color on same board)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . CWDS_KANBAN_TABLE_LABELS . " WHERE board_id = %d AND title = %s AND color = %s",
            $board_id, $title, $color
        ));
        if ($existing) {
            return new WP_Error('duplicate_label', 'A label with this title and color already exists', array('status' => 409));
        }

        $wpdb->insert(CWDS_KANBAN_TABLE_LABELS, array(
            'board_id' => $board_id,
            'title' => $title,
            'color' => $color
        ), array('%d', '%s', '%s'));

        $new_label = array(
            'id' => $wpdb->insert_id,
            'board_id' => $board_id,
            'title' => $title,
            'color' => $color
        );

        return rest_ensure_response($new_label);
    }

    public function delete_label($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $label_id = (int) $request['id'];

        $label = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_LABELS . " WHERE id = %d", $label_id
        ));

        if (!$label) return new WP_Error('not_found', 'Label not found', array('status' => 404));
        if (!$this->verify_board_access($auth, $label->board_id)) {
            return new WP_Error('forbidden', 'Access denied', array('status' => 403));
        }

        // Remove from all cards first
        $wpdb->delete(CWDS_KANBAN_TABLE_CARD_LABELS, array('label_id' => $label_id), array('%d'));
        // Delete the label
        $wpdb->delete(CWDS_KANBAN_TABLE_LABELS, array('id' => $label_id), array('%d'));

        return rest_ensure_response(array('success' => true));
    }

    public function toggle_card_label($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $card_id = (int) $request['card_id'];
        $params = $request->get_json_params();
        $label_id = (int) $params['label_id'];

        // Check if already attached
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . CWDS_KANBAN_TABLE_CARD_LABELS . " WHERE card_id = %d AND label_id = %d",
            $card_id, $label_id
        ));

        if ($exists) {
            $wpdb->delete(CWDS_KANBAN_TABLE_CARD_LABELS, array('card_id' => $card_id, 'label_id' => $label_id), array('%d', '%d'));
            $action = 'removed';
        } else {
            $wpdb->insert(CWDS_KANBAN_TABLE_CARD_LABELS, array('card_id' => $card_id, 'label_id' => $label_id), array('%d', '%d'));
            $action = 'added';
        }

        return rest_ensure_response(array('action' => $action));
    }

    // ─────────────────────────────────────────────
    // MEMBERS
    // ─────────────────────────────────────────────

    public function toggle_card_member($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $card_id = (int) $request['card_id'];
        $params = $request->get_json_params();
        $member_id = (int) $params['member_id'];

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . CWDS_KANBAN_TABLE_CARD_MEMBERS . " WHERE card_id = %d AND member_id = %d",
            $card_id, $member_id
        ));

        if ($exists) {
            $wpdb->delete(CWDS_KANBAN_TABLE_CARD_MEMBERS, array('card_id' => $card_id, 'member_id' => $member_id), array('%d', '%d'));
            $action = 'removed';
        } else {
            $wpdb->insert(CWDS_KANBAN_TABLE_CARD_MEMBERS, array('card_id' => $card_id, 'member_id' => $member_id), array('%d', '%d'));
            $action = 'added';
        }

        return rest_ensure_response(array('action' => $action));
    }

    // ─────────────────────────────────────────────
    // CHECKLISTS
    // ─────────────────────────────────────────────

    public function create_checklist($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $card_id = (int) $request['card_id'];
        $params = $request->get_json_params();
        $title = sanitize_text_field($params['title'] ?? 'Checklist');

        $max_pos = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(position) FROM " . CWDS_KANBAN_TABLE_CHECKLISTS . " WHERE card_id = %d", $card_id
        ));

        $wpdb->insert(CWDS_KANBAN_TABLE_CHECKLISTS, array(
            'card_id' => $card_id,
            'title' => $title,
            'position' => $max_pos + 1
        ), array('%d', '%s', '%d'));

        return rest_ensure_response(array('id' => $wpdb->insert_id, 'title' => $title, 'items' => []));
    }

    public function create_checklist_item($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $params = $request->get_json_params();
        $checklist_id = (int) $params['checklist_id'];
        $text = sanitize_text_field($params['text']);

        $max_pos = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(position) FROM " . CWDS_KANBAN_TABLE_CHECKLIST_ITEMS . " WHERE checklist_id = %d", $checklist_id
        ));

        $wpdb->insert(CWDS_KANBAN_TABLE_CHECKLIST_ITEMS, array(
            'checklist_id' => $checklist_id,
            'text' => $text,
            'position' => $max_pos + 1
        ), array('%d', '%s', '%d'));

        return rest_ensure_response(array('id' => $wpdb->insert_id, 'text' => $text, 'is_checked' => 0));
    }

    public function update_checklist_item($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $item_id = (int) $request['id'];
        $params = $request->get_json_params();

        $update = array();
        $format = array();
        if (isset($params['text'])) {
            $update['text'] = sanitize_text_field($params['text']);
            $format[] = '%s';
        }
        if (isset($params['is_checked'])) {
            $update['is_checked'] = (int) $params['is_checked'];
            $format[] = '%d';
        }

        $wpdb->update(CWDS_KANBAN_TABLE_CHECKLIST_ITEMS, $update, array('id' => $item_id), $format, array('%d'));

        return rest_ensure_response(array('success' => true));
    }

    public function delete_checklist_item($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $wpdb->delete(CWDS_KANBAN_TABLE_CHECKLIST_ITEMS, array('id' => (int) $request['id']), array('%d'));

        return rest_ensure_response(array('success' => true));
    }

    // ─────────────────────────────────────────────
    // ATTACHMENTS
    // ─────────────────────────────────────────────

    public function upload_attachment($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        $card_id = (int) $request['card_id'];
        $files = $request->get_file_params();

        if (empty($files['file'])) {
            return new WP_Error('no_file', 'No file uploaded', array('status' => 400));
        }

        $file = $files['file'];
        $upload_dir = wp_upload_dir();
        $kanban_dir = $upload_dir['basedir'] . '/cwds-kanban-attachments';

        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe_name = sanitize_file_name($file['name']);
        $unique_name = wp_unique_filename($kanban_dir, $safe_name);
        $dest = $kanban_dir . '/' . $unique_name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return new WP_Error('upload_error', 'Failed to save file', array('status' => 500));
        }

        $file_url = $upload_dir['baseurl'] . '/cwds-kanban-attachments/' . $unique_name;

        global $wpdb;
        $wpdb->insert(CWDS_KANBAN_TABLE_ATTACHMENTS, array(
            'card_id' => $card_id,
            'file_name' => $safe_name,
            'file_path' => $dest,
            'file_url' => $file_url,
            'file_type' => $file['type'],
            'file_size' => $file['size'],
            'uploaded_by' => $auth->name
        ), array('%d', '%s', '%s', '%s', '%s', '%d', '%s'));

        // Log activity
        $card = $wpdb->get_row($wpdb->prepare("SELECT board_id FROM " . CWDS_KANBAN_TABLE_CARDS . " WHERE id = %d", $card_id));
        $this->log_activity($card->board_id, $card_id, $auth->name, $auth->type, 'attached', "attached \"{$safe_name}\"");

        return rest_ensure_response(array(
            'id' => $wpdb->insert_id,
            'file_name' => $safe_name,
            'file_url' => $file_url,
            'file_type' => $file['type'],
            'file_size' => $file['size'],
            'uploaded_by' => $auth->name,
            'created_at' => current_time('mysql')
        ));
    }

    public function delete_attachment($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $att_id = (int) $request['id'];

        $att = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_ATTACHMENTS . " WHERE id = %d", $att_id
        ));

        if ($att && file_exists($att->file_path)) {
            unlink($att->file_path);
        }

        $wpdb->delete(CWDS_KANBAN_TABLE_ATTACHMENTS, array('id' => $att_id), array('%d'));

        return rest_ensure_response(array('success' => true));
    }

    // ─────────────────────────────────────────────
    // ACTIVITY
    // ─────────────────────────────────────────────

    public function get_card_activity($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $card_id = (int) $request['card_id'];

        $activity = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_ACTIVITY . " WHERE card_id = %d ORDER BY created_at DESC LIMIT 50",
            $card_id
        ));

        return rest_ensure_response($activity);
    }

    /**
     * Helper: Log activity
     */
    private function log_activity($board_id, $card_id, $actor_name, $actor_type, $action, $details) {
        global $wpdb;
        $wpdb->insert(CWDS_KANBAN_TABLE_ACTIVITY, array(
            'board_id' => $board_id,
            'card_id' => $card_id,
            'actor_name' => $actor_name,
            'actor_type' => $actor_type,
            'action' => $action,
            'details' => $details
        ), array('%d', '%d', '%s', '%s', '%s', '%s'));
    }

    // ─────────────────────────────────────────────
    // COMMENTS
    // ─────────────────────────────────────────────

    public function get_comments($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $card_id = (int) $request['card_id'];

        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_COMMENTS . " WHERE card_id = %d ORDER BY created_at DESC LIMIT 100",
            $card_id
        ));

        return rest_ensure_response($comments);
    }

    public function create_comment($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $card_id = (int) $request['card_id'];
        $params = $request->get_json_params();
        $body = sanitize_textarea_field($params['body']);

        if (empty(trim($body))) {
            return new WP_Error('empty_comment', 'Comment cannot be empty', array('status' => 400));
        }

        // Get card for board_id
        $card = $wpdb->get_row($wpdb->prepare(
            "SELECT board_id, title FROM " . CWDS_KANBAN_TABLE_CARDS . " WHERE id = %d", $card_id
        ));
        if (!$card) return new WP_Error('not_found', 'Card not found', array('status' => 404));
        if (!$this->verify_board_access($auth, $card->board_id)) {
            return new WP_Error('forbidden', 'Access denied', array('status' => 403));
        }

        // Determine avatar color
        $avatar_color = '#E3FF04';
        if ($auth->type === 'client' && isset($auth->member_id)) {
            $member = $wpdb->get_row($wpdb->prepare(
                "SELECT avatar_color FROM " . CWDS_KANBAN_TABLE_MEMBERS . " WHERE id = %d", $auth->member_id
            ));
            if ($member) $avatar_color = $member->avatar_color;
        }

        // Extract @mentions
        $mentions = array();
        if (preg_match_all('/@([a-zA-Z\s]+?)(?=\s|$|,|\.)/', $body, $matches)) {
            $mentions = array_map('trim', $matches[1]);
        }

        $wpdb->insert(CWDS_KANBAN_TABLE_COMMENTS, array(
            'card_id' => $card_id,
            'board_id' => $card->board_id,
            'author_name' => $auth->name,
            'author_type' => $auth->type,
            'author_avatar_color' => $avatar_color,
            'body' => $body,
            'mentions' => !empty($mentions) ? json_encode($mentions) : null
        ), array('%d', '%d', '%s', '%s', '%s', '%s', '%s'));

        $comment_id = $wpdb->insert_id;

        // Log activity & notify watchers
        $this->log_activity($card->board_id, $card_id, $auth->name, $auth->type, 'commented', "commented on \"{$card->title}\"");
        $this->notify_watchers($card_id, 'commented', "commented on \"{$card->title}\"", $auth->name);

        return rest_ensure_response(array(
            'id' => $comment_id,
            'card_id' => $card_id,
            'author_name' => $auth->name,
            'author_type' => $auth->type,
            'author_avatar_color' => $avatar_color,
            'body' => $body,
            'mentions' => $mentions,
            'created_at' => current_time('mysql')
        ));
    }

    public function delete_comment($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;

        global $wpdb;
        $comment_id = (int) $request['id'];

        $comment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_COMMENTS . " WHERE id = %d", $comment_id
        ));

        if (!$comment) return new WP_Error('not_found', 'Comment not found', array('status' => 404));

        // Only admin or comment author can delete
        if ($auth->type !== 'admin' && $auth->name !== $comment->author_name) {
            return new WP_Error('forbidden', 'Cannot delete this comment', array('status' => 403));
        }

        $wpdb->delete(CWDS_KANBAN_TABLE_COMMENTS, array('id' => $comment_id), array('%d'));

        return rest_ensure_response(array('success' => true));
    }

    // ─────────────────────────────────────────────
    // WATCHERS
    // ─────────────────────────────────────────────

    public function toggle_watch($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;
        if ($auth->type !== 'client' || !isset($auth->member_id)) {
            // Admins — use their WP user email to find/create a watcher entry
            return new WP_Error('not_supported', 'Watching is for board members', array('status' => 400));
        }

        global $wpdb;
        $card_id = (int) $request['card_id'];
        $member_id = (int) $auth->member_id;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . CWDS_KANBAN_TABLE_WATCHERS . " WHERE card_id = %d AND member_id = %d",
            $card_id, $member_id
        ));

        if ($exists) {
            $wpdb->delete(CWDS_KANBAN_TABLE_WATCHERS, array('card_id' => $card_id, 'member_id' => $member_id), array('%d', '%d'));
            return rest_ensure_response(array('watching' => false));
        } else {
            $wpdb->insert(CWDS_KANBAN_TABLE_WATCHERS, array('card_id' => $card_id, 'member_id' => $member_id), array('%d', '%d'));
            return rest_ensure_response(array('watching' => true));
        }
    }

    // ─────────────────────────────────────────────
    // NOTIFICATION PREFERENCES
    // ─────────────────────────────────────────────

    public function get_notification_prefs($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;
        if ($auth->type !== 'client' || !isset($auth->member_id)) {
            return rest_ensure_response(array('notify_comments' => 1, 'notify_due_dates' => 1, 'notify_assignments' => 1, 'notify_card_moves' => 1, 'notify_attachments' => 0));
        }

        global $wpdb;
        $prefs = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . CWDS_KANBAN_TABLE_NOTIFICATIONS . " WHERE member_id = %d", $auth->member_id
        ));

        if (!$prefs) {
            return rest_ensure_response(array(
                'notify_comments' => 1, 'notify_due_dates' => 1,
                'notify_assignments' => 1, 'notify_card_moves' => 1, 'notify_attachments' => 0
            ));
        }

        return rest_ensure_response(array(
            'notify_comments' => (int) $prefs->notify_comments,
            'notify_due_dates' => (int) $prefs->notify_due_dates,
            'notify_assignments' => (int) $prefs->notify_assignments,
            'notify_card_moves' => (int) $prefs->notify_card_moves,
            'notify_attachments' => (int) $prefs->notify_attachments
        ));
    }

    public function update_notification_prefs($request) {
        $auth = $this->authenticate($request);
        if (is_wp_error($auth)) return $auth;
        if ($auth->type !== 'client' || !isset($auth->member_id)) {
            return new WP_Error('not_supported', 'Preferences are for board members', array('status' => 400));
        }

        global $wpdb;
        $params = $request->get_json_params();
        $member_id = (int) $auth->member_id;

        $data = array(
            'member_id' => $member_id,
            'notify_comments' => isset($params['notify_comments']) ? (int) $params['notify_comments'] : 1,
            'notify_due_dates' => isset($params['notify_due_dates']) ? (int) $params['notify_due_dates'] : 1,
            'notify_assignments' => isset($params['notify_assignments']) ? (int) $params['notify_assignments'] : 1,
            'notify_card_moves' => isset($params['notify_card_moves']) ? (int) $params['notify_card_moves'] : 1,
            'notify_attachments' => isset($params['notify_attachments']) ? (int) $params['notify_attachments'] : 0,
        );

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . CWDS_KANBAN_TABLE_NOTIFICATIONS . " WHERE member_id = %d", $member_id
        ));

        if ($existing) {
            $wpdb->update(CWDS_KANBAN_TABLE_NOTIFICATIONS, $data, array('member_id' => $member_id));
        } else {
            $wpdb->insert(CWDS_KANBAN_TABLE_NOTIFICATIONS, $data);
        }

        return rest_ensure_response(array('success' => true));
    }

    /**
     * Helper: Notify watchers of a card about a change
     */
    private function notify_watchers($card_id, $event_type, $details, $actor_name) {
        global $wpdb;

        // Get all watchers for this card
        $watchers = $wpdb->get_results($wpdb->prepare(
            "SELECT w.member_id, m.name, m.email FROM " . CWDS_KANBAN_TABLE_WATCHERS . " w
             JOIN " . CWDS_KANBAN_TABLE_MEMBERS . " m ON w.member_id = m.id
             WHERE w.card_id = %d", $card_id
        ));

        if (empty($watchers)) return;

        $card = $wpdb->get_row($wpdb->prepare(
            "SELECT c.title, b.title as board_title FROM " . CWDS_KANBAN_TABLE_CARDS . " c
             JOIN " . CWDS_KANBAN_TABLE_BOARDS . " b ON c.board_id = b.id
             WHERE c.id = %d", $card_id
        ));
        if (!$card) return;

        foreach ($watchers as $watcher) {
            // Don't notify the person who made the change
            if ($watcher->name === $actor_name) continue;

            // Check notification preferences
            $prefs = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM " . CWDS_KANBAN_TABLE_NOTIFICATIONS . " WHERE member_id = %d", $watcher->member_id
            ));

            $should_notify = true;
            if ($prefs) {
                switch ($event_type) {
                    case 'commented': $should_notify = (bool) $prefs->notify_comments; break;
                    case 'moved': $should_notify = (bool) $prefs->notify_card_moves; break;
                    case 'updated': $should_notify = (bool) $prefs->notify_due_dates; break;
                    case 'attached': $should_notify = (bool) $prefs->notify_attachments; break;
                    case 'assigned': $should_notify = (bool) $prefs->notify_assignments; break;
                }
            }

            if ($should_notify) {
                $subject = "[{$card->board_title}] {$actor_name} {$details}";
                $body = "<p><strong>{$actor_name}</strong> {$details}</p><p>Card: <strong>{$card->title}</strong></p><p>Board: {$card->board_title}</p>";
                $headers = array('Content-Type: text/html; charset=UTF-8');
                wp_mail($watcher->email, $subject, $body, $headers);
            }
        }
    }
}
