<?php
/**
 * Audit Log
 *
 * Records AI moderation decisions for review and transparency.
 * Can be enabled/disabled via settings.
 */

namespace flavor\flavor;

defined('ABSPATH') || exit;

class AuditLog
{
    private static $instance = null;

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Get table name
     */
    public static function get_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ai_comment_audit_log';
    }

    /**
     * Create the audit log table
     */
    public static function create_table(): void
    {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            comment_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(20) NOT NULL,
            reason TEXT DEFAULT NULL,
            ai_provider VARCHAR(50) DEFAULT NULL,
            ai_model VARCHAR(100) DEFAULT NULL,
            token_usage TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_comment_id (comment_id),
            KEY idx_action (action),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Log a moderation action
     */
    public function log(int $comment_id, string $action, string $reason, array $meta = []): ?int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert(
            self::get_table_name(),
            [
                'comment_id' => $comment_id,
                'action' => $action,
                'reason' => $reason,
                'ai_provider' => $meta['ai_provider'] ?? null,
                'ai_model' => $meta['ai_model'] ?? null,
                'token_usage' => isset($meta['usage']) ? wp_json_encode($meta['usage']) : null,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return $inserted ? (int) $wpdb->insert_id : null;
    }

    /**
     * Get logs with pagination and filtering
     */
    public function get_logs(array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'action' => '',
            'per_page' => 20,
            'page' => 1,
            'order' => 'DESC',
        ];
        $args = wp_parse_args($args, $defaults);
        $table = esc_sql(self::get_table_name());
        $comments = esc_sql($wpdb->comments);
        $posts = esc_sql($wpdb->posts);

        $offset = absint(($args['page'] - 1) * $args['per_page']);
        $per_page = absint($args['per_page']);
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // All table names are from esc_sql(), $order is whitelisted to ASC/DESC only.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if (!empty($args['action'])) {
            $query = $wpdb->prepare(
                "SELECT l.*, c.comment_content, c.comment_author, p.post_title
                 FROM `{$table}` l
                 LEFT JOIN `{$comments}` c ON l.comment_id = c.comment_ID
                 LEFT JOIN `{$posts}` p ON c.comment_post_ID = p.ID
                 WHERE l.action = %s
                 ORDER BY l.created_at {$order}
                 LIMIT %d OFFSET %d",
                $args['action'],
                $per_page,
                $offset
            );
            $count_query = $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` l WHERE l.action = %s",
                $args['action']
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT l.*, c.comment_content, c.comment_author, p.post_title
                 FROM `{$table}` l
                 LEFT JOIN `{$comments}` c ON l.comment_id = c.comment_ID
                 LEFT JOIN `{$posts}` p ON c.comment_post_ID = p.ID
                 ORDER BY l.created_at {$order}
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            );
            $count_query = "SELECT COUNT(*) FROM `{$table}` l";
        }

        $items = $wpdb->get_results($query);
        $total = (int) $wpdb->get_var($count_query);

        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

        return [
            'items' => $items ?: [],
            'total' => $total,
            'pages' => ceil($total / $per_page),
        ];
    }

    /**
     * Clear all logs
     */
    public function clear_all(): int
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->query("TRUNCATE TABLE `" . esc_sql(self::get_table_name()) . "`");
    }

    /**
     * Cleanup old logs
     */
    public function cleanup(int $days = 30): int
    {
        global $wpdb;

        $table = esc_sql(self::get_table_name());
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$table}` WHERE created_at < %s",
                gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS))
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $result;
    }
}
