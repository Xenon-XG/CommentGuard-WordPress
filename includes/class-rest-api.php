<?php
/**
 * REST API
 *
 * Provides REST API endpoints for the React SPA settings page.
 */

namespace Xenon\CommentGuard;

defined('ABSPATH') || exit;

class RestAPI
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
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Check if current user is admin
     */
    public function check_permission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void
    {
        $namespace = COMMENTGUARD_REST_NAMESPACE;

        // Settings
        register_rest_route($namespace, '/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'save_settings'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        // Queue
        register_rest_route($namespace, '/queue', [
            'methods' => 'GET',
            'callback' => [$this, 'get_queue'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($namespace, '/queue/retry/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'retry_queue_item'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($namespace, '/queue/delete/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_queue_item'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Batch queue operations
        register_rest_route($namespace, '/queue/batch', [
            'methods' => 'POST',
            'callback' => [$this, 'batch_queue_action'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Stats
        register_rest_route($namespace, '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Logs
        register_rest_route($namespace, '/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_logs'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Clear all logs
        register_rest_route($namespace, '/logs/clear', [
            'methods' => 'DELETE',
            'callback' => [$this, 'clear_logs'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Batch delete logs
        register_rest_route($namespace, '/logs/batch', [
            'methods' => 'DELETE',
            'callback' => [$this, 'batch_delete_logs'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Test API connection
        register_rest_route($namespace, '/test', [
            'methods' => 'POST',
            'callback' => [$this, 'test_connection'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Manual trigger queue processing
        register_rest_route($namespace, '/process', [
            'methods' => 'POST',
            'callback' => [$this, 'trigger_process'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    /**
     * GET /settings
     */
    public function get_settings(): \WP_REST_Response
    {
        $settings = get_option('commentguard_settings', []);
        
        $defaults = [
            'enabled' => false,
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
            'api_key' => '',
            'api_base_url' => '',
            'system_prompt' => '',
            'skip_admins' => true,
            'auto_queue_approved' => false,
            'audit_log_enabled' => false,
            'cleanup_days' => 30,
            'cron_interval' => 1,
            'ui_language' => 'en',
            'ui_language_name' => 'English',
        ];

        $merged = wp_parse_args($settings, $defaults);

        // Mask API key
        if (!empty($merged['api_key'])) {
            $merged['api_key_masked'] = substr($merged['api_key'], 0, 8) . '...' . substr($merged['api_key'], -4);
            $merged['api_key_set'] = true;
        } else {
            $merged['api_key_masked'] = '';
            $merged['api_key_set'] = false;
        }
        unset($merged['api_key']);

        return new \WP_REST_Response($merged, 200);
    }

    /**
     * POST /settings
     */
    public function save_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $current = get_option('commentguard_settings', []);

        $allowed_keys = [
            'enabled', 'ai_provider', 'ai_model', 'api_key',
            'api_base_url', 'system_prompt', 'skip_admins',
            'auto_queue_approved', 'audit_log_enabled', 'cleanup_days',
            'cron_interval', 'ui_language', 'ui_language_name',
        ];

        foreach ($allowed_keys as $key) {
            if (isset($params[$key])) {
                $current[$key] = $this->sanitize_setting($key, $params[$key]);
            }
        }

        // If api_key is empty string or not provided, keep old key
        if (isset($params['api_key']) && $params['api_key'] === '') {
            // User explicitly cleared the key
            $current['api_key'] = '';
        } elseif (!isset($params['api_key'])) {
            // Key not sent (masked on frontend), keep existing
        }

        update_option('commentguard_settings', $current);

        // Update moderation notice transient
        if ($current['enabled'] && get_option('comment_moderation') !== '1') {
            set_transient('commentguard_needs_moderation', true, 0);
        } else {
            delete_transient('commentguard_needs_moderation');
        }

        // Build frontend-safe settings (mask API key)
        $frontend = $current;
        if (!empty($frontend['api_key'])) {
            $key = $frontend['api_key'];
            $frontend['api_key_masked'] = substr($key, 0, 8) . '...' . substr($key, -4);
            $frontend['api_key_set'] = true;
        } else {
            $frontend['api_key_masked'] = '';
            $frontend['api_key_set'] = false;
        }
        unset($frontend['api_key']);

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('设置已保存', 'commentguard'),
            'settings' => $frontend,
        ], 200);
    }

    /**
     * Sanitize a setting value
     */
    private function sanitize_setting(string $key, $value)
    {
        switch ($key) {
            case 'enabled':
            case 'skip_admins':
            case 'auto_queue_approved':
            case 'audit_log_enabled':
                return (bool) $value;

            case 'cleanup_days':
                return max(1, min(365, absint($value)));

            case 'cron_interval':
                return max(1, min(60, absint($value)));

            case 'api_key':
                return sanitize_text_field($value);

            case 'api_base_url':
                return esc_url_raw($value);

            case 'system_prompt':
                return sanitize_textarea_field($value);

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * GET /queue
     */
    public function get_queue(\WP_REST_Request $request): \WP_REST_Response
    {
        $queue = ModerationQueue::get_instance();
        $result = $queue->get_items([
            'status' => $request->get_param('status') ?: '',
            'per_page' => $request->get_param('per_page') ?: 20,
            'page' => $request->get_param('page') ?: 1,
        ]);

        return new \WP_REST_Response($result, 200);
    }

    /**
     * POST /queue/retry/{id}
     */
    public function retry_queue_item(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $queue = ModerationQueue::get_instance();
        $success = $queue->retry($id);

        return new \WP_REST_Response([
            'success' => $success,
            'message' => $success ? __('已加入重试队列', 'commentguard') : __('操作失败', 'commentguard'),
        ], $success ? 200 : 400);
    }

    /**
     * DELETE /queue/delete/{id}
     */
    public function delete_queue_item(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $queue = ModerationQueue::get_instance();
        $success = $queue->delete($id);

        return new \WP_REST_Response([
            'success' => $success,
        ], $success ? 200 : 400);
    }

    /**
     * POST /queue/batch — Batch queue operations (delete or retry)
     */
    public function batch_queue_action(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $ids = isset($params['ids']) ? array_map('absint', (array) $params['ids']) : [];
        $action = isset($params['action']) ? sanitize_text_field($params['action']) : '';

        if (empty($ids) || !in_array($action, ['delete', 'retry'], true)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('参数无效', 'commentguard'),
            ], 400);
        }

        $queue = ModerationQueue::get_instance();
        $success_count = 0;

        foreach ($ids as $id) {
            if ($action === 'delete') {
                if ($queue->delete($id)) {
                    $success_count++;
                }
            } elseif ($action === 'retry') {
                if ($queue->retry($id)) {
                    $success_count++;
                }
            }
        }

        return new \WP_REST_Response([
            'success' => $success_count > 0,
            /* translators: %d: number of successfully processed items */
            'message' => sprintf(__('成功处理 %d 条记录', 'commentguard'), $success_count),
            'count' => $success_count,
        ], 200);
    }

    /**
     * DELETE /logs/batch — Batch delete audit logs
     */
    public function batch_delete_logs(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $ids = isset($params['ids']) ? array_map('absint', (array) $params['ids']) : [];

        if (empty($ids)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('参数无效', 'commentguard'),
            ], 400);
        }

        $audit_log = AuditLog::get_instance();
        $deleted = $audit_log->delete_by_ids($ids);

        return new \WP_REST_Response([
            'success' => $deleted > 0,
            /* translators: %d: number of deleted log entries */
            'message' => sprintf(__('成功删除 %d 条日志', 'commentguard'), $deleted),
            'count' => $deleted,
        ], 200);
    }

    /**
     * GET /stats
     */
    public function get_stats(): \WP_REST_Response
    {
        $queue = ModerationQueue::get_instance();
        return new \WP_REST_Response($queue->get_stats(), 200);
    }

    /**
     * GET /logs
     */
    public function get_logs(\WP_REST_Request $request): \WP_REST_Response
    {
        $audit_log = AuditLog::get_instance();
        $result = $audit_log->get_logs([
            'action' => $request->get_param('action') ?: '',
            'per_page' => $request->get_param('per_page') ?: 20,
            'page' => $request->get_param('page') ?: 1,
        ]);

        return new \WP_REST_Response($result, 200);
    }

    /**
     * POST /test
     */
    public function test_connection(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $provider_id = $params['provider'] ?? 'openai';
        $api_key = $params['api_key'] ?? '';
        $base_url = $params['base_url'] ?? '';

        // If no key provided, use saved key
        if (empty($api_key)) {
            $settings = get_option('commentguard_settings', []);
            $api_key = $settings['api_key'] ?? '';
        }

        if (empty($api_key)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('请提供 API Key', 'commentguard'),
            ], 400);
        }

        $provider_manager = AIProviderManager::get_instance();
        $provider = $provider_manager->get_provider($provider_id);

        if (!$provider) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('未知的 AI 提供商', 'commentguard'),
            ], 400);
        }

        $options = [];
        if (!empty($base_url)) {
            $options['base_url'] = $base_url;
        }

        $result = $provider->validate_api_key($api_key, $options);

        return new \WP_REST_Response([
            'success' => $result['valid'],
            'message' => $result['valid']
                ? __('API 连接测试成功！', 'commentguard')
                /* translators: %s: error message from API */
                : sprintf(__('连接失败: %s', 'commentguard'), $result['error']),
        ], $result['valid'] ? 200 : 400);
    }

    /**
     * POST /process - Manually trigger queue processing
     */
    public function trigger_process(): \WP_REST_Response
    {
        $processor = QueueProcessor::get_instance();
        $processor->process();

        $queue = ModerationQueue::get_instance();
        $stats = $queue->get_stats();

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('队列处理已触发', 'commentguard'),
            'stats' => $stats,
        ], 200);
    }

    /**
     * DELETE /logs/clear - Clear all audit logs
     */
    public function clear_logs(): \WP_REST_Response
    {
        $audit_log = AuditLog::get_instance();
        $deleted = $audit_log->clear_all();

        return new \WP_REST_Response([
            'success' => true,
            'deleted' => $deleted,
            'message' => __('日志已清除', 'commentguard'),
        ], 200);
    }
}
