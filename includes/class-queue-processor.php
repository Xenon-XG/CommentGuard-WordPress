<?php
/**
 * Queue Processor
 *
 * Processes the moderation queue via WP-Cron.
 * Dequeues pending comments, runs AI Agent moderation, and applies results.
 */

namespace Xenon\CommentGuard;

defined('ABSPATH') || exit;

class QueueProcessor
{
    private static $instance = null;

    /** Maximum items to process per cron run */
    const BATCH_SIZE = 5;

    /** Maximum retry attempts before marking as error */
    const MAX_ATTEMPTS = 3;

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Register custom cron interval
        add_filter('cron_schedules', [$this, 'add_cron_interval']);

        // Register cron event handler
        add_action('commentguard_process_queue', [$this, 'process']);

        // Schedule cron if not already scheduled or if interval changed
        $settings = get_option('commentguard_settings', []);
        $interval_minutes = max(1, (int) ($settings['cron_interval'] ?? 1));
        $schedule_name = 'commentguard_every_' . $interval_minutes . '_min';

        $next = wp_next_scheduled('commentguard_process_queue');
        if ($next) {
            // Check if schedule changed
            $current_schedule = wp_get_schedule('commentguard_process_queue');
            if ($current_schedule !== $schedule_name) {
                wp_clear_scheduled_hook('commentguard_process_queue');
                wp_schedule_event(time(), $schedule_name, 'commentguard_process_queue');
            }
        } else {
            wp_schedule_event(time(), $schedule_name, 'commentguard_process_queue');
        }

