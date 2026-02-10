<?php
/**
 * Admin Page
 *
 * Registers admin menu, renders the React SPA mount point,
 * and handles admin notices.
 */

namespace flavor\flavor;

defined('ABSPATH') || exit;

class Admin
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
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_notices', [$this, 'show_notices']);
    }

    /**
     * Add admin menu under Comments
     */
    public function add_menu(): void
    {
        add_comments_page(
            __('AI 评论审核', 'ai-comment-moderator'),
            __('AI 审核', 'ai-comment-moderator'),
            'manage_options',
            'ai-comment-moderator',
            [$this, 'render_page']
        );
    }

    /**
     * Render the React SPA mount point
     */
    public function render_page(): void
    {
        echo '<div id="ai-comment-moderator-root"></div>';
    }

    /**
     * Enqueue React scripts and styles on our admin page
     */
    public function enqueue_scripts(string $hook): void
    {
        // Only load on our page
        if ($hook !== 'comments_page_ai-comment-moderator') {
            return;
        }

        $asset_file = FLAVOR_FLAVOR_DIR . 'build/settings.asset.php';
        $asset = file_exists($asset_file) ? require $asset_file : [
            'dependencies' => ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'],
            'version' => FLAVOR_FLAVOR_VERSION,
        ];

        wp_enqueue_script(
            'ai-comment-moderator-settings',
            FLAVOR_FLAVOR_URL . 'build/settings.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            'ai-comment-moderator-settings',
            FLAVOR_FLAVOR_URL . 'build/settings.css',
            ['wp-components'],
            $asset['version']
        );

        // Pass data to React
        $settings = get_option('flavor_flavor_settings', []);
        $provider_manager = AIProviderManager::get_instance();

        wp_localize_script('ai-comment-moderator-settings', 'aiCommentModerator', [
            'restUrl' => rest_url(FLAVOR_FLAVOR_REST_NAMESPACE),
            'nonce' => wp_create_nonce('wp_rest'),
            'settings' => $this->get_settings_for_frontend($settings),
            'providers' => $provider_manager->get_providers_info(),
            'defaultSystemPrompt' => ModerationAgent::get_instance()->get_default_system_prompt(),
            'wpModerationEnabled' => get_option('comment_moderation') === '1',
            'discussionSettingsUrl' => admin_url('options-discussion.php'),
            'version' => FLAVOR_FLAVOR_VERSION,
        ]);
    }

    /**
     * Prepare settings for frontend (hide sensitive data like full API key)
     */
    private function get_settings_for_frontend(array $settings): array
    {
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
        ];

        $merged = wp_parse_args($settings, $defaults);

        // Mask API key for display
        if (!empty($merged['api_key'])) {
            $key = $merged['api_key'];
            $merged['api_key_masked'] = substr($key, 0, 8) . '...' . substr($key, -4);
            $merged['api_key_set'] = true;
        } else {
            $merged['api_key_masked'] = '';
            $merged['api_key_set'] = false;
        }
        unset($merged['api_key']); // Don't send full key to frontend

        return $merged;
    }

    /**
     * Show admin notices
     */
    public function show_notices(): void
    {
        // Notice: WordPress comment moderation not enabled
        if (get_transient('flavor_flavor_needs_moderation')) {
            $settings = get_option('flavor_flavor_settings', []);
            $enabled = $settings['enabled'] ?? false;

            if ($enabled && get_option('comment_moderation') !== '1') {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <strong><?php esc_html_e('CommentGuard:', 'ai-comment-moderator'); ?></strong>
                        <?php
                        printf(
                            /* translators: %s: URL to discussion settings */
                            esc_html__('为了让 AI 审核正常工作，请前往 %s 并启用「评论必须经人工批准」选项。', 'ai-comment-moderator'),
                            '<a href="' . esc_url(admin_url('options-discussion.php')) . '">' . esc_html__('讨论设置', 'ai-comment-moderator') . '</a>'
                        );
                        ?>
                    </p>
                </div>
                <?php
            }
        }

        // Notice: Plugin just activated
        if (get_transient('flavor_flavor_activated')) {
            delete_transient('flavor_flavor_activated');
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php esc_html_e('CommentGuard', 'ai-comment-moderator'); ?></strong> —
                    <?php
                    printf(
                        /* translators: %s: URL to settings page */
                        esc_html__('插件已激活！请前往 %s 配置 AI 接口。', 'ai-comment-moderator'),
                        '<a href="' . esc_url(admin_url('edit-comments.php?page=ai-comment-moderator')) . '">' . esc_html__('设置页面', 'ai-comment-moderator') . '</a>'
                    );
                    ?>
                </p>
            </div>
            <?php
        }

        // Notice: re-moderation success
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['ai_remoderated'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('评论已重新加入 AI 审核队列。', 'ai-comment-moderator'); ?></p>
            </div>
            <?php
        }
    }
}
