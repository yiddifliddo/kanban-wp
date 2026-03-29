<?php
if (!defined('ABSPATH')) exit;

class CWDS_Kanban_Auth {

    const COOKIE_NAME = 'cwds_kanban_session';
    const TOKEN_EXPIRY_HOURS = 48;
    const SESSION_EXPIRY_DAYS = 30;

    /**
     * Generate a magic link token for a member
     */
    public function generate_magic_link($member_id, $board_id) {
        global $wpdb;

        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . self::TOKEN_EXPIRY_HOURS . ' hours'));

        $wpdb->insert(CWDS_KANBAN_TABLE_TOKENS, array(
            'member_id' => $member_id,
            'board_id' => $board_id,
            'token' => $token,
            'expires_at' => $expires_at,
            'used' => 0
        ), array('%d', '%d', '%s', '%s', '%d'));

        // Build magic link URL
        $board = $wpdb->get_row($wpdb->prepare(
            "SELECT slug FROM " . CWDS_KANBAN_TABLE_BOARDS . " WHERE id = %d",
            $board_id
        ));

        $page_id = get_option('cwds_kanban_page_id');
        if ($page_id) {
            $base_url = get_permalink($page_id);
        } else {
            $base_url = home_url('/');
        }

        return add_query_arg(array(
            'cwds_kanban_token' => $token
        ), $base_url);
    }

    /**
     * Handle incoming magic link clicks
     */
    public function handle_magic_link() {
        if (!isset($_GET['cwds_kanban_token'])) {
            return;
        }

        global $wpdb;
        $token = sanitize_text_field($_GET['cwds_kanban_token']);

        // Look up the token
        $token_row = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, m.name, m.email, m.board_id, b.slug as board_slug
             FROM " . CWDS_KANBAN_TABLE_TOKENS . " t
             JOIN " . CWDS_KANBAN_TABLE_MEMBERS . " m ON t.member_id = m.id
             JOIN " . CWDS_KANBAN_TABLE_BOARDS . " b ON t.board_id = b.id
             WHERE t.token = %s AND t.expires_at > NOW() AND t.used = 0",
            $token
        ));

        if (!$token_row) {
            // Invalid or expired token
            wp_die(
                '<div style="font-family: Unbounded, sans-serif; text-align: center; padding: 60px; background: #f4f5f7; color: #172b4d; min-height: 100vh; display: flex; align-items: center; justify-content: center; flex-direction: column;">'
                . '<h1 style="font-size: 24px; margin-bottom: 16px;">Link Expired</h1>'
                . '<p style="color: #5e6c84; font-size: 14px;">This access link has expired or has already been used. Please request a new one from your project manager.</p>'
                . '</div>',
                'Link Expired',
                array('response' => 403)
            );
            return;
        }

        // Mark token as used
        $wpdb->update(
            CWDS_KANBAN_TABLE_TOKENS,
            array('used' => 1),
            array('id' => $token_row->id),
            array('%d'),
            array('%d')
        );

        // Create session token
        $session_token = bin2hex(random_bytes(32));
        $session_expires = date('Y-m-d H:i:s', strtotime('+' . self::SESSION_EXPIRY_DAYS . ' days'));

        // Store session
        $wpdb->insert(CWDS_KANBAN_TABLE_TOKENS, array(
            'member_id' => $token_row->member_id,
            'board_id' => $token_row->board_id,
            'token' => 'session_' . $session_token,
            'session_token' => $session_token,
            'expires_at' => $session_expires,
            'used' => 0
        ), array('%d', '%d', '%s', '%s', '%s', '%d'));

        // Set cookie
        setcookie(
            self::COOKIE_NAME,
            $session_token,
            time() + (self::SESSION_EXPIRY_DAYS * DAY_IN_SECONDS),
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );

        // Log activity
        $wpdb->insert(CWDS_KANBAN_TABLE_ACTIVITY, array(
            'board_id' => $token_row->board_id,
            'actor_name' => $token_row->name,
            'actor_type' => 'client',
            'action' => 'login',
            'details' => $token_row->name . ' accessed the board'
        ), array('%d', '%s', '%s', '%s', '%s'));

        // Redirect to board page (remove token from URL)
        $page_id = get_option('cwds_kanban_page_id');
        if ($page_id) {
            $redirect_url = add_query_arg('cwds_board', $token_row->board_slug, get_permalink($page_id));
        } else {
            $redirect_url = home_url('/');
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Validate a session and return member data
     */
    public function get_current_member() {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return false;
        }

        global $wpdb;
        $session_token = sanitize_text_field($_COOKIE[self::COOKIE_NAME]);

        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, m.name, m.email, m.avatar_color, m.role, m.board_id, b.slug as board_slug, b.title as board_title
             FROM " . CWDS_KANBAN_TABLE_TOKENS . " t
             JOIN " . CWDS_KANBAN_TABLE_MEMBERS . " m ON t.member_id = m.id
             JOIN " . CWDS_KANBAN_TABLE_BOARDS . " b ON t.board_id = b.id
             WHERE t.session_token = %s AND t.expires_at > NOW()",
            $session_token
        ));

        if (!$session) {
            return false;
        }

        return (object) array(
            'member_id' => $session->member_id,
            'board_id' => $session->board_id,
            'name' => $session->name,
            'email' => $session->email,
            'avatar_color' => $session->avatar_color,
            'role' => $session->role,
            'board_slug' => $session->board_slug,
            'board_title' => $session->board_title
        );
    }

    /**
     * Check if current request is from an authenticated member or WP admin
     */
    public function get_auth_context() {
        // Check WP admin first
        if (current_user_can('manage_options')) {
            $user = wp_get_current_user();
            return (object) array(
                'type' => 'admin',
                'name' => $user->display_name,
                'email' => $user->user_email,
                'board_id' => null // Admin can access all boards
            );
        }

        // Check client session
        $member = $this->get_current_member();
        if ($member) {
            return (object) array(
                'type' => 'client',
                'member_id' => $member->member_id,
                'name' => $member->name,
                'email' => $member->email,
                'board_id' => $member->board_id,
                'board_slug' => $member->board_slug
            );
        }

        return false;
    }

    /**
     * Clean up expired tokens (called via cron or admin action)
     */
    public function cleanup_expired_tokens() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM " . CWDS_KANBAN_TABLE_TOKENS . " WHERE expires_at < NOW()"
        );
    }
}
