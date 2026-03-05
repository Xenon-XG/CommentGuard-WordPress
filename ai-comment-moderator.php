<?php
/**
 * Plugin Name:       CommentGuard
 * Description:       基于 AI Agent 模式的智能评论审核 WordPress 插件。支持 OpenAI，可扩展至其他 AI 提供商。自动批准、拒绝或标记评论。
 * Version:           1.1.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Xenon
 * Text Domain:       commentguard
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace flavor\flavor;

defined('ABSPATH') || exit;

// Plugin constants
define('FLAVOR_FLAVOR_VERSION', '1.1.1');
define('FLAVOR_FLAVOR_FILE', __FILE__);
define('FLAVOR_FLAVOR_DIR', plugin_dir_path(__FILE__));
define('FLAVOR_FLAVOR_URL', plugin_dir_url(__FILE__));
define('FLAVOR_FLAVOR_BASENAME', plugin_basename(__FILE__));
define('FLAVOR_FLAVOR_REST_NAMESPACE', 'ai-moderator/v1');
define('FLAVOR_FLAVOR_DB_VERSION', '1.0.0');
define('FLAVOR_FLAVOR_SLUG', 'commentguard');

// Autoload classes via classmap
spl_autoload_register(function ($class) {
    $prefix = 'flavor\\flavor\\';
    $base_dir = FLAVOR_FLAVOR_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);

    // Explicit classmap to avoid acronym conversion issues
    $classmap = [
        'AIProviderInterface' => 'class-ai-provider-interface.php',
        'OpenAIProvider'      => 'class-openai-provider.php',
        'AIProviderManager'   => 'class-ai-provider-manager.php',
        'ModerationAgent'     => 'class-moderation-agent.php',
        'ModerationQueue'     => 'class-moderation-queue.php',
        'QueueProcessor'      => 'class-queue-processor.php',
        'CommentHooks'        => 'class-comment-hooks.php',
        'Admin'               => 'class-admin.php',
        'RestAPI'             => 'class-rest-api.php',
        'AuditLog'            => 'class-audit-log.php',
    ];

    if (isset($classmap[$relative_class])) {
        $file = $base_dir . $classmap[$relative_class];
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

/**
 * Plugin activation
 */
register_activation_hook(__FILE__, function () {
    // Create database tables
    ModerationQueue::create_table();
    AuditLog::create_table();

    // Set a flag to show welcome notice
    set_transient('flavor_flavor_activated', true, 30);

    // Check if WordPress comment moderation is enabled
    if (get_option('comment_moderation') !== '1') {
        set_transient('flavor_flavor_needs_moderation', true, 0);
    }
});

/**
 * Plugin deactivation
 */
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('flavor_flavor_process_queue');
});

/**
 * Initialize plugin
 */
add_action('plugins_loaded', function () {
    // Initialize core components
    AIProviderManager::get_instance();
    ModerationAgent::get_instance();
    ModerationQueue::get_instance();
    QueueProcessor::get_instance();
    CommentHooks::get_instance();
    Admin::get_instance();
    RestAPI::get_instance();
    AuditLog::get_instance();
});
