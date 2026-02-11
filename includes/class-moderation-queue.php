<?php
/**
 * Moderation Queue
 *
 * Manages the custom database table for the comment moderation queue.
 * Comments enter the queue when submitted and are processed asynchronously.
 */

namespace flavor\flavor;

defined('ABSPATH') || exit;

class ModerationQueue
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
        return $wpdb->prefix . 'ai_comment_queue';
    }

    /**
     * Create the queue table
     */
    public static function create_table(): void
    {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            comment_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            result VARCHAR(20) DEFAULT NULL,
            reason TEXT DEFAULT NULL,
            ai_provider VARCHAR(50) DEFAULT NULL,
            ai_model VARCHAR(100) DEFAULT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_comment_id (comment_id),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('flavor_flavor_db_version', FLAVOR_FLAVOR_DB_VERSION);
    }

    /**
     * Add a comment to the moderation queue
     */
    public function enqueue(int $comment_id): ?int
    {
        global $wpdb;
        $table = esc_sql(self::get_table_name());

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT id FROM `{$table}` WHERE comment_id = %d AND status IN ('pending', 'processing')",
                $comment_id
            )
        );

        if ($existing) {
            return (int) $existing;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert(
            self::get_table_name(),
            [
                'comment_id' => $comment_id,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s']
        );

        return $inserted ? (int) $wpdb->insert_id : null;
    }

    /**
     * Get the next pending item from the queue
     */
    public function dequeue(): ?object
    {
        global $wpdb;
        $table = esc_sql(self::get_table_name());

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $item = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM `{$table}` WHERE status = %s ORDER BY created_at ASC LIMIT 1",
                'pending'
            )
        );

        if ($item) {
            // Mark as processing
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                self::get_table_name(),
                ['status' => 'processing', 'attempts' => $item->attempts + 1],
                ['id' => $item->id],
                ['%s', '%d'],
                ['%d']
            );
        }

        return $item;
    }

    /**
     * Update a queue item
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(self::get_table_name(), $data, ['id' => $id]);
        return $result !== false;
    }

    /**
     * Get queue items with pagination and filtering
     */
    public function get_items(array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'status' => '',
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];
        $args = wp_parse_args($args, $defaults);
        $table = esc_sql(self::get_table_name());
        $comments = esc_sql($wpdb->comments);
        $posts = esc_sql($wpdb->posts);

        $offset = absint(($args['page'] - 1) * $args['per_page']);
        $per_page = absint($args['per_page']);
        $allowed_orderby = ['created_at', 'processed_at', 'status'];
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $select_fields = "q.*, c.comment_content, c.comment_author, c.comment_author_email,
                          c.comment_date, p.post_title";
        $joins = "LEFT JOIN `{$comments}` c ON q.comment_id = c.comment_ID
                  LEFT JOIN `{$posts}` p ON c.comment_post_ID = p.ID";

        // All table names from esc_sql(), $orderby whitelisted, $order constrained to ASC/DESC.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if (!empty($args['status'])) {
            $query = $wpdb->prepare(
                "SELECT {$select_fields}
                 FROM `{$table}` q {$joins}
                 WHERE q.status = %s
                 ORDER BY q.`{$orderby}` {$order}
                 LIMIT %d OFFSET %d",
                $args['status'],
                $per_page,
                $offset
            );
            $count_query = $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` q WHERE q.status = %s",
                $args['status']
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT {$select_fields}
                 FROM `{$table}` q {$joins}
                 ORDER BY q.`{$orderby}` {$order}
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            );
            $count_query = "SELECT COUNT(*) FROM `{$table}` q";
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
     * Get queue statistics
     */
    public function get_stats(): array
    {
        global $wpdb;
        $table = esc_sql(self::get_table_name());

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stats = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT status, result, COUNT(*) as count FROM `{$table}` GROUP BY status, result"
        );

        $result = [
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'error' => 0,
            'approved' => 0,
            'rejected' => 0,
            'flagged' => 0,
        ];

        foreach ($stats as $row) {
            $result['total'] += $row->count;
            if (isset($result[$row->status])) {
                $result[$row->status] += $row->count;
            }
            if ($row->result && isset($result[$row->result])) {
                $result[$row->result] += $row->count;
            }
        }

        return $result;
    }

    /**
     * Retry a failed queue item
     */
    public function retry(int $id): bool
    {
        return $this->update($id, [
            'status' => 'pending',
            'result' => null,
            'reason' => null,
            'processed_at' => null,
        ]);
    }

    /**
     * Delete a queue item
     */
    public function delete(int $id): bool
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->delete(self::get_table_name(), ['id' => $id], ['%d']) !== false;
    }

    /**
     * Cleanup old completed items
     */
    public function cleanup(int $days = 30): int
    {
        global $wpdb;

        $table = esc_sql(self::get_table_name());
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$table}` WHERE status IN ('completed', 'error') AND created_at < %s",
                gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS))
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $result;
    }
}
