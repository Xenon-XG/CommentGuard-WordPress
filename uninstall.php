<?php
/**
 * Uninstall script — runs when the plugin is deleted from WordPress.
 * Removes all database tables, options, transients, and cron events.
 */

// Abort if not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// 1. Drop database tables
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_comment_queue");
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_comment_audit_log");

// 2. Delete options
delete_option('commentguard_settings');
delete_option('commentguard_db_version');

// 3. Delete transients
delete_transient('commentguard_activated');
delete_transient('commentguard_needs_moderation');

// 4. Clear scheduled cron events
wp_clear_scheduled_hook('commentguard_process_queue');
wp_clear_scheduled_hook('commentguard_cleanup');
