<?php
/**
 * Comment Hooks
 *
 * Integrates with WordPress comment system to intercept new comments
 * and add them to the moderation queue.
 */

namespace flavor\flavor;

defined('ABSPATH') || exit;

class CommentHooks
{
    private static $instance = null;

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Hook into new comment submission
        add_action('comment_post', [$this, 'on_comment_post'], 10, 3);

        // Add AI moderation status column to comments list
        add_filter('manage_edit-comments_columns', [$this, 'add_columns']);
        add_action('manage_comments_custom_column', [$this, 'render_column'], 10, 2);

        // Add row action for re-moderation
        add_filter('comment_row_actions', [$this, 'add_row_actions'], 10, 2);

        // Handle re-moderation action
        add_action('admin_init', [$this, 'handle_remoderate_action']);
    }

    /**
     * Called when a new comment is posted
     *
     * @param int        $comment_id       The comment ID
     * @param int|string $comment_approved 1 if approved, 0 if pending, 'spam' if spam
     * @param array      $commentdata     Comment data array
     */
    public function on_comment_post(int $comment_id, $comment_approved, array $commentdata): void
    {
        $settings = get_option('flavor_flavor_settings', []);
        $enabled = $settings['enabled'] ?? false;

        if (!$enabled) {
            return;
        }

        // Skip spam comments
        if ($comment_approved === 'spam') {
            return;
        }

        // Skip if user is admin and skip_admins is enabled
        $skip_admins = $settings['skip_admins'] ?? true;
        if ($skip_admins && isset($commentdata['user_id']) && $commentdata['user_id'] > 0) {
            $user = get_userdata($commentdata['user_id']);
            if ($user && $user->has_cap('manage_options')) {
                return;
            }
        }

        // Skip if already approved (e.g., whitelisted users)
        // Only queue comments that are pending moderation
        if ($comment_approved == 1) {
            // If auto_queue_approved is enabled, also queue approved comments
            $auto_queue_approved = $settings['auto_queue_approved'] ?? false;
            if (!$auto_queue_approved) {
                return;
            }
        }

        // Add to moderation queue
        $queue = ModerationQueue::get_instance();
        $queue->enqueue($comment_id);
    }

    /**
     * Add AI moderation column to comments list
     */
    public function add_columns(array $columns): array
    {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'comment') {
                $new_columns['ai_moderation'] = __('AI 审核', 'commentguard');
            }
        }
        return $new_columns;
    }

    /**
     * Render the AI moderation column content
     */
    public function render_column(string $column, int $comment_id): void
    {
        if ($column !== 'ai_moderation') {
            return;
        }

        $result = get_comment_meta($comment_id, '_ai_moderation_result', true);
        $reason = get_comment_meta($comment_id, '_ai_moderation_reason', true);

        if (empty($result)) {
            // Check if in queue
            global $wpdb;
            $queue_table = esc_sql(ModerationQueue::get_table_name());
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $queue_status = $wpdb->get_var(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "SELECT status FROM `{$queue_table}` WHERE comment_id = %d ORDER BY created_at DESC LIMIT 1",
                    $comment_id
                )
            );

            if ($queue_status) {
                $status_labels = [
                    'pending' => '<span style="color:#996800">⏳ ' . esc_html__('队列中', 'commentguard') . '</span>',
                    'processing' => '<span style="color:#0073aa">🔄 ' . esc_html__('处理中', 'commentguard') . '</span>',
                    'error' => '<span style="color:#dc3232">❌ ' . esc_html__('错误', 'commentguard') . '</span>',
                ];
                echo wp_kses_post($status_labels[$queue_status] ?? '—');
            } else {
                echo '—';
            }
            return;
        }

        $labels = [
            'approved' => '<span style="color:#46b450" title="' . esc_attr($reason) . '">✅ ' . esc_html__('通过', 'commentguard') . '</span>',
            'rejected' => '<span style="color:#dc3232" title="' . esc_attr($reason) . '">🚫 ' . esc_html__('拒绝', 'commentguard') . '</span>',
            'flagged' => '<span style="color:#996800" title="' . esc_attr($reason) . '">⚠️ ' . esc_html__('需复审', 'commentguard') . '</span>',
        ];

        echo wp_kses_post($labels[$result] ?? '—');
    }

    /**
     * Add "Re-moderate" row action
     */
    public function add_row_actions(array $actions, \WP_Comment $comment): array
    {
        $settings = get_option('flavor_flavor_settings', []);
        if (empty($settings['enabled'])) {
            return $actions;
        }

        $url = wp_nonce_url(
            admin_url('comment.php?action=ai_remoderate&c=' . $comment->comment_ID),
            'ai_remoderate_' . $comment->comment_ID
        );

        $actions['ai_remoderate'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($url),
            esc_html__('AI 重新审核', 'commentguard')
        );

        return $actions;
    }

    /**
     * Handle re-moderation action
     */
    public function handle_remoderate_action(): void
    {
        if (!isset($_GET['action']) || $_GET['action'] !== 'ai_remoderate') {
            return;
        }

        $comment_id = isset($_GET['c']) ? absint($_GET['c']) : 0;
        if (!$comment_id) {
            return;
        }

        check_admin_referer('ai_remoderate_' . $comment_id);

        if (!current_user_can('moderate_comments')) {
            wp_die(esc_html__('You do not have permission to do this.', 'commentguard'));
        }

        // Set comment back to pending
        wp_set_comment_status($comment_id, 'hold');

        // Clear old meta
        delete_comment_meta($comment_id, '_ai_moderation_result');
        delete_comment_meta($comment_id, '_ai_moderation_reason');

        // Re-queue
        ModerationQueue::get_instance()->enqueue($comment_id);

        wp_safe_redirect(admin_url('edit-comments.php?ai_remoderated=1'));
        exit;
    }
}