        // Cleanup old records twice daily
        add_action('commentguard_cleanup', [$this, 'cleanup']);
        if (!wp_next_scheduled('commentguard_cleanup')) {
            wp_schedule_event(time(), 'twicedaily', 'commentguard_cleanup');
        }
    }

    /**
     * Add custom cron interval based on settings
     */
    public function add_cron_interval(array $schedules): array
    {
        $settings = get_option('commentguard_settings', []);
        $interval_minutes = max(1, (int) ($settings['cron_interval'] ?? 1));

        $schedules['commentguard_every_' . $interval_minutes . '_min'] = [
            'interval' => $interval_minutes * 60,
            /* translators: %d: number of minutes */
            'display' => sprintf(__('Every %d Minute(s)', 'commentguard'), $interval_minutes),
        ];
        return $schedules;
    }

    /**
     * Process queue items
     */
    public function process(): void
    {
        $settings = get_option('commentguard_settings', []);
        $enabled = $settings['enabled'] ?? false;

        if (!$enabled) {
            return;
        }

        $queue = ModerationQueue::get_instance();
        $agent = ModerationAgent::get_instance();

        $processed = 0;
        while ($processed < self::BATCH_SIZE) {
            $item = $queue->dequeue();
            if (!$item) {
                break; // No more pending items
            }

            $this->process_item($item, $queue, $agent);
            $processed++;
        }
    }

    /**
     * Process a single queue item
     */
    private function process_item(object $item, ModerationQueue $queue, ModerationAgent $agent): void
    {
        $comment = get_comment($item->comment_id);
        if (!$comment) {
            // Comment was deleted
            $queue->update($item->id, [
                'status' => 'completed',
                'result' => 'rejected',
                'reason' => __('Comment was deleted', 'commentguard'),
                'processed_at' => current_time('mysql'),
            ]);
            return;
        }

        // Skip if comment was already manually moderated (only on first processing)
        // On retry (attempts > 0), reset comment to hold so AI can re-evaluate
        if ($comment->comment_approved === '1' || $comment->comment_approved === 'trash' || $comment->comment_approved === 'spam') {
            if ((int) $item->attempts <= 1) {
                // First processing — comment was moderated outside the plugin
                $queue->update($item->id, [
                    'status' => 'completed',
                    'result' => 'approved',
                    'reason' => __('Comment was already manually moderated', 'commentguard'),
                    'processed_at' => current_time('mysql'),
                ]);
                return;
            }
            // Retry — reset comment to hold so AI re-evaluates
            wp_set_comment_status($comment->comment_ID, 'hold');
        }

        // Build context for the agent
        $post = get_post($comment->comment_post_ID);
        $context = [
            'comment_content' => $comment->comment_content,
            'author_name' => $comment->comment_author,
            'author_email' => $comment->comment_author_email,
            'author_ip' => $comment->comment_author_IP,
            'post_title' => $post ? $post->post_title : '',
            'post_excerpt' => $post ? wp_trim_words(strip_shortcodes(strip_tags($post->post_content)), 100, '...') : '',
        ];

        // If it's a reply, include parent comment
        if ($comment->comment_parent) {
            $parent = get_comment($comment->comment_parent);
            if ($parent) {
                $context['parent_comment'] = $parent->comment_content;
            }
        }

        // Run AI Agent moderation
        $result = $agent->moderate($context);

        if (!$result['success']) {
            // Check if max attempts reached
            $attempts = ((int) $item->attempts) + 1;
            if ($attempts >= self::MAX_ATTEMPTS) {
                $queue->update($item->id, [
                    'status' => 'error',
                    'reason' => $result['error'] ?? __('Max attempts reached', 'commentguard'),
                    'processed_at' => current_time('mysql'),
                ]);
            } else {
                // Reset to pending for retry
                $queue->update($item->id, [
                    'status' => 'pending',
                ]);
            }
            return;
        }

        // Apply moderation result
        $this->apply_result($comment, $result);

        // Determine provider/model info
        $settings = get_option('commentguard_settings', []);

        // Update queue item
        $queue->update($item->id, [
            'status' => 'completed',
            'result' => $result['action'] === 'approve' ? 'approved' : ($result['action'] === 'reject' ? 'rejected' : 'flagged'),
            'reason' => $result['reason'],
            'ai_provider' => $settings['ai_provider'] ?? 'openai',
            'ai_model' => $settings['ai_model'] ?? '',
            'processed_at' => current_time('mysql'),
        ]);

        // Log to audit log if enabled
        $log_enabled = $settings['audit_log_enabled'] ?? false;
        if ($log_enabled) {
            AuditLog::get_instance()->log(
                $item->comment_id,
                $result['action'],
                $result['reason'],
                [
                    'ai_provider' => $settings['ai_provider'] ?? 'openai',
                    'ai_model' => $settings['ai_model'] ?? '',
                    'usage' => $result['usage'],
                ]
            );
        }
    }

    /**
     * Apply the moderation result to the comment
     */
    private function apply_result(\WP_Comment $comment, array $result): void
    {
        switch ($result['action']) {
            case 'approve':
                wp_set_comment_status($comment->comment_ID, 'approve');
                // Store AI moderation meta
                update_comment_meta($comment->comment_ID, '_ai_moderation_result', 'approved');
                update_comment_meta($comment->comment_ID, '_ai_moderation_reason', $result['reason']);
                break;

            case 'reject':
                wp_set_comment_status($comment->comment_ID, 'trash');
                update_comment_meta($comment->comment_ID, '_ai_moderation_result', 'rejected');
                update_comment_meta($comment->comment_ID, '_ai_moderation_reason', $result['reason']);
                break;

            case 'flag':
            default:
                // Keep as pending (hold) but mark as flagged
                update_comment_meta($comment->comment_ID, '_ai_moderation_result', 'flagged');
                update_comment_meta($comment->comment_ID, '_ai_moderation_reason', $result['reason']);
                break;
        }
    }

    /**
     * Cleanup old records
     */
    public function cleanup(): void
    {
        $settings = get_option('commentguard_settings', []);
        $days = $settings['cleanup_days'] ?? 30;

        ModerationQueue::get_instance()->cleanup($days);
        AuditLog::get_instance()->cleanup($days);
    }
}
