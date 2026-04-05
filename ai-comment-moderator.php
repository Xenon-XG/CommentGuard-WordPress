<?php
/**
 * Plugin Name:       CommentGuard
 * Description:       基于 AI Agent 模式的智能评论审核 WordPress 插件。支持 OpenAI，可扩展至其他 AI 提供商。自动批准、拒绝或标记评论。
 * Version:           1.1.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Xenon
 * Text Domain:       commentguard
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Xenon\CommentGuard;

defined('ABSPATH') || exit;

// Plugin constants
define('COMMENTGUARD_VERSION', '1.1.3');
define('COMMENTGUARD_FILE', __FILE__);
define('COMMENTGUARD_DIR', plugin_dir_path(__FILE__));
define('COMMENTGUARD_URL', plugin_dir_url(__FILE__));
define('COMMENTGUARD_BASENAME', plugin_basename(__FILE__));
define('COMMENTGUARD_REST_NAMESPACE', 'ai-moderator/v1');
define('COMMENTGUARD_DB_VERSION', '1.0.0');
define('COMMENTGUARD_SLUG', 'commentguard');

// Autoload classes via classmap
spl_autoload_register(function ($class) {
    $prefix = 'Xenon\\CommentGuard\\';
    $base_dir = COMMENTGUARD_DIR . 'includes/';

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
 * Initialize default settings if not exists or add new fields
 */
function init_default_settings() {
    $settings = get_option('commentguard_settings');
    $needs_update = false;

    if (!is_array($settings)) {
        $settings = [];
        $needs_update = true;
    }

    if (!isset($settings['ui_language'])) {
        $wp_locale = get_locale();
        $supported_languages = [
            'en' => 'English',
            'zh' => '简体中文',
        ];
        
        $default_lang = 'en';
        $default_lang_name = $supported_languages['en'];
        
        $locale_prefix = substr($wp_locale, 0, 2);
        if (isset($supported_languages[$locale_prefix])) {
            $default_lang = $locale_prefix;
            $default_lang_name = $supported_languages[$locale_prefix];
        }

        $settings['ui_language'] = $default_lang;
        $settings['ui_language_name'] = $default_lang_name;
        $needs_update = true;
    }

    if ($needs_update) {
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
        $settings = wp_parse_args($settings, $defaults);
        update_option('commentguard_settings', $settings);
    }
}

/**
 * Plugin activation
 */
register_activation_hook(__FILE__, function () {
    // Create database tables
    ModerationQueue::create_table();
    AuditLog::create_table();

    // Set a flag to show welcome notice
    set_transient('commentguard_activated', true, 30);

    // Check if WordPress comment moderation is enabled
    if (get_option('comment_moderation') !== '1') {
        set_transient('commentguard_needs_moderation', true, 0);
    }
    
    init_default_settings();
});

/**
 * Plugin deactivation
 */
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('commentguard_process_queue');
});

/**
 * Initialize plugin
 */
add_action('plugins_loaded', function () {
    // Check for DB updates
    $installed_ver = get_option('commentguard_db_version');
    if ($installed_ver !== COMMENTGUARD_DB_VERSION) {
        \Xenon\CommentGuard\ModerationQueue::create_table();
        \Xenon\CommentGuard\AuditLog::create_table();
        update_option('commentguard_db_version', COMMENTGUARD_DB_VERSION);
    }
    
    init_default_settings();

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
